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
<div class="main-hero">
    <div class="main-box" style="max-width:480px;">
        <h2 class="hero-title" style="margin-bottom: 8px;">Your Profile</h2>
        <p class="hero-subtitle" style="margin-bottom: 32px;">Update your information to help others get to know you.</p>
        <?php if ($must_complete): ?>
            <div style="background:#fff8e1;color:#b26a00;padding:10px 0;margin-bottom:18px;border-radius:10px;">
                Please complete your profile: Name, Age, and Gender are required.
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background:#ffe5e5;color:#ae2222;padding:10px 0;margin-bottom:18px;border-radius:10px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php elseif ($success): ?>
            <div style="background:#e8ffe6;color:#218e2c;padding:10px 0;margin-bottom:18px;border-radius:10px;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <input type="text" name="display_name" value="<?php echo htmlspecialchars($name); ?>" placeholder="Name" required style="width:90%;padding:14px;margin-bottom:18px;border-radius:10px;border:1px solid #e0e3ee;">
            <input type="number" name="age" value="<?php echo htmlspecialchars($age); ?>" placeholder="Age" min="18" max="120" required style="width:90%;padding:14px;margin-bottom:18px;border-radius:10px;border:1px solid #e0e3ee;">
            <select name="gender" required style="width:90%;padding:14px;margin-bottom:18px;border-radius:10px;border:1px solid #e0e3ee;">
                <option value="">Select Gender</option>
                <option value="male" <?php if($gender==='male') echo "selected"; ?>>Male</option>
                <option value="female" <?php if($gender==='female') echo "selected"; ?>>Female</option>
                <option value="other" <?php if($gender==='other') echo "selected"; ?>>Other</option>
            </select>
            <input type="text" name="ethnicity" value="<?php echo htmlspecialchars($ethnicity); ?>" placeholder="Ethnicity" style="width:90%;padding:14px;margin-bottom:18px;border-radius:10px;border:1px solid #e0e3ee;">
            <input type="text" name="interests" value="<?php echo htmlspecialchars($interests); ?>" placeholder="Interests (comma separated)" style="width:90%;padding:14px;margin-bottom:18px;border-radius:10px;border:1px solid #e0e3ee;">
            <textarea name="bio" placeholder="Short bio (max 300 chars)" maxlength="300" style="width:90%;padding:14px;margin-bottom:24px;border-radius:10px;border:1px solid #e0e3ee;"><?php echo htmlspecialchars($bio); ?></textarea>
            <button type="submit" class="btn" style="width:90%;">Update Profile</button>
        </form>
        <a href="index.php" style="display:block;margin-top:30px;color:#aaa;text-decoration:underline;font-size:0.95em;">&#8592; Back to Dashboard</a>
    </div>
</div>
</body>
</html>