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
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

if ($role !== 'admin') {
    header('Location: index.php');
    exit;
}

$table = $_GET['table'] ?? 'Users';
$action = $_GET['action'] ?? 'edit'; // 'edit' or 'add'
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$allowed_tables = ['Users', 'Profiles', 'Photos', 'Preferences', 'Likes', 'Matches', 'Messages', 'Blocks', 'Reports', 'Events', 'EventParticipants', 'Subscriptions', 'QuizQuestions', 'QuizAnswers', 'Logs'];

if (!in_array($table, $allowed_tables)) {
    header('Location: admin.php');
    exit;
}

$message = '';
$error = '';

// Fetch table structure
$structure_query = "DESCRIBE $table";
$structure_result = $conn->query($structure_query);
$columns = [];
while ($row = $structure_result->fetch_assoc()) {
    $columns[] = $row;
}

// Fetch existing data if editing
$data = [];
if ($action === 'edit' && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    if (!$data) {
        header("Location: admin_manage.php?table=" . urlencode($table));
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [];
    $values = [];
    $types = '';
    
    foreach ($columns as $col) {
        $field_name = $col['Field'];
        
        // Skip auto-increment ID for insert
        if ($field_name === 'id' && $action === 'add') continue;
        
        // Skip auto-increment ID and timestamp fields for update
        if ($action === 'edit' && $field_name === 'id') continue;
        
        // Handle timestamp fields
        if (strpos($col['Type'], 'timestamp') !== false && $action === 'add') {
            continue; // Let MySQL handle default timestamps
        }
        
        $value = $_POST[$field_name] ?? null;
        
        // Handle password hashing for Users table
        if ($table === 'Users' && $field_name === 'password_hash' && !empty($value)) {
            $value = password_hash($value, PASSWORD_DEFAULT);
        }
        
        // Handle NULL values
        if ($value === '' || $value === null) {
            // Check if NULL is allowed
            if ($col['Null'] === 'YES') {
                $value = null;
            } else {
                // Set default values for NOT NULL fields
                if (strpos($col['Type'], 'int') !== false) {
                    $value = 0;
                } elseif (strpos($col['Type'], 'varchar') !== false || strpos($col['Type'], 'text') !== false) {
                    $value = '';
                } elseif (strpos($col['Type'], 'boolean') !== false || strpos($col['Type'], 'tinyint(1)') !== false) {
                    $value = 0;
                }
            }
        }
        
        $fields[] = $field_name;
        $values[] = $value;
        
        // Determine type
        if ($value === null) {
            $types .= 's'; // Treat NULL as string
        } elseif (strpos($col['Type'], 'int') !== false) {
            $types .= 'i';
        } elseif (strpos($col['Type'], 'double') !== false || strpos($col['Type'], 'decimal') !== false) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    
    if ($action === 'add') {
        // INSERT query
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $field_list = implode(',', $fields);
        $sql = "INSERT INTO $table ($field_list) VALUES ($placeholders)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            $message = "Record added successfully!";
            header("Location: admin_manage.php?table=" . urlencode($table) . "&success=added");
            exit;
        } else {
            $error = "Error adding record: " . $conn->error;
        }
        $stmt->close();
        
    } else {
        // UPDATE query
        $set_clause = implode(' = ?, ', $fields) . ' = ?';
        $sql = "UPDATE $table SET $set_clause WHERE id = ?";
        
        $values[] = $id;
        $types .= 'i';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            $message = "Record updated successfully!";
            header("Location: admin_manage.php?table=" . urlencode($table) . "&success=updated");
            exit;
        } else {
            $error = "Error updating record: " . $conn->error;
        }
        $stmt->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($action); ?> <?php echo htmlspecialchars($table); ?> Record - Admin Panel</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }
        .form-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .form-field label {
            font-weight: 600;
            color: var(--text);
            font-size: 0.95rem;
        }
        .form-field input,
        .form-field select,
        .form-field textarea {
            padding: 12px 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--card-border);
            border-radius: 8px;
            color: var(--text);
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        .form-field input:focus,
        .form-field select:focus,
        .form-field textarea:focus {
            outline: none;
            border-color: rgba(167,139,250,0.5);
            background: rgba(255,255,255,0.08);
        }
        .form-field textarea {
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
        }
        .form-field small {
            color: var(--muted);
            font-size: 0.85rem;
        }
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 32px;
            justify-content: flex-start;
        }
        .alert {
            padding: 14px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: rgba(34,197,94,0.2);
            border: 1px solid rgba(34,197,94,0.3);
            color: #4ade80;
        }
        .alert-error {
            background: rgba(239,68,68,0.2);
            border: 1px solid rgba(239,68,68,0.3);
            color: #f87171;
        }
        .full-width {
            grid-column: 1 / -1;
        }
    </style>
</head>
<body>
<a href="admin_manage.php?table=<?php echo urlencode($table); ?>" class="back-btn" title="Back to Table">←</a>

