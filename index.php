<?php
session_start();
$error = "";

// Connect to SQLite database file
$db = new PDO('sqlite:portal.db');
// Saves the database in a secure cloud storage folder that never wipes out
//$db = new PDO('sqlite:/var/www/html/data/portal.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Automatically create tables if they don't exist yet
$db->exec("CREATE TABLE IF NOT EXISTS students (
    student_id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    full_name TEXT NOT NULL,
    role TEXT DEFAULT 'student'
)");

$db->exec("CREATE TABLE IF NOT EXISTS subjects (
    subject_id INTEGER PRIMARY KEY AUTOINCREMENT,
    subject_name TEXT UNIQUE NOT NULL
)");

$db->exec("CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER,
    date TEXT NOT NULL,
    subject TEXT NOT NULL,
    status TEXT CHECK(status IN ('Present', 'Absent')) NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id)
)");

// Pre-seed default core subjects if table is empty
$checkSubjects = $db->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
if ($checkSubjects == 0) {
    $default_subjects = ['Math', 'Physics', 'Chemistry', 'English'];
    $stmt = $db->prepare("INSERT INTO subjects (subject_name) VALUES (?)");
    foreach ($default_subjects as $sub) {
        $stmt->execute([$sub]);
    }
}

// Insert a Test Student if empty (Username: student1 | Password: password123)
$checkUsers = $db->query("SELECT COUNT(*) FROM students WHERE role = 'student'")->fetchColumn();
if ($checkUsers == 0) {
    $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO students (username, password, full_name, role) VALUES ('student1', ?, 'John Doe', 'student')");
    $stmt->execute([$hashedPassword]);
}

// Insert a Default Teacher if empty (Username: teacher1 | Password: teacher123)
$checkTeacher = $db->query("SELECT COUNT(*) FROM students WHERE role = 'teacher'")->fetchColumn();
if ($checkTeacher == 0) {
    $teacherHash = password_hash('teacher123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO students (username, password, full_name, role) VALUES ('teacher1', ?, 'Professor Smith', 'teacher')");
    $stmt->execute([$teacherHash]);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT student_id, password, full_name, role FROM students WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['student_id'];
        $_SESSION['student_id'] = $user['student_id']; 
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] === 'teacher') {
            header("Location: teacher.php");
        } else {
            header("Location: dashboard.php");
        }
        exit();
    } else {
        $error = "Invalid entry. Check your username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Portal - Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="login-container">
        <h2>Portal Login</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error-msg" style="color: red; margin-bottom: 10px; font-weight: bold; text-align: center;"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <div class="input-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autocomplete="off">
            </div>
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-primary">Sign In</button>
        </form>
    </div>

</body>
</html>
