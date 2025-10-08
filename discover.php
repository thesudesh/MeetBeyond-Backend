<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if user is admin - admins cannot use dating features
$stmt = $conn->prepare("SELECT role FROM Users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_role);
$stmt->fetch();
$stmt->close();

if ($user_role === 'admin') {
    header('Location: admin.php');
    exit;
}

$message = '';

// Handle like/pass actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target_id = intval($_POST['target_id'] ?? 0);
    
    if ($action === 'like' && $target_id > 0) {
        // Insert like
        $stmt = $conn->prepare("INSERT INTO Likes (liker_id, liked_id, status) VALUES (?, ?, 'liked') ON DUPLICATE KEY UPDATE status='liked', created_at=NOW()");
        $stmt->bind_param("ii", $user_id, $target_id);
        $stmt->execute();
        $stmt->close();
        
        // Check if they liked back (mutual like = match)
        $stmt = $conn->prepare("SELECT id FROM Likes WHERE liker_id=? AND liked_id=? AND status='liked'");
        $stmt->bind_param("ii", $target_id, $user_id);
        $stmt->execute();
        $stmt->store_result();
        $is_mutual = $stmt->num_rows > 0;
        $stmt->close();
        
        if ($is_mutual) {
            // Create match
            $user_low = min($user_id, $target_id);
            $user_high = max($user_id, $target_id);
            $stmt = $conn->prepare("INSERT IGNORE INTO Matches (user1_id, user2_id, user_low_id, user_high_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiii", $user_id, $target_id, $user_low, $user_high);
            $stmt->execute();
            $stmt->close();
            $message = "🎉 It's a Match!";
        }
    } elseif ($action === 'pass' && $target_id > 0) {
        // Insert pass (so we don't show them again)
        $stmt = $conn->prepare("INSERT INTO Likes (liker_id, liked_id, status) VALUES (?, ?, 'passed') ON DUPLICATE KEY UPDATE status='passed', created_at=NOW()");
        $stmt->bind_param("ii", $user_id, $target_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'block' && $target_id > 0) {
        // Block user
        $stmt = $conn->prepare("INSERT IGNORE INTO Blocks (blocker_id, blocked_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $target_id);
        $stmt->execute();
        $stmt->close();
        
        // Also add to passed
        $stmt = $conn->prepare("INSERT INTO Likes (liker_id, liked_id, status) VALUES (?, ?, 'passed') ON DUPLICATE KEY UPDATE status='passed'");
        $stmt->bind_param("ii", $user_id, $target_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Fetch user's preferences
$stmt = $conn->prepare("SELECT min_age, max_age, gender_pref FROM Preferences WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($min_age, $max_age, $gender_pref);
$stmt->fetch();
$stmt->close();

// Fetch next profile to show (exclude already liked/passed/blocked users and admins)
$sql = "
    SELECT u.id, p.name, p.age, p.gender, p.bio, ph.file_path
    FROM Users u
    JOIN Profiles p ON u.id = p.user_id
    JOIN Photos ph ON ph.user_id = u.id AND ph.is_primary=1 AND ph.is_active=1
    WHERE u.id != ?
    AND u.role != 'admin'
    AND p.name IS NOT NULL 
    AND p.age IS NOT NULL
    AND p.visible = TRUE
    AND u.id NOT IN (
        SELECT liked_id FROM Likes WHERE liker_id = ?
    )
    AND u.id NOT IN (
        SELECT blocked_id FROM Blocks WHERE blocker_id = ?
    )
";

// Add preference filters if set
$params = [$user_id, $user_id, $user_id];
$types = "iii";

if ($min_age && $max_age) {
    $sql .= " AND p.age BETWEEN ? AND ?";
    $params[] = $min_age;
    $params[] = $max_age;
    $types .= "ii";
}

if ($gender_pref && $gender_pref !== 'any') {
    $sql .= " AND p.gender = ?";
    $params[] = $gender_pref;
    $types .= "s";
}

$sql .= " ORDER BY RAND() LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($profile_id, $profile_name, $profile_age, $profile_gender, $profile_bio, $photo_path);
$has_profile = $stmt->fetch();
$stmt->close();

$photo_base = "MBusers/photos/";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Discover | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .swipe-container {
            max-width: 450px;
            margin: 0 auto;
            position: relative;
        }
        .profile-swipe-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow-lg);
            position: relative;
            transition: var(--transition);
        }
        .swipe-photo {
            width: 100%;
            height: 500px;
            object-fit: cover;
            display: block;
        }
        .swipe-info {
            padding: 28px;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
        }
        .swipe-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            padding: 30px 0;
        }
        .swipe-btn {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-md);
        }
        .swipe-btn:hover {
            transform: scale(1.15);
            box-shadow: var(--shadow-lg);
        }
        .swipe-btn:active {
            transform: scale(0.95);
        }
        .btn-pass {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        .btn-like {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
        }
        .btn-super {
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));
            color: white;
            width: 80px;
            height: 80px;
            font-size: 2.5rem;
        }
        .match-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.3s;
        }
        .match-overlay.active {
            display: flex;
        }
        .match-content {
            text-align: center;
            padding: 40px;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container" style="padding-top:40px;padding-bottom:60px">
    <div style="text-align:center;margin-bottom:32px">
        <h1 class="page-title">Discover</h1>
        <p class="lead">Swipe right to like, left to pass</p>
    </div>

    <?php if ($has_profile): ?>
        <div class="swipe-container">
            <div class="profile-swipe-card">
                <img src="<?php echo $photo_base . htmlspecialchars($photo_path); ?>" 
                     alt="<?php echo htmlspecialchars($profile_name); ?>" 
                     class="swipe-photo">
                
                <div class="swipe-info">
                    <h2 style="font-size:2rem;margin-bottom:8px;font-weight:700">
                        <?php echo htmlspecialchars($profile_name); ?>, <?php echo htmlspecialchars($profile_age); ?>
                    </h2>
                    <p style="color:rgba(255,255,255,0.9);margin-bottom:12px">
                        <?php echo ucfirst(htmlspecialchars($profile_gender)); ?>
                    </p>
                    <?php if ($profile_bio): ?>
                    <p style="color:rgba(255,255,255,0.85);line-height:1.6">
                        <?php echo nl2br(htmlspecialchars(mb_strimwidth($profile_bio, 0, 150, "..."))); ?>
                    </p>
                    <?php endif; ?>
                    <a href="profile_view.php?id=<?php echo $profile_id; ?>" 
                       style="display:inline-block;margin-top:16px;color:var(--accent-purple);font-weight:600;text-decoration:none">
                        View Full Profile →
                    </a>
                </div>
            </div>

            <div class="swipe-actions">
                <form method="POST" style="display:inline">
                    <input type="hidden" name="target_id" value="<?php echo $profile_id; ?>">
                    <button type="submit" name="action" value="pass" class="swipe-btn btn-pass" title="Pass">
                        ✕
                    </button>
                </form>

                <form method="POST" style="display:inline">
                    <input type="hidden" name="target_id" value="<?php echo $profile_id; ?>">
                    <button type="submit" name="action" value="like" class="swipe-btn btn-super" title="Like">
                        💖
                    </button>
                </form>

                <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure you want to block this user? They will no longer appear in your feed.')">
                    <input type="hidden" name="target_id" value="<?php echo $profile_id; ?>">
                    <button type="submit" name="action" value="block" class="swipe-btn btn-pass" title="Block">
                        🚫
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card" style="max-width:500px;margin:0 auto;text-align:center;padding:60px 40px">
            <div style="font-size:4rem;margin-bottom:24px">🎯</div>
            <h2 style="font-size:1.8rem;margin-bottom:16px;font-weight:700">No More Profiles</h2>
            <p style="color:var(--muted);font-size:1.1rem;margin-bottom:32px">
                You've seen all available profiles. Check back later for new members!
            </p>
            <a href="index.php" class="btn" style="padding:16px 32px">Back to Dashboard</a>
        </div>
    <?php endif; ?>
</main>

<?php if ($message): ?>
<div class="match-overlay active" onclick="this.classList.remove('active')">
    <div class="match-content">
        <div style="font-size:6rem;margin-bottom:24px">💕</div>
        <h2 style="font-size:3rem;margin-bottom:16px;font-weight:800"><?php echo $message; ?></h2>
        <p style="font-size:1.2rem;color:var(--muted);margin-bottom:32px">
            You can now send messages to each other!
        </p>
        <div style="display:flex;gap:16px;justify-content:center">
            <a href="messages.php" class="btn" style="padding:16px 32px">Send Message</a>
            <button onclick="location.reload()" class="btn-ghost" style="padding:16px 32px">Keep Swiping</button>
        </div>
    </div>
</div>
<?php endif; ?>

<a href="index.php" class="back-btn" title="Back to Dashboard">←</a>

<?php include_once __DIR__ . '/includes/footer.php'; ?>

<script>
// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') {
        document.querySelector('button[value="pass"]')?.click();
    } else if (e.key === 'ArrowRight') {
        document.querySelector('button[value="like"]')?.click();
    }
});

// Touch swipe support
let touchStartX = 0;
let touchEndX = 0;

const card = document.querySelector('.profile-swipe-card');
if (card) {
    card.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    });

    card.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    });

    function handleSwipe() {
        const swipeThreshold = 100;
        if (touchEndX < touchStartX - swipeThreshold) {
            // Swipe left - pass
            document.querySelector('button[value="pass"]')?.click();
        } else if (touchEndX > touchStartX + swipeThreshold) {
            // Swipe right - like
            document.querySelector('button[value="like"]')?.click();
        }
    }
}
</script>
</body>
</html>
