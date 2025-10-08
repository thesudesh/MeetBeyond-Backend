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
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container">
    <section class="card" style="max-width:980px;margin:0 auto">
        <div class="page-top">
            <h2 class="page-title">Your Matches</h2>
            <p class="lead">Start a conversation or view profiles below.</p>
        </div>

                <?php if (empty($matches)): ?>
            <div class="text-center" style="padding:60px 20px;color:var(--muted)">
                <div style="font-size:3rem;margin-bottom:16px">💑</div>
                <h3 style="font-size:1.3rem;margin-bottom:8px;color:var(--text)">No matches yet</h3>
                <p style="margin-bottom:24px">Keep browsing profiles to find your perfect match!</p>
                <a href="browse.php" class="btn">Start Browsing</a>
            </div>
        <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;margin-top:24px">
                <?php foreach ($matches as $match):
                    $profile = $profiles[$match['matched_user_id']];
                    $display_name = $profile['name'] ?: $profile['email'];
                    $display_age = $profile['age'] ? " ({$profile['age']})" : "";
                ?>
                    <article style="background:var(--panel-bg);border-radius:14px;padding:24px;border:1px solid rgba(255,255,255,0.08);transition:var(--transition)">
                        <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
                            <?php if (!empty($profile['photo'])): ?>
                                <img src="MBusers/photos/<?php echo $profile['photo']; ?>" alt="<?php echo htmlspecialchars($display_name); ?>" style="width:70px;height:70px;border-radius:12px;object-fit:cover;border:2px solid rgba(255,255,255,0.1)">
                            <?php else: ?>
                                <div style="width:70px;height:70px;border-radius:12px;background:linear-gradient(135deg,var(--accent-purple),var(--accent-pink));display:flex;align-items:center;justify-content:center;font-size:2rem">
                                    <?php echo strtoupper(substr($display_name, 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div style="flex:1">
                                <div style="font-weight:700;font-size:1.1rem;color:var(--text);margin-bottom:4px">
                                    <?php echo htmlspecialchars($display_name); ?>
                                </div>
                                <div style="color:var(--muted);font-size:0.95rem">
                                    <?php if ($profile['age']) echo $profile['age'] . ' years old'; ?>
                                </div>
                            </div>
                        </div>

                        <div style="display:flex;gap:10px">
                            <a class="btn-ghost" style="flex:1;justify-content:center;font-size:0.95rem" href="profile_view.php?id=<?php echo $profile['id']; ?>">View Profile</a>
                            <a class="btn" style="flex:1;justify-content:center;font-size:0.95rem" href="messages.php?match=<?php echo $match['match_id']; ?>">💬 Message</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<a href="index.php" class="back-btn" title="Back to Dashboard">←</a>

<?php include_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>