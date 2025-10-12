<?php
session_start();
require 'config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if user is admin - admins cannot use dating features
$stmt = $conn->prepare("SELECT role FROM Users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_role);
$stmt->fetch();
$stmt->close();

if ($user_role === 'admin') {
    header('Location: admin.php');
    exit;
}

// Get current question number from URL parameter, or determine smart default
$current_question_num = isset($_GET['q']) ? (int)$_GET['q'] : null;

// Check if this is a completion page request
$is_completion_request = isset($_GET['completed']);

// Get total questions count
$stmt = $conn->prepare("SELECT COUNT(*) FROM QuizQuestions");
$stmt->execute();
$stmt->bind_result($total_questions);
$stmt->fetch();
$stmt->close();

// If this is a completion request, skip the question logic
if ($is_completion_request) {
    $quiz_completed = true;
    $current_question = null;
} else {
    // Continue with normal question logic
    // If no specific question requested, find the next unanswered question or go to first
    if ($current_question_num === null) {
        // Get user's answered questions
        $stmt = $conn->prepare("SELECT question_id FROM QuizAnswers WHERE user_id = ? ORDER BY question_id ASC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $answered_questions = [];
        while ($row = $result->fetch_assoc()) {
            $answered_questions[] = $row['question_id'];
        }
        $stmt->close();
        
        // Get all question IDs
        $stmt = $conn->prepare("SELECT id FROM QuizQuestions ORDER BY id ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        $all_questions = [];
        while ($row = $result->fetch_assoc()) {
            $all_questions[] = $row['id'];
        }
        $stmt->close();
        
        // Find first unanswered question
        $current_question_num = 1; // default to first question
        foreach ($all_questions as $index => $question_id) {
            if (!in_array($question_id, $answered_questions)) {
                $current_question_num = $index + 1;
                break;
            }
        }
        
        // If all questions are answered, show completion page
        if (count($answered_questions) === count($all_questions) && count($all_questions) > 0) {
            header("Location: quiz.php?completed=1");
            exit;
        }
        
        // Redirect to the determined question number
        header("Location: quiz.php?q=" . $current_question_num);
        exit;
    }

    // Ensure question number is within valid range
    if ($current_question_num < 1) $current_question_num = 1;
    if ($current_question_num > $total_questions) $current_question_num = $total_questions;
}

// Get user's answered questions count
$stmt = $conn->prepare("SELECT COUNT(DISTINCT question_id) FROM QuizAnswers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($answered_count);
$stmt->fetch();
$stmt->close();

$completion_percentage = $total_questions > 0 ? round(($answered_count / $total_questions) * 100) : 0;

// Get current question with user's answer (only if not on completion page)
$current_question = null;
if (!$is_completion_request && $current_question_num) {
    $stmt = $conn->prepare("
        SELECT q.id, q.question_text, q.options, qa.answer 
        FROM QuizQuestions q
        LEFT JOIN QuizAnswers qa ON q.id = qa.question_id AND qa.user_id = ?
        ORDER BY q.id ASC
        LIMIT 1 OFFSET ?
    ");
    $offset = $current_question_num - 1;
    $stmt->bind_param("ii", $user_id, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_question = $result->fetch_assoc();
    $stmt->close();

    if ($current_question) {
        $current_question['options'] = json_decode($current_question['options'], true);
    }
}

// Get user's profile for personalization
$stmt = $conn->prepare("SELECT name FROM Profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer'])) {
    $answer = $_POST['answer'];
    $question_id = $_POST['question_id'];
    
    // Save the answer
    $stmt = $conn->prepare("
        INSERT INTO QuizAnswers (user_id, question_id, answer) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE answer = VALUES(answer), answered_at = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param("iis", $user_id, $question_id, $answer);
    $stmt->execute();
    $stmt->close();
    
    // Log the answer
    $stmt = $conn->prepare("INSERT INTO Logs (user_id, action, target_id, details) VALUES (?, 'quiz_answer_saved', ?, ?)");
    $log_details = "Answered question: " . substr($answer, 0, 50);
    $stmt->bind_param("iis", $user_id, $question_id, $log_details);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to next question or completion page
    if ($current_question_num < $total_questions) {
        header("Location: quiz.php?q=" . ($current_question_num + 1));
        exit;
    } else {
        header("Location: quiz.php?completed=1");
        exit;
    }
}

// Check if quiz is completed (if not already set above)
if (!isset($quiz_completed)) {
    $quiz_completed = false;
    
    // Check if explicitly marked as completed
    if (isset($_GET['completed'])) {
        $quiz_completed = true;
    } else {
        // Also check if user has actually answered all questions
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT question_id) FROM QuizAnswers WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($current_answered_count);
        $stmt->fetch();
        $stmt->close();
        
        if ($current_answered_count >= $total_questions && $total_questions > 0) {
            $quiz_completed = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Personality Quiz | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            overflow-x: hidden;
        }
        
        .quiz-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            min-height: calc(100vh - 120px);
            gap: 0;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .progress-sidebar {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px 0 0 20px;
            padding: 40px 30px;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 20px;
            height: fit-content;
            max-height: calc(100vh - 140px);
        }
        
        .progress-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .progress-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }
        
        .progress-subtitle {
            color: var(--muted);
            font-size: 0.9rem;
        }
        
        .progress-circle {
            width: 120px;
            height: 120px;
            margin: 30px auto;
            position: relative;
        }
        
        .progress-ring {
            width: 120px;
            height: 120px;
            transform: rotate(-90deg);
        }
        
        .progress-ring-bg {
            fill: none;
            stroke: rgba(255,255,255,0.1);
            stroke-width: 8;
        }
        
        .progress-ring-fill {
            fill: none;
            stroke: url(#progressGradient);
            stroke-width: 8;
            stroke-linecap: round;
            stroke-dasharray: 314;
            stroke-dashoffset: 314;
            transition: stroke-dashoffset 0.5s ease;
        }
        
        .progress-percentage {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
        }
        
        .progress-stats {
            background: rgba(0,0,0,0.2);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .progress-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .progress-stat:last-child {
            margin-bottom: 0;
        }
        
        .stat-label {
            color: var(--muted);
            font-size: 0.9rem;
        }
        
        .stat-value {
            color: var(--text);
            font-weight: 600;
        }
        
        .question-area {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 0 20px 20px 0;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 600px;
        }
        
        .question-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .question-number {
            background: linear-gradient(135deg, #ec4899, #db2777);
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        
        .question-counter {
            color: var(--muted);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .question-text {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 35px;
            line-height: 1.4;
        }
        
        .answer-options {
            display: grid;
            gap: 12px;
            margin-bottom: 40px;
        }
        
        .answer-option {
            background: rgba(255,255,255,0.05);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 18px 22px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text);
            font-size: 1rem;
            text-align: left;
            position: relative;
        }
        
        .answer-option:hover {
            background: rgba(236,72,153,0.1);
            border-color: rgba(236,72,153,0.3);
            transform: translateX(8px);
        }
        
        .answer-option.selected {
            background: rgba(236,72,153,0.2);
            border-color: rgba(236,72,153,0.6);
            color: white;
            transform: translateX(8px);
        }
        
        .answer-option input[type="radio"] {
            display: none;
        }
        
        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }
        
        .nav-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 12px;
            padding: 14px 28px;
            color: var(--text);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }
        
        .nav-btn:hover {
            background: rgba(255,255,255,0.15);
            transform: translateY(-1px);
        }
        
        .nav-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .next-btn {
            background: linear-gradient(135deg, #ec4899, #db2777);
            border: none;
            color: white;
        }
        
        .next-btn:hover:not(.disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(236,72,153,0.4);
        }
        
        .skip-btn {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.2);
            color: var(--muted);
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .skip-btn:hover {
            border-color: rgba(255,255,255,0.3);
            color: var(--text);
        }
        
        .completion-card {
            background: rgba(16,185,129,0.1);
            border: 1px solid rgba(16,185,129,0.3);
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            grid-column: 1 / -1;
            margin: 20px;
        }
        
        .completion-badge {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Mobile responsive */
        @media (max-width: 1024px) {
            .quiz-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .progress-sidebar {
                border-radius: 20px;
                position: static;
                max-height: none;
            }
            
            .question-area {
                border-radius: 20px;
                min-height: auto;
                padding: 40px 30px;
            }
            
            .progress-circle {
                width: 100px;
                height: 100px;
            }
            
            .progress-ring {
                width: 100px;
                height: 100px;
            }
            
            .question-text {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .question-area {
                padding: 30px 20px;
            }
            
            .question-text {
                font-size: 1.3rem;
            }
            
            .answer-option {
                padding: 20px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container" style="padding: 28px 20px;">
    <?php 
    // Debug output (remove in production)
    if (isset($_GET['debug'])) {
        echo "<div style='background: rgba(255,255,255,0.1); padding: 20px; margin-bottom: 20px; border-radius: 10px;'>";
        echo "<h4>Debug Info:</h4>";
        echo "quiz_completed: " . ($quiz_completed ? 'true' : 'false') . "<br>";
        echo "is_completion_request: " . ($is_completion_request ? 'true' : 'false') . "<br>";
        echo "current_question is null: " . ($current_question === null ? 'true' : 'false') . "<br>";
        echo "total_questions: " . $total_questions . "<br>";
        echo "answered_count: " . $answered_count . "<br>";
        echo "_GET['completed']: " . (isset($_GET['completed']) ? $_GET['completed'] : 'not set') . "<br>";
        echo "</div>";
    }
    ?>
    
    <?php if ($quiz_completed): ?>
        <div class="completion-card">
            <div class="completion-badge">
                ✨ Completed
            </div>
            <h3 style="color: #10b981; margin-bottom: 16px; font-size: 1.8rem; margin-top: 20px;">🎉 Quiz Complete!</h3>
            <p style="color: var(--text); margin-bottom: 12px; font-size: 1.1rem; font-weight: 600;">
                Congratulations, <?= htmlspecialchars($user_name ?: 'there') ?>!
            </p>
            <p style="color: var(--muted); margin-bottom: 30px; font-size: 1rem; line-height: 1.5;">
                Your personality profile has been saved and will help us show you more compatible matches. 
                You can now explore the platform to find your perfect match!
            </p>
            
            <div style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); border-radius: 12px; padding: 20px; margin: 20px 0; text-align: left;">
                <h4 style="color: #10b981; margin-bottom: 10px; font-size: 1rem;">✅ What's Next?</h4>
                <ul style="color: var(--muted); font-size: 0.9rem; margin: 0; padding-left: 20px;">
                    <li>Browse potential matches based on your personality</li>
                    <li>Check out who might be interested in you</li>
                    <li>Update your profile with photos and preferences</li>
                </ul>
            </div>
            
            <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; margin-top: 30px;">
                <a href="discover.php" class="btn" style="padding: 14px 28px; font-size: 1.1rem; text-decoration: none;">
                    🔍 Find Matches
                </a>
                <a href="browse.php" class="btn-ghost" style="padding: 14px 28px; font-size: 1.1rem; text-decoration: none;">
                    👥 Browse Users
                </a>
                <a href="index.php" class="btn-ghost" style="padding: 14px 28px; font-size: 1.1rem; text-decoration: none;">
                    🏠 Dashboard
                </a>
            </div>
            
        </div>
    <?php elseif ($current_question): ?>
        <div class="quiz-layout">
            <!-- Progress Sidebar -->
            <div class="progress-sidebar">
                <div class="progress-header">
                    <div class="progress-title">Personality Quiz</div>
                    <?php if ($user_name): ?>
                        <div class="progress-subtitle">Hey <?= htmlspecialchars($user_name) ?>!</div>
                    <?php endif; ?>
                </div>
                
                <!-- Circular Progress -->
                <div class="progress-circle">
                    <svg class="progress-ring" viewBox="0 0 120 120">
                        <defs>
                            <linearGradient id="progressGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" style="stop-color:#ec4899;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#db2777;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                        <circle class="progress-ring-bg" cx="60" cy="60" r="50"></circle>
                        <circle class="progress-ring-fill" cx="60" cy="60" r="50" 
                                style="stroke-dashoffset: <?= 314 - (314 * $completion_percentage / 100) ?>"></circle>
                    </svg>
                    <div class="progress-percentage"><?= $completion_percentage ?>%</div>
                </div>
                
                <!-- Progress Stats -->
                <div class="progress-stats">
                    <div class="progress-stat">
                        <span class="stat-label">Current Question</span>
                        <span class="stat-value"><?= $current_question_num ?>/<?= $total_questions ?></span>
                    </div>
                    <div class="progress-stat">
                        <span class="stat-label">Answered</span>
                        <span class="stat-value"><?= $answered_count ?></span>
                    </div>
                    <div class="progress-stat">
                        <span class="stat-label">Remaining</span>
                        <span class="stat-value"><?= max(0, $total_questions - $answered_count) ?></span>
                    </div>
                </div>
                
                <!-- Quick Tips -->
                <div style="background: rgba(167,139,250,0.1); border: 1px solid rgba(167,139,250,0.3); border-radius: 12px; padding: 16px; font-size: 0.9rem; color: var(--muted); text-align: center;">
                    💡 Use number keys (1-4) for quick selection, or press Enter to continue!
                </div>
            </div>
            
            <!-- Question Area -->
            <div class="question-area">
                <div class="question-header">
                    <div class="question-number"><?= $current_question_num ?></div>
                    <div class="question-counter">Question <?= $current_question_num ?> of <?= $total_questions ?></div>
                </div>
                
                <div class="question-text"><?= htmlspecialchars($current_question['question_text']) ?></div>
                
                <form method="POST" id="questionForm">
                    <input type="hidden" name="question_id" value="<?= $current_question['id'] ?>">
                    
                    <div class="answer-options">
                        <?php if (is_array($current_question['options'])): ?>
                            <?php foreach ($current_question['options'] as $option): ?>
                                <label class="answer-option <?= $current_question['answer'] === $option ? 'selected' : '' ?>">
                                    <input type="radio" 
                                           name="answer" 
                                           value="<?= htmlspecialchars($option) ?>"
                                           <?= $current_question['answer'] === $option ? 'checked' : '' ?>>
                                    <span><?= htmlspecialchars($option) ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="color: var(--muted); padding: 20px; text-align: center;">
                                Error loading question options. Please refresh the page.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="navigation-buttons">
                        <div>
                            <?php if ($current_question_num > 1): ?>
                                <a href="quiz.php?q=<?= $current_question_num - 1 ?>" class="nav-btn">
                                    ← Previous
                                </a>
                            <?php else: ?>
                                <span class="nav-btn disabled">← Previous</span>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <?php if ($current_question_num < $total_questions): ?>
                                <a href="quiz.php?q=<?= min($current_question_num + 1, $total_questions) ?>" class="skip-btn">
                                    Skip Question
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <button type="submit" class="nav-btn next-btn" id="nextBtn" disabled>
                                <?= $current_question_num == $total_questions ? 'Complete Quiz' : 'Next' ?> →
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="completion-card" style="background: rgba(239,68,68,0.1); border-color: rgba(239,68,68,0.3);">
            <h3 style="color: #ef4444; margin-bottom: 20px; font-size: 1.6rem;">⚠️ Quiz Error</h3>
            <p style="color: var(--text); margin-bottom: 20px; font-size: 1.1rem;">
                We're having trouble loading the quiz questions.
            </p>
            <div style="background: rgba(0,0,0,0.2); border-radius: 12px; padding: 20px; margin: 20px 0; text-align: left;">
                <h4 style="color: #ef4444; margin-bottom: 10px; font-size: 1rem;">Possible Issues:</h4>
                <ul style="color: var(--muted); font-size: 0.9rem; margin: 0; padding-left: 20px;">
                    <li>No quiz questions have been added to the database</li>
                    <li>Database connection issue</li>
                    <li>Invalid question ID or format</li>
                </ul>
            </div>
            <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; margin-top: 30px;">
                <a href="quiz.php?q=1" class="btn" style="padding: 14px 28px; font-size: 1.1rem; text-decoration: none;">
                    🔄 Try Again
                </a>
                <a href="index.php" class="btn-ghost" style="padding: 14px 28px; font-size: 1.1rem; text-decoration: none;">
                    🏠 Back to Dashboard
                </a>
            </div>
            <?php if ($total_questions == 0): ?>
                <p style="color: var(--muted); margin-top: 20px; font-size: 0.9rem;">
                    Admin Note: Please add quiz questions to the database using the admin panel.
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<a href="index.php" class="back-btn" title="Back to Dashboard">←</a>

<?php include_once __DIR__ . '/includes/footer.php'; ?>

<script>
// Handle answer selection
document.querySelectorAll('.answer-option').forEach(option => {
    option.addEventListener('click', function() {
        const form = this.closest('form');
        const radio = this.querySelector('input[type="radio"]');
        
        // Clear previous selections
        form.querySelectorAll('.answer-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        
        // Select this option
        this.classList.add('selected');
        radio.checked = true;
        
        // Enable next button
        const nextBtn = document.getElementById('nextBtn');
        if (nextBtn) {
            nextBtn.disabled = false;
        }
    });
});

// Handle form submission
document.getElementById('questionForm')?.addEventListener('submit', function(e) {
    const selectedAnswer = this.querySelector('input[name="answer"]:checked');
    if (!selectedAnswer) {
        e.preventDefault();
        alert('Please select an answer before proceeding.');
    }
});

// Check if an answer is already selected on page load
window.addEventListener('load', function() {
    const selectedAnswer = document.querySelector('input[name="answer"]:checked');
    const nextBtn = document.getElementById('nextBtn');
    if (nextBtn && selectedAnswer) {
        nextBtn.disabled = false;
    }
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    const options = document.querySelectorAll('.answer-option');
    const nextBtn = document.getElementById('nextBtn');
    
    // Number keys 1-4 to select answers
    if (e.key >= '1' && e.key <= '4') {
        const optionIndex = parseInt(e.key) - 1;
        if (options[optionIndex]) {
            options[optionIndex].click();
        }
    }
    
    // Enter or Space to proceed to next question
    if ((e.key === 'Enter' || e.key === ' ') && nextBtn && !nextBtn.disabled) {
        e.preventDefault();
        nextBtn.click();
    }
});
</script>

</body>
</html>