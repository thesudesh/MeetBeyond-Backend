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

// Handle BULK DELETE action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'delete') {
    if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
        $selected_ids = array_map('intval', $_POST['selected_ids']);
        $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
        
        try {
            $conn->begin_transaction();
            $stmt = $conn->prepare("DELETE FROM $table WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
            
            if ($stmt->execute()) {
                $deleted_count = $stmt->affected_rows;
                $conn->commit();
                $message = "Successfully deleted $deleted_count record(s)!";
            } else {
                $conn->rollback();
                $error = "Error deleting records: " . $conn->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error deleting records: " . $e->getMessage();
        }
    } else {
        $error = "No records selected for deletion.";
    }
}

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
        
        .bulk-actions {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .bulk-controls {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .select-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .select-all-btn, .select-none-btn {
            padding: 6px 12px;
            background: rgba(167,139,250,0.15);
            border: 1px solid rgba(167,139,250,0.3);
            border-radius: 6px;
            color: var(--accent-purple);
            text-decoration: none;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .select-all-btn:hover, .select-none-btn:hover {
            background: rgba(167,139,250,0.25);
        }
        
        .bulk-delete-btn {
            padding: 8px 16px;
            background: rgba(239,68,68,0.2);
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 8px;
            color: #f87171;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .bulk-delete-btn:hover:not(:disabled) {
            background: rgba(239,68,68,0.3);
            transform: translateY(-1px);
        }
        
        .bulk-delete-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .checkbox-cell {
            width: 40px;
            text-align: center;
        }
        
        .row-checkbox {
            width: 16px;
            height: 16px;
            accent-color: var(--accent-purple);
        }
        
        .selected-count {
            color: var(--text);
            font-weight: 600;
            font-size: 0.9rem;
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

        <!-- Bulk Actions -->
        <form method="POST" id="bulkForm">
            <div class="bulk-actions">
                <div class="bulk-controls">
                    <div class="select-controls">
                        <button type="button" class="select-all-btn" onclick="selectAll()">
                            ☑️ Select All
                        </button>
                        <button type="button" class="select-none-btn" onclick="selectNone()">
                            ⬜ Select None
                        </button>
                    </div>
                    <div class="selected-count">
                        Selected: <span id="selectedCount">0</span> record(s)
                    </div>
                </div>
                <div>
                    <input type="hidden" name="bulk_action" value="delete">
                    <button type="submit" class="bulk-delete-btn" id="bulkDeleteBtn" disabled 
                            onclick="return confirm('Are you sure you want to delete the selected records? This action cannot be undone!')">
                        🗑️ Delete Selected
                    </button>
                </div>
            </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th class="checkbox-cell">
                                <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll(this)">
                            </th>
                            <?php foreach ($columns as $col): ?>
                                <th><?php echo htmlspecialchars($col['Field']); ?></th>
                            <?php endforeach; ?>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="<?php echo count($columns) + 2; ?>" style="text-align: center; padding: 40px; color: var(--muted);">
                                    No records found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" name="selected_ids[]" value="<?php echo $row['id']; ?>" 
                                               class="row-checkbox" onchange="updateSelectedCount()">
                                    </td>
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
        </form>
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

<script>
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    const count = checkboxes.length;
    const selectedCountSpan = document.getElementById('selectedCount');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    
    selectedCountSpan.textContent = count;
    bulkDeleteBtn.disabled = count === 0;
    
    // Update select all checkbox state
    const allCheckboxes = document.querySelectorAll('.row-checkbox');
    if (count === 0) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
    } else if (count === allCheckboxes.length) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = true;
    } else {
        selectAllCheckbox.indeterminate = true;
    }
}

function toggleAll(selectAllCheckbox) {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    updateSelectedCount();
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    updateSelectedCount();
}

function selectNone() {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    updateSelectedCount();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCount();
    
    // Add row click to select functionality
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const checkbox = row.querySelector('.row-checkbox');
        if (checkbox) {
            row.addEventListener('click', function(e) {
                // Don't trigger if clicking on links, buttons, or the checkbox itself
                if (e.target.tagName !== 'A' && e.target.tagName !== 'BUTTON' && e.target.type !== 'checkbox') {
                    checkbox.checked = !checkbox.checked;
                    updateSelectedCount();
                }
            });
            
            // Add visual feedback for clickable rows
            row.style.cursor = 'pointer';
        }
    });
});
</script>

</body>
</html>
