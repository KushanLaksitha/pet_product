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

// Initialize variables for form data and messages
$email = '';
$address = '';
$phone = '';
$success_message = '';
$error_message = '';

// Fetch complete user data from database
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        $email = $user['email'];
        $address = $user['address'];
        $phone = $user['phone'];
    }
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Handle form submission for profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_first_name = trim($_POST['first_name']);
    $new_last_name = trim($_POST['last_name']);
    $new_email = trim($_POST['email']);
    $new_address = trim($_POST['address']);
    $new_phone = trim($_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate form data
    $validation_errors = [];
    
    if (empty($new_first_name)) {
        $validation_errors[] = "First name is required";
    }
    
    if (empty($new_last_name)) {
        $validation_errors[] = "Last name is required";
    }
    
    if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $validation_errors[] = "Valid email is required";
    }
    
    // Check if email already exists for another user
    if ($new_email !== $email) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$new_email, $user_id]);
        if ($stmt->rowCount() > 0) {
            $validation_errors[] = "Email is already in use by another account";
        }
    }
    
    // Password validation if user wants to change it
    if (!empty($current_password)) {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();
        
        if (!password_verify($current_password, $user_data['password'])) {
            $validation_errors[] = "Current password is incorrect";
        }
        
        if (empty($new_password)) {
            $validation_errors[] = "New password cannot be empty if you want to change password";
        } elseif (strlen($new_password) < 8) {
            $validation_errors[] = "New password must be at least 8 characters long";
        } elseif ($new_password !== $confirm_password) {
            $validation_errors[] = "New password and confirm password do not match";
        }
    }
    
    // If no validation errors, update user profile
    if (empty($validation_errors)) {
        try {
            $conn->beginTransaction();
            
            // Update basic info
            $stmt = $conn->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, address = ?, phone = ? 
                WHERE user_id = ?
            ");
            $stmt->execute([$new_first_name, $new_last_name, $new_email, $new_address, $new_phone, $user_id]);
            
            // Update password if provided
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->execute([$hashed_password, $user_id]);
            }
            
            $conn->commit();
            
            // Update session variables
            $_SESSION['first_name'] = $new_first_name;
            $_SESSION['last_name'] = $new_last_name;
            
            // Update local variables for display
            $first_name = $new_first_name;
            $last_name = $new_last_name;
            $email = $new_email;
            $address = $new_address;
            $phone = $new_phone;
            
            $success_message = "Profile updated successfully!";
        } catch (PDOException $e) {
            $conn->rollBack();
            $error_message = "Error updating profile: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $validation_errors);
    }
}

