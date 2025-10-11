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

// Check current user's subscription status for premium features
$stmt = $conn->prepare("
    SELECT plan_type, end_date 
    FROM Subscriptions 
    WHERE user_id = ? AND end_date > CURDATE() 
    ORDER BY end_date DESC LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($current_plan, $plan_end_date);
$stmt->fetch();
$stmt->close();

$is_premium = !empty($current_plan);

// Fetch next profile to show with premium user prioritization
$sql = "
    SELECT u.id, p.name, p.age, p.gender, p.bio, ph.file_path,
           s.plan_type,
           CASE 
               WHEN s.plan_type = 'boost_10x' THEN RAND() * 10
               WHEN s.plan_type = 'boost_5x' THEN RAND() * 5  
               WHEN s.plan_type = 'boost_2x' THEN RAND() * 2
               ELSE RAND()
           END as priority_score
    FROM Users u
    JOIN Profiles p ON u.id = p.user_id
    JOIN Photos ph ON ph.user_id = u.id AND ph.is_primary=1 AND ph.is_active=1
    LEFT JOIN Subscriptions s ON u.id = s.user_id AND s.end_date > CURDATE()
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

$sql .= " ORDER BY priority_score DESC, RAND() LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$profile_data = $result->fetch_assoc();
$stmt->close();

// Extract variables for backward compatibility
$has_profile = !empty($profile_data);
if ($has_profile) {
    $profile_id = $profile_data['id'];
    $profile_name = $profile_data['name'];
    $profile_age = $profile_data['age'];
    $profile_gender = $profile_data['gender'];
    $profile_bio = $profile_data['bio'];
    $photo_path = $profile_data['file_path'];
    $profile_plan_type = $profile_data['plan_type'];
}

$photo_base = "MBusers/photos/";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Discover | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .discover-container {
            max-width: 100%;
            margin: 0 auto;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 70vh;
            gap: 40px;
        }
        
        .discover-header {
            text-align: center;
            margin-bottom: 40px;
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 32px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .discover-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
        }
        
        .discover-subtitle {
            color: var(--muted);
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .swipe-area {
            flex: 1;
            height: 600px;
            max-width: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .swipe-area::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255,255,255,0.05);
            opacity: 0;
            transition: all 0.3s ease;
            border-radius: inherit;
        }
        
        .swipe-area:hover::before {
            opacity: 1;
        }
        
        .swipe-area.left {
            background: linear-gradient(135deg, rgba(255, 107, 107, 0.1), rgba(238, 90, 82, 0.1));
            border: 2px dashed rgba(255, 107, 107, 0.3);
        }
        
        .swipe-area.left:hover {
            background: linear-gradient(135deg, rgba(255, 107, 107, 0.2), rgba(238, 90, 82, 0.2));
            border-color: rgba(255, 107, 107, 0.5);
            transform: scale(1.02);
        }
        
        .swipe-area.right {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(56, 142, 60, 0.1));
            border: 2px dashed rgba(76, 175, 80, 0.3);
        }
        
        .swipe-area.right:hover {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.2), rgba(56, 142, 60, 0.2));
            border-color: rgba(76, 175, 80, 0.5);
            transform: scale(1.02);
        }
        
        .profile-card-wrapper {
            flex: 1;
            max-width: 420px;
            height: 600px;
            position: relative;
            z-index: 10;
            display: flex;
            flex-direction: column;
        }
        
        .swipe-content {
            text-align: center;
            color: rgba(255,255,255,0.7);
            transition: all 0.3s ease;
        }
        
        .swipe-area:hover .swipe-content {
            color: rgba(255,255,255,1);
            transform: scale(1.1);
        }
        
        .swipe-icon {
            font-size: 4rem;
            margin-bottom: 16px;
            display: block;
        }
        
        .swipe-text {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .swipe-hint {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .profile-card {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(30px);
            border-radius: 24px;
            overflow: hidden;
            border: 2px solid rgba(255,255,255,0.12);
            box-shadow: 0 24px 60px rgba(0,0,0,0.4);
            position: relative;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            transform-origin: center;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .profile-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 32px 80px rgba(0,0,0,0.5);
        }
        
        .profile-image {
            width: 100%;
            height: 520px;
            object-fit: cover;
            display: block;
            position: relative;
        }
        
        .profile-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8) 60%, rgba(0,0,0,0.95));
            padding: 40px 32px 32px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        
        .profile-info {
            flex: 1;
        }
        
        .profile-name {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 8px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
        }
        
        .profile-details {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
            opacity: 0.95;
        }
        
        .profile-tag {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .profile-bio {
            color: rgba(255,255,255,0.9);
            line-height: 1.6;
            font-size: 1.05rem;
        }
        
        .view-profile-btn {
            width: 45px;
            height: 45px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            text-decoration: none;
            margin-left: 16px;
            flex-shrink: 0;
        }
        
        .view-profile-btn:hover {
            background: rgba(124,58,237,0.9);
            border-color: rgba(124,58,237,1);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(124,58,237,0.4);
        }
        
        .empty-state {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 80px 40px;
            text-align: center;
            border: 2px solid rgba(255,255,255,0.1);
            max-width: 500px;
            margin: 0 auto;
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 24px;
            opacity: 0.6;
            filter: grayscale(0.3);
        }
        
        .empty-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--text);
        }
        
        .empty-text {
            color: var(--muted);
            font-size: 1.1rem;
            margin-bottom: 32px;
            line-height: 1.6;
        }
        
        /* Premium Badge Styles */
        .premium-badge-card {
            position: absolute;
            top: 16px;
            right: 16px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: #1f2937;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 10;
            box-shadow: 0 4px 12px rgba(251,191,36,0.3);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .premium-inline-badge {
            display: inline-flex;
            align-items: center;
            margin-left: 8px;
            font-size: 1.2rem;
            filter: drop-shadow(0 2px 4px rgba(251,191,36,0.5));
        }
        
        .badge-icon {
            font-size: 0.9rem;
        }
        
        /* Premium card glow effect */
        .profile-card:has(.premium-badge-card) {
            box-shadow: 
                0 20px 40px rgba(0,0,0,0.2),
                0 0 0 2px rgba(251,191,36,0.3),
                0 0 20px rgba(251,191,36,0.2);
        }
        
        .profile-card:has(.premium-badge-card):hover {
            box-shadow: 
                0 24px 48px rgba(0,0,0,0.3),
                0 0 0 2px rgba(251,191,36,0.4),
                0 0 30px rgba(251,191,36,0.3);
        }
        
        @media (max-width: 1024px) {
            .discover-container {
                flex-direction: column;
                gap: 20px;
                min-height: auto;
            }
            
            .swipe-area {
                height: 120px;
                flex: none;
                width: 100%;
                max-width: 420px;
                margin: 0 auto;
            }
            
            .swipe-area.left {
                order: 2;
            }
            
            .profile-card-wrapper {
                order: 1;
                flex: none;
                width: 100%;
                max-width: 420px;
                margin: 0 auto;
            }
            
            .swipe-area.right {
                order: 3;
            }
            
            .swipe-icon {
                font-size: 2.5rem;
                margin-bottom: 8px;
            }
            
            .swipe-text {
                font-size: 1.1rem;
            }
            
            .swipe-hint {
                font-size: 0.8rem;
            }
        }
        
        .match-popup {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.95);
            backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.4s ease;
        }
        
        .match-popup.active {
            display: flex;
        }
        
        .match-content {
            text-align: center;
            padding: 60px 40px;
            background: rgba(255,255,255,0.05);
            border-radius: 24px;
            border: 2px solid rgba(167,139,250,0.2);
            backdrop-filter: blur(20px);
            max-width: 500px;
            margin: 20px;
        }
        
        .match-emoji {
            font-size: 5rem;
            margin-bottom: 24px;
            filter: hue-rotate(15deg) saturate(0.8);
        }
        
        .match-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 16px;
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .match-text {
            font-size: 1.2rem;
            color: var(--muted);
            margin-bottom: 32px;
            line-height: 1.6;
        }
        
        .match-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        
        @media (max-width: 480px) {
            .profile-card-wrapper {
                margin: 0 10px;
            }
            
            .profile-card {
                border-radius: 20px;
            }
            
            .profile-image {
                height: 460px;
            }
            
            .swipe-area {
                height: 100px;
                margin: 0 10px;
            }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container" style="padding: 20px 20px 60px;">
    <div class="discover-header">
        <h1 class="discover-title">Discover</h1>
        <p class="discover-subtitle">Find your perfect match</p>
    </div>

    <?php if ($has_profile): ?>
        <div class="discover-container">
            <!-- Left Pass Area -->
            <form method="POST" class="swipe-area left" onclick="this.querySelector('button').click()">
                <input type="hidden" name="target_id" value="<?php echo $profile_id; ?>">
                <button type="submit" name="action" value="pass" style="display: none;"></button>
                <div class="swipe-content">
                    <span class="swipe-icon">👎</span>
                    <div class="swipe-text">Pass</div>
                    <div class="swipe-hint">Click to pass</div>
                </div>
            </form>

            <!-- Profile Card -->
            <div class="profile-card-wrapper">
                <div class="profile-card">
                    <?php if (!empty($profile_plan_type)): ?>
                        <div class="premium-badge-card">
                            <?php 
                            $badge_text = '';
                            $badge_icon = '✨';
                            switch ($profile_plan_type) {
                                case 'boost_2x': $badge_text = '2x Boost'; $badge_icon = '🚀'; break;
                                case 'boost_5x': $badge_text = '5x Boost'; $badge_icon = '⚡'; break;
                                case 'boost_10x': $badge_text = '10x Boost'; $badge_icon = '💎'; break;
                                default: $badge_text = 'Premium'; break;
                            }
                            ?>
                            <span class="badge-icon"><?php echo $badge_icon; ?></span>
                            <?php echo $badge_text; ?>
                        </div>
                    <?php endif; ?>
                    
                    <img src="<?php echo $photo_base . htmlspecialchars($photo_path); ?>" 
                         alt="<?php echo htmlspecialchars($profile_name); ?>" 
                         class="profile-image">
                    
                    <div class="profile-overlay">
                        <div class="profile-info">
                            <h2 class="profile-name">
                                <?php echo htmlspecialchars($profile_name); ?>, <?php echo htmlspecialchars($profile_age); ?>
                                <?php if (!empty($profile_plan_type)): ?>
                                    <span class="premium-inline-badge">
                                        <?php echo $badge_icon; ?>
                                    </span>
                                <?php endif; ?>
                            </h2>
                            <div class="profile-details">
                                <span class="profile-tag"><?php echo ucfirst(htmlspecialchars($profile_gender)); ?></span>
                            </div>
                            <?php if ($profile_bio): ?>
                            <p class="profile-bio">
                                <?php echo nl2br(htmlspecialchars(mb_strimwidth($profile_bio, 0, 120, "..."))); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        
                        <a href="profile_view.php?id=<?php echo $profile_id; ?>" class="view-profile-btn" title="View Profile">
                            👀
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Like Area -->
            <form method="POST" class="swipe-area right" onclick="this.querySelector('button').click()">
                <input type="hidden" name="target_id" value="<?php echo $profile_id; ?>">
                <button type="submit" name="action" value="like" style="display: none;"></button>
                <div class="swipe-content">
                    <span class="swipe-icon">💖</span>
                    <div class="swipe-text">Like</div>
                    <div class="swipe-hint">Click to like</div>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="discover-container">
            <div class="empty-state">
                <div class="empty-icon">🎯</div>
                <h2 class="empty-title">No More Profiles</h2>
                <p class="empty-text">
                    You've seen all available profiles. Check back later for new members!
                </p>
                <a href="index.php" class="btn" style="padding:16px 32px">Back to Dashboard</a>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php if ($message): ?>
<div class="match-popup active" onclick="this.classList.remove('active')">
    <div class="match-content">
        <div class="match-emoji">💕</div>
        <h2 class="match-title"><?php echo $message; ?></h2>
        <p class="match-text">
            You can now send messages to each other!
        </p>
        <div class="match-actions">
            <a href="messages.php" class="btn" style="padding:16px 32px">Send Message</a>
            <button onclick="location.reload()" class="btn-ghost" style="padding:16px 32px">Keep Discovering</button>
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
        document.querySelector('.swipe-area.left button')?.click();
    } else if (e.key === 'ArrowRight') {
        document.querySelector('.swipe-area.right button')?.click();
    } else if (e.key === 'ArrowUp' || e.key === ' ') {
        e.preventDefault();
        document.querySelector('.swipe-area.right button')?.click();
    }
});

// Touch swipe support
let touchStartX = 0;
let touchEndX = 0;

const card = document.querySelector('.profile-card');
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
            document.querySelector('.swipe-area.left button')?.click();
        } else if (touchEndX > touchStartX + swipeThreshold) {
            // Swipe right - like
            document.querySelector('.swipe-area.right button')?.click();
        }
    }
}

// Add visual feedback for swipe areas
document.querySelectorAll('.swipe-area').forEach(area => {
    area.addEventListener('mouseenter', () => {
        area.style.transform = 'scale(1.02)';
    });
    
    area.addEventListener('mouseleave', () => {
        area.style.transform = 'scale(1)';
    });
});
</script>
</body>
</html>
