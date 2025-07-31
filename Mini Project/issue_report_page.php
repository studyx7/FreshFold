<?php
require_once 'freshfold_config.php';

// Check if user is logged in
requireLogin();

// Get user data
$database = new Database();
$db = $database->getConnection();
$laundryRequest = new LaundryRequest($db);

// Get user's active requests for the dropdown
$user_requests = $laundryRequest->getStudentRequests($_SESSION['user_id']);
$active_requests = array_filter($user_requests, function($request) {
    return in_array($request['status'], ['pending', 'picked_up', 'in_progress', 'ready']);
});

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $issue_type = $_POST['issue_type'];
    $request_id = !empty($_POST['request_id']) ? $_POST['request_id'] : null;
    $description = $_POST['description'];
    $priority = $_POST['priority'];
    $contact_preference = $_POST['contact_preference'];
    
    // Insert issue into database
    $query = "INSERT INTO issues (student_id, request_id, issue_type, description, priority, contact_preference, status) 
              VALUES (:student_id, :request_id, :issue_type, :description, :priority, :contact_preference, 'open')";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":student_id", $_SESSION['user_id']);
    $stmt->bindParam(":request_id", $request_id);
    $stmt->bindParam(":issue_type", $issue_type);
    $stmt->bindParam(":description", $description);
    $stmt->bindParam(":priority", $priority);
    $stmt->bindParam(":contact_preference", $contact_preference);
    
    if ($stmt->execute()) {
        $issue_id = $db->lastInsertId();
        showAlert("Issue reported successfully! Issue ID: #" . str_pad($issue_id, 4, '0', STR_PAD_LEFT), 'success');
        redirect('report_issue_page.php');
    } else {
        showAlert("Error reporting issue. Please try again.", 'danger');
    }
}

// Get user's previous issues
$issues_query = "SELECT i.*, lr.bag_number 
                 FROM issues i 
                 LEFT JOIN laundry_requests lr ON i.request_id = lr.request_id 
                 WHERE i.student_id = :student_id 
                 ORDER BY i.created_at DESC";
