<?php
session_start();
require 'config.php';

// Redirect to login if not logged in
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

// Get all matches for this user (either as user1 or user2)
$stmt = $conn->prepare("
    SELECT 
        m.id AS match_id,
        CASE 
            WHEN m.user1_id = ? THEN m.user2_id
            ELSE m.user1_id
        END AS matched_user_id
    FROM Matches m
    WHERE m.user1_id = ? OR m.user2_id = ?
    ORDER BY m.matched_at DESC
");
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$stmt->bind_result($match_id, $matched_user_id);

$matches = [];
while ($stmt->fetch()) {
    $matches[] = ['match_id' => $match_id, 'matched_user_id' => $matched_user_id];
}
$stmt->close();

// Fetch profile info for each matched user including photos
$profiles = [];
if ($matches) {
    $ids = implode(',', array_map('intval', array_column($matches, 'matched_user_id')));
    $sql = "SELECT u.id, u.email, p.name, p.age, p.bio, p.gender, ph.file_path 
            FROM Users u 
            LEFT JOIN Profiles p ON u.id = p.user_id 
            LEFT JOIN Photos ph ON ph.user_id = u.id AND ph.is_primary = 1 AND ph.is_active = 1
            WHERE u.id IN ($ids)";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $profiles[$row['id']] = $row;
    }
}

$photo_base = "MBusers/photos/";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Matches | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .matches-header {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 24px 20px;
            text-align: center;
            margin-bottom: 32px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
        }
        
        .matches-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
            color: var(--text);
            letter-spacing: -0.02em;
        }
        
        .matches-count {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ec4899, #db2777);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
            min-width: 60px;
        }
        
        .matches-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .match-card {
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
        
        .match-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.2);
            border-color: rgba(236,72,153,0.3);
        }
        
        .match-photo-container {
            position: relative;
            flex: 1;
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .match-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .match-photo-placeholder {
            font-size: 4rem;
            color: rgba(255,255,255,0.7);
        }
        
        .match-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(236,72,153,0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 6px 12px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 4px 12px rgba(236,72,153,0.4);
        }
        
        .match-info-overlay {
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
        
        .match-user-info {
            flex: 1;
        }
        
        .match-name {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .match-details {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 2px;
        }
        
        .match-bio {
            font-size: 0.8rem;
            opacity: 0.8;
            line-height: 1.3;
        }
        
        .match-actions {
            padding: 16px;
            display: flex;
            gap: 8px;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(10px);
        }
        
        .match-action-btn {
            flex: 1;
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 12px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.9rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        
        .btn-message:hover {
            background: rgba(236,72,153,0.8);
            border-color: rgba(236,72,153,1);
            transform: translateY(-1px);
        }
        
        .btn-profile:hover {
            background: rgba(124,58,237,0.8);
            border-color: rgba(124,58,237,1);
            transform: translateY(-1px);
        }
        
        .empty-state {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 80px 40px;
            text-align: center;
            border: 2px dashed rgba(167,139,250,0.3);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 24px;
            opacity: 0.6;
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
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container" style="padding: 28px 20px;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <?php if (empty($matches)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">💑</div>
                <h2 style="font-size: 2rem; font-weight: 700; margin-bottom: 16px; color: var(--text)">
                    No Matches Yet
                </h2>
                <p style="font-size: 1.1rem; color: var(--muted); margin-bottom: 32px; max-width: 500px; margin-left: auto; margin-right: auto;">
                    Start discovering amazing people and you'll find your matches here. Your perfect connection is just a swipe away!
                </p>
                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                    <a href="discover.php" class="btn" style="padding: 16px 32px; font-size: 1.05rem;">
                        💖 Start Discovering
                    </a>
                    <a href="browse.php" class="btn-ghost" style="padding: 16px 32px; font-size: 1.05rem;">
                        👥 Browse Profiles
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="matches-header">
                <div class="matches-count"><?php echo count($matches); ?></div>
                <h1>Your Matches</h1>
            </div>

            <div class="matches-grid">
                <?php foreach ($matches as $match):
                    $profile = $profiles[$match['matched_user_id']];
                    $display_name = $profile['name'] ?: $profile['email'];
                    $has_photo = !empty($profile['file_path']);
                    $bio = $profile['bio'] ?? '';
                    $gender = $profile['gender'] ?? '';
                    $age = $profile['age'] ?? '';
                ?>
                    <div class="match-card">
                        <div class="match-photo-container">
                            <?php if ($has_photo): ?>
                                <img src="<?= $photo_base . htmlspecialchars($profile['file_path']) ?>" alt="<?= htmlspecialchars($display_name) ?>" class="match-photo">
                            <?php else: ?>
                                <div class="match-photo-placeholder">👤</div>
                            <?php endif; ?>
                            
                            <div class="match-badge">
                                <span>💕</span>
                                Match
                            </div>
                            
                            <div class="match-info-overlay">
                                <div class="match-user-info">
                                    <div class="match-name">
                                        <?= htmlspecialchars($display_name) ?>
                                    </div>
                                    <div class="match-details">
                                        <?php 
                                        if ($age) echo $age . ' years old';
                                        if ($gender) echo ' • ' . ucfirst(htmlspecialchars($gender));
                                        ?>
                                    </div>
                                    <?php if ($bio): ?>
                                        <div class="match-bio">
                                            <?= htmlspecialchars(substr($bio, 0, 80)) ?><?= strlen($bio) > 80 ? '...' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="match-actions">
                            <a href="messages.php?match=<?= $match['match_id'] ?>" class="match-action-btn btn-message">
                                <span>💬</span>
                                Message
                            </a>
                            <a href="profile_view.php?id=<?= $profile['id'] ?>" class="match-action-btn btn-profile">
                                <span>👤</span>
                                Profile
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<a href="index.php" class="back-btn" title="Back to Dashboard">←</a>

<?php include_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>