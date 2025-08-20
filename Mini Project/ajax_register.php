<?php
require_once 'freshfold_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);

    // Set user properties
    $user->username = $_POST['username'];
    $user->email = $_POST['email'];
    $user->password_hash = $_POST['password'];
    $user->full_name = $_POST['full_name'];
    $user->phone = $_POST['phone'];
    $user->user_type = 'student';
    $user->hostel_block = $_POST['floor'];
    $user->room_number = $_POST['room_number'];
    $user->gender = $_POST['gender'];

    // Check for duplicate username/email
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$user->username, $user->email]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'Username or email already exists.']);
        exit;
    }

    if ($user->register()) {
        echo json_encode(['success' => true, 'message' => 'Registration successful! You can now login.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Registration failed. Please try again.']);
    }
}