<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <meta name="description" content="<?php echo isset($page_description) ? $page_description : SITE_DESCRIPTION; ?>">
    
    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo SITE_URL; ?>/favicon_package_v0.16/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo SITE_URL; ?>/favicon_package_v0.16/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo SITE_URL; ?>/favicon_package_v0.16/favicon-16x16.png">
    <link rel="manifest" href="<?php echo SITE_URL; ?>/favicon_package_v0.16/site.webmanifest">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/style.css">
    
    <!-- Additional CSS -->
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Firebase Authentication Script -->
    <script type="module">
        // Import Firebase modules
        import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
        import {
            getAuth,
            onAuthStateChanged,
            signOut
        } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js";

        // Firebase configuration
        const firebaseConfig = {
            apiKey: "AIzaSyDz5t6mlBzXq7bjK3PGOGyEBo_WzjsHYME",
            authDomain: "tewuneed-marketplace.firebaseapp.com",
            databaseURL: "https://tewuneed-marketplace-default-rtdb.asia-southeast1.firebasedatabase.app",
            projectId: "tewuneed-marketplace",
            storageBucket: "tewuneed-marketplace.firebasestorage.app",
            messagingSenderId: "999093621738",
            appId: "1:999093621738:web:87b68aa3a5a5ebca395893",
            measurementId: "G-8WNLD8T7GY"
        };

        // Initialize Firebase
        const app = initializeApp(firebaseConfig);
        const auth = getAuth(app);

        // Listen for auth state changes
        onAuthStateChanged(auth, (user) => {
            const loginBtn = document.querySelector('.login-btn');
            const registerBtn = document.querySelector('.register-btn');
            const userMenu = document.querySelector('.user-menu');

            if (user) {
                // User is signed in
                console.log('User signed in:', user.email);

                if (loginBtn) loginBtn.style.display = 'none';
                if (registerBtn) registerBtn.style.display = 'none';
                if (userMenu) {
                    userMenu.style.display = 'block';
                    userMenu.innerHTML = `
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i>${user.displayName || user.email.split('@')[0]}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                                <li class="dropdown-header">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user-circle fa-2x me-2 text-primary"></i>
                                        <div>
                                            <div class="fw-bold">${user.displayName || 'User'}</div>
                                            <small class="text-muted">${user.email}</small>
                                        </div>
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2" href="profile.php">
                                    <i class="fas fa-user me-2 text-primary"></i>My Profile
                                </a></li>
                                <li><a class="dropdown-item py-2" href="my-orders.php">
                                    <i class="fas fa-shopping-bag me-2 text-primary"></i>My Orders
                                </a></li>
                                <li><a class="dropdown-item py-2" href="wishlist.php">
                                    <i class="fas fa-heart me-2 text-primary"></i>Wishlist
                                    <span class="badge bg-danger ms-2 wishlist-count" style="display: none;">0</span>
                                </a></li>
                                <li><a class="dropdown-item py-2" href="addresses.php">
                                    <i class="fas fa-map-marker-alt me-2 text-primary"></i>Addresses
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 text-danger" href="#" id="firebase-logout-btn">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                            </ul>
                        </div>
                    `;

                    // Add logout functionality
                    document.getElementById('firebase-logout-btn')?.addEventListener('click', async (e) => {
                        e.preventDefault();
                        try {
                            await signOut(auth);
                            window.location.reload();
                        } catch (error) {
                            console.error('Logout error:', error);
                        }
                    });
                }
            } else {
                // User is signed out
                console.log('User signed out');

                if (loginBtn) loginBtn.style.display = 'inline-block';
                if (registerBtn) registerBtn.style.display = 'inline-block';
                if (userMenu) userMenu.style.display = 'none';
            }
        });
    </script>

    <!-- Header JavaScript -->
    <script>
    // Update cart count on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateCartCount();

        // Add search functionality
        const searchInputs = document.querySelectorAll('input[name="search"]');
        searchInputs.forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const form = this.closest('form');
                    if (form) {
                        form.submit();
                    }
                }
            });
        });
    });

    // Function to update cart count
    function updateCartCount() {
        fetch('<?php echo SITE_URL; ?>/ajax/get_cart_count.php')
            .then(response => response.json())
            .then(data => {
                const cartCountElements = document.querySelectorAll('#cart-count');
                cartCountElements.forEach(element => {
                    element.textContent = data.count || 0;
                    element.style.display = (data.count > 0) ? 'inline' : 'none';
                });
            })
            .catch(error => {
                console.log('Cart count update failed:', error);
            });
    }

    // Update cart count every 30 seconds
    setInterval(updateCartCount, 30000);
    </script>
