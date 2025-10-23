<?php
require_once 'freshfold_config.php';
requireLogin();
requireUserType('student');

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$current_user = $user->getUserById($_SESSION['user_id']);
$gender = $current_user['gender'] ?? 'male';

// CRITICAL: Check payment status before allowing access
$current_year = date('Y');
$stmt = $db->prepare("SELECT payment_status, payment_year FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$payment_info = $stmt->fetch(PDO::FETCH_ASSOC);
$is_paid = ($payment_info && $payment_info['payment_status'] === 'Paid' && (int)$payment_info['payment_year'] == (int)$current_year);

// If not paid, show the payment required modal (handled in JavaScript)
$show_payment_modal = !$is_paid;

$error = '';
$success = '';

$boy_items = [
    "Shirt", "Pants", "Jeans", "T. Shirts", "Play Pant", "Bermuda", "Inner (Ban)", "Bedsheet", "Blanket", "Lunkey", "Over Coat", "Thorth", "Turkey", "Pillow", "Sweater"
];
$girl_items = [
    "Churidar Top", "Churidar Pant", "Churidar Shalls", "Pants", "Shirts", "T. Shirts", "Over Coat", "Top", "Play Pant", "Bermuda", "Saree", "Midi", "Turkey", "Thorth", "Sweates", "Bedsheet", "Blanket", "Pillow", "Shimmies"
];

function getPickupDate($db) {
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) FROM laundry_requests WHERE created_at >= ? AND created_at < ?");
    $stmt->execute([
        $today . " 00:00:00",
        $today . " 23:59:59"
    ]);
    $today_requests = $stmt->fetchColumn();
    $days_to_add = ($today_requests >= 30) ? 4 : 3;
    $pickup_date = date('Y-m-d', strtotime("+$days_to_add days"));
    while (date('w', strtotime($pickup_date)) == 0) {
        $pickup_date = date('Y-m-d', strtotime($pickup_date . ' +1 day'));
    }
    return $pickup_date;
}

$calculated_pickup_date = getPickupDate($db);

