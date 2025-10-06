<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get matches involving this user (so they can message their matches)
$stmt = $conn->prepare("
    SELECT 
        m.id AS match_id,
        CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END AS other_user_id,
        p.name, p.age, u.email
    FROM Matches m
    LEFT JOIN Profiles p ON p.user_id = CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END
    LEFT JOIN Users u ON u.id = CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END
    WHERE m.user1_id = ? OR m.user2_id = ?
    ORDER BY m.matched_at DESC
");
$stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$stmt->bind_result($match_id, $other_user_id, $other_name, $other_age, $other_email);

$matches = [];
while ($stmt->fetch()) {
    $matches[] = [
        'match_id' => $match_id,
        'other_user_id' => $other_user_id,
        'name' => $other_name,
        'age' => $other_age,
        'email' => $other_email
    ];
}
$stmt->close();

// If a match is selected, show conversation
$selected_match = isset($_GET['match']) ? intval($_GET['match']) : 0;
$messages = [];
if ($selected_match) {
    // Make sure this match belongs to this user
    $stmt = $conn->prepare("SELECT id FROM Matches WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
    $stmt->bind_param("iii", $selected_match, $user_id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 1) {
        // Get messages for this match
        $msgStmt = $conn->prepare("
            SELECT m.message, m.sender_id, m.sent_at, p.name
            FROM Messages m
            LEFT JOIN Profiles p ON p.user_id = m.sender_id
            WHERE m.match_id = ?
            ORDER BY m.sent_at ASC
        ");
        $msgStmt->bind_param("i", $selected_match);
        $msgStmt->execute();
        $msgStmt->bind_result($msg_text, $msg_sender, $msg_time, $msg_sender_name);
        while ($msgStmt->fetch()) {
            $messages[] = [
                'sender_id' => $msg_sender,
                'sender_name' => $msg_sender_name,
                'text' => $msg_text,
                'time' => $msg_time
            ];
        }
        $msgStmt->close();
    }
    $stmt->close();
}

// Handle sending a message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selected_match) {
    $message = trim($_POST['message']);
    if ($message !== '') {
        $stmt = $conn->prepare("INSERT INTO Messages (match_id, sender_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $selected_match, $user_id, $message);
        $stmt->execute();
        $stmt->close();
        header("Location: messages.php?match=$selected_match");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Messages | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container">
    <section class="card" style="max-width:950px;margin:0 auto">
        <div class="page-top">
            <div>
                <h2 class="page-title">Messages</h2>
                <p class="lead">Conversations with your matches</p>
            </div>
        </div>

        <div class="grid" style="grid-template-columns:220px 1fr;gap:28px;align-items:start">
            <aside>
                <div style="font-weight:700;margin-bottom:12px">Your Matches</div>
                <?php if (empty($matches)): ?>
                    <div class="muted">No matches yet.</div>
                <?php else: ?>
                    <nav class="form-row">
                        <?php foreach ($matches as $match): ?>
                            <?php $isActive = ($selected_match == $match['match_id']); ?>
                            <a href="messages.php?match=<?php echo $match['match_id']; ?>" class="dashboard-link" style="padding:10px 12px;display:block;">
                                <svg aria-hidden="true"><use xlink:href="assets/icons.svg#icon-profile"></use></svg>
                                <div style="margin-left:8px">
                                    <div class="label" style="font-weight:<?php echo $isActive ? '800' : '600'; ?>;color:var(--text)"><?php echo htmlspecialchars($match['name'] ?: $match['email']); if ($match['age']) echo " ({$match['age']})"; ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
            </aside>

            <section>
                <?php if (!$selected_match): ?>
                    <div class="muted">Select a match to view or send messages.</div>
                <?php else: ?>
                    <div style="max-height:420px;overflow-y:auto;padding:12px;border-radius:12px;background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01));margin-bottom:18px">
                        <?php if (empty($messages)): ?>
                            <div class="muted">No messages yet. Say hi!</div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <div style="margin-bottom:14px">
                                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                                        <div style="font-weight:700;color:var(--text)"><?php echo htmlspecialchars($msg['sender_id'] == $user_id ? 'You' : ($msg['sender_name'] ?: 'Match')); ?></div>
                                        <div class="muted" style="font-size:0.9rem"><?php echo date('M d, H:i', strtotime($msg['time'])); ?></div>
                                    </div>
                                    <div style="padding:10px 12px;border-radius:10px;background:rgba(0,0,0,0.04);color:var(--text)"><?php echo nl2br(htmlspecialchars($msg['text'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <form method="POST" style="display:flex;gap:12px;align-items:center">
                        <input type="text" name="message" placeholder="Type your message..." required style="flex:1;padding:12px 16px;border-radius:10px;border:1px solid rgba(255,255,255,0.06);background:rgba(255,255,255,0.03);color:var(--text)">
                        <button type="submit" class="btn btn-hero">Send</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>

        <div style="margin-top:20px">
            <a href="index.php" class="btn-ghost">← Back to Dashboard</a>
        </div>
    </section>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>