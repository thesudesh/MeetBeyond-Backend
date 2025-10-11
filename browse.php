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

// Fetch current user's preferences (for a real app, use these to filter users)
$stmt = $conn->prepare("SELECT min_age, max_age, gender_pref, location FROM Preferences WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($min_age, $max_age, $gender_pref, $location);
$stmt->fetch();
$stmt->close();

// Fetch random users (exclude current user, admins, users without complete profiles, and filter by gender preference)
$sql = "
    SELECT u.id, p.name, p.age, p.gender, p.bio, ph.file_path
    FROM Users u
    JOIN Profiles p ON u.id = p.user_id
    JOIN Photos ph ON ph.user_id = u.id AND ph.is_primary=1 AND ph.is_active=1
    WHERE u.id != ? 
    AND u.role != 'admin'
    AND p.name IS NOT NULL 
    AND p.age IS NOT NULL
";

// Add gender preference filter if set
if (!empty($gender_pref) && $gender_pref !== 'both' && $gender_pref !== 'any') {
    $sql .= " AND p.gender = ?";
    $use_gender_filter = true;
} else {
    $use_gender_filter = false;
}

// Add age preference filter if set
if (!empty($min_age) && !empty($max_age)) {
    $sql .= " AND p.age BETWEEN ? AND ?";
    $use_age_filter = true;
} else {
    $use_age_filter = false;
}

$sql .= " ORDER BY RAND() LIMIT 12";

$stmt = $conn->prepare($sql);

// Bind parameters based on what filters are active
if ($use_gender_filter && $use_age_filter) {
    $stmt->bind_param("isii", $user_id, $gender_pref, $min_age, $max_age);
} elseif ($use_gender_filter) {
    $stmt->bind_param("is", $user_id, $gender_pref);
} elseif ($use_age_filter) {
    $stmt->bind_param("iii", $user_id, $min_age, $max_age);
} else {
    $stmt->bind_param("i", $user_id);
}
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
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .browse-header {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 50px 30px;
            text-align: center;
            margin-bottom: 40px;
            border-radius: 16px;
        }
        
        .browse-header h1 {
            font-size: 2.2rem;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--text);
            letter-spacing: -0.02em;
        }
        
        .browse-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            padding: 0 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .browse-card {
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
        
        .browse-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.2);
            border-color: rgba(255,255,255,0.2);
        }
        
        .browse-photo-container {
            position: relative;
            flex: 1;
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .browse-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .browse-photo-placeholder {
            font-size: 4rem;
            color: rgba(255,255,255,0.7);
        }
        
        .browse-info-overlay {
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
        
        .browse-user-info {
            flex: 1;
        }
        
        .browse-name {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .browse-details {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 2px;
        }
        
        .browse-bio {
            font-size: 0.8rem;
            opacity: 0.8;
            line-height: 1.3;
        }
        
        .browse-actions {
            display: flex;
            gap: 8px;
            margin-left: 12px;
        }
        
        .btn-browse-view {
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
        
        .btn-browse-view:hover {
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
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container">
    <div class="browse-header">
        <h1>Browse Profiles</h1>
        <p style="opacity: 0.8; font-size: 1rem; max-width: 500px; margin: 0 auto;">
            Discover amazing people on Meet Beyond — view profiles and make connections
        </p>
        
        <?php if (!empty($gender_pref) || (!empty($min_age) && !empty($max_age))): ?>
            <div style="margin-top: 16px; padding: 12px 20px; background: rgba(167,139,250,0.1); border: 1px solid rgba(167,139,250,0.3); border-radius: 12px; display: inline-block;">
                <span style="color: var(--accent-purple); font-weight: 600; font-size: 0.9rem;">
                    🎯 Filtered by your preferences: 
                    <?php if (!empty($gender_pref) && $gender_pref !== 'both' && $gender_pref !== 'any'): ?>
                        <?php echo ucfirst($gender_pref); ?> profiles
                    <?php else: ?>
                        All genders
                    <?php endif; ?>
                    <?php if (!empty($min_age) && !empty($max_age)): ?>
                        , Ages <?php echo $min_age; ?>-<?php echo $max_age; ?>
                    <?php endif; ?>
                </span>
                <a href="preferences.php" style="color: var(--accent-purple); text-decoration: none; margin-left: 8px; font-size: 0.85rem;">
                    ⚙️ Edit
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($users)): ?>
        <div class="empty-state">
            <?php if (!empty($gender_pref) && $gender_pref !== 'both' && $gender_pref !== 'any'): ?>
                <h3>No <?php echo strtolower($gender_pref); ?> profiles found</h3>
                <p>No profiles match your current preferences. Try adjusting your filters or check back later!</p>
                <a href="preferences.php" class="btn" style="margin-top: 16px; text-decoration: none;">
                    ⚙️ Update Preferences
                </a>
            <?php else: ?>
                <h3>No profiles available</h3>
                <p>Check back soon for new profiles to discover!</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="browse-grid">
            <?php foreach ($users as $u): ?>
                <div class="browse-card">
                    <div class="browse-photo-container">
                        <?php if ($u['photo']): ?>
                            <img src="<?php echo $photo_base . htmlspecialchars($u['photo']); ?>" 
                                 alt="<?php echo htmlspecialchars($u['name']); ?>" 
                                 class="browse-photo">
                        <?php else: ?>
                            <div class="browse-photo-placeholder">👤</div>
                        <?php endif; ?>
                        
                        <div class="browse-info-overlay">
                            <div class="browse-user-info">
                                <div class="browse-name">
                                    <?php echo htmlspecialchars($u['name']); ?>, <?php echo $u['age']; ?>
                                </div>
                                <div class="browse-details">
                                    <?php echo ucfirst($u['gender']); ?>
                                </div>
                                <?php if ($u['bio']): ?>
                                    <div class="browse-bio">
                                        <?php echo htmlspecialchars(mb_strimwidth(trim($u['bio']), 0, 50, "...")); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="browse-actions">
                                <a href="profile_view.php?id=<?php echo $u['id']; ?>" class="btn-browse-view" title="View Profile">
                                    👀
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<a href="index.php" class="back-btn" title="Back to Dashboard">←</a>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>