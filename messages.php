<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get matches involving this user with their photos
$stmt = $conn->prepare("
    SELECT 
        m.id AS match_id,
        CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END AS other_user_id,
        p.name, p.age, u.email, ph.file_path
    FROM Matches m
    LEFT JOIN Profiles p ON p.user_id = CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END
    LEFT JOIN Users u ON u.id = CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END
    LEFT JOIN Photos ph ON ph.user_id = CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END AND ph.is_primary = 1 AND ph.is_active = 1
    WHERE m.user1_id = ? OR m.user2_id = ?
    ORDER BY m.matched_at DESC
");
$stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$stmt->bind_result($match_id, $other_user_id, $other_name, $other_age, $other_email, $photo_path);

$matches = [];
while ($stmt->fetch()) {
    $matches[] = [
        'match_id' => $match_id,
        'other_user_id' => $other_user_id,
        'name' => $other_name,
        'age' => $other_age,
        'email' => $other_email,
        'photo' => $photo_path
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
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .messaging-container {
            display: grid;
            grid-template-columns: 360px 1fr;
            grid-template-rows: 1fr;
            height: 700px;
            min-height: 600px;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            border: 2px solid rgba(167,139,250,0.2);
        }
        
        /* Sidebar Styles */
        .conversations-sidebar {
            background: rgba(41,20,63,0.5);
            border-right: 2px solid rgba(167,139,250,0.15);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .sidebar-header {
            padding: 24px;
            border-bottom: 2px solid rgba(167,139,250,0.15);
        }
        .sidebar-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 4px;
        }
        .sidebar-subtitle {
            color: var(--muted);
            font-size: 0.9rem;
        }
        .conversations-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
        }
        .conversation-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-bottom: 4px;
            border: 2px solid transparent;
        }
        .conversation-item:hover {
            background: rgba(167,139,250,0.1);
            transform: translateX(4px);
        }
        .conversation-item.active {
            background: linear-gradient(135deg, rgba(236,72,153,0.2), rgba(167,139,250,0.2));
            border-color: rgba(236,72,153,0.3);
        }
        .conversation-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8b5cf6, #ec4899);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
            border: 3px solid rgba(255,255,255,0.1);
        }
        .conversation-info {
            flex: 1;
            min-width: 0;
        }
        .conversation-name {
            font-weight: 700;
            color: var(--text);
            font-size: 1.05rem;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .conversation-age {
            color: var(--muted);
            font-size: 0.85rem;
        }
        
        /* Chat Area Styles */
        .chat-area {
            display: grid;
            grid-template-rows: auto 1fr auto;
            background: rgba(31,15,51,0.3);
            height: 100%;
            max-height: 100%;
            overflow: hidden;
        }
        .chat-header {
            padding: 24px 28px;
            background: rgba(41,20,63,0.6);
            border-bottom: 2px solid rgba(167,139,250,0.15);
            display: flex;
            align-items: center;
            gap: 16px;
            flex-shrink: 0;
        }
        .chat-header-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8b5cf6, #ec4899);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 700;
            color: white;
            border: 2px solid rgba(255,255,255,0.2);
        }
        .chat-header-info h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 2px;
        }
        .chat-header-info p {
            color: var(--muted);
            font-size: 0.9rem;
        }
        .messages-container {
            overflow-y: auto;
            overflow-x: hidden;
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            min-height: 0;
            scroll-behavior: smooth;
        }
        
        /* Custom scrollbar for webkit browsers */
        .messages-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .messages-container::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
            border-radius: 3px;
        }
        
        .messages-container::-webkit-scrollbar-thumb {
            background: rgba(167,139,250,0.3);
            border-radius: 3px;
        }
        
        .messages-container::-webkit-scrollbar-thumb:hover {
            background: rgba(167,139,250,0.5);
        }
        .message-bubble {
            max-width: 70%;
            padding: 14px 18px;
            border-radius: 18px;
            position: relative;
            animation: messageSlide 0.3s ease-out;
        }
        @keyframes messageSlide {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .message-sent {
            align-self: flex-end;
            background: linear-gradient(135deg, rgba(236,72,153,0.4), rgba(219,39,119,0.4));
            border: 1px solid rgba(236,72,153,0.4);
            color: var(--text);
            border-bottom-right-radius: 4px;
        }
        .message-received {
            align-self: flex-start;
            background: rgba(167,139,250,0.15);
            border: 1px solid rgba(167,139,250,0.3);
            color: var(--text);
            border-bottom-left-radius: 4px;
        }
        .message-time {
            font-size: 0.75rem;
            color: var(--muted);
            margin-top: 6px;
            display: block;
        }
        .message-text {
            line-height: 1.5;
            word-wrap: break-word;
        }
        .chat-input-container {
            padding: 20px 28px;
            background: rgba(41,20,63,0.6);
            border-top: 2px solid rgba(167,139,250,0.15);
        }
        .chat-input-form {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .chat-input {
            flex: 1;
            padding: 16px 20px;
            border-radius: 24px;
            border: 2px solid rgba(167,139,250,0.3);
            background: rgba(255,255,255,0.05);
            color: var(--text);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .chat-input:focus {
            outline: none;
            border-color: rgba(236,72,153,0.5);
            background: rgba(255,255,255,0.08);
        }
        .send-button {
            padding: 16px 28px;
            border-radius: 24px;
            background: linear-gradient(135deg, #ec4899, #db2777);
            color: white;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        .send-button:hover {
            background: linear-gradient(135deg, #db2777, #be185d);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(236,72,153,0.4);
        }
        .empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 40px;
            text-align: center;
        }
        .empty-state-icon {
            font-size: 5rem;
            margin-bottom: 24px;
            opacity: 0.5;
        }
        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 12px;
        }
        .empty-state p {
            color: var(--muted);
            font-size: 1.05rem;
            max-width: 400px;
        }
        
        @media (max-width: 768px) {
            .messaging-container {
                grid-template-columns: 1fr;
            }
            .conversations-sidebar {
                display: none;
            }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container" style="padding: 20px;">
    <div style="max-width: 1400px; margin: 0 auto;">
        <div class="messaging-container">
            <!-- Conversations Sidebar -->
            <div class="conversations-sidebar">
                <div class="sidebar-header">
                    <div class="sidebar-title">Messages</div>
                    <div class="sidebar-subtitle"><?php echo count($matches); ?> conversation<?php echo count($matches) !== 1 ? 's' : ''; ?></div>
                </div>
                
                <div class="conversations-list">
                    <?php if (empty($matches)): ?>
                        <div style="padding: 40px 20px; text-align: center;">
                            <div style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;">💬</div>
                            <p style="color: var(--muted); font-size: 0.95rem;">No matches yet.<br>Start discovering!</p>
                            <a href="discover.php" class="btn" style="margin-top: 20px; padding: 10px 20px; font-size: 0.9rem;">
                                Find Matches
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($matches as $match): 
                            $isActive = ($selected_match == $match['match_id']);
                            $display_name = $match['name'] ?: $match['email'];
                            $first_letter = strtoupper(substr($display_name, 0, 1));
                            $has_photo = !empty($match['photo']);
                        ?>
                            <a href="messages.php?match=<?php echo $match['match_id']; ?>" 
                               class="conversation-item <?php echo $isActive ? 'active' : ''; ?>">
                                <?php if ($has_photo): ?>
                                    <img src="MBusers/photos/<?php echo htmlspecialchars($match['photo']); ?>" 
                                         alt="<?php echo htmlspecialchars($display_name); ?>"
                                         class="conversation-avatar"
                                         style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="conversation-avatar"><?php echo $first_letter; ?></div>
                                <?php endif; ?>
                                <div class="conversation-info">
                                    <div class="conversation-name">
                                        <?php echo htmlspecialchars($display_name); ?>
                                    </div>
                                    <?php if ($match['age']): ?>
                                        <div class="conversation-age"><?php echo $match['age']; ?> years old</div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chat Area -->
            <div class="chat-area">
                <?php if (!$selected_match): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">💭</div>
                        <h3>Select a Conversation</h3>
                        <p>Choose a match from the left sidebar to start chatting</p>
                    </div>
                <?php else: 
                    // Get current match info
                    $current_match = null;
                    foreach ($matches as $match) {
                        if ($match['match_id'] == $selected_match) {
                            $current_match = $match;
                            break;
                        }
                    }
                    
                    if ($current_match):
                        $display_name = $current_match['name'] ?: $current_match['email'];
                        $first_letter = strtoupper(substr($display_name, 0, 1));
                        $has_photo = !empty($current_match['photo']);
                    ?>
                        <div class="chat-header">
                            <?php if ($has_photo): ?>
                                <img src="MBusers/photos/<?php echo htmlspecialchars($current_match['photo']); ?>" 
                                     alt="<?php echo htmlspecialchars($display_name); ?>"
                                     class="chat-header-avatar"
                                     style="object-fit: cover;">
                            <?php else: ?>
                                <div class="chat-header-avatar"><?php echo $first_letter; ?></div>
                            <?php endif; ?>
                            <div class="chat-header-info">
                                <h3><?php echo htmlspecialchars($display_name); ?></h3>
                                <?php if ($current_match['age']): ?>
                                    <p><?php echo $current_match['age']; ?> years old</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="messages-container" id="messagesContainer">
                            <?php if (empty($messages)): ?>
                                <div style="text-align: center; padding: 60px 20px; color: var(--muted);">
                                    <div style="font-size: 3rem; margin-bottom: 16px;">👋</div>
                                    <p style="font-size: 1.05rem;">No messages yet. Start the conversation!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($messages as $msg): 
                                    $is_sent = ($msg['sender_id'] == $user_id);
                                ?>
                                    <div class="message-bubble <?php echo $is_sent ? 'message-sent' : 'message-received'; ?>">
                                        <div class="message-text">
                                            <?php echo nl2br(htmlspecialchars($msg['text'])); ?>
                                        </div>
                                        <span class="message-time">
                                            <?php echo date('M d, g:i A', strtotime($msg['time'])); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="chat-input-container">
                            <form method="POST" class="chat-input-form">
                                <input type="text" 
                                       name="message" 
                                       class="chat-input" 
                                       placeholder="Type your message..." 
                                       required 
                                       autocomplete="off">
                                <button type="submit" class="send-button">
                                    Send 💬
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<a href="index.php" class="back-btn" title="Back to Dashboard">←</a>

<?php include_once __DIR__ . '/includes/footer.php'; ?>

<script>
// Auto-scroll to bottom of messages
function scrollToBottom() {
    const messagesContainer = document.getElementById('messagesContainer');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
}

// Scroll to bottom when page loads
document.addEventListener('DOMContentLoaded', function() {
    scrollToBottom();
});

// Scroll to bottom when new message is sent
document.querySelector('.chat-input-form')?.addEventListener('submit', function() {
    setTimeout(scrollToBottom, 100);
});
</script>
</body>
</html>