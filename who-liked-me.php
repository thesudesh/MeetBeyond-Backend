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

// Check subscription status
$stmt = $conn->prepare("
    SELECT plan_type, end_date 
    FROM Subscriptions 
    WHERE user_id = ? AND end_date > CURDATE() 
    ORDER BY end_date DESC LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_plan_type, $user_plan_end);
$stmt->fetch();
$stmt->close();

$is_premium = !empty($user_plan_type);

// If not premium, redirect to subscription page
if (!$is_premium) {
    header('Location: subscription.php?feature=who_liked');
    exit;
}

// Get users who liked this user but haven't been liked back
$stmt = $conn->prepare("
    SELECT DISTINCT 
        u.id, p.name, p.age, p.gender, p.bio, ph.file_path as photo,
        l.created_at as liked_date,
        s.plan_type as liker_plan
    FROM Likes l
    JOIN Users u ON l.liker_id = u.id  
    JOIN Profiles p ON u.id = p.user_id
    LEFT JOIN Photos ph ON u.id = ph.user_id AND ph.is_primary = 1 AND ph.is_active = 1
    LEFT JOIN Subscriptions s ON u.id = s.user_id AND s.end_date > CURDATE()
    WHERE l.liked_id = ? 
    AND l.status = 'liked'
    AND l.liker_id NOT IN (
        SELECT liked_id FROM Likes WHERE liker_id = ? AND status = 'liked'
    )
    AND l.liker_id NOT IN (
        SELECT blocked_id FROM Blocks WHERE blocker_id = ?
        UNION
        SELECT blocker_id FROM Blocks WHERE blocked_id = ?
    )
    ORDER BY l.created_at DESC
");
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$liked_by_users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle like back action
if ($_POST['action'] ?? '' === 'like_back') {
    $target_id = intval($_POST['target_id'] ?? 0);
    
    if ($target_id > 0) {
        // Insert like
        $stmt = $conn->prepare("INSERT INTO Likes (liker_id, liked_id, status) VALUES (?, ?, 'liked') ON DUPLICATE KEY UPDATE status='liked', created_at=NOW()");
        $stmt->bind_param("ii", $user_id, $target_id);
        $stmt->execute();
        $stmt->close();
        
        // Create match since both users liked each other
        $user_low = min($user_id, $target_id);
        $user_high = max($user_id, $target_id);
        $stmt = $conn->prepare("INSERT IGNORE INTO Matches (user1_id, user2_id, user_low_id, user_high_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiii", $user_id, $target_id, $user_low, $user_high);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success_message'] = "It's a match! 🎉";
        header('Location: who-liked-me.php');
        exit;
    }
}

$page_title = "Who Liked Me";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo $page_title; ?> | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <style>
        .premium-header {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 50px 30px;
            text-align: center;
            margin-bottom: 40px;
            border-radius: 16px;
        }
        
        .premium-header h1 {
            font-size: 2.2rem;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--text);
            letter-spacing: -0.02em;
        }
        
        .premium-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(251,191,36,0.15);
            color: #fbbf24;
            border: 1px solid rgba(251,191,36,0.3);
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            letter-spacing: 0.02em;
            margin-bottom: 24px;
        }
        
        .likes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            padding: 0 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .like-card {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
            aspect-ratio: 3/4;
            display: flex;
            flex-direction: column;
        }
        
        .like-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.2);
            border-color: rgba(255,255,255,0.2);
        }
        
        .like-card.premium-liker {
            border-color: rgba(251,191,36,0.3);
        }
        
        .like-card.premium-liker::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #fbbf24, #f59e0b);
            z-index: 10;
        }
        
        .like-photo-container {
            position: relative;
            flex: 1;
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .like-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .like-photo-placeholder {
            font-size: 4rem;
            color: rgba(255,255,255,0.7);
        }
        
        .like-premium-indicator {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(10px);
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        
        .like-info-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            color: white;
            padding: 30px 16px 16px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        
        .like-user-info {
            flex: 1;
        }
        
        .like-name {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .like-details {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 2px;
        }
        
        .like-date {
            font-size: 0.8rem;
            opacity: 0.7;
        }
        
        .like-actions {
            display: flex;
            gap: 8px;
            margin-left: 12px;
        }
        
        .btn-like-back, .btn-view-profile {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            text-decoration: none;
        }
        
        .btn-like-back:hover {
            background: rgba(236,72,153,0.9);
            border-color: rgba(236,72,153,1);
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(236,72,153,0.4);
        }
        
        .btn-view-profile:hover {
            background: rgba(124,58,237,0.9);
            border-color: rgba(124,58,237,1);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(124,58,237,0.4);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 16px;
            color: var(--text);
        }
        
        .liker-premium-badge {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .premium-section {
            text-align: center;
            margin: 60px auto;
            padding: 40px 30px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            max-width: 700px;
        }
        
        .premium-section h3 {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--text);
        }
        
        .premium-section p {
            opacity: 0.85;
            line-height: 1.6;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="container">
        <div class="premium-header">
            <div class="premium-badge">
                <span>⭐</span>
                Premium Feature
            </div>
            <h1>Who Liked Me</h1>
            <p style="opacity: 0.8; font-size: 1rem; max-width: 500px; margin: 0 auto;">
                Discover everyone who's interested in your profile
            </p>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($liked_by_users)): ?>
            <div class="empty-state">
                <h3>No likes yet</h3>
                <p>When someone likes your profile, they'll appear here!</p>
                <p style="margin-top: 20px;">
                    <a href="discover.php" class="btn" style="display: inline-block; padding: 12px 24px;">
                        Start Discovering
                    </a>
                </p>
            </div>
        <?php else: ?>
            <div class="likes-grid">
                <?php foreach ($liked_by_users as $liker): ?>
                    <div class="like-card <?php echo !empty($liker['liker_plan']) ? 'premium-liker' : ''; ?>">
                        <div class="like-photo-container">
                            <?php if ($liker['photo']): ?>
                                <img src="MBusers/photos/<?php echo htmlspecialchars($liker['photo']); ?>" 
                                     alt="<?php echo htmlspecialchars($liker['name']); ?>" 
                                     class="like-photo">
                            <?php else: ?>
                                <div class="like-photo-placeholder">👤</div>
                            <?php endif; ?>
                            
                            <?php if (!empty($liker['liker_plan'])): ?>
                                <div class="like-premium-indicator">
                                    <?php 
                                    switch ($liker['liker_plan']) {
                                        case 'boost_2x': echo '🚀'; break;
                                        case 'boost_5x': echo '⚡'; break;
                                        case 'boost_10x': echo '💎'; break;
                                        default: echo '⭐'; break;
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="like-info-overlay">
                                <div class="like-user-info">
                                    <div class="like-name">
                                        <?php echo htmlspecialchars($liker['name']); ?>, <?php echo $liker['age']; ?>
                                    </div>
                                    <div class="like-details">
                                        <?php echo ucfirst($liker['gender']); ?>
                                        <?php if ($liker['bio']): ?>
                                            • <?php echo htmlspecialchars(mb_strimwidth($liker['bio'], 0, 40, "...")); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="like-date">
                                        Liked <?php echo date('M j', strtotime($liker['liked_date'])); ?>
                                    </div>
                                </div>
                                
                                <div class="like-actions">
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="like_back">
                                        <input type="hidden" name="target_id" value="<?php echo $liker['id']; ?>">
                                        <button type="submit" class="btn-like-back" title="Like Back">
                                            ❤️
                                        </button>
                                    </form>
                                    <a href="profile_view.php?id=<?php echo $liker['id']; ?>" class="btn-view-profile" title="View Profile">
                                        👀
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="premium-section">
            <h3>Premium Benefits</h3>
            <p>
                As a premium member, you can see everyone who liked your profile and like them back instantly. 
                This feature helps you discover mutual interests faster and increases your chances of making meaningful connections.
            </p>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>