// Fetch user's order history
try {
    $order_stmt = $conn->prepare("
        SELECT o.order_id, o.total_amount, o.status, o.created_at, 
               COUNT(oi.order_item_id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.user_id = ?
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $order_stmt->execute([$user_id]);
    $recent_orders = $order_stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching orders: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <title>My Profile - Paws & Clows</title>
    <style>
        .profile-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .profile-header {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .order-card {
            transition: transform 0.3s;
        }
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
                        <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($first_name); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item active" href="profile.php">My Profile</a></li>
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
    <div class="container py-5">
        <div class="row">
            <!-- Profile Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="list-group">
                    <a href="#profile-info" class="list-group-item list-group-item-action active" data-bs-toggle="list">Profile Information</a>
                    <a href="#edit-profile" class="list-group-item list-group-item-action" data-bs-toggle="list">Edit Profile</a>
                    <a href="#recent-orders" class="list-group-item list-group-item-action" data-bs-toggle="list">Recent Orders</a>
                    <a href="#change-password" class="list-group-item list-group-item-action" data-bs-toggle="list">Change Password</a>
                </div>
            </div>
            
            <!-- Profile Content -->
            <div class="col-lg-9">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="tab-content">
                    <!-- Profile Information -->
                    <div class="tab-pane fade show active" id="profile-info">
                        <div class="profile-section">
                            <div class="profile-header">
                                <h3>Profile Information</h3>
                                <p class="text-muted">Your personal details</p>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Username:</div>
                                <div class="col-md-8"><?php echo htmlspecialchars($username); ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Full Name:</div>
                                <div class="col-md-8"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Email Address:</div>
                                <div class="col-md-8"><?php echo htmlspecialchars($email); ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Phone Number:</div>
                                <div class="col-md-8"><?php echo htmlspecialchars($phone) ?: 'Not provided'; ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Address:</div>
                                <div class="col-md-8"><?php echo htmlspecialchars($address) ?: 'Not provided'; ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Member Since:</div>
                                <div class="col-md-8"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></div>
                            </div>
                            <div class="mt-4">
                                <button class="btn btn-outline-success" data-bs-toggle="list" href="#edit-profile">Edit Profile</button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Edit Profile -->
                    <div class="tab-pane fade" id="edit-profile">
                        <div class="profile-section">
                            <div class="profile-header">
                                <h3>Edit Profile</h3>
                                <p class="text-muted">Update your personal information</p>
                            </div>
                            <form method="post" action="profile.php">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($address); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <p class="text-muted">Leave password fields empty if you don't want to change your password.</p>
                                </div>
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                    <div class="form-text">Required only if changing password</div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    </div>
                                </div>
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" name="update_profile" class="btn btn-success">Save Changes</button>
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="list" href="#profile-info">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Recent Orders -->
                    <div class="tab-pane fade" id="recent-orders">
                        <div class="profile-section">
                            <div class="profile-header">
                                <h3>Recent Orders</h3>
                                <p class="text-muted">Your last 5 orders</p>
                            </div>
                            
                            <?php if (!empty($recent_orders)): ?>
                                <div class="row">
                                    <?php foreach ($recent_orders as $order): ?>
                                        <div class="col-md-6 mb-4">
                                            <div class="card order-card">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <span>Order #<?php echo $order['order_id']; ?></span>
                                                    <span class="badge <?php echo getStatusBadgeClass($order['status']); ?>"><?php echo ucfirst($order['status']); ?></span>
                                                </div>
                                                <div class="card-body">
                                                    <p class="card-text">Date: <?php echo date('M d, Y', strtotime($order['created_at'])); ?></p>
                                                    <p class="card-text">Items: <?php echo $order['item_count']; ?></p>
                                                    <p class="card-text">Total: Rs.<?php echo number_format($order['total_amount'], 2); ?></p>
                                                    <a href="orders.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-outline-success">View Details</a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="orders.php" class="btn btn-outline-success">View All Orders</a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> You haven't placed any orders yet.
                                    <a href="Categories.php" class="alert-link">Start shopping now!</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Change Password -->
                    <div class="tab-pane fade" id="change-password">
                        <div class="profile-section">
                            <div class="profile-header">
                                <h3>Change Password</h3>
                                <p class="text-muted">Update your password for security</p>
                            </div>
                            <form method="post" action="profile.php">
                                <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>">
                                <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>">
                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                                <input type="hidden" name="address" value="<?php echo htmlspecialchars($address); ?>">
                                <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                                
                                <div class="mb-3">
                                    <label for="cp_current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="cp_current_password" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="cp_new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="cp_new_password" name="new_password" required>
                                    <div class="form-text">Password must be at least 8 characters long</div>
                                </div>
                                <div class="mb-3">
                                    <label for="cp_confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="cp_confirm_password" name="confirm_password" required>
                                </div>
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" name="update_profile" class="btn btn-success">Update Password</button>
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="list" href="#profile-info">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Section -->
    <footer class="bg-light text-center text-lg-start">
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
    // Function to get badge class based on order status
    function getStatusBadgeClass(status) {
        switch(status) {
            case 'pending':
                return 'bg-warning text-dark';
            case 'processing':
                return 'bg-info text-dark';
            case 'shipped':
                return 'bg-primary';
            case 'delivered':
                return 'bg-success';
            case 'cancelled':
                return 'bg-danger';
            default:
                return 'bg-secondary';
        }
    }
    
    // Update cart count on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateCartCount();
        
        // Change active tab based on URL hash
        const hash = window.location.hash;
        if (hash) {
            const tab = document.querySelector(`[href="${hash}"]`);
            if (tab) {
                const bsTab = new bootstrap.Tab(tab);
                bsTab.show();
            }
        }
    });
    
    // Function to update cart count
    function updateCartCount() {
        fetch('get_cart_count.php')
        .then(response => response.json())
        .then(data => {
            const cartCountElement = document.querySelector('.cart-count');
            if (cartCountElement) {
                cartCountElement.textContent = data.count;
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    </script>
</body>
</html>
<?php
// Helper function to get badge class for order status
function getStatusBadgeClass($status) {
    switch($status) {
        case 'pending':
            return 'bg-warning text-dark';
        case 'processing':
            return 'bg-info text-dark';
        case 'shipped':
            return 'bg-primary';
        case 'delivered':
            return 'bg-success';
        case 'cancelled':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}
?>