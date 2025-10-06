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
    <meta charset="utf-8">
    <title>Events | Meet Beyond</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container">
    <div class="card">
        <h2 class="page-title">Events</h2>
        <p class="lead">Join virtual or in-person events to connect with others!</p>

        <?php if (empty($events)): ?>
            <div class="text-center" style="padding:36px 0;color:var(--muted);">No events available at the moment.</div>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($events as $event): ?>
                <div class="card">
                    <div style="font-size:1.9rem;margin-bottom:6px;"><?php echo $event['type'] === 'virtual' ? '💻' : '📍'; ?></div>
                    <div style="font-weight:800;margin-bottom:6px;"><?php echo htmlspecialchars($event['title']); ?></div>
                    <div style="font-size:0.98rem;color:var(--muted);margin-bottom:10px;"><?php echo htmlspecialchars($event['desc']); ?></div>
                    <div style="color:var(--accent);font-size:0.97rem;margin-bottom:6px;">
                        <?php echo date('M d, Y H:i', strtotime($event['dt'])); ?><?php if ($event['loc']) echo ' | ' . htmlspecialchars($event['loc']); ?>
                    </div>
                    <div style="color:var(--muted);font-size:0.95rem;margin-bottom:8px;">
                        <?php echo ucfirst($event['type']); ?> · <?php echo (int)$event['count']; ?> participant<?php echo $event['count'] == 1 ? '' : 's'; ?>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                        <?php if ($event['my_status']): ?>
                            <button type="submit" name="action" value="cancel" class="btn btn-ghost">Cancel Registration</button>
                        <?php else: ?>
                            <button type="submit" name="action" value="join" class="btn">Join Event</button>
                        <?php endif; ?>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>