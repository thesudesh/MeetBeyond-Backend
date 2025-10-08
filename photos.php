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
$photos_dir = __DIR__ . '/MBusers/photos/';
$web_photos_dir = 'MBusers/photos/';

if (!is_dir($photos_dir)) {
    mkdir($photos_dir, 0777, true);
}

// Check if user has a primary photo
$stmt = $conn->prepare("SELECT id, file_path FROM Photos WHERE user_id=? AND is_primary=1 AND is_active=1 LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($primary_photo_id, $primary_photo_path);
$has_primary = $stmt->fetch();
$stmt->close();

// Handle photo deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo'])) {
    if ($has_primary) {
        // Delete physical file
        $file_to_delete = $photos_dir . $primary_photo_path;
        if (file_exists($file_to_delete)) {
            unlink($file_to_delete);
        }
        // Mark as inactive in database
        $stmt = $conn->prepare("UPDATE Photos SET is_active=0 WHERE id=?");
        $stmt->bind_param("i", $primary_photo_id);
        $stmt->execute();
        $stmt->close();
        $success = "Photo deleted successfully!";
        $has_primary = false;
        $primary_photo_path = '';
    }
}

// Handle upload (single photo only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $tmp_name = $_FILES['photo']['tmp_name'];
    $orig_name = basename($_FILES['photo']['name']);
    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    
    if (!in_array($ext, ['jpg','jpeg','png','gif'])) {
        $error = "Only JPG, PNG, GIF files allowed.";
    } else {
        // If user already has a photo, delete the old one
        if ($has_primary) {
            $old_file = $photos_dir . $primary_photo_path;
            if (file_exists($old_file)) {
                unlink($old_file);
            }
            // Mark old photo as inactive
            $conn->query("UPDATE Photos SET is_active=0 WHERE user_id=$user_id");
        }
        
        $new_name = "user{$user_id}_" . uniqid() . "." . $ext;
        $dest_path = $photos_dir . $new_name;
        
        if (move_uploaded_file($tmp_name, $dest_path)) {
            // Insert new photo as primary
            $stmt = $conn->prepare("INSERT INTO Photos (user_id, file_path, is_primary, is_active) VALUES (?, ?, 1, 1)");
            $stmt->bind_param("is", $user_id, $new_name);
            $stmt->execute();
            $stmt->close();
            $success = "Photo uploaded successfully!";
            
            // Redirect to dashboard if this completes the profile
            if (isset($_GET['complete'])) {
                header('Location: index.php');
                exit;
            }
        } else {
            $error = "Failed to upload photo.";
        }
    }
    
    // Re-check for primary photo
    $stmt = $conn->prepare("SELECT id, file_path FROM Photos WHERE user_id=? AND is_primary=1 AND is_active=1 LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($primary_photo_id, $primary_photo_path);
    $has_primary = $stmt->fetch();
    $stmt->close();
}

// If primary photo exists and user wants to manage it, show management page
if ($has_primary && !isset($_GET['complete'])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Photo | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container">
    <div class="card" style="max-width:600px;margin:40px auto">
        <h2 class="page-title">Your Profile Photo</h2>
        <p class="lead">Make a great first impression with your photo</p>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div style="text-align:center;margin:32px 0">
            <img src="<?php echo $web_photos_dir . htmlspecialchars($primary_photo_path); ?>" 
                 style="width:200px;height:200px;object-fit:cover;border-radius:50%;border:4px solid rgba(255,255,255,0.2);box-shadow:var(--shadow-lg)">
        </div>
        
        <div style="display:flex;flex-direction:column;gap:16px">
            <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:16px">
                <label class="btn" style="cursor:pointer;text-align:center">
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
    </div>
</main>

<a href="index.php" class="back-btn" title="Back to Dashboard">←</a>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
<?php
exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Your Photo | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<main class="container">
    <div class="card" style="max-width:550px;margin:80px auto;text-align:center">
        <div style="font-size:4rem;margin-bottom:20px">📸</div>
        <h2 class="page-title" style="margin-bottom:12px">Upload Your Profile Photo</h2>
        <p class="lead" style="margin-bottom:32px">Your photo is your first impression. Make it count!</p>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" style="margin-bottom:32px">
            <div style="border:2px dashed rgba(255,255,255,0.3);border-radius:14px;padding:40px 20px;background:rgba(167,139,250,0.08);margin-bottom:24px;cursor:pointer;transition:var(--transition)" onclick="document.getElementById('photo-input').click()">
                <div style="font-size:3rem;margin-bottom:16px">👤</div>
                <div style="font-weight:600;margin-bottom:8px;font-size:1.1rem">Choose Your Best Photo</div>
                <div style="color:var(--muted);font-size:0.95rem;margin-bottom:16px">Click to select a photo from your device</div>
                <input type="file" id="photo-input" name="photo" accept="image/*" required style="display:none" onchange="previewPhoto(this)">
                <div id="preview-container" style="margin-top:20px"></div>
            </div>
            
            <button type="submit" class="btn" style="width:100%;justify-content:center;padding:16px;font-size:1.1rem">
                ✨ Upload Photo
            </button>
        </form>
        
        <div style="background:rgba(251,146,60,0.12);border:1px solid rgba(251,146,60,0.3);border-radius:10px;padding:16px;color:rgba(251,191,36,0.95)">
            <strong>💡 Pro Tip:</strong> Profiles with a clear, friendly photo get 3x more matches!
        </div>
    </div>
</main>

<script>
function previewPhoto(input) {
    const container = document.getElementById('preview-container');
    container.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = 'width:150px;height:150px;object-fit:cover;border-radius:50%;border:4px solid rgba(255,255,255,0.3);box-shadow:var(--shadow-md)';
            container.appendChild(img);
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>