<main class="container" style="padding: 28px 20px;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <div style="margin-bottom: 24px;">
            <h1 style="font-size: 2rem; font-weight: 600; margin-bottom: 8px; color: var(--text)">
                <?php echo ucfirst($action); ?> <?php echo htmlspecialchars($table); ?> Record
            </h1>
            <p style="color: var(--muted); font-size: 1rem">
                <?php echo $action === 'add' ? 'Fill in the details to create a new record' : 'Modify the fields you want to update'; ?>
            </p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="">
                <div class="form-grid">
                    <?php foreach ($columns as $col): ?>
                        <?php 
                        $field_name = $col['Field'];
                        $field_type = $col['Type'];
                        $is_nullable = $col['Null'] === 'YES';
                        $default_value = $col['Default'];
                        
                        // Skip ID field for add action
                        if ($field_name === 'id' && $action === 'add') continue;
                        
                        // Get current value
                        $current_value = $data[$field_name] ?? $default_value ?? '';
                        
                        // Determine if this is a timestamp field that should be auto-managed
                        $is_auto_timestamp = strpos($field_type, 'timestamp') !== false && 
                                           ($default_value === 'CURRENT_TIMESTAMP' || strpos($col['Extra'], 'on update') !== false);
                        
                        // Skip auto-managed timestamp fields
                        if ($is_auto_timestamp) continue;
                        
                        // Determine field class (full-width for text fields)
                        $field_class = (strpos($field_type, 'text') !== false || $field_name === 'bio' || $field_name === 'description') ? 'form-field full-width' : 'form-field';
                        ?>
                        
                        <div class="<?php echo $field_class; ?>">
                            <label for="<?php echo htmlspecialchars($field_name); ?>">
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $field_name))); ?>
                                <?php if (!$is_nullable && $field_name !== 'id'): ?>
                                    <span style="color: #f87171;">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php if (strpos($field_type, 'enum') !== false): ?>
                                <?php
                                // Extract ENUM values
                                preg_match("/^enum\(\'(.*)\'\)$/", $field_type, $matches);
                                $enum_values = explode("','", $matches[1]);
                                ?>
                                <select name="<?php echo htmlspecialchars($field_name); ?>" 
                                        id="<?php echo htmlspecialchars($field_name); ?>"
                                        <?php echo !$is_nullable ? 'required' : ''; ?>>
                                    <?php if ($is_nullable): ?>
                                        <option value="">-- Select --</option>
                                    <?php endif; ?>
                                    <?php foreach ($enum_values as $enum_val): ?>
                                        <option value="<?php echo htmlspecialchars($enum_val); ?>"
                                                <?php echo $current_value === $enum_val ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(ucfirst($enum_val)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                            <?php elseif (strpos($field_type, 'text') !== false || $field_name === 'bio' || $field_name === 'description'): ?>
                                <textarea name="<?php echo htmlspecialchars($field_name); ?>"
                                          id="<?php echo htmlspecialchars($field_name); ?>"
                                          <?php echo !$is_nullable ? 'required' : ''; ?>><?php echo htmlspecialchars($current_value); ?></textarea>
                                
                            <?php elseif (strpos($field_type, 'int') !== false || strpos($field_type, 'decimal') !== false || strpos($field_type, 'double') !== false): ?>
                                <input type="number" 
                                       name="<?php echo htmlspecialchars($field_name); ?>"
                                       id="<?php echo htmlspecialchars($field_name); ?>"
                                       value="<?php echo htmlspecialchars($current_value); ?>"
                                       <?php echo !$is_nullable ? 'required' : ''; ?>
                                       <?php echo $field_name === 'id' ? 'readonly' : ''; ?>>
                                
                            <?php elseif (strpos($field_type, 'date') !== false && strpos($field_type, 'datetime') === false): ?>
                                <input type="date" 
                                       name="<?php echo htmlspecialchars($field_name); ?>"
                                       id="<?php echo htmlspecialchars($field_name); ?>"
                                       value="<?php echo htmlspecialchars($current_value); ?>"
                                       <?php echo !$is_nullable ? 'required' : ''; ?>>
                                
                            <?php elseif (strpos($field_type, 'datetime') !== false): ?>
                                <input type="datetime-local" 
                                       name="<?php echo htmlspecialchars($field_name); ?>"
                                       id="<?php echo htmlspecialchars($field_name); ?>"
                                       value="<?php echo htmlspecialchars(str_replace(' ', 'T', $current_value)); ?>"
                                       <?php echo !$is_nullable ? 'required' : ''; ?>>
                                
                            <?php elseif (strpos($field_type, 'tinyint(1)') !== false || strpos($field_type, 'boolean') !== false): ?>
                                <select name="<?php echo htmlspecialchars($field_name); ?>"
                                        id="<?php echo htmlspecialchars($field_name); ?>">
                                    <option value="0" <?php echo $current_value == 0 ? 'selected' : ''; ?>>No / False</option>
                                    <option value="1" <?php echo $current_value == 1 ? 'selected' : ''; ?>>Yes / True</option>
                                </select>
                                
                            <?php elseif ($field_name === 'password_hash'): ?>
                                <input type="password" 
                                       name="<?php echo htmlspecialchars($field_name); ?>"
                                       id="<?php echo htmlspecialchars($field_name); ?>"
                                       placeholder="<?php echo $action === 'edit' ? 'Leave blank to keep current password' : 'Enter password'; ?>"
                                       <?php echo $action === 'add' ? 'required' : ''; ?>>
                                <small>Password will be automatically hashed</small>
                                
                            <?php else: ?>
                                <input type="text" 
                                       name="<?php echo htmlspecialchars($field_name); ?>"
                                       id="<?php echo htmlspecialchars($field_name); ?>"
                                       value="<?php echo htmlspecialchars($current_value); ?>"
                                       <?php echo !$is_nullable && $field_name !== 'id' ? 'required' : ''; ?>
                                       <?php echo $field_name === 'id' ? 'readonly' : ''; ?>>
                            <?php endif; ?>
                            
                            <small>Type: <?php echo htmlspecialchars($field_type); ?> | <?php echo $is_nullable ? 'Nullable' : 'Required'; ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">
                        <?php echo $action === 'add' ? '➕ Add Record' : '💾 Update Record'; ?>
                    </button>
                    <a href="admin_manage.php?table=<?php echo urlencode($table); ?>" class="btn-ghost">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
