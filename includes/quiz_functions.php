<?php
// Quiz utility functions for Meet Beyond dating app

/**
 * Get user's quiz completion status
 */
function getUserQuizStatus($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT qa.question_id) as answered_count,
            (SELECT COUNT(*) FROM QuizQuestions) as total_questions,
            MAX(qa.answered_at) as last_answered
        FROM QuizAnswers qa 
        WHERE qa.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($answered_count, $total_questions, $last_answered);
    $stmt->fetch();
    $stmt->close();
    
    $completion_percentage = $total_questions > 0 ? round(($answered_count / $total_questions) * 100) : 0;
    
    return [
        'answered_count' => $answered_count,
        'total_questions' => $total_questions,
        'completion_percentage' => $completion_percentage,
        'last_answered' => $last_answered,
        'is_complete' => $completion_percentage >= 100
    ];
}

/**
 * Get user's quiz insights for profile display
 */
function getUserQuizInsights($conn, $user_id, $limit = 5) {
    $stmt = $conn->prepare("
        SELECT qq.question_text, qa.answer 
        FROM QuizAnswers qa
        JOIN QuizQuestions qq ON qa.question_id = qq.id
        WHERE qa.user_id = ?
        ORDER BY qa.answered_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $insights = [];
    while ($row = $result->fetch_assoc()) {
        $insights[] = $row;
    }
    $stmt->close();
    
    return $insights;
}

/**
 * Calculate compatibility score between two users based on quiz answers
 */
function calculateQuizCompatibility($conn, $user1_id, $user2_id) {
    $stmt = $conn->prepare("
        SELECT 
            q1.question_id,
            q1.answer as user1_answer,
            q2.answer as user2_answer
        FROM QuizAnswers q1
        JOIN QuizAnswers q2 ON q1.question_id = q2.question_id
        WHERE q1.user_id = ? AND q2.user_id = ?
    ");
    $stmt->bind_param("ii", $user1_id, $user2_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $total_questions = 0;
    $matching_answers = 0;
    $partial_matches = 0;
    
    while ($row = $result->fetch_assoc()) {
        $total_questions++;
        
        if ($row['user1_answer'] === $row['user2_answer']) {
            $matching_answers++;
        } else {
            // Check for partial compatibility (similar themes)
            if (areAnswersCompatible($row['user1_answer'], $row['user2_answer'])) {
                $partial_matches++;
            }
        }
    }
    $stmt->close();
    
    if ($total_questions == 0) {
        return ['score' => 0, 'message' => 'No quiz data available'];
    }
    
    // Calculate compatibility score (0-100)
    $exact_score = ($matching_answers / $total_questions) * 100;
    $partial_score = ($partial_matches / $total_questions) * 50;
    $total_score = min(100, $exact_score + $partial_score);
    
    return [
        'score' => round($total_score),
        'total_questions' => $total_questions,
        'exact_matches' => $matching_answers,
        'partial_matches' => $partial_matches,
        'message' => getCompatibilityMessage($total_score)
    ];
}

/**
 * Check if two answers are compatible (similar themes)
 */
function areAnswersCompatible($answer1, $answer2) {
    // Define compatible answer groups
    $compatibility_groups = [
        'social' => ['Going out to a party or club', 'Social activities', 'Life of the party', 'Enjoy small groups'],
        'homebody' => ['Netflix and chill at home', 'Happy staying in', 'Cozy nights in'],
        'active' => ['Outdoor adventure', 'Gym regular', 'Sports and team activities', 'Mountain hiking'],
        'intellectual' => ['Deep and meaningful', 'Reading', 'Museums', 'Learning'],
        'romantic' => ['Dinner at a nice restaurant', 'Quality time together', 'Romantic gestures'],
        'career_focused' => ['Career is top priority', 'Work hard, play hard', 'Ambition and goals'],
        'family_oriented' => ['Definitely want children', 'Family time', 'Married with children']
    ];
    
    foreach ($compatibility_groups as $group) {
        $answer1_in_group = false;
        $answer2_in_group = false;
        
        foreach ($group as $keyword) {
            if (stripos($answer1, $keyword) !== false) $answer1_in_group = true;
            if (stripos($answer2, $keyword) !== false) $answer2_in_group = true;
        }
        
        if ($answer1_in_group && $answer2_in_group) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get compatibility message based on score
 */
function getCompatibilityMessage($score) {
    if ($score >= 80) return "Excellent match! You have very similar values and preferences.";
    if ($score >= 60) return "Great compatibility! You share many common interests.";
    if ($score >= 40) return "Good potential! Some differences but complementary qualities.";
    if ($score >= 20) return "Moderate compatibility. You might learn from each other.";
    return "Different perspectives, but opposites can attract!";
}

/**
 * Get quiz-based personality summary for a user
 */
function getUserPersonalitySummary($conn, $user_id) {
    $insights = getUserQuizInsights($conn, $user_id, 10);
    
    if (empty($insights)) {
        return "Quiz not completed yet";
    }
    
    // Analyze answers to create personality traits
    $traits = [];
    
    foreach ($insights as $insight) {
        $answer = strtolower($insight['answer']);
        
        // Social traits
        if (stripos($answer, 'party') !== false || stripos($answer, 'social') !== false) {
            $traits['social'] = true;
        }
        if (stripos($answer, 'home') !== false || stripos($answer, 'chill') !== false) {
            $traits['homebody'] = true;
        }
        
        // Activity traits
        if (stripos($answer, 'gym') !== false || stripos($answer, 'fitness') !== false || stripos($answer, 'active') !== false) {
            $traits['active'] = true;
        }
        if (stripos($answer, 'adventure') !== false || stripos($answer, 'travel') !== false) {
            $traits['adventurous'] = true;
        }
        
        // Relationship traits
        if (stripos($answer, 'family') !== false || stripos($answer, 'children') !== false) {
            $traits['family_oriented'] = true;
        }
        if (stripos($answer, 'career') !== false || stripos($answer, 'ambitious') !== false) {
            $traits['career_focused'] = true;
        }
    }
    
    // Generate summary based on traits
    $personality_traits = [];
    if (isset($traits['social'])) $personality_traits[] = "Social";
    if (isset($traits['homebody'])) $personality_traits[] = "Homebody";
    if (isset($traits['active'])) $personality_traits[] = "Active";
    if (isset($traits['adventurous'])) $personality_traits[] = "Adventurous";
    if (isset($traits['family_oriented'])) $personality_traits[] = "Family-oriented";
    if (isset($traits['career_focused'])) $personality_traits[] = "Career-focused";
    
    if (empty($personality_traits)) {
        return "Unique personality - complete more quiz questions for insights";
    }
    
    return implode(", ", $personality_traits);
}

/**
 * Log quiz-related activity
 */
function logQuizActivity($conn, $user_id, $action, $target_id = null, $details = null) {
    $stmt = $conn->prepare("INSERT INTO Logs (user_id, action, target_id, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $target_id, $details);
    $stmt->execute();
    $stmt->close();
}

/**
 * Get quiz statistics for admin dashboard
 */
function getQuizStatistics($conn) {
    $stats = [];
    
    // Basic statistics
    $result = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM QuizQuestions) as total_questions,
            (SELECT COUNT(*) FROM QuizAnswers) as total_answers,
            (SELECT COUNT(DISTINCT user_id) FROM QuizAnswers) as users_participated,
            (SELECT COUNT(*) FROM QuizAnswers WHERE DATE(answered_at) = CURDATE()) as answers_today,
            (SELECT COUNT(*) FROM QuizAnswers WHERE answered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as answers_this_week
    ");
    $stats['basic'] = $result->fetch_assoc();
    
    // Completion rates
    $result = $conn->query("
        SELECT 
            user_id,
            COUNT(*) as answered_questions,
            (SELECT COUNT(*) FROM QuizQuestions) as total_questions,
            ROUND((COUNT(*) / (SELECT COUNT(*) FROM QuizQuestions)) * 100) as completion_percentage
        FROM QuizAnswers 
        GROUP BY user_id
        HAVING completion_percentage >= 100
    ");
    $stats['completed_users'] = $result->num_rows;
    
    // Most popular answers
    $result = $conn->query("
        SELECT 
            qq.question_text,
            qa.answer,
            COUNT(*) as answer_count
        FROM QuizAnswers qa
        JOIN QuizQuestions qq ON qa.question_id = qq.id
        GROUP BY qa.question_id, qa.answer
        ORDER BY answer_count DESC
        LIMIT 10
    ");
    $stats['popular_answers'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['popular_answers'][] = $row;
    }
    
    return $stats;
}

/**
 * Get users with similar quiz answers for improved matching
 */
function getUsersWithSimilarAnswers($conn, $user_id, $limit = 10) {
    $stmt = $conn->prepare("
        SELECT 
            other_users.user_id,
            COUNT(*) as matching_answers,
            p.name,
            u.email
        FROM QuizAnswers user_answers
        JOIN QuizAnswers other_users ON user_answers.question_id = other_users.question_id 
            AND user_answers.answer = other_users.answer
            AND other_users.user_id != ?
        LEFT JOIN Profiles p ON other_users.user_id = p.user_id
        LEFT JOIN Users u ON other_users.user_id = u.id
        WHERE user_answers.user_id = ?
        GROUP BY other_users.user_id
        ORDER BY matching_answers DESC
        LIMIT ?
    ");
    $stmt->bind_param("iii", $user_id, $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $similar_users = [];
    while ($row = $result->fetch_assoc()) {
        $similar_users[] = $row;
    }
    $stmt->close();
    
    return $similar_users;
}
?>