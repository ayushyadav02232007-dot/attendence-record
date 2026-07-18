<?php
session_start();
// Security Check: Kick out anyone who isn't logged in as a teacher
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit();
}

$db = new PDO('sqlite:portal.db');
$message = "";

// Handle Attendance Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mark_attendance'])) {
    $date = $_POST['attendance_date'];
    $statuses = $_POST['status']; // Array of student_id => status

    try {
        $db->beginTransaction();
        foreach ($statuses as $student_id => $status) {
            // Check if a record already exists for this student on this date
            $check_stmt = $db->prepare("SELECT attendance_id FROM attendance WHERE student_id = ? AND date = ?");
            $check_stmt->execute([$student_id, $date]);
            $existing = $check_stmt->fetch();

            if ($existing) {
                // Update existing record
                $update_stmt = $db->prepare("UPDATE attendance SET status = ? WHERE attendance_id = ?");
                $update_stmt->execute([$status, $existing['attendance_id']]);
            } else {
                // Insert new record
                $insert_stmt = $db->prepare("INSERT INTO attendance (student_id, date, status) VALUES (?, ?, ?)");
                $insert_stmt->execute([$student_id, $date, $status]);
            }
        }
        $db->commit();
        $message = "<div style='color: green; margin-bottom: 15px;'>Attendance updated successfully for $date!</div>";
    } catch (Exception $e) {
        $db->rollBack();
        $message = "<div style='color: red; margin-bottom: 15px;'>Error updating attendance.</div>";
    }
}

// Fetch all profiles designated as students
$students_stmt = $db->query("SELECT student_id, full_name, username FROM students WHERE role = 'student' ORDER BY full_name ASC");
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Panel - Manage Attendance</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="dashboard-card" style="max-width: 700px;">
        <div class="dashboard-header">
            <h2>Teacher Panel: <?php echo htmlspecialchars($_SESSION['full_name']); ?></h2>
            <a href="logout.php" class="logout-link">Sign Out</a>
        </div>

        <?php echo $message; ?>

        <form method="POST" action="teacher.php">
            <div class="input-group" style="max-width: 250px; margin-bottom: 25px;">
                <label for="attendance_date">Select Date</label>
                <input type="date" id="attendance_date" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <h3 class="table-title">Student Roster</h3>
            <table class="attendance-table" style="margin-bottom: 20px;">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Username</th>
                        <th>Status Mapping</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($students) > 0): ?>
                        <?php foreach($students as $student): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td>`<?php echo htmlspecialchars($student['username']); ?>`</td>
                            <td>
                                <label style="margin-right: 15px; font-weight: normal; cursor:pointer;">
                                    <input type="radio" name="status[<?php echo $student['student_id']; ?>]" value="Present" checked> Present
                                </label>
                                <label style="font-weight: normal; cursor:pointer;">
                                    <input type="radio" name="status[<?php echo $student['student_id']; ?>]" value="Absent"> Absent
                                </label>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: #999;">No students registered in the system database yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <button type="submit" name="mark_attendance" class="btn-primary" style="max-width: 200px;">Submit Attendance</button>
        </form>
    </div>

</body>
</html>
