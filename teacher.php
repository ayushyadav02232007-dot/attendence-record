<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit();
}

//$db = new PDO('sqlite:portal.db');
// Saves the database in a secure cloud storage folder that never wipes out
$db = new PDO('sqlite:/var/www/html/data/portal.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$message = "";

// Keep track of active view tab via URL query parameters (?tab=dashboard or ?tab=register)
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// 1. Handle Adding a New Student
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_student'])) {
    $new_name = trim($_POST['new_student_name']);
    $new_user = trim($_POST['new_username']);
    $new_pass = password_hash(trim($_POST['new_password']), PASSWORD_DEFAULT);
    $current_tab = 'register';

    try {
        $check_stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE username = ?");
        $check_stmt->execute([$new_user]);
        if ($check_stmt->fetchColumn() > 0) {
            $message = "<div class='alert error'>Error: Username '$new_user' is already taken.</div>";
        } else {
            $stmt = $db->prepare("INSERT INTO students (username, password, full_name, role) VALUES (?, ?, ?, 'student')");
            $stmt->execute([$new_user, $new_pass, $new_name]);
            $message = "<div class='alert success'>Student '$new_name' registered successfully!</div>";
        }
    } catch (Exception $e) {
        $message = "<div class='alert error'>Error: System could not register student.</div>";
    }
}

// 2. Handle Adding a New Subject
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_subject'])) {
    $new_sub = trim($_POST['new_subject_name']);
    $current_tab = 'register';
    
    if (!empty($new_sub)) {
        try {
            $stmt = $db->prepare("INSERT INTO subjects (subject_name) VALUES (?)");
            $stmt->execute([$new_sub]);
            $message = "<div class='alert success'>Subject '$new_sub' added successfully!</div>";
        } catch (Exception $e) {
            $message = "<div class='alert error'>Subject already exists.</div>";
        }
    }
}

// 3. Handle REMOVING a Student
if (isset($_GET['delete_student'])) {
    $del_student_id = intval($_GET['delete_student']);
    $current_tab = 'register';
    try {
        $db->beginTransaction();
        $stmt1 = $db->prepare("DELETE FROM attendance WHERE student_id = ?");
        $stmt1->execute([$del_student_id]);
        $stmt2 = $db->prepare("DELETE FROM students WHERE student_id = ? AND role = 'student'");
        $stmt2->execute([$del_student_id]);
        $db->commit();
        $message = "<div class='alert success'>Student profile and attendance history removed successfully.</div>";
    } catch (Exception $e) {
        $db->rollBack();
        $message = "<div class='alert error'>Error removing student.</div>";
    }
}

// 4. Handle REMOVING a Subject Module
if (isset($_GET['delete_subject'])) {
    $del_subject_name = $_GET['delete_subject'];
    $current_tab = 'register';
    try {
        $db->beginTransaction();
        $stmt1 = $db->prepare("DELETE FROM attendance WHERE subject = ?");
        $stmt1->execute([$del_subject_name]);
        $stmt2 = $db->prepare("DELETE FROM subjects WHERE subject_name = ?");
        $stmt2->execute([$del_subject_name]);
        $db->commit();
        $message = "<div class='alert success'>Subject module '$del_subject_name' removed successfully.</div>";
    } catch (Exception $e) {
        $db->rollBack();
        $message = "<div class='alert error'>Error removing subject module.</div>";
    }
}

