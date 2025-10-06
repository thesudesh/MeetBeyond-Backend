<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get all events, marking those the user has joined
$events = [];
$sql = "
    SELECT e.id, e.title, e.description, e.event_datetime, e.location, e.event_type,
        (SELECT COUNT(*) FROM EventParticipants ep WHERE ep.event_id = e.id) AS participant_count,
        (SELECT status FROM EventParticipants ep WHERE ep.event_id = e.id AND ep.user_id = ?) AS my_status
    FROM Events e
    ORDER BY e.event_datetime ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($id, $title, $desc, $dt, $loc, $type, $count, $my_status);
while ($stmt->fetch()) {
    $events[] = [
        'id' => $id,
        'title' => $title,
        'desc' => $desc,
        'dt' => $dt,
        'loc' => $loc,
        'type' => $type,
        'count' => $count,
        'my_status' => $my_status
    ];
}
$stmt->close();

// Handle join/cancel
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['event_id'])) {
    $event_id = intval($_POST['event_id']);
    if ($_POST['action'] === 'join') {
        // Register if not already registered
        $stmt = $conn->prepare("INSERT IGNORE INTO EventParticipants (event_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $event_id, $user_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['action'] === 'cancel') {
        // Remove registration
        $stmt = $conn->prepare("DELETE FROM EventParticipants WHERE event_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $event_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: events.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Events | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<div class="dashboard-hero">
    <div class="dashboard-box" style="max-width:900px;">
        <h2 class="dashboard-title" style="margin-bottom: 10px;">Events</h2>
        <p class="dashboard-subtitle" style="margin-bottom: 34px;">Join virtual or in-person events to connect with others!</p>
        <div class="dashboard-cards" style="flex-wrap:wrap;">
            <?php if (empty($events)): ?>
                <div style="width:100%;padding:40px 0;color:#7b7ce9;font-size:1.1rem;">
                    No events available at the moment.
                </div>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                <div class="dashboard-card" style="min-width:250px;max-width:350px;">
                    <span class="icon" style="font-size:2.2rem;">
                        <?php echo $event['type'] === 'virtual' ? '💻' : '📍'; ?>
                    </span>
                    <div style="font-weight:700;margin-bottom:6px;"><?php echo htmlspecialchars($event['title']); ?></div>
                    <div style="font-size:0.98em;color:#6867a5;margin-bottom:10px;"><?php echo htmlspecialchars($event['desc']); ?></div>
                    <div style="color:#5b5bd6;font-size:0.97em;margin-bottom:6px;">
                        <?php echo date('M d, Y H:i', strtotime($event['dt'])); ?>
                        <?php if ($event['loc']) echo " | " . htmlspecialchars($event['loc']); ?>
                    </div>
                    <div style="color:#aaa;font-size:0.96em;margin-bottom:9px;">
                        <?php echo ucfirst($event['type']); ?> Event | <?php echo (int)$event['count']; ?> participant<?php echo $event['count'] == 1 ? '' : 's'; ?>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                        <?php if ($event['my_status']): ?>
                            <button type="submit" name="action" value="cancel" class="btn btn-secondary" style="width:100%;">Cancel Registration</button>
                        <?php else: ?>
                            <button type="submit" name="action" value="join" class="btn" style="width:100%;">Join Event</button>
                        <?php endif; ?>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <a href="index.php" style="display:block;margin-top:40px;color:#aaa;text-decoration:underline;font-size:0.95em;">&#8592; Back to Dashboard</a>
    </div>
</div>
</body>
</html>