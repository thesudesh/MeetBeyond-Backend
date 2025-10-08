<?php
session_start();
require 'config.php';

if (isset($_SESSION['user_id'])) {
    // Check if user is admin and redirect accordingly
    $stmt = $conn->prepare("SELECT role FROM Users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($role);
    $stmt->fetch();
    $stmt->close();
    
    if ($role === 'admin') {
        header('Location: admin.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password_hash, role FROM Users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_id, $hash, $role);
        $stmt->fetch();

        if (password_verify($password, $hash)) {
            $_SESSION['user_id'] = $user_id;
            
            // Redirect admins to admin panel, regular users to index
            if ($role === 'admin') {
                header('Location: admin.php');
            } else {
                header('Location: index.php');
            }
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
    <div class="card" style="max-width:500px;margin:60px auto;text-align:center">
        <div style="font-size:3.5rem;margin-bottom:20px">👋</div>
        <h2 class="page-title" style="margin-bottom:12px">Welcome Back!</h2>
        <p class="lead" style="margin-bottom:32px">Sign in to continue your journey</p>

        <?php if ($error): ?>
            <div class="alert alert-error" style="text-align:left"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" style="text-align:left">
            <div class="form-row">
                <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                    <span style="font-size:1.2rem">📧</span> Email Address
                </label>
                <input type="email" name="email" placeholder="your@email.com" required>
            </div>

            <div class="form-row">
                <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                    <span style="font-size:1.2rem">🔒</span> Password
                </label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn" style="width:100%;justify-content:center;padding:16px;margin-top:24px;font-size:1.05rem">
                Sign In →
            </button>
        </form>

        <div style="margin-top:32px;padding-top:24px;border-top:1px solid rgba(255,255,255,0.1)">
            <p style="color:var(--muted);margin-bottom:16px">Don't have an account?</p>
            <a href="register.php" class="btn-ghost" style="display:inline-flex">Create Account</a>
        </div>

        <div style="margin-top:20px">
            <a href="index.php" style="color:var(--muted);font-size:0.95rem">← Back to Home</a>
        </div>
    </div>
</main>
</body>
</html>