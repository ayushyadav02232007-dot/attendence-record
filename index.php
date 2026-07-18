<?php
session_start();
$error = "";

$db = new PDO('sqlite:portal.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Ensure the new 'role' column exists in your students table to separate profiles
$db->exec("CREATE TABLE IF NOT EXISTS students (
    student_id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    full_name TEXT NOT NULL,
    role TEXT DEFAULT 'student' -- Can be 'student' or 'teacher'
)");

$db->exec("CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER,
    date TEXT NOT NULL,
    status TEXT CHECK(status IN ('Present', 'Absent')) NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id)
)");

// 2. Insert a Default Teacher Account alongside your test student if missing
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
        $_SESSION['student_id'] = $user['student_id']; // For backwards compatibility
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        // Redirect based on authority role
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
