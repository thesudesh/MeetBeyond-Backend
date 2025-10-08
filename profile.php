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
$must_complete = isset($_GET['complete']);

// Load current user profile info
$stmt = $conn->prepare("SELECT name, age, gender, ethnicity, interests, bio FROM Profiles WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $age, $gender, $ethnicity, $interests, $bio);
$stmt->fetch();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $display_name = trim($_POST['display_name']);
    $age = intval($_POST['age']);
    $gender = $_POST['gender'];
    $ethnicity = trim($_POST['ethnicity']);
    $interests = trim($_POST['interests']);
    $bio = trim($_POST['bio']);

    if (strlen($display_name) < 2) {
        $error = "Name must be at least 2 characters.";
    } elseif ($age < 18 || $age > 120) {
        $error = "Please enter a valid age (18-120).";
    } elseif (!$gender) {
        $error = "Gender is required.";
    } else {
        $stmt = $conn->prepare("UPDATE Profiles SET name=?, age=?, gender=?, ethnicity=?, interests=?, bio=? WHERE user_id=?");
        $stmt->bind_param("sissssi", $display_name, $age, $gender, $ethnicity, $interests, $bio, $user_id);
        if ($stmt->execute()) {
            // Redirect to preferences page
            header('Location: preferences.php?complete=1');
            exit;
        } else {
            $error = "Failed to update profile.";
        }
        $stmt->close();
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
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container">
    <div style="max-width:1000px;margin:40px auto">
        <!-- Header -->
        <div class="card" style="margin-bottom:24px;background:linear-gradient(135deg,rgba(167,139,250,0.15),rgba(244,114,182,0.1))">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px">
                <div>
                    <h2 class="page-title" style="margin-bottom:8px">✏️ Edit Your Profile</h2>
                    <p style="color:var(--muted);margin:0">Make your profile stand out and attract the right connections</p>
                </div>
                <div style="display:flex;gap:12px">
                    <a href="profile_view.php" class="btn-ghost">👁️ Preview</a>
                    <a href="photos.php" class="btn-ghost">📸 Photos</a>
                </div>
            </div>
        </div>

        <?php if ($must_complete): ?>
            <div class="alert alert-warn" style="margin-bottom:20px">
                <strong>⚠️ Profile Incomplete:</strong> Please fill in your Name, Age, and Gender to continue
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:20px"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success" style="margin-bottom:20px"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
                <!-- Left Column -->
                <div class="card">
                    <h3 style="font-size:1.2rem;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:8px">
                        <span style="font-size:1.5rem">👤</span> Basic Information
                    </h3>
                    
                    <div class="form-row">
                        <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                            <span style="font-size:1.2rem">✨</span> Display Name <span style="color:#f472b6">*</span>
                        </label>
                        <input type="text" name="display_name" value="<?php echo htmlspecialchars($name); ?>" placeholder="Your name" required>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                        <div class="form-row">
                            <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                                <span style="font-size:1.2rem">🎂</span> Age <span style="color:#f472b6">*</span>
                            </label>
                            <input type="number" name="age" value="<?php echo htmlspecialchars($age); ?>" placeholder="18" min="18" max="120" required>
                        </div>

                        <div class="form-row">
                            <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                                <span style="font-size:1.2rem">⚧️</span> Gender <span style="color:#f472b6">*</span>
                            </label>
                            <select name="gender" required>
                                <option value="">Select</option>
                                <option value="male" <?php if($gender==='male') echo "selected"; ?>>Male</option>
                                <option value="female" <?php if($gender==='female') echo "selected"; ?>>Female</option>
                                <option value="other" <?php if($gender==='other') echo "selected"; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                            <span style="font-size:1.2rem">🌍</span> Ethnicity <span style="font-size:0.85rem;color:var(--muted);font-weight:400">(optional)</span>
                        </label>
                        <input type="text" name="ethnicity" value="<?php echo htmlspecialchars($ethnicity); ?>" placeholder="e.g., Asian, Caucasian, Mixed">
                    </div>
                </div>

                <!-- Right Column -->
                <div class="card">
                    <h3 style="font-size:1.2rem;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:8px">
                        <span style="font-size:1.5rem">💬</span> About You
                    </h3>

                    <div class="form-row">
                        <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                            <span style="font-size:1.2rem">🎯</span> Interests <span style="font-size:0.85rem;color:var(--muted);font-weight:400">(optional)</span>
                        </label>
                        <input type="text" name="interests" value="<?php echo htmlspecialchars($interests); ?>" placeholder="e.g., hiking, reading, cooking, travel">
                        <small style="color:var(--muted);font-size:0.85rem">Separate with commas</small>
                    </div>

                    <div class="form-row">
                        <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                            <span style="font-size:1.2rem">📝</span> Bio <span style="font-size:0.85rem;color:var(--muted);font-weight:400">(optional)</span>
                        </label>
                        <textarea name="bio" placeholder="Tell others about yourself, what makes you unique, what you're looking for..." maxlength="300" style="min-height:160px"><?php echo htmlspecialchars($bio); ?></textarea>
                        <small style="color:var(--muted);font-size:0.85rem">Max 300 characters</small>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="card" style="background:linear-gradient(135deg,rgba(96,165,250,0.1),rgba(74,222,128,0.1))">
                <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap">
                    <button type="submit" class="btn" style="padding:16px 40px;font-size:1.05rem">
                        💾 Save Changes
                    </button>
                    <?php if (!$must_complete): ?>
                    <a href="profile_view.php" class="btn-ghost" style="padding:16px 32px">
                        Cancel
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</main>

<?php if (!$must_complete): ?>
<a href="index.php" class="back-btn" title="Back to Dashboard">←</a>
<?php endif; ?>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>