// Handle form submission - ONLY if payment is confirmed
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Double-check payment status on submission
    if (!$is_paid) {
        $error = 'Payment required. Please complete your annual fee payment before submitting a request.';
    } else {
        $pickup_date = $_POST['pickup_date'];
        $special_instructions = $_POST['special_instructions'] ?? '';
        $items = $_POST['items'] ?? [];
        $total_items = array_sum($items);

        if($total_items > 20) {
            $error = 'You cannot submit more than 20 clothes in a single request.';
        } elseif($total_items < 1) {
            $error = 'Please enter at least one item.';
        } else {
            $laundryRequest = new LaundryRequest($db);
            $request_id = $laundryRequest->createRequest($_SESSION['user_id'], $pickup_date, $special_instructions);

            if($request_id) {
                $item_names = ($gender === 'female') ? $girl_items : $boy_items;
                foreach($items as $idx => $qty) {
                    $qty = intval($qty);
                    if($qty > 0 && isset($item_names[$idx])) {
                        $stmt = $db->prepare("INSERT INTO laundry_items (request_id, item_type, quantity) VALUES (?, ?, ?)");
                        $stmt->execute([$request_id, $item_names[$idx], $qty]);
                    }
                }
                showAlert('Laundry request created successfully! Your request ID is #' . $request_id, 'success');
                redirect('my_requests_page.php');
            } else {
                $error = 'Failed to create request. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Laundry Request - FreshFold</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #f8f9fa;
            --accent-color: #28a745;
            --sidebar-width: 250px;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, var(--primary-color) 0%, #1e3d6f 100%);
        }
        body {
            background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
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
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 20px 0;
            z-index: 1000;
            overflow-y: auto;
            transform: translateX(0);
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        .sidebar:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        .sidebar .brand {
            text-align: center;
            padding: 0 20px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 30px;
            animation: slideInFromTop 0.8s ease-out;
            position: relative;
        }
        .sidebar .brand i {
            position: relative;
            display: inline-block;
            filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.3));
            transition: all 0.3s ease;
        }
        .sidebar .brand:hover i {
            filter: drop-shadow(0 0 15px rgba(255, 255, 255, 0.5));
            transform: scale(1.05);
        }
        @keyframes slideInFromTop {
            0% { transform: translateY(-50px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }
        .sidebar .brand h4 {
            margin: 10px 0 5px 0;
            font-weight: 700;
            background: linear-gradient(45deg, #fff, #f0f0f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
        }
        .sidebar .brand h4::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            width: 0;
            height: 2px;
            background: linear-gradient(45deg, #fff, #f0f0f0);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        .sidebar .brand:hover h4::after {
            width: 100%;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border: none;
            display: flex;
            align-items: center;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            text-decoration: none;
            margin: 2px 0;
            position: relative;
            overflow: hidden;
        }
        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            left: -100%;
            top: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s;
        }
        .sidebar .nav-link:hover::before {
            left: 100%;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.2);
            border-radius: 0 25px 25px 0;
            margin-right: 20px;
            transform: translateX(10px) scale(1.05);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        .sidebar .nav-link:hover i {
            transform: scale(1.2) rotate(5deg);
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
            z-index: 10;
            position: relative;
        }
        .request-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.3);
            animation: fadeInUp 0.7s cubic-bezier(.39,.575,.565,1.000) both;
        }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(40px);}
            100% { opacity: 1; transform: translateY(0);}
        }
        .laundry-table th, .laundry-table td { text-align: center; vertical-align: middle; }
        .laundry-table input[type="number"] { width: 60px; margin: 0 auto; }
        .total-warning { color: #dc3545; font-weight: 600; }
        .btn-primary {
            background: linear-gradient(90deg, #2c5aa0 60%, #23d5ab 100%);
            border: none;
            border-radius: 14px;
            padding: 13px 0;
            font-weight: 700;
            font-size: 17px;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 12px 0 #23d5ab33;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #23d5ab 0%, #2c5aa0 100%);
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 6px 24px 0 #23d5ab33;
        }
        .alert {
            border-radius: 12px;
            font-size: 1rem;
            margin-bottom: 18px;
        }

        /* Premium Payment Required Modal */
        .payment-required-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        .payment-required-modal.show {
            opacity: 1;
            visibility: visible;
        }
        .payment-modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            animation: fadeIn 0.4s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .payment-modal-container {
            position: relative;
            width: 90%;
            max-width: 520px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 28px;
            overflow: hidden;
            transform: scale(0.8) translateY(40px);
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.25), 0 15px 30px rgba(0, 0, 0, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }
        .payment-required-modal.show .payment-modal-container {
            transform: scale(1) translateY(0);
        }
        .payment-modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 32px 28px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .payment-modal-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            animation: rotate 15s linear infinite;
        }
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .payment-modal-icon {
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0 auto 18px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid rgba(255, 255, 255, 0.3);
            animation: bounce 1.5s ease infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .payment-modal-icon i {
            font-size: 2.5rem;
            color: white;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }
        .payment-modal-title {
            position: relative;
            font-size: 1.75rem;
            font-weight: 700;
            color: white;
            margin: 0 0 8px 0;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        .payment-modal-subtitle {
            position: relative;
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            margin: 0;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.15);
        }
        .payment-modal-body {
            padding: 32px 28px;
        }
        .payment-info-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .payment-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }
        .payment-info-item:last-child {
            border-bottom: none;
        }
        .payment-info-label {
            color: #6c757d;
            font-size: 0.95rem;
            font-weight: 500;
        }
        .payment-info-value {
            color: #2c3e50;
            font-size: 1rem;
            font-weight: 600;
        }
        .payment-info-value.unpaid {
            color: #dc3545;
        }
        .payment-modal-message {
            text-align: center;
            color: #495057;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .payment-modal-actions {
            display: flex;
            gap: 12px;
        }
        .payment-action-btn {
            flex: 1;
            padding: 14px 20px;
            border-radius: 14px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .payment-action-btn.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .payment-action-btn.primary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
            color: white;
            text-decoration: none;
        }
        .payment-action-btn.secondary {
            background: #f8f9fa;
            color: #495057;
            border: 2px solid #dee2e6;
        }
        .payment-action-btn.secondary:hover {
            background: #e9ecef;
            border-color: #adb5bd;
            transform: translateY(-2px);
            color: #495057;
            text-decoration: none;
        }

        /* Form overlay when payment is required */
        .form-disabled-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(3px);
            z-index: 100;
            display: none;
            border-radius: 20px;
        }
        .form-disabled-overlay.active {
            display: block;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .particles {
                display: none;
            }
            .payment-modal-container {
                width: 95%;
                max-width: 95%;
            }
            .payment-modal-header {
                padding: 24px 20px;
            }
            .payment-modal-body {
                padding: 24px 20px;
            }
            .payment-modal-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<div class="particles" id="particles"></div>
<div class="sidebar" id="sidebar">
    <div class="brand">
        <i class="fas fa-tshirt fa-2x mb-2"></i>
        <h4>FreshFold</h4>
        <small>Laundry Management</small>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link" href="dashboard_page.php">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a class="nav-link active" href="new_request_page.php">
            <i class="fas fa-plus-circle"></i> New Request
        </a>
        <a class="nav-link" href="my_requests_page.php">
            <i class="fas fa-list"></i> My Requests
        </a>
        <a class="nav-link" href="issue_report_page.php">
            <i class="fas fa-exclamation-triangle"></i> Report Issue
        </a>
        <a class="nav-link" href="payment_tab.php">
            <i class="fas fa-credit-card"></i> Payment
        </a>
        <a class="nav-link" href="profile_page.php">
            <i class="fas fa-user"></i> Profile
        </a>
        <hr style="border-color: rgba(255,255,255,0.2); margin: 20px;">
        <a class="nav-link" href="logout.php" onclick="return confirm('Are you sure you want to logout?')">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</div>
<div class="main-content">
    <div class="request-card" style="position: relative;">
        <?php if ($show_payment_modal): ?>
        <div class="form-disabled-overlay active"></div>
        <?php endif; ?>

        <h2 class="mb-4"><i class="fas fa-plus-circle me-2"></i>New Laundry Request</h2>
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" id="laundryForm" autocomplete="off">
            <div class="mb-3">
                <label class="form-label">Pickup Date</label>
                <input type="text" class="form-control" value="<?php echo date('l, M j, Y', strtotime($calculated_pickup_date)); ?>" readonly>
                <input type="hidden" name="pickup_date" value="<?php echo $calculated_pickup_date; ?>">
                <div class="form-text text-success">Your pickup date is automatically set based on workload and holidays.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Laundry Items (Max 20 per request)</label>
                <table class="table table-bordered laundry-table">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Item</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $items = ($gender === 'female') ? $girl_items : $boy_items;
                    foreach($items as $i => $item): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><?php echo htmlspecialchars($item); ?></td>
                            <td>
                                <input type="number" min="0" max="20" name="items[<?php echo $i; ?>]" value="0" class="form-control item-qty" required>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2">TOTAL</th>
                            <th><span id="totalQty">0</span></th>
                        </tr>
                    </tfoot>
                </table>
                <div id="totalWarning" class="total-warning"></div>
            </div>
            <div class="mb-3">
                <label for="special_instructions" class="form-label">Special Instructions (Optional)</label>
                <textarea class="form-control" id="special_instructions" name="special_instructions" rows="3"></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg flex-fill" id="submitBtn">
                    Submit Request
                </button>
                <a href="dashboard_page.php" class="btn btn-outline-secondary btn-lg flex-fill">Cancel</a>
            </div>
        </form>
    </div>
</div>

<!-- Premium Payment Required Modal -->
<?php if ($show_payment_modal): ?>
<div class="payment-required-modal show" id="paymentModal">
    <div class="payment-modal-backdrop"></div>
    <div class="payment-modal-container">
        <div class="payment-modal-header">
            <div class="payment-modal-icon">
                <i class="fas fa-lock"></i>
            </div>
            <h3 class="payment-modal-title">Payment Required</h3>
            <p class="payment-modal-subtitle">Complete your annual fee to continue</p>
        </div>
        <div class="payment-modal-body">
            <div class="payment-info-card">
                <div class="payment-info-item">
                    <span class="payment-info-label">Payment Status</span>
                    <span class="payment-info-value unpaid">
                        <i class="fas fa-times-circle"></i> Not Paid
                    </span>
                </div>
                <div class="payment-info-item">
                    <span class="payment-info-label">Academic Year</span>
                    <span class="payment-info-value"><?php echo $current_year; ?></span>
                </div>
                <div class="payment-info-item">
                    <span class="payment-info-label">Annual Fee</span>
                    <span class="payment-info-value">₹8,500</span>
                </div>
            </div>
            <p class="payment-modal-message">
                To submit a new laundry request, you need to complete your annual laundry service fee payment for <?php echo $current_year; ?>. This one-time payment covers all laundry services for the entire academic year.
            </p>
            <div class="payment-modal-actions">
                <a href="payment_tab.php" class="payment-action-btn primary">
                    <i class="fas fa-credit-card"></i>
                    <span>Pay Now</span>
                </a>
                <a href="dashboard_page.php" class="payment-action-btn secondary">
                    <i class="fas fa-arrow-left"></i>
                    <span>Go Back</span>
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="custom_alerts.js"></script>
<script>
function createParticles() {
    const particlesContainer = document.getElementById('particles');
    const particleCount = 20;
    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.top = Math.random() * 100 + '%';
        particle.style.animationDelay = Math.random() * 6 + 's';
        particle.style.animationDuration = (Math.random() * 3 + 3) + 's';
        particlesContainer.appendChild(particle);
    }
}
createParticles();

