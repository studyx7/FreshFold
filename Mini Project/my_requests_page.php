<?php
require_once 'freshfold_config.php';
requireLogin();
requireUserType('student');

$database = new Database();
$db = $database->getConnection();
$laundryRequest = new LaundryRequest($db);

// Get all requests for the logged-in student
$requests = $laundryRequest->getStudentRequests($_SESSION['user_id']);
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
        /* Sidebar with glassmorphism */
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
        /* Mobile menu toggle */
        .btn-menu-toggle {
            display: none;
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
            .btn-menu-toggle {
                display: block;
            }
        }
        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
            z-index: 10;
            position: relative;
        }
        /* Request cards with animation */
        .request-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.3);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            opacity: 0;
            transform: translateY(30px);
        }
        .request-card.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .request-card:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            background: rgba(255,255,255,1);
        }
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-picked_up { background-color: #cff4fc; color: #087990; }
        .status-in_progress { background-color: #d1ecf1; color: #0c5460; }
        .status-ready { background-color: #d4edda; color: #155724; }
        .status-delivered { background-color: #e2e3e5; color: #41464b; }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            height: 100%;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 15px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -19px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #6c757d;
        }
        .timeline-item.completed::before {
            background: var(--accent-color);
        }
        .timeline-item.current::before {
            background: var(--primary-color);
            width: 14px;
            height: 14px;
            left: -21px;
            top: 3px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }
    </style>
</head>
<body>

<!-- Floating Particles Background -->
<div class="particles" id="particles"></div>

<!-- Mobile menu toggle -->
<button class="btn btn-primary d-md-none position-fixed btn-menu-toggle" style="top: 20px; left: 20px; z-index: 1001;" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
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
        <a class="nav-link" href="new_request_page.php">
            <i class="fas fa-plus-circle"></i> New Request
        </a>
        <a class="nav-link active" href="my_requests_page.php">
            <i class="fas fa-list"></i> My Requests
        </a>
        <a class="nav-link" href="issue_report_page.php">
            <i class="fas fa-exclamation-triangle"></i> Report Issue
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

<!-- Main Content -->
<div class="main-content">
    <?php displayAlerts(); ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-list me-2"></i>My Laundry Requests</h2>
        <a href="new_request_page.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>New Request
        </a>
    </div>

    <?php if(empty($requests)): ?>
    <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <h4>No Requests Yet</h4>
        <p>You haven't made any laundry requests yet.</p>
        <a href="new_request_page.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Create Your First Request
        </a>
    </div>
    <?php else: ?>

    <div class="row">
        <?php foreach($requests as $request): ?>
        <div class="col-md-6">
            <div class="request-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1">Request #<?php echo $request['request_id']; ?></h5>
                        <p class="text-muted mb-0">Bag: <?php echo $request['bag_number']; ?></p>
                    </div>
                    <span class="status-badge status-<?php echo $request['status']; ?>">
                        <?php echo ucwords(str_replace('_', ' ', $request['status'])); ?>
                    </span>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <small class="text-muted">Pickup Date</small>
                        <div><?php echo date('M j, Y', strtotime($request['pickup_date'])); ?></div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Expected Delivery</small>
                        <div><?php echo date('M j, Y', strtotime($request['expected_delivery'])); ?></div>
                    </div>
                </div>

                <?php if($request['special_instructions']): ?>
                <div class="mb-3">
                    <small class="text-muted">Special Instructions</small>
                    <div class="bg-light p-2 rounded"><?php echo htmlspecialchars($request['special_instructions']); ?></div>
                </div>
                <?php endif; ?>

                <!-- Status Timeline -->
                <div class="timeline">
                    <div class="timeline-item <?php echo in_array($request['status'], ['pending', 'picked_up', 'in_progress', 'ready', 'delivered']) ? 'completed' : ''; ?>">
                        <small class="text-muted">Request Submitted</small>
                        <div><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></div>
                    </div>
                    
                    <div class="timeline-item <?php echo in_array($request['status'], ['picked_up', 'in_progress', 'ready', 'delivered']) ? 'completed' : ($request['status'] == 'pending' ? 'current' : ''); ?>">
                        <small class="text-muted">Pickup Scheduled</small>
                        <div><?php echo $request['status'] == 'pending' ? 'Waiting for pickup' : 'Picked up'; ?></div>
                    </div>
                    
                    <div class="timeline-item <?php echo in_array($request['status'], ['in_progress', 'ready', 'delivered']) ? 'completed' : ($request['status'] == 'picked_up' ? 'current' : ''); ?>">
                        <small class="text-muted">Processing</small>
                        <div><?php echo in_array($request['status'], ['in_progress', 'ready', 'delivered']) ? 'In progress' : 'Waiting'; ?></div>
                    </div>
                    
                    <div class="timeline-item <?php echo in_array($request['status'], ['ready', 'delivered']) ? 'completed' : ($request['status'] == 'in_progress' ? 'current' : ''); ?>">
                        <small class="text-muted">Ready for Delivery</small>
                        <div><?php echo in_array($request['status'], ['ready', 'delivered']) ? 'Ready' : 'Processing'; ?></div>
                    </div>
                    
                    <div class="timeline-item <?php echo $request['status'] == 'delivered' ? 'completed' : ($request['status'] == 'ready' ? 'current' : ''); ?>">
                        <small class="text-muted">Delivered</small>
                        <div><?php echo $request['status'] == 'delivered' ? 'Completed' : 'Pending'; ?></div>
                    </div>
                </div>

                <div class="text-muted small mt-3">
                    Last updated: <?php echo date('M j, Y g:i A', strtotime($request['updated_at'])); ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
// Floating particles effect
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

// Toggle sidebar for mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}

// Animate cards on scroll
function addScrollAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.2 });
    document.querySelectorAll('.request-card').forEach(el => {
        observer.observe(el);
    });
}
document.addEventListener('DOMContentLoaded', addScrollAnimations);
</script>
</body>
</html>