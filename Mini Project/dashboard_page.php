<?php
require_once 'freshfold_config.php';

// Check if user is logged in - redirect to login if not
requireLogin();

// Get user data for display
$database = new Database();
$db = $database->getConnection();
$laundryRequest = new LaundryRequest($db);

// Get user's requests for statistics
$user_requests = $laundryRequest->getStudentRequests($_SESSION['user_id']);

// Calculate statistics
$stats = [
    'submitted' => 0,
    'processing' => 0,
    'delivered' => 0
];

foreach($user_requests as $request) {
    $status = $request['status'];
    if(isset($stats[$status])) {
        $stats[$status]++;
    }
}

// Get recent requests (last 3)
$recent_requests = array_slice($user_requests, 0, 3);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FreshFold</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <meta http-equiv="refresh" content="30">
    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #f8f9fa;
            --accent-color: #28a745;
            --sidebar-width: 250px;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, var(--primary-color) 0%, #1e3d6f 100%);
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

        /* FIXED: Stable logo with subtle glow effect instead of spinning */
        .sidebar .brand i {
            position: relative;
            display: inline-block;
            filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.3));
            transition: all 0.3s ease;
        }

        /* Subtle hover effect for logo */
        .sidebar .brand:hover i {
            filter: drop-shadow(0 0 15px rgba(255, 255, 255, 0.5));
            transform: scale(1.05);
        }

        /* Removed spinning animation, replaced with fade-in */
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

        /* Added subtle brand text animation */
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

        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
            z-index: 10;
            position: relative;
        }

        /* Enhanced stat cards with 3D effects */
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 20px;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            height: 100%;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(from 0deg, transparent, rgba(44, 90, 160, 0.1), transparent);
            transition: transform 0.6s;
            transform: rotate(0deg);
        }

        .stat-card:hover::before {
            transform: rotate(180deg);
        }

        .stat-card:hover {
            transform: translateY(-15px) rotateX(5deg);
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
            background: rgba(255, 255, 255, 1);
        }

        .stat-card-content {
            position: relative;
            z-index: 2;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
            animation: countUp 1s ease-out;
            background: linear-gradient(45deg, var(--primary-color), #1e3d6f);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        @keyframes countUp {
            from { transform: scale(0.5); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 5px;
            opacity: 0;
            animation: fadeInUp 0.8s ease-out 0.3s forwards;
        }

        @keyframes fadeInUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Enhanced welcome card */
        .welcome-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 25px;
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            animation: slideInFromLeft 1s ease-out;
        }

        @keyframes slideInFromLeft {
            0% { transform: translateX(-100px); opacity: 0; }
            100% { transform: translateX(0); opacity: 1; }
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .welcome-card-content {
            position: relative;
            z-index: 2;
        }

        .welcome-card h2 {
            margin-bottom: 10px;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        /* Quick actions with hover effects */
        .quick-actions {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: slideInFromRight 1s ease-out;
        }

        @keyframes slideInFromRight {
            0% { transform: translateX(100px); opacity: 0; }
            100% { transform: translateX(0); opacity: 1; }
        }

        .btn-quick-action {
            background: var(--gradient-2);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 15px 20px;
            margin: 5px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .btn-quick-action::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-quick-action:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-quick-action:hover {
            color: white;
            transform: translateY(-8px) scale(1.05);
            text-decoration: none;
            box-shadow: 0 15px 30px rgba(44, 90, 160, 0.4);
        }

        .btn-quick-action span {
            position: relative;
            z-index: 2;
        }

        /* Recent requests with animations */
        .recent-requests {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-top: 20px;
            animation: slideInFromBottom 1s ease-out;
        }

        @keyframes slideInFromBottom {
            0% { transform: translateY(50px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }

        .request-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            transition: all 0.3s ease;
            position: relative;
        }

        .request-item:hover {
            background: rgba(44, 90, 160, 0.05);
            padding-left: 10px;
            border-radius: 10px;
        }

        .request-item:last-child {
            border-bottom: none;
        }

        /* Status badges with pulse animation */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
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

        /* Loading animation */
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255,255,255,0.3);
            border-top: 5px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        /* Responsive design */
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
            
            .stat-card {
                margin-bottom: 15px;
            }

            .particles {
                display: none;
            }
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

        /* ADDED: Logo stability indicator */
        .brand-stable {
            position: relative;
        }

        .brand-stable::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            width: 30px;
            height: 2px;
            background: rgba(255, 255, 255, 0.5);
            transform: translateX(-50%);
            border-radius: 2px;
        }
    </style>
</head>
<body>

<!-- Floating Particles Background -->
<div class="particles" id="particles"></div>

<!-- Mobile menu toggle -->
<button class="btn btn-primary d-md-none position-fixed" style="top: 20px; left: 20px; z-index: 1001;" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="brand brand-stable">
        <i class="fas fa-tshirt fa-2x mb-2"></i>
        <h4>FreshFold</h4>
        <small>Laundry Management</small>
    </div>
    
    <nav class="nav flex-column">
        <a class="nav-link active" href="dashboard_page.php">
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
        
        <?php if($_SESSION['user_type'] == 'staff' || $_SESSION['user_type'] == 'admin'): ?>
        <a class="nav-link" href="manage_requests_page.php">
            <i class="fas fa-tasks"></i> Manage Requests
        </a>
        <a class="nav-link" href="users.php">
            <i class="fas fa-users"></i> Users
        </a>
        <?php endif; ?>
        
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
    <!-- Welcome Card -->
    <div class="welcome-card">
        <div class="welcome-card-content">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                    <p class="mb-2">Manage your laundry services efficiently with FreshFold</p>
                    <small class="opacity-75">
                        <?php echo ucfirst($_SESSION['user_type']); ?> • Block <?php echo $_SESSION['hostel_block']; ?> - Room <?php echo $_SESSION['room_number']; ?>
                    </small>
                </div>
                <div class="col-md-4 text-end">
                    <i class="fas fa-user-circle fa-5x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row">
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card" onclick="window.location.href='my_requests_page.php?status=submitted'" style="cursor:pointer;">
                <div class="stat-card-content text-center">
                    <div class="stat-number" data-target="<?php echo $stats['submitted']; ?>">0</div>
                    <div class="stat-label">Submitted</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card" onclick="window.location.href='my_requests_page.php?status=processing'" style="cursor:pointer;">
                <div class="stat-card-content text-center">
                    <div class="stat-number" data-target="<?php echo $stats['processing']; ?>">0</div>
                    <div class="stat-label">Processing</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
            <div class="stat-card" onclick="window.location.href='my_requests_page.php?status=delivered'" style="cursor:pointer;">
                <div class="stat-card-content text-center">
                    <div class="stat-number" data-target="<?php echo $stats['delivered']; ?>">0</div>
                    <div class="stat-label">Delivered</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-md-6">
            <div class="quick-actions">
                <h5 class="mb-3"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                <a href="new_request_page.php" class="btn-quick-action">
                    <span><i class="fas fa-plus-circle me-2"></i>New Laundry Request</span>
                </a>
                <a href="my_requests_page.php" class="btn-quick-action">
                    <span><i class="fas fa-list me-2"></i>View My Requests</span>
                </a>
                <a href="issue_report_page.php" class="btn-quick-action">
                    <span><i class="fas fa-exclamation-triangle me-2"></i>Report Issue</span>
                </a>
                <a href="profile_page.php" class="btn-quick-action">
                    <span><i class="fas fa-user me-2"></i>Update Profile</span>
                </a>
            </div>
        </div>

        <!-- Recent Requests -->
        <div class="col-md-6">
            <div class="recent-requests">
                <h5 class="mb-3"><i class="fas fa-history me-2"></i>Recent Requests</h5>
                
                <?php if(empty($recent_requests)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No requests yet</p>
                    <a href="new_request_page.php" class="btn btn-primary btn-sm">Create Your First Request</a>
                </div>
                <?php else: ?>
                    <?php foreach($recent_requests as $request): ?>
                    <div class="request-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars($request['bag_number']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo date('M j, Y', strtotime($request['created_at'])); ?></small>
                            </div>
                            <span class="status-badge status-<?php echo $request['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-3">
                        <a href="my_requests_page.php" class="btn btn-outline-primary btn-sm">View All Requests</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Notification Bell -->
    <div id="notification-bell" style="position:relative; cursor:pointer; margin-top: 20px;">
        <i class="fas fa-bell fa-lg" style="color: var(--primary-color);"></i>
        <span id="notification-count" class="badge bg-danger" style="position:absolute; top:-8px; right:-8px; display:none;">0</span>
    </div>
    <div id="notification-dropdown" class="dropdown-menu" style="display:none; position:absolute; z-index:2000; min-width:250px; right:0;">
        <div id="notification-list"></div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
// Create floating particles
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

// Animate stat numbers on load
function animateStatNumbers() {
    const statNumbers = document.querySelectorAll('.stat-number[data-target]');
    
    statNumbers.forEach(stat => {
        const target = parseInt(stat.getAttribute('data-target'));
        const increment = target / 50;
        let current = 0;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                stat.textContent = target;
                clearInterval(timer);
            } else {
                stat.textContent = Math.floor(current);
            }
        }, 30);
    });
}

// Toggle sidebar for mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}

// Animate card on click
function animateCard(card) {
    card.style.transform = 'scale(0.95)';
    setTimeout(() => {
        card.style.transform = '';
    }, 150);
}

// Add scroll animations
function addScrollAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    });
    
    document.querySelectorAll('.stat-card, .quick-actions, .recent-requests').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });
}

