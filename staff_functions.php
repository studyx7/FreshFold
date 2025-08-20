<?php
// filepath: c:\xampp\htdocs\Mini Project\staff_functions.php

require_once 'freshfold_config.php';

/**
 * Check if the current user is staff.
 * @return bool
 */
function isStaff() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'staff';
}

/**
 * Get all students for staff view.
 * @param PDO $db
 * @return array
 */
function getAllStudents($db) {
    $stmt = $db->prepare("SELECT * FROM users WHERE user_type = 'student' ORDER BY full_name");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get laundry requests assigned to staff.
 * @param PDO $db
 * @param int $staff_id
 * @return array
 */
function getAssignedRequests($db, $staff_id) {
    $stmt = $db->prepare("SELECT lr.*, u.full_name, u.room_number 
        FROM laundry_requests lr
        JOIN users u ON lr.student_id = u.user_id
        WHERE lr.assigned_staff_id = ?
        ORDER BY lr.created_at DESC");
    $stmt->execute([$staff_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Update request status by staff.
 * @param PDO $db
 * @param int $request_id
 * @param string $new_status
 * @param string $remarks
 * @return bool
 */
function staffUpdateRequestStatus($db, $request_id, $new_status, $remarks = '') {
    $stmt = $db->prepare("UPDATE laundry_requests SET status = ?, staff_remarks = ? WHERE request_id = ?");
    return $stmt->execute([$new_status, $remarks, $request_id]);
}

/**
 * Get staff statistics.
 * @param PDO $db
 * @return array
 */
function getStaffStats($db) {
    $stats = [];
    $stats['total_students'] = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'student'")->fetchColumn();
    $stats['total_requests'] = $db->query("SELECT COUNT(*) FROM laundry_requests")->fetchColumn();
    $stats['delivered'] = $db->query("SELECT COUNT(*) FROM laundry_requests WHERE status = 'delivered'")->fetchColumn();
    $stats['submitted'] = $db->query("SELECT COUNT(*) FROM laundry_requests WHERE status = 'submitted'")->fetchColumn();
    $stats['processing'] = $db->query("SELECT COUNT(*) FROM laundry_requests WHERE status = 'processing'")->fetchColumn();
    return $stats;
}