<?php
require_once 'freshfold_config.php';
requireLogin();

// Only admin can access
if ($_SESSION['user_type'] !== 'admin') {
    redirect('login_page.php');
}

$database = new Database();
$db = $database->getConnection();

// Fetch all issues reported by students
$query = "SELECT i.*, u.full_name, lr.bag_number
          FROM issues i
          JOIN users u ON i.student_id = u.user_id
          LEFT JOIN laundry_requests lr ON i.request_id = lr.request_id
          ORDER BY i.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle admin reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_issue'])) {
    $issue_id = $_POST['issue_id'];
    $reply = $_POST['admin_reply'];
    $update = $db->prepare("UPDATE issues SET admin_reply = :reply, reply_at = NOW() WHERE issue_id = :id");
    $update->execute([':reply' => $reply, ':id' => $issue_id]);
    header("Location: admin_issue_management.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Issue Management - FreshFold</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c5aa0;
            --sidebar-width: 250px;
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
            padding: 40px 30px 30px 30px;
            min-height: 100vh;
            z-index: 10;
            position: relative;
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
        .welcome-card-content {
            position: relative;
            z-index: 2;
        }
        .welcome-card h2 {
            margin-bottom: 10px;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .card {
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(44,90,160,0.08);
            border: none;
            margin-bottom: 30px;
        }
        .card-body {
            background: rgba(255,255,255,0.95);
            border-radius: 18px;
        }
        .table thead th {
            background: #e9ecef;
            color: #273043;
            font-weight: 600;
            vertical-align: middle;
        }
        .table td, .table th {
            vertical-align: middle;
        }
        .admin-reply-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .admin-reply-form input[type="text"] {
            min-width: 180px;
        }
        .badge.bg-success.bg-opacity-75 {
            background-color: #28a745 !important;
            opacity: 0.85 !important;
            color: #fff !important;
        }
        @media (max-width: 991px) {
            .main-content {
                margin-left: 0;
                padding: 20px 5px;
            }
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
                padding: 10px 0;
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
    </style>
</head>
<body>

<div class="particles" id="particles"></div>

<div class="sidebar" id="sidebar">
    <div class="brand">
        <i class="fas fa-tshirt fa-2x mb-2"></i>
        <h4>FreshFold</h4>
        <small>Admin Panel</small>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a class="nav-link" href="admin_manage_requests.php"><i class="fas fa-tasks"></i> Manage Requests</a>
        <a class="nav-link" href="users.php"><i class="fas fa-users"></i> Users</a>
        <a class="nav-link active" href="admin_issue_management.php"><i class="fas fa-exclamation-triangle"></i> Issue Management</a>
        <a class="nav-link" href="admin_payments.php"><i class="fas fa-credit-card"></i> Payment Management</a>
        <a class="nav-link" href="profile_page.php"><i class="fas fa-user"></i> Profile</a>
        <hr style="border-color: rgba(255,255,255,0.2); margin: 20px;">
        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</div>

<div class="main-content">
    <div class="welcome-card">
        <div class="welcome-card-content">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                    <p class="mb-2">You have admin access to all FreshFold system features.</p>
                    <small class="opacity-75">
                        <?php echo ucfirst($_SESSION['user_type']); ?> • <?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>
                    </small>
                </div>
                <div class="col-md-4 text-end">
                    <i class="fas fa-user-shield fa-5x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm animated-fadeInUp">
        <div class="card-body">
            <h2 class="mb-4" style="color:#273043;"><i class="fas fa-exclamation-triangle me-2"></i>Student Reported Issues</h2>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Bag Number</th>
                            <th>Issue</th>
                            <th>Reported At</th>
                            <th>Admin Reply</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($issues)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                No issues reported by students.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($issues as $issue): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($issue['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($issue['bag_number'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($issue['description']); ?></td>
                            <td><?php echo date('M j, Y H:i', strtotime($issue['created_at'])); ?></td>
                            <td>
                                <?php if (!empty($issue['admin_reply'])): ?>
                                    <span class="badge bg-success bg-opacity-75 mb-1"><?php echo htmlspecialchars($issue['admin_reply']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">No reply yet</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" class="admin-reply-form">
                                    <input type="hidden" name="issue_id" value="<?php echo $issue['issue_id']; ?>">
                                    <input type="text" name="admin_reply" class="form-control form-control-sm" placeholder="Reply..." required>
                                    <button type="submit" name="reply_issue" class="btn btn-primary btn-sm">Reply</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
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

// Animate card on scroll
function addScrollAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animated-fadeInUp');
            }
        });
    }, { threshold: 0.2 });
    document.querySelectorAll('.card').forEach(el => {
        observer.observe(el);
    });
}
addScrollAnimations();
</script>
</body>
</html>