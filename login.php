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

    $stmt = $conn->prepare("SELECT id, password_hash FROM Users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_id, $hash);
        $stmt->fetch();

        if (password_verify($password, $hash)) {
            $_SESSION['user_id'] = $user_id;
            header('Location: index.php');
            exit;
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "No account found with that email.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign In | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<div class="main-hero">
    <div class="main-box" style="max-width: 420px;">
        <h2 class="hero-title" style="margin-bottom: 8px;">Sign In</h2>
        <p class="hero-subtitle" style="margin-bottom: 26px;">Welcome back! Please sign in to continue.</p>
        <?php if ($error): ?>
            <div style="background:#ffe5e5;color:#ae2222;padding:10px 0;margin-bottom:18px;border-radius:10px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <input type="email" name="email" placeholder="Email" required 
                   style="width:90%;padding:14px;margin-bottom:18px;border-radius:10px;border:1px solid #e0e3ee;">
            <input type="password" name="password" placeholder="Password" required 
                   style="width:90%;padding:14px;margin-bottom:24px;border-radius:10px;border:1px solid #e0e3ee;">
            <button type="submit" class="btn" style="width:90%;">Sign In</button>
        </form>
        <p style="margin-top:20px;color:#7b7ce9;font-size:0.97em;">
            Don't have an account? 
            <a href="register.php" style="color:#9e71e6;text-decoration:underline;">Register</a>
        </p>
        <a href="index.php" style="display:block;margin-top:30px;color:#aaa;text-decoration:underline;font-size:0.95em;">&#8592; Back to Home</a>
    </div>
</div>
</body>
</html>