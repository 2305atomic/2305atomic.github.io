// Firebase Configuration and Authentication
import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
import { 
    getAuth, 
    createUserWithEmailAndPassword, 
    signInWithEmailAndPassword, 
    signOut, 
    onAuthStateChanged,
    updateProfile,
    sendPasswordResetEmail
} from "https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js";
import { 
    getFirestore, 
    doc, 
    setDoc, 
    getDoc 
} from "https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore.js";

// Your web app's Firebase configuration
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
const db = getFirestore(app);

// Authentication functions
export const firebaseAuth = {
    // Register new user
    async register(email, password, firstName, lastName, phone) {
        try {
            const userCredential = await createUserWithEmailAndPassword(auth, email, password);
            const user = userCredential.user;
            
            // Update user profile
            await updateProfile(user, {
                displayName: `${firstName} ${lastName}`
            });
            
            // Save additional user data to Firestore
            await setDoc(doc(db, 'users', user.uid), {
                email: email,
                firstName: firstName,
                lastName: lastName,
                phone: phone,
                createdAt: new Date().toISOString(),
                status: 'active'
            });
            
            return { success: true, user: user };
        } catch (error) {
            return { success: false, error: error.message };
        }
    },
    
    // Login user
    async login(email, password) {
        try {
            const userCredential = await signInWithEmailAndPassword(auth, email, password);
            const user = userCredential.user;
            
            // Get additional user data from Firestore
            const userDoc = await getDoc(doc(db, 'users', user.uid));
            const userData = userDoc.exists() ? userDoc.data() : {};
            
            return { 
                success: true, 
                user: user,
                userData: userData
            };
        } catch (error) {
            return { success: false, error: error.message };
        }
    },
    
    // Logout user
    async logout() {
        try {
            await signOut(auth);
            return { success: true };
        } catch (error) {
            return { success: false, error: error.message };
        }
    },
    
    // Reset password
    async resetPassword(email) {
        try {
            await sendPasswordResetEmail(auth, email);
            return { success: true };
        } catch (error) {
            return { success: false, error: error.message };
        }
    },
    
    // Get current user
    getCurrentUser() {
        return auth.currentUser;
    },
    
    // Listen to auth state changes
    onAuthStateChanged(callback) {
        return onAuthStateChanged(auth, callback);
    }
};

// Helper functions
export const authHelpers = {
    // Show loading state
    showLoading(buttonElement, loadingText = 'Loading...') {
        buttonElement.disabled = true;
        buttonElement.innerHTML = `<i class="fas fa-spinner fa-spin me-2"></i>${loadingText}`;
    },
    
    // Hide loading state
    hideLoading(buttonElement, originalText) {
        buttonElement.disabled = false;
        buttonElement.innerHTML = originalText;
    },
    
    // Show error message
    showError(message, containerId = 'error-container') {
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
    },
    
    // Show success message
    showSuccess(message, containerId = 'success-container') {
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
    },
    
    // Clear messages
    clearMessages() {
        const errorContainer = document.getElementById('error-container');
        const successContainer = document.getElementById('success-container');
        if (errorContainer) errorContainer.innerHTML = '';
        if (successContainer) successContainer.innerHTML = '';
    },
    
    // Validate email
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },
    
    // Validate password strength
    validatePassword(password) {
        if (password.length < 6) {
            return { valid: false, message: 'Password must be at least 6 characters long' };
        }
        return { valid: true };
    }
};

// Initialize auth state listener
document.addEventListener('DOMContentLoaded', function() {
    firebaseAuth.onAuthStateChanged((user) => {
        if (user) {
            // User is signed in
            console.log('User signed in:', user.email);
            
            // Update UI for logged in user
            const loginBtn = document.querySelector('.login-btn');
            const registerBtn = document.querySelector('.register-btn');
            const userMenu = document.querySelector('.user-menu');
            
            if (loginBtn) loginBtn.style.display = 'none';
            if (registerBtn) registerBtn.style.display = 'none';
            if (userMenu) {
                userMenu.style.display = 'block';
                userMenu.innerHTML = `
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i>${user.displayName || user.email}
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="orders.php"><i class="fas fa-shopping-bag me-2"></i>My Orders</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" id="logout-btn"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                `;
                
                // Add logout functionality
                document.getElementById('logout-btn')?.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const result = await firebaseAuth.logout();
                    if (result.success) {
                        window.location.reload();
                    }
                });
            }
        } else {
            // User is signed out
            console.log('User signed out');
            
            // Update UI for logged out user
            const loginBtn = document.querySelector('.login-btn');
            const registerBtn = document.querySelector('.register-btn');
            const userMenu = document.querySelector('.user-menu');
            
            if (loginBtn) loginBtn.style.display = 'inline-block';
            if (registerBtn) registerBtn.style.display = 'inline-block';
            if (userMenu) userMenu.style.display = 'none';
        }
    });
});
