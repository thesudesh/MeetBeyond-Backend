<?php
session_start();
require 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT role FROM Users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_role);
$stmt->fetch();
$stmt->close();

if ($user_role !== 'admin') {
    header('Location: index.php');
    exit;
}

// Handle adding new questions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $question_text = $_POST['question_text'];
    $options = $_POST['options'];
    
    // Convert options array to JSON
    $options_json = json_encode(array_filter($options)); // Remove empty options
    
    $stmt = $conn->prepare("INSERT INTO QuizQuestions (question_text, options) VALUES (?, ?)");
    $stmt->bind_param("ss", $question_text, $options_json);
    
    if ($stmt->execute()) {
        $success = "Question added successfully!";
        
        // Log admin action
        $stmt2 = $conn->prepare("INSERT INTO Logs (user_id, action, details) VALUES (?, 'admin_quiz_question_added', ?)");
        $log_details = "Added new quiz question: " . substr($question_text, 0, 100);
        $stmt2->bind_param("is", $user_id, $log_details);
        $stmt2->execute();
        $stmt2->close();
    } else {
        $error = "Failed to add question.";
    }
    $stmt->close();
}

// Handle deleting questions
if (isset($_GET['delete_question'])) {
    $question_id = $_GET['delete_question'];
    
    // First get question text for logging
    $stmt = $conn->prepare("SELECT question_text FROM QuizQuestions WHERE id = ?");
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $stmt->bind_result($question_text);
    $stmt->fetch();
    $stmt->close();
    
    // Delete the question (cascade will handle answers)
    $stmt = $conn->prepare("DELETE FROM QuizQuestions WHERE id = ?");
    $stmt->bind_param("i", $question_id);
    
    if ($stmt->execute()) {
        $success = "Question deleted successfully!";
        
        // Log admin action
        $stmt2 = $conn->prepare("INSERT INTO Logs (user_id, action, target_id, details) VALUES (?, 'admin_quiz_question_deleted', ?, ?)");
        $log_details = "Deleted quiz question: " . substr($question_text, 0, 100);
        $stmt2->bind_param("iis", $user_id, $question_id, $log_details);
        $stmt2->execute();
        $stmt2->close();
    } else {
        $error = "Failed to delete question.";
    }
    $stmt->close();
}

