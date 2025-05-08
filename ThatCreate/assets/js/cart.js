/**
 * Shopping Cart functionality
 */
document.addEventListener('DOMContentLoaded', function() {
    // Add to cart functionality
    const addToCartForm = document.querySelector('#add-to-cart-form');
    
    if (addToCartForm) {
        addToCartForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const productId = this.querySelector('input[name="product_id"]').value;
            const quantity = this.querySelector('input[name="quantity"]').value;
            
            addToCart(productId, quantity);
        });
    }
    
    // Cart quantity update
    const cartQuantityInputs = document.querySelectorAll('.cart-quantity-input');
    
    if (cartQuantityInputs.length > 0) {
        cartQuantityInputs.forEach(input => {
            input.addEventListener('change', function() {
                const cartItemId = this.getAttribute('data-cart-id');
                const newQuantity = this.value;
                
                updateCartItemQuantity(cartItemId, newQuantity);
            });
        });
    }
    
    // Remove from cart
    const removeCartButtons = document.querySelectorAll('.remove-cart-item');
    
    if (removeCartButtons.length > 0) {
        removeCartButtons.forEach(button => {
            button.addEventListener('click', function() {
                const cartItemId = this.getAttribute('data-cart-id');
                
                removeFromCart(cartItemId);
            });
        });
    }
    
    /**
     * Add product to cart via AJAX
     */
    function addToCart(productId, quantity) {
        // Show loading spinner
        const addToCartButton = document.querySelector('#add-to-cart-button');
        if (addToCartButton) {
            const originalText = addToCartButton.innerHTML;
            addToCartButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...';
            addToCartButton.disabled = true;
        }
        
        // Send AJAX request
        fetch('api/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add&product_id=${productId}&quantity=${quantity}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showAlert('success', 'Product added to cart successfully!');
                
                // Update cart count in navbar
                updateCartCount(data.cart_count);
                
                // Reset button
                if (addToCartButton) {
                    addToCartButton.innerHTML = 'Added to Cart!';
                    setTimeout(() => {
                        addToCartButton.innerHTML = originalText;
                        addToCartButton.disabled = false;
                    }, 2000);
                }
            } else {
                showAlert('danger', data.message || 'Failed to add product to cart');
                
                // Reset button
                if (addToCartButton) {
                    addToCartButton.innerHTML = originalText;
                    addToCartButton.disabled = false;
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred. Please try again.');
            
            // Reset button
            if (addToCartButton) {
                addToCartButton.innerHTML = originalText;
                addToCartButton.disabled = false;
            }
        });
    }
    
    /**
     * Update cart item quantity via AJAX
     */
    function updateCartItemQuantity(cartItemId, quantity) {
        // Send AJAX request
        fetch('api/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update&cart_id=${cartItemId}&quantity=${quantity}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update subtotal
                const subtotalElement = document.querySelector(`.cart-item-subtotal[data-cart-id="${cartItemId}"]`);
                if (subtotalElement) {
                    subtotalElement.textContent = data.subtotal;
                }
                
                // Update cart total
                updateCartSummary(data.cart_total);
                
                // Update cart count in navbar
                updateCartCount(data.cart_count);
            } else {
                showAlert('danger', data.message || 'Failed to update cart');
                // Reset to previous quantity
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred. Please try again.');
            location.reload();
        });
    }
    
    /**
     * Remove item from cart via AJAX
     */
    function removeFromCart(cartItemId) {
        if (!confirm('Are you sure you want to remove this item from your cart?')) {
            return;
        }
        
        // Send AJAX request
        fetch('api/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=remove&cart_id=${cartItemId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove item from DOM
                const cartItem = document.querySelector(`.cart-item[data-cart-id="${cartItemId}"]`);
                if (cartItem) {
                    cartItem.remove();
                }
                
                // Update cart total
                updateCartSummary(data.cart_total);
                
                // Update cart count in navbar
                updateCartCount(data.cart_count);
                
                // Show empty cart message if needed
                if (data.cart_count === 0) {
                    const cartContainer = document.querySelector('.cart-items-container');
                    if (cartContainer) {
                        cartContainer.innerHTML = '<div class="alert alert-info">Your cart is empty</div>';
                    }
                    
                    const cartSummary = document.querySelector('.cart-summary');
                    if (cartSummary) {
                        cartSummary.style.display = 'none';
                    }
                }
                
                showAlert('success', 'Item removed from cart');
            } else {
                showAlert('danger', data.message || 'Failed to remove item from cart');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred. Please try again.');
        });
    }
    
    /**
     * Update cart count in navbar
     */
    function updateCartCount(count) {
        const cartCountElement = document.querySelector('.nav-link .badge');
        if (cartCountElement) {
            cartCountElement.textContent = count;
            
            if (count > 0) {
                cartCountElement.style.display = 'inline-block';
            } else {
                cartCountElement.style.display = 'none';
            }
        }
    }
    
    /**
     * Update cart summary totals
     */
    function updateCartSummary(total) {
        const cartTotalElement = document.querySelector('.cart-total');
        if (cartTotalElement) {
            cartTotalElement.textContent = total;
        }
    }
    
    /**
     * Show alert message
     */
    function showAlert(type, message) {
        const alertsContainer = document.querySelector('.alerts-container');
        if (!alertsContainer) return;
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        alertsContainer.appendChild(alert);
        
        // Auto-dismiss after 3 seconds
        setTimeout(() => {
            alert.classList.remove('show');
            setTimeout(() => {
                alert.remove();
            }, 150);
        }, 3000);
    }
});
