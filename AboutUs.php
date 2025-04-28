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

// Fetch categories for team member specialization display
try {
    $categories_stmt = $conn->prepare("SELECT * FROM categories ORDER BY name");
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <title>About Us - Paws & Clows</title>
    <style>
        .about-section {
            padding: 60px 0;
        }
        .mission-vision {
            background-color: #f8f9fa;
            padding: 40px 0;
        }
        .team-section {
            padding: 60px 0;
        }
        .team-member {
            margin-bottom: 30px;
            text-align: center;
        }
        .team-member img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
        }
        .timeline {
            position: relative;
            padding: 40px 0;
        }
        .timeline::before {
            content: '';
            position: absolute;
            width: 4px;
            background-color: #198754;
            top: 0;
            bottom: 0;
            left: 50%;
            margin-left: -2px;
        }
        .timeline-item {
            margin-bottom: 50px;
            position: relative;
        }
        .timeline-content {
            padding: 20px;
            background-color: white;
            border-radius: 6px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 45%;
        }
        .timeline-item:nth-child(odd) .timeline-content {
            float: left;
        }
        .timeline-item:nth-child(even) .timeline-content {
            float: right;
        }
        .timeline-year {
            position: absolute;
            width: 40px;
            height: 40px;
            background-color: #198754;
            border-radius: 50%;
            top: 20px;
            left: 50%;
            margin-left: -20px;
            color: white;
            text-align: center;
            line-height: 40px;
            font-weight: bold;
        }
        .value-item {
            text-align: center;
            margin-bottom: 30px;
        }
        .value-icon {
            font-size: 2.5rem;
            color: #198754;
            margin-bottom: 15px;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        .stats-section {
            background-color: #198754;
            color: white;
            padding: 40px 0;
        }
        .stat-item {
            text-align: center;
            padding: 20px;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
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
                        <a class="nav-link active" aria-current="page" href="AboutUs.php">About Us</a>
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

    <!-- Page Title -->
    <div class="bg-light py-4">
        <div class="container">
            <h1 class="text-center">About Us</h1>
        </div>
    </div>

    <!-- About Section -->
    <div class="about-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <h2>Our Story</h2>
                    <p>Paws & Clows was founded in 2015 with a simple mission: to provide the highest quality products and services for pets and their owners. What started as a small family-owned shop has grown into a trusted destination for pet lovers across the country.</p>
                    <p>Our journey began when our founder, Sarah Johnson, a passionate animal lover and veterinarian, recognized the need for a comprehensive pet care center that not only offered premium products but also educated pet owners about proper animal care.</p>
                    <p>Over the years, we've expanded our product lines, brought in expert staff members, and built a community of loyal customers who share our love for animals. We pride ourselves on our deep knowledge of pet nutrition, behavior, and wellness.</p>
                    <p>Today, Paws & Clows continues to grow, but our core values remain the same. We believe every pet deserves the best care, and every pet owner deserves reliable information and quality products at fair prices.</p>
                </div>
                <div class="col-lg-6">
                    <img src="https://irwin.armymwr.com/application/files/3815/2968/7219/Paws__Claws-webColor.jpg" alt="Our Store" class="img-fluid rounded shadow">
                </div>
            </div>
        </div>
    </div>

    <!-- Mission and Vision Section -->
    <div class="mission-vision">
        <div class="container">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-bullseye text-success mb-3" style="font-size: 3rem;"></i>
                            <h3 class="card-title">Our Mission</h3>
                            <p class="card-text">To enhance the lives of pets and their owners by providing exceptional products, services, and knowledge that promote animal health, happiness, and the human-animal bond.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-eye text-success mb-3" style="font-size: 3rem;"></i>
                            <h3 class="card-title">Our Vision</h3>
                            <p class="card-text">To be the most trusted and loved pet care partner, setting the standard for quality, innovation, and compassion in the pet industry while fostering a world where all animals are treated with kindness and respect.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Company Values -->
    <div class="container my-5">
        <h2 class="text-center mb-5">Our Values</h2>
        <div class="row">
            <div class="col-md-3 value-item">
                <i class="bi bi-heart-fill value-icon"></i>
                <h4>Compassion</h4>
                <p>We treat every animal with love and respect, understanding that they are family members to our customers.</p>
            </div>
            <div class="col-md-3 value-item">
                <i class="bi bi-award value-icon"></i>
                <h4>Quality</h4>
                <p>We carefully select only the highest quality products that meet our strict standards for safety and effectiveness.</p>
            </div>
            <div class="col-md-3 value-item">
                <i class="bi bi-lightbulb value-icon"></i>
                <h4>Education</h4>
                <p>We believe informed pet owners make better decisions, so we prioritize sharing knowledge and best practices.</p>
            </div>
            <div class="col-md-3 value-item">
                <i class="bi bi-globe value-icon"></i>
                <h4>Sustainability</h4>
                <p>We're committed to environmentally responsible practices and products that are good for pets and the planet.</p>
            </div>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-3 stat-item">
                    <div class="stat-number">10+</div>
                    <div>Years of Experience</div>
                </div>
                <div class="col-md-3 stat-item">
                    <div class="stat-number">5,000+</div>
                    <div>Happy Customers</div>
                </div>
                <div class="col-md-3 stat-item">
                    <div class="stat-number">500+</div>
                    <div>Products</div>
                </div>
                <div class="col-md-3 stat-item">
                    <div class="stat-number">20+</div>
                    <div>Expert Staff</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Our History Timeline -->
    <div class="container my-5">
        <h2 class="text-center mb-5">Our Journey</h2>
        <div class="timeline">
            <div class="timeline-item clearfix">
                <div class="timeline-year">2015</div>
                <div class="timeline-content">
                    <h4>Beginning of Paws & Clows</h4>
                    <p>Paws & Clows opened its doors as a small pet supplies shop with a limited selection of premium pet foods and accessories.</p>
                </div>
            </div>
            <div class="timeline-item clearfix">
                <div class="timeline-year">2017</div>
                <div class="timeline-content">
                    <h4>Expansion of Product Lines</h4>
                    <p>We expanded our store to include more specialized pet care products, exotic pet supplies, and began offering pet nutrition consultations.</p>
                </div>
            </div>
            <div class="timeline-item clearfix">
                <div class="timeline-year">2019</div>
                <div class="timeline-content">
                    <h4>Online Store Launch</h4>
                    <p>Paws & Clows went digital with the launch of our e-commerce platform, allowing us to serve pet owners nationwide.</p>
                </div>
            </div>
            <div class="timeline-item clearfix">
                <div class="timeline-year">2022</div>
                <div class="timeline-content">
                    <h4>Community Initiative</h4>
                    <p>We launched our "Paws for a Cause" program, partnering with local animal shelters to help homeless pets find loving homes.</p>
                </div>
            </div>
            <div class="timeline-item clearfix">
                <div class="timeline-year">2024</div>
                <div class="timeline-content">
                    <h4>Today</h4>
                    <p>Today, Paws & Clows continues to grow while maintaining our core values of quality, education, and compassion for all animals.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Our Team Section -->
    <div class="team-section bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Meet Our Team</h2>
            <div class="row">
                <div class="col-md-3 team-member">
                    <img src="https://yt3.googleusercontent.com/ytc/AIdro_nKk5Wm_gGErdJeTzjEH6CuxoX45JMG1OPV-0i2r2U_=s900-c-k-c0x00ffffff-no-rj" alt="Sarah Johnson">
                    <h4>Chathumal Lakshitha</h4>
                    <p class="text-success">Founder & CEO</p>
                    <p>Veterinarian with over 15 years of experience in animal care.</p>
                </div>
                <div class="col-md-3 team-member">
                    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT9QsNNKPOs5EMC4BgohMCnPOeM0cb4BcogBA&s" alt="Michael Patel">
                    <h4>Pramuditha Yatawara</h4>
                    <p class="text-success">Pet Nutrition Specialist</p>
                    <p>Expert in dietary requirements for dogs and cats.</p>
                </div>
                <div class="col-md-3 team-member">
                    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR-xtM_wdoUbPR3LC4yi8fF9XXkBGVot-Dohg&s" alt="Emily Rodriguez">
                    <h4>Sasindu Dilshan</h4>
                    <p class="text-success">Exotic Pet Specialist</p>
                    <p>Specializes in care for reptiles, birds, and small mammals.</p>
                </div>
                <div class="col-md-3 team-member">
                    <img src="https://media.licdn.com/dms/image/v2/D4D03AQFTh_9BFeIsug/profile-displayphoto-shrink_200_200/profile-displayphoto-shrink_200_200/0/1677464725397?e=2147483647&v=beta&t=J2H8Bq99Gi9as7_DeAdj0nJeAbjDqhYnne2o0Rv7HkY" alt="David Kim">
                    <h4>Naditha Sandesh</h4>
                    <p class="text-success">Customer Relations</p>
                    <p>Dedicated to ensuring exceptional customer experiences.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Testimonials Section -->
    <div class="container my-5">
        <h2 class="text-center mb-5">What Our Customers Say</h2>
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="mb-3">
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                        </div>
                        <p class="card-text">"The staff at Paws & Clows are incredibly knowledgeable. They helped me find the perfect diet for my senior dog who has special dietary needs. Their recommendations have made a huge difference in his health!"</p>
                        <p class="text-end mb-0"><strong>- Achintha Bhanuka</strong></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="mb-3">
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                        </div>
                        <p class="card-text">"I've been shopping at Paws & Clows for years, and I'm always impressed by their selection and quality. The online ordering and delivery service has been a lifesaver during busy times!"</p>
                        <p class="text-end mb-0"><strong>- Ashen Charuka</strong></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="mb-3">
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-half text-warning"></i>
                        </div>
                        <p class="card-text">"When I adopted my first cat, I knew nothing about cat care. The team at Paws & Clows guided me through everything I needed to know and helped me set up the perfect environment for my new pet. Forever grateful!"</p>
                        <p class="text-end mb-0"><strong>- Irosh Malinga</strong></p>
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
    // Add to cart functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Get cart count from server via AJAX
        updateCartCount();
        
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
    });
    </script>
</body>
</html>