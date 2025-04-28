<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to add items to cart'
    ]);
    exit();
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Check if it's a POST request and action is add_to_cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    // Get product ID and quantity from POST data
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    // Validate inputs
    if ($product_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid product ID'
        ]);
        exit();
    }
    
    if ($quantity <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Quantity must be at least 1'
        ]);
        exit();
    }
    
    try {
        // Check if product exists and has enough stock
        $product_stmt = $conn->prepare("
            SELECT product_id, stock_quantity 
            FROM products 
            WHERE product_id = ?
        ");
        $product_stmt->execute([$product_id]);
        $product = $product_stmt->fetch();
        
        if (!$product) {
            echo json_encode([
                'success' => false,
                'message' => 'Product not found'
            ]);
            exit();
        }
        
        if ($product['stock_quantity'] < $quantity) {
            echo json_encode([
                'success' => false,
                'message' => 'Not enough stock available'
            ]);
            exit();
        }
        
        // Check if the product is already in the user's cart
        $check_stmt = $conn->prepare("
            SELECT cart_id, quantity 
            FROM cart 
            WHERE user_id = ? AND product_id = ?
        ");
        $check_stmt->execute([$user_id, $product_id]);
        $existing_cart_item = $check_stmt->fetch();
        
        if ($existing_cart_item) {
            // Update existing cart item quantity
            $new_quantity = $existing_cart_item['quantity'] + $quantity;
            
            // Check if the new quantity exceeds available stock
            if ($new_quantity > $product['stock_quantity']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Cannot add more items than available in stock'
                ]);
                exit();
            }
            
            $update_stmt = $conn->prepare("
                UPDATE cart 
                SET quantity = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE cart_id = ?
            ");
            $update_stmt->execute([$new_quantity, $existing_cart_item['cart_id']]);
        } else {
            // Add new item to cart
            $insert_stmt = $conn->prepare("
                INSERT INTO cart (user_id, product_id, quantity) 
                VALUES (?, ?, ?)
            ");
            $insert_stmt->execute([$user_id, $product_id, $quantity]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Product added to cart successfully'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}
?>