<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if user is admin
$stmt = $conn->prepare("SELECT role FROM Users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_role);
$stmt->fetch();
$stmt->close();

if ($user_role === 'admin') {
    echo json_encode(['success' => false, 'error' => 'Admins cannot use quiz features']);
    exit;
}

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Auto-save quiz answers
    if (isset($_POST['auto_save'])) {
        $answers_saved = 0;
        
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'question_') === 0) {
                $question_id = str_replace('question_', '', $key);
                
                // Validate question exists
                $stmt = $conn->prepare("SELECT id FROM QuizQuestions WHERE id = ?");
                $stmt->bind_param("i", $question_id);
                $stmt->execute();
                $stmt->bind_result($valid_question);
                $stmt->fetch();
                $stmt->close();
                
                if ($valid_question) {
                    // Update or insert answer
                    $stmt = $conn->prepare("
                        INSERT INTO QuizAnswers (user_id, question_id, answer) 
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE answer = VALUES(answer), answered_at = CURRENT_TIMESTAMP
                    ");
                    $stmt->bind_param("iis", $user_id, $question_id, $value);
                    
                    if ($stmt->execute()) {
                        $answers_saved++;
                        
                        // Log the answer with more details
                        $stmt2 = $conn->prepare("INSERT INTO Logs (user_id, action, target_id, details) VALUES (?, 'quiz_answer_updated', ?, ?)");
                        $log_details = "Question ID: $question_id, Answer: " . substr($value, 0, 100);
                        $stmt2->bind_param("iis", $user_id, $question_id, $log_details);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                    $stmt->close();
                }
            }
        }
        
        // Log quiz progress update
        $stmt = $conn->prepare("INSERT INTO Logs (user_id, action, details) VALUES (?, 'quiz_progress_saved', ?)");
        $log_details = "Saved $answers_saved quiz answers via auto-save";
        $stmt->bind_param("is", $user_id, $log_details);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode([
            'success' => true, 
            'answers_saved' => $answers_saved,
            'message' => 'Progress saved automatically'
        ]);
        exit;
    }
    
    // Clear all answers
    if (isset($_POST['clear_all'])) {
        // Get count of answers before clearing
        $stmt = $conn->prepare("SELECT COUNT(*) FROM QuizAnswers WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($answer_count);
        $stmt->fetch();
        $stmt->close();
        
        // Clear all answers
        $stmt = $conn->prepare("DELETE FROM QuizAnswers WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            // Log the action
            $stmt = $conn->prepare("INSERT INTO Logs (user_id, action, details) VALUES (?, 'quiz_answers_cleared', ?)");
            $log_details = "Cleared $answer_count quiz answers - full reset";
            $stmt->bind_param("is", $user_id, $log_details);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode([
                'success' => true, 
                'cleared_count' => $answer_count,
                'message' => 'All quiz answers cleared'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to clear answers']);
        }
        exit;
    }
    
    // Get quiz progress
    if (isset($_POST['get_progress'])) {
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
        
        echo json_encode([
            'success' => true,
            'answered_count' => $answered_count,
            'total_questions' => $total_questions,
            'completion_percentage' => $completion_percentage,
            'last_answered' => $last_answered
        ]);
        exit;
    }
    
    // Submit complete quiz
    if (isset($_POST['submit_quiz'])) {
        // Check if quiz is complete
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT qa.question_id) as answered_count,
                (SELECT COUNT(*) FROM QuizQuestions) as total_questions
            FROM QuizAnswers qa 
            WHERE qa.user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($answered_count, $total_questions);
        $stmt->fetch();
        $stmt->close();
        
        if ($answered_count >= $total_questions) {
            // Log quiz completion
            $stmt = $conn->prepare("INSERT INTO Logs (user_id, action, details) VALUES (?, 'quiz_completed', ?)");
            $log_details = "Completed full personality quiz - $total_questions questions answered";
            $stmt->bind_param("is", $user_id, $log_details);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'completed' => true,
                'message' => 'Quiz completed successfully! Your personality profile is ready.'
            ]);
        } else {
            $remaining = $total_questions - $answered_count;
            echo json_encode([
                'success' => false,
                'completed' => false,
                'remaining_questions' => $remaining,
                'message' => "Please answer $remaining more questions to complete the quiz."
            ]);
        }
        exit;
    }
}

// Handle GET requests for quiz data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // Get user's quiz insights/summary
    if (isset($_GET['get_insights'])) {
        $stmt = $conn->prepare("
            SELECT qq.question_text, qa.answer, qa.answered_at 
            FROM QuizAnswers qa
            JOIN QuizQuestions qq ON qa.question_id = qq.id
            WHERE qa.user_id = ?
            ORDER BY qa.answered_at DESC
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $insights = [];
        while ($row = $result->fetch_assoc()) {
            $insights[] = $row;
        }
        $stmt->close();
        
        // Log insights access
        $stmt = $conn->prepare("INSERT INTO Logs (user_id, action, details) VALUES (?, 'quiz_insights_viewed', ?)");
        $log_details = "Viewed quiz insights - " . count($insights) . " answers";
        $stmt->bind_param("is", $user_id, $log_details);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'insights' => $insights,
            'total_answers' => count($insights)
        ]);
        exit;
    }
}

// Default response for invalid requests
echo json_encode(['success' => false, 'error' => 'Invalid request']);
?>