<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$reporter_id = $_SESSION['user_id'];
$reported_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if (!$reported_user_id || $reported_user_id === $reporter_id) {
    header('Location: index.php');
    exit;
}

// Get reported user info
$stmt = $conn->prepare("SELECT u.email, p.name FROM Users u LEFT JOIN Profiles p ON u.id = p.user_id WHERE u.id = ?");
$stmt->bind_param("i", $reported_user_id);
$stmt->execute();
$stmt->bind_result($reported_email, $reported_name);
$has_user = $stmt->fetch();
$stmt->close();

if (!$has_user) {
    header('Location: index.php');
    exit;
}

$reported_display_name = $reported_name ?: $reported_email;
$success = false;
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['reason']);
    $description = trim($_POST['description']);
    
    if (empty($reason)) {
        $error = 'Please select a reason for reporting.';
    } elseif (empty($description)) {
        $error = 'Please provide a description of the issue.';
    } else {
        // Insert report
        $stmt = $conn->prepare("INSERT INTO Reports (reporter_id, reported_user_id, reason, description, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->bind_param("iiss", $reporter_id, $reported_user_id, $reason, $description);
        
        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = 'Failed to submit report. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report User | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>

<main class="container" style="padding: 40px 20px;">
    <div style="max-width: 600px; margin: 0 auto;">
        <?php if ($success): ?>
            <!-- Success Message -->
            <div class="card" style="text-align: center; padding: 60px 40px; background: linear-gradient(135deg, rgba(74,222,128,0.15), rgba(34,197,94,0.1))">
                <div style="font-size: 4rem; margin-bottom: 24px;">✅</div>
                <h2 style="font-size: 1.8rem; font-weight: 700; margin-bottom: 16px; color: #10b981;">
                    Report Submitted Successfully
                </h2>
                <p style="color: var(--muted); font-size: 1.1rem; margin-bottom: 32px; line-height: 1.6;">
                    Thank you for reporting this issue. Our moderation team will review this report and take appropriate action if necessary.
                </p>
                <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
                    <a href="index.php" class="btn" style="padding: 16px 32px;">Back to Dashboard</a>
                    <a href="profile_view.php?id=<?php echo $reported_user_id; ?>" class="btn-ghost" style="padding: 16px 32px;">Back to Profile</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Report Form -->
            <div class="card" style="padding: 40px;">
                <div style="text-align: center; margin-bottom: 32px;">
                    <div style="font-size: 3rem; margin-bottom: 16px;">⚠️</div>
                    <h2 style="font-size: 1.8rem; font-weight: 700; margin-bottom: 8px;">
                        Report User
                    </h2>
                    <p style="color: var(--muted); font-size: 1rem;">
                        Reporting: <strong><?php echo htmlspecialchars($reported_display_name); ?></strong>
                    </p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom: 24px;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" style="display: flex; flex-direction: column; gap: 24px;">
                    <div class="form-group">
                        <label for="reason" style="font-weight: 600; color: var(--text); margin-bottom: 12px; display: block;">
                            Reason for Reporting *
                        </label>
                        <select name="reason" id="reason" required style="width: 100%; padding: 16px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.12); background: rgba(0,0,0,0.15); color: var(--text); font-size: 1rem;">
                            <option value="">Select a reason...</option>
                            <option value="inappropriate_content">Inappropriate Content</option>
                            <option value="harassment">Harassment or Bullying</option>
                            <option value="fake_profile">Fake Profile</option>
                            <option value="spam">Spam or Promotional Content</option>
                            <option value="inappropriate_photos">Inappropriate Photos</option>
                            <option value="underage">Underage User</option>
                            <option value="threats">Threats or Violence</option>
                            <option value="scam">Scam or Fraud</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description" style="font-weight: 600; color: var(--text); margin-bottom: 12px; display: block;">
                            Description *
                        </label>
                        <textarea name="description" id="description" required 
                                  placeholder="Please provide specific details about the issue. Include any relevant information that would help our moderation team understand the situation."
                                  style="width: 100%; min-height: 140px; padding: 16px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.12); background: rgba(0,0,0,0.15); color: var(--text); font-size: 1rem; resize: vertical; line-height: 1.6;"></textarea>
                        <small style="color: var(--muted); font-size: 0.9rem; margin-top: 8px; display: block;">
                            Minimum 20 characters required
                        </small>
                    </div>

                    <div style="background: rgba(251,146,60,0.1); border: 1px solid rgba(251,146,60,0.3); border-radius: 12px; padding: 20px; margin: 16px 0;">
                        <div style="display: flex; align-items: start; gap: 12px;">
                            <span style="font-size: 1.2rem;">ℹ️</span>
                            <div>
                                <h4 style="color: #f59e0b; font-weight: 600; margin-bottom: 8px;">Important Information</h4>
                                <ul style="color: var(--muted); font-size: 0.95rem; line-height: 1.6; margin: 0; padding-left: 20px;">
                                    <li>False reports may result in action against your account</li>
                                    <li>Reports are reviewed by our moderation team within 24-48 hours</li>
                                    <li>All reports are kept confidential</li>
                                    <li>You will not receive direct updates on the report status</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; margin-top: 24px;">
                        <button type="submit" class="btn" style="padding: 16px 32px; background: linear-gradient(135deg, #ef4444, #dc2626);">
                            Submit Report
                        </button>
                        <a href="profile_view.php?id=<?php echo $reported_user_id; ?>" class="btn-ghost" style="padding: 16px 32px;">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>

<a href="profile_view.php?id=<?php echo $reported_user_id; ?>" class="back-btn" title="Back to Profile">←</a>

<?php include_once __DIR__ . '/includes/footer.php'; ?>

<script>
// Form validation
document.querySelector('form')?.addEventListener('submit', function(e) {
    const description = document.getElementById('description').value.trim();
    if (description.length < 20) {
        e.preventDefault();
        alert('Please provide a more detailed description (minimum 20 characters).');
        return false;
    }
});

// Character counter for description
document.getElementById('description')?.addEventListener('input', function() {
    const length = this.value.trim().length;
    const small = this.parentNode.querySelector('small');
    if (length < 20) {
        small.style.color = '#ef4444';
        small.textContent = `${length}/20 characters minimum required`;
    } else {
        small.style.color = 'var(--muted)';
        small.textContent = `${length} characters`;
    }
});
</script>
</body>
</html>