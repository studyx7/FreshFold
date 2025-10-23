<?php
require_once 'freshfold_config.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];
$current_year = date('Y');

// Get user's laundry request statistics
$stats = [
    'submitted' => 0,
    'processing' => 0,
    'delivered' => 0
];

// Get statistics for the current user
$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM laundry_requests WHERE student_id = ? GROUP BY status");
$stmt->execute([$user_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($stats[$row['status']])) {
        $stats[$row['status']] = $row['count'];
    }
}

// Get user's payment status
$stmt = $db->prepare("SELECT payment_status, payment_year FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_payment = $stmt->fetch(PDO::FETCH_ASSOC);
$is_paid = ($user_payment && $user_payment['payment_status'] === 'Paid' && (int)$user_payment['payment_year'] == (int)$current_year);

// Get recent requests for current user
$stmt = $db->prepare("SELECT request_id, bag_number, created_at, status FROM laundry_requests WHERE student_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt->execute([$user_id]);
$recent_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FreshFold</title>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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

        /* Floating particles background */
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

        /* Sidebar with glassmorphism */
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

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 10px;
            padding: 12px;
            color: var(--primary-color);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        /* Main content - Contained layout */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            height: 100vh;
            overflow-y: auto;
            z-index: 10;
            position: relative;
            box-sizing: border-box;
        }

        /* Dashboard container with proper bounds */
        .dashboard-container {
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            padding-bottom: 20px;
        }

        /* Welcome card - ensure it doesn't clip dropdown */
        .welcome-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 25px;
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: visible; /* Changed from hidden to visible */
            animation: slideInFromLeft 1s ease-out;
        }

        @keyframes slideInFromLeft {
            0% { transform: translateX(-100px); opacity: 0; }
            100% { transform: translateX(0); opacity: 1; }
        }

        .welcome-card::before {
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

        .welcome-card-content {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 80px;
        }

        .welcome-text {
            flex: 1;
        }

        .welcome-card h2 {
            margin-bottom: 10px;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        /* Header Actions Container */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 3;
        }

        /* Modern Notification Bell */
        .notification-container {
            position: relative;
        }

        .notification-bell {
            position: relative;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            backdrop-filter: blur(15px);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
        }

        .notification-bell::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.3), rgba(255,255,255,0.1));
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .notification-bell:hover::before {
            opacity: 1;
        }

        .notification-bell:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 12px 40px rgba(31, 38, 135, 0.5);
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.2));
        }

        .notification-bell i {
            color: white;
            font-size: 1.2rem;
            transition: transform 0.3s ease;
            z-index: 2;
            position: relative;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
        }

        .notification-bell:hover i {
            transform: scale(1.1);
            animation: bellRing 0.6s ease;
        }

        @keyframes bellRing {
            0%, 100% { transform: rotate(0deg) scale(1.1); }
            25% { transform: rotate(-10deg) scale(1.1); }
            75% { transform: rotate(10deg) scale(1.1); }
        }

        /* Notification Badge */
        .notification-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: linear-gradient(135deg, #ff4757, #ff3742);
            color: white;
            border-radius: 50%;
            min-width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            border: 3px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 4px 15px rgba(255, 71, 87, 0.6);
            animation: pulse 2s infinite;
            transform: scale(0);
            transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .notification-count.show {
            transform: scale(1);
        }

        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 4px 15px rgba(255, 71, 87, 0.6); }
            50% { transform: scale(1.1); box-shadow: 0 6px 25px rgba(255, 71, 87, 0.8); }
            100% { transform: scale(1); box-shadow: 0 4px 15px rgba(255, 71, 87, 0.6); }
        }

        /* Notification Container - Ensure static positioning to break out of stacking context */
        .notification-container {
            position: static;
        }

        /* Modern Dropdown - Glass effect with readable text */
        .notification-dropdown {
            position: fixed;
            top: 0;
            left: 0;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-radius: 18px;
            min-width: 340px;
            max-width: 400px;
            max-height: 400px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2),
                        0 8px 20px rgba(0, 0, 0, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.4),
                        inset 0 -1px 0 rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-15px) scale(0.95);
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            pointer-events: none;
            z-index: 2147483647;
            overflow: hidden;
        }

        .notification-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }

        .notification-dropdown::before {
            content: '';
            position: absolute;
            top: -7px;
            right: 24px;
            width: 14px;
            height: 14px;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-bottom: none;
            border-right: none;
            transform: rotate(45deg);
            box-shadow: -2px -2px 10px rgba(0, 0, 0, 0.1);
        }

        .notification-dropdown-header {
            padding: 18px 22px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            border-radius: 18px 18px 0 0;
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(10px);
        }

        .notification-dropdown-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1a202c;
            margin: 0;
            text-shadow: 0 1px 3px rgba(255, 255, 255, 0.8);
        }

        /* Mark all as read button */
        .btn-mark-all-read {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.4);
            border-radius: 6px;
            padding: 4px 8px;
            color: #059669;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-mark-all-read:hover {
            background: rgba(16, 185, 129, 0.3);
            color: #047857;
            transform: scale(1.05);
        }

        /* Notification Details Modal */
        .notification-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            pointer-events: none;
        }

        .notification-modal.show {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .notification-modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }

        .notification-modal-container {
            position: relative;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 24px;
            overflow: hidden;
            transform: scale(0.9) translateY(20px);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.2),
                0 10px 25px rgba(0, 0, 0, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.4);
        }

        .notification-modal.show .notification-modal-container {
            transform: scale(1) translateY(0);
        }

        .notification-modal-header {
            padding: 24px 24px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 24px;
            padding-bottom: 20px;
        }

        .notification-modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .notification-modal-close {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
            font-size: 1.1rem;
        }

        .notification-modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }

        .notification-modal-content {
            padding: 0 24px 24px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .notification-detail-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .notification-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: capitalize;
            border: 1px solid;
        }

        .notification-type-badge.type-info {
            background: rgba(59, 130, 246, 0.2);
            border-color: rgba(59, 130, 246, 0.4);
            color: #93c5fd;
        }

        .notification-type-badge.type-success {
            background: rgba(16, 185, 129, 0.2);
            border-color: rgba(16, 185, 129, 0.4);
            color: #6ee7b7;
        }

        .notification-type-badge.type-warning {
            background: rgba(245, 158, 11, 0.2);
            border-color: rgba(245, 158, 11, 0.4);
            color: #fbbf24;
        }

        .notification-type-badge.type-error {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.4);
            color: #f87171;
        }

        .notification-timestamp {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .notification-detail-message {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 20px;
            color: white;
            line-height: 1.6;
            font-size: 1rem;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        .notification-modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .notification-action-btn {
            padding: 10px 20px;
            border-radius: 12px;
            border: 1px solid;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .notification-action-btn.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: rgba(102, 126, 234, 0.5);
            color: white;
        }

        .notification-action-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            color: white;
            text-decoration: none;
        }

        .notification-action-btn.secondary {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
            color: rgba(255, 255, 255, 0.9);
        }

        .notification-action-btn.secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
            color: white;
            text-decoration: none;
        }

        .notification-modal-loading {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 12px;
            color: white;
            font-weight: 500;
        }

        .notification-modal-loading.show {
            display: flex;
        }

        .spinner-small {
            width: 32px;
            height: 32px;
            border: 3px solid rgba(255, 255, 255, 0.2);
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .notification-list {
            max-height: 320px;
            overflow-y: auto;
            padding: 0;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.4) transparent;
        }

        .notification-list::-webkit-scrollbar {
            width: 6px;
        }

        .notification-list::-webkit-scrollbar-track {
            background: transparent;
        }

        .notification-list::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.4);
            border-radius: 3px;
        }

        .notification-list::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.6);
        }

        .notification-item {
            padding: 16px 22px;
            cursor: pointer;
            transition: all 0.25s ease;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(5px);
        }

        .notification-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.8) 0%, rgba(255, 255, 255, 0.4) 100%);
            opacity: 0;
            transition: all 0.25s ease;
        }

        .notification-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(4px);
            backdrop-filter: blur(15px);
        }

        .notification-item:hover::before {
            opacity: 1;
        }

        .notification-item:last-child {
            border-bottom: none;
            border-radius: 0 0 18px 18px;
        }

        .notification-item-title {
            font-weight: 600;
            color: #1a202c;
            font-size: 0.875rem;
            margin-bottom: 6px;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.6);
            line-height: 1.3;
        }

        .notification-item-message {
            color: #2d3748;
            font-size: 0.8rem;
            line-height: 1.4;
            margin-bottom: 8px;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.4);
        }

        .notification-item-time {
            color: #4a5568;
            font-size: 0.7rem;
            font-weight: 500;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.3);
        }

        .no-notifications {
            padding: 40px 22px;
            text-align: center;
            color: #4a5568;
        }

        .no-notifications i {
            font-size: 2.5rem;
            margin-bottom: 12px;
            color: #a0aec0;
            opacity: 0.8;
            text-shadow: 0 2px 4px rgba(255, 255, 255, 0.5);
        }

        .no-notifications div {
            font-size: 0.9rem;
            font-weight: 500;
            color: #2d3748;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.5);
        }

        /* Profile Section in Welcome Card */
        .header-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 25px;
            transition: all 0.3s ease;
            cursor: pointer;
            backdrop-filter: blur(10px);
        }

        .header-profile:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }

        .profile-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .profile-info h6 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .profile-info small {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.75rem;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        /* Grid Layout for Dashboard Items */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            align-items: start;
        }

        .grid-item {
            height: 100%;
        }

        /* Enhanced stat cards with perfect grid alignment */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            z-index: 1;
            position: relative;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            height: 120px;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
            z-index: 1;
        }

        .stat-card::before {
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

        .stat-card:hover::before {
            transform: rotate(180deg);
        }

        .stat-card:hover {
            transform: translateY(-10px) rotateX(5deg);
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
            background: rgba(255, 255, 255, 1);
        }

        .stat-card-content {
            position: relative;
            z-index: 2;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
            animation: countUp 1s ease-out;
            background: linear-gradient(45deg, var(--primary-color), #1e3d6f);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        @keyframes countUp {
            from { transform: scale(0.5); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 8px;
            opacity: 0;
            animation: fadeInUp 0.8s ease-out 0.3s forwards;
        }

        @keyframes fadeInUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Payment Status Card - Perfect Grid Integration */
        .payment-status-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            height: 100%;
            min-height: 140px;
        }

        .payment-status-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(44, 90, 160, 0.05), rgba(35, 166, 213, 0.05));
            z-index: 1;
        }

        .payment-status-content {
            position: relative;
            z-index: 2;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }

        .payment-icon {
            font-size: 1.5rem;
            color: var(--primary-color);
            filter: drop-shadow(0 2px 4px rgba(44, 90, 160, 0.3));
            margin-bottom: 10px;
        }

        .payment-label {
            font-weight: 600;
            font-size: 1.1rem;
            color: #2c3e50;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }

        .payment-badge {
            font-size: 1rem;
            font-weight: 700;
            padding: 8px 20px;
            border-radius: 25px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
            display: inline-block;
            margin-bottom: 10px;
        }

        .payment-paid {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .payment-unpaid {
            background: linear-gradient(135deg, #dc3545, #e74c3c);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .payment-year-info {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.7));
            backdrop-filter: blur(10px);
            padding: 6px 12px;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: inline-block;
        }

        .payment-year-text {
            font-weight: 600;
            font-size: 0.85rem;
            color: #2c5aa0;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
        }

        /* Quick actions and recent requests with grid alignment */
        .content-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            height: 100%;
            min-height: 350px;
        }

        /* Premium Quick Actions Grid Layout - Symmetrical Design */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: repeat(2, 1fr);
            gap: 16px;
            margin-top: 8px;
            height: 280px;
        }

        .btn-quick-action {
            background: var(--gradient-2);
            color: white;
            border: none;
            border-radius: 16px;
            padding: 20px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            font-weight: 500;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(44, 90, 160, 0.25);
            height: 100%;
        }

        .btn-quick-action.primary-action {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-quick-action.secondary-action {
            background: var(--gradient-2);
        }

        .btn-quick-action::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.05));
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 16px;
        }

        .btn-quick-action:hover::before {
            opacity: 1;
        }

        .btn-quick-action:hover {
            color: white;
            transform: translateY(-4px) scale(1.02);
            text-decoration: none;
            box-shadow: 0 8px 25px rgba(44, 90, 160, 0.4);
        }

        .btn-quick-action.primary-action:hover {
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
        }

        .action-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 14px;
            flex-shrink: 0;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .primary-action .action-icon {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 16px;
        }

        .action-icon i {
            font-size: 1.3rem;
            color: white;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        .action-content {
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1;
            text-align: left;
        }

        .action-title {
            font-weight: 600;
            font-size: 1rem;
            color: white;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
            line-height: 1.2;
        }

        .action-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.8rem;
            font-weight: 400;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            line-height: 1.3;
        }

        /* Recent requests styling */
        .request-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            transition: all 0.3s ease;
            position: relative;
        }

        .request-item:hover {
            background: rgba(44, 90, 160, 0.05);
            padding-left: 10px;
            border-radius: 10px;
        }

        .request-item:last-child {
            border-bottom: none;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-submitted { background-color: #fff3cd; color: #856404; }
        .status-picked_up { background-color: #cff4fc; color: #087990; }
        .status-in_progress { background-color: #d1ecf1; color: #0c5460; }
        .status-processing { background-color: #d1ecf1; color: #0c5460; }
        .status-ready { background-color: #d4edda; color: #155724; }
        .status-delivered { background-color: #e2e3e5; color: #41464b; }

        /* Responsive design */
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 80px 15px 20px;
            }

            .welcome-card {
                padding: 20px;
                margin-bottom: 20px;
            }

            .header-actions {
                top: 15px;
                right: 15px;
                gap: 10px;
            }

            .header-profile .profile-info {
                display: none;
            }

            .notification-dropdown {
                min-width: 300px;
                max-width: 320px;
                right: -15px;
                top: calc(100% + 8px);
                max-height: 350px;
            }

            .notification-dropdown::before {
                right: 30px;
            }

            .notification-item {
                padding: 14px 18px;
            }

            .notification-dropdown-header {
                padding: 16px 18px 12px;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .quick-actions-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .btn-quick-action.primary-action {
                grid-column: 1;
                min-height: 70px;
                padding: 20px 16px;
            }

            .btn-quick-action {
                min-height: 60px;
                padding: 16px 14px;
                gap: 10px;
            }

            .action-icon {
                width: 40px;
                height: 40px;
            }

            .primary-action .action-icon {
                width: 44px;
                height: 44px;
            }

            .action-title {
                font-size: 0.9rem;
            }

            .primary-action .action-title {
                font-size: 1rem;
            }

            .action-subtitle {
                font-size: 0.7rem;
            }
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

        .brand-stable {
            position: relative;
        }

        .brand-stable::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            width: 30px;
            height: 2px;
            background: rgba(255, 255, 255, 0.5);
            transform: translateX(-50%);
            border-radius: 2px;
        }
    </style>
</head>
<body>

<!-- Floating Particles Background -->
<div class="particles" id="particles"></div>

<!-- Mobile Menu Toggle -->
<button class="mobile-menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="brand brand-stable">
        <i class="fas fa-tshirt fa-2x mb-2"></i>
        <h4>FreshFold</h4>
        <small>Laundry Management</small>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link active" href="dashboard_page.php">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a class="nav-link" href="new_request_page.php">
            <i class="fas fa-plus-circle"></i> New Request
        </a>
        <a class="nav-link" href="my_requests_page.php">
            <i class="fas fa-list"></i> My Requests
        </a>
        <a class="nav-link" href="issue_report_page.php">
            <i class="fas fa-exclamation-triangle"></i> Report Issue
        </a>
        <a class="nav-link" href="payment_tab.php">
            <i class="fas fa-credit-card"></i> Payment
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
    <!-- Enhanced Welcome Card with Integrated Header Actions -->
    <div class="welcome-card">
        <div class="welcome-card-content">
            <!-- Welcome Text Section -->
            <div class="welcome-text">
                <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                <p class="mb-2">Manage your laundry services efficiently with FreshFold</p>
                <small class="opacity-75">
                    <?php echo ucfirst(htmlspecialchars($_SESSION['user_type'])); ?> • Block <?php echo htmlspecialchars($_SESSION['hostel_block']); ?> - Room <?php echo htmlspecialchars($_SESSION['room_number']); ?>
                </small>
            </div>
            
            <!-- Header Actions -->
            <div class="header-actions">
                <!-- Notification Bell -->
                <div class="notification-container">
                    <div class="notification-bell" id="notification-bell">
                        <i class="fas fa-bell"></i>
                        <div class="notification-count" id="notification-count">0</div>
                    </div>
                    
                    <!-- Modern Notification Dropdown -->
                    <div class="notification-dropdown" id="notification-dropdown">
                        <div class="notification-dropdown-header">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <h6 class="notification-dropdown-title">Notifications</h6>
                                <button id="mark-all-read-btn" class="btn-mark-all-read" onclick="markAllNotificationsAsRead()" style="display: none;">
                                    <i class="fas fa-check-double"></i>
                                </button>
                            </div>
                        </div>
                        <div class="notification-list" id="notification-list">
                            <div class="no-notifications">
                                <i class="fas fa-bell-slash"></i>
                                <div>No new notifications</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Section -->
                <div class="header-profile">
                    <div class="profile-avatar">
                        <?php 
                        $initials = '';
                        $name_parts = explode(' ', $_SESSION['full_name']);
                        foreach($name_parts as $part) {
                            $initials .= strtoupper(substr($part, 0, 1));
                        }
                        echo htmlspecialchars($initials);
                        ?>
                    </div>
                    <div class="profile-info d-none d-md-block">
                        <h6><?php echo htmlspecialchars($_SESSION['full_name']); ?></h6>
                        <small><?php echo ucfirst(htmlspecialchars($_SESSION['user_type'])); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Grid -->
    <div class="stats-grid">
        <div class="stat-card" onclick="window.location.href='my_requests_page.php?status=submitted'" style="cursor:pointer;">
            <div class="stat-card-content">
                <div class="stat-number" data-target="<?php echo $stats['submitted']; ?>">0</div>
                <div class="stat-label">Submitted</div>
            </div>
        </div>
        <div class="stat-card" onclick="window.location.href='my_requests_page.php?status=processing'" style="cursor:pointer;">
            <div class="stat-card-content">
                <div class="stat-number" data-target="<?php echo $stats['processing']; ?>">0</div>
                <div class="stat-label">Processing</div>
            </div>
        </div>
        <div class="stat-card" onclick="window.location.href='my_requests_page.php?status=delivered'" style="cursor:pointer;">
            <div class="stat-card-content">
                <div class="stat-number" data-target="<?php echo $stats['delivered']; ?>">0</div>
                <div class="stat-label">Delivered</div>
            </div>
        </div>
    </div>

    <!-- Payment Status Card -->
    <div class="row justify-content-center mb-4">
        <div class="col-lg-8 col-md-10">
            <div class="payment-status-card">
                <div class="payment-status-content">
                    <div class="payment-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="payment-label">Payment Status</div>
                    <div class="mb-2">
                        <span class="badge payment-badge <?php echo $is_paid ? 'payment-paid' : 'payment-unpaid'; ?>">
                            <?php echo $is_paid ? 'Paid' : 'Not Paid'; ?>
                        </span>
                    </div>
                    <?php if ($is_paid): ?>
                    <div class="payment-year-info">
                        <i class="fas fa-check-circle text-success me-1"></i>
                        <span class="payment-year-text">Paid for <?php echo $user_payment['payment_year']; ?></span>
                    </div>
                    <?php else: ?>
                    <div class="payment-year-info">
                        <i class="fas fa-exclamation-circle text-warning me-1"></i>
                        <span class="payment-year-text">Payment required for <?php echo $current_year; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Grid -->
    <div class="dashboard-grid">
        <!-- Quick Actions -->
        <div class="grid-item">
            <div class="content-card">
                <h5 class="mb-4"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                <div class="quick-actions-grid">
                    <a href="new_request_page.php" class="btn-quick-action primary-action">
                        <div class="action-icon">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div class="action-content">
                            <span class="action-title">New Request</span>
                            <small class="action-subtitle">Submit laundry</small>
                        </div>
                    </a>
                    
                    <a href="my_requests_page.php" class="btn-quick-action secondary-action">
                        <div class="action-icon">
                            <i class="fas fa-list"></i>
                        </div>
                        <div class="action-content">
                            <span class="action-title">My Requests</span>
                            <small class="action-subtitle">View history</small>
                        </div>
                    </a>
                    
                    <a href="issue_report_page.php" class="btn-quick-action secondary-action">
                        <div class="action-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="action-content">
                            <span class="action-title">Report Issue</span>
                            <small class="action-subtitle">Get support</small>
                        </div>
                    </a>
                    
                    <a href="profile_page.php" class="btn-quick-action secondary-action">
                        <div class="action-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="action-content">
                            <span class="action-title">Update Profile</span>
                            <small class="action-subtitle">Edit details</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Requests -->
        <div class="grid-item">
            <div class="content-card">
                <h5 class="mb-3"><i class="fas fa-history me-2"></i>Recent Requests</h5>
                
                <?php if (empty($recent_requests)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox text-muted mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="text-muted">No requests yet</p>
                    <a href="new_request_page.php" class="btn btn-outline-primary btn-sm">Create Your First Request</a>
                </div>
                <?php else: ?>
                    <?php foreach ($recent_requests as $request): ?>
                    <div class="request-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars($request['bag_number']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo date('M j, Y', strtotime($request['created_at'])); ?></small>
                            </div>
                            <span class="status-badge status-<?php echo $request['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-3">
                        <a href="my_requests_page.php" class="btn btn-outline-primary btn-sm">View All Requests</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Notification Details Modal -->
<div class="notification-modal" id="notification-modal">
    <div class="notification-modal-backdrop" onclick="closeNotificationModal()"></div>
    <div class="notification-modal-container">
        <div class="notification-modal-header">
            <h3 class="notification-modal-title" id="modal-notification-title">Notification Details</h3>
            <button class="notification-modal-close" onclick="closeNotificationModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="notification-modal-content">
            <div class="notification-detail-meta">
                <div class="notification-type-badge" id="modal-notification-type">
                    <i class="fas fa-info-circle"></i>
                    <span>Info</span>
                </div>
                <div class="notification-timestamp" id="modal-notification-timestamp">
                    Just now
                </div>
            </div>
            <div class="notification-detail-message" id="modal-notification-message">
                Loading notification details...
            </div>
            <div class="notification-modal-actions" id="modal-notification-actions">
                <!-- Action buttons will be dynamically added here -->
            </div>
        </div>
        <div class="notification-modal-loading" id="modal-loading">
            <div class="spinner-small"></div>
            <span>Loading details...</span>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
// Create floating particles
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

// Animate stat numbers on load
function animateStatNumbers() {
    const statNumbers = document.querySelectorAll('.stat-number[data-target]');
    
    statNumbers.forEach(stat => {
        const target = parseInt(stat.getAttribute('data-target'));
        const increment = target > 0 ? target / 50 : 0;
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

// Toggle sidebar for mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}

// Modern Notification System with proper backend integration
function fetchNotifications() {
    fetch('notifications_ajax.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(notifications => {
        updateNotificationUI(notifications);
    })
    .catch(error => {
        console.error('Error fetching notifications:', error);
        // Fallback to empty notifications on error
        updateNotificationUI([]);
    });
}

function updateNotificationUI(notifications) {
    const countElement = document.getElementById('notification-count');
    const listElement = document.getElementById('notification-list');
    const markAllBtn = document.getElementById('mark-all-read-btn');
    
    // Update count - only show actual unread notifications
    const unreadCount = notifications.length;
    countElement.textContent = unreadCount;
    
    if (unreadCount > 0) {
        countElement.classList.add('show');
        if (markAllBtn) markAllBtn.style.display = 'flex'; // Show mark-all-read button
    } else {
        countElement.classList.remove('show');
        if (markAllBtn) markAllBtn.style.display = 'none'; // Hide mark-all-read button
    }
    
    // Update notification list
    if (unreadCount === 0) {
        listElement.innerHTML = `
            <div class="no-notifications">
                <i class="fas fa-bell-slash"></i>
                <div>No new notifications</div>
            </div>
        `;
    } else {
        listElement.innerHTML = notifications.map(notification => `
            <div class="notification-item" onclick="handleNotificationClick(${notification.notification_id}, '${notification.target_url || ''}')">
                <div class="notification-item-title">${escapeHtml(notification.title)}</div>
                <div class="notification-item-message">${notification.message}</div>
                <div class="notification-item-time">${formatNotificationTime(notification.created_at)}</div>
            </div>
        `).join('');
    }
}

function handleNotificationClick(notificationId, targetUrl) {
    // Show notification details modal
    showNotificationDetails(notificationId);
    
    // Mark notification as read (but don't close dropdown yet)
    markNotificationAsRead(notificationId);
}

function showNotificationDetails(notificationId) {
    const modal = document.getElementById('notification-modal');
    const loading = document.getElementById('modal-loading');
    const content = modal.querySelector('.notification-modal-content');
    
    // Show modal and loading state
    modal.classList.add('show');
    loading.classList.add('show');
    content.style.opacity = '0.5';
    
    // Close the notification dropdown
    closeNotificationDropdown();
    
    // Fetch detailed notification data
    fetch('notifications_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_details&notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        loading.classList.remove('show');
        content.style.opacity = '1';
        
        if (data.success) {
            populateNotificationModal(data.notification);
        } else {
            showNotificationError(data.error || 'Failed to load notification details');
        }
    })
    .catch(error => {
        loading.classList.remove('show');
        content.style.opacity = '1';
        console.error('Error fetching notification details:', error);
        showNotificationError('Network error occurred');
    });
}

function populateNotificationModal(notification) {
    // Update modal title
    document.getElementById('modal-notification-title').textContent = notification.title;
    
    // Update type badge
    const typeBadge = document.getElementById('modal-notification-type');
    const typeIcon = typeBadge.querySelector('i');
    const typeText = typeBadge.querySelector('span');
    
    // Remove existing type classes
    typeBadge.className = 'notification-type-badge';
    typeBadge.classList.add(`type-${notification.type}`);
    
    // Set appropriate icon based on type
    const iconMap = {
        'info': 'fas fa-info-circle',
        'success': 'fas fa-check-circle',
        'warning': 'fas fa-exclamation-triangle',
        'error': 'fas fa-times-circle'
    };
    
    typeIcon.className = iconMap[notification.type] || 'fas fa-info-circle';
    typeText.textContent = notification.type.charAt(0).toUpperCase() + notification.type.slice(1);
    
    // Update timestamp
    document.getElementById('modal-notification-timestamp').textContent = formatNotificationTime(notification.created_at);
    
    // Update message (allow HTML for formatted content)
    document.getElementById('modal-notification-message').innerHTML = notification.message;
    
    // Update actions
    const actionsContainer = document.getElementById('modal-notification-actions');
    actionsContainer.innerHTML = '';
    
    // Add target URL action if present
    if (notification.target_url && notification.target_url.trim() !== '') {
        const viewBtn = document.createElement('a');
        // Clean up the target URL to use the correct page name
        let targetUrl = notification.target_url;
        if (targetUrl.includes('manage_requests_page.php')) {
            targetUrl = targetUrl.replace('manage_requests_page.php', 'my_requests_page.php');
        }
        
        viewBtn.href = targetUrl;
        viewBtn.className = 'notification-action-btn primary';
        viewBtn.innerHTML = '<i class="fas fa-external-link-alt"></i> View Request';
        viewBtn.onclick = function(e) {
            e.preventDefault();
            closeNotificationModal();
            // Small delay to allow modal close animation
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 200);
        };
        actionsContainer.appendChild(viewBtn);
    }
    
    // Add close button
    const closeBtn = document.createElement('button');
    closeBtn.className = 'notification-action-btn secondary';
    closeBtn.innerHTML = '<i class="fas fa-times"></i> Close';
    closeBtn.onclick = closeNotificationModal;
    actionsContainer.appendChild(closeBtn);
}

function showNotificationError(message) {
    document.getElementById('modal-notification-title').textContent = 'Error';
    document.getElementById('modal-notification-message').innerHTML = `
        <div style="text-align: center; padding: 20px;">
            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 12px; color: #f87171;"></i>
            <p>${escapeHtml(message)}</p>
        </div>
    `;
    
    // Clear actions and add only close button
    const actionsContainer = document.getElementById('modal-notification-actions');
    actionsContainer.innerHTML = `
        <button class="notification-action-btn secondary" onclick="closeNotificationModal()">
            <i class="fas fa-times"></i> Close
        </button>
    `;
    
    // Hide type badge for error state
    document.getElementById('modal-notification-type').style.display = 'none';
    document.getElementById('modal-notification-timestamp').style.display = 'none';
}

function closeNotificationModal() {
    const modal = document.getElementById('notification-modal');
    modal.classList.remove('show');
    
    // Reset modal state
    setTimeout(() => {
        document.getElementById('modal-notification-type').style.display = 'flex';
        document.getElementById('modal-notification-timestamp').style.display = 'block';
        document.getElementById('modal-loading').classList.remove('show');
        document.querySelector('.notification-modal-content').style.opacity = '1';
    }, 300);
}

function markNotificationAsRead(notificationId) {
    fetch('notifications_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=mark_read&notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh notifications immediately to update the count
            fetchNotifications();
        } else {
            console.error('Failed to mark notification as read:', data.error);
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

function markAllNotificationsAsRead() {
    fetch('notifications_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_all_read'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh notifications immediately
            fetchNotifications();
        } else {
            console.error('Failed to mark all notifications as read:', data.error);
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
    });
}

// Helper function to escape HTML and prevent XSS
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function formatNotificationTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diffInMinutes = Math.floor((now - date) / (1000 * 60));
    
    if (diffInMinutes < 1) return 'Just now';
    if (diffInMinutes < 60) return `${diffInMinutes}m ago`;
    if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)}h ago`;
    return `${Math.floor(diffInMinutes / 1440)}d ago`;
}

// Notification dropdown toggle with DOM portal approach
document.getElementById('notification-bell').addEventListener('click', function(e) {
    e.stopPropagation();
    let dropdown = document.getElementById('notification-dropdown');
    
    if (dropdown.classList.contains('show')) {
        closeNotificationDropdown();
    } else {
        openNotificationDropdown(this);
    }
});

function openNotificationDropdown(bell) {
    let dropdown = document.getElementById('notification-dropdown');
    
    // Move dropdown to body to escape all stacking contexts
    if (dropdown.parentElement !== document.body) {
        document.body.appendChild(dropdown);
    }
    
    // Calculate position relative to the notification bell
    const bellRect = bell.getBoundingClientRect();
    const dropdownWidth = 340;
    
    // Position dropdown below the bell
    const top = bellRect.bottom + 10;
    let left = bellRect.right - dropdownWidth;
    
    // Ensure dropdown doesn't go off-screen
    const viewportWidth = window.innerWidth;
    if (left < 10) {
        left = 10;
    } else if (left + dropdownWidth > viewportWidth - 10) {
        left = viewportWidth - dropdownWidth - 10;
    }
    
    // Apply positioning with maximum priority
    dropdown.style.position = 'fixed';
    dropdown.style.top = top + 'px';
    dropdown.style.left = left + 'px';
    dropdown.style.zIndex = '2147483647';
    
    // Show dropdown
    dropdown.classList.add('show');
}

function closeNotificationDropdown() {
    const dropdown = document.getElementById('notification-dropdown');
    dropdown.classList.remove('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('notification-dropdown');
    const bell = document.getElementById('notification-bell');
    
    if (!dropdown.contains(e.target) && !bell.contains(e.target)) {
        closeNotificationDropdown();
    }
});

// Close mobile sidebar when clicking outside
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('.mobile-menu-toggle');
    
    if (window.innerWidth <= 768 && sidebar.classList.contains('show') && 
        !sidebar.contains(e.target) && !toggle.contains(e.target)) {
        sidebar.classList.remove('show');
    }
});

// Initialize notifications with proper error handling and reasonable polling
let notificationPollInterval = null;

function initializeNotifications() {
    // Fetch notifications immediately
    fetchNotifications();
    
    // Set up polling every 30 seconds (reduced from 15 seconds to reduce server load)
    if (notificationPollInterval) {
        clearInterval(notificationPollInterval);
    }
    
    notificationPollInterval = setInterval(() => {
        // Only fetch if the page is visible to avoid unnecessary requests
        if (!document.hidden) {
            fetchNotifications();
        }
    }, 30000);
}

// Clean up interval when page is unloaded
window.addEventListener('beforeunload', function() {
    if (notificationPollInterval) {
        clearInterval(notificationPollInterval);
    }
});

// Keyboard support for modal
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('notification-modal');
    if (modal.classList.contains('show')) {
        if (e.key === 'Escape') {
            closeNotificationModal();
        }
    }
});

// Close modal when clicking on backdrop
document.addEventListener('click', function(e) {
    const modal = document.getElementById('notification-modal');
    if (e.target.classList.contains('notification-modal-backdrop')) {
        closeNotificationModal();
    }
});

// Pause polling when page becomes hidden, resume when visible
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        if (notificationPollInterval) {
            clearInterval(notificationPollInterval);
            notificationPollInterval = null;
        }
    } else {
        // Resume polling when page becomes visible again
        initializeNotifications();
    }
});

// On page load
window.onload = function() {
    createParticles();
    animateStatNumbers();
    initializeNotifications(); // Use the new initialization function
};

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth > 768) {
        sidebar.classList.remove('show');
    }
});
</script>
</body>
</html>