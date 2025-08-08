// TeWuNeed - Main JavaScript File
// Modern ES6+ JavaScript with jQuery

$(document).ready(function() {
    // Initialize components
    initializeComponents();
    
    // Update cart count on page load
    updateCartCount();
    
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize smooth scrolling
    initializeSmoothScrolling();
});

// Initialize all components
function initializeComponents() {
    // Add to cart functionality
    initializeAddToCart();
    
    // Search functionality
    initializeSearch();
    
    // Image lazy loading
    initializeLazyLoading();
    
    // Form validation
    initializeFormValidation();
}

// Add to Cart functionality
function initializeAddToCart() {
    $(document).on('click', '.add-to-cart-btn', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const productId = button.data('product-id');
        let quantity = button.data('quantity') || 1;

        // Check if there's a quantity input on the same page
        const quantityInput = $('#quantity');
        if (quantityInput.length) {
            quantity = parseInt(quantityInput.val()) || 1;
        }
        
        // Disable button and show loading
        button.prop('disabled', true);
        const originalText = button.html();
        button.html('<span class="loading"></span> Adding...');
        
        // Send AJAX request
        $.ajax({
            url: '/tewuneed2/api/add-to-cart.php',
            method: 'POST',
            data: {
                product_id: productId,
                quantity: quantity,
                csrf_token: $('meta[name="csrf-token"]').attr('content')
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showNotification('Product added to cart successfully!', 'success');
                    
                    // Update cart count
                    updateCartCount();
                    
                    // Update button text temporarily
                    button.html('<i class="fas fa-check"></i> Added!');
                    setTimeout(() => {
                        button.html(originalText);
                        button.prop('disabled', false);
                    }, 2000);
                } else {
                    showNotification(response.message || 'Failed to add product to cart', 'error');
                    button.html(originalText);
                    button.prop('disabled', false);
                }
            },
            error: function() {
                showNotification('An error occurred. Please try again.', 'error');
                button.html(originalText);
                button.prop('disabled', false);
            }
        });
    });
}

// Update cart count
function updateCartCount() {
    $.get('/tewuneed2/api/cart-count.php', function(data) {
        if (data.success) {
            $('#cart-count').text(data.count);
            
            // Hide badge if count is 0
            if (data.count === 0) {
                $('#cart-count').hide();
            } else {
                $('#cart-count').show();
            }
        }
    }).fail(function() {
        $('#cart-count').text('0').hide();
    });
}

// Search functionality
function initializeSearch() {
    let searchTimeout;
    
    // Live search suggestions
    $('#search-input').on('input', function() {
        const query = $(this).val().trim();
        
        clearTimeout(searchTimeout);
        
        if (query.length >= 2) {
            searchTimeout = setTimeout(() => {
                fetchSearchSuggestions(query);
            }, 300);
        } else {
            hideSearchSuggestions();
        }
    });
    
    // Hide suggestions when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.search-container').length) {
            hideSearchSuggestions();
        }
    });
}

// Fetch search suggestions
function fetchSearchSuggestions(query) {
    $.get('/tewuneed2/api/search-suggestions.php', { q: query }, function(data) {
        if (data.success && data.suggestions.length > 0) {
            showSearchSuggestions(data.suggestions);
        } else {
            hideSearchSuggestions();
        }
    });
}

// Show search suggestions
function showSearchSuggestions(suggestions) {
    let html = '<div class="search-suggestions">';
    suggestions.forEach(suggestion => {
        html += `<div class="search-suggestion-item" data-value="${suggestion.name}">
            <img src="${suggestion.image}" alt="${suggestion.name}" class="suggestion-image">
            <div class="suggestion-content">
                <div class="suggestion-name">${suggestion.name}</div>
                <div class="suggestion-price">${suggestion.price}</div>
            </div>
        </div>`;
    });
    html += '</div>';
    
    $('.search-container').append(html);
}

// Hide search suggestions
function hideSearchSuggestions() {
    $('.search-suggestions').remove();
}

// Initialize tooltips
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Initialize smooth scrolling
function initializeSmoothScrolling() {
    $('a[href*="#"]:not([href="#"])').click(function() {
        if (location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') && location.hostname == this.hostname) {
            let target = $(this.hash);
            target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 100
                }, 1000);
                return false;
            }
        }
    });
}

// Initialize lazy loading for images
function initializeLazyLoading() {
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
}

// Initialize form validation
function initializeFormValidation() {
    // Bootstrap form validation
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
}

// Show notification
function showNotification(message, type = 'info', duration = 5000) {
    const alertClass = type === 'success' ? 'alert-success' : 
                     type === 'error' ? 'alert-danger' : 
                     type === 'warning' ? 'alert-warning' : 'alert-info';
    
    const notification = $(`
        <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 
                              type === 'error' ? 'exclamation-circle' : 
                              type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    
    $('body').append(notification);
    
    // Auto remove after duration
    setTimeout(() => {
        notification.alert('close');
    }, duration);
}

// Format price
function formatPrice(price) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(price);
}

// Debounce function
function debounce(func, wait, immediate) {
    let timeout;
    return function executedFunction() {
        const context = this;
        const args = arguments;
        const later = function() {
            timeout = null;
            if (!immediate) func.apply(context, args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(context, args);
    };
}

// Loading overlay
function showLoading() {
    if (!$('#loading-overlay').length) {
        $('body').append(`
            <div id="loading-overlay" class="position-fixed w-100 h-100 d-flex align-items-center justify-content-center" 
                 style="top: 0; left: 0; background: rgba(0,0,0,0.5); z-index: 9999;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `);
    }
}

function hideLoading() {
    $('#loading-overlay').remove();
}

// Utility functions
const Utils = {
    // Validate email
    isValidEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },
    
    // Validate phone number
    isValidPhone: function(phone) {
        const re = /^[\+]?[1-9][\d]{0,15}$/;
        return re.test(phone);
    },
    
    // Generate random string
    randomString: function(length = 10) {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    },
    
    // Format date
    formatDate: function(date) {
        return new Date(date).toLocaleDateString('id-ID', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
};

// Global showToast function for compatibility
function showToast(message, type = 'info') {
    showNotification(message, type);
}

// Export for global use
window.TeWuNeed = {
    updateCartCount,
    showNotification,
    showToast,
    formatPrice,
    showLoading,
    hideLoading,
    Utils
};

// Make functions globally available
window.showToast = showToast;
window.showNotification = showNotification;
window.updateCartCount = updateCartCount;
