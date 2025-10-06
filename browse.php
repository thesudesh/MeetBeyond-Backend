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
    <style>
    body {
        background: linear-gradient(135deg, #fff6fa 0%, #eae6fd 100%);
    }
    .browse-section {
        max-width: 1100px;
        margin: 58px auto 0 auto;
        padding: 0 16px;
    }
    .browse-title {
        font-size: 2.3em;
        font-weight: 900;
        color: #7b7ce9;
        text-align: center;
        letter-spacing: 0.02em;
        margin-bottom: 12px;
    }
    .browse-desc {
        font-size: 1.16em;
        text-align: center;
        color: #a06ee5;
        margin-bottom: 32px;
    }
    .card-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 36px;
        justify-content: center;
    }
    .match-card {
        background: #fff;
        border-radius: 22px;
        box-shadow: 0 6px 24px #a06ee533;
        width: 270px;
        padding: 0 0 23px 0;
        position: relative;
        transition: box-shadow 0.17s, transform 0.17s;
        display: flex;
        flex-direction: column;
        align-items: center;
        overflow: hidden;
    }
    .match-card:hover {
        box-shadow: 0 12px 36px #7b7ce977;
        transform: translateY(-7px) scale(1.025);
    }
    .match-photo {
        width: 100%;
        height: 330px;
        object-fit: cover;
        background: #eee;
        border-bottom: 2px solid #f2eaff;
        transition: filter 0.17s;
    }
    .match-card:hover .match-photo {
        filter: brightness(1.06) saturate(1.07);
    }
    .match-info {
        padding: 16px 18px 0 18px;
        text-align: center;
        flex: 1;
    }
    .match-name {
        font-size: 1.27em;
        font-weight: 700;
        color: #7b7ce9;
        margin-bottom: 2px;
    }
    .match-age-gender {
        color: #a06ee5;
        font-size: 1.02em;
        margin-bottom: 6px;
    }
    .match-bio {
        color: #7676a7;
        font-size: 1.04em;
        margin-bottom: 12px;
        min-height: 38px;
    }
    .card-action {
        display: flex;
        justify-content: center;
        gap: 18px;
    }
    .like-btn, .pass-btn, .view-btn {
        border: none;
        border-radius: 50%;
        font-size: 1.45em;
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: box-shadow 0.18s, background 0.18s, transform 0.13s;
    }
    .like-btn {
        background: linear-gradient(145deg, #f7c3e8 35%, #a06ee5 100%);
        color: #fff;
        box-shadow: 0 2px 9px #f7c3e855;
    }
    .like-btn:hover {
        background: linear-gradient(145deg, #a06ee5 0%, #f7c3e8 85%);
        box-shadow: 0 7px 24px #a06ee566;
        transform: scale(1.12);
    }
    .pass-btn {
        background: #f5f6fa;
        color: #aaa;
        box-shadow: 0 2px 9px #a06ee522;
    }
    .pass-btn:hover {
        background: #fff0f5;
        color: #e75b7b;
        box-shadow: 0 6px 16px #f7c3e833;
        transform: scale(1.11);
    }
    .view-btn {
        background: #f7f7fe;
        color: #7b7ce9;
        font-size: 1.1em;
        width: 42px;
        height: 42px;
        border-radius: 12px;
        margin-top: 2px;
    }
    .view-btn:hover {
        background: #eae6fd;
        color: #a06ee5;
        box-shadow: 0 3px 12px #a06ee522;
        transform: scale(1.09);
    }
    @media (max-width: 700px) {
        .card-grid { gap: 20px; }
        .match-card { width: 94vw; max-width: 330px; }
        .match-photo { height: 220px; }
    }
    </style>
</head>
<body>
<div class="browse-section">
    <div class="browse-title">Browse Profiles</div>
    <div class="browse-desc">
        Discover amazing people on <span class="brand">Meet Beyond</span>.
        <br>Swipe right to like, left to pass, or view more!
    </div>
    <div class="card-grid">
        <?php if (empty($users)): ?>
            <div style="width:100%;padding:40px 0;color:#7b7ce9;font-size:1.1rem;">
                No profiles to show right now. Try again soon!
            </div>
        <?php else: foreach ($users as $u): ?>
            <div class="match-card">
                <img class="match-photo" src="<?php echo $photo_base . htmlspecialchars($u['photo']); ?>" alt="Profile Photo">
                <div class="match-info">
                    <div class="match-name"><?php echo htmlspecialchars($u['name']); ?></div>
                    <div class="match-age-gender">
                        <?php echo htmlspecialchars($u['age']); ?> &bull; <?php echo ucfirst(htmlspecialchars($u['gender'])); ?>
                    </div>
                    <div class="match-bio">
                        <?php
                        $bio = trim($u['bio']);
                        echo $bio ? htmlspecialchars(mb_strimwidth($bio, 0, 80, "...")) : "<span style='color:#bcbcf0'>No bio yet</span>";
                        ?>
                    </div>
                    <div class="card-action">
                        <button class="pass-btn" title="Pass" onclick="alert('Pass feature coming soon!');">✖️</button>
                        <a class="view-btn" title="View Profile" href="profile.php?user=<?php echo $u['id']; ?>">👁️</a>
                        <button class="like-btn" title="Like" onclick="alert('Like feature coming soon!');">❤️</button>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
    <div style="text-align:center;margin:44px 0 0 0;">
        <a href="index.php" style="color:#aaa;text-decoration:underline;font-size:0.96em;">&#8592; Back to Dashboard</a>
    </div>
</div>
</body>
</html>