<?php
require_once 'freshfold_config.php';

// Check if user is logged in
requireLogin();

// Handle logout
if(isset($_GET['logout'])) {
    User::logout();
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();
$user = new User($db);

// Get current user data
$current_user = $user->getUserById($_SESSION['user_id']);
$success_message = "";
$error_message = "";

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_profile'])) {
        // Validate input
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $gender = trim($_POST['gender']);

        // Only require hostel_block and room_number for students
        if($current_user['user_type'] === 'student') {
            $hostel_block = trim($_POST['hostel_block']);
            $room_number = trim($_POST['room_number']);
        } else {
            $hostel_block = '';
            $room_number = '';
        }

        // Basic validation
        if(
            empty($full_name) || empty($email) || empty($phone) || empty($gender) ||
            ($current_user['user_type'] === 'student' && (empty($hostel_block) || empty($room_number)))
        ) {
            $error_message = "All fields are required.";
        } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format.";
        } elseif(!preg_match('/^[0-9]{10}$/', $phone)) {
            $error_message = "Phone number must be 10 digits.";
        } else {
            // Check if email already exists for another user
            $check_email_query = "SELECT user_id FROM users WHERE email = :email AND user_id != :user_id";
            $check_stmt = $db->prepare($check_email_query);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $check_stmt->execute();

            if($check_stmt->rowCount() > 0) {
                $error_message = "Email already exists for another user.";
            } else {
                // Update user profile (add gender)
                $update_query = "UPDATE users SET 
                    full_name = :full_name,
                    email = :email,
                    phone = :phone,
                    hostel_block = :hostel_block,
                    room_number = :room_number,
                    gender = :gender
                    WHERE user_id = :user_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':full_name', $full_name);
                $update_stmt->bindParam(':email', $email);
                $update_stmt->bindParam(':phone', $phone);
                $update_stmt->bindParam(':hostel_block', $hostel_block);
                $update_stmt->bindParam(':room_number', $room_number);
                $update_stmt->bindParam(':gender', $gender);
                $update_stmt->bindParam(':user_id', $_SESSION['user_id']);

                if($update_stmt->execute()) {
                    $success_message = "Profile updated successfully!";
                    // Refresh user data
                    $current_user = $user->getUserById($_SESSION['user_id']);
                } else {
                    $error_message = "Failed to update profile. Please try again.";
                }
            }
        }
    }
    
    // Handle password change
    if(isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if(empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif(strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters long.";
        } else {
            // Verify current password
            if(password_verify($current_password, $current_user['password_hash'])) {
                // Update password
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_password_query = "UPDATE users SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP WHERE user_id = :user_id";
                $update_password_stmt = $db->prepare($update_password_query);
                $update_password_stmt->bindParam(':password_hash', $new_password_hash);
                $update_password_stmt->bindParam(':user_id', $_SESSION['user_id']);
                
                if($update_password_stmt->execute()) {
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Error changing password. Please try again.";
                }
            } else {
                $error_message = "Current password is incorrect.";
            }
        }
    }
}

