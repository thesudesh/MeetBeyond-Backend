<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get plan details from URL
$plan_type = $_GET['plan'] ?? '';
$duration = intval($_GET['duration'] ?? 1);

$valid_plans = ['boost_2x', 'boost_5x', 'boost_10x'];

if (!in_array($plan_type, $valid_plans)) {
    header('Location: subscription.php');
    exit;
}

// Plan pricing
$pricing = [
    'boost_2x' => 9.99,
    'boost_5x' => 19.99,
    'boost_10x' => 39.99
];

$plan_names = [
    'boost_2x' => '2x Boost',
    'boost_5x' => '5x Boost',
    'boost_10x' => '10x Boost'
];

$plan_icons = [
    'boost_2x' => '🚀',
    'boost_5x' => '⚡',
    'boost_10x' => '💎'
];

$base_price = $pricing[$plan_type];
$total_price = $base_price * $duration;

// Apply discounts for longer durations
$discount = 0;
if ($duration == 3) {
    $discount = 0.10; // 10% off for 3 months
} elseif ($duration >= 6) {
    $discount = 0.20; // 20% off for 6+ months
}

$discount_amount = $total_price * $discount;
$final_price = $total_price - $discount_amount;

// Handle payment submission
if ($_POST['action'] ?? '' === 'process_payment') {
    // Simulate payment processing
    sleep(2); // Simulate processing time
    
    $payment_reference = 'MB_' . strtoupper($plan_type) . '_' . time() . '_' . $user_id;
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+{$duration} months"));
    
    try {
        // Cancel any existing active subscription
        $stmt = $conn->prepare("UPDATE Subscriptions SET end_date = CURDATE() WHERE user_id = ? AND end_date > CURDATE()");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Insert new subscription
        $stmt = $conn->prepare("
            INSERT INTO Subscriptions (user_id, plan_type, payment_reference, start_date, end_date) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issss", $user_id, $plan_type, $payment_reference, $start_date, $end_date);
        $stmt->execute();
        $stmt->close();
        
        // Log the subscription
        $stmt = $conn->prepare("
            INSERT INTO Logs (user_id, action, details) 
            VALUES (?, 'subscription_purchase', ?)
        ");
        $log_details = json_encode([
            'plan_type' => $plan_type,
            'duration_months' => $duration,
            'payment_reference' => $payment_reference,
            'amount_paid' => $final_price
        ]);
        $stmt->bind_param("is", $user_id, $log_details);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success_message'] = "Payment successful! Welcome to Premium! 🎉";
        header('Location: profile_view.php');
        exit;
        
    } catch (Exception $e) {
        $error_message = "Payment failed. Please try again.";
    }
}

$page_title = "Secure Payment";
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
        .payment-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .payment-header {
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));
            padding: 40px 30px;
            text-align: center;
            border-radius: 16px;
            margin-bottom: 30px;
        }
        
        .payment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .order-summary {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 30px;
        }
        
        .payment-form {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 30px;
        }
        
        .plan-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: #1f2937;
            padding: 12px 20px;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .price-breakdown {
            margin: 20px 0;
            padding: 20px 0;
            border-top: 1px solid rgba(255,255,255,0.1);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .total-price {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--accent-purple);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px;
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            background: rgba(255,255,255,0.05);
            color: var(--text);
            font-size: 1rem;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--accent-purple);
            box-shadow: 0 0 0 3px rgba(167,139,250,0.1);
        }
        
        .payment-grid-2 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
        }
        
        .btn-pay {
            width: 100%;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            border: none;
            padding: 18px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(34,197,94,0.3);
        }
        
        .btn-pay:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .security-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 0.9rem;
            margin-top: 16px;
        }
        
        .discount-badge {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .payment-grid {
                grid-template-columns: 1fr;
            }
            
            .payment-grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="payment-container">
        <div class="payment-header">
            <h1 style="font-size: 2.5rem; margin-bottom: 12px;">🔒 Secure Payment</h1>
            <p style="opacity: 0.9; font-size: 1.1rem;">Complete your premium upgrade securely</p>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="payment-grid">
            <!-- Order Summary -->
            <div class="order-summary">
                <h3 style="margin-bottom: 20px; font-size: 1.3rem;">Order Summary</h3>
                
                <div class="plan-badge">
                    <span style="font-size: 1.2rem;"><?php echo $plan_icons[$plan_type]; ?></span>
                    <?php echo $plan_names[$plan_type]; ?> Plan
                </div>
                
                <div style="margin-bottom: 20px;">
                    <strong>Duration:</strong> <?php echo $duration; ?> month<?php echo $duration > 1 ? 's' : ''; ?>
                    <?php if ($discount > 0): ?>
                        <span class="discount-badge">Save <?php echo intval($discount * 100); ?>%</span>
                    <?php endif; ?>
                </div>
                
                <div class="price-breakdown">
                    <div class="price-row">
                        <span>Base price:</span>
                        <span>$<?php echo number_format($base_price, 2); ?>/month</span>
                    </div>
                    <div class="price-row">
                        <span>Duration:</span>
                        <span><?php echo $duration; ?> month<?php echo $duration > 1 ? 's' : ''; ?></span>
                    </div>
                    <div class="price-row">
                        <span>Subtotal:</span>
                        <span>$<?php echo number_format($total_price, 2); ?></span>
                    </div>
                    <?php if ($discount > 0): ?>
                        <div class="price-row" style="color: #22c55e;">
                            <span>Discount (<?php echo intval($discount * 100); ?>%):</span>
                            <span>-$<?php echo number_format($discount_amount, 2); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="price-row total-price" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.1);">
                        <span>Total:</span>
                        <span>$<?php echo number_format($final_price, 2); ?></span>
                    </div>
                </div>
                
                <div style="background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); border-radius: 8px; padding: 16px; margin-top: 20px;">
                    <h4 style="margin-bottom: 8px; color: #22c55e;">✨ You'll get instant access to:</h4>
                    <ul style="margin: 0; padding-left: 20px; color: var(--muted);">
                        <li>Enhanced profile visibility</li>
                        <li>Priority in match suggestions</li>
                        <li>See who liked your profile</li>
                        <li>Unlimited likes and features</li>
                    </ul>
                </div>
            </div>
            
            <!-- Payment Form -->
            <div class="payment-form">
                <h3 style="margin-bottom: 20px; font-size: 1.3rem;">Payment Details</h3>
                
                <form method="post" id="paymentForm">
                    <input type="hidden" name="action" value="process_payment">
                    
                    <div class="form-group">
                        <label for="card_number">Card Number</label>
                        <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" required>
                    </div>
                    
                    <div class="payment-grid-2">
                        <div class="form-group">
                            <label for="expiry">Expiry Date</label>
                            <input type="text" id="expiry" name="expiry" placeholder="MM/YY" maxlength="5" required>
                        </div>
                        <div class="form-group">
                            <label for="cvv">CVV</label>
                            <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="4" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="card_name">Cardholder Name</label>
                        <input type="text" id="card_name" name="card_name" placeholder="John Doe" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="billing_email">Billing Email</label>
                        <input type="email" id="billing_email" name="billing_email" placeholder="john@example.com" required>
                    </div>
                    
                    <button type="submit" class="btn-pay" id="payButton">
                        🔐 Complete Payment - $<?php echo number_format($final_price, 2); ?>
                    </button>
                    
                    <div class="security-badge">
                        <span>🔒</span>
                        <span>Your payment information is encrypted and secure</span>
                    </div>
                </form>
            </div>
        </div>
        
        <div style="text-align: center; padding: 30px; background: rgba(255,255,255,0.05); border-radius: 12px;">
            <p style="color: var(--muted); margin: 0;">
                <strong>Note:</strong> This is a demo payment system. No actual charges will be made.
                In a real application, this would integrate with payment processors like Stripe or PayPal.
            </p>
        </div>
    </div>
    
    <script>
        // Format card number input
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
            e.target.value = value;
        });
        
        // Format expiry date
        document.getElementById('expiry').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0,2) + '/' + value.substring(2,4);
            }
            e.target.value = value;
        });
        
        // Only allow numbers for CVV
        document.getElementById('cvv').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
        
        // Handle form submission with loading state
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const payButton = document.getElementById('payButton');
            payButton.disabled = true;
            payButton.innerHTML = '⏳ Processing Payment...';
        });
    </script>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>