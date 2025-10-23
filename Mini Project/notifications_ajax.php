<?php
require_once 'freshfold_config.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_details':
            $notification_id = intval($_POST['notification_id'] ?? 0);
            if ($notification_id > 0) {
                // Get detailed notification information
                $stmt = $db->prepare("
                    SELECT n.notification_id, n.title, n.message, n.type, n.created_at, n.target_url,
                           u.full_name as sender_name, n.is_read
                    FROM notifications n
                    LEFT JOIN users u ON n.user_id = u.user_id
                    WHERE n.notification_id = ? AND n.user_id = ?
                ");
                $stmt->execute([$notification_id, $user_id]);
                $notification = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($notification) {
                    echo json_encode(['success' => true, 'notification' => $notification]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Notification not found']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
            }
            break;
            
        case 'mark_read':
            $notification_id = intval($_POST['notification_id'] ?? 0);
            if ($notification_id > 0) {
                // Mark specific notification as read
                $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
                $result = $stmt->execute([$notification_id, $user_id]);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
            }
            break;
            
        case 'mark_all_read':
            // Mark all notifications as read for the user
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $result = $stmt->execute([$user_id]);
            echo json_encode(['success' => $result]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// Handle GET requests - fetch unread notifications
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get only unread notifications for the current user
    $stmt = $db->prepare("
        SELECT notification_id, title, message, type, created_at, target_url 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($notifications);
    exit;
}

// Fallback for any other request method
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>