// 5. Handle Attendance Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mark_attendance'])) {
    $date = $_POST['attendance_date'];
    $subject = $_POST['subject']; 
    $statuses = isset($_POST['status']) ? $_POST['status'] : [];
    $current_tab = 'dashboard';

    try {
        $db->beginTransaction();
        foreach ($statuses as $student_id => $status) {
            $check_stmt = $db->prepare("SELECT attendance_id FROM attendance WHERE student_id = ? AND date = ? AND subject = ?");
            $check_stmt->execute([$student_id, $date, $subject]);
            $existing = $check_stmt->fetch();

            if ($existing) {
                $update_stmt = $db->prepare("UPDATE attendance SET status = ? WHERE attendance_id = ?");
                $update_stmt->execute([$status, $existing['attendance_id']]);
            } else {
                $insert_stmt = $db->prepare("INSERT INTO attendance (student_id, date, subject, status) VALUES (?, ?, ?, ?)");
                $insert_stmt->execute([$student_id, $date, $subject, $status]);
            }
        }
        $db->commit();
        $message = "<div class='alert success'>Attendance updated successfully for $subject on $date!</div>";
    } catch (Exception $e) {
        $db->rollBack();
        $message = "<div class='alert error'>Error saving records: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Fetch database records
$subject_list = $db->query("SELECT subject_name FROM subjects ORDER BY subject_name ASC")->fetchAll(PDO::FETCH_COLUMN);
$students = $db->query("SELECT student_id, full_name, username FROM students WHERE role = 'student' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Panel - Management</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Top Navigation Menu Bar Styling */
        .navbar {
            display: flex;
            background-color: #1976d2;
            padding: 0 10px;
            border-radius: 6px 6px 0 0;
            margin-bottom: 20px;
        }
        .navbar a {
            color: white;
            padding: 14px 20px;
            text-decoration: none;
            font-weight: bold;
            font-size: 15px;
            transition: background 0.2s ease;
        }
        .navbar a:hover {
            background-color: #1565c0;
        }
        .navbar a.active {
            background-color: #0d47a1;
            border-bottom: 3px solid #64b5f6;
        }
        .navbar .logout-item {
            margin-left: auto;
            color: #ffcdd2;
        }
        .navbar .logout-item:hover {
            background-color: #d32f2f;
            color: white;
        }
        
        /* Message Boxes */
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: bold;
        }
        .alert.success { color: #2e7d32; background: #e8f5e9; border-left: 5px solid #2e7d32; }
        .alert.error { color: #d32f2f; background: #ffebee; border-left: 5px solid #d32f2f; }

        /* Utility Layouts */
        .flex-container { display: flex; gap: 25px; }
        .pane-card { flex: 1; background: #fafafa; border: 1px solid #e0e0e0; padding: 20px; border-radius: 6px; }
        .badge { background:#e0e0e0; padding:4px 10px; border-radius:12px; font-size:13px; display:inline-flex; align-items:center; gap:6px; margin: 4px; }
        .badge a { text-decoration:none; color:#c62828; font-weight:bold; font-size:11px; }
        .remove-btn { color: #d32f2f; text-decoration: none; font-size: 12px; font-weight:bold; background:#ffebee; padding:5px 10px; border-radius:4px; transition: 0.2s; }
        .remove-btn:hover { background: #d32f2f; color: white; }
    </style>
</head>
<body>

    <div class="dashboard-card" style="max-width: 850px; margin: 30px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
        
        <!-- Header banner info -->
        <div style="padding: 5px 0 15px 0; border-bottom: 1px solid #eee; margin-bottom: 15px;">
            <h2 style="margin:0; color:#222;">Teacher Control Station</h2>
        </div>

        <!-- Navigation Menu Bar -->
        <div class="navbar">
            <a href="teacher.php?tab=dashboard" class="<?php echo ($current_tab === 'dashboard') ? 'active' : ''; ?>">Attendance Dashboard</a>
            <a href="teacher.php?tab=register" class="<?php echo ($current_tab === 'register') ? 'active' : ''; ?>">Register & Manage Menu</a>
            <a href="logout.php" class="logout-item">Sign Out</a>
        </div>

        <?php if (!empty($message)) echo $message; ?>

        <!-- ================= TAB 1: ATTENDANCE DASHBOARD UPDATE VIEW ================= -->
        <?php if ($current_tab === 'dashboard'): ?>
            <form method="POST" action="teacher.php?tab=dashboard">
                <div style="display: flex; gap: 20px; margin-bottom: 25px; background: #fcfcfc; padding: 15px; border-radius: 6px; border: 1px solid #eee;">
                    <div class="input-group" style="flex: 1;">
                        <label style="display: block; margin-bottom: 6px; font-weight: bold;">Select Target Date</label>
                        <input type="date" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" required style="width: 95%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    </div>

                    <div class="input-group" style="flex: 1;">
                        <label style="display: block; margin-bottom: 6px; font-weight: bold;">Select Subject Module</label>
                        <select name="subject" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; background: white; height: 38px;">
                            <?php foreach ($subject_list as $sub): ?>
                                <option value="<?php echo htmlspecialchars($sub); ?>"><?php echo htmlspecialchars($sub); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <h3 class="table-title" style="margin-bottom: 12px; color: #333;">Student Class Roster</h3>
                <table class="attendance-table" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                    <thead>
                        <tr style="background-color: #f5f5f5; text-align: left;">
                            <th style="padding: 12px; border-bottom: 2px solid #ddd;">Student Name</th>
                            <th style="padding: 12px; border-bottom: 2px solid #ddd;">System Username</th>
                            <th style="padding: 12px; border-bottom: 2px solid #ddd;">Roster Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($students) > 0): ?>
                            <?php foreach($students as $student): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px; font-weight:500;"><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td style="padding: 12px; color: #666;"><code><?php echo htmlspecialchars($student['username']); ?></code></td>
                                <td style="padding: 12px;">
                                    <label style="margin-right: 20px; cursor:pointer; font-weight:500;"><input type="radio" name="status[<?php echo $student['student_id']; ?>]" value="Present" checked> Present</label>
                                    <label style="cursor:pointer; font-weight:500; color:#c62828;"><input type="radio" name="status[<?php echo $student['student_id']; ?>]" value="Absent"> Absent</label>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 30px; color: #999;">No active students tracked yet. Go to the "Register & Manage Menu" tab to register students.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (count($students) > 0): ?>
                    <button type="submit" name="mark_attendance" class="btn-primary" style="background: #1976d2; color: white; border: none; padding: 12px 24px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size:14px;">Save Attendance Updates</button>
                <?php endif; ?>
            </form>

        <!-- ================= TAB 2: REGISTER & REMOVE MANAGEMENT MODULE ================= -->
        <?php elseif ($current_tab === 'register'): ?>
            <div class="flex-container">
                
                <!-- Left Column: Add / Remove Student Accounts -->
                <div class="pane-card">
                    <h3 style="margin-top:0; color:#1976d2; border-bottom: 2px solid #e3f2fd; padding-bottom: 8px;">Student Management</h3>
                    
                    <!-- Form to Add Student -->
                    <form method="POST" action="teacher.php?tab=register" style="margin-bottom: 25px; background: #fff; padding: 12px; border-radius: 4px; border: 1px dashed #ccc;">
                        <h4 style="margin-top:0; color:#555;">Add New Student Account</h4>
                        <input type="text" name="new_student_name" placeholder="Full Profile Name" required style="width:92%; padding:7px; margin-bottom:8px; border:1px solid #ccc; border-radius:4px;"><br>
                        <input type="text" name="new_username" placeholder="Login Username" required style="width:92%; padding:7px; margin-bottom:8px; border:1px solid #ccc; border-radius:4px;"><br>
                        <input type="password" name="new_password" placeholder="System Password" required style="width:92%; padding:7px; margin-bottom:12px; border:1px solid #ccc; border-radius:4px;"><br>
                        <button type="submit" name="add_student" style="background:#2e7d32; color:white; border:none; padding:7px 14px; border-radius:4px; cursor:pointer; font-weight:bold; width:100%;">Create Account</button>
                    </form>

                    <!-- Roster Removal Directory -->
                    <h4 style="color:#555; margin-bottom:10px;">Registered Student Directory</h4>
                    <div style="max-height: 250px; overflow-y: auto; border: 1px solid #eee; background:#fff; padding: 5px; border-radius:4px;">
                        <table style="width:100%; border-collapse: collapse; font-size: 13px;">
                            <?php if (count($students) > 0): ?>
                                <?php foreach ($students as $st): ?>
                                <tr style="border-bottom: 1px solid #f5f5f5;">
                                    <td style="padding: 8px 4px;"><strong><?php echo htmlspecialchars($st['full_name']); ?></strong> (<?php echo htmlspecialchars($st['username']); ?>)</td>
                                    <td style="text-align: right; padding-right:4px;">
                                        <a href="teacher.php?tab=register&delete_student=<?php echo $st['student_id']; ?>" onclick="return confirm('Permanently remove this student profile?');" class="remove-btn">Remove</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td style="padding:10px; text-align:center; color:#999;">No student items present.</td></tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <!-- Right Column: Add / Remove Course Subjects -->
                <div class="pane-card">
                    <h3 style="margin-top:0; color:#0288d1; border-bottom: 2px solid #e1f5fe; padding-bottom: 8px;">Subject Curriculum Management</h3>
                    
                    <!-- Form to Add Subject -->
                    <form method="POST" action="teacher.php?tab=register" style="margin-bottom: 25px; background: #fff; padding: 12px; border-radius: 4px; border: 1px dashed #ccc;">
                        <h4 style="margin-top:0; color:#555;">Add New Subject Module</h4>
                        <input type="text" name="new_subject_name" placeholder="e.g. Data Structures, Network Security" required style="width:92%; padding:7px; margin-bottom:12px; border:1px solid #ccc; border-radius:4px;"><br>
                        <button type="submit" name="add_subject" style="background:#0288d1; color:white; border:none; padding:7px 14px; border-radius:4px; cursor:pointer; font-weight:bold; width:100%;">Insert Subject</button>
                    </form>

                    <!-- Active Subjects Badges -->
                    <h4 style="margin-bottom:10px; color:#555;">Active Subject Offerings</h4>
                    <div style="background:#fff; border:1px solid #eee; padding:10px; border-radius:4px; min-height:100px;">
                        <?php if (count($subject_list) > 0): ?>
                            <?php foreach ($subject_list as $sub): ?>
                                <span class="badge">
                                    <?php echo htmlspecialchars($sub); ?>
                                    <a href="teacher.php?tab=register&delete_subject=<?php echo urlencode($sub); ?>" onclick="return confirm('Delete this subject and all its attendance logs?');" title="Delete module">❌</a>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color:#999; text-align:center; margin-top:30px; font-size:13px;">No registered courses setup.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        <?php endif; ?>

    </div>

</body>
</html>