// Add mouse follow effect
document.addEventListener('mousemove', (e) => {
    const cursor = document.querySelector('.cursor');
    if (!cursor) {
        const newCursor = document.createElement('div');
        newCursor.className = 'cursor';
        newCursor.style.cssText = `
            position: fixed;
            width: 20px;
            height: 20px;
            background: radial-gradient(circle, rgba(255,255,255,0.3), transparent);
            border-radius: 50%;
            pointer-events: none;
            z-index: 9999;
            transition: transform 0.1s ease;
        `;
        document.body.appendChild(newCursor);
    }
    
    const cursorElement = document.querySelector('.cursor');
    cursorElement.style.transform = `translate(${e.clientX - 10}px, ${e.clientY - 10}px)`;
});

// Notification fetching
function fetchNotifications() {
    fetch('notifications_ajax.php')
        .then(res => res.json())
        .then(data => {
            const list = document.getElementById('notification-list');
            list.innerHTML = '';
            data.forEach(n => {
                const item = document.createElement('div');
                item.className = 'dropdown-item';
                item.style.cursor = 'pointer';
                item.innerHTML = `<strong>${n.title}</strong><br><span class="text-muted">${n.message}</span>`;
                item.onclick = function() {
                    fetch('mark_staff_notification_read.php?id=' + n.notification_id);
                    if (n.target_url) {
                        window.location.href = n.target_url;
                    }
                };
                list.appendChild(item);
            });
            document.getElementById('notification-count').textContent = data.length;
            document.getElementById('notification-count').style.display = data.length ? 'inline-block' : 'none';
        });
}
setInterval(fetchNotifications, 15000); // Poll every 15 seconds
fetchNotifications();

document.getElementById('notification-bell').onclick = function(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('notification-dropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
};
document.body.onclick = function() {
    document.getElementById('notification-dropdown').style.display = 'none';
};

// On page load
window.onload = function() {
    createParticles();
    animateStatNumbers();
    addScrollAnimations();
};
</script>
</body>
</html>