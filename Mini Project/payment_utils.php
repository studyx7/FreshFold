<?php
/**
 * Payment utility functions for FreshFold Laundry System
 * Include this file where payment-related operations are needed
 */

require_once 'freshfold_config.php';

class PaymentUtils {
    private $db;
    private $razorpay_key_id;
    private $razorpay_key_secret;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();

        // Use defined constants or fallback to default values
        $this->razorpay_key_id = defined('RAZORPAY_KEY_ID') ? RAZORPAY_KEY_ID : 'rzp_test_RB1vkBgk5LPRlL';
        $this->razorpay_key_secret = defined('RAZORPAY_KEY_SECRET') ? RAZORPAY_KEY_SECRET : '7XkmtPaSZPYnEmCCRIpj7Gem';
    }
    
    /**
     * Check if student has paid for current academic year
     */
    public function hasStudentPaid($student_id, $academic_year = null) {
        if (!$academic_year) {
            $academic_year = date('Y');
        }
        
        // Check if student_payments table exists
        if (!$this->tableExists('student_payments')) {
            // Fallback to users table
            $stmt = $this->db->prepare("SELECT payment_status, payment_year FROM users WHERE user_id = ?");
            $stmt->execute([$student_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($user && $user['payment_status'] === 'Paid' && $user['payment_year'] == $academic_year);
        }
        
        $stmt = $this->db->prepare("
            SELECT payment_status 
            FROM student_payments 
            WHERE student_id = ? AND academic_year = ? AND payment_status = 'completed'
        ");
        $stmt->execute([$student_id, $academic_year]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get payment details for a student
     */
    public function getStudentPaymentDetails($student_id, $academic_year = null) {
        if (!$academic_year) {
            $academic_year = date('Y');
        }
        
        if (!$this->tableExists('student_payments')) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT sp.*, u.full_name, u.email 
            FROM student_payments sp
            JOIN users u ON sp.student_id = u.user_id
            WHERE sp.student_id = ? AND sp.academic_year = ?
            ORDER BY sp.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$student_id, $academic_year]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create a new Razorpay order
     */
    public function createRazorpayOrder($student_id, $amount, $academic_year = null) {
        if (!$academic_year) {
            $academic_year = date('Y');
        }
        
        $order_data = [
            'receipt' => 'FL_' . $student_id . '_' . $academic_year . '_' . time(),
            'amount' => (float)$amount * 100, // Amount in paise
            'currency' => 'INR',
            'notes' => [
                'student_id' => $student_id,
                'academic_year' => $academic_year,
                'purpose' => 'Annual Laundry Fee'
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
        curl_setopt($ch, CURLOPT_USERPWD, $this->razorpay_key_id . ':' . $this->razorpay_key_secret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            $order = json_decode($response, true);
            
            // Store order in database if table exists
            if ($this->tableExists('student_payments')) {
                $stmt = $this->db->prepare("
                    INSERT INTO student_payments (student_id, amount, academic_year, razorpay_order_id, payment_status) 
                    VALUES (?, ?, ?, ?, 'pending') 
                    ON DUPLICATE KEY UPDATE 
                    razorpay_order_id = VALUES(razorpay_order_id), 
                    payment_status = 'pending',
                    updated_at = NOW()
                ");
                $stmt->execute([$student_id, $amount, $academic_year, $order['id']]);
            }
            
            return $order;
        }
        
        return false;
    }
    
    /**
     * Verify Razorpay payment signature
     */
    public function verifyPaymentSignature($order_id, $payment_id, $signature) {
        $body = $order_id . "|" . $payment_id;
        $expected_signature = hash_hmac('sha256', $body, $this->razorpay_key_secret);
        
        return hash_equals($expected_signature, $signature);
    }
    
    /**
     * Process successful payment
     */
    public function processSuccessfulPayment($payment_id, $order_id, $signature, $student_id = null) {
        try {
            $this->db->beginTransaction();
            
            // Get payment record
            if ($this->tableExists('student_payments')) {
                $stmt = $this->db->prepare("
                    SELECT student_id, academic_year, amount 
                    FROM student_payments 
                    WHERE razorpay_order_id = ?
                ");
                $stmt->execute([$order_id]);
                $payment_record = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$payment_record) {
                    throw new Exception('Payment record not found');
                }
                
                // Update student_payments table
                $stmt = $this->db->prepare("
                    UPDATE student_payments 
                    SET payment_status = 'completed', 
                        razorpay_payment_id = ?, 
                        razorpay_signature = ?, 
                        transaction_date = NOW(),
                        updated_at = NOW()
                    WHERE razorpay_order_id = ?
                ");
                $stmt->execute([$payment_id, $signature, $order_id]);
                
                // Update users table
                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET payment_status = 'Paid', 
                        payment_year = ? 
                    WHERE user_id = ?
                ");
                $stmt->execute([$payment_record['academic_year'], $payment_record['student_id']]);
                
                // Add to payments table for record keeping
                $stmt = $this->db->prepare("
                    INSERT INTO payments (
                        user_id, amount, payment_method, transaction_id, 
                        razorpay_payment_id, razorpay_order_id, razorpay_signature, 
                        payment_status, payment_type, academic_year
                    ) VALUES (?, ?, 'razorpay', ?, ?, ?, ?, 'completed', 'annual_fee', ?)
                ");
                $stmt->execute([
                    $payment_record['student_id'], 
                    $payment_record['amount'], 
                    $payment_id, 
                    $payment_id, 
                    $order_id, 
                    $signature, 
                    $payment_record['academic_year']
                ]);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Payment processing error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get payment history for a student
     */
    public function getPaymentHistory($student_id) {
        if (!$this->tableExists('student_payments')) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                academic_year,
                amount,
                payment_status,
                razorpay_payment_id,
                transaction_date,
                created_at
            FROM student_payments 
            WHERE student_id = ? 
            ORDER BY academic_year DESC
        ");
        $stmt->execute([$student_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get pending payments (for admin dashboard)
     */
    public function getPendingPayments() {
        if (!$this->tableExists('student_payments')) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                sp.payment_id,
                sp.student_id,
                sp.academic_year,
                sp.amount,
                sp.created_at,
                u.full_name,
                u.email,
                u.hostel_block,
                u.room_number
            FROM student_payments sp
            JOIN users u ON sp.student_id = u.user_id
            WHERE sp.payment_status = 'pending'
            ORDER BY sp.created_at DESC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate payment report
     */
    public function generatePaymentReport($academic_year = null, $status = null) {
        if (!$this->tableExists('student_payments')) {
            return [];
        }
        
        $where_conditions = [];
        $params = [];
        
        if ($academic_year) {
            $where_conditions[] = "sp.academic_year = ?";
            $params[] = $academic_year;
        }
        
        if ($status) {
            $where_conditions[] = "sp.payment_status = ?";
            $params[] = $status;
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        $stmt = $this->db->prepare("
            SELECT 
                sp.payment_id,
                sp.student_id,
                sp.academic_year,
                sp.amount,
                sp.payment_status,
                sp.transaction_date,
                u.full_name,
                u.email,
                u.hostel_block,
                u.room_number,
                COUNT(sp.payment_id) OVER() as total_records,
                SUM(CASE WHEN sp.payment_status = 'completed' THEN sp.amount ELSE 0 END) OVER() as total_collected
            FROM student_payments sp
            JOIN users u ON sp.student_id = u.user_id
            $where_clause
            ORDER BY sp.transaction_date DESC, sp.created_at DESC
        ");
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if a table exists in the database
     */
    private function tableExists($tableName) {
        try {
            $stmt = $this->db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Initialize payment system (create tables if they don't exist)
     */
    public function initializePaymentSystem() {
        try {
            // Create student_payments table if it doesn't exist
            if (!$this->tableExists('student_payments')) {
                $sql = "CREATE TABLE `student_payments` (
                    `payment_id` int(11) NOT NULL AUTO_INCREMENT,
                    `student_id` int(11) NOT NULL,
                    `amount` decimal(10,2) NOT NULL,
                    `academic_year` year NOT NULL,
                    `payment_status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
                    `razorpay_order_id` varchar(255) DEFAULT NULL,
                    `razorpay_payment_id` varchar(255) DEFAULT NULL,
                    `razorpay_signature` varchar(255) DEFAULT NULL,
                    `payment_method` varchar(50) DEFAULT 'razorpay',
                    `transaction_date` timestamp NULL DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`payment_id`),
                    UNIQUE KEY `unique_student_year` (`student_id`, `academic_year`),
                    KEY `idx_student_payment` (`student_id`, `academic_year`),
                    KEY `idx_payment_status` (`payment_status`),
                    CONSTRAINT `student_payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                
                $this->db->exec($sql);
            }
            
            // Add payment columns to users table if they don't exist
            $this->addColumnIfNotExists('users', 'payment_status', "ENUM('Paid', 'Not Paid') DEFAULT 'Not Paid'");
            $this->addColumnIfNotExists('users', 'payment_year', "YEAR DEFAULT NULL");
            
            // Add Razorpay settings if they don't exist
            $this->addSettingIfNotExists('razorpay_key_id', 'rzp_test_RB1vkBgk5LPRlL', 'Razorpay Key ID');
            $this->addSettingIfNotExists('annual_fee_amount', '8500.00', 'Annual laundry fee amount');
            
            return true;
        } catch (Exception $e) {
            error_log("Payment system initialization error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add column to table if it doesn't exist
     */
    private function addColumnIfNotExists($table, $column, $definition) {
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            
            if ($stmt->rowCount() == 0) {
                $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
                $this->db->exec($sql);
            }
        } catch (Exception $e) {
            error_log("Error adding column $column to table $table: " . $e->getMessage());
        }
    }
    
    /**
     * Add setting to settings table if it doesn't exist
     */
    private function addSettingIfNotExists($key, $value, $description) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            
            if ($stmt->fetchColumn() == 0) {
                $stmt = $this->db->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
                $stmt->execute([$key, $value, $description]);
            }
        } catch (Exception $e) {
            error_log("Error adding setting $key: " . $e->getMessage());
        }
    }
}

// Usage example:
// $paymentUtils = new PaymentUtils();
// $paymentUtils->initializePaymentSystem(); // Call this once to set up tables
// $hasPaid = $paymentUtils->hasStudentPaid($student_id);
// $paymentHistory = $paymentUtils->getPaymentHistory($student_id);
?>