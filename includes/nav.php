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
}
?>
<?php if ($is_logged_in && !empty($avatar_src)): ?>
    <img src="<?php echo htmlspecialchars($avatar_src); ?>" alt="Avatar" class="top-avatar">
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
