// Wishlist functionality
class WishlistManager {
    constructor() {
        this.init();
    }
    
    init() {
        // Add event listeners for wishlist buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.wishlist-btn')) {
                e.preventDefault();
                this.toggleWishlist(e.target.closest('.wishlist-btn'));
            }
        });
        
        // Update wishlist count on page load
        this.updateWishlistCount();
    }
    
    async toggleWishlist(button) {
        const productId = button.dataset.productId;
        const isInWishlist = button.classList.contains('in-wishlist');
        
        if (!productId) {
            this.showMessage('Product ID not found', 'error');
            return;
        }
        
        // Show loading state
        const originalContent = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;
        
        try {
            const response = await fetch('/tewuneed2/api/wishlist.php', {
                method: isInWishlist ? 'DELETE' : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ product_id: parseInt(productId) })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update button state
                if (isInWishlist) {
                    button.classList.remove('in-wishlist', 'btn-danger');
                    button.classList.add('btn-outline-danger');
                    button.innerHTML = '<i class="fas fa-heart"></i>';
                    button.title = 'Add to Wishlist';
                } else {
                    button.classList.add('in-wishlist', 'btn-danger');
                    button.classList.remove('btn-outline-danger');
                    button.innerHTML = '<i class="fas fa-heart"></i>';
                    button.title = 'Remove from Wishlist';
                }
                
                // Update wishlist count
                this.updateWishlistCountDisplay(data.wishlist_count);
                
                // Show success message
                this.showMessage(data.message, 'success');
                
                // If on wishlist page, remove the item
                if (window.location.pathname.includes('wishlist.php') && isInWishlist) {
                    const productCard = button.closest('.wishlist-card');
                    if (productCard) {
                        productCard.style.transition = 'all 0.3s ease';
                        productCard.style.opacity = '0';
                        productCard.style.transform = 'scale(0.8)';
                        setTimeout(() => {
                            productCard.remove();
                            // Reload page if no items left
                            if (document.querySelectorAll('.wishlist-card').length === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
                }
                
            } else {
                this.showMessage(data.message, 'error');
                button.innerHTML = originalContent;
            }
            
        } catch (error) {
            console.error('Wishlist error:', error);
            this.showMessage('An error occurred. Please try again.', 'error');
            button.innerHTML = originalContent;
        } finally {
            button.disabled = false;
        }
    }
    
    async updateWishlistCount() {
        try {
            const response = await fetch('/tewuneed2/api/wishlist.php');
            const data = await response.json();
            
            if (data.success) {
                this.updateWishlistCountDisplay(data.count);
            }
        } catch (error) {
            console.error('Error updating wishlist count:', error);
        }
    }
    
    updateWishlistCountDisplay(count) {
        const countElements = document.querySelectorAll('.wishlist-count');
        countElements.forEach(element => {
            element.textContent = count;
            element.style.display = count > 0 ? 'inline' : 'none';
        });
    }
    
    showMessage(message, type = 'info') {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        
        toast.innerHTML = `
            <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'check-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 3000);
    }
    
    // Check if product is in wishlist
    async checkWishlistStatus(productId) {
        try {
            const response = await fetch('/tewuneed2/api/wishlist.php');
            const data = await response.json();
            
            if (data.success) {
                return data.items.some(item => item.product_id == productId);
            }
        } catch (error) {
            console.error('Error checking wishlist status:', error);
        }
        return false;
    }
    
    // Initialize wishlist buttons on product pages
    async initializeWishlistButtons() {
        const wishlistButtons = document.querySelectorAll('.wishlist-btn');
        
        for (const button of wishlistButtons) {
            const productId = button.dataset.productId;
            if (productId) {
                const isInWishlist = await this.checkWishlistStatus(productId);
                if (isInWishlist) {
                    button.classList.add('in-wishlist', 'btn-danger');
                    button.classList.remove('btn-outline-danger');
                    button.title = 'Remove from Wishlist';
                }
            }
        }
    }
}

// Initialize wishlist manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.wishlistManager = new WishlistManager();
    
    // Initialize wishlist button states
    if (document.querySelectorAll('.wishlist-btn').length > 0) {
        window.wishlistManager.initializeWishlistButtons();
    }
});

// Helper function to add wishlist button to product cards
function addWishlistButton(productId, container) {
    const button = document.createElement('button');
    button.className = 'btn btn-outline-danger wishlist-btn';
    button.dataset.productId = productId;
    button.title = 'Add to Wishlist';
    button.innerHTML = '<i class="fas fa-heart"></i>';
    
    container.appendChild(button);
    return button;
}
