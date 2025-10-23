<?php
require_once 'freshfold_config.php';
requireLogin();
requireUserType('student');

$database = new Database();
$db = $database->getConnection();
$laundryRequest = new LaundryRequest($db);

// Get all requests for the logged-in student
$user_requests = $laundryRequest->getStudentRequests($_SESSION['user_id']);

// For admin: Get all requests with optional status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$all_requests = $laundryRequest->getAllRequests($status_filter);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_request_id'])) {
    $feedback_request_id = intval($_POST['feedback_request_id']);
    $feedback_text = trim($_POST['feedback_text']);
    if ($feedback_text !== '') {
        $stmt = $db->prepare("INSERT INTO feedback (request_id, student_id, feedback_text, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$feedback_request_id, $_SESSION['user_id'], $feedback_text]);
        showAlert('Thank you for your feedback!', 'success');
    } else {
        showAlert('No feedback entered. Nothing was submitted.', 'info');
    }
    redirect('my_requests_page.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests - FreshFold</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c5aa0;
            --primary-dark: #1e3d6f;
            --secondary-color: #f8f9fa;
            --accent-color: #28a745;
            --sidebar-width: 250px;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, var(--primary-color) 0%, #1e3d6f 100%);
            --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            --card-shadow-hover: 0 16px 48px rgba(0, 0, 0, 0.12);
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
            color: #2d3748;
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
        }

        .sidebar .brand i {
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

        .btn-menu-toggle {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 12px;
            padding: 12px 16px;
            color: var(--primary-color);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            display: none;
        }

        .btn-menu-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.2);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 40px;
            min-height: 100vh;
            z-index: 10;
            position: relative;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 32px 40px;
            margin-bottom: 32px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.5);
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideInDown 0.6s ease-out;
        }

        @keyframes slideInDown {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .page-header h2 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-new-request {
            background: var(--gradient-1);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-new-request:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
        }

        .request-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.5);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            opacity: 0;
            transform: translateY(30px);
            position: relative;
            overflow: hidden;
        }

        .request-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-1);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .request-card:hover::before {
            transform: scaleX(1);
        }

        .request-card.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .request-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--card-shadow-hover);
        }

        .card-header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        }

        .card-title-group h5 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 6px;
        }

        .card-subtitle {
            color: #718096;
            font-size: 0.95rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-badge {
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .status-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }

        .status-submitted { 
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: #78350f;
        }
        .status-processing { 
            background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
            color: #1e3a8a;
        }
        .status-delivered { 
            background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
            color: #064e3b;
        }
        .status-cancelled { 
            background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
            color: #7f1d1d;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .info-item {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            padding: 18px 20px;
            border-radius: 16px;
            border: 1px solid rgba(102, 126, 234, 0.1);
            transition: all 0.3s ease;
        }

        .info-item:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            transform: translateY(-2px);
        }

        .info-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #718096;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .special-instructions {
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.08), rgba(245, 158, 11, 0.08));
            border-left: 4px solid #f59e0b;
            padding: 18px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .special-instructions-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #92400e;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .special-instructions-text {
            color: #78350f;
            font-size: 0.95rem;
            line-height: 1.6;
            font-weight: 500;
        }

        .timeline-container {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.03), rgba(118, 75, 162, 0.03));
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 20px;
        }

        .timeline {
            position: relative;
            padding-left: 40px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 8px;
            height: calc(100% - 16px);
            width: 3px;
            background: linear-gradient(180deg, #e2e8f0 0%, #cbd5e0 100%);
            border-radius: 2px;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 24px;
            padding-left: 8px;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -27px;
            top: 6px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #cbd5e0;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }

        .timeline-item.completed::before {
            background: linear-gradient(135deg, #10b981, #059669);
            width: 16px;
            height: 16px;
            left: -28px;
            top: 5px;
        }

        .timeline-item.current::before {
            background: linear-gradient(135deg, #667eea, #764ba2);
            width: 18px;
            height: 18px;
            left: -29px;
            top: 4px;
            animation: pulse-timeline 2s infinite;
        }

        @keyframes pulse-timeline {
            0%, 100% { 
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), 0 0 0 0 rgba(102, 126, 234, 0.4);
            }
            50% { 
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), 0 0 0 8px rgba(102, 126, 234, 0);
            }
        }

        .timeline-label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #718096;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .timeline-item.completed .timeline-label {
            color: #059669;
        }

        .timeline-item.current .timeline-label {
            color: #667eea;
        }

        .timeline-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #2d3748;
        }

        .card-footer-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            border-top: 2px solid rgba(0, 0, 0, 0.05);
            margin-top: 20px;
        }

        .last-updated {
            color: #a0aec0;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-feedback {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-feedback:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .empty-state {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 80px 40px;
            text-align: center;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.5);
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .empty-state-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .empty-state-icon::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            animation: pulse-ring 2s infinite;
        }

        @keyframes pulse-ring {
            0% { transform: scale(1); opacity: 1; }
            100% { transform: scale(1.3); opacity: 0; }
        }

        .empty-state-icon i {
            font-size: 3.5rem;
            color: #667eea;
            position: relative;
            z-index: 1;
        }

        .empty-state h4 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 12px;
        }

        .empty-state p {
            font-size: 1.1rem;
            color: #718096;
            margin-bottom: 32px;
        }

        @media (max-width: 768px) {
            .btn-menu-toggle {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 80px 20px 20px;
            }

            .particles {
                display: none;
            }

            .page-header {
                flex-direction: column;
                gap: 20px;
                padding: 24px;
                text-align: center;
            }

            .page-header h2 {
                font-size: 1.5rem;
            }

            .request-card {
                padding: 24px;
            }

            .card-header-section {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .card-footer-section {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }
        }

        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 5px;
        }

        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
            padding: 24px 32px;
        }

        .modal-title {
            font-weight: 700;
            color: var(--primary-color);
        }

        .modal-body {
            padding: 32px;
        }

        .modal-footer {
            border-top: 2px solid rgba(0, 0, 0, 0.05);
            padding: 20px 32px;
        }
    </style>
