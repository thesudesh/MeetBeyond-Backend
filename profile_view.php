<?php
session_start();
require 'config.php';

// Must be logged in to view profiles
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$viewer_id = $_SESSION['user_id'];
$profile_id = isset($_GET['id']) ? intval($_GET['id']) : $viewer_id;

// Fetch user + profile
$stmt = $conn->prepare("SELECT u.id AS user_id, u.email, p.name, p.age, p.gender, p.bio FROM Users u LEFT JOIN Profiles p ON u.id = p.user_id WHERE u.id = ? LIMIT 1");
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$profile = $result->fetch_assoc()) {
    http_response_code(404);
    echo "<p style='padding:20px'>Profile not found.</p>";
    exit;
}
$stmt->close();

// Primary photo
$stmt = $conn->prepare("SELECT file_path FROM Photos WHERE user_id = ? AND is_primary = 1 AND is_active = 1 LIMIT 1");
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$stmt->bind_result($primary_photo);
$has_primary = $stmt->fetch();
$stmt->close();

// Other photos (up to 8)
$photos = [];
$stmt = $conn->prepare("SELECT file_path FROM Photos WHERE user_id = ? AND is_active = 1 ORDER BY is_primary DESC, id DESC LIMIT 8");
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $photos[] = $r['file_path'];
$stmt->close();

// Preferences
$stmt = $conn->prepare("SELECT min_age, max_age, gender_pref, location, relationship_type FROM Preferences WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$prefs = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$is_self = ($viewer_id === intval($profile['user_id']));

// friendly fallbacks
$display_name = $profile['name'] ?: $profile['email'];
$display_age = $profile['age'] ? intval($profile['age']) : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars($display_name); ?> — Profile | Meet Beyond</title>
  <link rel="stylesheet" href="assets/style.css">
 </head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container" style="padding:28px 20px;">
  <section class="card" style="max-width:980px;margin:0 auto;">
    <div class="page-top">
      <div>
        <h2 class="page-title"><?php echo htmlspecialchars($display_name); ?><?php if ($display_age) echo ' · ' . $display_age; ?></h2>
        <div class="muted">Profile</div>
      </div>
      <div class="user-card">
        <?php if ($is_self): ?>
          <a href="profile.php" class="btn btn-hero">Edit profile</a>
        <?php else: ?>
          <a href="messages.php?match=<?php echo $profile_id; ?>" class="btn">Message</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="grid" style="grid-template-columns:260px 1fr;gap:20px;align-items:start;margin-top:12px;">
      <aside>
        <div class="profile-card" style="padding:18px">
          <?php if ($has_primary && $primary_photo): ?>
            <img src="MBusers/photos/<?php echo htmlspecialchars($primary_photo); ?>" alt="<?php echo htmlspecialchars($display_name); ?>" class="avatar" style="width:120px;height:120px;border-radius:12px;">
          <?php else: ?>
            <div class="avatar" style="width:120px;height:120px;border-radius:12px;display:inline-block;background:linear-gradient(135deg,#6b21a8,#a78bfa);"></div>
          <?php endif; ?>

          <div style="margin-top:12px">
            <div class="label" style="font-weight:800"><?php echo htmlspecialchars($display_name); ?></div>
            <?php if ($profile['gender']): ?><div class="muted"><?php echo htmlspecialchars($profile['gender']); ?></div><?php endif; ?>
            <?php if ($profile['age']): ?><div class="muted"><?php echo intval($profile['age']); ?> yrs</div><?php endif; ?>
          </div>

          <?php if (!empty($photos)): ?>
            <div style="margin-top:14px;display:grid;grid-template-columns:repeat(2,1fr);gap:8px">
              <?php foreach ($photos as $ph): ?>
                <img src="MBusers/photos/<?php echo htmlspecialchars($ph); ?>" alt="photo" style="width:100%;height:72px;object-fit:cover;border-radius:8px;">
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="card" style="margin-top:14px;padding:14px">
          <div class="label">Preferences</div>
          <div style="margin-top:8px">
            <div class="muted">Age range: <strong><?php echo htmlspecialchars($prefs['min_age'] ?? '—'); ?></strong> — <strong><?php echo htmlspecialchars($prefs['max_age'] ?? '—'); ?></strong></div>
            <div class="muted">Gender preference: <strong><?php echo htmlspecialchars($prefs['gender_pref'] ?? '—'); ?></strong></div>
            <div class="muted">Location: <strong><?php echo htmlspecialchars($prefs['location'] ?? '—'); ?></strong></div>
            <div class="muted">Relationship: <strong><?php echo htmlspecialchars($prefs['relationship_type'] ?? '—'); ?></strong></div>
          </div>
        </div>
      </aside>

      <section>
        <div class="card" style="padding:18px">
          <div class="label">About</div>
          <div style="margin-top:12px;color:var(--card-text)"><?php echo nl2br(htmlspecialchars($profile['bio'] ?: 'This user has not added a bio yet.')); ?></div>
        </div>

        <div class="card" style="margin-top:12px;padding:12px">
          <div class="label">Contact</div>
          <div class="muted" style="margin-top:8px">Email: <strong><?php echo htmlspecialchars($profile['email']); ?></strong></div>
        </div>
      </section>
    </div>
  </section>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
