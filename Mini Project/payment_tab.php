<?php
require_once 'freshfold_config.php';
requireLogin();
requireUserType('student');

$database = new Database();
$db = $database->getConnection();
$student_id = $_SESSION['user_id'];
$current_year = date('Y');

// Fetch Razorpay settings
$stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('razorpay_key_id', 'annual_fee_amount')");
$stmt->execute();
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$razorpay_key = $settings['razorpay_key_id'] ?? 'rzp_test_RB1vkBgk5LPRlL';
$fee_amount = $settings['annual_fee_amount'] ?? '8500.00';

// Fetch student details and payment status
$stmt = $db->prepare("SELECT full_name, email, phone, payment_status, payment_year FROM users WHERE user_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

$paid = ($student && $student['payment_status'] === 'Paid' && (int)$student['payment_year'] == (int)$current_year);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_order':
                try {
                    // Create Razorpay order
                    $order_data = [
                        'receipt' => 'FL_' . $student_id . '_' . time(),
                        'amount' => (float)$fee_amount * 100, // Amount in paise
                        'currency' => 'INR',
                        'notes' => [
                            'student_id' => $student_id,
                            'academic_year' => $current_year,
                            'purpose' => 'Annual Laundry Fee'
                        ]
                    ];
                    
                    $razorpay_order = createRazorpayOrder($order_data);
                    
                    if ($razorpay_order) {
                        // Store order details in database
                        $stmt = $db->prepare("INSERT INTO student_payments (student_id, amount, academic_year, razorpay_order_id, payment_status) VALUES (?, ?, ?, ?, 'pending') ON DUPLICATE KEY UPDATE razorpay_order_id = VALUES(razorpay_order_id), payment_status = 'pending'");
                        $stmt->execute([$student_id, $fee_amount, $current_year, $razorpay_order['id']]);
                        
                        echo json_encode([
                            'success' => true,
                            'order_id' => $razorpay_order['id'],
                            'amount' => $razorpay_order['amount'],
                            'currency' => $razorpay_order['currency']
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Failed to create payment order']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => 'Payment initialization failed: ' . $e->getMessage()]);
                }
                break;
                
            case 'verify_payment':
                try {
                    $razorpay_payment_id = $_POST['razorpay_payment_id'] ?? '';
                    $razorpay_order_id = $_POST['razorpay_order_id'] ?? '';
                    $razorpay_signature = $_POST['razorpay_signature'] ?? '';
                    
                    if (verifyRazorpayPayment($razorpay_order_id, $razorpay_payment_id, $razorpay_signature)) {
                        // Update payment status
                        $db->beginTransaction();
                        
                        // Update student_payments table
                        $stmt = $db->prepare("UPDATE student_payments SET payment_status = 'completed', razorpay_payment_id = ?, razorpay_signature = ?, transaction_date = NOW() WHERE student_id = ? AND academic_year = ? AND razorpay_order_id = ?");
                        $stmt->execute([$razorpay_payment_id, $razorpay_signature, $student_id, $current_year, $razorpay_order_id]);
                        
                        // Update users table
                        $stmt = $db->prepare("UPDATE users SET payment_status = 'Paid', payment_year = ? WHERE user_id = ?");
                        $stmt->execute([$current_year, $student_id]);
                        
                        // Add to payments table for record keeping
                        $stmt = $db->prepare("INSERT INTO payments (user_id, amount, payment_method, transaction_id, razorpay_payment_id, razorpay_order_id, razorpay_signature, payment_status, payment_type, academic_year) VALUES (?, ?, 'razorpay', ?, ?, ?, ?, 'completed', 'annual_fee', ?)");
                        $stmt->execute([$student_id, $fee_amount, $razorpay_payment_id, $razorpay_payment_id, $razorpay_order_id, $razorpay_signature, $current_year]);
                        
                        $db->commit();
                        
                        echo json_encode(['success' => true, 'message' => 'Payment verified and recorded successfully']);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Payment verification failed']);
                    }
                } catch (Exception $e) {
                    $db->rollback();
                    echo json_encode(['success' => false, 'error' => 'Payment verification error: ' . $e->getMessage()]);
                }
                break;
        }
    }
    exit;
}

// Razorpay helper functions
function createRazorpayOrder($order_data) {
    $razorpay_secret = '7XkmtPaSZPYnEmCCRIpj7Gem';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
    curl_setopt($ch, CURLOPT_USERPWD, 'rzp_test_RB1vkBgk5LPRlL:' . $razorpay_secret);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        return json_decode($response, true);
    }
    return false;
}

