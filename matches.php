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
            <div class="muted" style="padding:36px 0;margin-top:12px">You don’t have any matches yet.</div>
        <?php else: ?>
            <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:18px;margin-top:18px">
                <?php foreach ($matches as $match):
                    $profile = $profiles[$match['matched_user_id']];
                    $display_name = $profile['name'] ?: $profile['email'];
                    $display_age = $profile['age'] ? " ({$profile['age']})" : "";
                ?>
                    <article class="profile-card">
                        <div class="profile-top" style="align-items:center;gap:12px">
                            <?php if (!empty($profile['photo'])): ?>
                                <img src="MBusers/photos/<?php echo $profile['photo']; ?>" alt="<?php echo htmlspecialchars($display_name); ?>" class="avatar" style="width:64px;height:64px;border-radius:10px;object-fit:cover">
                            <?php else: ?>
                                <div class="avatar" style="width:64px;height:64px;border-radius:10px;background:linear-gradient(135deg,var(--accent-2),var(--accent));"></div>
                            <?php endif; ?>
                            <div>
                                <div class="label" style="font-weight:800"><?php echo htmlspecialchars($display_name . $display_age); ?></div>
                                <div class="muted" style="font-size:0.9rem"><?php if ($profile['age']) echo $profile['age'] . ' yrs'; ?></div>
                            </div>
                        </div>

                        <div class="profile-actions" style="margin-top:12px;display:flex;gap:8px">
                            <a class="btn-ghost" href="profile.php?user=<?php echo $profile['id']; ?>"><svg aria-hidden><use xlink:href="assets/icons.svg#icon-view"></use></svg> View</a>
                            <a class="btn" href="messages.php?match=<?php echo $match['match_id']; ?>"><svg aria-hidden><use xlink:href="assets/icons.svg#icon-messages"></use></svg> Message</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div style="margin-top:24px">
            <a href="index.php" class="btn-ghost">← Back to Dashboard</a>
        </div>
    </section>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>