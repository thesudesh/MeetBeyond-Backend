<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$must_complete = isset($_GET['complete']);

// Load preferences if any
$stmt = $conn->prepare("SELECT min_age, max_age, gender_pref, location, relationship_type, interests FROM Preferences WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($min_age, $max_age, $gender_pref, $location, $relationship_type, $interests);
$stmt->fetch();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $min_age = intval($_POST['min_age']);
    $max_age = intval($_POST['max_age']);
    $gender_pref = $_POST['gender_pref'];
    $location = trim($_POST['location']);
    $relationship_type = trim($_POST['relationship_type']);
    $interests = trim($_POST['interests']);

    if ($min_age < 18 || $max_age < $min_age) {
        $error = "Please enter a valid age range (minimum age at least 18, max age not less than min age).";
    } elseif (!$gender_pref || !$location || !$relationship_type) {
        $error = "All fields except interests are required.";
    } else {
        // Insert or update preferences
        $stmt = $conn->prepare("SELECT id FROM Preferences WHERE user_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            $stmt = $conn->prepare("UPDATE Preferences SET min_age=?, max_age=?, gender_pref=?, location=?, relationship_type=?, interests=? WHERE user_id=?");
            $stmt->bind_param("iissssi", $min_age, $max_age, $gender_pref, $location, $relationship_type, $interests, $user_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO Preferences (user_id, min_age, max_age, gender_pref, location, relationship_type, interests) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiissss", $user_id, $min_age, $max_age, $gender_pref, $location, $relationship_type, $interests);
            $stmt->execute();
            $stmt->close();
        }
        // Redirect to photos setup
        header('Location: photos.php?complete=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dating Preferences | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>
<main class="container">
    <div style="max-width:900px;margin:40px auto">
        <!-- Header -->
        <div class="card" style="margin-bottom:24px;background:linear-gradient(135deg,rgba(244,114,182,0.15),rgba(167,139,250,0.1))">
            <div style="text-align:center">
                <div style="font-size:3rem;margin-bottom:12px">💝</div>
                <h2 class="page-title" style="margin-bottom:8px">Your Dating Preferences</h2>
                <p style="color:var(--muted);margin:0">Help us find your perfect match by setting your preferences</p>
            </div>
        </div>

        <?php if ($must_complete): ?>
            <div class="alert alert-warn" style="margin-bottom:20px">
                <strong>⚠️ Almost There!</strong> Complete your preferences to unlock the full experience
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:20px"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <!-- Age Range Card -->
            <div class="card" style="margin-bottom:20px">
                <h3 style="font-size:1.2rem;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:8px">
                    <span style="font-size:1.5rem">🎂</span> Age Preference
                </h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
                    <div>
                        <label for="min_age" style="display:block;margin-bottom:8px;font-weight:600;color:var(--text)">
                            Minimum Age
                        </label>
                        <input id="min_age" type="number" name="min_age" placeholder="18" value="<?php echo htmlspecialchars($min_age); ?>" min="18" max="120" required>
                    </div>
                    <div>
                        <label for="max_age" style="display:block;margin-bottom:8px;font-weight:600;color:var(--text)">
                            Maximum Age
                        </label>
                        <input id="max_age" type="number" name="max_age" placeholder="99" value="<?php echo htmlspecialchars($max_age); ?>" min="18" max="120" required>
                    </div>
                </div>
                <p style="color:var(--muted);font-size:0.9rem;margin-top:12px">
                    💡 We'll show you profiles within this age range
                </p>
            </div>

            <!-- Match Preferences Card -->
            <div class="card" style="margin-bottom:20px">
                <h3 style="font-size:1.2rem;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:8px">
                    <span style="font-size:1.5rem">💕</span> Who Are You Looking For?
                </h3>
                
                <div class="form-row">
                    <label for="gender_pref" style="display:flex;align-items:center;gap:8px;font-weight:600">
                        <span style="font-size:1.2rem">👤</span> Interested In
                    </label>
                    <select id="gender_pref" name="gender_pref" required aria-label="Interested In">
                        <option value="">Select gender preference</option>
                        <option value="male" <?php if($gender_pref==='male') echo "selected"; ?>>Men</option>
                        <option value="female" <?php if($gender_pref==='female') echo "selected"; ?>>Women</option>
                        <option value="other" <?php if($gender_pref==='other') echo "selected"; ?>>Other</option>
                    </select>
                </div>

                <div class="form-row">
                    <label for="relationship_type" style="display:flex;align-items:center;gap:8px;font-weight:600">
                        <span style="font-size:1.2rem">💝</span> Relationship Type
                    </label>
                    <select id="relationship_type" name="relationship_type" required aria-label="Looking For">
                        <option value="">What are you looking for?</option>
                        <option value="friendship" <?php if($relationship_type==='friendship') echo "selected"; ?>>Friendship</option>
                        <option value="dating" <?php if($relationship_type==='dating') echo "selected"; ?>>Casual Dating</option>
                        <option value="long-term" <?php if($relationship_type==='long-term') echo "selected"; ?>>Long-term Relationship</option>
                        <option value="marriage" <?php if($relationship_type==='marriage') echo "selected"; ?>>Marriage</option>
                        <option value="networking" <?php if($relationship_type==='networking') echo "selected"; ?>>Professional Networking</option>
                    </select>
                </div>
            </div>

            <!-- Location & Interests Card -->
            <div class="card" style="margin-bottom:20px">
                <h3 style="font-size:1.2rem;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:8px">
                    <span style="font-size:1.5rem">🌍</span> Location & Interests
                </h3>

                <div class="form-row">
                    <label for="location" style="display:flex;align-items:center;gap:8px;font-weight:600">
                        <span style="font-size:1.2rem">📍</span> Your Location
                    </label>
                    <input id="location" type="text" name="location" placeholder="e.g., New York, USA" value="<?php echo htmlspecialchars($location); ?>" required>
                    <small style="color:var(--muted);font-size:0.85rem">We'll prioritize matches near you</small>
                </div>

                <div class="form-row">
                    <label for="interests" style="display:flex;align-items:center;gap:8px;font-weight:600">
                        <span style="font-size:1.2rem">✨</span> Shared Interests <span style="font-weight:400;color:var(--muted);font-size:0.85rem">(optional)</span>
                    </label>
                    <input id="interests" type="text" name="interests" placeholder="e.g., hiking, music, cooking, travel" value="<?php echo htmlspecialchars($interests); ?>">
                    <small style="color:var(--muted);font-size:0.85rem">Separate multiple interests with commas</small>
                </div>
            </div>

            <!-- Action Button -->
            <div class="card" style="background:linear-gradient(135deg,rgba(167,139,250,0.12),rgba(244,114,182,0.12))">
                <button type="submit" class="btn" style="width:100%;justify-content:center;padding:18px;font-size:1.1rem">
                    💾 Save My Preferences
                </button>
                <p style="text-align:center;color:var(--muted);font-size:0.9rem;margin-top:16px;margin-bottom:0">
                    You can update these anytime from your profile
                </p>
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