function verifyRazorpayPayment($order_id, $payment_id, $signature) {
    $razorpay_secret = '7XkmtPaSZPYnEmCCRIpj7Gem';
    
    $body = $order_id . "|" . $payment_id;
    $expected_signature = hash_hmac('sha256', $body, $razorpay_secret);
    
    return hash_equals($expected_signature, $signature);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laundry Payment - FreshFold</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #f8f9fa;
            --accent-color: #28a745;
            --gold-accent: #ffd700;
            --success-green: #10b981;
            --warning-red: #ef4444;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            overflow-x: hidden;
            position: relative;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Floating particles background */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 1; }
            50% { transform: translateY(-20px) rotate(180deg); opacity: 0.8; }
        }

        /* Premium sidebar with enhanced glassmorphism */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-right: 1px solid rgba(255, 255, 255, 0.15);
            color: white;
            padding: 20px 0;
            z-index: 1000;
            overflow-y: auto;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.1);
        }

        .sidebar:hover {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
        }

        .sidebar .brand {
            text-align: center;
            padding: 0 20px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            margin-bottom: 30px;
            position: relative;
        }

        .sidebar .brand i {
            filter: drop-shadow(0 0 15px rgba(255, 255, 255, 0.4));
            transition: all 0.3s ease;
        }

        .sidebar .brand:hover i {
            filter: drop-shadow(0 0 20px rgba(255, 255, 255, 0.6));
            transform: scale(1.08);
        }

        .sidebar .brand h4 {
            margin: 15px 0 8px 0;
            font-weight: 700;
            font-size: 1.4rem;
            background: linear-gradient(135deg, #fff 0%, #f0f0f0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 0.5px;
        }

        .sidebar .brand small {
            opacity: 0.8;
            font-weight: 400;
            letter-spacing: 0.3px;
        }

        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 15px 20px;
            border: none;
            display: flex;
            align-items: center;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            text-decoration: none;
            margin: 3px 0;
            position: relative;
            overflow: hidden;
            border-radius: 0 20px 20px 0;
            font-weight: 500;
        }

        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            left: -100%;
            top: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.6s ease;
        }

        .sidebar .nav-link:hover::before {
            left: 100%;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.18);
            margin-right: 15px;
            transform: translateX(8px) scale(1.02);
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
        }

        .sidebar .nav-link i {
            width: 22px;
            margin-right: 12px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover i {
            transform: scale(1.15);
        }

        .sidebar hr {
            border-color: rgba(255,255,255,0.15);
            margin: 25px 15px;
        }

        /* Main content area */
        .main-content {
            margin-left: 250px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            position: relative;
            z-index: 10;
        }

        /* Premium payment container */
        .payment-container {
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 20;
        }

        /* Enhanced payment card with stronger contrast */
        .payment-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 28px;
            padding: 48px 40px;
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.15),
                0 8px 20px rgba(0, 0, 0, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.3),
                inset 0 -1px 0 rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        .payment-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
            z-index: -1;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .payment-card:hover {
            transform: translateY(-4px);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.2),
                0 12px 25px rgba(0, 0, 0, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.4);
        }

        /* Header section */
        .payment-header {
            text-align: center;
            margin-bottom: 36px;
        }

        .payment-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 12px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
            letter-spacing: -0.5px;
        }

        .payment-header .subtitle {
            color: rgba(255, 255, 255, 0.95);
            font-size: 1.05rem;
            font-weight: 400;
            letter-spacing: 0.3px;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
        }

        /* Academic year badge with clean styling */
        .academic-year-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            padding: 12px 20px;
            margin-bottom: 24px;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .academic-year-badge:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.02);
        }

        /* Amount display with elegant styling */
        .amount-display {
            text-align: center;
            margin-bottom: 32px;
            padding: 24px;
            background: rgba(255, 255, 255, 0.18);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .amount-display:hover {
            background: rgba(255, 255, 255, 0.22);
            transform: scale(1.01);
        }

        .amount-value {
            font-size: 3rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 8px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
            letter-spacing: -1px;
        }

        .amount-currency {
            font-size: 2.2rem;
            opacity: 0.95;
        }

        .amount-label {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            font-weight: 500;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        /* Status display with refined elegance */
        .status-display {
            margin-bottom: 32px;
            text-align: center;
        }

        .status-card {
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .status-paid {
            background: linear-gradient(135deg, 
                rgba(16, 185, 129, 0.2) 0%, 
                rgba(5, 150, 105, 0.15) 100%);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .status-unpaid {
            background: linear-gradient(135deg, 
                rgba(239, 68, 68, 0.2) 0%, 
                rgba(220, 38, 38, 0.15) 100%);
            border-color: rgba(239, 68, 68, 0.3);
        }

        .status-icon {
            font-size: 3rem;
            margin-bottom: 16px;
            transition: all 0.3s ease;
        }

        .status-paid .status-icon {
            color: #34d399;
            filter: drop-shadow(0 0 15px rgba(52, 211, 153, 0.4));
        }

        .status-unpaid .status-icon {
            color: #f87171;
            filter: drop-shadow(0 0 15px rgba(248, 113, 113, 0.4));
        }

        .status-text {
            font-size: 1.4rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 8px;
            text-shadow: 0 2px 6px rgba(0, 0, 0, 0.4);
        }

        .status-subtext {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            font-weight: 500;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        /* Payment success details with clean design */
        .payment-success-details {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 16px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.1);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
        }

        .success-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.25);
            border: 1px solid rgba(16, 185, 129, 0.4);
            border-radius: 25px;
            padding: 10px 18px;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 12px;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        /* Premium payment button */
        .btn-pay {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 18px 0;
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            margin-bottom: 20px;
        }

        .btn-pay::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s;
        }

        .btn-pay:hover::before {
            left: 100%;
        }

        .btn-pay:hover {
            transform: translateY(-2px) scale(1.01);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }

        .btn-pay:active {
            transform: translateY(0) scale(0.98);
        }

        .btn-pay:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Payment methods */
        .payment-methods {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin: 24px 0;
            opacity: 0.8;
        }

        .payment-method-logo {
            height: 32px;
            filter: grayscale(1) brightness(2);
            transition: all 0.3s ease;
        }

        .payment-method-logo:hover {
            filter: none;
            transform: scale(1.1);
        }

        /* Security badge */
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: rgba(255, 255, 255, 0.95);
            font-size: 0.95rem;
            font-weight: 500;
            margin-top: 20px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .security-icon {
            color: var(--success-green);
            filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.3));
        }

        /* Loading spinner */
        .loading-spinner {
            display: none;
            text-align: center;
            margin: 24px 0;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(255, 255, 255, 0.2);
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }

        /* Back button */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 12px 20px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            text-decoration: none;
            transform: translateX(-2px);
        }

        /* Alert messages */
        .alert-custom {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 16px 20px;
            color: white;
            margin-top: 20px;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.15);
            border-color: rgba(239, 68, 68, 0.3);
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .payment-card {
                padding: 32px 24px;
                border-radius: 20px;
            }

            .payment-header h1 {
                font-size: 1.8rem;
            }

            .amount-value {
                font-size: 2.4rem;
            }

            .amount-currency {
                font-size: 1.8rem;
            }
        }

        /* Particle positioning for better distribution matching dashboard */
        .particle:nth-child(1) { top: 10%; left: 10%; animation-delay: -1s; }
        .particle:nth-child(2) { top: 20%; left: 80%; animation-delay: -2s; }
        .particle:nth-child(3) { top: 40%; left: 30%; animation-delay: -3s; }
        .particle:nth-child(4) { top: 60%; left: 70%; animation-delay: -4s; }
        .particle:nth-child(5) { top: 80%; left: 20%; animation-delay: -5s; }
        .particle:nth-child(6) { top: 30%; left: 90%; animation-delay: -6s; }
        .particle:nth-child(7) { top: 70%; left: 10%; animation-delay: -7s; }
        .particle:nth-child(8) { top: 50%; left: 50%; animation-delay: -8s; }
    </style>
