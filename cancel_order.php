<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // User is not logged in, redirect to login page
    header("Location: auth/login.php");
    exit();
}

// Initialize response variables
$response = [
    'success' => false,
    'message' => ''
];

// Check if this is a POST request and order_id is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && is_numeric($_POST['order_id'])) {
    $order_id = $_POST['order_id'];
    $user_id = $_SESSION['user_id'];
    
    try {
        // First check if the order belongs to the current user and is in a cancellable state
        $check_stmt = $conn->prepare("
            SELECT status 
            FROM orders 
            WHERE order_id = :order_id AND user_id = :user_id
        ");
        $check_stmt->bindParam(':order_id', $order_id);
        $check_stmt->bindParam(':user_id', $user_id);
        $check_stmt->execute();
        $order = $check_stmt->fetch();
        
        if ($order) {
            // Check if order is in a cancellable state
            if ($order['status'] === 'pending' || $order['status'] === 'processing') {
                // Begin transaction
                $conn->beginTransaction();
                
                // 1. Update order status to 'cancelled'
                $update_stmt = $conn->prepare("
                    UPDATE orders 
                    SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP 
                    WHERE order_id = :order_id AND user_id = :user_id
                ");
                $update_stmt->bindParam(':order_id', $order_id);
                $update_stmt->bindParam(':user_id', $user_id);
                $update_stmt->execute();
                
                // 2. Get order items to restore stock
                $items_stmt = $conn->prepare("
                    SELECT product_id, quantity 
                    FROM order_items 
                    WHERE order_id = :order_id
                ");
                $items_stmt->bindParam(':order_id', $order_id);
                $items_stmt->execute();
                $items = $items_stmt->fetchAll();
                
                // 3. Restore product stock quantities
                $restore_stock_stmt = $conn->prepare("
                    UPDATE products 
                    SET stock_quantity = stock_quantity + :quantity 
                    WHERE product_id = :product_id
                ");
                
                foreach ($items as $item) {
                    if ($item['product_id']) { // Check if product_id is not null
                        $restore_stock_stmt->bindParam(':quantity', $item['quantity']);
                        $restore_stock_stmt->bindParam(':product_id', $item['product_id']);
                        $restore_stock_stmt->execute();
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
                // Set success response
                $response['success'] = true;
                $response['message'] = 'Order cancelled successfully.';
                
                // Redirect back to the order details page
                header("Location: orders.php?order_id=$order_id&cancelled=true");
                exit();
                
            } else {
                // Order is not in a cancellable state
                $response['message'] = 'This order cannot be cancelled because it has already been ' . $order['status'] . '.';
                
                // Redirect with error
                header("Location: orders.php?order_id=$order_id&error=" . urlencode($response['message']));
                exit();
            }
        } else {
            // Order not found or doesn't belong to current user
            $response['message'] = 'Order not found or unauthorized access.';
            
            // Redirect with error
            header("Location: orders.php?error=" . urlencode($response['message']));
            exit();
        }
        
    } catch (PDOException $e) {
        // Rollback transaction if there was an error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        $response['message'] = 'Database error: ' . $e->getMessage();
        
        // Log the error
        error_log('Order cancellation error: ' . $e->getMessage());
        
        // Redirect with error
        header("Location: orders.php?error=" . urlencode('An error occurred while processing your request.'));
        exit();
    }
} else {
    // Invalid request
    $response['message'] = 'Invalid request.';
    
    // Redirect back to orders page
    header("Location: orders.php");
    exit();
}

// If we get here, something went wrong and we haven't redirected yet
// This is a fallback in case the expected flow is interrupted
header("Location: orders.php?error=" . urlencode($response['message'] ?: 'An unknown error occurred.'));
exit();
?>