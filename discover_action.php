<?php
session_start();
require 'config.php';

// Only handle AJAX requests
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$target_id = intval($_POST['target_id'] ?? 0);

$response = ['success' => false, 'message' => '', 'match' => false];

if ($action === 'like' && $target_id > 0) {
    // Insert like
    $stmt = $conn->prepare("INSERT INTO Likes (liker_id, liked_id, status) VALUES (?, ?, 'liked') ON DUPLICATE KEY UPDATE status='liked', created_at=NOW()");
    $stmt->bind_param("ii", $user_id, $target_id);
    $stmt->execute();
    $stmt->close();
    
    // Check if they liked back (mutual like = match)
    $stmt = $conn->prepare("SELECT id FROM Likes WHERE liker_id=? AND liked_id=? AND status='liked'");
    $stmt->bind_param("ii", $target_id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    $is_mutual = $stmt->num_rows > 0;
    $stmt->close();
    
    if ($is_mutual) {
        // Create match
        $user_low = min($user_id, $target_id);
        $user_high = max($user_id, $target_id);
        $stmt = $conn->prepare("INSERT IGNORE INTO Matches (user1_id, user2_id, user_low_id, user_high_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiii", $user_id, $target_id, $user_low, $user_high);
        $stmt->execute();
        $stmt->close();
        $response['match'] = true;
        $response['message'] = "🎉 It's a Match!";
    }
    
    // Clear the current discover profile from session after action
    unset($_SESSION['current_discover_profile']);
    $response['success'] = true;
    
} elseif ($action === 'pass' && $target_id > 0) {
    // Insert pass (so we don't show them again)
    $stmt = $conn->prepare("INSERT INTO Likes (liker_id, liked_id, status) VALUES (?, ?, 'passed') ON DUPLICATE KEY UPDATE status='passed', created_at=NOW()");
    $stmt->bind_param("ii", $user_id, $target_id);
    $stmt->execute();
    $stmt->close();
    
    // Clear the current discover profile from session after action
    unset($_SESSION['current_discover_profile']);
    $response['success'] = true;
    
} elseif ($action === 'block' && $target_id > 0) {
    // Block user
    $stmt = $conn->prepare("INSERT IGNORE INTO Blocks (blocker_id, blocked_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $target_id);
    $stmt->execute();
    $stmt->close();
    
    // Also add to passed
    $stmt = $conn->prepare("INSERT INTO Likes (liker_id, liked_id, status) VALUES (?, ?, 'passed') ON DUPLICATE KEY UPDATE status='passed'");
    $stmt->bind_param("ii", $user_id, $target_id);
    $stmt->execute();
    $stmt->close();
    
    // Clear the current discover profile from session after action
    unset($_SESSION['current_discover_profile']);
    $response['success'] = true;
}

header('Content-Type: application/json');
echo json_encode($response);
?>