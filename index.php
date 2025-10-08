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

    // Fetch primary photo path for avatar
    $stmt = $conn->prepare("SELECT file_path FROM Photos WHERE user_id=? AND is_primary=1 AND is_active=1 LIMIT 1");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($avatar_path);
    $has_avatar = $stmt->fetch();
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
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container">
    <?php if (!$is_logged_in): ?>
        <section class="card card-hero">
            <div>
                <h1 class="page-title">Welcome to <span class="brand"><span class="accent">Meet</span> Beyond</span></h1>
                <p class="lead">Your platform for authentic connections. Join thousands of people finding meaningful relationships every day.</p>
                <div class="hero-cta">
                    <a href="register.php" class="btn-hero">Get Started</a>
                    <a href="browse.php" class="btn btn-ghost">Browse Profiles</a>
                </div>
            </div>
            <div style="margin-left:auto;min-width:260px;text-align:right">
                <div class="muted">Smart Matching · Safe Messaging · Real Events</div>
            </div>
        </section>
    <?php else: ?>
        <section class="card">
            <div class="page-top">
                <div>
                    <h2 class="page-title">Hi, <?php echo htmlspecialchars($user_name); ?>!</h2>
                    <p class="lead">Here’s your Meet Beyond dashboard. Ready to connect?</p>
                </div>
                <div class="muted">Welcome back</div>
            </div>

            <div class="dashboard-header-row">
                <div class="dashboard-tiles-inline">
                    <a class="tile-big dashboard-link accent-purple" href="profile_view.php">
                        <span class="icon-badge" aria-hidden="true"><svg><use xlink:href="assets/icons.svg#icon-profile"></use></svg></span>
                        <div>
                            <div class="label">Profile</div>
                            <div class="sub">View your profile</div>
                        </div>
                    </a>
                    <a class="tile-big dashboard-link accent-teal" href="events.php">
                        <span class="icon-badge" aria-hidden="true"><svg><use xlink:href="assets/icons.svg#icon-events"></use></svg></span>
                        <div>
                            <div class="label">Events</div>
                            <div class="sub">Nearby gatherings</div>
                        </div>
                    </a>
                    <a class="tile-big dashboard-link accent-rose" href="browse.php">
                        <span class="icon-badge" aria-hidden="true"><svg><use xlink:href="assets/icons.svg#icon-browse"></use></svg></span>
                        <div>
                            <div class="label">Browse</div>
                            <div class="sub">Explore profiles</div>
                        </div>
                    </a>
                    <a class="tile-big dashboard-link accent-rose" href="matches.php">
                        <span class="icon-badge" aria-hidden="true"><svg><use xlink:href="assets/icons.svg#icon-heart"></use></svg></span>
                        <div>
                            <div class="label">Matches</div>
                            <div class="sub">See your top matches</div>
                        </div>
                    </a>
                    <a class="tile-big dashboard-link accent-purple" href="messages.php">
                        <span class="icon-badge" aria-hidden="true"><svg><use xlink:href="assets/icons.svg#icon-messages"></use></svg></span>
                        <div>
                            <div class="label">Messages</div>
                            <div class="sub">Inbox & chats</div>
                        </div>
                    </a>
                </div>
                <div class="dashboard-tile-grid">
                    <!-- additional tiles or quick actions could go here -->
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>

<script src="assets/theme.js"></script>
</body>
</html>