const qtyInputs = document.querySelectorAll('.item-qty');
const totalQty = document.getElementById('totalQty');
const totalWarning = document.getElementById('totalWarning');
const form = document.getElementById('laundryForm');
const isPaymentRequired = <?php echo $show_payment_modal ? 'true' : 'false'; ?>;

// Disable form submission if payment is required
if (isPaymentRequired) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        // Direct redirect to payment page without alert
        window.location.href = 'payment_tab.php';
        return false;
    });
    
    // Disable all form inputs
    const allInputs = form.querySelectorAll('input, textarea, button[type="submit"]');
    allInputs.forEach(input => {
        if (input.type !== 'button' && !input.classList.contains('btn-outline-secondary')) {
            input.disabled = true;
        }
    });
}

function updateTotal() {
    let total = 0;
    qtyInputs.forEach(input => {
        let val = parseInt(input.value) || 0;
        total += val;
    });
    totalQty.textContent = total;
    if (total > 20) {
        totalWarning.textContent = "You cannot submit more than 20 clothes in a single request.";
    } else {
        totalWarning.textContent = "";
    }
}

qtyInputs.forEach(input => {
    input.addEventListener('input', updateTotal);
});

form.addEventListener('submit', function(e) {
    if (isPaymentRequired) {
        e.preventDefault();
        return false;
    }
    
    let total = 0;
    qtyInputs.forEach(input => {
        let val = parseInt(input.value) || 0;
        total += val;
    });
    if (total > 20) {
        totalWarning.textContent = "You cannot submit more than 20 clothes in a single request.";
        e.preventDefault();
    } else if (total < 1) {
        totalWarning.textContent = "Please enter at least one item.";
        e.preventDefault();
    }
});

