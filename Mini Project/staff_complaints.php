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

// Handle staff reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_issue'])) {
    $issue_id = $_POST['issue_id'];
    $reply = trim($_POST['staff_reply']);
    $update = $db->prepare("UPDATE issues SET response = :reply WHERE issue_id = :id");
    $update->execute([':reply' => $reply, ':id' => $issue_id]);
    showAlert('Reply sent successfully.', 'success');
    redirect('staff_complaints.php');
}

// Fetch all student complaints/queries (issues)
$query = "SELECT i.*, u.full_name, u.email, lr.bag_number
          FROM issues i
          JOIN users u ON i.student_id = u.user_id
          LEFT JOIN laundry_requests lr ON i.request_id = lr.request_id
          ORDER BY i.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Complaints & Queries - Staff - FreshFold</title>
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
        }
        .sidebar .brand {
            text-align: center;
            padding: 0 20px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 30px;
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
            border-radius: 0 25px 25px 0;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.2);
            margin-right: 20px;
            transform: translateX(10px) scale(1.05);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
            z-index: 10;
            position: relative;
        }
        .complaints-card {
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
            animation: fadeInUp 0.7s cubic-bezier(.39,.575,.565,1.000) both;
        }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(40px);}
            100% { opacity: 1; transform: translateY(0);}
        }
        .table thead th {
            background: #f8f9fa;
        }
        .modal-content {
            border-radius: 15px;
        }
        .form-control, .form-select {
            border-radius: 10px;
        }
        .btn-primary {
            background: var(--primary-color);
            border: none;
        }
        .btn-primary:hover {
            background: #1e3d6f;
        }
        /* Fix modal stacking and interactivity for complaint details */
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
        <a class="nav-link" href="staff_dashboard.php">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a class="nav-link" href="staff_manage_requests.php">
            <i class="fas fa-tasks"></i> Manage Requests
        </a>
        <a class="nav-link active" href="staff_complaints.php">
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
    <?php displayAlerts(); ?>
    <div class="complaints-card">
        <h3 class="mb-4"><i class="fas fa-comments me-2"></i>Student Complaints & Queries</h3>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Bag No.</th>
                        <th>Type</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($issues)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                            <div>No complaints or queries found</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($issues as $issue): ?>
                    <tr>
                        <td><?php echo $issue['issue_id']; ?></td>
                        <td><?php echo htmlspecialchars($issue['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($issue['email']); ?></td>
                        <td><?php echo htmlspecialchars($issue['bag_number'] ?? '-'); ?></td>
                        <td><?php echo ucwords(str_replace('_',' ', $issue['issue_type'])); ?></td>
                        <td>
                            <span class="badge bg-<?php
                                if($issue['priority']=='high') echo 'danger';
                                elseif($issue['priority']=='medium') echo 'warning';
                                else echo 'success';
                            ?>">
                                <?php echo ucfirst($issue['priority']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php
                                if($issue['status']=='open') echo 'secondary';
                                elseif($issue['status']=='in_progress') echo 'info';
                                elseif($issue['status']=='resolved') echo 'success';
                                else echo 'dark';
                            ?>">
                                <?php echo ucwords(str_replace('_',' ', $issue['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y H:i', strtotime($issue['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $issue['issue_id']; ?>">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($issues)): ?>
    <?php foreach($issues as $issue): ?>
    <div class="modal fade" id="viewModal<?php echo $issue['issue_id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $issue['issue_id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="background: #fff; border-radius: 15px;">
                <form method="POST">
                    <input type="hidden" name="issue_id" value="<?php echo $issue['issue_id']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewModalLabel<?php echo $issue['issue_id']; ?>"><i class="fas fa-comment-dots me-2"></i>Complaint/Query Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <strong>Student:</strong> <?php echo htmlspecialchars($issue['full_name']); ?> (<?php echo htmlspecialchars($issue['email']); ?>)
                        </div>
                        <div class="mb-2">
                            <strong>Bag Number:</strong> <?php echo htmlspecialchars($issue['bag_number'] ?? '-'); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Type:</strong> <?php echo ucwords(str_replace('_',' ', $issue['issue_type'])); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Priority:</strong> <?php echo ucfirst($issue['priority']); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Status:</strong> <?php echo ucwords(str_replace('_',' ', $issue['status'])); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Description:</strong>
                            <div class="bg-light rounded p-2"><?php echo nl2br(htmlspecialchars($issue['description'])); ?></div>
                        </div>
                        <div class="mb-3">
                            <strong>Student's Preferred Contact:</strong> <?php echo ucfirst($issue['contact_preference']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Staff Response:</strong>
                            <?php if($issue['response']): ?>
                                <div class="bg-success bg-opacity-10 rounded p-2 mb-2"><?php echo nl2br(htmlspecialchars($issue['response'])); ?></div>
                            <?php endif; ?>
                            <textarea class="form-control" name="staff_reply" rows="3" placeholder="Type your reply here..." required><?php echo htmlspecialchars($issue['response']); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="reply_issue" class="btn btn-primary">Send Reply</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

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
</script>
</body>
</html>