<?php
session_start();
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Database Connection
    $conn = new mysqli("localhost", "root", "", "school_portal");
    if ($conn->connect_error) { die("Connection failed"); }

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT student_id, password, full_name FROM students WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
        if (password_verify($password, $student['password'])) {
            $_SESSION['student_id'] = $student['student_id'];
            $_SESSION['full_name'] = $student['full_name'];
            header("Location: dashboard.php");
            exit();
        }
    }
    $error = "Invalid entry. Check your username or password.";
    $stmt->close();
    $conn->close();
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
        <h2>Student Portal</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error-msg"><?php echo $error; ?></div>
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
