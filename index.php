<?php
session_start();
require 'config.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_name = '';

if ($is_logged_in) {
    // 1. Ensure profile is complete (name, age, gender)
    $stmt = $conn->prepare("SELECT name, age, gender FROM Profiles WHERE user_id=?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($name, $age, $gender);
    $stmt->fetch();
    $stmt->close();

    if (empty($name) || empty($age) || empty($gender)) {
        // Profile incomplete
        header('Location: profile.php?complete=1');
        exit;
    }

    // 2. Ensure preferences are complete (min_age, max_age, gender_pref, location, relationship_type)
    $stmt = $conn->prepare("SELECT min_age, max_age, gender_pref, location, relationship_type FROM Preferences WHERE user_id=?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($min_age, $max_age, $gender_pref, $location, $relationship_type);
    $stmt->fetch();
    $stmt->close();

    if (empty($min_age) || empty($max_age) || empty($gender_pref) || empty($location) || empty($relationship_type)) {
        // Preferences incomplete
        header('Location: preferences.php?complete=1');
        exit;
    }

    // 3. Ensure a primary photo exists
    $stmt = $conn->prepare("SELECT id FROM Photos WHERE user_id=? AND is_primary=1 AND is_active=1 LIMIT 1");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($photo_id);
    $has_primary_photo = $stmt->fetch();
    $stmt->close();

    if (!$has_primary_photo) {
        header('Location: photos.php?complete=1');
        exit;
    }

    // Fetch email for fallback display
    $stmt = $conn->prepare("SELECT email FROM Users WHERE id=?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($email);
    $stmt->fetch();
    $stmt->close();

    $user_name = $name ?: $email;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<?php if (!$is_logged_in): ?>
    <div class="main-hero">
        <div class="main-box">
            <h1 class="hero-title">Welcome to <span class="brand">Meet Beyond</span></h1>
            <p class="hero-subtitle">Your platform for authentic connections. Join thousands of people finding meaningful relationships every day.</p>
            <div class="hero-cta">
                <a href="register.php" class="btn">Get Started</a>
                <a href="browse.php" class="btn btn-secondary">Browse Profiles</a>
            </div>
            <div class="features">
                <div class="feature">
                    <span class="icon">🎯</span>
                    <span>Smart Matching</span>
                </div>
                <div class="feature">
                    <span class="icon">💬</span>
                    <span>Safe Messaging</span>
                </div>
                <div class="feature">
                    <span class="icon">🎉</span>
                    <span>Real Events</span>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="dashboard-hero">
        <div class="dashboard-box">
            <h1 class="dashboard-title">Hi, <?php echo htmlspecialchars($user_name); ?>!</h1>
            <p class="dashboard-subtitle">Here’s your Meet Beyond dashboard. Ready to connect?</p>
            <div class="dashboard-cards">
                <a href="matches.php" class="dashboard-card">
                    <span class="icon">❤️</span>
                    <span>Matches</span>
                </a>
                <a href="messages.php" class="dashboard-card">
                    <span class="icon">💬</span>
                    <span>Messages</span>
                </a>
                <a href="profile.php" class="dashboard-card">
                    <span class="icon">📝</span>
                    <span>Edit Profile</span>
                </a>
                <a href="events.php" class="dashboard-card">
                    <span class="icon">🎟️</span>
                    <span>Events</span>
                </a>
                <a href="browse.php" class="dashboard-card">
                    <span class="icon">🔍</span>
                    <span>Browse</span>
                </a>
            </div>
            <div class="dashboard-logout">
                <a href="logout.php" class="logout">Logout</a>
            </div>
        </div>
    </div>
<?php endif; ?>
</body>
</html>