<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "school_portal");
$student_id = $_SESSION['student_id'];

// Fetch Total Sessions
$total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendance WHERE student_id = ?");
$total_stmt->bind_param("i", $student_id);
$total_stmt->execute();
$total_days = $total_stmt->get_result()->fetch_assoc()['total'];

// Fetch Attended Sessions
$present_stmt = $conn->prepare("SELECT COUNT(*) as present FROM attendance WHERE student_id = ? AND status = 'Present'");
$present_stmt->bind_param("i", $student_id);
$present_stmt->execute();
$present_days = $present_stmt->get_result()->fetch_assoc()['present'];

// Compute Percentage Rate
$percentage = ($total_days > 0) ? round(($present_days / $total_days) * 100, 1) : 0;

// Fetch Recent History Logs
$log_stmt = $conn->prepare("SELECT date, status FROM attendance WHERE student_id = ? ORDER BY date DESC LIMIT 8");
$log_stmt->bind_param("i", $student_id);
$log_stmt->execute();
$logs = $log_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="dashboard-card">
        <div class="dashboard-header">
            <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h2>
            <a href="logout.php" class="logout-link">Sign Out</a>
        </div>

        <div class="metrics-grid">
            <div class="metric-box">
                <h3>Total Classes</h3>
                <div class="value"><?php echo $total_days; ?></div>
            </div>
            <div class="metric-box">
                <h3>Days Present</h3>
                <div class="value"><?php echo $present_days; ?></div>
            </div>
            <div class="metric-box">
                <h3>Attendance Rate</h3>
                <div class="value percentage"><?php echo $percentage; ?>%</div>
            </div>
        </div>

        <h3 class="table-title">Recent Attendance Log</h3>
        <table class="attendance-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Status Mapping</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs->num_rows > 0): ?>
                    <?php while($row = $logs->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date("F j, Y", strtotime($row['date'])); ?></td>
                        <td>
                            <span class="status-badge <?php echo $row['status']; ?>">
                                <?php echo $row['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2" style="text-align: center; color: #999;">No attendance records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>
<?php
$total_stmt->close();
$present_stmt->close();
$log_stmt->close();
$conn->close();
?>