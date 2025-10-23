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
$laundryRequest = new LaundryRequest($db);

// Handle status updates
if($_POST && isset($_POST['update_status'])) {
    $request_id = $_POST['request_id'];
    $new_status = $_POST['new_status'];
    $remarks = $_POST['remarks'] ?? '';
    
    if($laundryRequest->updateStatus($request_id, $new_status, $_SESSION['user_id'], $remarks)) {
        showAlert('Request status updated successfully!', 'success');
    } else {
        showAlert('Failed to update status. Please try again.', 'danger');
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Admin: Get all requests (no restriction)
$all_requests = $laundryRequest->getAllRequests($status_filter);

// Filter by search if provided
if($search) {
    $all_requests = array_filter($all_requests, function($request) use ($search) {
        return stripos($request['bag_number'], $search) !== false || 
               stripos($request['full_name'], $search) !== false ||
               stripos($request['room_number'], $search) !== false;
    });
}

// Status options
$status_options = [
    'submitted' => 'Request Submitted',
    'processing' => 'Processing',
    'delivered' => 'Delivered'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests - Admin - FreshFold</title>
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
        .request-table {
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
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: background 0.3s, color 0.3s;
        }
        .status-submitted { 
            background-color: #fff3cd; 
            color: #856404; 
            border: 1px solid #ffeaa7;
        }
        .status-processing { 
            background-color: #d1ecf1; 
            color: #0c5460; 
            border: 1px solid #bee5eb;
        }
        .status-delivered { 
            background-color: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb;
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
        .btn-info {
            background: linear-gradient(45deg, #17a2b8, #138496);
            border: none;
            color: white;
        }
        .btn-info:hover {
            background: linear-gradient(45deg, #138496, #17a2b8);
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
        <a class="nav-link active" href="admin_manage_requests.php">
            <i class="fas fa-tasks"></i> Manage Requests
        </a>
        <a class="nav-link" href="users.php">
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
                    <h2><i class="fas fa-tasks me-2"></i>Manage Laundry Requests</h2>
                    <p class="mb-0 opacity-75">Monitor and update all laundry request statuses</p>
                </div>
                <div>
                    <button class="btn btn-outline-light" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" class="row g-3" id="filterForm">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Filter by Status</label>
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All Requests</option>
                    <?php foreach($status_options as $value => $label): ?>
                    <option value="<?php echo $value; ?>" <?php echo $status_filter === $value ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Bag number, student name, or room..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="admin_manage_requests.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="stat-card" onclick="window.location.href='admin_manage_requests.php?status=submitted'">
                <div class="stat-card-content text-center">
                    <div class="stat-number"><?php echo count(array_filter($all_requests, fn($r) => $r['status'] === 'submitted')); ?></div>
                    <div class="stat-label">Submitted</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="stat-card" onclick="window.location.href='admin_manage_requests.php?status=processing'">
                <div class="stat-card-content text-center">
                    <div class="stat-number"><?php echo count(array_filter($all_requests, fn($r) => $r['status'] === 'processing')); ?></div>
                    <div class="stat-label">Processing</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="stat-card" onclick="window.location.href='admin_manage_requests.php?status=delivered'">
                <div class="stat-card-content text-center">
                    <div class="stat-number"><?php echo count(array_filter($all_requests, fn($r) => $r['status'] === 'delivered')); ?></div>
                    <div class="stat-label">Delivered</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="request-table">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="requestsTable">
                <thead class="table-light">
                    <tr>
                        <th>Request ID</th>
                        <th>Bag Number</th>
                        <th>Student</th>
                        <th>Room</th>
                        <th>Pickup Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($all_requests)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                            <div>No requests found</div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($all_requests as $request): ?>
                    <tr>
                        <td><strong>#<?php echo $request['request_id']; ?></strong></td>
                        <td><?php echo $request['bag_number']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($request['full_name']); ?></strong><br>
                            <small class="text-muted"><?php echo $request['phone']; ?></small>
                        </td>
                        <td>Block <?php echo $request['hostel_block']; ?> - <?php echo $request['room_number']; ?></td>
                        <td><?php echo date('M j, Y', strtotime($request['pickup_date'])); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $request['status']; ?>" id="status-badge-<?php echo $request['request_id']; ?>">
                                <?php echo ucwords(str_replace('_', ' ', $request['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary me-1" onclick="updateStatus(<?php echo $request['request_id']; ?>, '<?php echo $request['status']; ?>', '<?php echo htmlspecialchars($request['bag_number']); ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo $request['request_id']; ?>)">
                                <i class="fas fa-eye"></i>
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

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Request Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="updateStatusForm">
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="modal_request_id">
                    <input type="hidden" name="update_status" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Request Details</label>
                        <div id="request_details" class="bg-light p-3 rounded"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_status" class="form-label">New Status</label>
                        <select class="form-select" name="new_status" id="new_status" required>
                            <?php foreach($status_options as $value => $label): ?>
                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks (Optional)</label>
                        <textarea class="form-control" name="remarks" id="remarks" rows="3" placeholder="Any additional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
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

function updateStatus(requestId, currentStatus, bagNumber) {
    document.getElementById('modal_request_id').value = requestId;
    document.getElementById('new_status').value = currentStatus;
    document.getElementById('request_details').innerHTML = "<strong>Bag:</strong> " + bagNumber + "<br><strong>Current Status:</strong> " + currentStatus;
    var modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
    modal.show();
}

function viewDetails(requestId) {
    alert("Show details for request #" + requestId);
}
</script>
</body>
</html>