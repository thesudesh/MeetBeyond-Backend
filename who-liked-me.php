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
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));
            padding: 40px 20px;
            text-align: center;
            margin-bottom: 30px;
            border-radius: 16px;
        }
        
        .premium-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 12px;
            text-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .premium-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: #1f2937;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
        }
        
        .likes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            padding: 0 20px;
        }
        
        .like-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 24px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .like-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            border-color: rgba(255,255,255,0.2);
        }
        
        .like-card.premium-liker {
            border-color: rgba(251,191,36,0.3);
            background: linear-gradient(135deg, rgba(251,191,36,0.1), rgba(245,158,11,0.05));
        }
        
        .like-card.premium-liker::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #fbbf24, #f59e0b);
        }
        
        .like-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.2);
            margin-bottom: 16px;
        }
        
        .like-info h3 {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .like-meta {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 12px;
        }
        
        .like-bio {
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.4;
            margin-bottom: 20px;
        }
        
        .like-actions {
            display: flex;
            gap: 12px;
        }
        
        .btn-like-back {
            flex: 1;
            background: linear-gradient(135deg, #ec4899, #db2777);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-like-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(236,72,153,0.3);
        }
        
        .btn-view-profile {
            background: rgba(255,255,255,0.1);
            color: var(--text);
            border: 2px solid rgba(255,255,255,0.2);
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .btn-view-profile:hover {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.3);
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
            font-size: 1.1rem;
            filter: drop-shadow(0 2px 4px rgba(251,191,36,0.5));
        }
        
        .liked-date {
            font-size: 0.8rem;
            color: var(--muted);
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="container">
        <div class="premium-header">
            <div class="premium-badge">
                <span>✨</span>
                Premium Feature
            </div>
            <h1>Who Liked Me</h1>
            <p style="opacity: 0.9; font-size: 1.1rem;">
                See everyone who liked your profile
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
                        <?php if (!empty($liker['liker_plan'])): ?>
                            <div style="position: absolute; top: 16px; right: 16px;">
                                <span class="liker-premium-badge">
                                    <?php 
                                    switch ($liker['liker_plan']) {
                                        case 'boost_2x': echo '🚀'; break;
                                        case 'boost_5x': echo '⚡'; break;
                                        case 'boost_10x': echo '💎'; break;
                                        default: echo '✨'; break;
                                    }
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div style="text-align: center;">
                            <?php if ($liker['photo']): ?>
                                <img src="MBusers/photos/<?php echo htmlspecialchars($liker['photo']); ?>" 
                                     alt="<?php echo htmlspecialchars($liker['name']); ?>" 
                                     class="like-avatar">
                            <?php else: ?>
                                <div class="like-avatar" style="background: var(--accent-purple); display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                                    👤
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="like-info">
                            <h3>
                                <?php echo htmlspecialchars($liker['name']); ?>, <?php echo $liker['age']; ?>
                                <?php if (!empty($liker['liker_plan'])): ?>
                                    <span class="liker-premium-badge">
                                        <?php 
                                        switch ($liker['liker_plan']) {
                                            case 'boost_2x': echo '🚀'; break;
                                            case 'boost_5x': echo '⚡'; break;
                                            case 'boost_10x': echo '💎'; break;
                                            default: echo '✨'; break;
                                        }
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </h3>
                            
                            <div class="like-meta">
                                <?php echo ucfirst($liker['gender']); ?> • 
                                <span class="liked-date">
                                    Liked <?php echo date('M j, Y', strtotime($liker['liked_date'])); ?>
                                </span>
                            </div>
                            
                            <?php if ($liker['bio']): ?>
                                <div class="like-bio">
                                    <?php echo nl2br(htmlspecialchars(mb_strimwidth($liker['bio'], 0, 100, "..."))); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="like-actions">
                            <form method="post" style="flex: 1;">
                                <input type="hidden" name="action" value="like_back">
                                <input type="hidden" name="target_id" value="<?php echo $liker['id']; ?>">
                                <button type="submit" class="btn-like-back">
                                    <span>💖</span>
                                    Like Back
                                </button>
                            </form>
                            <a href="profile_view.php?id=<?php echo $liker['id']; ?>" class="btn-view-profile">
                                View Profile
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin: 60px 0; padding: 40px; background: rgba(255,255,255,0.05); border-radius: 16px;">
            <h3 style="margin-bottom: 16px;">Premium Benefits</h3>
            <p style="opacity: 0.8; max-width: 600px; margin: 0 auto;">
                As a premium member, you can see everyone who liked your profile and like them back instantly. 
                This feature helps you discover mutual interests faster and increases your chances of making meaningful connections.
            </p>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>