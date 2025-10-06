<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$must_complete = isset($_GET['complete']);

// Load preferences if any
$stmt = $conn->prepare("SELECT min_age, max_age, gender_pref, location, relationship_type, interests FROM Preferences WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($min_age, $max_age, $gender_pref, $location, $relationship_type, $interests);
$stmt->fetch();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $min_age = intval($_POST['min_age']);
    $max_age = intval($_POST['max_age']);
    $gender_pref = $_POST['gender_pref'];
    $location = trim($_POST['location']);
    $relationship_type = trim($_POST['relationship_type']);
    $interests = trim($_POST['interests']);

    if ($min_age < 18 || $max_age < $min_age) {
        $error = "Please enter a valid age range (minimum age at least 18, max age not less than min age).";
    } elseif (!$gender_pref || !$location || !$relationship_type) {
        $error = "All fields except interests are required.";
    } else {
        // Insert or update preferences
        $stmt = $conn->prepare("SELECT id FROM Preferences WHERE user_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            $stmt = $conn->prepare("UPDATE Preferences SET min_age=?, max_age=?, gender_pref=?, location=?, relationship_type=?, interests=? WHERE user_id=?");
            $stmt->bind_param("iissssi", $min_age, $max_age, $gender_pref, $location, $relationship_type, $interests, $user_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO Preferences (user_id, min_age, max_age, gender_pref, location, relationship_type, interests) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiissss", $user_id, $min_age, $max_age, $gender_pref, $location, $relationship_type, $interests);
            $stmt->execute();
            $stmt->close();
        }
        // Redirect to photos setup
        header('Location: photos.php?complete=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Preferences | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<div class="main-hero">
    <div class="main-box" style="max-width:500px;">
        <h2 class="hero-title" style="margin-bottom: 8px;">Your Preferences</h2>
        <p class="hero-subtitle" style="margin-bottom: 32px;">Tell us what you're looking for!</p>
        <?php if ($must_complete): ?>
            <div style="background:#fff8e1;color:#b26a00;padding:10px 0;margin-bottom:18px;border-radius:10px;">
                Please complete your preferences for better matches.
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background:#ffe5e5;color:#ae2222;padding:10px 0;margin-bottom:18px;border-radius:10px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <input type="number" name="min_age" placeholder="Minimum Age" value="<?php echo htmlspecialchars($min_age); ?>" min="18" max="120" required style="width:90%;padding:14px;margin-bottom:18px;border-radius:10px;border:1px solid #e0e3ee;">
            <input type="number" name="max_age" placeholder="Maximum Age" value="<?php echo htmlspecialchars($max_age); ?>" min="18" max="120" required style="width:90%;padding:14px;margin-bottom:18px;border-radius:10px;border:1px solid #e0e3ee;">

            <select name="gender_pref" required style="width:90%;padding:14px;margin-bottom:18px;border-radius:10px;border:1px solid #e0e3ee;">
                <option value="">Interested In</option>
                <option value="male" <?php if($gender_pref==='male') echo "selected"; ?>>Men</option>
                <option value="female" <?php if($gender_pref==='female') echo "selected"; ?>>Women</option>
                <option value="other" <?php if($gender_pref==='other') echo "selected"; ?>>Other</option>
            </select>

            <input type="text" name="location" placeholder="Location" value="<?php echo htmlspecialchars($location); ?>" required style="width:90%;padding:14px;margin-bottom:18px;border-radius:10px;border:1px solid #e0e3ee;">
            <select name="relationship_type" required style="width:90%;padding:14px;margin-bottom:18px;border-radius:10px;border:1px solid #e0e3ee;">
                <option value="">Looking For</option>
                <option value="friendship" <?php if($relationship_type==='friendship') echo "selected"; ?>>Friendship</option>
                <option value="dating" <?php if($relationship_type==='dating') echo "selected"; ?>>Dating</option>
                <option value="long-term" <?php if($relationship_type==='long-term') echo "selected"; ?>>Long-term</option>
                <option value="marriage" <?php if($relationship_type==='marriage') echo "selected"; ?>>Marriage</option>
                <option value="networking" <?php if($relationship_type==='networking') echo "selected"; ?>>Networking</option>
            </select>
            <input type="text" name="interests" placeholder="Interests (optional)" value="<?php echo htmlspecialchars($interests); ?>" style="width:90%;padding:14px;margin-bottom:24px;border-radius:10px;border:1px solid #e0e3ee;">
            <button type="submit" class="btn" style="width:90%;">Save Preferences</button>
        </form>
        <a href="index.php" style="display:block;margin-top:30px;color:#aaa;text-decoration:underline;font-size:0.95em;">&#8592; Back to Dashboard</a>
    </div>
</div>
</body>
</html>