</head>
<body>

<div class="particles" id="particles"></div>

<button class="btn btn-primary btn-menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar" id="sidebar">
    <div class="brand">
        <i class="fas fa-tshirt fa-2x mb-2"></i>
        <h4>FreshFold</h4>
        <small>Laundry Management</small>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='dashboard_page.php') echo ' active'; ?>" href="dashboard_page.php">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='new_request_page.php') echo ' active'; ?>" href="new_request_page.php">
            <i class="fas fa-plus-circle"></i> New Request
        </a>
        <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='my_requests_page.php') echo ' active'; ?>" href="my_requests_page.php">
            <i class="fas fa-list"></i> My Requests
        </a>
        <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='issue_report_page.php') echo ' active'; ?>" href="issue_report_page.php">
            <i class="fas fa-exclamation-triangle"></i> Report Issue
        </a>
        <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='payment_tab.php') echo ' active'; ?>" href="payment_tab.php">
            <i class="fas fa-credit-card"></i> Payment
        </a>
        <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='profile_page.php') echo ' active'; ?>" href="profile_page.php">
            <i class="fas fa-user"></i> Profile
        </a>
        <hr style="border-color: rgba(255,255,255,0.2); margin: 20px;">
        <a class="nav-link" href="logout.php" onclick="return confirm('Are you sure you want to logout?')">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</div>

