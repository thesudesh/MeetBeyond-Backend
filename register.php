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

<main class="container" style="padding-top:36px; padding-bottom:36px;">
    <section class="card card-hero" style="max-width:920px;margin:0 auto;">
        <div class="page-top">
            <div>
                <h2 class="page-title">Create Account</h2>
                <p class="lead">Join Meet Beyond and start making meaningful connections.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert" style="margin-bottom:16px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" class="form-row" novalidate>
            <label class="form-row">
                <input id="email" type="email" name="email" placeholder="Email" required class="form-control">
            </label>

            <label class="form-row">
                <input id="password" type="password" name="password" placeholder="Password" required class="form-control">
            </label>

            <label class="form-row">
                <input id="confirm" type="password" name="confirm" placeholder="Confirm Password" required class="form-control">
            </label>

            <div class="form-row" style="margin-top:8px;">
                <button type="submit" class="btn btn-hero" style="width:100%;">Create account</button>
            </div>

            <div class="form-row" style="justify-content:center;gap:8px;margin-top:12px;">
                <span class="muted">Already have an account?</span>
                <a class="btn btn-ghost" href="login.php">Sign In</a>
            </div>

        </form>
    </section>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>