<?php
/**
 * Razorpay Webhook Handler
 * Place this file as webhook.php in your project root
 * Configure webhook URL in Razorpay dashboard: https://yourdomain.com/webhook.php
 */

require_once 'freshfold_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Get the request body
$input = file_get_contents('php://input');
$webhookSignature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
$webhookSecret = 'your_webhook_secret'; // Set this in Razorpay dashboard

// Verify webhook signature (recommended for production)
if ($webhookSecret && !hash_equals(hash_hmac('sha256', $input, $webhookSecret), $webhookSignature)) {
    http_response_code(400);
    exit('Invalid signature');
}

// Parse webhook data
$webhookData = json_decode($input, true);

if (!$webhookData) {
    http_response_code(400);
    exit('Invalid JSON');
}

$database = new Database();
$db = $database->getConnection();

try {
    $event = $webhookData['event'];
    $paymentEntity = $webhookData['payload']['payment']['entity'] ?? null;
    
    if (!$paymentEntity) {
        throw new Exception('Payment entity not found in webhook');
    }
    
    $razorpay_payment_id = $paymentEntity['id'];
    $razorpay_order_id = $paymentEntity['order_id'];
    $amount = $paymentEntity['amount'] / 100; // Convert from paise
    $status = $paymentEntity['status'];
    
    switch ($event) {
        case 'payment.captured':
            // Payment successful
            $db->beginTransaction();
            
            // Find the student payment record
            $stmt = $db->prepare("SELECT student_id, academic_year FROM student_payments WHERE razorpay_order_id = ?");
            $stmt->execute([$razorpay_order_id]);
            $payment_record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($payment_record) {
                // Update payment status
                $stmt = $db->prepare("UPDATE student_payments SET payment_status = 'completed', razorpay_payment_id = ?, transaction_date = NOW() WHERE razorpay_order_id = ?");
                $stmt->execute([$razorpay_payment_id, $razorpay_order_id]);
                
                // Update user payment status
                $stmt = $db->prepare("UPDATE users SET payment_status = 'Paid', payment_year = ? WHERE user_id = ?");
                $stmt->execute([$payment_record['academic_year'], $payment_record['student_id']]);
                
                // Log the successful payment
                if (function_exists('logPaymentActivity')) {
                    logPaymentActivity($payment_record['student_id'], 'payment_captured', [
                        'payment_id' => $razorpay_payment_id,
                        'order_id' => $razorpay_order_id,
                        'amount' => $amount
                    ]);
                }
            }
            
            $db->commit();
            break;
            
        case 'payment.failed':
            // Payment failed
            $stmt = $db->prepare("UPDATE student_payments SET payment_status = 'failed' WHERE razorpay_order_id = ?");
            $stmt->execute([$razorpay_order_id]);
            
            // Log the failed payment
            if (function_exists('logPaymentActivity')) {
                $stmt = $db->prepare("SELECT student_id FROM student_payments WHERE razorpay_order_id = ?");
                $stmt->execute([$razorpay_order_id]);
                $student_id = $stmt->fetchColumn();
                
                if ($student_id) {
                    logPaymentActivity($student_id, 'payment_failed', [
                        'payment_id' => $razorpay_payment_id,
                        'order_id' => $razorpay_order_id,
                        'reason' => $paymentEntity['error_description'] ?? 'Payment failed'
                    ]);
                }
            }
            break;
            
        case 'order.paid':
            // Order fully paid - additional confirmation
            error_log("Order paid webhook received for order: " . $razorpay_order_id);
            break;
            
        default:
            // Log unhandled events
            error_log("Unhandled Razorpay webhook event: " . $event);
            break;
    }
    
    // Send success response
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    // Rollback transaction if active
    if ($db->inTransaction()) {
        $db->rollback();
    }
    
    // Log error
    error_log("Webhook processing error: " . $e->getMessage());
    
    // Send error response
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>