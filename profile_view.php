<?php
session_start();
require 'config.php';

// Must be logged in to view profiles
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$viewer_id = $_SESSION['user_id'];
// Support both 'id' and 'user' parameters for compatibility
$profile_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['user']) ? intval($_GET['user']) : $viewer_id);

// Handle block action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'block') {
    $target_id = intval($_POST['target_id']);
    if ($target_id && $target_id !== $viewer_id) {
        // Block user
        $stmt = $conn->prepare("INSERT IGNORE INTO Blocks (blocker_id, blocked_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $viewer_id, $target_id);
        $stmt->execute();
        $stmt->close();
        
        // Also add to passed likes to prevent showing in discover
        $stmt = $conn->prepare("INSERT INTO Likes (liker_id, liked_id, status) VALUES (?, ?, 'passed') ON DUPLICATE KEY UPDATE status='passed'");
        $stmt->bind_param("ii", $viewer_id, $target_id);
        $stmt->execute();
        $stmt->close();
        
        // Redirect with success message
        header("Location: index.php?blocked=1");
        exit;
    }
}

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

// Check if the profile being viewed has premium (for any user)
$stmt = $conn->prepare("SELECT plan_type FROM Subscriptions WHERE user_id = ? AND end_date > CURDATE() ORDER BY end_date DESC LIMIT 1");
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$stmt->bind_result($viewed_user_plan_type);
$viewed_user_has_premium = $stmt->fetch();
$stmt->close();

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
  <link rel="icon" type="image/png" href="assets/favicon.png">
  <style>
    .btn-action {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 14px 20px;
      border-radius: 14px;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.95rem;
      border: 2px solid;
      background: rgba(255,255,255,0.05);
      backdrop-filter: blur(15px);
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      position: relative;
      overflow: hidden;
      min-width: 140px;
      white-space: nowrap;
    }
    
    .btn-action::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
      opacity: 0;
      transition: opacity 0.3s ease;
      border-radius: inherit;
    }
    
    .btn-action:hover::before {
      opacity: 1;
    }
    
    .btn-action:hover {
      transform: translateY(-3px) scale(1.02);
      box-shadow: 0 12px 32px rgba(0,0,0,0.3);
    }
    
    .btn-action:active {
      transform: translateY(-1px) scale(1.01);
      transition: all 0.1s ease;
    }
    
    .btn-block {
      color: #fca5a5;
      border-color: #ef4444;
      background: linear-gradient(135deg, rgba(239,68,68,0.15), rgba(220,38,38,0.1));
    }
    
    .btn-block:hover {
      color: #ffffff;
      background: linear-gradient(135deg, #ef4444, #dc2626);
      border-color: #ef4444;
      box-shadow: 0 12px 32px rgba(239,68,68,0.4);
    }
    
    .btn-report {
      color: #fdba74;
      border-color: #f59e0b;
      background: linear-gradient(135deg, rgba(245,158,11,0.15), rgba(217,119,6,0.1));
    }
    
    .btn-report:hover {
      color: #ffffff;
      background: linear-gradient(135deg, #f59e0b, #d97706);
      border-color: #f59e0b;
      box-shadow: 0 12px 32px rgba(245,158,11,0.4);
    }
    
    .btn-icon {
      font-size: 1.1rem;
      filter: brightness(1.1);
      transition: transform 0.3s ease;
    }
    
    .btn-action:hover .btn-icon {
      transform: scale(1.1);
    }
    
    .subscription-card {
      background: linear-gradient(135deg, rgba(167,139,250,0.15), rgba(236,72,153,0.1));
      border: 2px solid rgba(167,139,250,0.2);
      position: relative;
      overflow: hidden;
    }
    
    .subscription-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--accent-purple), var(--accent-pink));
    }
    
    .premium-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: linear-gradient(135deg, #fbbf24, #f59e0b);
      color: #1f2937;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .profile-main-avatar.premium-avatar {
      border: 4px solid #fbbf24 !important;
      box-shadow: var(--shadow-md), 0 0 0 2px rgba(251,191,36,0.3) !important;
    }
  </style>
 </head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container" style="padding:28px 20px;">
  <section style="max-width:1100px;margin:0 auto;">
    <!-- Header Card -->
    <div class="card" style="background:linear-gradient(135deg,rgba(167,139,250,0.15),rgba(244,114,182,0.1))">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px">
        <div style="display:flex;align-items:center;gap:20px">
          <?php if ($has_primary && $primary_photo): ?>
            <img src="MBusers/photos/<?php echo htmlspecialchars($primary_photo); ?>" 
                 alt="<?php echo htmlspecialchars($display_name); ?>" 
                 class="profile-main-avatar <?php echo $viewed_user_has_premium ? 'premium-avatar' : ''; ?>"
                 style="width:90px;height:90px;object-fit:cover;border-radius:50%;border:4px solid rgba(255,255,255,0.2);box-shadow:var(--shadow-md)">
          <?php else: ?>
            <div class="profile-main-avatar <?php echo $viewed_user_has_premium ? 'premium-avatar' : ''; ?>" 
                 style="width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,var(--accent-purple),var(--accent-pink));display:flex;align-items:center;justify-content:center;font-size:2.5rem;border:4px solid rgba(255,255,255,0.2)">
              <?php echo strtoupper(substr($display_name, 0, 1)); ?>
            </div>
          <?php endif; ?>
          <div>
            <h2 style="font-size:2rem;font-weight:700;margin-bottom:6px">
              <?php echo htmlspecialchars($display_name); ?>
              <?php if ($display_age): ?>
                <span style="color:var(--muted)"> · <?php echo $display_age; ?></span>
              <?php endif; ?>
            </h2>
            <div style="display:flex;gap:12px;flex-wrap:wrap">
              <?php if ($profile['gender']): ?>
                <span class="badge"><?php echo ucfirst(htmlspecialchars($profile['gender'])); ?></span>
              <?php endif; ?>
              <?php if (!empty($prefs['location'])): ?>
                <span class="badge">📍 <?php echo htmlspecialchars($prefs['location']); ?></span>
              <?php endif; ?>
              <?php if ($viewed_user_has_premium): ?>
                <span class="badge premium-badge">✨ Premium</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div>
          <?php if ($is_self): ?>
            <a href="profile_edit.php" class="btn">✏️ Edit Profile</a>
            <a href="subscription.php" class="btn btn-ghost" style="margin-left: 12px;">✨ Manage Premium</a>
          <?php else: ?>
            <!-- Actions for viewing other users -->
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
              <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('Are you sure you want to block this user? They will no longer appear in your feed.')">
                <input type="hidden" name="action" value="block">
                <input type="hidden" name="target_id" value="<?php echo $profile_id; ?>">
                <button type="submit" class="btn-action btn-block">
                  <span class="btn-icon">🚫</span>
                  <span>Block User</span>
                </button>
              </form>
              <a href="report.php?user_id=<?php echo $profile_id; ?>" class="btn-action btn-report">
                <span class="btn-icon">⚠️</span>
                <span>Report User</span>
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Content Grid -->
    <div style="display:grid;grid-template-columns:350px 1fr;gap:24px;margin-top:24px">
      <!-- Sidebar -->
      <aside style="display:flex;flex-direction:column;gap:20px">
        <!-- Photo Gallery -->
        <?php if (!empty($photos) && count($photos) > 1): ?>
        <div class="card">
          <h3 style="font-size:1.2rem;font-weight:700;margin-bottom:16px">📷 Photos</h3>
          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px">
            <?php foreach ($photos as $ph): ?>
              <img src="MBusers/photos/<?php echo htmlspecialchars($ph); ?>" 
                   alt="photo" 
                   style="width:100%;height:140px;object-fit:cover;border-radius:10px;cursor:pointer;transition:var(--transition)"
                   onmouseover="this.style.transform='scale(1.05)'"
                   onmouseout="this.style.transform='scale(1)'">
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Preferences Card -->
        <div class="card">
          <h3 style="font-size:1.2rem;font-weight:700;margin-bottom:16px">⚙️ Preferences</h3>
          <div style="display:flex;flex-direction:column;gap:14px">
            <div style="display:flex;align-items:center;gap:10px;padding:12px;background:rgba(0,0,0,0.2);border-radius:8px">
              <span style="font-size:1.3rem">🎂</span>
              <div style="flex:1">
                <div style="font-size:0.85rem;color:var(--muted)">Age Range</div>
                <div style="font-weight:600"><?php echo htmlspecialchars($prefs['min_age'] ?? '—'); ?> - <?php echo htmlspecialchars($prefs['max_age'] ?? '—'); ?> years</div>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;padding:12px;background:rgba(0,0,0,0.2);border-radius:8px">
              <span style="font-size:1.3rem">👤</span>
              <div style="flex:1">
                <div style="font-size:0.85rem;color:var(--muted)">Gender Preference</div>
                <div style="font-weight:600"><?php echo ucfirst(htmlspecialchars($prefs['gender_pref'] ?? '—')); ?></div>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;padding:12px;background:rgba(0,0,0,0.2);border-radius:8px">
              <span style="font-size:1.3rem">💝</span>
              <div style="flex:1">
                <div style="font-size:0.85rem;color:var(--muted)">Looking For</div>
                <div style="font-weight:600"><?php echo ucfirst(htmlspecialchars($prefs['relationship_type'] ?? '—')); ?></div>
              </div>
            </div>
          </div>
        </div>
      </aside>

      <!-- Main Content -->
      <section style="display:flex;flex-direction:column;gap:20px">
        <!-- Bio Card -->
        <div class="card">
          <h3 style="font-size:1.3rem;font-weight:700;margin-bottom:16px">📖 About Me</h3>
          <p style="line-height:1.8;color:rgba(239,233,255,0.9);font-size:1.05rem">
            <?php echo nl2br(htmlspecialchars($profile['bio'] ?: 'This user hasn\'t written a bio yet.')); ?>
          </p>
        </div>

        <?php if ($viewed_user_has_premium): ?>
        <!-- Premium Subscription Info -->
        <div class="card subscription-card">
          <h3 style="font-size:1.3rem;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px">
            <span>✨</span> Premium Member
          </h3>
          <div style="display:flex;flex-direction:column;gap:12px">
            <div style="display:flex;align-items:center;gap:12px">
              <span style="background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#1f2937;padding:6px 12px;border-radius:8px;font-size:0.85rem;font-weight:700;text-transform:uppercase;">
                <?php 
                $plan_display = '';
                switch ($viewed_user_plan_type) {
                    case 'boost_2x': $plan_display = '2x Boost'; break;
                    case 'boost_5x': $plan_display = '5x Boost'; break;
                    case 'boost_10x': $plan_display = '10x Boost'; break;
                    default: $plan_display = 'Premium'; break;
                }
                echo $plan_display; 
                ?>
              </span>
              <span style="color:var(--muted);font-size:0.9rem">
                Enhanced profile visibility and priority matching
              </span>
            </div>
            <div style="color:rgba(251,191,36,0.8);font-size:0.9rem;font-style:italic">
              <?php 
              switch ($viewed_user_plan_type) {
                  case 'boost_2x': echo 'This profile appears 2x more often in discovery'; break;
                  case 'boost_5x': echo 'This profile appears 5x more often in discovery'; break;
                  case 'boost_10x': echo 'This profile appears 10x more often in discovery'; break;
                  default: echo 'This profile has enhanced visibility'; break;
              }
              ?>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </section>
    </div>
  </section>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
