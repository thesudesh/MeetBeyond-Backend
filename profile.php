<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$must_complete = isset($_GET['complete']);

// Load current user profile info
$stmt = $conn->prepare("SELECT name, age, gender, ethnicity, interests, bio FROM Profiles WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $age, $gender, $ethnicity, $interests, $bio);
$stmt->fetch();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $display_name = trim($_POST['display_name']);
    $age = intval($_POST['age']);
    $gender = $_POST['gender'];
    $ethnicity = trim($_POST['ethnicity']);
    $interests = trim($_POST['interests']);
    $bio = trim($_POST['bio']);

    if (strlen($display_name) < 2) {
        $error = "Name must be at least 2 characters.";
    } elseif ($age < 18 || $age > 120) {
        $error = "Please enter a valid age (18-120).";
    } elseif (!$gender) {
        $error = "Gender is required.";
    } else {
        $stmt = $conn->prepare("UPDATE Profiles SET name=?, age=?, gender=?, ethnicity=?, interests=?, bio=? WHERE user_id=?");
        $stmt->bind_param("sissssi", $display_name, $age, $gender, $ethnicity, $interests, $bio, $user_id);
        if ($stmt->execute()) {
            // Redirect to preferences page
            header('Location: preferences.php?complete=1');
            exit;
        } else {
            $error = "Failed to update profile.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Profile | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<header class="site-header container">
    <a class="brand" href="index.php">Meet <span class="accent">Beyond</span></a>
    <nav class="nav">
        <a href="index.php">Dashboard</a>
        <a href="photos.php">Photos</a>
    </nav>
</header>

<main class="container">
    <div class="card" style="max-width:680px;margin:0 auto">
        <h2 class="page-title">Your Profile</h2>
        <p class="lead">Update your information to help others get to know you.</p>

        <?php if ($must_complete): ?>
            <div class="alert alert-warn">Please complete your profile: Name, Age, and Gender are required.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" class="form-row">
            <input type="text" name="display_name" value="<?php echo htmlspecialchars($name); ?>" placeholder="Name" required>
            <input type="number" name="age" value="<?php echo htmlspecialchars($age); ?>" placeholder="Age" min="18" max="120" required>
            <select name="gender" required>
                <option value="">Select Gender</option>
                <option value="male" <?php if($gender==='male') echo "selected"; ?>>Male</option>
                <option value="female" <?php if($gender==='female') echo "selected"; ?>>Female</option>
                <option value="other" <?php if($gender==='other') echo "selected"; ?>>Other</option>
            </select>
            <input type="text" name="ethnicity" value="<?php echo htmlspecialchars($ethnicity); ?>" placeholder="Ethnicity">
            <input type="text" name="interests" value="<?php echo htmlspecialchars($interests); ?>" placeholder="Interests (comma separated)">
            <textarea name="bio" placeholder="Short bio (max 300 chars)" maxlength="300"><?php echo htmlspecialchars($bio); ?></textarea>
            <div style="display:flex;gap:12px;align-items:center">
                <button type="submit" class="btn">Update Profile</button>
                <a href="index.php" class="btn-ghost">Cancel</a>
            </div>
        </form>

    </div>
</main>
</body>
</html>