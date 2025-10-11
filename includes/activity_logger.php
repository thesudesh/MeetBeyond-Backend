<?php
// Activity logging functions for Meet Beyond
// This file handles comprehensive user activity tracking for admin analytics

/**
 * Enhanced activity logger with detailed context
 */
function logUserActivity($conn, $user_id, $action, $target_id = null, $details = null, $additional_context = []) {
    // Get user info for context
    $user_info = getUserBasicInfo($conn, $user_id);
    
    // Build detailed log entry
    $log_details = $details;
    if (!empty($additional_context)) {
        $context_data = json_encode($additional_context);
        $log_details .= " | Context: " . $context_data;
    }
    
    // Add timestamp and user context
    $log_details .= " | IP: " . getUserIP() . " | UserAgent: " . substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 200);
    
    $stmt = $conn->prepare("INSERT INTO Logs (user_id, action, target_id, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $target_id, $log_details);
    $stmt->execute();
    $stmt->close();
}

/**
 * Get user's IP address
 */
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
}

/**
 * Get basic user info for logging context
 */
function getUserBasicInfo($conn, $user_id) {
    $stmt = $conn->prepare("SELECT u.email, u.role, p.name FROM Users u LEFT JOIN Profiles p ON u.id = p.user_id WHERE u.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($email, $role, $name);
    $stmt->fetch();
    $stmt->close();
    
    return [
        'email' => $email,
        'role' => $role,
        'name' => $name ?: $email
    ];
}

/**
 * Specific logging functions for different activities
 */

// Authentication logs
function logLogin($conn, $user_id, $success = true) {
    $action = $success ? 'user_login_success' : 'user_login_failed';
    $details = $success ? 'User logged in successfully' : 'Failed login attempt';
    logUserActivity($conn, $user_id, $action, null, $details);
}

function logLogout($conn, $user_id) {
    logUserActivity($conn, $user_id, 'user_logout', null, 'User logged out');
}

// Profile activities
function logProfileView($conn, $viewer_id, $viewed_id) {
    $details = "Viewed profile of user ID: $viewed_id";
    logUserActivity($conn, $viewer_id, 'profile_viewed', $viewed_id, $details);
}

function logProfileEdit($conn, $user_id, $fields_changed) {
    $details = "Profile updated - fields: " . implode(', ', $fields_changed);
    logUserActivity($conn, $user_id, 'profile_updated', null, $details, ['fields' => $fields_changed]);
}

// Dating activities
function logSwipeAction($conn, $user_id, $target_id, $action) {
    $details = "Swiped $action on user ID: $target_id";
    logUserActivity($conn, $user_id, "swipe_$action", $target_id, $details);
}

function logMatch($conn, $user1_id, $user2_id, $match_id) {
    logUserActivity($conn, $user1_id, 'match_created', $user2_id, "New match created with user ID: $user2_id", ['match_id' => $match_id]);
    logUserActivity($conn, $user2_id, 'match_created', $user1_id, "New match created with user ID: $user1_id", ['match_id' => $match_id]);
}

function logMessage($conn, $sender_id, $match_id, $message_length) {
    $details = "Sent message ($message_length characters) in match ID: $match_id";
    logUserActivity($conn, $sender_id, 'message_sent', $match_id, $details, ['message_length' => $message_length]);
}

// Quiz activities (enhanced)
function logQuizStart($conn, $user_id) {
    logUserActivity($conn, $user_id, 'quiz_started', null, 'Started taking personality quiz');
}

function logQuizAnswer($conn, $user_id, $question_id, $answer) {
    $details = "Answered quiz question ID: $question_id";
    logUserActivity($conn, $user_id, 'quiz_answer_submitted', $question_id, $details, ['answer' => substr($answer, 0, 100)]);
}

function logQuizCompletion($conn, $user_id, $total_questions) {
    $details = "Completed personality quiz - $total_questions questions answered";
    logUserActivity($conn, $user_id, 'quiz_completed', null, $details, ['questions_count' => $total_questions]);
}

// Premium activities
function logSubscriptionPurchase($conn, $user_id, $plan_type, $amount) {
    $details = "Purchased $plan_type subscription for $amount";
    logUserActivity($conn, $user_id, 'subscription_purchased', null, $details, ['plan' => $plan_type, 'amount' => $amount]);
}

function logPremiumFeatureUse($conn, $user_id, $feature) {
    $details = "Used premium feature: $feature";
    logUserActivity($conn, $user_id, 'premium_feature_used', null, $details, ['feature' => $feature]);
}

// Safety activities
function logUserReport($conn, $reporter_id, $target_id, $reason) {
    $details = "Reported user ID: $target_id for: $reason";
    logUserActivity($conn, $reporter_id, 'user_reported', $target_id, $details, ['reason' => $reason]);
}

function logUserBlock($conn, $blocker_id, $blocked_id) {
    $details = "Blocked user ID: $blocked_id";
    logUserActivity($conn, $blocker_id, 'user_blocked', $blocked_id, $details);
}

// Admin activities
function logAdminAction($conn, $admin_id, $action, $target_table, $target_id = null, $details = null) {
    $log_details = "Admin action: $action on table: $target_table";
    if ($details) $log_details .= " - $details";
    logUserActivity($conn, $admin_id, "admin_$action", $target_id, $log_details, ['table' => $target_table]);
}

// Page visit tracking
function logPageVisit($conn, $user_id, $page, $referrer = null) {
    $details = "Visited page: $page";
    $context = ['page' => $page];
    if ($referrer) $context['referrer'] = $referrer;
    logUserActivity($conn, $user_id, 'page_visited', null, $details, $context);
}

/**
 * Analytics functions for admin dashboard
 */

// Get user activity summary
function getUserActivitySummary($conn, $user_id, $days = 30) {
    $stmt = $conn->prepare("
        SELECT 
            action,
            COUNT(*) as count,
            MAX(created_at) as last_occurrence
        FROM Logs 
        WHERE user_id = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY action
        ORDER BY count DESC
    ");
    $stmt->bind_param("ii", $user_id, $days);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    $stmt->close();
    
    return $activities;
}

// Get platform activity overview
function getPlatformActivityOverview($conn, $days = 7) {
    $overview = [];
    
    // Daily active users
    $stmt = $conn->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(DISTINCT user_id) as active_users
        FROM Logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $result = $stmt->get_result();
    $overview['daily_active_users'] = [];
    while ($row = $result->fetch_assoc()) {
        $overview['daily_active_users'][] = $row;
    }
    $stmt->close();
    
    // Most common actions
    $stmt = $conn->prepare("
        SELECT 
            action,
            COUNT(*) as count
        FROM Logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY action
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $result = $stmt->get_result();
    $overview['top_actions'] = [];
    while ($row = $result->fetch_assoc()) {
        $overview['top_actions'][] = $row;
    }
    $stmt->close();
    
    // Activity by hour
    $stmt = $conn->prepare("
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as activity_count
        FROM Logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ");
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $result = $stmt->get_result();
    $overview['hourly_activity'] = [];
    while ($row = $result->fetch_assoc()) {
        $overview['hourly_activity'][] = $row;
    }
    $stmt->close();
    
    return $overview;
}

// Get most active users
function getMostActiveUsers($conn, $days = 30, $limit = 10) {
    $stmt = $conn->prepare("
        SELECT 
            l.user_id,
            COUNT(*) as activity_count,
            p.name,
            u.email,
            MAX(l.created_at) as last_activity
        FROM Logs l
        LEFT JOIN Users u ON l.user_id = u.id
        LEFT JOIN Profiles p ON l.user_id = p.user_id
        WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY l.user_id
        ORDER BY activity_count DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $days, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $active_users = [];
    while ($row = $result->fetch_assoc()) {
        $active_users[] = $row;
    }
    $stmt->close();
    
    return $active_users;
}

/**
 * Auto-logging middleware functions
 */

// Automatically log page visits (call this in each page)
function autoLogPageVisit($conn) {
    if (isset($_SESSION['user_id'])) {
        $page = basename($_SERVER['PHP_SELF']);
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;
        logPageVisit($conn, $_SESSION['user_id'], $page, $referrer);
    }
}

// Log form submissions automatically
function autoLogFormSubmission($conn, $form_name, $fields = []) {
    if (isset($_SESSION['user_id'])) {
        $details = "Submitted form: $form_name";
        logUserActivity($conn, $_SESSION['user_id'], 'form_submitted', null, $details, ['form' => $form_name, 'fields' => $fields]);
    }
}
?>