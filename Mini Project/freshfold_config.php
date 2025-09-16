<?php
// config/database.php - Database configuration
session_start();

class Database {
    private $host = "localhost";
    private $db_name = "freshfold_laundry";
    private $username = "root";  // Change this to your DB username
    private $password = "";      // Change this to your DB password
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            // Show a user-friendly error and stop execution
            die("Database connection error: " . $exception->getMessage() . "<br>Please check if MySQL/XAMPP is running and your DB credentials are correct.");
        }
        return $this->conn;
    }
}

// classes/User.php - User management class
class User {
    private $conn;
    private $table_name = "users";

    public $user_id;
    public $username;
    public $email;
    public $password_hash;
    public $full_name;
    public $phone;
    public $gender;
    public $user_type;
    public $hostel_block;
    public $room_number;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Register new user
    public function register() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (username, email, password_hash, full_name, phone, gender, user_type, hostel_block, room_number) 
                  VALUES (:username, :email, :password_hash, :full_name, :phone, :gender, :user_type, :hostel_block, :room_number)";

        $stmt = $this->conn->prepare($query);

        // Hash password
        $this->password_hash = password_hash($this->password_hash, PASSWORD_DEFAULT);

        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password_hash", $this->password_hash);
        $stmt->bindParam(":full_name", $this->full_name);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":gender", $this->gender);
        $stmt->bindParam(":user_type", $this->user_type);
        $stmt->bindParam(":hostel_block", $this->hostel_block);
        $stmt->bindParam(":room_number", $this->room_number);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Login user
    public function login($username, $password) {
        $query = "SELECT user_id, username, email, password_hash, full_name, user_type, hostel_block, room_number 
                  FROM " . $this->table_name . " 
                  WHERE (username = :username OR email = :username) AND is_active = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if(password_verify($password, $row['password_hash'])) {
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['full_name'] = $row['full_name'];
                $_SESSION['user_type'] = $row['user_type'];
                $_SESSION['hostel_block'] = $row['hostel_block'];
                $_SESSION['room_number'] = $row['room_number'];
                return true;
            }
        }
        return false;
    }

    // Check if user is logged in
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    // Get user type
    public static function getUserType() {
        return isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;
    }

    // Logout user
    public static function logout() {
        session_destroy();
        header("Location: login_page.php");
        exit();
    }

    // Get user by ID
    public function getUserById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }
}

// classes/LaundryRequest.php - Laundry request management
class LaundryRequest {
    private $conn;
    private $table_name = "laundry_requests";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create new laundry request
    public function createRequest($student_id, $pickup_date, $special_instructions = "") {
        // Generate unique bag number
        $bag_number = "FL" . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $query = "INSERT INTO " . $this->table_name . " 
                  (student_id, bag_number, pickup_date, expected_delivery, special_instructions) 
                  VALUES (:student_id, :bag_number, :pickup_date, :expected_delivery, :special_instructions)";

        $stmt = $this->conn->prepare($query);
        
        // Calculate expected delivery (3 days from pickup)
        $expected_delivery = date('Y-m-d', strtotime($pickup_date . ' +3 days'));

        $stmt->bindParam(":student_id", $student_id);
        $stmt->bindParam(":bag_number", $bag_number);
        $stmt->bindParam(":pickup_date", $pickup_date);
        $stmt->bindParam(":expected_delivery", $expected_delivery);
        $stmt->bindParam(":special_instructions", $special_instructions);

        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // Get requests by student ID
    public function getStudentRequests($student_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE student_id = :student_id 
                  ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":student_id", $student_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Update request status
    public function updateStatus($request_id, $new_status, $updated_by, $remarks = "") {
        // Get current status
        $current_query = "SELECT status FROM " . $this->table_name . " WHERE request_id = :request_id";
        $current_stmt = $this->conn->prepare($current_query);
        $current_stmt->bindParam(":request_id", $request_id);
        $current_stmt->execute();
        $current_status = $current_stmt->fetchColumn();

        // Update status
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status, updated_at = CURRENT_TIMESTAMP 
                  WHERE request_id = :request_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $new_status);
        $stmt->bindParam(":request_id", $request_id);
        
        if($stmt->execute()) {
            // Log status change
            $this->logStatusChange($request_id, $current_status, $new_status, $updated_by, $remarks);

            // Send notification to student if status changed
            $student_id = $this->getStudentIdByRequest($request_id);
            if ($student_id) {
                $title = "Laundry Request Status Updated";
                $msg = "Your laundry request #$request_id status changed to <b>" . ucfirst($new_status) . "</b>.";
                $target_url = "manage_requests_page.php?open_request_id=$request_id";
                $this->sendNotification($student_id, $title, $msg, 'info', $target_url);
            }
            return true;
        }
        return false;
    }

    // Log status changes
    private function logStatusChange($request_id, $old_status, $new_status, $updated_by, $remarks) {
        $query = "INSERT INTO status_history 
                  (request_id, old_status, new_status, updated_by, remarks) 
                  VALUES (:request_id, :old_status, :new_status, :updated_by, :remarks)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":request_id", $request_id);
        $stmt->bindParam(":old_status", $old_status);
        $stmt->bindParam(":new_status", $new_status);
        $stmt->bindParam(":updated_by", $updated_by);
        $stmt->bindParam(":remarks", $remarks);
        
        $stmt->execute();
    }

    // Get all requests (for staff/admin)
    public function getAllRequests($status = null) {
        $query = "SELECT lr.*, u.full_name, u.hostel_block, u.room_number, u.phone 
                  FROM " . $this->table_name . " lr 
                  JOIN users u ON lr.student_id = u.user_id";
        
        if($status) {
            $query .= " WHERE lr.status = :status";
        }
        
        $query .= " ORDER BY lr.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        
        if($status) {
            $stmt->bindParam(":status", $status);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getStudentIdByRequest($request_id) {
        $stmt = $this->conn->prepare("SELECT student_id FROM laundry_requests WHERE request_id = ?");
        $stmt->execute([$request_id]);
        return $stmt->fetchColumn();
    }

    private function sendNotification($user_id, $title, $message, $type = 'info', $target_url = null) {
        $stmt = $this->conn->prepare("INSERT INTO notifications (user_id, title, message, type, target_url) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $message, $type, $target_url]);
    }
}

// Add this utility function at the end of the file (outside any class)
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login_page.php');
        exit();
    }
}

// Add this utility function at the end of the file (outside any class)
function redirect($url) {
    header("Location: $url");
    exit();
}

// Add this utility function at the end of the file (outside any class)
function requireUserType($type) {
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== $type) {
        header('Location: login_page.php');
        exit();
    }
}

// Add this utility function at the end of the file (outside any class)
function displayAlerts() {
    if (!empty($GLOBALS['success_message'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
            . htmlspecialchars($GLOBALS['success_message']) .
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
    if (!empty($GLOBALS['error_message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
            . htmlspecialchars($GLOBALS['error_message']) .
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
}

// Add this utility function at the end of the file (outside any class)
function showAlert($message, $type = 'info') {
    // Store the alert in a global variable for displayAlerts()
    if ($type === 'success') {
        $GLOBALS['success_message'] = $message;
    } else {
        $GLOBALS['error_message'] = $message;
    }
}