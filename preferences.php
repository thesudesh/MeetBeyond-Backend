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
    <title>Your Preferences | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>
<main class="container">
    <div class="card" style="max-width:680px;margin:0 auto">
        <div class="page-top">
            <div>
                <h2 class="page-title">Your Preferences</h2>
                <p class="lead">Tell us what you're looking for!</p>
            </div>
            <div class="muted">
                <?php if ($must_complete): ?>
                    <span class="alert alert-warn" style="display:inline-block;padding:8px;border-radius:8px">Please complete your preferences for better matches.</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" class="form-row" novalidate>
            <label for="min_age" class="muted">Minimum Age</label>
            <input id="min_age" type="number" name="min_age" placeholder="Minimum Age" value="<?php echo htmlspecialchars($min_age); ?>" min="18" max="120" required>

            <label for="max_age" class="muted">Maximum Age</label>
            <input id="max_age" type="number" name="max_age" placeholder="Maximum Age" value="<?php echo htmlspecialchars($max_age); ?>" min="18" max="120" required>

            <label for="gender_pref" class="muted">Interested In</label>
            <select id="gender_pref" name="gender_pref" required aria-label="Interested In">
                <option value="">Choose</option>
                <option value="male" <?php if($gender_pref==='male') echo "selected"; ?>>Men</option>
                <option value="female" <?php if($gender_pref==='female') echo "selected"; ?>>Women</option>
                <option value="other" <?php if($gender_pref==='other') echo "selected"; ?>>Other</option>
            </select>

            <label for="location" class="muted">Location</label>
            <input id="location" type="text" name="location" placeholder="Location" value="<?php echo htmlspecialchars($location); ?>" required>

            <label for="relationship_type" class="muted">Looking For</label>
            <select id="relationship_type" name="relationship_type" required aria-label="Looking For">
                <option value="">Choose</option>
                <option value="friendship" <?php if($relationship_type==='friendship') echo "selected"; ?>>Friendship</option>
                <option value="dating" <?php if($relationship_type==='dating') echo "selected"; ?>>Dating</option>
                <option value="long-term" <?php if($relationship_type==='long-term') echo "selected"; ?>>Long-term</option>
                <option value="marriage" <?php if($relationship_type==='marriage') echo "selected"; ?>>Marriage</option>
                <option value="networking" <?php if($relationship_type==='networking') echo "selected"; ?>>Networking</option>
            </select>

            <label for="interests" class="muted">Interests (optional)</label>
            <input id="interests" type="text" name="interests" placeholder="Interests (optional)" value="<?php echo htmlspecialchars($interests); ?>">

            <div class="form-actions" style="display:flex;gap:12px;align-items:center;margin-top:6px">
                <button type="submit" class="btn-hero">Save Preferences</button>
                <a href="photos.php" class="btn-ghost">Next: Upload Photo</a>
            </div>
        </form>
    </div>
</main>
<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>