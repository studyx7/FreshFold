<?php
require_once 'freshfold_config.php';
require_once 'payment_utils.php';

requireLogin();
requireUserType('admin');

$paymentUtils = new PaymentUtils();
$current_year = date('Y');

// Initialize payment system to ensure tables exist
$paymentUtils->initializePaymentSystem();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_payment_stats':
            $year = $_POST['year'] ?? $current_year;
            $stats = getPaymentStatistics($year);
            echo json_encode($stats);
            break;
            
        case 'export_report':
            $year = $_POST['year'] ?? $current_year;
            $status = $_POST['status'] ?? null;
            $report = $paymentUtils->generatePaymentReport($year, $status);
            
            // Generate CSV
            $filename = "payment_report_{$year}_" . date('Y-m-d') . ".csv";
            $output = fopen('php://temp', 'w');
            
            // Headers
            fputcsv($output, [
                'Student ID', 'Name', 'Email', 'Hostel Block', 'Room', 
                'Academic Year', 'Amount', 'Status', 'Transaction Date'
            ]);
            
            // Data
            foreach ($report as $row) {
                fputcsv($output, [
                    $row['student_id'],
                    $row['full_name'],
                    $row['email'],
                    $row['hostel_block'],
                    $row['room_number'],
                    $row['academic_year'],
                    $row['amount'],
                    $row['payment_status'],
                    $row['transaction_date'] ?? 'N/A'
                ]);
            }
            
            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);
            
            echo json_encode(['success' => true, 'csv' => base64_encode($csv), 'filename' => $filename]);
            break;
            
        case 'send_notification':
            $student_id = intval($_POST['student_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            
            if ($student_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid student ID']);
                break;
            }
            
            if (empty($message)) {
                echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
                break;
            }
            
            $result = sendPaymentNotification($student_id, $message);
            echo json_encode($result);
            break;
            
        case 'send_bulk_notification':
            $message = trim($_POST['message'] ?? '');
            
            if (empty($message)) {
                echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
                break;
            }
            
            $result = sendBulkPaymentNotification($message);
            echo json_encode($result);
            break;
    }
    exit;
}

// Get statistics for current year using the existing PaymentUtils
$stats = getPaymentStatistics($current_year);
$pending_payments = $paymentUtils->getPendingPayments();
$recent_payments = $paymentUtils->generatePaymentReport($current_year, 'completed');
$recent_payments = array_slice($recent_payments, 0, 10); // Last 10 payments

function getPaymentStatistics($year) {
    global $paymentUtils;
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if student_payments table exists
    $table_exists = false;
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'student_payments'");
        $table_exists = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        // Table doesn't exist yet
    }
    
    // Total students
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'student'");
    $stmt->execute();
    $total_students = $stmt->fetchColumn();
    
    if ($table_exists) {
        // Use student_payments table for accurate statistics
        $stmt = $db->prepare("SELECT COUNT(*) FROM student_payments WHERE academic_year = ? AND payment_status = 'completed'");
        $stmt->execute([$year]);
        $paid_students = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM student_payments WHERE academic_year = ? AND payment_status = 'completed'");
        $stmt->execute([$year]);
        $total_collected = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM student_payments WHERE academic_year = ? AND payment_status = 'pending'");
        $stmt->execute([$year]);
        $pending_amount = $stmt->fetchColumn();
        
        $pending_students = $total_students - $paid_students;
    } else {
        // Fallback when table doesn't exist
        $paid_students = 0;
        $pending_students = $total_students;
        $total_collected = 0;
        $pending_amount = 0;
    }
    
    return [
        'total_students' => $total_students,
        'paid_students' => $paid_students,
        'pending_students' => $pending_students,
        'payment_rate' => $total_students > 0 ? round(($paid_students / $total_students) * 100, 1) : 0,
        'total_collected' => $total_collected,
        'pending_amount' => $pending_amount
    ];
}

function sendPaymentNotification($student_id, $message) {
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // Verify student exists and hasn't paid
        $stmt = $db->prepare("SELECT full_name, payment_status, payment_year FROM users WHERE user_id = ? AND user_type = 'student'");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            return ['success' => false, 'error' => 'Student not found'];
        }
        
        // Insert notification
        $title = "Payment Reminder - Annual Laundry Fee";
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, target_url, created_at) VALUES (?, ?, ?, 'warning', 'payment_tab.php', NOW())");
        $stmt->execute([$student_id, $title, $message]);
        
        return [
            'success' => true, 
            'message' => 'Notification sent successfully to ' . htmlspecialchars($student['full_name'])
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Failed to send notification: ' . $e->getMessage()];
    }
}

