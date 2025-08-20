<?php
require_once 'freshfold_config.php';
header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$type = $_POST['type'] ?? '';
$value = $_POST['value'] ?? '';

if ($type === 'username') {
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$value]);
    echo json_encode(['exists' => $stmt->fetchColumn() > 0]);
} elseif ($type === 'email') {
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$value]);
    echo json_encode(['exists' => $stmt->fetchColumn() > 0]);
} else {
    echo json_encode(['exists' => false]);
}