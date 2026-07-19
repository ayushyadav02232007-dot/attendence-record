<?php
session_start();

// Security Check: Kick out anyone who isn't logged in as a student
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

$db = new PDO('sqlite:portal.db');
// Saves the database in a secure cloud storage folder that never wipes out
//$db = new PDO('sqlite:/var/www/html/data/portal.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$student_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Fetch all unique subjects currently registered dynamically in the database 
$subjects = $db->query("SELECT subject_name FROM subjects ORDER BY subject_name ASC")->fetchAll(PDO::FETCH_COLUMN);
$subject_stats = [];

// Calculate individual metrics for each subject dynamically
foreach ($subjects as $sub) {
    $total_stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND subject = ?");
    $total_stmt->execute([$student_id, $sub]);
    $t_days = $total_stmt->fetchColumn();

    $present_stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND subject = ? AND status = 'Present'");
    $present_stmt->execute([$student_id, $sub]);
    $p_days = $present_stmt->fetchColumn();

    $pct = ($t_days > 0) ? round(($p_days / $t_days) * 100) : 0;

    $subject_stats[$sub] = [
        'total' => $t_days,
        'present' => $p_days,
        'percentage' => $pct
    ];
}

// Calculate Overall Combined Metrics
$overall_total_stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ?");
$overall_total_stmt->execute([$student_id]);
$overall_total = $overall_total_stmt->fetchColumn();

$overall_present_stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND status = 'Present'");
$overall_present_stmt->execute([$student_id]);
$overall_present = $overall_present_stmt->fetchColumn();

$overall_percentage = ($overall_total > 0) ? round(($overall_present / $overall_total) * 100) : 0;

// Fetch all history entries, including the subject names
$logs_stmt = $db->prepare("SELECT date, subject, status FROM attendance WHERE student_id = ? ORDER BY date DESC");
$logs_stmt->execute([$student_id]);
$logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Portal - Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .subject-grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
            margin-bottom: 30px;
        }
        .subject-card {
            background: #fdfdfd;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .subject-card h4 {
            margin: 0 0 10px 0;
            color: #1976d2;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 5px;
        }
        .sub-stat {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 14px;
        }
        .sub-pct {
            font-weight: bold;
            font-size: 16px;
            color: #333;
            margin-top: 8px;
            border-top: 1px dashed #eee;
            padding-top: 8px;
        }
    </style>
</head>
<body>

    <div class="dashboard-card">
        <div class="dashboard-header">
            <h2>Welcome, <?php echo htmlspecialchars($full_name); ?></h2>
            <a href="logout.php" class="logout-link">Sign Out</a>
        </div>

        <!-- Section A: Overall Combined Score Overview -->
        <h3 class="table-title" style="margin-top:10px;">Overall Summary</h3>
        <div class="metrics-grid">
            <div class="metric-box">
                <h4>Total Classes (All)</h4>
                <p class="metric-value"><?php echo $overall_total; ?></p>
            </div>
            <div class="metric-box">
                <h4>Total Present</h4>
                <p class="metric-value" style="color: #2e7d32;"><?php echo $overall_present; ?></p>
            </div>
            <div class="metric-box">
                <h4>Total Attendance %</h4>
                <p class="metric-value"><?php echo $overall_percentage; ?>%</p>
            </div>
        </div>

        <!-- Section B: Subject-Wise Performance Breakdown -->
        <h3 class="table-title" style="margin-top: 25px;">Subject Wise Breakdown</h3>
        <div class="subject-grid-container">
            <?php foreach ($subject_stats as $name => $stats): ?>
                <div class="subject-card">
                    <h4><?php echo $name; ?></h4>
                    <div class="sub-stat"><span>Total Classes:</span> <strong><?php echo $stats['total']; ?></strong></div>
                    <div class="sub-stat"><span>Attended:</span> <strong style="color: #2e7d32;"><?php echo $stats['present']; ?></strong></div>
                    <div class="sub-stat sub-pct"><span>Attendance:</span> <span><?php echo $stats['percentage']; ?>%</span></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Section C: Comprehensive Log Tracking Data Table -->
        <h3 class="table-title">Your Attendance History</h3>
        <table class="attendance-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Subject Name</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($logs) > 0): ?>
                    <?php foreach($logs as $row): ?>
                    <tr>
                        <td><?php echo date("F j, Y", strtotime($row['date'])); ?></td>
                        <td style="font-weight: 500; color: #333;"><?php echo htmlspecialchars($row['subject']); ?></td>
                        <td>
                            <span class="status-badge <?php echo strtolower($row['status']); ?>">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center; color: #999;">No attendance tracking records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>