$issues_stmt = $db->prepare($issues_query);
$issues_stmt->bindParam(":student_id", $_SESSION['user_id']);
$issues_stmt->execute();
$user_issues = $issues_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Issue - FreshFold</title>
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

        /* Sidebar styles (same as dashboard) */
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

        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
            z-index: 10;
            position: relative;
        }

        /* Form styles */
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 30px;
            animation: slideInFromBottom 1s ease-out;
        }

        @keyframes slideInFromBottom {
            0% { transform: translateY(50px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.25);
            background: rgba(255, 255, 255, 1);
        }

        .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.25);
            background: rgba(255, 255, 255, 1);
        }

        .btn-primary {
            background: var(--gradient-2);
            border: none;
            border-radius: 15px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
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

        .btn-primary:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(44, 90, 160, 0.3);
        }

        /* Issues history */
        .issues-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: slideInFromRight 1s ease-out;
        }

        @keyframes slideInFromRight {
            0% { transform: translateX(100px); opacity: 0; }
            100% { transform: translateX(0); opacity: 1; }
        }

        .issue-item {
            background: rgba(248, 249, 250, 0.8);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .issue-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .issue-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(44, 90, 160, 0.05), transparent);
            transform: translateX(-100%);
            transition: transform 0.5s ease;
        }

        .issue-item:hover::before {
            transform: translateX(100%);
        }

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

        .status-open { background-color: #fff3cd; color: #856404; }
        .status-in_progress { background-color: #d1ecf1; color: #0c5460; }
        .status-resolved { background-color: #d4edda; color: #155724; }
        .status-closed { background-color: #e2e3e5; color: #41464b; }

        .priority-low { color: #28a745; }
        .priority-medium { color: #ffc107; }
        .priority-high { color: #dc3545; }

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
        }

        .page-title {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 20px;
            padding: 20px 30px;
            margin-bottom: 30px;
            text-align: center;
            animation: slideInFromTop 0.8s ease-out;
        }

        @keyframes slideInFromTop {
            0% { transform: translateY(-50px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }

        .page-title h2 {
            margin: 0;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>

<!-- Mobile menu toggle -->
<button class="btn btn-primary d-md-none position-fixed" style="top: 20px; left: 20px; z-index: 1001;" onclick="toggleSidebar()">
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
        <a class="nav-link" href="my_requests_page.php">
            <i class="fas fa-list"></i> My Requests
        </a>
        <a class="nav-link active" href="report_issue_page.php">
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
    <!-- Page Title -->
    <div class="page-title">
        <h2><i class="fas fa-exclamation-triangle me-2"></i>Report an Issue</h2>
        <p class="mb-0">Having trouble with your laundry service? Let us know!</p>
    </div>

    <!-- Alerts -->
    <?php displayAlerts(); ?>

    <div class="row">
        <!-- Issue Report Form -->
        <div class="col-lg-6">
            <div class="form-container">
                <h4 class="mb-4"><i class="fas fa-edit me-2"></i>Submit New Issue</h4>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="issue_type" class="form-label">Issue Type *</label>
                        <select class="form-select" id="issue_type" name="issue_type" required>
                            <option value="">Select Issue Type</option>
                            <option value="damaged_item">Damaged Item</option>
                            <option value="missing_item">Missing Item</option>
                            <option value="wrong_item">Wrong Item Received</option>
                            <option value="poor_cleaning">Poor Cleaning Quality</option>
                            <option value="delay">Delivery Delay</option>
                            <option value="pickup_issue">Pickup Issue</option>
                            <option value="staff_behavior">Staff Behavior</option>
                            <option value="billing">Billing Issue</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="request_id" class="form-label">Related Request (Optional)</label>
                        <select class="form-select" id="request_id" name="request_id">
                            <option value="">Select Request (if applicable)</option>
                            <?php foreach($active_requests as $request): ?>
                            <option value="<?php echo $request['request_id']; ?>">
                                <?php echo $request['bag_number']; ?> - <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="priority" class="form-label">Priority *</label>
                        <select class="form-select" id="priority" name="priority" required>
                            <option value="">Select Priority</option>
                            <option value="low">Low - General inquiry</option>
                            <option value="medium">Medium - Standard issue</option>
                            <option value="high">High - Urgent matter</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="4" 
                                  placeholder="Please describe your issue in detail..." required></textarea>
                        <div class="form-text">Be as specific as possible to help us resolve your issue quickly.</div>
                    </div>

                    <div class="mb-3">
                        <label for="contact_preference" class="form-label">Preferred Contact Method *</label>
                        <select class="form-select" id="contact_preference" name="contact_preference" required>
                            <option value="">Select Contact Method</option>
                            <option value="email">Email</option>
                            <option value="phone">Phone Call</option>
                            <option value="sms">SMS</option>
                            <option value="in_person">In Person</option>
                        </select>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Submit Issue
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Issues History -->
        <div class="col-lg-6">
            <div class="issues-container">
                <h4 class="mb-4"><i class="fas fa-history me-2"></i>Your Issues History</h4>
                
                <?php if(empty($user_issues)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <p class="text-muted">No issues reported yet</p>
                    <small class="text-muted">We're glad everything is working smoothly!</small>
                </div>
                <?php else: ?>
                    <?php foreach($user_issues as $issue): ?>
                    <div class="issue-item">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo ucfirst(str_replace('_', ' ', $issue['issue_type'])); ?>
                                </h6>
                                <small class="text-muted">
                                    Issue #<?php echo str_pad($issue['issue_id'], 4, '0', STR_PAD_LEFT); ?>
                                    <?php if($issue['bag_number']): ?>
                                        • Related to: <?php echo $issue['bag_number']; ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="status-badge status-<?php echo $issue['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $issue['status'])); ?>
                                </span>
                                <br>
                                <small class="priority-<?php echo $issue['priority']; ?>">
                                    <i class="fas fa-flag"></i> <?php echo ucfirst($issue['priority']); ?>
                                </small>
                            </div>
                        </div>
                        
                        <p class="mb-2"><?php echo htmlspecialchars($issue['description']); ?></p>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> 
                                <?php echo date('M j, Y g:i A', strtotime($issue['created_at'])); ?>
                            </small>
                            <small class="text-muted">
                                <i class="fas fa-phone"></i> 
                                <?php echo ucfirst($issue['contact_preference']); ?>
                            </small>
                        </div>
                        
                        <?php if($issue['response'] && $issue['status'] != 'open'): ?>
                        <div class="mt-3 pt-3 border-top">
                            <h6 class="text-success"><i class="fas fa-reply me-2"></i>Response:</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($issue['response']); ?></p>
                            <?php if($issue['resolved_at']): ?>
                            <small class="text-muted">
                                Resolved on <?php echo date('M j, Y g:i A', strtotime($issue['resolved_at'])); ?>
                            </small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle sidebar for mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}

// Form validation and enhancement
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const issueType = document.getElementById('issue_type');
    const description = document.getElementById('description');
    
    // Dynamic placeholder text based on issue type
    const placeholders = {
        'damaged_item': 'Please describe which item was damaged and how (e.g., torn shirt, stained pants, etc.)',
        'missing_item': 'Please list the missing items and when you last saw them',
        'wrong_item': 'Please describe what item you received and what you were expecting',
        'poor_cleaning': 'Please describe the cleaning issues (e.g., stains not removed, bad smell, etc.)',
        'delay': 'Please mention your expected delivery date and current status',
        'pickup_issue': 'Please describe the pickup problem you experienced',
        'staff_behavior': 'Please describe the incident with staff member',
        'billing': 'Please describe the billing issue or discrepancy',
        'other': 'Please describe your issue in detail'
    };
    
    issueType.addEventListener('change', function() {
        const selectedType = this.value;
        if (placeholders[selectedType]) {
            description.placeholder = placeholders[selectedType];
        } else {
            description.placeholder = 'Please describe your issue in detail...';
        }
    });
    
    // Form submission animation
    form.addEventListener('submit', function(e) {
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
        submitBtn.disabled = true;
    });
    
    // Auto-resize textarea
    description.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
});

// Add smooth scrolling for better UX
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
            behavior: 'smooth'
        });
    });
});
</script>
</body>
</html>