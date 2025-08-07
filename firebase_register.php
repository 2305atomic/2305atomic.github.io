<?php
require_once 'config/config.php';

$page_title = 'Create Firebase Account';
$page_description = 'Create your Firebase account for TeWuNeed';

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold text-primary">Create Firebase Account</h2>
                        <p class="text-muted">Join TeWuNeed with Firebase</p>
                    </div>
                    
                    <!-- Message containers -->
                    <div id="error-container"></div>
                    <div id="success-container"></div>
                    
                    <form id="registerForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="user@tewuneed.com" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   value="user123" required>
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirmPassword" 
                                   value="user123" required>
                        </div>
                        
                        <button type="submit" id="registerBtn" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </button>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="mb-0">Already have an account?</p>
                        <a href="login_firebase.php" class="btn btn-outline-primary w-100 mt-2">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Quick Create Accounts -->
            <div class="card mt-4">
                <div class="card-body">
                    <h6 class="card-title">Quick Create Test Accounts</h6>
                    <button class="btn btn-outline-success btn-sm me-2" onclick="createTestAccount('user@tewuneed.com')">
                        Create user@tewuneed.com
                    </button>
                    <button class="btn btn-outline-success btn-sm me-2" onclick="createTestAccount('john.doe@email.com')">
                        Create john.doe@email.com
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="createTestAccount('jane.smith@email.com')">
                        Create jane.smith@email.com
                    </button>
                    <p class="mt-2 mb-0"><small class="text-muted">All accounts use password: user123</small></p>
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
    document.getElementById('error-container').innerHTML = `
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}

function showSuccess(message) {
    document.getElementById('success-container').innerHTML = `
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}

function clearMessages() {
    document.getElementById('error-container').innerHTML = '';
    document.getElementById('success-container').innerHTML = '';
}

// Create account function
async function createAccount(email, password) {
    try {
        const userCredential = await createUserWithEmailAndPassword(auth, email, password);
        const user = userCredential.user;
        console.log('Account created:', user.email);
        showSuccess(`Account created successfully for ${user.email}!`);
        return true;
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
                errorMessage = 'Password should be at least 6 characters.';
                break;
            case 'auth/network-request-failed':
                errorMessage = 'Network error. Please check your connection.';
                break;
            default:
                errorMessage = error.message;
        }
        
        showError(errorMessage);
        return false;
    }
}

// Make createTestAccount global
window.createTestAccount = async function(email) {
    clearMessages();
    const success = await createAccount(email, 'user123');
    if (success) {
        setTimeout(() => {
            window.location.href = 'login_firebase.php';
        }, 2000);
    }
};

document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('registerForm');
    const registerBtn = document.getElementById('registerBtn');
    
    // Handle registration form
    registerForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        clearMessages();
        
        // Validate inputs
        if (!email || !password || !confirmPassword) {
            showError('Please fill in all required fields.');
            return;
        }
        
        if (password !== confirmPassword) {
            showError('Passwords do not match.');
            return;
        }
        
        if (password.length < 6) {
            showError('Password must be at least 6 characters long.');
            return;
        }
        
        // Show loading
        const originalText = registerBtn.innerHTML;
        registerBtn.disabled = true;
        registerBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...';
        
        const success = await createAccount(email, password);
        
        // Reset button
        registerBtn.disabled = false;
        registerBtn.innerHTML = originalText;
        
        if (success) {
            setTimeout(() => {
                window.location.href = 'login_firebase.php';
            }, 2000);
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
