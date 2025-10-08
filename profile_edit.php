<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch current user data
$stmt = $conn->prepare("SELECT email FROM Users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($email);
$stmt->fetch();
$stmt->close();

// Fetch profile data
$stmt = $conn->prepare("SELECT name, age, gender, bio FROM Profiles WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $age, $gender, $bio);
$stmt->fetch();
$stmt->close();

// Fetch preferences
$stmt = $conn->prepare("SELECT min_age, max_age, gender_pref, location, relationship_type, interests FROM Preferences WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($min_age, $max_age, $gender_pref, $location, $relationship_type, $interests);
$stmt->fetch();
$stmt->close();

// Fetch primary photo
$stmt = $conn->prepare("SELECT id, file_path FROM Photos WHERE user_id=? AND is_primary=1 AND is_active=1 LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($photo_id, $photo_path);
$has_photo = $stmt->fetch();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $age = intval($_POST['age']);
    $gender = $_POST['gender'];
    $bio = trim($_POST['bio']);

    if (empty($name) || $age < 18 || empty($gender)) {
        $error = "Please fill all required fields. Age must be 18+.";
    } else {
        $stmt = $conn->prepare("UPDATE Profiles SET name=?, age=?, gender=?, bio=? WHERE user_id=?");
        $stmt->bind_param("sissi", $name, $age, $gender, $bio, $user_id);
        if ($stmt->execute()) {
            $success = "Profile updated successfully!";
        } else {
            $error = "Failed to update profile.";
        }
        $stmt->close();
    }
}

// Handle preferences update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_preferences'])) {
    $min_age = intval($_POST['min_age']);
    $max_age = intval($_POST['max_age']);
    $gender_pref = $_POST['gender_pref'];
    $location = trim($_POST['location']);
    $relationship_type = trim($_POST['relationship_type']);
    $interests = trim($_POST['interests']);

    if ($min_age < 18 || $max_age < $min_age || empty($gender_pref) || empty($location) || empty($relationship_type)) {
        $error = "Please fill all required preference fields correctly.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM Preferences WHERE user_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->close();
            $stmt = $conn->prepare("UPDATE Preferences SET min_age=?, max_age=?, gender_pref=?, location=?, relationship_type=?, interests=? WHERE user_id=?");
            $stmt->bind_param("iissssi", $min_age, $max_age, $gender_pref, $location, $relationship_type, $interests, $user_id);
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO Preferences (user_id, min_age, max_age, gender_pref, location, relationship_type, interests) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiissss", $user_id, $min_age, $max_age, $gender_pref, $location, $relationship_type, $interests);
        }
        
        if ($stmt->execute()) {
            $success = "Preferences updated successfully!";
        } else {
            $error = "Failed to update preferences.";
        }
        $stmt->close();
    }
}

// Handle photo upload
$photos_dir = __DIR__ . '/MBusers/photos/';
if (!is_dir($photos_dir)) {
    mkdir($photos_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $tmp_name = $_FILES['photo']['tmp_name'];
    $orig_name = basename($_FILES['photo']['name']);
    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    
    if (!in_array($ext, ['jpg','jpeg','png','gif'])) {
        $error = "Only JPG, PNG, GIF files allowed.";
    } else {
        // Delete old photo if exists
        if ($has_photo) {
            $old_file = $photos_dir . $photo_path;
            if (file_exists($old_file)) {
                unlink($old_file);
            }
            $conn->query("UPDATE Photos SET is_active=0 WHERE user_id=$user_id");
        }
        
        $new_name = "user{$user_id}_" . uniqid() . "." . $ext;
        $dest_path = $photos_dir . $new_name;
        
        if (move_uploaded_file($tmp_name, $dest_path)) {
            $stmt = $conn->prepare("INSERT INTO Photos (user_id, file_path, is_primary, is_active) VALUES (?, ?, 1, 1)");
            $stmt->bind_param("is", $user_id, $new_name);
            $stmt->execute();
            $stmt->close();
            $success = "Photo updated successfully!";
            $photo_path = $new_name;
            $has_photo = true;
        } else {
            $error = "Failed to upload photo.";
        }
    }
}

// Handle photo deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo'])) {
    if ($has_photo) {
        $file_to_delete = $photos_dir . $photo_path;
        if (file_exists($file_to_delete)) {
            unlink($file_to_delete);
        }
        $stmt = $conn->prepare("UPDATE Photos SET is_active=0 WHERE id=?");
        $stmt->bind_param("i", $photo_id);
        $stmt->execute();
        $stmt->close();
        $success = "Photo deleted successfully!";
        $has_photo = false;
        $photo_path = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .tabs{
            display:flex;
            gap:8px;
            margin-bottom:32px;
            border-bottom:2px solid rgba(167, 139, 250, 0.2);
            padding-bottom:0;
        }
        .tab{
            padding:14px 24px;
            background:transparent;
            border:none;
            color:var(--muted);
            cursor:pointer;
            font-weight:600;
            font-size:1rem;
            transition:var(--transition);
            border-bottom:3px solid transparent;
            margin-bottom:-2px;
        }
        .tab:hover{color:var(--text);background:rgba(167, 139, 250, 0.1)}
        .tab.active{color:var(--text);border-bottom-color:var(--accent-purple)}
        .tab-content{display:none}
        .tab-content.active{display:block;animation:fadeIn 0.3s ease}
        @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        @media (max-width:768px){
            .form-grid{grid-template-columns:1fr}
            .tabs{overflow-x:auto;-webkit-overflow-scrolling:touch}
            .tab{white-space:nowrap}
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container">
    <div class="card" style="max-width:900px;margin:40px auto">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:32px">
            <div>
                <h2 class="page-title" style="margin-bottom:8px">Edit Your Profile</h2>
                <p class="lead" style="margin:0">Manage your information, preferences, and photo</p>
            </div>
            <a href="profile_view.php?id=<?php echo $user_id; ?>" class="btn-ghost">👁️ View Profile</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('profile')">📝 Profile Info</button>
            <button class="tab" onclick="showTab('preferences')">⚙️ Preferences</button>
            <button class="tab" onclick="showTab('photo')">📸 Photo</button>
        </div>

        <!-- Profile Tab -->
        <div id="profile-tab" class="tab-content active">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-row">
                        <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                            <span style="font-size:1.2rem">👤</span> Full Name *
                        </label>
                        <input type="text" name="name" placeholder="Your full name" value="<?php echo htmlspecialchars($name); ?>" required>
                    </div>

                    <div class="form-row">
                        <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                            <span style="font-size:1.2rem">🎂</span> Age *
                        </label>
                        <input type="number" name="age" placeholder="Your age" value="<?php echo htmlspecialchars($age); ?>" min="18" max="120" required>
                    </div>
                </div>

                <div class="form-row">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                        <span style="font-size:1.2rem">⚧</span> Gender *
                    </label>
                    <select name="gender" required>
                        <option value="">Select gender</option>
                        <option value="male" <?php if($gender==='male') echo 'selected'; ?>>Male</option>
                        <option value="female" <?php if($gender==='female') echo 'selected'; ?>>Female</option>
                        <option value="other" <?php if($gender==='other') echo 'selected'; ?>>Other</option>
                    </select>
                </div>

                <div class="form-row">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                        <span style="font-size:1.2rem">📧</span> Email (read-only)
                    </label>
                    <input type="email" value="<?php echo htmlspecialchars($email); ?>" disabled style="opacity:0.6;cursor:not-allowed">
                </div>

                <div class="form-row">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                        <span style="font-size:1.2rem">✍️</span> Bio
                    </label>
                    <textarea name="bio" placeholder="Tell us about yourself..." rows="5"><?php echo htmlspecialchars($bio); ?></textarea>
                </div>

                <button type="submit" name="update_profile" class="btn" style="width:100%;justify-content:center;padding:16px;margin-top:24px">
                    💾 Save Profile
                </button>
            </form>
        </div>

        <!-- Preferences Tab -->
        <div id="preferences-tab" class="tab-content">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-row">
                        <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                            <span style="font-size:1.2rem">🎂</span> Minimum Age *
                        </label>
                        <input type="number" name="min_age" placeholder="18" value="<?php echo htmlspecialchars($min_age); ?>" min="18" max="120" required>
                    </div>

                    <div class="form-row">
                        <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                            <span style="font-size:1.2rem">🎂</span> Maximum Age *
                        </label>
                        <input type="number" name="max_age" placeholder="99" value="<?php echo htmlspecialchars($max_age); ?>" min="18" max="120" required>
                    </div>
                </div>

                <div class="form-row">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                        <span style="font-size:1.2rem">👤</span> Interested In *
                    </label>
                    <select name="gender_pref" required>
                        <option value="">Select preference</option>
                        <option value="male" <?php if($gender_pref==='male') echo 'selected'; ?>>Men</option>
                        <option value="female" <?php if($gender_pref==='female') echo 'selected'; ?>>Women</option>
                        <option value="other" <?php if($gender_pref==='other') echo 'selected'; ?>>Other</option>
                    </select>
                </div>

                <div class="form-row">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                        <span style="font-size:1.2rem">📍</span> Location *
                    </label>
                    <input type="text" name="location" placeholder="e.g., New York, USA" value="<?php echo htmlspecialchars($location); ?>" required>
                </div>

                <div class="form-row">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                        <span style="font-size:1.2rem">💝</span> Looking For *
                    </label>
                    <select name="relationship_type" required>
                        <option value="">Select type</option>
                        <option value="friendship" <?php if($relationship_type==='friendship') echo 'selected'; ?>>Friendship</option>
                        <option value="dating" <?php if($relationship_type==='dating') echo 'selected'; ?>>Dating</option>
                        <option value="long-term" <?php if($relationship_type==='long-term') echo 'selected'; ?>>Long-term Relationship</option>
                        <option value="marriage" <?php if($relationship_type==='marriage') echo 'selected'; ?>>Marriage</option>
                        <option value="networking" <?php if($relationship_type==='networking') echo 'selected'; ?>>Networking</option>
                    </select>
                </div>

                <div class="form-row">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                        <span style="font-size:1.2rem">✨</span> Interests <span style="font-weight:400;color:var(--muted);font-size:0.9rem">(optional)</span>
                    </label>
                    <input type="text" name="interests" placeholder="e.g., hiking, music, cooking" value="<?php echo htmlspecialchars($interests); ?>">
                </div>

                <button type="submit" name="update_preferences" class="btn" style="width:100%;justify-content:center;padding:16px;margin-top:24px">
                    💾 Save Preferences
                </button>
            </form>
        </div>

        <!-- Photo Tab -->
        <div id="photo-tab" class="tab-content">
            <div style="text-align:center">
                <?php if ($has_photo): ?>
                    <div style="margin-bottom:32px">
                        <img src="MBusers/photos/<?php echo htmlspecialchars($photo_path); ?>" 
                             style="width:200px;height:200px;object-fit:cover;border-radius:50%;border:4px solid rgba(167, 139, 250, 0.3);box-shadow:var(--shadow-lg)">
                    </div>
                    
                    <div style="display:flex;flex-direction:column;gap:16px;max-width:400px;margin:0 auto">
                        <form method="POST" enctype="multipart/form-data">
                            <label class="btn" style="cursor:pointer;width:100%;justify-content:center">
                                <input type="file" name="photo" accept="image/*" style="display:none" onchange="this.form.submit()">
                                📸 Change Photo
                            </label>
                        </form>
                        
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete your photo?')">
                            <button type="submit" name="delete_photo" value="1" class="btn-ghost" style="width:100%;justify-content:center">
                                🗑️ Delete Photo
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="font-size:4rem;margin-bottom:24px">📸</div>
                    <h3 style="font-size:1.5rem;margin-bottom:12px">No Photo Yet</h3>
                    <p style="color:var(--muted);margin-bottom:32px">Upload a photo to make your profile stand out!</p>
                    
                    <form method="POST" enctype="multipart/form-data" style="max-width:400px;margin:0 auto">
                        <div style="border:2px dashed rgba(167, 139, 250, 0.3);border-radius:14px;padding:40px 20px;background:rgba(167,139,250,0.05);margin-bottom:24px;cursor:pointer;transition:var(--transition)" onclick="document.getElementById('photo-upload').click()">
                            <div style="font-size:3rem;margin-bottom:16px">👤</div>
                            <div style="font-weight:600;margin-bottom:8px;font-size:1.1rem">Choose Your Photo</div>
                            <div style="color:var(--muted);font-size:0.95rem">Click to select from your device</div>
                            <input type="file" id="photo-upload" name="photo" accept="image/*" required style="display:none" onchange="previewPhoto(this); this.form.submit();">
                        </div>
                    </form>
                <?php endif; ?>
                
                <div style="margin-top:32px;padding:16px;background:rgba(251,146,60,0.1);border:1px solid rgba(251,146,60,0.3);border-radius:10px;max-width:500px;margin:32px auto 0">
                    <strong style="color:rgba(251,191,36,0.95)">💡 Pro Tip:</strong>
                    <span style="color:var(--muted)"> Profiles with clear photos get 3x more matches!</span>
                </div>
            </div>
        </div>
    </div>
</main>

<a href="index.php" class="back-btn" title="Back to Dashboard">←</a>

<?php include_once __DIR__ . '/includes/footer.php'; ?>

<script>
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(btn => btn.classList.remove('active'));
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.classList.add('active');
}

function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            // Photo will be uploaded, just show loading state
            input.parentElement.innerHTML = '<div style="padding:40px;color:var(--muted)">Uploading...</div>';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
