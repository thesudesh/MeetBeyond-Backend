<?php
session_start();
require 'config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

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

// Fetch profile info for each matched user
$profiles = [];
if ($matches) {
    $ids = implode(',', array_map('intval', array_column($matches, 'matched_user_id')));
    $sql = "SELECT u.id, u.email, p.name, p.age FROM Users u LEFT JOIN Profiles p ON u.id = p.user_id WHERE u.id IN ($ids)";
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
</head>
<body>
<div class="dashboard-hero">
    <div class="dashboard-box">
        <h2 class="dashboard-title" style="margin-bottom: 10px;">Your Matches</h2>
        <p class="dashboard-subtitle" style="margin-bottom: 34px;">Start a conversation or view profiles below.</p>
        <div class="dashboard-cards">
            <?php if (empty($matches)): ?>
                <div style="width:100%;padding:40px 0;color:#7b7ce9;font-size:1.1rem;">
                    You don’t have any matches yet.
                </div>
            <?php else: ?>
                <?php foreach ($matches as $match): 
                    $profile = $profiles[$match['matched_user_id']];
                    $display_name = $profile['name'] ?: $profile['email'];
                    $display_age = $profile['age'] ? " ({$profile['age']})" : "";
                ?>
                <div class="dashboard-card" style="min-width:200px;">
                    <span class="icon" style="font-size:2.3rem;">🤝</span>
                    <div style="font-weight:700;margin-bottom:8px;"><?php echo htmlspecialchars($display_name . $display_age); ?></div>
                    <div style="margin-bottom:14px;">
                        <a href="profile.php?user=<?php echo $profile['id']; ?>" style="color:#7b7ce9;text-decoration:underline;font-size:0.97em;">View Profile</a>
                        <br>
                        <a href="messages.php?match=<?php echo $match['match_id']; ?>" style="color:#9e71e6;text-decoration:underline;font-size:0.97em;">Message</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <a href="index.php" style="display:block;margin-top:40px;color:#aaa;text-decoration:underline;font-size:0.95em;">&#8592; Back to Dashboard</a>
    </div>
</div>
</body>
</html>