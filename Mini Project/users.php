<?php
require_once 'freshfold_config.php';
requireLogin();

// Only admin can access this page
if (User::getUserType() !== 'admin') {
    showAlert('Access denied. Admins only.', 'danger');
    redirect('admin_dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Handle activate/deactivate
if (isset($_POST['toggle_active']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $new_status = $_POST['is_active'] ? 0 : 1;
    $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
    $stmt->execute([$new_status, $user_id]);
    showAlert('User status updated.', 'success');
    redirect('users.php');
}

// Handle delete user
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    // Prevent admin from deleting themselves
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        showAlert('User deleted.', 'success');
    } else {
        showAlert('You cannot delete your own account.', 'danger');
    }
    redirect('users.php');
}

// Handle add user
if (isset($_POST['add_user'])) {
    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $user_type = $_POST['user_type'];
    $hostel_block = $_POST['hostel_block'] ?? null;
    $room_number = $_POST['room_number'] ?? null;
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check for duplicate username/email
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetchColumn() > 0) {
        showAlert('Username or email already exists.', 'danger');
    } else {
        $stmt = $db->prepare("INSERT INTO users (full_name, username, email, phone, user_type, hostel_block, room_number, password_hash, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$full_name, $username, $email, $phone, $user_type, $hostel_block, $room_number, $password]);
        showAlert('User added successfully.', 'success');
        redirect('users.php');
    }
}

// Handle edit user
if (isset($_POST['edit_user']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $user_type = $_POST['user_type'];
    $hostel_block = $_POST['hostel_block'] ?? null;
    $room_number = $_POST['room_number'] ?? null;

    // Optional password update
    $update_password = '';
    $params = [$full_name, $username, $email, $phone, $user_type, $hostel_block, $room_number, $user_id];
    if (!empty($_POST['password'])) {
        $update_password = ', password_hash = ?';
        $params = [$full_name, $username, $email, $phone, $user_type, $hostel_block, $room_number, password_hash($_POST['password'], PASSWORD_DEFAULT), $user_id];
    }

    $stmt = $db->prepare("UPDATE users SET full_name=?, username=?, email=?, phone=?, user_type=?, hostel_block=?, room_number=? $update_password WHERE user_id=?");
    $stmt->execute($params);
    showAlert('User updated successfully.', 'success');
    redirect('users.php');
}

// Filter by user type and search
$user_type = $_GET['user_type'] ?? '';
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM users WHERE 1";
$params = [];
if ($user_type && in_array($user_type, ['student', 'staff', 'admin'])) {
    $query .= " AND user_type = ?";
    $params[] = $user_type;
}
if ($search) {
    $query .= " AND (full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY user_type, full_name";
$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate user statistics
$total_users = count($users);
$total_students = count(array_filter($users, fn($u) => $u['user_type'] === 'student'));
$total_staff = count(array_filter($users, fn($u) => $u['user_type'] === 'staff'));
$total_admins = count(array_filter($users, fn($u) => $u['user_type'] === 'admin'));
$active_users = count(array_filter($users, fn($u) => $u['is_active'] == 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Admin - FreshFold</title>
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
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
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
        .stat-card-content {
            position: relative;
            z-index: 2;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
            background: linear-gradient(45deg, var(--primary-color), #1e3d6f);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        .filters-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .user-table {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .table th {
            background-color: #f8f9fa;
            border: none;
            font-weight: 600;
            color: #495057;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(44, 90, 160, 0.05);
        }
        .badge-admin { 
            background: linear-gradient(45deg, #6f42c1, #5a2d91); 
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        .badge-staff { 
            background: linear-gradient(45deg, #0d6efd, #0b5ed7); 
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        .badge-student { 
            background: linear-gradient(45deg, #198754, #146c43); 
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        .badge-inactive { 
            background: linear-gradient(45deg, #dc3545, #bb2d3b); 
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        .badge-active { 
            background: linear-gradient(45deg, #28a745, #1e7e34); 
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), #1e3d6f);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(45deg, #1e3d6f, var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 90, 160, 0.3);
        }
        .btn-success {
            background: linear-gradient(45deg, #28a745, #1e7e34);
            border: none;
            color: white;
        }
        .btn-success:hover {
            background: linear-gradient(45deg, #1e7e34, #28a745);
            transform: translateY(-2px);
        }
        .btn-danger {
            background: linear-gradient(45deg, #dc3545, #bb2d3b);
            border: none;
            color: white;
        }
        .btn-danger:hover {
            background: linear-gradient(45deg, #bb2d3b, #dc3545);
            transform: translateY(-2px);
        }
        .modal-content {
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
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
        <small>Admin Panel</small>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link" href="admin_dashboard.php">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a class="nav-link" href="admin_manage_requests.php">
            <i class="fas fa-tasks"></i> Manage Requests
        </a>
        <a class="nav-link active" href="users.php">
            <i class="fas fa-users"></i> Users
        </a>
        <a class="nav-link" href="admin_issue_management.php">
            <i class="fas fa-exclamation-triangle"></i> Issue Management
        </a>
        <a class="nav-link" href="admin_payments.php">
            <i class="fas fa-credit-card"></i> Payment Management
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
    
    <div class="welcome-card">
        <div class="welcome-card-content">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-users me-2"></i>User Management</h2>
                    <p class="mb-0 opacity-75">Manage all system users and their permissions</p>
                </div>
                <div>
                    <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- User Statistics -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card" onclick="window.location.href='users.php'">
                <div class="stat-card-content text-center">
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card" onclick="window.location.href='users.php?user_type=student'">
                <div class="stat-card-content text-center">
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Students</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card" onclick="window.location.href='users.php?user_type=staff'">
                <div class="stat-card-content text-center">
                    <div class="stat-number"><?php echo $total_staff; ?></div>
                    <div class="stat-label">Staff</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card">
                <div class="stat-card-content text-center">
                    <div class="stat-number"><?php echo $active_users; ?></div>
                    <div class="stat-label">Active Users</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <form class="row g-3" method="get">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Filter by Type</label>
                <select name="user_type" class="form-select" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="student" <?php if($user_type=='student') echo 'selected'; ?>>Student</option>
                    <option value="staff" <?php if($user_type=='staff') echo 'selected'; ?>>Staff</option>
                    <option value="admin" <?php if($user_type=='admin') echo 'selected'; ?>>Admin</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Search Users</label>
                <input type="text" name="search" class="form-control" placeholder="Search name, email, username..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="users.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="user-table">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email / Username</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Phone</th>
                        <th>Gender</th>
                        <th>Hostel/Room</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($users)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                            <div>No users found</div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td><?php echo $user['user_id']; ?></td>
                        <td><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></td>
                        <td>
                            <div><?php echo htmlspecialchars($user['email']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($user['username']); ?></small>
                        </td>
                        <td>
                            <span class="badge 
                                <?php
                                    if($user['user_type']=='admin') echo 'badge-admin';
                                    elseif($user['user_type']=='staff') echo 'badge-staff';
                                    else echo 'badge-student';
                                ?>">
                                <?php echo ucfirst($user['user_type']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $user['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                        <td>
                            <?php
                                if ($user['gender'] === 'male') echo 'Male';
                                elseif ($user['gender'] === 'female') echo 'Female';
                                elseif ($user['gender'] === 'other') echo 'Other';
                                else echo '-';
                            ?>
                        </td>
                        <td>
                            <?php
                            if($user['user_type']=='student') {
                                echo 'Floor ' . htmlspecialchars($user['hostel_block']) . ', Room ' . htmlspecialchars($user['room_number']);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if($user['user_type'] !== 'admin' || $user['user_id'] != $_SESSION['user_id']): ?>
                            <div class="btn-group" role="group">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <input type="hidden" name="is_active" value="<?php echo $user['is_active']; ?>">
                                    <button type="submit" name="toggle_active" class="btn btn-sm <?php echo $user['is_active'] ? 'btn-danger' : 'btn-success'; ?>">
                                        <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <button class="btn btn-sm btn-primary" onclick='openEditModal(<?php echo json_encode($user); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <button type="submit" name="delete_user" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                            <?php else: ?>
                                <span class="text-muted">Protected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="add_user" value="1">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">User Type</label>
                        <select name="user_type" class="form-select" required onchange="toggleStudentFields(this.value)">
                            <option value="student">Student</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3 student-fields">
                        <label class="form-label">Floor</label>
                        <select name="hostel_block" class="form-select">
                            <option value="">Choose...</option>
                            <option value="-1">-1</option>
                            <option value="Ground Floor">Ground Floor</option>
                            <option value="1st Floor">1st Floor</option>
                            <option value="2nd Floor">2nd Floor</option>
                            <option value="3rd Floor">3rd Floor</option>
                            <option value="4th Floor">4th Floor</option>
                        </select>
                    </div>
                    <div class="mb-3 student-fields">
                        <label class="form-label">Room Number</label>
                        <input type="text" name="room_number" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editUserForm">
                <input type="hidden" name="edit_user" value="1">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" id="edit_full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" id="edit_username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" id="edit_email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" id="edit_phone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">User Type</label>
                        <select name="user_type" class="form-select" id="edit_user_type" required>
                            <option value="student">Student</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Floor</label>
                        <select name="hostel_block" class="form-select" id="edit_hostel_block">
                            <option value="">Choose...</option>
                            <option value="-1">-1</option>
                            <option value="Ground Floor">Ground Floor</option>
                            <option value="1st Floor">1st Floor</option>
                            <option value="2nd Floor">2nd Floor</option>
                            <option value="3rd Floor">3rd Floor</option>
                            <option value="4th Floor">4th Floor</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Room Number</label>
                        <input type="text" name="room_number" class="form-control" id="edit_room_number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password (leave blank to keep unchanged)</label>
                        <input type="password" name="password" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
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

// Toggle sidebar for mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}

function toggleStudentFields(type) {
    var fields = document.querySelectorAll('.student-fields');
    fields.forEach(function(field) {
        field.style.display = (type === 'student') ? 'block' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Hide student fields if not student
    var userTypeSelect = document.querySelector('select[name="user_type"]');
    if (userTypeSelect) {
        toggleStudentFields(userTypeSelect.value);
        userTypeSelect.addEventListener('change', function() {
            toggleStudentFields(this.value);
        });
    }
});

function openEditModal(user) {
    document.getElementById('edit_user_id').value = user.user_id;
    document.getElementById('edit_full_name').value = user.full_name || '';
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_phone').value = user.phone || '';
    document.getElementById('edit_user_type').value = user.user_type;
    document.getElementById('edit_hostel_block').value = user.hostel_block || '';
    document.getElementById('edit_room_number').value = user.room_number || '';
    var modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}
</script>
</body>
</html>