<?php
session_start();
require 'config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$match_id = isset($_GET['match_id']) ? intval($_GET['match_id']) : 0;
$last_timestamp = $_GET['last_timestamp'] ?? '1970-01-01 00:00:00';

if (!$match_id) {
    http_response_code(400);
    echo json_encode(['error' => 'No match ID provided']);
    exit;
}

// Quick verification
$stmt = $conn->prepare("SELECT id FROM Matches WHERE id = ? AND (user1_id = ? OR user2_id = ?) LIMIT 1");
$stmt->bind_param("iii", $match_id, $user_id, $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}
$stmt->close();

// Get new messages efficiently
$stmt = $conn->prepare("
    SELECT m.id, m.message, m.sender_id, m.sent_at, p.name
    FROM Messages m
    LEFT JOIN Profiles p ON p.user_id = m.sender_id
    WHERE m.match_id = ? AND m.sent_at > ?
    ORDER BY m.sent_at ASC
    LIMIT 20
");
$stmt->bind_param("is", $match_id, $last_timestamp);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>