// Get user statistics
$laundryRequest = new LaundryRequest($db);
$user_requests = $laundryRequest->getStudentRequests($_SESSION['user_id']);
$total_requests = count($user_requests);
$recent_request = !empty($user_requests) ? $user_requests[0] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - FreshFold</title>
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

        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
            z-index: 10;
            position: relative;
        }

        /* Profile cards */
        .profile-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 30px;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
            overflow: hidden;
        }

        .profile-card::before {
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

        .profile-card:hover::before {
            transform: rotate(180deg);
        }

        .profile-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
        }

        .profile-card-content {
            position: relative;
            z-index: 2;
        }

        /* Profile header */
        .profile-header {
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

        .profile-header::before {
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

        .profile-header-content {
            position: relative;
            z-index: 2;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--gradient-2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            margin: 0 auto 20px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .profile-avatar:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }

        /* Form styling */
        .form-control {
            border-radius: 15px;
            border: 2px solid rgba(44, 90, 160, 0.2);
            padding: 12px 20px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.25);
            background: white;
            transform: translateY(-2px);
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

        .btn-primary {
            background: var(--gradient-2);
            border: none;
            border-radius: 15px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
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
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(44, 90, 160, 0.4);
        }

        .btn-secondary {
            background: rgba(108, 117, 125, 0.8);
            border: none;
            border-radius: 15px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: rgba(108, 117, 125, 1);
            transform: translateY(-2px);
        }

        /* Stats section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-item {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* Alert styling */
        .alert {
            border-radius: 15px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
            animation: slideInFromTop 0.5s ease-out;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border-left: 4px solid #dc3545;
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
            
            .profile-card {
                padding: 20px;
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
    <div class="brand">
        <i class="fas fa-tshirt fa-2x mb-2"></i>
        <h4>FreshFold</h4>
        <small>Admin Panel</small>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='admin_dashboard.php') echo ' active'; ?>" href="admin_dashboard.php">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='admin_manage_requests.php') echo ' active'; ?>" href="admin_manage_requests.php">
            <i class="fas fa-tasks"></i> Manage Requests
        </a>
        <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='users.php') echo ' active'; ?>" href="users.php">
            <i class="fas fa-users"></i> Users
        </a>
        <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='admin_issue_management.php') echo ' active'; ?>" href="admin_issue_management.php">
            <i class="fas fa-exclamation-triangle"></i> Issue Management
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

<!-- Main Content -->
<div class="main-content">
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-header-content">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                <div class="col-md-6">
                    <h2 class="mb-2"><?php echo htmlspecialchars($current_user['full_name']); ?></h2>
                    <p class="mb-1"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($current_user['email']); ?></p>
                    <p class="mb-1"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($current_user['phone']); ?></p>
                    <p class="mb-1"><i class="fas fa-building me-2"></i>Floor <?php echo htmlspecialchars($current_user['hostel_block']); ?> - Room <?php echo htmlspecialchars($current_user['room_number']); ?></p>
                    <span class="badge bg-light text-dark px-3 py-2 rounded-pill">
                        <?php echo ucfirst($current_user['user_type']); ?>
                    </span>
                </div>
                <div class="col-md-3">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $total_requests; ?></div>
                            <div class="stat-label">Total Requests</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Display Alerts -->
    <?php if($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Profile Information -->
        <div class="col-md-8">
            <div class="profile-card">
                <div class="profile-card-content">
                    <h4 class="mb-4"><i class="fas fa-user-edit me-2"></i>Profile Information</h4>
                    
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($current_user['full_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($current_user['phone']); ?>" required>
                            </div>
                            <?php if($current_user['user_type'] === 'student'): ?>
                            <div class="col-md-3 mb-3">
                                <label for="hostel_block" class="form-label">Floor</label>
                                <select class="form-select" id="hostel_block" name="hostel_block" required>
                                    <option value="">Choose...</option>
                                    <option value="-1" <?php if($current_user['hostel_block']=='-1') echo 'selected'; ?>>-1</option>
                                    <option value="Ground Floor" <?php if($current_user['hostel_block']=='Ground Floor') echo 'selected'; ?>>Ground Floor</option>
                                    <option value="1st Floor" <?php if($current_user['hostel_block']=='1st Floor') echo 'selected'; ?>>1st Floor</option>
                                    <option value="2nd Floor" <?php if($current_user['hostel_block']=='2nd Floor') echo 'selected'; ?>>2nd Floor</option>
                                    <option value="3rd Floor" <?php if($current_user['hostel_block']=='3rd Floor') echo 'selected'; ?>>3rd Floor</option>
                                    <option value="4th Floor" <?php if($current_user['hostel_block']=='4th Floor') echo 'selected'; ?>>4th Floor</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="room_number" class="form-label">Room Number</label>
                                <input type="text" class="form-control" id="room_number" name="room_number" 
                                       value="<?php echo htmlspecialchars($current_user['room_number']); ?>" required>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php if($current_user['gender']=='male') echo 'selected'; ?>>Male</option>
                                    <option value="female" <?php if($current_user['gender']=='female') echo 'selected'; ?>>Female</option>
                                    <option value="other" <?php if($current_user['gender']=='other') echo 'selected'; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($current_user['username']); ?>" disabled>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="user_type" class="form-label">User Type</label>
                                <input type="text" class="form-control" id="user_type" name="user_type" 
                                       value="<?php echo ucfirst($current_user['user_type']); ?>" disabled>
                                <small class="text-muted">User type cannot be changed</small>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                            <a href="dashboard_page.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Security Settings -->
        <div class="col-md-4">
            <div class="profile-card">
                <div class="profile-card-content">
                    <h5 class="mb-4"><i class="fas fa-lock me-2"></i>Change Password</h5>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary w-100">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}

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
</script>
</body>
</html>