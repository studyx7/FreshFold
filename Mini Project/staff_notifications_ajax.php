<?php
<?php
require_once 'freshfold_config.php';
requireLogin();

if (User::getUserType() !== 'staff') {
    http_response_code(403);
    exit('Forbidden');
}

$database = new Database();
$db = $database->getConnection();

// Fetch unread staff notifications
$stmt = $db->prepare("SELECT notification_id, title, message, type, is_read, created_at, target_url 
                      FROM notifications 
                      WHERE for_staff = 1 AND is_read = 0 
                      ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($notifications);