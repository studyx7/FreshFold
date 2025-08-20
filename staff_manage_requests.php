<?php
require_once 'freshfold_config.php';
requireLogin();

// Only staff can access this page
if(User::getUserType() !== 'staff') {
    showAlert('Access denied. Staff only.', 'danger');
    redirect('dashboard_page.php');
}

$database = new Database();
$db = $database->getConnection();
$laundryRequest = new LaundryRequest($db);

// Handle status updates and return date update
if($_POST && isset($_POST['update_status'])) {
    $request_id = $_POST['request_id'];
    $new_status = $_POST['new_status'];
    $remarks = $_POST['remarks'] ?? '';
    $new_return_date = $_POST['return_date'] ?? null;

    // Validate return date if provided
    if ($new_return_date) {
        // Get the request's pickup date
        $stmt = $db->prepare("SELECT pickup_date FROM laundry_requests WHERE request_id = ?");
        $stmt->execute([$request_id]);
        $pickup_date = $stmt->fetchColumn();

        if (strtotime($new_return_date) < strtotime($pickup_date)) {
            showAlert('Return date cannot be earlier than the pickup date.', 'danger');
        } else {
            // Update return date (expected_delivery)
            $stmt = $db->prepare("UPDATE laundry_requests SET expected_delivery = ? WHERE request_id = ?");
            $stmt->execute([$new_return_date, $request_id]);
        }
    }

    if($laundryRequest->updateStatus($request_id, $new_status, $_SESSION['user_id'], $remarks)) {
        showAlert('Request status updated successfully!', 'success');
    } else {
        showAlert('Failed to update status. Please try again.', 'danger');
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$open_request_id = isset($_GET['open_request_id']) ? intval($_GET['open_request_id']) : 0;

// Get all requests
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
    <title>Manage Requests - Staff - FreshFold</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #f8f9fa;
            --accent-color: #28a745;
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
            border: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
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
        .request-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            animation: fadeInUp 0.7s cubic-bezier(.39,.575,.565,1.000) both;
        }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(40px);}
            100% { opacity: 1; transform: translateY(0);}
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
        .status-submitted { background-color: #fff3cd; color: #856404; }
        .status-processing { background-color: #d1ecf1; color: #0c5460; }
        .status-delivered { background-color: #e2e3e5; color: #41464b; }
        .filters-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            animation: fadeInUp 0.7s cubic-bezier(.39,.575,.565,1.000) both;
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
        .card {
            animation: fadeInUp 0.7s cubic-bezier(.39,.575,.565,1.000) both;
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
        <a class="nav-link active" href="staff_manage_requests.php">
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
                    <a href="staff_manage_requests.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center stat-card">
                <div class="card-body">
                    <h5 class="stat-number" data-target="<?php echo count(array_filter($all_requests, fn($r) => $r['status'] === 'submitted')); ?>">0</h5>
                    <small class="text-muted stat-label">Submitted</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center stat-card">
                <div class="card-body">
                    <h5 class="stat-number" data-target="<?php echo count(array_filter($all_requests, fn($r) => $r['status'] === 'processing')); ?>">0</h5>
                    <small class="text-muted stat-label">Processing</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center stat-card">
                <div class="card-body">
                    <h5 class="stat-number" data-target="<?php echo count(array_filter($all_requests, fn($r) => $r['status'] === 'delivered')); ?>">0</h5>
                    <small class="text-muted stat-label">Delivered</small>
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
                    <tr class="request-row" data-request-id="<?php echo $request['request_id']; ?>" data-return-date="<?php echo $request['expected_delivery']; ?>" data-pickup-date="<?php echo $request['pickup_date']; ?>" style="opacity:0;transform:translateY(30px);transition:opacity 0.6s,transform 0.6s;">
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
                        <label for="return_date" class="form-label">Return Date (Expected Delivery)</label>
                        <input type="date" class="form-control" name="return_date" id="return_date" required>
                        <div class="form-text">You can adjust the return date if needed. It cannot be before the pickup date.</div>
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
animateStatNumbers();

// Animate table rows on scroll
function addScrollAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });
    document.querySelectorAll('.request-row').forEach(el => {
        observer.observe(el);
    });
}
document.addEventListener('DOMContentLoaded', addScrollAnimations);

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

function updateStatus(requestId, currentStatus, bagNumber) {
    // Fetch the current return date and pickup date for the request (AJAX or from table row data)
    // For simplicity, pass them as data attributes in the button or fetch via AJAX if needed.
    // Here, let's assume you have them in JS variables or can fetch via AJAX:
    let row = document.querySelector('tr[data-request-id="' + requestId + '"]');
    let returnDate = row ? row.getAttribute('data-return-date') : '';
    let pickupDate = row ? row.getAttribute('data-pickup-date') : '';

    document.getElementById('modal_request_id').value = requestId;
    document.getElementById('new_status').value = currentStatus;
    document.getElementById('request_details').innerHTML = `
        <strong>Request ID:</strong> #${requestId}<br>
        <strong>Bag Number:</strong> ${bagNumber}<br>
        <strong>Current Status:</strong> ${currentStatus.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
    `;
    document.getElementById('return_date').value = returnDate;
    document.getElementById('return_date').min = pickupDate;

    var modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
    modal.show();
}

function viewDetails(requestId) {
    // This can be expanded to show a detailed view modal
    alert('View details functionality can be implemented here for request #' + requestId);
}

// Auto-open the update status modal if open_request_id is set
<?php if ($open_request_id): ?>
document.addEventListener('DOMContentLoaded', function() {
    var row = document.querySelector('tr[data-request-id="<?php echo $open_request_id; ?>"]');
    if (row) {
        var requestId = row.getAttribute('data-request-id');
        var currentStatus = row.querySelector('.status-badge').textContent.trim().toLowerCase();
        var bagNumber = row.children[1].textContent.trim();
        // Use the same updateStatus function as in your code
        updateStatus(requestId, currentStatus, bagNumber);
        // Optionally, scroll to the row
        row.scrollIntoView({behavior: "smooth", block: "center"});
        row.classList.add('table-primary');
        setTimeout(() => row.classList.remove('table-primary'), 2000);
    }
});
<?php endif; ?>
</script>
</body>
</html>