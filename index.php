<?php
session_start();
$error = "";

// Connect to SQLite database file instead of mysqli
$db = new PDO('sqlite:portal.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Automatically create tables if they don't exist yet
$db->exec("CREATE TABLE IF NOT EXISTS students (
    student_id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    full_name TEXT NOT NULL,
    role TEXT DEFAULT 'student'
)");

$db->exec("CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER,
    date TEXT NOT NULL,
    status TEXT CHECK(status IN ('Present', 'Absent')) NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id)
)");

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

// Handle the Login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Query using PDO (SQLite)
    $stmt = $db->prepare("SELECT student_id, password, full_name, role FROM students WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['student_id'];
        $_SESSION['student_id'] = $user['student_id']; 
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        // Redirect based on role
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
