<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Count items in user's cart
    $stmt = $conn->prepare("
        SELECT SUM(quantity) AS total_items 
        FROM cart 
        WHERE user_id = :user_id
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $cart_count = $result['total_items'] ? intval($result['total_items']) : 0;
    
    // Return cart count
    echo json_encode(['count' => $cart_count]);
    
} catch (PDOException $e) {
    // Log error details for debugging
    error_log('Database error in get_cart_count.php: ' . $e->getMessage());
    
    // Return zero count in case of error
    echo json_encode(['count' => 0]);
}
?>