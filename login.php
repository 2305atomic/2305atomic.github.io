<?php
require_once 'config/config.php';

$page_title = 'Login';
$page_description = 'Login to your TeWuNeed account to access exclusive features and track your orders.';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    redirect(SITE_URL);
}

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="h4 text-primary">Welcome Back</h2>
                        <p class="text-muted">Sign in to your account</p>
                    </div>
                    
                    <!-- Error/Success Messages -->
                    <div id="error-container"></div>
                    <div id="success-container"></div>
                    
                    <form id="loginForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   placeholder="Enter your email address">
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required 
                                       placeholder="Enter your password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
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
                        <a href="register.php" class="btn btn-outline-primary w-100 mt-2">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Demo Accounts -->
            <div class="card mt-4">
                <div class="card-body">
                    <h6 class="card-title text-center">Demo Accounts</h6>
                    <div class="row text-center">
                        <div class="col-6">
                            <small class="text-muted">Test User:</small><br>
                            <code>user@tewuneed.com</code><br>
                            <code>user123</code>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">John Doe:</small><br>
                            <code>john@example.com</code><br>
                            <code>john123</code>
                        </div>
                    </div>
                </div>
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
    onAuthStateChanged,
    sendPasswordResetEmail
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
    document.getElementById('error-container').innerHTML = '';
    document.getElementById('success-container').innerHTML = '';
}

function showLoading(button, text = 'Loading...') {
    button.disabled = true;
    button.innerHTML = `<i class="fas fa-spinner fa-spin me-2"></i>${text}`;
}

function hideLoading(button, originalText) {
    button.disabled = false;
    button.innerHTML = originalText;
}

// DOM elements
const loginForm = document.getElementById('loginForm');
const loginBtn = document.getElementById('loginBtn');
const togglePassword = document.getElementById('togglePassword');
const forgotPasswordLink = document.getElementById('forgotPasswordLink');

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

// Handle forgot password
if (forgotPasswordLink) {
    forgotPasswordLink.addEventListener('click', async function(e) {
        e.preventDefault();
        
        const email = document.getElementById('email').value.trim();
        if (!email) {
            showError('Please enter your email address first.');
            return;
        }
        
        try {
            await sendPasswordResetEmail(auth, email);
            showSuccess('Password reset email sent! Check your inbox.');
        } catch (error) {
            showError('Error sending password reset email: ' + error.message);
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

            // Create PHP session via AJAX
            const response = await fetch('api/firebase-session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    uid: user.uid,
                    email: user.email,
                    displayName: user.displayName
                })
            });

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

// Check if user is already logged in
onAuthStateChanged(auth, (user) => {
    if (user) {
        console.log('User already logged in:', user.email);
        // Redirect to home page if already logged in
        window.location.href = 'index.php';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
