<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if user is admin
$stmt = $conn->prepare("SELECT role FROM Users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

if ($role !== 'admin') {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_users') {
        $count = (int)($_POST['user_count'] ?? 10);
        $count = min($count, 100); // Limit to 100 users at once
        
        try {
            $conn->begin_transaction();
            
            $created = 0;
            for ($i = 1; $i <= $count; $i++) {
                // Generate unique email
                $email = "user" . time() . $i . "@example.com";
                $password = password_hash("password123", PASSWORD_DEFAULT);
                
                // Insert user
                $stmt = $conn->prepare("INSERT INTO Users (email, password_hash, role, created_at) VALUES (?, ?, 'member', NOW())");
                $stmt->bind_param("ss", $email, $password);
                if ($stmt->execute()) {
                    $new_user_id = $conn->insert_id;
                    $stmt->close();
                    
                    // Create profile with simple name selection
                    $male_names = [
                        'Alexander Johnson', 'Benjamin Smith', 'Christopher Davis', 'Daniel Wilson', 'Ethan Brown',
                        'Felix Martinez', 'Gabriel Garcia', 'Harrison Miller', 'Isaac Rodriguez', 'Jackson Taylor',
                        'Kevin Anderson', 'Liam Thompson', 'Matthew White', 'Nathan Harris', 'Oliver Clark',
                        'Patrick Lewis', 'Quinn Walker', 'Ryan Hall', 'Samuel Young', 'Theodore King',
                        'Victor Wright', 'William Lopez', 'Xavier Hill', 'Zachary Green', 'Adrian Scott'
                    ];
                    
                    $female_names = [
                        'Sophia Williams', 'Emma Johnson', 'Olivia Brown', 'Ava Davis', 'Isabella Miller',
                        'Charlotte Wilson', 'Amelia Moore', 'Harper Taylor', 'Evelyn Anderson', 'Abigail Thomas',
                        'Emily Jackson', 'Elizabeth White', 'Sofia Harris', 'Avery Martin', 'Ella Thompson',
                        'Scarlett Garcia', 'Grace Martinez', 'Chloe Robinson', 'Victoria Clark', 'Madison Rodriguez',
                        'Luna Lewis', 'Penelope Lee', 'Layla Walker', 'Riley Hall', 'Zoey Young'
                    ];
                    
                    $locations = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose'];
                    
                    // Select gender and name
                    $genders = ['male', 'female'];
                    $gender = $genders[array_rand($genders)];
                    
                    if ($gender === 'male') {
                        $name = $male_names[($i - 1) % count($male_names)];
                    } else {
                        $name = $female_names[($i - 1) % count($female_names)];
                    }
                    
                    $age = rand(18, 45);
                    $first_name = explode(' ', $name)[0];
                    $bio_templates = [
                        "Hi! I'm $first_name and I love exploring new places and meeting interesting people. Let's chat!",
                        "Hey there! $first_name here. I'm passionate about life and always up for an adventure.",
                        "$first_name's the name! I enjoy good conversations, great food, and spontaneous adventures.",
                        "Hello! I'm $first_name. Looking to connect with genuine people and see where things go.",
                        "Hi, I'm $first_name! Love hiking, reading, and trying new restaurants. What about you?",
                        "$first_name here! Big fan of music, movies, and meaningful connections.",
                        "Hey! $first_name speaking. I believe in living life to the fullest and spreading good vibes.",
                        "Hi there! I'm $first_name and I'm all about making memories and meeting amazing people."
                    ];
                    $bio = $bio_templates[array_rand($bio_templates)];
                    
                    // Debug before database insert
                    error_log("DEBUG: About to insert - User ID: $new_user_id, Name: '$name', Age: $age, Gender: '$gender'");
                    
                    $stmt = $conn->prepare("INSERT INTO Profiles (user_id, name, age, gender, bio, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("isiss", $new_user_id, $name, $age, $gender, $bio);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Add profile photo
                    $photo_added = false;
                    if ($gender === 'male') {
                        $photo_num = rand(1, 25);
                        $source_path = __DIR__ . "/images/men/m$photo_num.jpg";
                        if (file_exists($source_path)) {
                            // Create unique filename
                            $unique_filename = "user_" . $new_user_id . "_" . time() . "_" . $i . "_m$photo_num.jpg";
                            $dest_path = __DIR__ . "/MBusers/photos/" . $unique_filename;
                            
                            // Ensure directory exists
                            $photo_dir = dirname($dest_path);
                            if (!is_dir($photo_dir)) {
                                mkdir($photo_dir, 0755, true);
                            }
                            
                            // Copy the image
                            if (copy($source_path, $dest_path)) {
                                $stmt = $conn->prepare("INSERT INTO Photos (user_id, file_path, is_primary, is_active, uploaded_at) VALUES (?, ?, 1, 1, NOW())");
                                $stmt->bind_param("is", $new_user_id, $unique_filename);
                                $stmt->execute();
                                $stmt->close();
                                $photo_added = true;
                            }
                        }
                    } else { // female
                        $photo_num = rand(1, 25);
                        $source_path = __DIR__ . "/images/women/f$photo_num.jpg";
                        if (file_exists($source_path)) {
                            // Create unique filename
                            $unique_filename = "user_" . $new_user_id . "_" . time() . "_" . $i . "_f$photo_num.jpg";
                            $dest_path = __DIR__ . "/MBusers/photos/" . $unique_filename;
                            
                            // Ensure directory exists
                            $photo_dir = dirname($dest_path);
                            if (!is_dir($photo_dir)) {
                                mkdir($photo_dir, 0755, true);
                            }
                            
                            // Copy the image
                            if (copy($source_path, $dest_path)) {
                                $stmt = $conn->prepare("INSERT INTO Photos (user_id, file_path, is_primary, is_active, uploaded_at) VALUES (?, ?, 1, 1, NOW())");
                                $stmt->bind_param("is", $new_user_id, $unique_filename);
                                $stmt->execute();
                                $stmt->close();
                                $photo_added = true;
                            }
                        }
                    }
                    
                    // Create preferences
                    $min_age = max(18, $age - 10);
                    $max_age = min(65, $age + 10);
                    $gender_prefs = ['male', 'female', 'both'];
                    $gender_pref = $gender_prefs[array_rand($gender_prefs)];
                    $location = $locations[array_rand($locations)];
                    $relationship_types = ['casual', 'serious', 'friendship', 'any'];
                    $relationship_type = $relationship_types[array_rand($relationship_types)];
                    
                    $stmt = $conn->prepare("INSERT INTO Preferences (user_id, min_age, max_age, gender_pref, location, relationship_type) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iiisss", $new_user_id, $min_age, $max_age, $gender_pref, $location, $relationship_type);
                    $stmt->execute();
                    $stmt->close();
                    
                    $created++;
                }
            }
            
            $conn->commit();
            $message = "Successfully created $created users with profiles, preferences, and photos!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error creating users: " . $e->getMessage();
        }
    }
    
    if ($action === 'create_likes') {
        $count = (int)($_POST['like_count'] ?? 50);
        $count = min($count, 500); // Limit to 500 likes at once
        
        // Get all user IDs
        $result = $conn->query("SELECT id FROM Users WHERE role = 'member' ORDER BY id");
        $user_ids = [];
        while ($row = $result->fetch_assoc()) {
            $user_ids[] = $row['id'];
        }
        
        if (count($user_ids) < 2) {
            $error = "Need at least 2 users to create likes. Create users first.";
        } else {
            try {
                $conn->begin_transaction();
                
                $created = 0;
                for ($i = 0; $i < $count; $i++) {
                    $liker = $user_ids[array_rand($user_ids)];
                    $liked = $user_ids[array_rand($user_ids)];
                    
                    // Don't like yourself
                    if ($liker === $liked) continue;
                    
                    $statuses = ['liked', 'passed'];
                    $status = $statuses[array_rand($statuses)];
                    
                    $stmt = $conn->prepare("INSERT IGNORE INTO Likes (liker_id, liked_id, status, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->bind_param("iis", $liker, $liked, $status);
                    if ($stmt->execute() && $conn->affected_rows > 0) {
                        $created++;
                    }
                    $stmt->close();
                }
                
                $conn->commit();
                $message = "Successfully created $created likes/passes!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error creating likes: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'create_matches') {
        // Get all mutual likes
        $result = $conn->query("
            SELECT l1.liker_id, l1.liked_id 
            FROM Likes l1 
            JOIN Likes l2 ON l1.liker_id = l2.liked_id AND l1.liked_id = l2.liker_id 
            WHERE l1.status = 'liked' AND l2.status = 'liked'
            AND NOT EXISTS (SELECT 1 FROM Matches WHERE (user1_id = l1.liker_id AND user2_id = l1.liked_id) OR (user1_id = l1.liked_id AND user2_id = l1.liker_id))
        ");
        
        try {
            $conn->begin_transaction();
            
            $created = 0;
            while ($row = $result->fetch_assoc()) {
                $user1 = min($row['liker_id'], $row['liked_id']);
                $user2 = max($row['liker_id'], $row['liked_id']);
                
                $stmt = $conn->prepare("INSERT IGNORE INTO Matches (user1_id, user2_id, user_low_id, user_high_id, matched_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("iiii", $row['liker_id'], $row['liked_id'], $user1, $user2);
                if ($stmt->execute() && $conn->affected_rows > 0) {
                    $created++;
                }
                $stmt->close();
            }
            
            $conn->commit();
            $message = "Successfully created $created matches from mutual likes!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error creating matches: " . $e->getMessage();
        }
    }
    
    if ($action === 'create_messages') {
        $count = (int)($_POST['message_count'] ?? 100);
        $count = min($count, 1000); // Limit to 1000 messages at once
        
        // First, check if Messages table has match_id or sender_id/receiver_id
        $table_structure = $conn->query("DESCRIBE Messages");
        $has_match_id = false;
        $has_sender_receiver = false;
        
        while ($row = $table_structure->fetch_assoc()) {
            if ($row['Field'] === 'match_id') $has_match_id = true;
            if ($row['Field'] === 'sender_id') $has_sender_receiver = true;
        }
        
        if ($has_match_id && !$has_sender_receiver) {
            // Original schema with match_id
            $result = $conn->query("SELECT id, user1_id, user2_id FROM Matches ORDER BY RAND()");
            $matches = [];
            while ($row = $result->fetch_assoc()) {
                $matches[] = ['id' => $row['id'], 'user1' => $row['user1_id'], 'user2' => $row['user2_id']];
            }
            
            if (empty($matches)) {
                $error = "No matches found. Create matches first.";
            } else {
                $sample_messages = [
                    "Hey! How's it going?",
                    "Hi there! Nice to match with you 😊",
                    "Hello! What are you up to today?",
                    "Hey! I loved your profile!",
                    "Hi! How was your weekend?",
                    "Hello! What's your favorite hobby?",
                    "Hey! Great photos! Where was that taken?",
                    "Hi! What kind of music do you like?",
                    "Hello! Are you new to the area?",
                    "Hey! What's your favorite restaurant around here?"
                ];
                
                try {
                    $conn->begin_transaction();
                    
                    $created = 0;
                    for ($i = 0; $i < $count; $i++) {
                        $match = $matches[array_rand($matches)];
                        $sender = $match['user1']; // Always use user1 as sender for simplicity
                        $message_text = $sample_messages[array_rand($sample_messages)];
                        
                        $stmt = $conn->prepare("INSERT INTO Messages (match_id, sender_id, message, sent_at) VALUES (?, ?, ?, NOW())");
                        $stmt->bind_param("iis", $match['id'], $sender, $message_text);
                        if ($stmt->execute()) {
                            $created++;
                        }
                        $stmt->close();
                    }
                    
                    $conn->commit();
                    $message = "Successfully created $created messages!";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error creating messages: " . $e->getMessage();
                }
            }
        } else {
            // Updated schema with sender_id/receiver_id
            $result = $conn->query("SELECT user1_id, user2_id FROM Matches ORDER BY RAND()");
            $matches = [];
            while ($row = $result->fetch_assoc()) {
                $matches[] = [$row['user1_id'], $row['user2_id']];
            }
            
            if (empty($matches)) {
                $error = "No matches found. Create matches first.";
            } else {
                $sample_messages = [
                    "Hey! How's it going?",
                    "Hi there! Nice to match with you 😊",
                    "Hello! What are you up to today?",
                    "Hey! I loved your profile!",
                    "Hi! How was your weekend?",
                    "Hello! What's your favorite hobby?",
                    "Hey! Great photos! Where was that taken?",
                    "Hi! What kind of music do you like?",
                    "Hello! Are you new to the area?",
                    "Hey! What's your favorite restaurant around here?"
                ];
                
                try {
                    $conn->begin_transaction();
                    
                    $created = 0;
                    for ($i = 0; $i < $count; $i++) {
                        $match = $matches[array_rand($matches)];
                        $sender = $match[array_rand($match)]; // Random user from the match
                        $receiver = $sender === $match[0] ? $match[1] : $match[0];
                        
                        $message_text = $sample_messages[array_rand($sample_messages)];
                        
                        $stmt = $conn->prepare("INSERT INTO Messages (sender_id, receiver_id, message, sent_at) VALUES (?, ?, ?, NOW())");
                        $stmt->bind_param("iis", $sender, $receiver, $message_text);
                        if ($stmt->execute()) {
                            $created++;
                        }
                        $stmt->close();
                    }
                    
                    $conn->commit();
                    $message = "Successfully created $created messages!";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error creating messages: " . $e->getMessage();
                }
            }
        }
    }
    
    if ($action === 'execute_sql') {
        $sql = trim($_POST['custom_sql'] ?? '');
        
        if (empty($sql)) {
            $error = "Please enter SQL commands.";
        } else {
            // Security check - only allow certain operations
            $sql_lower = strtolower($sql);
            $dangerous_keywords = ['drop', 'delete', 'truncate', 'alter table', 'create database', 'drop database'];
            
            $is_dangerous = false;
            foreach ($dangerous_keywords as $keyword) {
                if (strpos($sql_lower, $keyword) !== false) {
                    $is_dangerous = true;
                    break;
                }
            }
            
            if ($is_dangerous) {
                $error = "Dangerous SQL commands are not allowed. Only SELECT, INSERT, UPDATE operations are permitted.";
            } else {
                try {
                    $result = $conn->query($sql);
                    if ($result === true) {
                        $message = "SQL executed successfully. Affected rows: " . $conn->affected_rows;
                    } elseif ($result) {
                        $message = "SQL executed successfully. Returned " . $result->num_rows . " rows.";
                    } else {
                        $error = "SQL Error: " . $conn->error;
                    }
                } catch (Exception $e) {
                    $error = "SQL Error: " . $e->getMessage();
                }
            }
        }
    }
}

// Get current statistics
$stats = [];
$tables = ['Users', 'Profiles', 'Preferences', 'Likes', 'Matches', 'Messages', 'Photos', 'Events', 'QuizQuestions', 'QuizAnswers'];

foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    $stats[$table] = $result ? $result->fetch_assoc()['count'] : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dummy Data Generator - Meet Beyond Admin</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <style>
        .generator-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--text);
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            background: rgba(0,0,0,0.2);
            color: var(--text);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent-purple);
            box-shadow: 0 0 0 3px rgba(167,139,250,0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 120px;
            font-family: 'Courier New', monospace;
        }
        
        .btn-generate {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16,185,129,0.3);
        }
        
        .btn-sql {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }
        
        .btn-sql:hover {
            box-shadow: 0 8px 25px rgba(59,130,246,0.3);
        }
        
        .warning-box {
            background: rgba(245,158,11,0.1);
            border: 2px solid rgba(245,158,11,0.3);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        
        .success-message {
            background: rgba(16,185,129,0.1);
            border: 2px solid rgba(16,185,129,0.3);
            color: #10b981;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .error-message {
            background: rgba(239,68,68,0.1);
            border: 2px solid rgba(239,68,68,0.3);
            color: #ef4444;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--accent-purple);
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--muted);
            margin-top: 4px;
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container" style="padding: 28px 20px;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <!-- Header -->
        <div style="margin-bottom: 32px;">
            <h1 style="font-size: 2rem; font-weight: 600; margin-bottom: 8px; color: var(--text);">
                🎲 Dummy Data Generator
            </h1>
            <p style="color: var(--muted); font-size: 1rem;">
                Generate realistic test data for development and testing
            </p>
            <a href="admin.php" style="color: var(--accent-purple); text-decoration: none; font-size: 0.9rem;">← Back to Admin Dashboard</a>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="success-message">
                ✅ <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Current Statistics -->
        <div class="generator-card">
            <h3 style="margin-bottom: 20px; color: var(--text);">📊 Current Database Statistics</h3>
            <div class="stats-grid">
                <?php foreach ($stats as $table => $count): ?>
                    <div class="stat-card">
                        <div class="stat-number"><?= number_format($count) ?></div>
                        <div class="stat-label"><?= $table ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top: 16px; padding: 12px; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); border-radius: 8px; font-size: 0.9rem; color: var(--muted);">
                📸 <strong>Photo Integration:</strong> Users are automatically assigned photos from your images/men and images/women collections
            </div>
        </div>
        
        <!-- Warning -->
        <div class="warning-box">
            <h4 style="color: #f59e0b; margin-bottom: 8px;">⚠️ Important Notice</h4>
            <p style="color: var(--muted); margin: 0; font-size: 0.9rem;">
                This tool generates dummy data for testing purposes. Use with caution in production environments.
                Always backup your database before generating large amounts of data.
            </p>
        </div>
        
        <!-- Data Generators -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
            
            <!-- User Generator -->
            <div class="generator-card">
                <h3 style="margin-bottom: 16px; color: var(--text);">👥 Generate Users</h3>
                <p style="color: var(--muted); margin-bottom: 20px; font-size: 0.9rem;">
                    Create realistic users with unique names, profiles, preferences, and photos from your image collection
                </p>
                <form method="POST">
                    <input type="hidden" name="action" value="create_users">
                    <div class="form-group">
                        <label class="form-label">Number of Users (max 100)</label>
                        <input type="number" name="user_count" class="form-input" value="10" min="1" max="100">
                    </div>
                    <button type="submit" class="btn-generate">Generate Users</button>
                </form>
            </div>
            
            <!-- Likes Generator -->
            <div class="generator-card">
                <h3 style="margin-bottom: 16px; color: var(--text);">💖 Generate Likes</h3>
                <p style="color: var(--muted); margin-bottom: 20px; font-size: 0.9rem;">
                    Create random likes and passes between users
                </p>
                <form method="POST">
                    <input type="hidden" name="action" value="create_likes">
                    <div class="form-group">
                        <label class="form-label">Number of Likes (max 500)</label>
                        <input type="number" name="like_count" class="form-input" value="50" min="1" max="500">
                    </div>
                    <button type="submit" class="btn-generate">Generate Likes</button>
                </form>
            </div>
            
            <!-- Match Generator -->
            <div class="generator-card">
                <h3 style="margin-bottom: 16px; color: var(--text);">❤️ Generate Matches</h3>
                <p style="color: var(--muted); margin-bottom: 20px; font-size: 0.9rem;">
                    Create matches from existing mutual likes
                </p>
                <form method="POST">
                    <input type="hidden" name="action" value="create_matches">
                    <button type="submit" class="btn-generate">Generate Matches</button>
                </form>
            </div>
            
            <!-- Message Generator -->
            <div class="generator-card">
                <h3 style="margin-bottom: 16px; color: var(--text);">💬 Generate Messages</h3>
                <p style="color: var(--muted); margin-bottom: 20px; font-size: 0.9rem;">
                    Create sample messages between matched users
                </p>
                <form method="POST">
                    <input type="hidden" name="action" value="create_messages">
                    <div class="form-group">
                        <label class="form-label">Number of Messages (max 1000)</label>
                        <input type="number" name="message_count" class="form-input" value="100" min="1" max="1000">
                    </div>
                    <button type="submit" class="btn-generate">Generate Messages</button>
                </form>
            </div>
        </div>
        
        <!-- Custom SQL -->
        <div class="generator-card" style="margin-top: 30px;">
            <h3 style="margin-bottom: 16px; color: var(--text);">🛠️ Custom SQL Commands</h3>
            <p style="color: var(--muted); margin-bottom: 20px; font-size: 0.9rem;">
                Execute custom SQL commands. Only INSERT, UPDATE, and SELECT operations are allowed for security.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="execute_sql">
                <div class="form-group">
                    <label class="form-label">SQL Commands</label>
                    <textarea name="custom_sql" class="form-input form-textarea" placeholder="INSERT INTO Users (email, password_hash, role) VALUES ('test@example.com', 'hash', 'user');&#10;&#10;-- Add your SQL commands here&#10;-- Dangerous operations (DROP, DELETE, TRUNCATE) are blocked"></textarea>
                </div>
                <button type="submit" class="btn-generate btn-sql">Execute SQL</button>
            </form>
        </div>
        
        <!-- Quick Actions -->
        <div class="generator-card" style="margin-top: 30px;">
            <h3 style="margin-bottom: 16px; color: var(--text);">⚡ Quick Actions</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="create_users">
                    <input type="hidden" name="user_count" value="50">
                    <button type="submit" class="btn-generate" style="width: 100%;">Create 50 Users</button>
                </form>
                
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="create_likes">
                    <input type="hidden" name="like_count" value="200">
                    <button type="submit" class="btn-generate" style="width: 100%;">Create 200 Likes</button>
                </form>
                
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="create_matches">
                    <button type="submit" class="btn-generate" style="width: 100%;">Generate All Matches</button>
                </form>
                
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="create_messages">
                    <input type="hidden" name="message_count" value="500">
                    <button type="submit" class="btn-generate" style="width: 100%;">Create 500 Messages</button>
                </form>
            </div>
        </div>
    </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>