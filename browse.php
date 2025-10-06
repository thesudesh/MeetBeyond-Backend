<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch current user's preferences (for a real app, use these to filter users)
$stmt = $conn->prepare("SELECT min_age, max_age, gender_pref, location FROM Preferences WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($min_age, $max_age, $gender_pref, $location);
$stmt->fetch();
$stmt->close();

// For now: fetch random users that are NOT the current user and have a primary photo, name, and age
$sql = "
    SELECT u.id, p.name, p.age, p.gender, p.bio, ph.file_path
    FROM Users u
    JOIN Profiles p ON u.id = p.user_id
    JOIN Photos ph ON ph.user_id = u.id AND ph.is_primary=1 AND ph.is_active=1
    WHERE u.id != ? AND p.name IS NOT NULL AND p.age IS NOT NULL
    ORDER BY RAND() 
    LIMIT 12
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($other_id, $other_name, $other_age, $other_gender, $other_bio, $photo_path);

$users = [];
while ($stmt->fetch()) {
    $users[] = [
        'id' => $other_id,
        'name' => $other_name,
        'age' => $other_age,
        'gender' => $other_gender,
        'bio' => $other_bio,
        'photo' => $photo_path
    ];
}
$stmt->close();
$photo_base = "MBusers/photos/";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Browse | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container">
    <div class="card">
        <div class="page-top">
            <div>
                <h1 class="page-title">Browse Profiles</h1>
                <p class="lead">Discover people on <span class="brand"><span class="accent">Meet</span> Beyond</span> — swipe, like, or view profiles.</p>
            </div>
            <div class="text-center muted" style="min-width:160px">Explore · Safe · Real</div>
        </div>

        <?php if (empty($users)): ?>
            <div class="text-center" style="padding:36px 0;color:var(--muted);">No profiles to show right now. Try again soon.</div>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($users as $u): ?>
                    <article class="profile-card">
                        <img class="profile-photo" src="<?php echo $photo_base . htmlspecialchars($u['photo']); ?>" alt="Profile Photo">
                        <div class="profile-body">
                            <div class="profile-name"><?php echo htmlspecialchars($u['name']); ?></div>
                            <div class="profile-meta"><?php echo htmlspecialchars($u['age']); ?> • <?php echo ucfirst(htmlspecialchars($u['gender'])); ?></div>
                            <div class="profile-bio"><?php $bio = trim($u['bio']); echo $bio ? htmlspecialchars(mb_strimwidth($bio, 0, 140, "...")) : '<span class="muted">No bio yet</span>'; ?></div>
                            <div class="profile-actions">
                                <button class="icon-btn" title="Pass" aria-label="Pass" onclick="alert('Pass feature coming soon!');">
                                    <svg aria-hidden="true"><use xlink:href="assets/icons.svg#icon-cross"></use></svg>
                                </button>
                                <a class="icon-btn view" title="View Profile" aria-label="View Profile" href="profile.php?user=<?php echo $u['id']; ?>">
                                    <svg aria-hidden="true"><use xlink:href="assets/icons.svg#icon-view"></use></svg>
                                </a>
                                <button class="icon-btn like" title="Like" aria-label="Like" onclick="alert('Like feature coming soon!');">
                                    <svg aria-hidden="true"><use xlink:href="assets/icons.svg#icon-heart"></use></svg>
                                </button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="text-center" style="margin-top:20px">
            <a href="index.php" class="btn-ghost"><svg aria-hidden="true" style="width:16px;height:16px;vertical-align:middle"><use xlink:href="assets/icons.svg#icon-back"></use></svg> Back to Dashboard</a>
        </div>
    </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>