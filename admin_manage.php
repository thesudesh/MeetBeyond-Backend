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
$allowed_tables = ['Users', 'Profiles', 'Photos', 'Preferences', 'Likes', 'Matches', 'Messages', 'Blocks', 'Reports', 'Events', 'EventParticipants', 'Subscriptions', 'QuizQuestions', 'QuizAnswers', 'Logs'];

if (!in_array($table, $allowed_tables)) {
    $table = 'Users';
}

$message = '';
$error = '';

// Handle DELETE action
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Record deleted successfully!";
    } else {
        $error = "Error deleting record: " . $conn->error;
    }
    $stmt->close();
}

// Fetch table structure
$structure_query = "DESCRIBE $table";
$structure_result = $conn->query($structure_query);
$columns = [];
while ($row = $structure_result->fetch_assoc()) {
    $columns[] = $row;
}

// Fetch data with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$count_query = "SELECT COUNT(*) as total FROM $table";
$count_result = $conn->query($count_query);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

$data_query = "SELECT * FROM $table LIMIT $per_page OFFSET $offset";
$data_result = $conn->query($data_query);
$data = [];
while ($row = $data_result->fetch_assoc()) {
    $data[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage <?php echo htmlspecialchars($table); ?> - Admin Panel</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .table-container {
            overflow-x: auto;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-radius: 12px;
            overflow: hidden;
        }
        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid rgba(167,139,250,0.15);
        }
        th {
            background: rgba(167,139,250,0.15);
            font-weight: 600;
            color: var(--text);
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            color: var(--muted);
            font-size: 0.9rem;
        }
        tr:hover td {
            background: rgba(167,139,250,0.08);
        }
        .action-btn {
            padding: 6px 12px;
            margin: 0 4px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn-edit {
            background: rgba(59,130,246,0.2);
            color: #60a5fa;
            border: 1px solid rgba(59,130,246,0.3);
        }
        .btn-edit:hover {
            background: rgba(59,130,246,0.3);
        }
        .btn-delete {
            background: rgba(239,68,68,0.2);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
        }
        .btn-delete:hover {
            background: rgba(239,68,68,0.3);
        }
        .pagination {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-top: 24px;
            flex-wrap: wrap;
        }
        .pagination a {
            padding: 8px 14px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 6px;
            text-decoration: none;
            color: var(--text);
            transition: all 0.3s ease;
        }
        .pagination a:hover, .pagination a.active {
            background: rgba(167,139,250,0.2);
            border-color: rgba(167,139,250,0.5);
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
        .truncate {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
<a href="admin.php" class="back-btn" title="Back to Admin Dashboard">←</a>

<main class="container" style="padding: 28px 20px;">
    <div style="max-width: 1400px; margin: 0 auto;">
        <div style="margin-bottom: 24px;">
            <h1 style="font-size: 2rem; font-weight: 600; margin-bottom: 8px; color: var(--text)">
                Manage <?php echo htmlspecialchars($table); ?>
            </h1>
            <p style="color: var(--muted); font-size: 1rem">
                Total Records: <?php echo $total_records; ?>
            </p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div style="margin-bottom: 20px;">
            <a href="admin_edit.php?table=<?php echo urlencode($table); ?>&action=add" class="btn">
                ➕ Add New Record
            </a>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <th><?php echo htmlspecialchars($col['Field']); ?></th>
                            <?php endforeach; ?>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="<?php echo count($columns) + 1; ?>" style="text-align: center; padding: 40px; color: var(--muted);">
                                    No records found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <?php foreach ($columns as $col): ?>
                                        <td>
                                            <?php 
                                            $value = $row[$col['Field']] ?? '';
                                            if ($col['Field'] === 'password_hash') {
                                                echo '••••••••';
                                            } elseif (strlen($value) > 50) {
                                                echo '<span class="truncate" title="' . htmlspecialchars($value) . '">' . htmlspecialchars(substr($value, 0, 50)) . '...</span>';
                                            } else {
                                                echo htmlspecialchars($value);
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <a href="admin_edit.php?table=<?php echo urlencode($table); ?>&id=<?php echo $row['id']; ?>&action=edit" class="action-btn btn-edit">
                                            ✏️ Edit
                                        </a>
                                        <a href="?table=<?php echo urlencode($table); ?>&delete=1&id=<?php echo $row['id']; ?>&page=<?php echo $page; ?>" 
                                           class="action-btn btn-delete" 
                                           onclick="return confirm('Are you sure you want to delete this record? This action cannot be undone.')">
                                            🗑️ Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?table=<?php echo urlencode($table); ?>&page=1">« First</a>
                    <a href="?table=<?php echo urlencode($table); ?>&page=<?php echo $page - 1; ?>">‹ Previous</a>
                <?php endif; ?>

                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++): 
                ?>
                    <a href="?table=<?php echo urlencode($table); ?>&page=<?php echo $i; ?>" 
                       class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?table=<?php echo urlencode($table); ?>&page=<?php echo $page + 1; ?>">Next ›</a>
                    <a href="?table=<?php echo urlencode($table); ?>&page=<?php echo $total_pages; ?>">Last »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
