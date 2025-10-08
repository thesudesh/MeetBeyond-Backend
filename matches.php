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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Matches | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .match-card {
            position: relative;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            border: 2px solid rgba(236,72,153,0.2);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        .match-card:hover {
            transform: translateY(-8px);
            border-color: rgba(236,72,153,0.5);
            box-shadow: 0 20px 40px rgba(236,72,153,0.3);
        }
        .match-card-image {
            position: relative;
            height: 280px;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(139,92,246,0.3), rgba(236,72,153,0.3));
        }
        .match-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .match-card:hover .match-card-image img {
            transform: scale(1.08);
        }
        .match-badge {
            position: absolute;
            top: 16px;
            right: 16px;
            background: rgba(236,72,153,0.95);
            backdrop-filter: blur(10px);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(236,72,153,0.4);
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .match-card-content {
            padding: 24px;
        }
        .match-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .match-details {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }
        .match-detail-badge {
            padding: 6px 12px;
            background: rgba(167,139,250,0.15);
            border: 1px solid rgba(167,139,250,0.3);
            border-radius: 12px;
            font-size: 0.85rem;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .match-bio {
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .match-actions {
            display: flex;
            gap: 10px;
        }
        .match-action-btn {
            flex: 1;
            padding: 14px;
            border-radius: 12px;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-message {
            background: linear-gradient(135deg, rgba(236,72,153,0.3), rgba(219,39,119,0.3));
            color: #ec4899;
            border: 2px solid rgba(236,72,153,0.4);
        }
        .btn-message:hover {
            background: linear-gradient(135deg, rgba(236,72,153,0.4), rgba(219,39,119,0.4));
            border-color: rgba(236,72,153,0.6);
            transform: translateY(-2px);
        }
        .btn-profile {
            background: rgba(167,139,250,0.15);
            color: var(--text);
            border: 2px solid rgba(167,139,250,0.3);
        }
        .btn-profile:hover {
            background: rgba(167,139,250,0.25);
            border-color: rgba(167,139,250,0.5);
            transform: translateY(-2px);
        }
        .matches-header {
            text-align: center;
            margin-bottom: 48px;
            padding: 40px 20px;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 2px solid rgba(236,72,153,0.2);
        }
        .matches-count {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ec4899, #db2777);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
        }
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 2px dashed rgba(167,139,250,0.3);
        }
        .empty-state-icon {
            font-size: 5rem;
            margin-bottom: 24px;
            opacity: 0.6;
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
                <h1 style="font-size: 2.2rem; font-weight: 700; margin-bottom: 12px; color: var(--text)">
                    Your Matches
                </h1>
                <p style="font-size: 1.1rem; color: var(--muted);">
                    You've connected with these amazing people. Start a conversation!
                </p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 28px;">
                <?php foreach ($matches as $match):
                    $profile = $profiles[$match['matched_user_id']];
                    $display_name = $profile['name'] ?: $profile['email'];
                    $has_photo = !empty($profile['file_path']);
                    $bio = $profile['bio'] ?? '';
                    $gender = $profile['gender'] ?? '';
                    $age = $profile['age'] ?? '';
                ?>
                    <article class="match-card">
                        <div class="match-card-image">
                            <?php if ($has_photo): ?>
                                <img src="MBusers/photos/<?php echo htmlspecialchars($profile['file_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($display_name); ?>">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 6rem; background: linear-gradient(135deg, rgba(139,92,246,0.4), rgba(236,72,153,0.4));">
                                    <?php echo strtoupper(substr($display_name, 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="match-badge">
                                ❤️ It's a Match!
                            </div>
                        </div>

                        <div class="match-card-content">
                            <div class="match-name">
                                <?php echo htmlspecialchars($display_name); ?>
                                <?php if ($age): ?>
                                    <span style="font-size: 1.2rem; color: var(--muted); font-weight: 600;"><?php echo $age; ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($gender || $age): ?>
                                <div class="match-details">
                                    <?php if ($gender): ?>
                                        <span class="match-detail-badge">
                                            👤 <?php echo ucfirst(htmlspecialchars($gender)); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($age): ?>
                                        <span class="match-detail-badge">
                                            🎂 <?php echo htmlspecialchars($age); ?> years
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($bio): ?>
                                <div class="match-bio">
                                    <?php echo htmlspecialchars($bio); ?>
                                </div>
                            <?php endif; ?>

                            <div class="match-actions">
                                <a href="messages.php?match=<?php echo $match['match_id']; ?>" class="match-action-btn btn-message">
                                    💬 Message
                                </a>
                                <a href="profile_view.php?id=<?php echo $profile['id']; ?>" class="match-action-btn btn-profile">
                                    👤 Profile
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<a href="index.php" class="back-btn" title="Back to Dashboard">←</a>

<?php include_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>