// Get all quiz questions with answer statistics
$stmt = $conn->prepare("
    SELECT 
        qq.id, 
        qq.question_text, 
        qq.options, 
        qq.created_at,
        COUNT(qa.id) as total_answers,
        COUNT(DISTINCT qa.user_id) as unique_users
    FROM QuizQuestions qq
    LEFT JOIN QuizAnswers qa ON qq.id = qa.question_id
    GROUP BY qq.id
    ORDER BY qq.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$questions = [];
while ($row = $result->fetch_assoc()) {
    $row['options'] = json_decode($row['options'], true);
    $questions[] = $row;
}
$stmt->close();

// Get quiz statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM QuizQuestions) as total_questions,
        (SELECT COUNT(*) FROM QuizAnswers) as total_answers,
        (SELECT COUNT(DISTINCT user_id) FROM QuizAnswers) as users_participated,
        (SELECT COUNT(*) FROM QuizAnswers WHERE DATE(answered_at) = CURDATE()) as answers_today
";
$result = $conn->query($stats_query);
$stats = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quiz Manager | Meet Beyond Admin</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .admin-header {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 30px;
            margin-bottom: 30px;
            border-radius: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ec4899, #db2777);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }
        
        .quiz-manager {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }
        
        .questions-list {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 24px;
        }
        
        .question-item {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s ease;
        }
        
        .question-item:hover {
            border-color: rgba(236,72,153,0.3);
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .question-text {
            font-weight: 600;
            color: var(--text);
            flex: 1;
            margin-right: 16px;
        }
        
        .question-stats {
            font-size: 0.875rem;
            color: var(--muted);
            margin-bottom: 12px;
        }
        
        .question-options {
            display: grid;
            gap: 6px;
        }
        
        .option-item {
            background: rgba(167,139,250,0.1);
            border: 1px solid rgba(167,139,250,0.2);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.875rem;
            color: var(--muted);
        }
        
        .add-question-form {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 24px;
            position: sticky;
            top: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text);
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            padding: 12px;
            color: var(--text);
            font-size: 0.9rem;
        }
        
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .options-list {
            display: grid;
            gap: 8px;
        }
        
        .option-input {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .option-input input {
            flex: 1;
        }
        
        .add-option-btn, .remove-option-btn {
            background: rgba(167,139,250,0.2);
            border: 1px solid rgba(167,139,250,0.3);
            border-radius: 6px;
            padding: 8px 12px;
            color: var(--text);
            cursor: pointer;
            font-size: 0.875rem;
        }
        
        .remove-option-btn {
            background: rgba(239,68,68,0.2);
            border-color: rgba(239,68,68,0.3);
        }
        
        .delete-btn {
            background: rgba(239,68,68,0.2);
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 6px;
            padding: 6px 12px;
            color: #ef4444;
            cursor: pointer;
            font-size: 0.875rem;
            text-decoration: none;
        }
        
        .delete-btn:hover {
            background: rgba(239,68,68,0.3);
        }
        
        @media (max-width: 768px) {
            .quiz-manager {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container" style="padding: 28px 20px;">
    <div class="admin-header">
        <div>
            <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 8px; color: var(--text);">
                🧩 Quiz Manager
            </h1>
            <p style="color: var(--muted);">Manage personality quiz questions and view user responses</p>
        </div>
        <div style="display: flex; gap: 12px;">
            <a href="admin.php" class="btn-ghost" style="padding: 10px 20px;">← Back to Admin</a>
            <a href="admin_manage.php?table=QuizAnswers" class="btn" style="padding: 10px 20px;">📊 View All Answers</a>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); border-radius: 8px; padding: 16px; margin-bottom: 20px; color: #10b981;">
            ✅ <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); border-radius: 8px; padding: 16px; margin-bottom: 20px; color: #ef4444;">
            ❌ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= $stats['total_questions'] ?></div>
            <div style="color: var(--muted); font-weight: 500;">Total Questions</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['total_answers'] ?></div>
            <div style="color: var(--muted); font-weight: 500;">Total Answers</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['users_participated'] ?></div>
            <div style="color: var(--muted); font-weight: 500;">Users Participated</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['answers_today'] ?></div>
            <div style="color: var(--muted); font-weight: 500;">Answers Today</div>
        </div>
    </div>

    <div class="quiz-manager">
        <div class="questions-list">
            <h3 style="color: var(--text); margin-bottom: 20px; font-size: 1.3rem;">📝 Quiz Questions</h3>
            
            <?php foreach ($questions as $question): ?>
                <div class="question-item">
                    <div class="question-header">
                        <div class="question-text"><?= htmlspecialchars($question['question_text']) ?></div>
                        <a href="?delete_question=<?= $question['id'] ?>" 
                           class="delete-btn"
                           onclick="return confirm('Are you sure you want to delete this question?')">
                            🗑️ Delete
                        </a>
                    </div>
                    
                    <div class="question-stats">
                        👥 <?= $question['unique_users'] ?> users • 
                        📝 <?= $question['total_answers'] ?> total answers • 
                        📅 Created <?= date('M j, Y', strtotime($question['created_at'])) ?>
                    </div>
                    
                    <div class="question-options">
                        <?php foreach ($question['options'] as $option): ?>
                            <div class="option-item"><?= htmlspecialchars($option) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="add-question-form">
            <h3 style="color: var(--text); margin-bottom: 20px; font-size: 1.3rem;">➕ Add New Question</h3>
            
            <form method="POST">
                <div class="form-group">
                    <label for="question_text">Question Text</label>
                    <textarea name="question_text" id="question_text" 
                              placeholder="Enter your quiz question..." required></textarea>
                </div>

                <div class="form-group">
                    <label>Answer Options</label>
                    <div class="options-list" id="optionsList">
                        <div class="option-input">
                            <input type="text" name="options[]" placeholder="Option 1" required>
                        </div>
                        <div class="option-input">
                            <input type="text" name="options[]" placeholder="Option 2" required>
                        </div>
                        <div class="option-input">
                            <input type="text" name="options[]" placeholder="Option 3">
                        </div>
                        <div class="option-input">
                            <input type="text" name="options[]" placeholder="Option 4">
                        </div>
                    </div>
                    <button type="button" class="add-option-btn" onclick="addOption()" style="margin-top: 8px;">
                        ➕ Add Option
                    </button>
                </div>

                <button type="submit" name="add_question" class="btn" style="width: 100%;">
                    💾 Add Question
                </button>
            </form>
        </div>
    </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>

<script>
function addOption() {
    const optionsList = document.getElementById('optionsList');
    const optionCount = optionsList.children.length + 1;
    
    const newOption = document.createElement('div');
    newOption.className = 'option-input';
    newOption.innerHTML = `
        <input type="text" name="options[]" placeholder="Option ${optionCount}">
        <button type="button" class="remove-option-btn" onclick="removeOption(this)">🗑️</button>
    `;
    
    optionsList.appendChild(newOption);
}

function removeOption(button) {
    button.parentElement.remove();
}
</script>

</body>
</html>