</head>
<body>

<!-- Floating Particles Background -->
<div class="particles">
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
</div>

<!-- Enhanced Sidebar -->
<div class="sidebar">
    <div class="brand">
        <i class="fas fa-tshirt fa-2x mb-3"></i>
        <h4>FreshFold</h4>
        <small>Laundry Management</small>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link" href="dashboard_page.php">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a class="nav-link" href="new_request_page.php">
            <i class="fas fa-plus-circle"></i> New Request
        </a>
        <a class="nav-link" href="my_requests_page.php">
            <i class="fas fa-list"></i> My Requests
        </a>
        <a class="nav-link" href="issue_report_page.php">
            <i class="fas fa-exclamation-triangle"></i> Report Issue
        </a>
        <a class="nav-link active" href="payment_tab.php">
            <i class="fas fa-credit-card"></i> Payment
        </a>
        <a class="nav-link" href="profile_page.php">
            <i class="fas fa-user"></i> Profile
        </a>
        <hr>
        <a class="nav-link" href="logout.php" onclick="return confirm('Are you sure you want to logout?')">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="payment-container">
        <div class="payment-card">
            <!-- Payment Header -->
            <div class="payment-header">
                <h1><i class="fas fa-credit-card me-3"></i>Payment Portal</h1>
                <p class="subtitle">Secure Annual Laundry Service Payment</p>
            </div>

            <!-- Academic Year Badge -->
            <div class="text-center">
                <div class="academic-year-badge">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Academic Year: <?php echo $current_year; ?></span>
                </div>
            </div>

            <!-- Amount Display -->
            <div class="amount-display">
                <div class="amount-value">
                    <span class="amount-currency">₹</span><?php echo number_format($fee_amount, 0); ?><span style="font-size: 1.8rem; opacity: 0.8;">.00</span>
                </div>
                <div class="amount-label">Annual Laundry Service Fee</div>
            </div>

            <!-- Payment Status -->
            <div class="status-display">
                <?php if ($paid): ?>
                <!-- For Paid Status -->
                <div class="status-card status-paid">
                    <div class="status-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="status-text">Payment Completed</div>
                    <div class="status-subtext">Your annual fee has been successfully paid</div>
                    
                    <div class="payment-success-details">
                        <div class="success-badge">
                            <i class="fas fa-shield-check"></i>
                            <span>Verified Payment for <?php echo $student['payment_year']; ?></span>
                        </div>
                        <div style="color: rgba(255, 255, 255, 0.9); font-size: 0.9rem;">
                            You're all set for this academic year!
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- For Unpaid Status -->
                <div class="status-card status-unpaid">
                    <div class="status-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="status-text">Payment Required</div>
                    <div class="status-subtext">Complete your annual laundry service payment</div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!$paid): ?>
            <!-- Payment Button and Methods -->
            <div id="paymentSection">
                <!-- Payment Methods Display -->
                <div class="payment-methods">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/8/89/Razorpay_logo.svg" alt="Razorpay" class="payment-method-logo" title="Powered by Razorpay">
                </div>

                <!-- Premium Payment Button -->
                <button type="button" id="payBtn" class="btn btn-pay">
                    <i class="fas fa-lock me-2"></i>
                    Pay Securely with Razorpay
                    <i class="fas fa-arrow-right ms-2"></i>
                </button>

                <!-- Security Badge -->
                <div class="security-badge">
                    <i class="fas fa-shield-alt security-icon"></i>
                    <span>256-bit SSL Encrypted • Bank-grade Security</span>
                </div>
            </div>

            <!-- Loading Spinner -->
            <div class="loading-spinner" id="loadingSpinner">
                <div class="spinner"></div>
                <div class="loading-text">Processing your payment...</div>
            </div>

            <!-- Payment Messages -->
            <div id="paymentMsg"></div>
            <?php else: ?>
            <!-- Back to Dashboard -->
            <div class="text-center mt-4">
                <a href="dashboard_page.php" class="btn-back">
                    <i class="fas fa-home"></i>
                    <span>Back to Dashboard</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Razorpay Checkout Script -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const payBtn = document.getElementById('payBtn');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const paymentMsg = document.getElementById('paymentMsg');
    
    if (payBtn) {
        payBtn.addEventListener('click', function() {
            initiatePayment();
        });
    }
    
    function showMessage(message, type = 'info') {
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'error' ? 'alert-danger' : 'alert-info';
        paymentMsg.innerHTML = `<div class="alert-custom ${alertClass}">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 
                          type === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle'} me-2"></i>
            ${message}
        </div>`;
    }
    
    function showLoading(show = true) {
        if (show) {
            payBtn.style.display = 'none';
            loadingSpinner.style.display = 'block';
        } else {
            payBtn.style.display = 'block';
            loadingSpinner.style.display = 'none';
        }
    }
    
    function initiatePayment() {
        showLoading(true);
        showMessage('');
        
        // Create Razorpay order
        fetch('payment_tab.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=create_order'
        })
        .then(response => response.json())
        .then(data => {
            showLoading(false);
            
            if (data.success) {
                openRazorpayCheckout(data);
            } else {
                showMessage('Failed to initialize payment: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            showLoading(false);
            showMessage('Network error. Please check your connection and try again.', 'error');
            console.error('Payment initialization error:', error);
        });
    }
    
    function openRazorpayCheckout(orderData) {
        const options = {
            "key": "<?php echo $razorpay_key; ?>",
            "amount": orderData.amount,
            "currency": orderData.currency,
            "name": "FreshFold Laundry",
            "description": "Annual Laundry Fee - Academic Year <?php echo $current_year; ?>",
            "image": "https://i.imgur.com/n5tjHFD.png",
            "order_id": orderData.order_id,
            "prefill": {
                "name": "<?php echo htmlspecialchars($student['full_name'] ?? ''); ?>",
                "email": "<?php echo htmlspecialchars($student['email'] ?? ''); ?>",
                "contact": "<?php echo htmlspecialchars($student['phone'] ?? ''); ?>"
            },
            "notes": {
                "student_id": "<?php echo $student_id; ?>",
                "academic_year": "<?php echo $current_year; ?>"
            },
            "theme": {
                "color": "#667eea"
            },
            "method": {
                "netbanking": true,
                "card": true,
                "upi": true,
                "wallet": true
            },
            "handler": function(response) {
                verifyPayment(response);
            },
            "modal": {
                "ondismiss": function() {
                    showMessage('Payment cancelled by user.', 'info');
                }
            }
        };
        
        const razorpayInstance = new Razorpay(options);
        razorpayInstance.on('payment.failed', function(response) {
            showMessage('Payment failed: ' + response.error.description, 'error');
        });
        
        razorpayInstance.open();
    }
    
    function verifyPayment(response) {
        showLoading(true);
        
        const formData = new FormData();
        formData.append('action', 'verify_payment');
        formData.append('razorpay_payment_id', response.razorpay_payment_id);
        formData.append('razorpay_order_id', response.razorpay_order_id);
        formData.append('razorpay_signature', response.razorpay_signature);
        
        fetch('payment_tab.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showLoading(false);
            
            if (data.success) {
                showMessage('Payment successful! Redirecting...', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showMessage('Payment verification failed: ' + (data.error || 'Please contact support'), 'error');
            }
        })
        .catch(error => {
            showLoading(false);
            showMessage('Verification failed. Please contact support with payment ID: ' + response.razorpay_payment_id, 'error');
            console.error('Payment verification error:', error);
        });
    }
});

// Mobile sidebar toggle
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.style.transform = sidebar.style.transform === 'translateX(0px)' ? 'translateX(-100%)' : 'translateX(0px)';
}

// Add mobile menu button if on mobile
if (window.innerWidth <= 768) {
    const mobileToggle = document.createElement('button');
    mobileToggle.innerHTML = '<i class="fas fa-bars"></i>';
    mobileToggle.className = 'btn btn-primary position-fixed';
    mobileToggle.style.cssText = 'top: 20px; left: 20px; z-index: 1001; border-radius: 50%; width: 50px; height: 50px; background: rgba(255,255,255,0.2); backdrop-filter: blur(15px); border: 1px solid rgba(255,255,255,0.3); color: white;';
    mobileToggle.onclick = toggleSidebar;
    document.body.appendChild(mobileToggle);
}
</script>
</body>
</html>