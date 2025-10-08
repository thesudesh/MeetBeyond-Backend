<?php
/**
 * ADMIN CREATION HACK PAGE
 * WARNING: This page should be deleted or secured in production!
 * Use this only for development/testing purposes.
 */

session_start();
require 'config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $name = trim($_POST['name']);
    
    if (empty($email) || empty($password)) {
        $error = "Email and password are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM Users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = "Email already exists!";
            $stmt->close();
        } else {
            $stmt->close();
            
            // Create admin user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO Users (email, password_hash, role, is_verified) VALUES (?, ?, 'admin', 1)");
            $stmt->bind_param("ss", $email, $password_hash);
            
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                $stmt->close();
                
                // Create basic profile
                if (!empty($name)) {
                    $stmt = $conn->prepare("INSERT INTO Profiles (user_id, name, visible) VALUES (?, ?, 1)");
                    $stmt->bind_param("is", $user_id, $name);
                    $stmt->execute();
                    $stmt->close();
                }
                
                $message = "✅ Admin user created successfully! You can now login with: <br><strong>Email:</strong> $email<br><strong>Password:</strong> (the one you entered)";
            } else {
                $error = "Error creating user: " . $conn->error;
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 Create Admin User - Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .warning-banner {
            background: linear-gradient(135deg, rgba(239,68,68,0.3), rgba(220,38,38,0.3));
            border: 2px solid rgba(239,68,68,0.5);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            text-align: center;
        }
        .warning-icon {
            font-size: 3rem;
            margin-bottom: 12px;
        }
        .hack-card {
            max-width: 500px;
            margin: 60px auto;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            border: 2px solid rgba(239,68,68,0.3);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        .form-group input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(255,255,255,0.05);
            border: 2px solid rgba(167,139,250,0.3);
            border-radius: 10px;
            color: var(--text);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .form-group input:focus {
            outline: none;
            border-color: rgba(239,68,68,0.5);
            background: rgba(255,255,255,0.08);
        }
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 0.95rem;
        }
        .alert-success {
            background: rgba(34,197,94,0.2);
            border: 2px solid rgba(34,197,94,0.4);
            color: #4ade80;
        }
        .alert-error {
            background: rgba(239,68,68,0.2);
            border: 2px solid rgba(239,68,68,0.4);
            color: #f87171;
        }
        .btn-create {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 700;
            font-size: 1.05rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-create:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239,68,68,0.4);
        }
        .links {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 24px;
        }
        .links a {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        .links a:hover {
            color: var(--text);
        }
    </style>
</head>
<body>
    <div class="hack-card">
        <div class="warning-banner">
            <div class="warning-icon">⚠️</div>
            <h3 style="font-size: 1.3rem; font-weight: 700; color: #f87171; margin-bottom: 8px;">
                DEVELOPMENT HACK PAGE
            </h3>
            <p style="color: var(--muted); font-size: 0.9rem;">
                This page creates admin users. <br>
                <strong>Delete this file in production!</strong>
            </p>
        </div>

        <h2 style="font-size: 1.8rem; font-weight: 700; margin-bottom: 8px; color: var(--text); text-align: center;">
            🛡️ Create Admin User
        </h2>
        <p style="color: var(--muted); text-align: center; margin-bottom: 32px;">
            Generate a new administrator account
        </p>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       placeholder="admin@meetbeyond.com" 
                       required
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       placeholder="Minimum 6 characters" 
                       required
                       minlength="6">
            </div>

            <div class="form-group">
                <label for="name">Name (Optional)</label>
                <input type="text" 
                       id="name" 
                       name="name" 
                       placeholder="Admin Name"
                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>

            <button type="submit" class="btn-create">
                🔧 Create Admin User
            </button>
        </form>

        <div class="links">
            <a href="login.php">← Back to Login</a>
            <span style="color: var(--muted);">|</span>
            <a href="index.php">Home</a>
        </div>

        <div style="margin-top: 32px; padding: 16px; background: rgba(239,68,68,0.1); border-radius: 8px; border: 1px dashed rgba(239,68,68,0.3);">
            <p style="color: var(--muted); font-size: 0.85rem; text-align: center; margin: 0;">
                <strong style="color: #f87171;">SECURITY WARNING:</strong><br>
                Delete <code style="color: #ec4899;">create_admin.php</code> before deploying to production!
            </p>
        </div>
    </div>
</body>
</html>
