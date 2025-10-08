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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container">
    <div class="card" style="max-width:550px;margin:60px auto;text-align:center">
        <div style="font-size:3.5rem;margin-bottom:20px">✨</div>
        <h2 class="page-title" style="margin-bottom:12px">Join Meet Beyond</h2>
        <p class="lead" style="margin-bottom:32px">Create your account and start connecting with amazing people</p>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert" style="text-align:left">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" style="text-align:left">
            <div class="form-row">
                <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                    <span style="font-size:1.2rem">📧</span> Email Address
                </label>
                <input id="email" type="email" name="email" placeholder="your@email.com" required>
            </div>

            <div class="form-row">
                <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                    <span style="font-size:1.2rem">🔒</span> Password
                </label>
                <input id="password" type="password" name="password" placeholder="At least 6 characters" required>
                <small style="color:var(--muted);font-size:0.85rem">Must be at least 6 characters long</small>
            </div>

            <div class="form-row">
                <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                    <span style="font-size:1.2rem">✓</span> Confirm Password
                </label>
                <input id="confirm" type="password" name="confirm" placeholder="Re-enter your password" required>
            </div>

            <button type="submit" class="btn" style="width:100%;justify-content:center;padding:16px;margin-top:24px;font-size:1.05rem">
                Create My Account →
            </button>
        </form>

        <div style="margin-top:32px;padding-top:24px;border-top:1px solid rgba(255,255,255,0.1)">
            <p style="color:var(--muted);margin-bottom:16px">Already have an account?</p>
            <a href="login.php" class="btn-ghost" style="display:inline-flex">Sign In</a>
        </div>

        <div style="margin-top:20px">
            <a href="index.php" style="color:var(--muted);font-size:0.95rem">← Back to Home</a>
        </div>
    </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>