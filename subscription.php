<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Check current subscription status
$stmt = $conn->prepare("
    SELECT s.*, u.email, p.name 
    FROM Subscriptions s 
    JOIN Users u ON s.user_id = u.id 
    LEFT JOIN Profiles p ON u.id = p.user_id 
    WHERE s.user_id = ? AND s.end_date > CURDATE() 
    ORDER BY s.end_date DESC LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_subscription = $result->fetch_assoc();
$stmt->close();

// Get user profile for name display
$stmt = $conn->prepare("SELECT name FROM Profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$user_name = $profile ? $profile['name'] : 'User';
$stmt->close();

$page_title = "Premium Subscriptions";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo $page_title; ?> | Meet Beyond</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <style>
        .subscription-hero {
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));
            padding: 60px 20px;
            text-align: center;
            margin-bottom: 40px;
        }
        
        .subscription-hero h1 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 16px;
            text-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .subscription-hero p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .subscription-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .subscription-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 32px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .subscription-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            border-color: rgba(255,255,255,0.2);
        }
        
        .subscription-card.featured {
            border-color: #fbbf24;
            background: linear-gradient(135deg, rgba(251,191,36,0.1), rgba(245,158,11,0.05));
        }
        
        .subscription-card.featured::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #fbbf24, #f59e0b);
        }
        
        .plan-header {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .plan-icon {
            font-size: 3rem;
            margin-bottom: 16px;
            display: block;
        }
        
        .plan-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .plan-description {
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .plan-pricing {
            text-align: center;
            margin: 24px 0;
        }
        
        .plan-price {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--accent-purple);
        }
        
        .plan-period {
            opacity: 0.7;
            font-size: 0.9rem;
        }
        
        .plan-features {
            list-style: none;
            padding: 0;
            margin: 24px 0;
        }
        
        .plan-features li {
            padding: 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .plan-features li::before {
            content: '✨';
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        
        .current-subscription {
            background: linear-gradient(135deg, rgba(34,197,94,0.15), rgba(22,163,74,0.1));
            border-color: rgba(34,197,94,0.3);
            margin-bottom: 40px;
        }
        
        .current-subscription::before {
            background: linear-gradient(90deg, #22c55e, #16a34a);
        }
        
        .subscription-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        
        .btn-subscribe {
            flex: 1;
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-subscribe:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(139,92,246,0.3);
        }
        
        .btn-cancel {
            background: rgba(239,68,68,0.1);
            color: #fca5a5;
            border: 2px solid #ef4444;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background: #ef4444;
            color: white;
        }
        
        .duration-selector {
            margin-bottom: 20px;
        }
        
        .duration-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 8px;
        }
        
        .duration-option {
            display: none;
        }
        
        .duration-label {
            display: block;
            padding: 12px;
            background: rgba(255,255,255,0.05);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .duration-option:checked + .duration-label {
            background: var(--accent-purple);
            border-color: var(--accent-purple);
            color: white;
        }
        
        .boost-multiplier {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: #1f2937;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="subscription-hero">
        <h1>✨ Premium Features</h1>
        <p>Boost your profile visibility and get more matches with our premium subscription plans</p>
        <?php if (isset($_GET['feature']) && $_GET['feature'] === 'who_liked'): ?>
            <div style="background: rgba(251,191,36,0.2); border: 2px solid #fbbf24; border-radius: 12px; padding: 16px; margin-top: 20px; max-width: 500px; margin-left: auto; margin-right: auto;">
                <p style="margin: 0; font-weight: 600; color: #fbbf24;">
                    🔒 "Who Liked Me" is a premium feature. Subscribe to see everyone who liked your profile!
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($current_subscription): ?>
            <div class="subscription-card current-subscription">
                <div class="plan-header">
                    <span class="plan-icon">👑</span>
                    <h3 class="plan-name">Current Subscription</h3>
                    <p class="plan-description">
                        <?php echo ucfirst(str_replace('_', ' ', $current_subscription['plan_type'])); ?> Plan
                    </p>
                </div>
                
                <div class="plan-pricing">
                    <div style="font-size: 1.2rem; margin-bottom: 8px;">
                        Active until: <strong><?php echo date('F j, Y', strtotime($current_subscription['end_date'])); ?></strong>
                    </div>
                    <div style="opacity: 0.7; font-size: 0.9rem;">
                        Payment Reference: <?php echo htmlspecialchars($current_subscription['payment_reference']); ?>
                    </div>
                </div>
                
                <div class="subscription-actions">
                    <form method="post" style="flex: 1;">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn-cancel" onclick="return confirm('Are you sure you want to cancel your subscription?')">
                            Cancel Subscription
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="subscription-grid">
            <!-- 2x Boost Plan -->
            <div class="subscription-card">
                <div class="plan-header">
                    <span class="plan-icon">🚀</span>
                    <h3 class="plan-name">
                        Boost 2x
                        <span class="boost-multiplier">2x visibility</span>
                    </h3>
                    <p class="plan-description">Double your profile visibility and get noticed faster</p>
                </div>
                
                <div class="plan-pricing">
                    <div class="plan-price">$9.99</div>
                    <div class="plan-period">per month</div>
                </div>
                
                <ul class="plan-features">
                    <li>2x profile visibility in discovery</li>
                    <li>Priority in match suggestions</li>
                    <li>See who liked your profile</li>
                    <li>Unlimited likes per day</li>
                    <li>Advanced filtering options</li>
                </ul>
                
                <form onsubmit="redirectToPayment('boost_2x', this); return false;">
                    <div class="duration-selector">
                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Duration:</label>
                        <div class="duration-options">
                            <input type="radio" id="boost2x_1m" name="duration" value="1" class="duration-option" checked>
                            <label for="boost2x_1m" class="duration-label">1 Month</label>
                            
                            <input type="radio" id="boost2x_3m" name="duration" value="3" class="duration-option">
                            <label for="boost2x_3m" class="duration-label">3 Months</label>
                            
                            <input type="radio" id="boost2x_6m" name="duration" value="6" class="duration-option">
                            <label for="boost2x_6m" class="duration-label">6 Months</label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-subscribe">Get 2x Boost</button>
                </form>
            </div>
            
            <!-- 5x Boost Plan -->
            <div class="subscription-card featured">
                <div class="plan-header">
                    <span class="plan-icon">⚡</span>
                    <h3 class="plan-name">
                        Boost 5x
                        <span class="boost-multiplier">5x visibility</span>
                    </h3>
                    <p class="plan-description">Most popular! Maximum exposure for serious daters</p>
                </div>
                
                <div class="plan-pricing">
                    <div class="plan-price">$19.99</div>
                    <div class="plan-period">per month</div>
                </div>
                
                <ul class="plan-features">
                    <li>5x profile visibility in discovery</li>
                    <li>Top priority in all recommendations</li>
                    <li>See who liked your profile</li>
                    <li>Unlimited likes and super likes</li>
                    <li>Advanced search and filters</li>
                    <li>Read receipts for messages</li>
                    <li>Boost your profile weekly</li>
                </ul>
                
                <form onsubmit="redirectToPayment('boost_5x', this); return false;">
                    <div class="duration-selector">
                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Duration:</label>
                        <div class="duration-options">
                            <input type="radio" id="boost5x_1m" name="duration" value="1" class="duration-option" checked>
                            <label for="boost5x_1m" class="duration-label">1 Month</label>
                            
                            <input type="radio" id="boost5x_3m" name="duration" value="3" class="duration-option">
                            <label for="boost5x_3m" class="duration-label">3 Months</label>
                            
                            <input type="radio" id="boost5x_6m" name="duration" value="6" class="duration-option">
                            <label for="boost5x_6m" class="duration-label">6 Months</label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-subscribe">Get 5x Boost</button>
                </form>
            </div>
            
            <!-- 10x Boost Plan -->
            <div class="subscription-card">
                <div class="plan-header">
                    <span class="plan-icon">💎</span>
                    <h3 class="plan-name">
                        Boost 10x
                        <span class="boost-multiplier">10x visibility</span>
                    </h3>
                    <p class="plan-description">Ultimate visibility for the most serious users</p>
                </div>
                
                <div class="plan-pricing">
                    <div class="plan-price">$39.99</div>
                    <div class="plan-period">per month</div>
                </div>
                
                <ul class="plan-features">
                    <li>10x profile visibility in discovery</li>
                    <li>Premium placement in all feeds</li>
                    <li>See who liked your profile</li>
                    <li>Unlimited everything</li>
                    <li>Advanced search and filters</li>
                    <li>Read receipts and typing indicators</li>
                    <li>Daily profile boosts</li>
                    <li>VIP customer support</li>
                    <li>Exclusive premium events access</li>
                </ul>
                
                <form onsubmit="redirectToPayment('boost_10x', this); return false;">
                    <div class="duration-selector">
                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Duration:</label>
                        <div class="duration-options">
                            <input type="radio" id="boost10x_1m" name="duration" value="1" class="duration-option" checked>
                            <label for="boost10x_1m" class="duration-label">1 Month</label>
                            
                            <input type="radio" id="boost10x_3m" name="duration" value="3" class="duration-option">
                            <label for="boost10x_3m" class="duration-label">3 Months</label>
                            
                            <input type="radio" id="boost10x_6m" name="duration" value="6" class="duration-option">
                            <label for="boost10x_6m" class="duration-label">6 Months</label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-subscribe">Get 10x Boost</button>
                </form>
            </div>
        </div>
        
        <div style="text-align: center; margin: 60px 0; padding: 40px; background: rgba(255,255,255,0.05); border-radius: 16px;">
            <h3 style="margin-bottom: 16px;">Why Choose Premium?</h3>
            <p style="opacity: 0.8; max-width: 600px; margin: 0 auto;">
                Premium subscriptions increase your profile visibility dramatically. Our algorithm prioritizes premium users 
                in discovery feeds, match suggestions, and search results. Get more matches, more conversations, 
                and find your perfect match faster!
            </p>
        </div>
    </div>
    
    <script>
        function redirectToPayment(planType, form) {
            const duration = form.querySelector('input[name="duration"]:checked').value;
            window.location.href = `payment.php?plan=${planType}&duration=${duration}`;
        }
    </script>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>