// Notification fetching
function fetchNotifications() {
    fetch('notifications_ajax.php')
        .then(res => res.json())
        .then(data => {
            const list = document.getElementById('notification-list');
            if (list) {
                list.innerHTML = '';
                data.forEach(n => {
                    const item = document.createElement('div');
                    item.className = 'dropdown-item';
                    item.style.cursor = 'pointer';
                    item.innerHTML = `<strong>${n.title}</strong><br><span class="text-muted">${n.message}</span>`;
                    item.onclick = function() {
                        // Mark as read
                        fetch('notifications_ajax.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'action=mark_read&notification_id=' + n.notification_id
                        });
                        // Direct redirect without alert
                        if (n.target_url) {
                            window.location.href = n.target_url;
                        }
                    };
                    list.appendChild(item);
                });
                const countEl = document.getElementById('notification-count');
                if (countEl) {
                    countEl.textContent = data.length;
                    countEl.style.display = data.length ? 'inline-block' : 'none';
                }
            }
        })
        .catch(error => console.error('Notification fetch error:', error));
}

// Only fetch notifications if elements exist
if (document.getElementById('notification-bell')) {
    setInterval(fetchNotifications, 15000);
    fetchNotifications();
    
    document.getElementById('notification-bell').onclick = function(e) {
        e.stopPropagation();
        const dropdown = document.getElementById('notification-dropdown');
        if (dropdown) {
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }
    };
    
    document.body.onclick = function() {
        const dropdown = document.getElementById('notification-dropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    };
}

// Close modal on backdrop click (optional, if you want this functionality)
const paymentModal = document.getElementById('paymentModal');
if (paymentModal) {
    const backdrop = paymentModal.querySelector('.payment-modal-backdrop');
    if (backdrop) {
        backdrop.addEventListener('click', function(e) {
            // Prevent closing - force user to make a choice
            e.stopPropagation();
        });
    }
}
</script>
</body>
</html>