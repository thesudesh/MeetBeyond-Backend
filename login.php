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
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container">
    <div class="card" style="max-width:480px;margin:0 auto">
        <h2 class="page-title">Sign In</h2>
        <p class="lead">Welcome back! Please sign in to continue.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" class="form-row" style="margin-top:6px">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" class="btn-hero">Sign In</button>
        </form>

        <p class="form-note">Don't have an account? <a href="register.php" class="btn-ghost">Register</a></p>
    <p class="form-note"><a href="index.php" class="btn-ghost"><svg aria-hidden="true" style="width:16px;height:16px;vertical-align:middle"><use xlink:href="assets/icons.svg#icon-back"></use></svg> Back to Home</a></p>
    </div>
</main>
</body>
</html>