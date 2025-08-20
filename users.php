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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users - Admin - FreshFold</title>
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
            border: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .user-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .badge-admin { background: #6f42c1; }
        .badge-staff { background: #0d6efd; }
        .badge-student { background: #198754; }
        .badge-inactive { background: #dc3545; }
        .badge-active { background: #28a745; }

        /* Animation keyframes */
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(40px);}
            100% { opacity: 1; transform: translateY(0);}
        }
        @keyframes popIn {
            0% { opacity: 0; transform: scale(0.95);}
            100% { opacity: 1; transform: scale(1);}
        }

        /* Animation classes */
        .animated-fadeInUp {
            animation: fadeInUp 0.7s cubic-bezier(.39,.575,.565,1.000) both;
        }
        .animated-popIn {
            animation: popIn 0.5s cubic-bezier(.39,.575,.565,1.000) both;
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

<div class="main-content animated-fadeInUp">
    <h2 class="mb-4"><i class="fas fa-users me-2"></i>Users</h2>
    <!-- Add User Button -->
    <div class="mb-3 d-flex justify-content-between">
        <form class="d-flex" method="get">
            <select name="user_type" class="form-select me-2" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="student" <?php if($user_type=='student') echo 'selected'; ?>>Student</option>
                <option value="staff" <?php if($user_type=='staff') echo 'selected'; ?>>Staff</option>
                <option value="admin" <?php if($user_type=='admin') echo 'selected'; ?>>Admin</option>
            </select>
            <input type="text" name="search" class="form-control me-2" placeholder="Search name, email, username..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
        </form>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-user-plus"></i> Add User
        </button>
    </div>
    <div class="user-table table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email / Username</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Phone</th>
                    <th>Gender</th> <!-- Add this line -->
                    <th>Hostel/Room</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($users)): ?>
                <tr>
                    <td colspan="8" class="text-center py-4">
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
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="add_user" value="1">
                <div class="modal-header">
                    <h5 class="modal-title">Add User</h5>
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
    <div class="modal-dialog animated-popIn">
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

<div class="particles" id="particles"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
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

function openEditModal(user) {
    document.getElementById('edit_user_id').value = user.user_id;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_phone').value = user.phone;
    document.getElementById('edit_user_type').value = user.user_type;
    document.getElementById('edit_hostel_block').value = user.hostel_block || '';
    document.getElementById('edit_room_number').value = user.room_number || '';
    var modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}
</script>
</body>
</html>