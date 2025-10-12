<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$user_id = $_SESSION['user_id'];
$match_id = isset($_POST['match_id']) ? intval($_POST['match_id']) : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (!$match_id || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing match ID or message']);
    exit;
}

// Quick access check
$stmt = $conn->prepare("SELECT id FROM Matches WHERE id = ? AND (user1_id = ? OR user2_id = ?) LIMIT 1");
$stmt->bind_param("iii", $match_id, $user_id, $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}
$stmt->close();

// Insert message
$stmt = $conn->prepare("INSERT INTO Messages (match_id, sender_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $match_id, $user_id, $message);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'timestamp' => date('Y-m-d H:i:s')]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
}
$stmt->close();
?>