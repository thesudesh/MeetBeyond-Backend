<?php
// Simple header/navigation include
// Assumes session_start() has already been called in the including file.
// Safe-include config if not already included.
if (!function_exists('mb_db_connected')) {
    if (file_exists(__DIR__ . '/../config.php')) {
        require_once __DIR__ . '/../config.php';
    }
    function mb_db_connected() { return true; }
}

$is_logged_in = isset($_SESSION['user_id']);
$user_name = '';
$is_admin = false;
if ($is_logged_in) {
    $uid = $_SESSION['user_id'];
    // Try to fetch display name and role; silent on failure
    $tmp = $conn->prepare("SELECT p.name, u.email, u.role FROM Profiles p JOIN Users u ON u.id = p.user_id WHERE p.user_id = ? LIMIT 1");
    if ($tmp) {
        $tmp->bind_param('i', $uid);
        $tmp->execute();
        $tmp->bind_result($pname, $pemail, $urole);
        if ($tmp->fetch()) {
            $user_name = $pname ?: $pemail;
            $is_admin = ($urole === 'admin');
        }
        $tmp->close();
    }
    // Try to fetch primary avatar path for top-left avatar
    $avatar_src = '';
    $user_has_premium = false;
    $a = $conn->prepare("SELECT file_path FROM Photos WHERE user_id=? AND is_primary=1 AND is_active=1 LIMIT 1");
    if ($a) {
        $a->bind_param('i', $uid);
        $a->execute();
        $a->bind_result($apath);
        if ($a->fetch()) {
            $avatar_src = 'MBusers/photos/' . $apath;
        }
        $a->close();
    }
    
    // Check if user has premium subscription for golden ring
    $premium_check = $conn->prepare("SELECT plan_type FROM Subscriptions WHERE user_id = ? AND end_date > CURDATE() ORDER BY end_date DESC LIMIT 1");
    if ($premium_check) {
        $premium_check->bind_param('i', $uid);
        $premium_check->execute();
        $premium_check->bind_result($user_plan_type);
        $user_has_premium = $premium_check->fetch();
        $premium_check->close();
    }
}
?>
<?php if ($is_logged_in): ?>
    <div class="top-avatar-container">
        <?php if (!empty($avatar_src)): ?>
            <img src="<?php echo htmlspecialchars($avatar_src); ?>" alt="Avatar" class="top-avatar <?php echo $user_has_premium ? 'premium-avatar' : ''; ?>">
        <?php else: ?>
            <div class="top-avatar default-avatar <?php echo $user_has_premium ? 'premium-avatar' : ''; ?>">
                <?php echo strtoupper(substr($user_name ?: 'U', 0, 1)); ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<header class="site-header container">
    <a class="brand" href="index.php">Meet <span class="accent">Beyond</span></a>
    <nav class="nav">
        <?php if ($is_logged_in): ?>
            <?php if ($is_admin): ?>
                <!-- Admin-only navigation -->
                <a href="javascript:location.reload()" class="btn-ghost">🔄 Refresh</a>
                <a href="logout.php" class="btn-ghost">Logout</a>
            <?php else: ?>
                <!-- Regular user navigation -->
                <a href="discover.php">💖 Discover</a>
                <a href="browse.php">Browse</a>
                <a href="messages.php">Messages</a>
                <?php 
                // Check if user has premium subscription for premium nav link
                $nav_user_id = $_SESSION['user_id'];
                $nav_stmt = $conn->prepare("SELECT plan_type FROM Subscriptions WHERE user_id = ? AND end_date > CURDATE() ORDER BY end_date DESC LIMIT 1");
                $nav_stmt->bind_param("i", $nav_user_id);
                $nav_stmt->execute();
                $nav_stmt->bind_result($nav_plan_type);
                $nav_has_premium = $nav_stmt->fetch();
                $nav_stmt->close();
                
                if ($nav_has_premium): ?>
                    <a href="who-liked-me.php" style="color: #fbbf24; font-weight: 700;">👁️ Who Liked Me</a>
                <?php endif; ?>
                <a href="profile_view.php">Profile</a>
                <a href="logout.php" class="btn-ghost">Logout</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="register.php" class="btn">Get Started</a>
            <a href="browse.php" class="btn-ghost">Browse</a>
            <a href="login.php" class="btn-ghost">Sign In</a>
        <?php endif; ?>
    </nav>
</header>
