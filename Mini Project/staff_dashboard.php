<?php
require_once 'freshfold_config.php';
requireLogin();

// Only staff can access this page
if (User::getUserType() !== 'staff') {
    showAlert('Access denied. Staff only.', 'danger');
    redirect('login_page.php');
}

$database = new Database();
$db = $database->getConnection();

// Count students
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'student'");
$total_students = $stmt->fetchColumn();

// Count requests
$stmt = $db->query("SELECT COUNT(*) FROM laundry_requests");
$total_requests = $stmt->fetchColumn();

// Count delivered requests
$total_delivered = $db->query("SELECT COUNT(*) FROM laundry_requests WHERE status = 'delivered'")->fetchColumn();

// Count submitted requests
$total_submitted = $db->query("SELECT COUNT(*) FROM laundry_requests WHERE status = 'submitted'")->fetchColumn();

// Count processing requests
$total_processing = $db->query("SELECT COUNT(*) FROM laundry_requests WHERE status = 'processing'")->fetchColumn();

$feedbacks = $db->query("SELECT f.*, u.full_name, lr.bag_number 
    FROM feedback f 
    JOIN users u ON f.student_id = u.user_id 
    JOIN laundry_requests lr ON f.request_id = lr.request_id 
    ORDER BY f.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Dashboard - FreshFold</title>
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
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 20px;
            height: 100%;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        .stat-card.visible {
            opacity: 1;
            transform: translateY(0);
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
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c5aa0;
            margin: 0;
            background: linear-gradient(45deg, #2c5aa0, #1e3d6f);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 5px;
        }
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
        .welcome-card h2 {
            margin-bottom: 10px;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        hr {
            border-color: rgba(255,255,255,0.2);
            margin: 20px;
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
        .modal-backdrop.show {
            opacity: 0.5 !important;
            background: #000 !important;
            z-index: 1050 !important;
        }
        .modal {
            z-index: 1060 !important;
        }
        .modal-content {
            opacity: 1 !important;
            pointer-events: auto !important;
        }
        body.modal-open {
            pointer-events: auto !important;
        }
        .feedback-card {
            animation: fadeInUp 0.8s cubic-bezier(.42,.65,.27,.99);
        }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(40px);}
            100% { opacity: 1; transform: translateY(0);}
        }
        .feedback-row:hover .feedback-bubble {
            box-shadow: 0 8px 32px rgba(35,213,171,0.18), 0 0 0 2px #23d5ab33;
            background: linear-gradient(90deg, #eafaf1 60%, #f8f9fa 100%);
            transition: box-shadow 0.2s, background 0.2s;
        }
        .feedback-bubble {
            transition: box-shadow 0.2s, background 0.2s;
        }
        .text-teal { color: #23d5ab !important; }
    </style>
</head>
<body>
<div class="particles" id="particles"></div>
<div class="sidebar">
    <div class="brand">
        <i class="fas fa-tshirt fa-2x mb-2"></i>
        <h4>FreshFold</h4>
        <small>Staff Panel</small>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link active" href="staff_dashboard.php">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a class="nav-link" href="staff_manage_requests.php">
            <i class="fas fa-tasks"></i> Manage Requests
        </a>
        <a class="nav-link" href="staff_complaints.php">
            <i class="fas fa-comments"></i> Student Complaints
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
    <div class="welcome-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                <p class="mb-2">You have staff access to FreshFold laundry management.</p>
                <small class="opacity-75">
                    <?php echo ucfirst($_SESSION['user_type']); ?> • <?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>
                </small>
            </div>
            <div class="col-md-4 text-end">
                <i class="fas fa-user-tie fa-5x opacity-50"></i>
            </div>
        </div>
    </div>

    <!-- Statistics Tiles (Interactive) -->
    <div class="row">
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="stat-card tile-interactive" data-status="submitted" style="cursor:pointer;">
                <div class="stat-number"><?php echo $total_submitted; ?></div>
                <div class="stat-label">Submitted</div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="stat-card tile-interactive" data-status="processing" style="cursor:pointer;">
                <div class="stat-number"><?php echo $total_processing; ?></div>
                <div class="stat-label">Processing</div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="stat-card tile-interactive" data-status="delivered" style="cursor:pointer;">
                <div class="stat-number"><?php echo $total_delivered; ?></div>
                <div class="stat-label">Delivered</div>
            </div>
        </div>
    </div>
    <!-- End Statistics Tiles -->

    <div class="card mt-4 shadow-lg border-0 feedback-card" style="background: rgba(255,255,255,0.98); border-radius: 32px; box-shadow: 0 16px 48px rgba(44,90,160,0.15);">
        <div class="card-header text-white d-flex align-items-center" style="
            border-radius: 32px 32px 0 0;
            background: linear-gradient(90deg, #23d5ab 0%, #23a6d5 100%);
            box-shadow: 0 4px 16px rgba(44,90,160,0.12);
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            min-height: 64px;
            display: flex;
            align-items: center;
        ">
            <i class="fas fa-comment-dots me-3 fa-lg"></i>
            <span>Student Feedback</span>
            <span class="ms-auto badge rounded-pill bg-light text-teal px-4 py-2" style="font-size:1.05rem;box-shadow:0 2px 8px #23d5ab22; color:#23d5ab;">
                <?php echo count($feedbacks); ?> Feedbacks
            </span>
        </div>
        <div class="card-body px-0 pt-0 pb-4">
            <?php if(empty($feedbacks)): ?>
                <div class="text-muted text-center py-5" style="font-size: 1.18rem;">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <div>No feedback submitted yet.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive px-3">
                    <table class="table table-hover align-middle mb-0" style="border-radius: 0 0 32px 32px; overflow: hidden; background: transparent;">
                        <thead style="background: linear-gradient(90deg, #eafaf1 0%, #d3f9f7 100%);">
                            <tr>
                                <th style="width: 110px; border: none;">Request</th>
                                <th style="width: 140px; border: none;">Bag</th>
                                <th style="width: 180px; border: none;">Student</th>
                                <th style="border: none;">Feedback</th>
                                <th style="width: 170px; border: none;">Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($feedbacks as $fb): ?>
                            <tr class="feedback-row" style="transition: background 0.2s;">
                                <td>
                                    <span class="badge rounded-pill" style="background: linear-gradient(90deg, #23d5ab 0%, #23a6d5 100%); color: #fff; font-size:1rem; box-shadow:0 2px 8px #23d5ab22;">
                                        #<?php echo $fb['request_id']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-primary"><?php echo htmlspecialchars($fb['bag_number']); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($fb['full_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo date('M j, Y', strtotime($fb['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="p-3 rounded-4 shadow-sm feedback-bubble" style="
                                        background: linear-gradient(90deg, #f8f9fa 60%, #eafaf1 100%);
                                        border-left: 5px solid #23d5ab;
                                        font-size: 1.09rem;
                                        position: relative;
                                        min-width: 180px;
                                        box-shadow: 0 2px 12px #23d5ab11;
                                        transition: box-shadow 0.2s, background 0.2s;
                                    ">
                                        <i class="fas fa-quote-left" style="color:#23d5ab; opacity:0.5;" class="me-2"></i>
                                        <?php echo nl2br(htmlspecialchars($fb['feedback_text'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge rounded-pill bg-secondary text-dark px-4 py-2" style="font-size:1rem;box-shadow:0 2px 8px #23d5ab22;">
                                        <?php echo date('M j, Y H:i', strtotime($fb['created_at'])); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal for requests list -->
<div class="modal fade" id="requestsModal" tabindex="-1" aria-labelledby="requestsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="background: #fff;">
            <div class="modal-header">
                <h5 class="modal-title" id="requestsModalLabel">Laundry Requests</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="requestsModalBody">
                <!-- Table will be injected here -->
            </div>
        </div>
    </div>
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

// Animate stat numbers on load
function animateStatNumbers() {
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach(stat => {
        const target = parseInt(stat.textContent);
        const increment = target / 50;
        let current = 0;
        stat.textContent = '0';
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
animateStatNumbers();

// Animate cards on scroll
function addScrollAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.2 });
    document.querySelectorAll('.stat-card').forEach(el => {
        observer.observe(el);
    });
}
addScrollAnimations();

// Mouse follow effect
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

// Animate card on click
function animateCard(card) {
    card.style.transform = 'scale(0.95)';
    setTimeout(() => {
        card.style.transform = '';
    }, 150);
}

// Make tiles interactive
document.querySelectorAll('.tile-interactive').forEach(tile => {
    tile.addEventListener('click', function() {
        const status = this.getAttribute('data-status');
        showRequests(status);
    });
});

// Show requests by status in modal
function showRequests(status) {
    // Requests data (PHP to JS)
    const requestsData = <?php
        $stmt = $db->query("SELECT r.*, u.full_name, u.phone, u.room_number, u.hostel_block FROM laundry_requests r JOIN users u ON r.student_id = u.user_id");
        $allRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($allRequests);
    ?>;

    // Filter by status
    const filtered = requestsData.filter(r => r.status === status);

    // Modal title
    let title = '';
    if(status === 'submitted') title = 'Submitted Laundry Requests';
    else if(status === 'processing') title = 'Processing Laundry Requests';
    else if(status === 'delivered') title = 'Delivered Laundry Requests';

    document.getElementById('requestsModalLabel').textContent = title;

    // Build table
    let html = '';
    if(filtered.length === 0) {
        html = `<div class="text-center py-4">
            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
            <div>No requests found for this status.</div>
        </div>`;
    } else {
        html = `<div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Request ID</th>
                    <th>Bag Number</th>
                    <th>Student</th>
                    <th>Room</th>
                    <th>Pickup Date</th>
                    <th>Expected Delivery</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>`;
        filtered.forEach(r => {
            html += `<tr style="cursor:pointer" onclick="redirectToManageRequest(${r.request_id})">
                <td><strong>#${r.request_id}</strong></td>
                <td>${r.bag_number}</td>
                <td>
                    <strong>${r.full_name}</strong><br>
                    <small class="text-muted">${r.phone}</small>
                </td>
                <td>Block ${r.hostel_block} - ${r.room_number}</td>
                <td>${r.pickup_date ? (new Date(r.pickup_date)).toLocaleDateString() : '-'}</td>
                <td>${r.expected_delivery ? (new Date(r.expected_delivery)).toLocaleDateString() : '-'}</td>
                <td><span class="badge bg-${
                    r.status === 'submitted' ? 'warning' :
                    r.status === 'processing' ? 'info' :
                    r.status === 'delivered' ? 'success' : 'secondary'
                }">${r.status.charAt(0).toUpperCase() + r.status.slice(1)}</span></td>
            </tr>`;
        });
        html += `</tbody></table></div>`;
    }
    document.getElementById('requestsModalBody').innerHTML = html;

    // Show modal
    var modal = new bootstrap.Modal(document.getElementById('requestsModal'));
    modal.show();
}

// Redirect to Manage Requests page and open the specific request
function redirectToManageRequest(requestId) {
    window.location.href = "staff_manage_requests.php?open_request_id=" + encodeURIComponent(requestId);
}
</script>
</body>
</html>