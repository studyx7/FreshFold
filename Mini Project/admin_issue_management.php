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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
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
        <a class="nav-link" href="profile_page.php"><i class="fas fa-user"></i> Profile</a>
        <hr>
        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</div>
<div class="main-content" style="margin-left:250px; padding:20px;">
    <h2>Student Reported Issues</h2>
    <table class="table table-bordered table-hover">
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
        <?php foreach ($issues as $issue): ?>
            <tr>
                <td><?php echo htmlspecialchars($issue['full_name']); ?></td>
                <td><?php echo htmlspecialchars($issue['bag_number']); ?></td>
                <td><?php echo htmlspecialchars($issue['description']); ?></td>
                <td><?php echo htmlspecialchars($issue['created_at']); ?></td>
                <td><?php echo htmlspecialchars($issue['admin_reply']); ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="issue_id" value="<?php echo $issue['issue_id']; ?>">
                        <input type="text" name="admin_reply" class="form-control" placeholder="Reply..." required>
                        <button type="submit" name="reply_issue" class="btn btn-primary btn-sm mt-1">Reply</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>