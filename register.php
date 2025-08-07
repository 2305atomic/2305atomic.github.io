<?php
require_once 'config/config.php';

$page_title = 'Register';
$page_description = 'Create your TeWuNeed account to start shopping and enjoy exclusive benefits.';

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
                        <h2 class="h4 text-primary">Create Account</h2>
                        <p class="text-muted">Join TeWuNeed today</p>
                    </div>
                    
                    <!-- Error/Success Messages -->
                    <div id="error-container"></div>
                    <div id="success-container"></div>
                    
                    <form id="registerForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="firstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="firstName" name="firstName" required 
                                       placeholder="Enter your first name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="lastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lastName" name="lastName" required 
                                       placeholder="Enter your last name">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   placeholder="Enter your email address">
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number (Optional)</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   placeholder="Enter your phone number">
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required 
                                       placeholder="Create a strong password" minlength="6">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Password must be at least 6 characters long.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required 
                                   placeholder="Confirm your password">
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="agreeTerms" name="agreeTerms" required>
                            <label class="form-check-label" for="agreeTerms">
                                I agree to the <a href="#" class="text-decoration-none">Terms and Conditions</a>
                            </label>
                        </div>
                        
                        <button type="submit" id="registerBtn" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </button>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="mb-0">Already have an account?</p>
                        <a href="login.php" class="btn btn-outline-primary w-100 mt-2">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="module">
// Import Firebase modules
import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
import { 
    getAuth, 
    createUserWithEmailAndPassword,
    updateProfile,
    onAuthStateChanged
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

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePassword(password) {
    if (password.length < 6) {
        return { valid: false, message: 'Password must be at least 6 characters long.' };
    }
    return { valid: true };
}

// DOM elements
const registerForm = document.getElementById('registerForm');
const registerBtn = document.getElementById('registerBtn');
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

// Handle registration form submission
if (registerForm) {
    registerForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const firstName = document.getElementById('firstName').value.trim();
        const lastName = document.getElementById('lastName').value.trim();
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.trim();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const agreeTerms = document.getElementById('agreeTerms').checked;
        
        // Clear previous messages
        clearMessages();
        
        // Validate inputs
        if (!firstName || !lastName || !email || !password || !confirmPassword) {
            showError('Please fill in all required fields.');
            return;
        }
        
        if (!isValidEmail(email)) {
            showError('Please enter a valid email address.');
            return;
        }
        
        const passwordValidation = validatePassword(password);
        if (!passwordValidation.valid) {
            showError(passwordValidation.message);
            return;
        }
        
        if (password !== confirmPassword) {
            showError('Passwords do not match.');
            return;
        }
        
        if (!agreeTerms) {
            showError('You must agree to the terms and conditions.');
            return;
        }
        
        // Show loading state
        const originalText = registerBtn.innerHTML;
        showLoading(registerBtn, 'Creating Account...');
        
        try {
            // Create Firebase user
            const userCredential = await createUserWithEmailAndPassword(auth, email, password);
            const user = userCredential.user;
            
            // Update user profile
            await updateProfile(user, {
                displayName: `${firstName} ${lastName}`
            });
            
            console.log('Registration successful:', user.email);
            showSuccess('Account created successfully! Redirecting...');
            
            // Create PHP session via AJAX
            const response = await fetch('api/firebase-session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    uid: user.uid,
                    email: user.email,
                    displayName: `${firstName} ${lastName}`
                })
            });
            
            // Clear form
            registerForm.reset();
            
            // Redirect after short delay
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 2000);
            
        } catch (error) {
            console.error('Registration error:', error);
            let errorMessage = 'Registration failed. Please try again.';
            
            switch (error.code) {
                case 'auth/email-already-in-use':
                    errorMessage = 'An account with this email already exists.';
                    break;
                case 'auth/invalid-email':
                    errorMessage = 'Invalid email address.';
                    break;
                case 'auth/weak-password':
                    errorMessage = 'Password is too weak. Please choose a stronger password.';
                    break;
                case 'auth/network-request-failed':
                    errorMessage = 'Network error. Please check your connection.';
                    break;
                default:
                    errorMessage = error.message;
            }
            
            showError(errorMessage);
            hideLoading(registerBtn, originalText);
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

// Real-time password validation
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const validation = validatePassword(password);
    
    if (password && !validation.valid) {
        this.setCustomValidity(validation.message);
        this.classList.add('is-invalid');
    } else {
        this.setCustomValidity('');
        this.classList.remove('is-invalid');
    }
    
    // Also check confirm password if it has a value
    const confirmPassword = document.getElementById('confirmPassword');
    if (confirmPassword.value) {
        confirmPassword.dispatchEvent(new Event('input'));
    }
});

// Real-time confirm password validation
document.getElementById('confirmPassword').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && password !== confirmPassword) {
        this.setCustomValidity('Passwords do not match.');
        this.classList.add('is-invalid');
    } else {
        this.setCustomValidity('');
        this.classList.remove('is-invalid');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
