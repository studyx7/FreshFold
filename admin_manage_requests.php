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
    <title>Manage Requests - Admin - FreshFold</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            width: 250px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            color: white;
            padding: 20px 0;
            z-index: 1000;
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
            display: flex;
            align-items: center;
            text-decoration: none;
            margin: 2px 0;
            border-radius: 0 25px 25px 0;
            transition: all 0.3s;
        }
        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
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
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            z-index: 10;
            position: relative;
        }
        .request-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-submitted { background-color: #fff3cd; color: #856404; }
        .status-processing { background-color: #d1ecf1; color: #0c5460; }
        .status-delivered { background-color: #e2e3e5; color: #41464b; }
        .filters-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .btn-sm {
            padding: 4px 8px;
            font-size: 0.8rem;
        }
        .modal-content {
            border-radius: 15px;
        }
        .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
<div class="sidebar">
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
    <h2 class="mb-4"><i class="fas fa-tasks me-2"></i>Manage Laundry Requests</h2>

    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Filter by Status</label>
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
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Bag number, student name, or room..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="admin_manage_requests.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5><?php echo count(array_filter($all_requests, fn($r) => $r['status'] === 'submitted')); ?></h5>
                    <small class="text-muted">Submitted</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5><?php echo count(array_filter($all_requests, fn($r) => $r['status'] === 'processing')); ?></h5>
                    <small class="text-muted">Processing</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5><?php echo count(array_filter($all_requests, fn($r) => $r['status'] === 'delivered')); ?></h5>
                    <small class="text-muted">Delivered</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="request-table">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
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
                            <span class="status-badge status-<?php echo $request['status']; ?>">
                                <?php echo ucwords(str_replace('_', ' ', $request['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="updateStatus(<?php echo $request['request_id']; ?>, '<?php echo $request['status']; ?>', '<?php echo htmlspecialchars($request['bag_number']); ?>')">
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
            <form method="POST">
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
function updateStatus(requestId, currentStatus, bagNumber) {
    document.getElementById('modal_request_id').value = requestId;
    document.getElementById('new_status').value = currentStatus;
    document.getElementById('request_details').innerHTML = `
        <strong>Request ID:</strong> #${requestId}<br>
        <strong>Bag Number:</strong> ${bagNumber}<br>
        <strong>Current Status:</strong> ${currentStatus.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
    `;
    
    var modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
    modal.show();
}

function viewDetails(requestId) {
    // This can be expanded to show a detailed view modal
    alert('View details functionality can be implemented here for request #' + requestId);
}
</script>
</body>
</html>