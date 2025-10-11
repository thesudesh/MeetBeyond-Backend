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

<main class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-icon">👋</div>
            <h2 class="auth-title">Welcome Back!</h2>
            <p class="auth-subtitle">Sign in to continue your journey</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" class="auth-form">
            <div class="form-group">
                <label for="email" class="form-label">
                    <span class="label-icon">📧</span>
                    Email Address
                </label>
                <input type="email" id="email" name="email" class="form-input" placeholder="your@email.com" required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">
                    <span class="label-icon">🔒</span>
                    Password
                </label>
                <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="auth-btn auth-btn-primary">
                Sign In
            </button>
        </form>

        <div class="auth-divider">
            <span>Don't have an account?</span>
        </div>

        <div class="auth-footer">
            <a href="register.php" class="auth-btn auth-btn-secondary">Create Account</a>
            <a href="index.php" class="auth-link">← Back to Home</a>
        </div>
    </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>