<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if user is admin
$stmt = $conn->prepare("SELECT role FROM Users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

if ($role !== 'admin') {
    header('Location: index.php');
    exit;
}

// Fetch admin user details
$stmt = $conn->prepare("SELECT email, u.id FROM Users u WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($email, $uid);
$stmt->fetch();
$stmt->close();

// Fetch some statistics
$stats = [];

// Total users
$result = $conn->query("SELECT COUNT(*) as count FROM Users");
$stats['users'] = $result->fetch_assoc()['count'];

// Total profiles
$result = $conn->query("SELECT COUNT(*) as count FROM Profiles");
$stats['profiles'] = $result->fetch_assoc()['count'];

// Total matches
$result = $conn->query("SELECT COUNT(*) as count FROM Matches");
$stats['matches'] = $result->fetch_assoc()['count'];

// Total messages
$result = $conn->query("SELECT COUNT(*) as count FROM Messages");
$stats['messages'] = $result->fetch_assoc()['count'];

// Total events
$result = $conn->query("SELECT COUNT(*) as count FROM Events");
$stats['events'] = $result->fetch_assoc()['count'];

// Pending reports
$result = $conn->query("SELECT COUNT(*) as count FROM Reports WHERE status = 'pending'");
$stats['pending_reports'] = $result->fetch_assoc()['count'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/png" href="assets/favicon.png">
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container">
    <!-- Admin Dashboard Header -->
    <div style="margin-bottom:32px">
        <h1 style="font-size:2rem;font-weight:600;margin-bottom:8px;color:var(--text)">
            🛡️ Admin Dashboard
        </h1>
        <p style="color:var(--muted);font-size:1rem">
            Manage all aspects of Meet Beyond platform
        </p>
    </div>

    <!-- Statistics Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:32px">
        <div class="card" style="text-align:center;padding:24px">
            <div style="font-size:2.5rem;margin-bottom:8px">👥</div>
            <div style="font-size:2rem;font-weight:700;margin-bottom:4px"><?php echo $stats['users']; ?></div>
            <div style="color:var(--muted)">Total Users</div>
        </div>
        <div class="card" style="text-align:center;padding:24px">
            <div style="font-size:2.5rem;margin-bottom:8px">📝</div>
            <div style="font-size:2rem;font-weight:700;margin-bottom:4px"><?php echo $stats['profiles']; ?></div>
            <div style="color:var(--muted)">Profiles</div>
        </div>
        <div class="card" style="text-align:center;padding:24px">
            <div style="font-size:2.5rem;margin-bottom:8px">❤️</div>
            <div style="font-size:2rem;font-weight:700;margin-bottom:4px"><?php echo $stats['matches']; ?></div>
            <div style="color:var(--muted)">Matches</div>
        </div>
        <div class="card" style="text-align:center;padding:24px">
            <div style="font-size:2.5rem;margin-bottom:8px">💬</div>
            <div style="font-size:2rem;font-weight:700;margin-bottom:4px"><?php echo $stats['messages']; ?></div>
            <div style="color:var(--muted)">Messages</div>
        </div>
        <div class="card" style="text-align:center;padding:24px">
            <div style="font-size:2.5rem;margin-bottom:8px">📅</div>
            <div style="font-size:2rem;font-weight:700;margin-bottom:4px"><?php echo $stats['events']; ?></div>
            <div style="color:var(--muted)">Events</div>
        </div>
        <div class="card" style="text-align:center;padding:24px;border:2px solid rgba(239,68,68,0.3)">
            <div style="font-size:2.5rem;margin-bottom:8px">⚠️</div>
            <div style="font-size:2rem;font-weight:700;margin-bottom:4px"><?php echo $stats['pending_reports']; ?></div>
            <div style="color:var(--muted)">Pending Reports</div>
        </div>
    </div>

    <!-- Management Options -->
    <h2 style="font-size:1.5rem;font-weight:600;margin-bottom:20px;color:var(--text)">
        Database Management
    </h2>
    
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-bottom:32px">
        <a href="admin_manage.php?table=Users" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="flex-shrink:0;width:48px;height:48px;background:rgba(59,130,246,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px">
                👥
            </div>
            <div>
                <h4 style="font-size:1.1rem;font-weight:600;margin-bottom:4px;color:var(--text)">Users</h4>
                <p style="color:var(--muted);font-size:0.875rem;margin:0">Manage user accounts</p>
            </div>
        </a>

        <a href="admin_manage.php?table=Profiles" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="flex-shrink:0;width:48px;height:48px;background:rgba(139,92,246,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px">
                📝
            </div>
            <div>
                <h4 style="font-size:1.1rem;font-weight:600;margin-bottom:4px;color:var(--text)">Profiles</h4>
                <p style="color:var(--muted);font-size:0.875rem;margin:0">Manage user profiles</p>
            </div>
        </a>

        <a href="admin_manage.php?table=Photos" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="flex-shrink:0;width:48px;height:48px;background:rgba(236,72,153,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px">
                📷
            </div>
            <div>
                <h4 style="font-size:1.1rem;font-weight:600;margin-bottom:4px;color:var(--text)">Photos</h4>
                <p style="color:var(--muted);font-size:0.875rem;margin:0">Manage user photos</p>
            </div>
        </a>

        <a href="admin_manage.php?table=Preferences" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="flex-shrink:0;width:48px;height:48px;background:rgba(251,146,60,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px">
                ⚙️
            </div>
            <div>
                <h4 style="font-size:1.1rem;font-weight:600;margin-bottom:4px;color:var(--text)">Preferences</h4>
                <p style="color:var(--muted);font-size:0.875rem;margin:0">User preferences</p>
            </div>
        </a>

        <a href="admin_manage.php?table=Matches" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="flex-shrink:0;width:48px;height:48px;background:rgba(236,72,153,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px">
                ❤️
            </div>
            <div>
                <h4 style="font-size:1.1rem;font-weight:600;margin-bottom:4px;color:var(--text)">Matches</h4>
                <p style="color:var(--muted);font-size:0.875rem;margin:0">User matches</p>
            </div>
        </a>

        <a href="admin_manage.php?table=Likes" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="flex-shrink:0;width:48px;height:48px;background:rgba(167,139,250,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px">
                💖
            </div>
            <div>
                <h4 style="font-size:1.1rem;font-weight:600;margin-bottom:4px;color:var(--text)">Likes</h4>
                <p style="color:var(--muted);font-size:0.875rem;margin:0">User likes & passes</p>
            </div>
        </a>

        <a href="admin_manage.php?table=Messages" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="flex-shrink:0;width:48px;height:48px;background:rgba(34,197,94,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px">
                💬
            </div>
            <div>
                <h4 style="font-size:1.1rem;font-weight:600;margin-bottom:4px;color:var(--text)">Messages</h4>
                <p style="color:var(--muted);font-size:0.875rem;margin:0">User messages</p>
            </div>
        </a>

        <a href="admin_manage.php?table=Events" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="flex-shrink:0;width:48px;height:48px;background:rgba(52,211,153,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px">
                📅
            </div>
            <div>
                <h4 style="font-size:1.1rem;font-weight:600;margin-bottom:4px;color:var(--text)">Events</h4>
                <p style="color:var(--muted);font-size:0.875rem;margin:0">Platform events</p>
            </div>
        </a>

        <a href="admin_manage.php?table=EventParticipants" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="flex-shrink:0;width:48px;height:48px;background:rgba(14,165,233,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px">
                🎫
            </div>
            <div>
                <h4 style="font-size:1.1rem;font-weight:600;margin-bottom:4px;color:var(--text)">Event Participants</h4>
                <p style="color:var(--muted);font-size:0.875rem;margin:0">Event registrations</p>
            </div>
        </a>

        <a href="admin_manage.php?table=Blocks" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="flex-shrink:0;width:48px;height:48px;background:rgba(239,68,68,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px">
                🚫
            </div>
            <div>
                <h4 style="font-size:1.1rem;font-weight:600;margin-bottom:4px;color:var(--text)">Blocks</h4>
                <p style="color:var(--muted);font-size:0.875rem;margin:0">Blocked users</p>
            </div>
        </a>

        <a href="admin_manage.php?table=Reports" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease;border:2px solid rgba(239,68,68,0.2)" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="flex-shrink:0;width:48px;height:48px;background:rgba(239,68,68,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px">
                ⚠️
            </div>
            <div>
                <h4 style="font-size:1.1rem;font-weight:600;margin-bottom:4px;color:var(--text)">Reports</h4>
                <p style="color:var(--muted);font-size:0.875rem;margin:0">User reports</p>
            </div>
        </a>

        <a href="admin_manage.php?table=Subscriptions" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="flex-shrink:0;width:48px;height:48px;background:rgba(168,85,247,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px">
                💳
            </div>
            <div>
                <h4 style="font-size:1.1rem;font-weight:600;margin-bottom:4px;color:var(--text)">Subscriptions</h4>
                <p style="color:var(--muted);font-size:0.875rem;margin:0">User subscriptions</p>
            </div>
        </a>

        <a href="admin_manage.php?table=QuizQuestions" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="flex-shrink:0;width:48px;height:48px;background:rgba(99,102,241,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px">
                ❓
            </div>
            <div>
                <h4 style="font-size:1.1rem;font-weight:600;margin-bottom:4px;color:var(--text)">Quiz Questions</h4>
                <p style="color:var(--muted);font-size:0.875rem;margin:0">Manage quiz questions</p>
            </div>
        </a>

        <a href="admin_manage.php?table=QuizAnswers" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="flex-shrink:0;width:48px;height:48px;background:rgba(99,102,241,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px">
                ✅
            </div>
            <div>
                <h4 style="font-size:1.1rem;font-weight:600;margin-bottom:4px;color:var(--text)">Quiz Answers</h4>
                <p style="color:var(--muted);font-size:0.875rem;margin:0">User quiz responses</p>
            </div>
        </a>

        <a href="admin_manage.php?table=Logs" class="card" style="display:flex;align-items:center;gap:16px;padding:20px;text-decoration:none;transition:all 0.3s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="flex-shrink:0;width:48px;height:48px;background:rgba(148,163,184,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px">
                📋
            </div>
            <div>
                <h4 style="font-size:1.1rem;font-weight:600;margin-bottom:4px;color:var(--text)">Logs</h4>
                <p style="color:var(--muted);font-size:0.875rem;margin:0">System activity logs</p>
            </div>
        </a>
    </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
