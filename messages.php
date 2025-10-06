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
<div class="dashboard-hero">
    <div class="dashboard-box" style="max-width: 950px;">
        <h2 class="dashboard-title" style="margin-bottom: 10px;">Messages</h2>
        <div style="display:flex;gap:38px;">
            <!-- Matches sidebar -->
            <div style="min-width:210px;">
                <div style="font-weight:600;margin-bottom:12px;">Your Matches</div>
                <?php if (empty($matches)): ?>
                    <div style="color:#7b7ce9;">No matches yet.</div>
                <?php else: ?>
                    <?php foreach ($matches as $match): ?>
                        <div style="margin-bottom:10px;">
                            <a href="messages.php?match=<?php echo $match['match_id']; ?>"
                               style="color:<?php echo ($selected_match == $match['match_id']) ? '#5b5bd6' : '#7b7ce9'; ?>;font-weight:<?php echo ($selected_match == $match['match_id']) ? '700' : '500'; ?>;text-decoration:underline;">
                               <?php
                                   echo htmlspecialchars($match['name'] ?: $match['email']);
                                   if ($match['age']) echo " ({$match['age']})";
                               ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <!-- Conversation -->
            <div style="flex:1;">
                <?php if (!$selected_match): ?>
                    <div style="color:#7b7ce9;">Select a match to view or send messages.</div>
                <?php else: ?>
                    <div style="max-height:340px;overflow-y:auto;background:#f7f8fc;padding:18px 12px 18px 18px;border-radius:16px;margin-bottom:18px;">
                        <?php if (empty($messages)): ?>
                            <div style="color:#bdbfcf;">No messages yet. Say hi!</div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <div style="margin-bottom:14px;">
                                    <span style="color:#7b7ce9;font-weight:600;">
                                        <?php echo htmlspecialchars($msg['sender_id'] == $user_id ? 'You' : ($msg['sender_name'] ?: 'Match')); ?>
                                    </span>
                                    <span style="color:#aaa;font-size:0.93em;">
                                        <?php echo date('M d, H:i', strtotime($msg['time'])); ?>
                                    </span>
                                    <div style="margin-top:2px;"><?php echo nl2br(htmlspecialchars($msg['text'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <form method="POST" style="display:flex;gap:12px;">
                        <input type="text" name="message" placeholder="Type your message..." required style="flex:1;padding:12px 16px;border-radius:10px;border:1px solid #e0e3ee;">
                        <button type="submit" class="btn" style="padding:12px 28px;">Send</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <a href="index.php" style="display:block;margin-top:40px;color:#aaa;text-decoration:underline;font-size:0.95em;">&#8592; Back to Dashboard</a>
    </div>
</div>
</body>
</html>