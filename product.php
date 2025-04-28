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

// Fetch user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$first_name = $_SESSION['first_name'] ?? 'User';
$last_name = $_SESSION['last_name'] ?? '';

// Initialize variables
$product = null;
$related_products = [];
$reviews = [];
$error = null;
$avg_rating = 0;
$review_count = 0;

// Check if product ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = $_GET['id'];
    
    try {
        // Fetch product details
        $stmt = $conn->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE p.product_id = ?
        ");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            $error = "Product not found";
        } else {
            // Fetch related products from the same category
            $related_stmt = $conn->prepare("
                SELECT * FROM products 
                WHERE category_id = ? AND product_id != ? 
                LIMIT 4
            ");
            $related_stmt->execute([$product['category_id'], $product_id]);
            $related_products = $related_stmt->fetchAll();
            
            // Fetch product reviews
            $reviews_stmt = $conn->prepare("
                SELECT r.*, u.username, u.first_name, u.last_name 
                FROM reviews r
                LEFT JOIN users u ON r.user_id = u.user_id
                WHERE r.product_id = ?
                ORDER BY r.created_at DESC
            ");
            $reviews_stmt->execute([$product_id]);
            $reviews = $reviews_stmt->fetchAll();
            
            // Calculate average rating
            $rating_stmt = $conn->prepare("
                SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
                FROM reviews 
                WHERE product_id = ?
            ");
            $rating_stmt->execute([$product_id]);
            $rating_data = $rating_stmt->fetch();
            $avg_rating = round($rating_data['avg_rating'], 1);
            $review_count = $rating_data['review_count'];
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
} else {
    $error = "Invalid product ID";
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    
    // Validate input
    if ($rating < 1 || $rating > 5) {
        $review_error = "Rating must be between 1 and 5";
    } elseif (empty($comment)) {
        $review_error = "Comment cannot be empty";
    } else {
        try {
            // Check if user already reviewed this product
            $check_stmt = $conn->prepare("
                SELECT review_id FROM reviews 
                WHERE product_id = ? AND user_id = ?
            ");
            $check_stmt->execute([$product_id, $user_id]);
            $existing_review = $check_stmt->fetch();
            
            if ($existing_review) {
                // Update existing review
                $update_stmt = $conn->prepare("
                    UPDATE reviews 
                    SET rating = ?, comment = ? 
                    WHERE review_id = ?
                ");
                $update_stmt->execute([$rating, $comment, $existing_review['review_id']]);
                $review_success = "Your review has been updated!";
            } else {
                // Insert new review
                $insert_stmt = $conn->prepare("
                    INSERT INTO reviews (product_id, user_id, rating, comment) 
                    VALUES (?, ?, ?, ?)
                ");
                $insert_stmt->execute([$product_id, $user_id, $rating, $comment]);
                $review_success = "Your review has been submitted!";
            }
            
            // Refresh page to show the new review
            header("Location: product.php?id=$product_id");
            exit();
        } catch (PDOException $e) {
            $review_error = "Error submitting review: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <title><?php echo $product ? htmlspecialchars($product['name']) : 'Product Not Found'; ?> - Paws & Clows</title>
    <style>
        .product-image {
            max-height: 400px;
            object-fit: contain;
        }
        .rating {
            color: #ffc107;
            font-size: 1.2rem;
        }
        .review-card {
            margin-bottom: 1rem;
        }
        .stock-badge {
            font-size: 0.9rem;
            padding: 0.3rem 0.6rem;
        }
        .quantity-input {
            width: 70px;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg bg-light shadow-sm">
        <div class="container-fluid">
            <!-- Logo -->
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="reso/logo.png" alt="Logo" class="logo">
            </a>

            <!-- Navbar Toggler for Mobile -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Search Bar -->
            <form class="d-flex ms-auto search-bar">
                <input class="form-control me-2" type="search" placeholder="Search..." aria-label="Search">
                <button class="btn btn-success" type="submit">Search</button>
            </form>

            <!-- Navbar Links -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="AboutUs.php">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ContactUs.php">Contact Us</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($first_name); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                            <li><a class="dropdown-item" href="orders.php">My Orders</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="bi bi-cart"></i> Cart
                            <span class="badge bg-success rounded-pill cart-count">0</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <div class="text-center">
                <a href="index.php" class="btn btn-success">Return to Home</a>
            </div>
        <?php elseif ($product): ?>
            <!-- Product Details Section -->
            <div class="row">
                <!-- Product Image -->
                <div class="col-md-5">
                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="img-fluid product-image">
                </div>
                
                <!-- Product Information -->
                <div class="col-md-7">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="Categories.php">Categories</a></li>
                            <li class="breadcrumb-item"><a href="category.php?id=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
                        </ol>
                    </nav>
                    
                    <h2><?php echo htmlspecialchars($product['name']); ?></h2>
                    
                    <!-- Rating Display -->
                    <div class="mb-2">
                        <div class="rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $avg_rating): ?>
                                    <i class="bi bi-star-fill"></i>
                                <?php elseif ($i <= $avg_rating + 0.5): ?>
                                    <i class="bi bi-star-half"></i>
                                <?php else: ?>
                                    <i class="bi bi-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <span class="text-muted ms-2">(<?php echo $review_count; ?> reviews)</span>
                        </div>
                    </div>
                    
                    <!-- Price Display -->
                    <div class="mb-3">
                        <?php if ($product['is_sale'] && $product['sale_price']): ?>
                            <h4>
                                <span class="text-decoration-line-through text-muted">Rs.<?php echo number_format($product['price'], 2); ?></span>
                                <span class="text-danger">Rs.<?php echo number_format($product['sale_price'], 2); ?></span>
                                <?php 
                                $discount_percentage = round(($product['price'] - $product['sale_price']) / $product['price'] * 100);
                                ?>
                                <span class="badge bg-danger"><?php echo $discount_percentage; ?>% OFF</span>
                            </h4>
                        <?php else: ?>
                            <h4>Rs.<?php echo number_format($product['price'], 2); ?></h4>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Stock Availability -->
                    <div class="mb-3">
                        <?php if ($product['stock_quantity'] > 0): ?>
                            <span class="badge bg-success stock-badge">In Stock (<?php echo $product['stock_quantity']; ?> available)</span>
                        <?php else: ?>
                            <span class="badge bg-danger stock-badge">Out of Stock</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Product Description -->
                    <div class="mb-4">
                        <h5>Description</h5>
                        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>
                    
                    <!-- Add to Cart Form -->
                    <?php if ($product['stock_quantity'] > 0): ?>
                        <form id="addToCartForm" class="mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <label for="quantity" class="me-2">Quantity:</label>
                                <input type="number" id="quantity" name="quantity" class="form-control quantity-input me-3" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-cart-plus"></i> Add to Cart
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <!-- Quick Links -->
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-heart"></i> Add to Wishlist
                        </button>
                        <button class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-share"></i> Share
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Product Reviews Section -->
            <div class="mt-5">
                <h3>Customer Reviews</h3>
                <hr>
                
                <!-- Review Form -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        Write a Review
                    </div>
                    <div class="card-body">
                        <?php if (isset($review_error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo htmlspecialchars($review_error); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($review_success)): ?>
                            <div class="alert alert-success" role="alert">
                                <?php echo htmlspecialchars($review_success); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post">
                            <div class="mb-3">
                                <label for="rating" class="form-label">Rating</label>
                                <select class="form-select" id="rating" name="rating" required>
                                    <option value="">Select Rating</option>
                                    <option value="5">5 Stars - Excellent</option>
                                    <option value="4">4 Stars - Very Good</option>
                                    <option value="3">3 Stars - Good</option>
                                    <option value="2">2 Stars - Fair</option>
                                    <option value="1">1 Star - Poor</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="comment" class="form-label">Your Review</label>
                                <textarea class="form-control" id="comment" name="comment" rows="3" required></textarea>
                            </div>
                            <button type="submit" name="submit_review" class="btn btn-success">Submit Review</button>
                        </form>
                    </div>
                </div>
                
                <!-- Review List -->
                <?php if (count($reviews) > 0): ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="card review-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <h5 class="card-title">
                                        <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>
                                    </h5>
                                    <small class="text-muted">
                                        <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                    </small>
                                </div>
                                <div class="rating mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $review['rating']): ?>
                                            <i class="bi bi-star-fill"></i>
                                        <?php else: ?>
                                            <i class="bi bi-star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        No reviews yet. Be the first to review this product!
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Related Products Section -->
            <?php if (count($related_products) > 0): ?>
                <div class="mt-5">
                    <h3>Related Products</h3>
                    <div class="row">
                        <?php foreach ($related_products as $related): ?>
                            <div class="col-md-3 mb-4">
                                <div class="card product-card">
                                    <img src="<?php echo htmlspecialchars($related['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($related['name']); ?>">
                                    <div class="card-body text-center">
                                        <p class="card-title"><?php echo htmlspecialchars($related['name']); ?></p>
                                        <?php if ($related['is_sale'] && $related['sale_price']): ?>
                                            <p class="card-text">
                                                <span class="text-decoration-line-through text-muted">Rs.<?php echo number_format($related['price'], 2); ?></span>
                                                <span class="text-danger">Rs.<?php echo number_format($related['sale_price'], 2); ?></span>
                                            </p>
                                        <?php else: ?>
                                            <p class="card-text">Rs.<?php echo number_format($related['price'], 2); ?></p>
                                        <?php endif; ?>
                                        <a href="product.php?id=<?php echo $related['product_id']; ?>" class="btn btn-outline-success btn-sm">View Details</a>
                                        <button class="btn btn-success btn-sm add-to-cart" data-product-id="<?php echo $related['product_id']; ?>">
                                            Add to Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Footer Section -->
    <footer class="bg-light text-center text-lg-start mt-5">
        <div class="container p-4">
            <div class="row">
                <!-- Logo Section -->
                <div class="col-lg-12 text-left mb-4">
                    <img src="reso/logo.png" alt="Logo" style="height: 50px;">
                </div>

                <!-- Address Section -->
                <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                    <h5 class="text-uppercase">Address</h5>
                    <p>
                        123 Main Street, City<br>
                        State Province, Country
                    </p>
                </div>

                <!-- Call Us Section -->
                <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                    <h5 class="text-uppercase">Call us</h5>
                    <ul class="list-unstyled">
                        <li>+94767692128</li>
                        <li>+94741230346</li>
                    </ul>
                </div>

                <!-- Main Menu Section -->
                <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                    <h5 class="text-uppercase">Main menu</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-dark">Home</a></li>
                        <li><a href="Categories.php" class="text-dark">Categories</a></li>
                        <li><a href="AboutUs.php" class="text-dark">About us</a></li>
                        <li><a href="ContactUs.php" class="text-dark">Contact us</a></li>
                    </ul>
                </div>

                <!-- Find Us On Section -->
                <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                    <h5 class="text-uppercase">Find us on</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-dark">Facebook</a></li>
                        <li><a href="#" class="text-dark">X / Twitter</a></li>
                        <li><a href="#" class="text-dark">Instagram</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Copyright and Links Section -->
        <div class="text-center p-3" style="background-color: rgba(0, 0, 0, 0.2);">
            © 2025 Paws & Clows. All rights reserved.
            <a class="text-dark" href="#">Privacy Policy</a>
            <a class="text-dark" href="#">Terms of Service</a>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
    // Update cart count on page load
    updateCartCount();
    
    // Add to cart form submission
    const addToCartForm = document.getElementById('addToCartForm');
    if (addToCartForm) {
        addToCartForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const productId = this.querySelector('input[name="product_id"]').value;
            const quantity = this.querySelector('input[name="quantity"]').value;
            addToCart(productId, quantity);
        });
    }
    
    // Add to cart buttons for related products
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            addToCart(productId, 1);
        });
    });
    
    // Function to add product to cart
    function addToCart(productId, quantity) {
        // Create form data
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('quantity', quantity);
        formData.append('action', 'add_to_cart');
        
        fetch('add_to_carts.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateCartCount();
                alert('Product added to cart!');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('There was an error adding the product to your cart. Please try again.');
        });
    }
    
    // Function to update cart count
    function updateCartCount() {
        fetch('get_cart_counts.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            const cartCountElement = document.querySelector('.cart-count');
            if (cartCountElement) {
                cartCountElement.textContent = data.count;
            }
        })
        .catch(error => {
            console.error('Error updating cart count:', error);
        });
    }
});
    </script>
</body>
</html>