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
        'count' => 0
    ]);
    exit();
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

try {
    // Get count of items in user's cart
    $count_stmt = $conn->prepare("
        SELECT SUM(quantity) as total_items 
        FROM cart 
        WHERE user_id = ?
    ");
    $count_stmt->execute([$user_id]);
    $result = $count_stmt->fetch();
    
    $count = $result['total_items'] ?? 0;
    
    echo json_encode([
        'count' => (int)$count
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'count' => 0,
        'error' => $e->getMessage()
    ]);
}
?>