<?php
session_start();
require 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM Users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Email already registered.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO Users (email, password_hash, is_verified) VALUES (?, ?, 0)");
            $stmt->bind_param("ss", $email, $password_hash);
            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;
                // Create an empty profile row for the user
                $profileStmt = $conn->prepare("INSERT INTO Profiles (user_id) VALUES (?)");
                $profileStmt->bind_param("i", $new_user_id);
                $profileStmt->execute();
                $profileStmt->close();

                $_SESSION['user_id'] = $new_user_id;
                header('Location: index.php');
                exit;
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<div class="main-hero">
    <div class="main-box" style="max-width: 420px;">
        <h2 class="hero-title" style="margin-bottom: 8px;">Create Account</h2>
        <p class="hero-subtitle" style="margin-bottom: 26px;">Join Meet Beyond and start making meaningful connections.</p>
        <?php if ($error): ?>
            <div style="background:#ffe5e5;color:#ae2222;padding:10px 0;margin-bottom:18px;border-radius:10px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <input type="email" name="email" placeholder="Email" required 
                   style="width:90%;padding:14px;margin-bottom:18px;border-radius:10px;border:1px solid #e0e3ee;">
            <input type="password" name="password" placeholder="Password" required 
                   style="width:90%;padding:14px;margin-bottom:18px;border-radius:10px;border:1px solid #e0e3ee;">
            <input type="password" name="confirm" placeholder="Confirm Password" required 
                   style="width:90%;padding:14px;margin-bottom:24px;border-radius:10px;border:1px solid #e0e3ee;">
            <button type="submit" class="btn" style="width:90%;">Register</button>
        </form>
        <p style="margin-top:20px;color:#7b7ce9;font-size:0.97em;">
            Already have an account?
            <a href="login.php" style="color:#9e71e6;text-decoration:underline;">Sign In</a>
        </p>
        <a href="index.php" style="display:block;margin-top:30px;color:#aaa;text-decoration:underline;font-size:0.95em;">&#8592; Back to Home</a>
    </div>
</div>
</body>
</html>