</head>
<body class="d-flex flex-column min-vh-100">
    <!-- Modern Navigation Header -->
    <header class="bg-primary shadow-lg sticky-top">
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container-lg">
                <!-- Brand Logo -->
                <a class="navbar-brand fw-bold fs-3" href="<?php echo SITE_URL; ?>">
                    <i class="fas fa-gem me-2 text-warning"></i>
                    <span class="text-white"><?php echo SITE_NAME; ?></span>
                </a>

                <!-- Mobile Toggle -->
                <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <!-- Navigation Menu -->
                <div class="collapse navbar-collapse" id="navbarNav">
                    <!-- Main Navigation -->
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link text-white fw-medium" href="<?php echo SITE_URL; ?>">
                                <i class="fas fa-home me-1"></i>Home
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-white fw-medium" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-th-large me-1"></i>Categories
                            </a>
                            <ul class="dropdown-menu shadow-lg border-0">
                                <?php
                                try {
                                    $stmt = getDBConnection()->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name LIMIT 10");
                                    while ($category = $stmt->fetch()) {
                                        echo '<li><a class="dropdown-item py-2" href="' . SITE_URL . '/products.php?category=' . $category['slug'] . '">
                                                <i class="fas fa-tag me-2 text-primary"></i>' . htmlspecialchars($category['name']) . '
                                              </a></li>';
                                    }
                                } catch (Exception $e) {
                                    echo '<li><a class="dropdown-item" href="' . SITE_URL . '/products.php">All Products</a></li>';
                                }
                                ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item fw-bold" href="<?php echo SITE_URL; ?>/products.php">
                                    <i class="fas fa-arrow-right me-2 text-primary"></i>View All Categories
                                </a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white fw-medium" href="<?php echo SITE_URL; ?>/products.php">
                                <i class="fas fa-shopping-cart me-1"></i>Products
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-white fw-medium" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-info-circle me-1"></i>About
                            </a>
                            <ul class="dropdown-menu shadow-lg border-0">
                                <li><a class="dropdown-item py-2" href="<?php echo SITE_URL; ?>/about.php">
                                    <i class="fas fa-building me-2 text-primary"></i>About Us
                                </a></li>
                                <li><a class="dropdown-item py-2" href="<?php echo SITE_URL; ?>/contact.php">
                                    <i class="fas fa-envelope me-2 text-primary"></i>Contact Us
                                </a></li>
                                <li><a class="dropdown-item py-2" href="<?php echo SITE_URL; ?>/faq.php">
                                    <i class="fas fa-question-circle me-2 text-primary"></i>FAQ
                                </a></li>
                            </ul>
                        </li>
                    </ul>

                    <!-- Search Bar -->
                    <form class="d-flex me-3 d-none d-lg-flex" method="GET" action="<?php echo SITE_URL; ?>/products.php">
                        <div class="input-group">
                            <input class="form-control bg-white border-0" type="search" name="search" placeholder="Search products..."
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" style="border-radius: 25px 0 0 25px;">
                            <button class="btn btn-warning border-0" type="submit" style="border-radius: 0 25px 25px 0;">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>

                    <!-- Right Side Navigation -->
                    <ul class="navbar-nav">
                        <!-- Cart -->
                        <li class="nav-item me-3">
                            <a class="nav-link position-relative text-white" href="<?php echo SITE_URL; ?>/cart.php">
                                <i class="fas fa-shopping-cart fa-lg"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark" id="cart-count">
                                    0
                                </span>
                            </a>
                        </li>

                        <!-- Wishlist -->
                        <li class="nav-item me-3 d-none d-md-block">
                            <a class="nav-link text-white" href="<?php echo SITE_URL; ?>/wishlist.php">
                                <i class="fas fa-heart fa-lg"></i>
                            </a>
                        </li>

                        <!-- User Account -->
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle text-white fw-medium" href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-circle me-1"></i>
                                    <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                                    <li class="dropdown-header">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user-circle fa-2x me-2 text-primary"></i>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></small>
                                            </div>
                                        </div>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item py-2" href="<?php echo SITE_URL; ?>/profile.php">
                                        <i class="fas fa-user me-2 text-primary"></i>My Profile
                                    </a></li>
                                    <li><a class="dropdown-item py-2" href="<?php echo SITE_URL; ?>/my-orders.php">
                                        <i class="fas fa-shopping-bag me-2 text-primary"></i>My Orders
                                    </a></li>
                                    <li><a class="dropdown-item py-2" href="<?php echo SITE_URL; ?>/wishlist.php">
                                        <i class="fas fa-heart me-2 text-primary"></i>Wishlist
                                        <span class="badge bg-danger ms-2 wishlist-count" style="display: none;">0</span>
                                    </a></li>
                                    <li><a class="dropdown-item py-2" href="<?php echo SITE_URL; ?>/addresses.php">
                                        <i class="fas fa-map-marker-alt me-2 text-primary"></i>Addresses
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item py-2 text-danger" href="<?php echo SITE_URL; ?>/logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item login-btn me-2">
                                <a class="btn btn-outline-light" href="<?php echo SITE_URL; ?>/login.php">
                                    <i class="fas fa-sign-in-alt me-1"></i>Login
                                </a>
                            </li>
                            <li class="nav-item register-btn">
                                <a class="btn btn-warning" href="<?php echo SITE_URL; ?>/register.php">
                                    <i class="fas fa-user-plus me-1"></i>Register
                                </a>
                            </li>
                            <li class="nav-item user-menu" style="display: none;">
                                <!-- Firebase user menu will be inserted here -->
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Mobile Search Bar -->
        <div class="container-lg d-lg-none">
            <div class="py-3 border-top border-light border-opacity-25">
                <form method="GET" action="<?php echo SITE_URL; ?>/products.php">
                    <div class="input-group">
                        <input type="text" class="form-control bg-white border-0" name="search" placeholder="Search products..."
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" style="border-radius: 25px 0 0 25px;">
                        <button class="btn btn-warning border-0" type="submit" style="border-radius: 0 25px 25px 0;">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content flex-grow-1">
