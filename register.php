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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-icon">✨</div>
            <h2 class="auth-title">Join Meet Beyond</h2>
            <p class="auth-subtitle">Create your account and start connecting with amazing people</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
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
                <input type="password" id="password" name="password" class="form-input" placeholder="At least 6 characters" required>
                <small class="form-hint">Must be at least 6 characters long</small>
            </div>

            <div class="form-group">
                <label for="confirm" class="form-label">
                    <span class="label-icon">✓</span>
                    Confirm Password
                </label>
                <input type="password" id="confirm" name="confirm" class="form-input" placeholder="Re-enter your password" required>
            </div>

            <button type="submit" class="auth-btn auth-btn-primary">
                Create My Account
            </button>
        </form>

        <div class="auth-divider">
            <span>Already have an account?</span>
        </div>

        <div class="auth-footer">
            <a href="login.php" class="auth-btn auth-btn-secondary">Sign In</a>
            <a href="index.php" class="auth-link">← Back to Home</a>
        </div>
    </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>