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
            <div class="text-center" style="padding:60px 20px;color:var(--muted)">
                <div style="font-size:3rem;margin-bottom:16px">🎉</div>
                <h3 style="font-size:1.3rem;margin-bottom:8px;color:var(--text)">No events available</h3>
                <p>Check back soon for exciting events!</p>
            </div>
        <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px;margin-top:24px">
                <?php foreach ($events as $event): ?>
                <article style="background:var(--panel-bg);border-radius:14px;padding:28px;border:1px solid rgba(255,255,255,0.08);transition:var(--transition);display:flex;flex-direction:column">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
                        <div style="font-size:2.5rem;"><?php echo $event['type'] === 'virtual' ? '💻' : '📍'; ?></div>
                        <div style="flex:1">
                            <span class="badge <?php echo $event['type'] === 'virtual' ? 'badge-primary' : 'badge-pink'; ?>" style="font-size:0.8rem">
                                <?php echo ucfirst($event['type']); ?>
                            </span>
                        </div>
                        <?php if ($event['my_status']): ?>
                            <span class="badge badge-success" style="font-size:0.8rem">✓ Registered</span>
                        <?php endif; ?>
                    </div>
                    
                    <h3 style="font-weight:700;font-size:1.25rem;margin-bottom:12px;color:var(--text)">
                        <?php echo htmlspecialchars($event['title']); ?>
                    </h3>
                    
                    <p style="font-size:0.95rem;color:rgba(239,233,255,0.75);margin-bottom:16px;line-height:1.6;flex:1">
                        <?php echo htmlspecialchars($event['desc']); ?>
                    </p>
                    
                    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;padding:14px;background:rgba(0,0,0,0.2);border-radius:10px">
                        <div style="display:flex;align-items:center;gap:8px;color:var(--accent);font-size:0.9rem">
                            <span style="font-size:1.2rem">📅</span>
                            <span><?php echo date('M d, Y \a\t g:i A', strtotime($event['dt'])); ?></span>
                        </div>
                        <?php if ($event['loc']): ?>
                        <div style="display:flex;align-items:center;gap:8px;color:var(--accent);font-size:0.9rem">
                            <span style="font-size:1.2rem">📍</span>
                            <span><?php echo htmlspecialchars($event['loc']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div style="display:flex;align-items:center;gap:8px;color:var(--accent);font-size:0.9rem">
                            <span style="font-size:1.2rem">👥</span>
                            <span><?php echo (int)$event['count']; ?> participant<?php echo $event['count'] == 1 ? '' : 's'; ?> attending</span>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                        <?php if ($event['my_status']): ?>
                            <button type="submit" name="action" value="cancel" class="btn-ghost" style="width:100%;justify-content:center">Cancel Registration</button>
                        <?php else: ?>
                            <button type="submit" name="action" value="join" class="btn" style="width:100%;justify-content:center">Join Event</button>
                        <?php endif; ?>
                    </form>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>