<div class="main-content">
    <?php displayAlerts(); ?>
    
    <div class="page-header">
        <h2>
            <i class="fas fa-list-alt"></i>
            My Laundry Requests
        </h2>
        <a href="new_request_page.php" class="btn-new-request">
            <i class="fas fa-plus-circle"></i>
            New Request
        </a>
    </div>

    <?php if(empty($user_requests)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">
            <i class="fas fa-inbox"></i>
        </div>
        <h4>No Requests Yet</h4>
        <p>You haven't made any laundry requests yet.</p>
        <a href="new_request_page.php" class="btn-new-request">
            <i class="fas fa-plus-circle"></i>
            Create Your First Request
        </a>
    </div>
    <?php else: ?>

    <div class="row">
        <?php foreach($user_requests as $index => $request): ?>
        <div class="col-lg-6 col-xl-6">
            <div class="request-card" id="request-card-<?php echo $request['request_id']; ?>" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                
                <div class="card-header-section">
                    <div class="card-title-group">
                        <h5>Request #<?php echo $request['request_id']; ?></h5>
                        <p class="card-subtitle">
                            <i class="fas fa-shopping-bag"></i>
                            Bag: <?php echo htmlspecialchars($request['bag_number']); ?>
                        </p>
                    </div>
                    <span class="status-badge status-<?php echo $request['status']; ?>">
                        <?php
                        $status_icons = [
                            'submitted' => 'fa-clock',
                            'processing' => 'fa-spinner',
                            'delivered' => 'fa-check-circle',
                            'cancelled' => 'fa-times-circle'
                        ];
                        $status_map = [
                            'submitted' => 'Submitted',
                            'processing' => 'Processing',
                            'delivered' => 'Delivered',
                            'cancelled' => 'Cancelled'
                        ];
                        $icon = $status_icons[$request['status']] ?? 'fa-info-circle';
                        echo '<i class="fas ' . $icon . '"></i>';
                        echo $status_map[$request['status']] ?? ucwords(str_replace('_', ' ', $request['status']));
                        ?>
                    </span>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-calendar-alt"></i>
                            Pickup Date
                        </div>
                        <div class="info-value">
                            <?php echo date('M j, Y', strtotime($request['pickup_date'])); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-truck"></i>
                            Expected Delivery
                        </div>
                        <div class="info-value">
                            <?php echo date('M j, Y', strtotime($request['expected_delivery'])); ?>
                        </div>
                    </div>
                </div>

                <?php if($request['special_instructions']): ?>
                <div class="special-instructions">
                    <div class="special-instructions-label">
                        <i class="fas fa-sticky-note"></i>
                        Special Instructions
                    </div>
                    <div class="special-instructions-text">
                        <?php echo htmlspecialchars($request['special_instructions']); ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="timeline-container">
                    <div class="timeline">
                        <div class="timeline-item <?php echo in_array($request['status'], ['submitted','processing','delivered']) ? 'completed' : ''; ?>">
                            <div class="timeline-label">Request Submitted</div>
                            <div class="timeline-value">
                                <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                            </div>
                        </div>
                        <div class="timeline-item <?php echo in_array($request['status'], ['processing','delivered']) ? 'completed' : ($request['status'] == 'submitted' ? 'current' : ''); ?>">
                            <div class="timeline-label">Processing</div>
                            <div class="timeline-value">
                                <?php echo in_array($request['status'], ['processing','delivered']) ? 'In progress' : 'Waiting'; ?>
                            </div>
                        </div>
                        <div class="timeline-item <?php echo $request['status'] == 'delivered' ? 'completed' : ($request['status'] == 'processing' ? 'current' : ''); ?>">
                            <div class="timeline-label">Delivered</div>
                            <div class="timeline-value">
                                <?php echo $request['status'] == 'delivered' ? 'Completed' : 'Pending'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer-section">
                    <div class="last-updated">
                        <i class="fas fa-clock"></i>
                        Updated: <?php echo date('M j, Y g:i A', strtotime($request['updated_at'])); ?>
                    </div>
                    
                    <?php if($request['status'] === 'delivered'): ?>
                        <button class="btn-feedback" onclick="openFeedbackModal(<?php echo $request['request_id']; ?>)">
                            <i class="fas fa-comment-dots"></i>
                            Give Feedback
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<div class="modal fade" id="feedbackModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="feedbackForm" method="POST">
                <input type="hidden" name="feedback_request_id" id="feedback_request_id">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-star me-2"></i>
                        Share Your Feedback
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label for="feedback_text" class="form-label">How was your experience?</label>
                    <textarea class="form-control" name="feedback_text" id="feedback_text" rows="4" maxlength="500" placeholder="Share your thoughts about the service quality, timeliness, or any suggestions for improvement..."></textarea>
                    <small class="text-muted mt-2 d-block">Your feedback helps us improve our service</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-paper-plane me-2"></i>
                        Submit Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
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

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}

document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('.btn-menu-toggle');
    
    if (window.innerWidth <= 768 && sidebar.classList.contains('show') && 
        !sidebar.contains(e.target) && !toggle.contains(e.target)) {
        sidebar.classList.remove('show');
    }
});

function addScrollAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { 
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });
    
    document.querySelectorAll('.request-card').forEach(el => {
        observer.observe(el);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    addScrollAnimations();
});

function openFeedbackModal(requestId) {
    document.getElementById('feedback_request_id').value = requestId;
    document.getElementById('feedback_text').value = '';
    var modal = new bootstrap.Modal(document.getElementById('feedbackModal'));
    modal.show();
}
</script>
</body>
</html>