function sendBulkPaymentNotification($message) {
    $database = new Database();
    $db = $database->getConnection();
    $current_year = date('Y');
    
    try {
        // Get all unpaid students for current year
        $stmt = $db->prepare("
            SELECT user_id, full_name 
            FROM users 
            WHERE user_type = 'student' 
            AND (payment_status = 'Not Paid' OR payment_year != ? OR payment_year IS NULL)
        ");
        $stmt->execute([$current_year]);
        $unpaid_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($unpaid_students)) {
            return ['success' => false, 'error' => 'No unpaid students found'];
        }
        
        $title = "Payment Reminder - Annual Laundry Fee";
        $sent_count = 0;
        
        // Insert notifications for all unpaid students
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, target_url, created_at) VALUES (?, ?, ?, 'warning', 'payment_tab.php', NOW())");
        
        foreach ($unpaid_students as $student) {
            try {
                $stmt->execute([$student['user_id'], $title, $message]);
                $sent_count++;
            } catch (Exception $e) {
                // Continue with other students if one fails
                continue;
            }
        }
        
        return [
            'success' => true,
            'message' => "Notification sent successfully to {$sent_count} student(s)",
            'count' => $sent_count
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Failed to send bulk notifications: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - FreshFold Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #f8f9fa;
            --accent-color: #28a745;
            --sidebar-width: 250px;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, var(--primary-color) 0%, #1e3d6f 100%);
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
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 20px 0;
            z-index: 1000;
            overflow-y: auto;
            transform: translateX(0);
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        .sidebar:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        .sidebar .brand {
            text-align: center;
            padding: 0 20px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 30px;
            animation: slideInFromTop 0.8s ease-out;
            position: relative;
        }
        .sidebar .brand i {
            position: relative;
            display: inline-block;
            filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.3));
            transition: all 0.3s ease;
        }
        .sidebar .brand:hover i {
            filter: drop-shadow(0 0 15px rgba(255, 255, 255, 0.5));
            transform: scale(1.05);
        }
        @keyframes slideInFromTop {
            0% { transform: translateY(-50px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }
        .sidebar .brand h4 {
            margin: 10px 0 5px 0;
            font-weight: 700;
            background: linear-gradient(45deg, #fff, #f0f0f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
        }
        .sidebar .brand h4::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            width: 0;
            height: 2px;
            background: linear-gradient(45deg, #fff, #f0f0f0);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        .sidebar .brand:hover h4::after {
            width: 100%;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border: none;
            display: flex;
            align-items: center;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            text-decoration: none;
            margin: 2px 0;
            position: relative;
            overflow: hidden;
        }
        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            left: -100%;
            top: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s;
        }
        .sidebar .nav-link:hover::before {
            left: 100%;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.2);
            border-radius: 0 25px 25px 0;
            margin-right: 20px;
            transform: translateX(10px) scale(1.05);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        .sidebar .nav-link:hover i {
            transform: scale(1.2) rotate(5deg);
        }
        .btn-menu-toggle {
            display: none;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .particles {
                display: none;
            }
            .btn-menu-toggle {
                display: block;
            }
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
            z-index: 10;
            position: relative;
        }
        .dashboard-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 25px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            animation: slideInFromLeft 1s ease-out;
        }
        @keyframes slideInFromLeft {
            0% { transform: translateX(-100px); opacity: 0; }
            100% { transform: translateX(0); opacity: 1; }
        }
        .dashboard-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .dashboard-card-content {
            position: relative;
            z-index: 2;
        }
        .stats-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 20px;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            height: 100%;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            opacity: 0;
            transform: translateY(30px);
            border-left: 4px solid;
        }
        .stats-card.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .stats-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(from 0deg, transparent, rgba(44, 90, 160, 0.1), transparent);
            transition: transform 0.6s;
            transform: rotate(0deg);
        }
        .stats-card:hover::before {
            transform: rotate(180deg);
        }
        .stats-card:hover {
            transform: translateY(-15px) rotateX(5deg);
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
            background: rgba(255, 255, 255, 1);
        }
        .stats-card-content {
            position: relative;
            z-index: 2;
        }
        .stats-card.success { border-left-color: #28a745; }
        .stats-card.warning { border-left-color: #ffc107; }
        .stats-card.info { border-left-color: #17a2b8; }
        .stats-card.primary { border-left-color: #007bff; }
        
        .payment-table {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 20px;
        }
        .table th {
            background-color: #f8f9fa;
            border: none;
            font-weight: 600;
            color: #495057;
        }
        .badge-status {
            padding: 0.5rem 0.8rem;
            border-radius: 25px;
            font-size: 0.8rem;
        }
        .progress-custom {
            height: 8px;
            border-radius: 10px;
        }
        
        /* Notification Modal Styles */
        .notification-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .notification-modal.show {
            display: flex;
            opacity: 1;
        }
        
        .notification-modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        
        .notification-modal-container {
            position: relative;
            width: 90%;
            max-width: 550px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 24px;
            overflow: hidden;
            transform: scale(0.9) translateY(20px);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3), 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .notification-modal.show .notification-modal-container {
            transform: scale(1) translateY(0);
        }
        
        .notification-modal-header {
            padding: 28px 30px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.15) 100%);
        }
        
        .notification-modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin: 0;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .notification-modal-title i {
            font-size: 1.6rem;
            filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.4));
        }
        
        .notification-modal-body {
            padding: 30px;
        }
        
        .form-group-modern {
            margin-bottom: 24px;
        }
        
        .form-label-modern {
            display: block;
            color: white;
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 10px;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }
        
        .form-control-modern {
            width: 100%;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 14px;
            color: white;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .form-control-modern::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .form-control-modern:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.3), inset 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        textarea.form-control-modern {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }
        
        .student-info-card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }
        
        .student-info-card h6 {
            color: white;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 1rem;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }
        
        .student-info-card p {
            color: rgba(255, 255, 255, 0.9);
            margin: 4px 0;
            font-size: 0.9rem;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }
        
        .notification-modal-footer {
            padding: 20px 30px 28px;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .btn-modern {
            padding: 12px 28px;
            border-radius: 14px;
            border: 1px solid;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn-modern.btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: rgba(102, 126, 234, 0.5);
            color: white;
        }
        
        .btn-modern.btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-modern.btn-secondary {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            color: white;
        }
        
        .btn-modern.btn-secondary:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-1px);
        }
        
        .btn-modern:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-notify {
            padding: 6px 14px;
            font-size: 0.8rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: 1px solid rgba(102, 126, 234, 0.5);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-notify:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        
        .btn-notify i {
            font-size: 0.85rem;
        }
        
        .btn-bulk-notify {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 14px 28px;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-bulk-notify::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s;
        }
        
        .btn-bulk-notify:hover::before {
            left: 100%;
        }
        
        .btn-bulk-notify:hover {
            transform: translateY(-2px) scale(1.01);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        
        .btn-bulk-notify:active {
            transform: translateY(0) scale(0.98);
        }
        
        /* Alert Messages */
        .alert-modern {
            padding: 16px 20px;
            border-radius: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            backdrop-filter: blur(10px);
            border: 1px solid;
            animation: slideInDown 0.4s ease;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-modern.alert-success {
            background: rgba(40, 167, 69, 0.2);
            border-color: rgba(40, 167, 69, 0.5);
            color: #d4edda;
        }
        
        .alert-modern.alert-error {
            background: rgba(220, 53, 69, 0.2);
            border-color: rgba(220, 53, 69, 0.5);
            color: #f8d7da;
        }
        
        .alert-modern i {
            font-size: 1.2rem;
        }
        
        /* Loading Spinner */
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .loading-spinner.show {
            display: block;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 255, 255, 0.2);
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 12px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }
    </style>
</head>
<body>

<!-- Floating Particles Background -->
<div class="particles" id="particles"></div>

<!-- Mobile menu toggle -->
<button class="btn btn-primary d-md-none position-fixed btn-menu-toggle" style="top: 20px; left: 20px; z-index: 1001;" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="brand">
        <i class="fas fa-tshirt fa-2x mb-2"></i>
        <h4>FreshFold</h4>
        <small>Admin Panel</small>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link" href="admin_dashboard.php">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a class="nav-link" href="admin_manage_requests.php">
            <i class="fas fa-tasks"></i> Manage Requests
        </a>
        <a class="nav-link" href="users.php">
            <i class="fas fa-users"></i> Users
        </a>
        <a class="nav-link" href="admin_issue_management.php">
            <i class="fas fa-exclamation-triangle"></i> Issue Management
        </a>
        <a class="nav-link active" href="admin_payments.php">
            <i class="fas fa-credit-card"></i> Payment Management
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

<!-- Main Content -->
<div class="main-content">
    <div class="dashboard-card">
        <div class="dashboard-card-content">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2><i class="fas fa-rupee-sign me-2"></i>Payment Management</h2>
                    <p class="mb-0 opacity-75">Academic Year <?php echo $current_year; ?></p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($stats['pending_students'] > 0): ?>
                    <button class="btn-modern btn-bulk-notify" onclick="openBulkNotificationModal()">
                        <i class="fas fa-bullhorn"></i>
                        <span>Notify All Unpaid (<?php echo $stats['pending_students']; ?>)</span>
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-outline-light" onclick="refreshStats()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="btn btn-outline-light" onclick="exportReport()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stats-card success visible">
                <div class="stats-card-content">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-success mb-1"><?php echo $stats['paid_students']; ?></h3>
                            <p class="text-muted mb-0">Students Paid</p>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                    <div class="progress progress-custom mt-3">
                        <div class="progress-bar bg-success" style="width: <?php echo $stats['payment_rate']; ?>%"></div>
                    </div>
                    <small class="text-muted"><?php echo $stats['payment_rate']; ?>% payment rate</small>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stats-card warning visible">
                <div class="stats-card-content">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-warning mb-1"><?php echo $stats['pending_students']; ?></h3>
                            <p class="text-muted mb-0">Pending Payments</p>
                        </div>
                        <div class="text-warning">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stats-card success visible">
                <div class="stats-card-content">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-success mb-1">₹<?php echo number_format($stats['total_collected'], 0); ?></h3>
                            <p class="text-muted mb-0">Total Collected</p>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-rupee-sign fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stats-card info visible">
                <div class="stats-card-content">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-info mb-1"><?php echo $stats['total_students']; ?></h3>
                            <p class="text-muted mb-0">Total Students</p>
                        </div>
                        <div class="text-info">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Pending Payments -->
        <div class="col-lg-6 mb-4">
            <div class="payment-table">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Pending Payments</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Room</th>
                                <th>Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_payments)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        All payments completed!
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach (array_slice($pending_payments, 0, 10) as $payment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($payment['full_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($payment['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($payment['hostel_block'] . ' - ' . $payment['room_number']); ?></td>
                                        <td><strong>₹<?php echo number_format($payment['amount'], 0); ?></strong></td>
                                        <td>
                                            <button class="btn-notify" onclick='openNotificationModal(<?php echo json_encode([
                                                "id" => $payment["student_id"],
                                                "name" => $payment["full_name"],
                                                "email" => $payment["email"],
                                                "room" => $payment["hostel_block"] . " - " . $payment["room_number"]
                                            ]); ?>)'>
                                                <i class="fas fa-bell"></i>
                                                <span>Notify</span>
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
        
        <!-- Recent Payments -->
        <div class="col-lg-6 mb-4">
            <div class="payment-table">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0"><i class="fas fa-check-circle text-success me-2"></i>Recent Payments</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_payments)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        No recent payments
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_payments as $payment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($payment['full_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($payment['hostel_block'] . ' - ' . $payment['room_number']); ?></small>
                                        </td>
                                        <td><strong>₹<?php echo number_format($payment['amount'], 0); ?></strong></td>
                                        <td><small><?php echo date('M j, Y g:i A', strtotime($payment['transaction_date'])); ?></small></td>
                                        <td>
                                            <span class="badge badge-status bg-success text-white">
                                                <i class="fas fa-check me-1"></i>Completed
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Individual Notification Modal -->
<div class="notification-modal" id="notificationModal">
    <div class="notification-modal-backdrop" onclick="closeNotificationModal()"></div>
    <div class="notification-modal-container">
        <div class="notification-modal-header">
            <h3 class="notification-modal-title">
                <i class="fas fa-paper-plane"></i>
                Send Payment Reminder
            </h3>
        </div>
        <div class="notification-modal-body">
            <div id="alertContainer"></div>
            
            <div class="student-info-card" id="studentInfo">
                <!-- Student info will be populated by JS -->
            </div>
            
            <div class="form-group-modern">
                <label class="form-label-modern">
                    <i class="fas fa-comment-alt me-2"></i>Notification Message
                </label>
                <textarea 
                    class="form-control-modern" 
                    id="notificationMessage" 
                    placeholder="Type your payment reminder message here..."
                    rows="5"
                >Dear student,

This is a friendly reminder that your annual laundry service fee of ₹8,500 for the academic year <?php echo $current_year; ?> is pending.

Please complete your payment at your earliest convenience to continue enjoying our laundry services.

Thank you for your cooperation.</textarea>
            </div>
            
            <div class="loading-spinner" id="sendingSpinner">
                <div class="spinner"></div>
                <p style="color: white; margin: 0;">Sending notification...</p>
            </div>
        </div>
        <div class="notification-modal-footer">
            <button class="btn-modern btn-secondary" onclick="closeNotificationModal()">
                <i class="fas fa-times"></i>
                Cancel
            </button>
            <button class="btn-modern btn-primary" id="sendNotificationBtn" onclick="sendNotification()">
                <i class="fas fa-paper-plane"></i>
                Send Notification
            </button>
        </div>
    </div>
</div>

<!-- Bulk Notification Modal -->
<div class="notification-modal" id="bulkNotificationModal">
    <div class="notification-modal-backdrop" onclick="closeBulkNotificationModal()"></div>
    <div class="notification-modal-container">
        <div class="notification-modal-header">
            <h3 class="notification-modal-title">
                <i class="fas fa-bullhorn"></i>
                Send Bulk Payment Reminder
            </h3>
        </div>
        <div class="notification-modal-body">
            <div id="bulkAlertContainer"></div>
            
            <div class="student-info-card">
                <h6><i class="fas fa-users me-2"></i>Recipients</h6>
                <p>This notification will be sent to <strong><?php echo $stats['pending_students']; ?> student(s)</strong> who haven't paid their annual laundry fee for <?php echo $current_year; ?>.</p>
            </div>
            
            <div class="form-group-modern">
                <label class="form-label-modern">
                    <i class="fas fa-comment-alt me-2"></i>Notification Message
                </label>
                <textarea 
                    class="form-control-modern" 
                    id="bulkNotificationMessage" 
                    placeholder="Type your payment reminder message here..."
                    rows="5"
                >Dear student,

This is a friendly reminder that your annual laundry service fee of ₹8,500 for the academic year <?php echo $current_year; ?> is pending.

Please complete your payment at your earliest convenience to continue enjoying our laundry services.

Thank you for your cooperation.</textarea>
            </div>
            
            <div class="loading-spinner" id="bulkSendingSpinner">
                <div class="spinner"></div>
                <p style="color: white; margin: 0;">Sending notifications...</p>
            </div>
        </div>
        <div class="notification-modal-footer">
            <button class="btn-modern btn-secondary" onclick="closeBulkNotificationModal()">
                <i class="fas fa-times"></i>
                Cancel
            </button>
            <button class="btn-modern btn-primary" id="sendBulkNotificationBtn" onclick="sendBulkNotification()">
                <i class="fas fa-bullhorn"></i>
                Send to All
            </button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
// Global variables for notification modals
let currentStudentId = null;

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

// Toggle sidebar for mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}

function refreshStats() {
    location.reload();
}

function exportReport() {
    const year = '<?php echo $current_year; ?>';
    
    fetch('admin_payments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=export_report&year=${year}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const blob = new Blob([atob(data.csv)], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = data.filename;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        } else {
            alert('Export failed. Please try again.');
        }
    })
    .catch(error => {
        console.error('Export error:', error);
        alert('Export failed. Please try again.');
    });
}

// Individual notification functions
function openNotificationModal(studentData) {
    currentStudentId = studentData.id;
    
    // Populate student info
    const studentInfo = document.getElementById('studentInfo');
    studentInfo.innerHTML = `
        <h6><i class="fas fa-user me-2"></i>${escapeHtml(studentData.name)}</h6>
        <p><i class="fas fa-envelope me-2"></i>${escapeHtml(studentData.email)}</p>
        <p><i class="fas fa-door-open me-2"></i>Room: ${escapeHtml(studentData.room)}</p>
    `;
    
    // Clear previous alerts
    document.getElementById('alertContainer').innerHTML = '';
    
    // Show modal
    const modal = document.getElementById('notificationModal');
    modal.classList.add('show');
}

function closeNotificationModal() {
    const modal = document.getElementById('notificationModal');
    modal.classList.remove('show');
    currentStudentId = null;
    
    // Reset form
    setTimeout(() => {
        document.getElementById('notificationMessage').value = document.getElementById('notificationMessage').defaultValue;
        document.getElementById('alertContainer').innerHTML = '';
        document.getElementById('sendingSpinner').classList.remove('show');
        document.getElementById('sendNotificationBtn').disabled = false;
    }, 300);
}

function sendNotification() {
    const message = document.getElementById('notificationMessage').value.trim();
    
    if (!message) {
        showAlert('alertContainer', 'Please enter a notification message', 'error');
        return;
    }
    
    if (!currentStudentId) {
        showAlert('alertContainer', 'Student information is missing', 'error');
        return;
    }
    
    // Show loading
    document.getElementById('sendingSpinner').classList.add('show');
    document.getElementById('sendNotificationBtn').disabled = true;
    document.getElementById('alertContainer').innerHTML = '';
    
    // Send notification
    fetch('admin_payments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=send_notification&student_id=${currentStudentId}&message=${encodeURIComponent(message)}`
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('sendingSpinner').classList.remove('show');
        document.getElementById('sendNotificationBtn').disabled = false;
        
        if (data.success) {
            showAlert('alertContainer', data.message, 'success');
            setTimeout(() => {
                closeNotificationModal();
            }, 2000);
        } else {
            showAlert('alertContainer', data.error || 'Failed to send notification', 'error');
        }
    })
    .catch(error => {
        document.getElementById('sendingSpinner').classList.remove('show');
        document.getElementById('sendNotificationBtn').disabled = false;
        showAlert('alertContainer', 'Network error. Please try again.', 'error');
        console.error('Notification error:', error);
    });
}

