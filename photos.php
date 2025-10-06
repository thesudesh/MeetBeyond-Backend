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

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $files = $_FILES['photo'];
    $is_primary = isset($_POST['is_primary']) ? 1 : 0;

    for ($i=0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $tmp_name = $files['tmp_name'][$i];
            $orig_name = basename($files['name'][$i]);
            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif'])) {
                $error = "Only JPG, PNG, GIF files allowed.";
                continue;
            }
            $new_name = "user{$user_id}_" . uniqid() . "." . $ext;
            $dest_path = $photos_dir . $new_name;
            if (move_uploaded_file($tmp_name, $dest_path)) {
                if ($is_primary) {
                    $conn->query("UPDATE Photos SET is_primary=0 WHERE user_id=$user_id");
                }
                $stmt = $conn->prepare("INSERT INTO Photos (user_id, file_path, is_primary) VALUES (?, ?, ?)");
                $stmt->bind_param("isi", $user_id, $new_name, $is_primary);
                $stmt->execute();
                $stmt->close();
                $success = "Photo uploaded!";
            } else {
                $error = "Failed to upload photo.";
            }
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

// If primary photo uploaded, show "complete" page
if ($has_primary) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile Complete | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .complete-card {
            background: #fff8fa;
            border-radius: 18px;
            box-shadow: 0 8px 32px #efefff22;
            padding: 44px 32px 36px 32px;
            text-align: center;
            max-width: 430px;
            margin: 70px auto 0 auto;
        }
        .complete-card img {
            width: 130px;
            height: 130px;
            object-fit: cover;
            border-radius: 50%;
            box-shadow: 0 2px 18px #e6c7fd44;
            margin-bottom: 30px;
            border: 6px solid #fff;
        }
        .cheesy-line {
            color: #a06ee5;
            font-size: 1.13em;
            margin: 15px 0 20px 0;
        }
    </style>
</head>
<body>
    <div class="complete-card">
        <div style="font-size:2.4em;margin-bottom:10px;">🎉</div>
        <h2 style="margin-bottom: 18px;">Your profile is now complete!</h2>
        <img src="<?php echo $web_photos_dir . htmlspecialchars($primary_photo_path); ?>">
        <div class="cheesy-line">
            Welcome to <span class="brand">Meet Beyond</span>, where<br>
            <b>connections turn into stories</b>!<br>
            Your new adventure starts now.
        </div>
        <a href="index.php" class="btn" style="width:90%;font-size:1.08em;">Go to Dashboard</a>
    </div>
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
    <style>
        .photo-card {
            background: #fff6fa;
            border-radius: 18px;
            box-shadow: 0 8px 32px #efefff22;
            padding: 40px 30px 30px 30px;
            max-width: 430px;
            margin: 70px auto 0 auto;
            text-align: center;
        }
        .drop-zone {
            border: 2px dashed #a06ee5;
            border-radius: 15px;
            background: #f7f1ff;
            padding: 30px 10px 20px 10px;
            margin-bottom: 22px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .drop-zone input[type="file"] {
            display: none;
        }
        .drop-label {
            font-size: 2.2em;
            color: #a06ee5;
            margin-bottom: 8px;
        }
        .upload-btn {
            background: linear-gradient(90deg, #a06ee5 40%, #7b7ce9 100%);
            color: #fff;
            border: none;
            padding: 14px 0;
            border-radius: 11px;
            font-size: 1.09em;
            width: 89%;
            margin: 20px auto 0 auto;
            box-shadow: 0 3px 16px #e6c7fd30;
            cursor: pointer;
            transition: box-shadow 0.2s;
        }
        .upload-btn:hover {
            box-shadow: 0 4px 24px #a06ee566;
            background: linear-gradient(90deg, #7b7ce9 0%, #a06ee5 100%);
        }
        .tip-line {
            color: #b26a00;
            background: #fff8e1;
            border-radius: 9px;
            padding: 12px 0;
            font-size: 0.99em;
            margin: 22px 0 0 0;
        }
        .uploaded-preview {
            margin: 16px 0 16px 0;
        }
        .uploaded-preview img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 50%;
            margin: 0 4px;
            border: 2px solid #a06ee5;
            box-shadow: 0 2px 9px #a06ee522;
        }
    </style>
    <script>
    // Show selected filenames and preview
    function showPreview(input) {
        const preview = document.getElementById('preview');
        preview.innerHTML = '';
        Array.from(input.files).forEach(file => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = e => {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    preview.appendChild(img);
                };
                reader.readAsDataURL(file);
            }
        });
    }
    </script>
</head>
<body>
    <div class="photo-card">
        <div class="drop-label">📸</div>
        <h2 style="margin-bottom: 10px;">Upload Your Best Photo!</h2>
        <div style="color:#a06ee5;margin-bottom:13px;">
            Your profile photo is your first impression. Make it shine!
        </div>
        <?php if ($error): ?>
            <div style="background:#ffe5e5;color:#ae2222;padding:10px 0;margin-bottom:18px;border-radius:10px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php elseif ($success): ?>
            <div style="background:#e8ffe6;color:#218e2c;padding:10px 0;margin-bottom:18px;border-radius:10px;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" style="margin-bottom:0;">
            <div class="drop-zone" onclick="document.getElementById('primary-photo').click();">
                <div style="font-size:2.3em;">👤</div>
                <div style="margin-bottom:10px;font-weight:600;">Primary Photo (required)</div>
                <input type="file" id="primary-photo" name="photo[]" accept=".jpg,.jpeg,.png,.gif" required onchange="showPreview(this)">
                <div style="color:#7b7ce9;font-size:0.97em;margin-bottom:12px;">Click here to choose your main photo</div>
                <input type="hidden" name="is_primary" value="1">
                <div id="preview" class="uploaded-preview"></div>
            </div>
            <div class="drop-zone" onclick="document.getElementById('additional-photo').click();">
                <div style="font-size:1.6em;">🖼️</div>
                <div style="margin-bottom:10px;font-weight:600;">Additional Photos (optional)</div>
                <input type="file" id="additional-photo" name="photo[]" accept=".jpg,.jpeg,.png,.gif" multiple onchange="showPreview(this)">
                <div style="color:#7b7ce9;font-size:0.97em;margin-bottom:12px;">Click here to add more photos</div>
            </div>
            <button type="submit" class="upload-btn">Upload Photos</button>
        </form>
        <div class="tip-line">
            Tip: Profiles with a friendly, clear photo get <b>3x more connections</b>!<br>
            Smile &amp; let your personality shine. 🌟
        </div>
    </div>
</body>
</html>