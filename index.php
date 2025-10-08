<?php
session_start();
require 'config.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_name = '';

if ($is_logged_in) {
    // 1. Ensure profile is complete (name, age, gender)
    $stmt = $conn->prepare("SELECT name, age, gender FROM Profiles WHERE user_id=?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($name, $age, $gender);
    $stmt->fetch();
    $stmt->close();

    if (empty($name) || empty($age) || empty($gender)) {
        // Profile incomplete
        header('Location: profile.php?complete=1');
        exit;
    }

    // 2. Ensure preferences are complete (min_age, max_age, gender_pref, location, relationship_type)
    $stmt = $conn->prepare("SELECT min_age, max_age, gender_pref, location, relationship_type FROM Preferences WHERE user_id=?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($min_age, $max_age, $gender_pref, $location, $relationship_type);
    $stmt->fetch();
    $stmt->close();

    if (empty($min_age) || empty($max_age) || empty($gender_pref) || empty($location) || empty($relationship_type)) {
        // Preferences incomplete
        header('Location: preferences.php?complete=1');
        exit;
    }

    // 3. Ensure a primary photo exists
    $stmt = $conn->prepare("SELECT id FROM Photos WHERE user_id=? AND is_primary=1 AND is_active=1 LIMIT 1");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($photo_id);
    $has_primary_photo = $stmt->fetch();
    $stmt->close();

    if (!$has_primary_photo) {
        header('Location: photos.php?complete=1');
        exit;
    }

    // Fetch email for fallback display
    $stmt = $conn->prepare("SELECT email FROM Users WHERE id=?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($email);
    $stmt->fetch();
    $stmt->close();

    // Fetch primary photo path for avatar
    $stmt = $conn->prepare("SELECT file_path FROM Photos WHERE user_id=? AND is_primary=1 AND is_active=1 LIMIT 1");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($avatar_path);
    $has_avatar = $stmt->fetch();
    $stmt->close();

    $user_name = $name ?: $email;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container">
    <?php if (!$is_logged_in): ?>
        <!-- Hero Section -->
        <section class="card card-hero" style="min-height:450px">
            <div style="flex:1">
                <div style="display:inline-block;padding:8px 16px;background:rgba(167,139,250,0.2);border-radius:20px;margin-bottom:20px;font-size:0.9rem;font-weight:600">
                    ✨ Find Your Perfect Match
                </div>
                <h1 class="page-title" style="font-size:3rem;margin-bottom:16px">
                    Welcome to <span class="brand"><span class="accent">Meet</span> Beyond</span>
                </h1>
                <p class="lead" style="font-size:1.2rem;margin-bottom:32px">
                    Connect with amazing people. Build meaningful relationships. Create lasting memories.
                </p>
                <div class="hero-cta" style="gap:16px">
                    <a href="register.php" class="btn-hero" style="padding:16px 32px;font-size:1.1rem">
                        Get Started Free
                    </a>
                    <a href="browse.php" class="btn-ghost" style="padding:16px 28px">
                        Browse Profiles
                    </a>
                </div>
                <div style="display:flex;gap:24px;margin-top:40px;flex-wrap:wrap">
                    <div style="display:flex;align-items:center;gap:8px">
                        <span style="font-size:1.5rem">✓</span>
                        <span style="color:var(--muted)">Smart Matching</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px">
                        <span style="font-size:1.5rem">✓</span>
                        <span style="color:var(--muted)">Safe & Secure</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px">
                        <span style="font-size:1.5rem">✓</span>
                        <span style="color:var(--muted)">Real Events</span>
                    </div>
                </div>
            </div>
            <div style="flex:0 0 300px;display:flex;align-items:center;justify-content:center">
                <div style="position:relative;width:280px;height:280px">
                    <div style="position:absolute;inset:0;background:linear-gradient(135deg,var(--accent-purple),var(--accent-pink));border-radius:50%;opacity:0.2;filter:blur(40px)"></div>
                    <div style="position:relative;width:100%;height:100%;background:rgba(255,255,255,0.08);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:8rem;backdrop-filter:blur(10px);border:2px solid rgba(255,255,255,0.1)">
                        💝
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;margin-top:40px">
            <div class="card" style="text-align:center">
                <div style="font-size:3rem;margin-bottom:16px">🎯</div>
                <h3 style="font-size:1.3rem;margin-bottom:12px;font-weight:700">Smart Matching</h3>
                <p style="color:var(--muted);line-height:1.6">
                    Our advanced algorithm finds people who share your interests and values
                </p>
            </div>
            <div class="card" style="text-align:center">
                <div style="font-size:3rem;margin-bottom:16px">💬</div>
                <h3 style="font-size:1.3rem;margin-bottom:12px;font-weight:700">Real Conversations</h3>
                <p style="color:var(--muted);line-height:1.6">
                    Connect through meaningful messages and genuine interactions
                </p>
            </div>
            <div class="card" style="text-align:center">
                <div style="font-size:3rem;margin-bottom:16px">🎉</div>
                <h3 style="font-size:1.3rem;margin-bottom:12px;font-weight:700">Local Events</h3>
                <p style="color:var(--muted);line-height:1.6">
                    Meet in person at fun, safe events happening in your area
                </p>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="card" style="margin-top:40px;text-align:center;background:linear-gradient(135deg,rgba(167,139,250,0.15),rgba(244,114,182,0.15))">
            <h2 style="font-size:2rem;margin-bottom:32px;font-weight:700">Join Thousands of Happy Members</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:32px">
                <div>
                    <div style="font-size:2.5rem;font-weight:800;color:var(--accent-purple);margin-bottom:8px">10K+</div>
                    <div style="color:var(--muted);font-size:1.05rem">Active Users</div>
                </div>
                <div>
                    <div style="font-size:2.5rem;font-weight:800;color:var(--accent-pink);margin-bottom:8px">5K+</div>
                    <div style="color:var(--muted);font-size:1.05rem">Successful Matches</div>
                </div>
                <div>
                    <div style="font-size:2.5rem;font-weight:800;color:var(--accent-blue);margin-bottom:8px">100+</div>
                    <div style="color:var(--muted);font-size:1.05rem">Events Monthly</div>
                </div>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="card" style="margin-top:40px;text-align:center;background:linear-gradient(135deg,rgba(124,58,237,0.2),rgba(244,114,182,0.2))">
            <h2 style="font-size:2.2rem;margin-bottom:16px;font-weight:700">Ready to Find Your Match?</h2>
            <p style="font-size:1.1rem;color:var(--muted);margin-bottom:32px">
                Join for free and start connecting with amazing people today
            </p>
            <a href="register.php" class="btn-hero" style="padding:18px 40px;font-size:1.15rem">
                Create Your Profile Now →
            </a>
        </div>
        <?php else: ?>
        <!-- Professional Dashboard Header -->
        <div style="margin-bottom:32px">
            <h1 style="font-size:2rem;font-weight:600;margin-bottom:8px;color:var(--text)">
                Welcome back, <?php echo htmlspecialchars($user_name); ?>
            </h1>
            <p style="color:var(--muted);font-size:1rem">
                Manage your connections and discover new opportunities
            </p>
        </div>

        <!-- Main Dashboard Grid -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:24px;margin-bottom:24px">
            <!-- Discover Card -->
            <a href="discover.php" class="card" style="display:flex;align-items:center;gap:20px;padding:24px;text-decoration:none;transition:all 0.3s ease;border:2px solid rgba(236,72,153,0.2)" onmouseover="this.style.transform='translateY(-4px)';this.style.borderColor='rgba(236,72,153,0.5)'" onmouseout="this.style.transform='translateY(0)';this.style.borderColor='rgba(236,72,153,0.2)'">
                <div style="flex-shrink:0;width:56px;height:56px;background:linear-gradient(135deg,rgba(236,72,153,0.2),rgba(219,39,119,0.3));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:28px">
                    💖
                </div>
                <div style="flex:1">
                    <h3 style="font-size:1.25rem;font-weight:600;margin-bottom:6px;color:var(--text)">Discover</h3>
                    <p style="color:var(--muted);font-size:0.95rem;margin:0">Find and match with new people</p>
                </div>
            </a>

            <!-- Matches Card -->
            <a href="matches.php" class="card" style="display:flex;align-items:center;gap:20px;padding:24px;text-decoration:none;transition:all 0.3s ease;border:2px solid rgba(167,139,250,0.2)" onmouseover="this.style.transform='translateY(-4px)';this.style.borderColor='rgba(167,139,250,0.5)'" onmouseout="this.style.transform='translateY(0)';this.style.borderColor='rgba(167,139,250,0.2)'">
                <div style="flex-shrink:0;width:56px;height:56px;background:linear-gradient(135deg,rgba(167,139,250,0.2),rgba(139,92,246,0.3));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:28px">
                    ❤️
                </div>
                <div style="flex:1">
                    <h3 style="font-size:1.25rem;font-weight:600;margin-bottom:6px;color:var(--text)">Matches</h3>
                    <p style="color:var(--muted);font-size:0.95rem;margin:0">View your mutual connections</p>
                </div>
            </a>

            <!-- Messages Card -->
            <a href="messages.php" class="card" style="display:flex;align-items:center;gap:20px;padding:24px;text-decoration:none;transition:all 0.3s ease;border:2px solid rgba(59,130,246,0.2)" onmouseover="this.style.transform='translateY(-4px)';this.style.borderColor='rgba(59,130,246,0.5)'" onmouseout="this.style.transform='translateY(0)';this.style.borderColor='rgba(59,130,246,0.2)'">
                <div style="flex-shrink:0;width:56px;height:56px;background:linear-gradient(135deg,rgba(59,130,246,0.2),rgba(37,99,235,0.3));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:28px">
                    💬
                </div>
                <div style="flex:1">
                    <h3 style="font-size:1.25rem;font-weight:600;margin-bottom:6px;color:var(--text)">Messages</h3>
                    <p style="color:var(--muted);font-size:0.95rem;margin:0">Chat with your connections</p>
                </div>
            </a>
        </div>

        <!-- Secondary Options -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px">
            <a href="browse.php" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="flex-shrink:0;width:44px;height:44px;background:rgba(167,139,250,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px">
                    👥
                </div>
                <div>
                    <h4 style="font-size:1.05rem;font-weight:600;margin-bottom:4px;color:var(--text)">Browse</h4>
                    <p style="color:var(--muted);font-size:0.875rem;margin:0">Explore all profiles</p>
                </div>
            </a>

            <a href="events.php" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="flex-shrink:0;width:44px;height:44px;background:rgba(52,211,153,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px">
                    📅
                </div>
                <div>
                    <h4 style="font-size:1.05rem;font-weight:600;margin-bottom:4px;color:var(--text)">Events</h4>
                    <p style="color:var(--muted);font-size:0.875rem;margin:0">Nearby gatherings</p>
                </div>
            </a>

            <a href="profile_view.php" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="flex-shrink:0;width:44px;height:44px;background:rgba(139,92,246,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px">
                    👤
                </div>
                <div>
                    <h4 style="font-size:1.05rem;font-weight:600;margin-bottom:4px;color:var(--text)">Profile</h4>
                    <p style="color:var(--muted);font-size:0.875rem;margin:0">View & edit your profile</p>
                </div>
            </a>

            <a href="preferences.php" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="flex-shrink:0;width:44px;height:44px;background:rgba(251,146,60,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px">
                    ⚙️
                </div>
                <div>
                    <h4 style="font-size:1.05rem;font-weight:600;margin-bottom:4px;color:var(--text)">Preferences</h4>
                    <p style="color:var(--muted);font-size:0.875rem;margin:0">Update match settings</p>
                </div>
            </a>
        </div>
    <?php endif; ?>
</main>

<script src="assets/theme.js"></script>
</body>
</html>