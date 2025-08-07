<?php
require_once 'config/config.php';

$page_title = 'Login';
$page_description = 'Login to your TeWuNeed account to access exclusive features and track your orders.';

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold text-primary">Welcome Back</h2>
                        <p class="text-muted">Sign in to your account</p>
                    </div>
                    
                    <!-- Message containers -->
                    <div id="error-container"></div>
                    <div id="success-container"></div>
                    
                    <form id="loginForm" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <div class="invalid-feedback">
                                Please provide a valid email address.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">
                                Please provide your password.
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                            <label class="form-check-label" for="remember_me">
                                Remember me
                            </label>
                        </div>
                        
                        <button type="submit" id="loginBtn" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </button>
                        
                        <div class="text-center">
                            <a href="#" id="forgotPasswordLink" class="text-decoration-none">
                                Forgot your password?
                            </a>
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="mb-0">Don't have an account?</p>
                        <a href="register_firebase.php" class="btn btn-outline-primary w-100 mt-2">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Demo Accounts Info -->
            <div class="card mt-4">
                <div class="card-body">
                    <h6 class="card-title">Demo Accounts</h6>
                    <small class="text-muted">
                        <strong>Test User:</strong> user@tewuneed.com / user123<br>
                        <strong>John Doe:</strong> john.doe@email.com / user123<br>
                        <strong>Jane Smith:</strong> jane.smith@email.com / user123
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Forgot Password Modal -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="reset-error-container"></div>
                <div id="reset-success-container"></div>
                <form id="resetPasswordForm">
                    <div class="mb-3">
                        <label for="resetEmail" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="resetEmail" required>
                        <div class="form-text">We'll send you a password reset link.</div>
                    </div>
                    <button type="submit" id="resetBtn" class="btn btn-primary w-100">
                        Send Reset Link
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script type="module">
// Import Firebase modules directly
import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
import {
    getAuth,
    signInWithEmailAndPassword,
    createUserWithEmailAndPassword,
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

// Helper functions
function showError(message) {
    const container = document.getElementById('error-container');
    if (container) {
        container.innerHTML = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
    }
}

function showSuccess(message) {
    const container = document.getElementById('success-container');
    if (container) {
        container.innerHTML = `
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
    }
}

function clearMessages() {
    const errorContainer = document.getElementById('error-container');
    const successContainer = document.getElementById('success-container');
    if (errorContainer) errorContainer.innerHTML = '';
    if (successContainer) successContainer.innerHTML = '';
}

function showLoading(button, text) {
    button.disabled = true;
    button.innerHTML = `<i class="fas fa-spinner fa-spin me-2"></i>${text}`;
}

function hideLoading(button, originalText) {
    button.disabled = false;
    button.innerHTML = originalText;
}

// Check if user is already logged in
onAuthStateChanged(auth, (user) => {
    if (user) {
        console.log('User is logged in:', user.email);
        // Redirect to home page if already logged in
        window.location.href = 'index.php';
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    const togglePassword = document.getElementById('togglePassword');

    // Toggle password visibility
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');

            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    }

    // Handle login form submission
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;

            console.log('Attempting login with:', email);

            // Clear previous messages
            clearMessages();

            // Validate inputs
            if (!email || !password) {
                showError('Please fill in all required fields.');
                return;
            }

            // Show loading state
            const originalText = loginBtn.innerHTML;
            showLoading(loginBtn, 'Signing In...');

            try {
                // Attempt Firebase login
                const userCredential = await signInWithEmailAndPassword(auth, email, password);
                const user = userCredential.user;

                console.log('Login successful:', user.email);
                showSuccess('Login successful! Redirecting to home page...');

                // Redirect to home page
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 1500);

            } catch (error) {
                console.error('Login error:', error);
                let errorMessage = 'Login failed. Please try again.';

                switch (error.code) {
                    case 'auth/user-not-found':
                        errorMessage = 'No account found with this email address.';
                        break;
                    case 'auth/wrong-password':
                        errorMessage = 'Incorrect password.';
                        break;
                    case 'auth/invalid-email':
                        errorMessage = 'Invalid email address.';
                        break;
                    case 'auth/too-many-requests':
                        errorMessage = 'Too many failed attempts. Please try again later.';
                        break;
                    case 'auth/network-request-failed':
                        errorMessage = 'Network error. Please check your connection.';
                        break;
                    default:
                        errorMessage = error.message;
                }

                showError(errorMessage);
                hideLoading(loginBtn, originalText);
            }
        });
    }

    // Auto-fill demo account for testing
    const urlParams = new URLSearchParams(window.location.search);
    const demo = urlParams.get('demo');

    if (demo === 'user') {
        document.getElementById('email').value = 'user@tewuneed.com';
        document.getElementById('password').value = 'user123';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