// Bulk notification functions
function openBulkNotificationModal() {
    // Clear previous alerts
    document.getElementById('bulkAlertContainer').innerHTML = '';
    
    // Show modal
    const modal = document.getElementById('bulkNotificationModal');
    modal.classList.add('show');
}

function closeBulkNotificationModal() {
    const modal = document.getElementById('bulkNotificationModal');
    modal.classList.remove('show');
    
    // Reset form
    setTimeout(() => {
        document.getElementById('bulkNotificationMessage').value = document.getElementById('bulkNotificationMessage').defaultValue;
        document.getElementById('bulkAlertContainer').innerHTML = '';
        document.getElementById('bulkSendingSpinner').classList.remove('show');
        document.getElementById('sendBulkNotificationBtn').disabled = false;
    }, 300);
}

function sendBulkNotification() {
    const message = document.getElementById('bulkNotificationMessage').value.trim();
    
    if (!message) {
        showAlert('bulkAlertContainer', 'Please enter a notification message', 'error');
        return;
    }
    
    // Confirmation
    if (!confirm('Are you sure you want to send this notification to all unpaid students?')) {
        return;
    }
    
    // Show loading
    document.getElementById('bulkSendingSpinner').classList.add('show');
    document.getElementById('sendBulkNotificationBtn').disabled = true;
    document.getElementById('bulkAlertContainer').innerHTML = '';
    
    // Send bulk notification
    fetch('admin_payments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=send_bulk_notification&message=${encodeURIComponent(message)}`
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('bulkSendingSpinner').classList.remove('show');
        document.getElementById('sendBulkNotificationBtn').disabled = false;
        
        if (data.success) {
            showAlert('bulkAlertContainer', data.message, 'success');
            setTimeout(() => {
                closeBulkNotificationModal();
            }, 2500);
        } else {
            showAlert('bulkAlertContainer', data.error || 'Failed to send notifications', 'error');
        }
    })
    .catch(error => {
        document.getElementById('bulkSendingSpinner').classList.remove('show');
        document.getElementById('sendBulkNotificationBtn').disabled = false;
        showAlert('bulkAlertContainer', 'Network error. Please try again.', 'error');
        console.error('Bulk notification error:', error);
    });
}

// Helper functions
function showAlert(containerId, message, type) {
    const container = document.getElementById(containerId);
    const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
    
    container.innerHTML = `
        <div class="alert-modern ${alertClass}">
            <i class="fas ${icon}"></i>
            <span>${escapeHtml(message)}</span>
        </div>
    `;
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeNotificationModal();
        closeBulkNotificationModal();
    }
});

// Auto-refresh stats every 60 seconds
setInterval(function() {
    fetch('admin_payments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_payment_stats&year=<?php echo $current_year; ?>`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Stats updated:', data);
    })
    .catch(error => console.log('Auto-refresh error:', error));
}, 60000);
</script>

</body>
</html>