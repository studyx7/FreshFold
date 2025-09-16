<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'freshfold_config.php';
header('Content-Type: application/json');
requireLogin();
requireUserType('student');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    $request_id = intval($_POST['feedback_request_id'] ?? 0);
    $feedback_text = trim($_POST['feedback_text'] ?? '');

    if ($request_id && $feedback_text !== '') {
        // Check if request belongs to the logged-in student
        $stmt = $db->prepare("SELECT * FROM laundry_requests WHERE request_id = ? AND student_id = ?");
        $stmt->execute([$request_id, $_SESSION['user_id']]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid request.']);
            exit;
        }

        // Insert feedback
        $stmt = $db->prepare("INSERT INTO feedback (request_id, student_id, feedback_text, created_at) VALUES (?, ?, ?, NOW())");
        if ($stmt->execute([$request_id, $_SESSION['user_id'], $feedback_text])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Feedback cannot be empty.']);
    }
    exit;
}
echo json_encode(['success' => false, 'error' => 'Invalid request.']);