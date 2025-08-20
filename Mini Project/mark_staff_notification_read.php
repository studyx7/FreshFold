<?php
<?php
require_once 'freshfold_config.php';
requireLogin();

if (User::getUserType() !== 'staff') {
    http_response_code(403);
    exit('Forbidden');
}

if (isset($_GET['id'])) {
    $database = new Database();
    $db = $database->getConnection();
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
    $stmt->execute([intval($_GET['id'])]);
}