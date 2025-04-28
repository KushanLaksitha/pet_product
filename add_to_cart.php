<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to add items to cart']);
    exit();
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

// Get product ID and quantity from POST
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

// Validate inputs
if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit();
}

if ($quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Quantity must be greater than zero']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Check if product exists and has enough stock
    $product_stmt = $conn->prepare("
        SELECT product_id, stock_quantity 
        FROM products 
        WHERE product_id = :product_id
    ");
    $product_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $product_stmt->execute();
    $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit();
    }
    
    if ($product['stock_quantity'] < $quantity) {
        echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
        exit();
    }
    
    // Check if product is already in cart
    $check_stmt = $conn->prepare("
        SELECT cart_id, quantity 
        FROM cart 
        WHERE user_id = :user_id AND product_id = :product_id
    ");
    $check_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $check_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $check_stmt->execute();
    $existing_item = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_item) {
        // Update quantity if product already in cart
        $new_quantity = $existing_item['quantity'] + $quantity;
        
        // Check if new quantity exceeds available stock
        if ($new_quantity > $product['stock_quantity']) {
            echo json_encode([
                'success' => false, 
                'message' => 'Cannot add more items. Cart would exceed available stock.'
            ]);
            exit();
        }
        
        $update_stmt = $conn->prepare("
            UPDATE cart 
            SET quantity = :quantity, updated_at = CURRENT_TIMESTAMP 
            WHERE cart_id = :cart_id
        ");
        $update_stmt->bindParam(':quantity', $new_quantity, PDO::PARAM_INT);
        $update_stmt->bindParam(':cart_id', $existing_item['cart_id'], PDO::PARAM_INT);
        $update_stmt->execute();
    } else {
        // Insert new cart item
        $insert_stmt = $conn->prepare("
            INSERT INTO cart (user_id, product_id, quantity) 
            VALUES (:user_id, :product_id, :quantity)
        ");
        $insert_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $insert_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $insert_stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        $insert_stmt->execute();
    }
    
    // Return success response
    echo json_encode(['success' => true, 'message' => 'Product added to cart successfully']);
    
} catch (PDOException $e) {
    // Log error details for debugging
    error_log('Database error in add_to_cart.php: ' . $e->getMessage());
    
    // Return error response
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>