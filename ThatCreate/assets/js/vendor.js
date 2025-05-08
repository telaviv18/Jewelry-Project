/**
 * Vendor Portal JavaScript functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts if they exist on the page
    initCharts();
    
    // Set up product image preview
    initProductImagePreview();
    
    // Initialize datepickers
    initDatepickers();
    
    // Product form validation
    initFormValidation();
});

/**
 * Initialize dashboard charts
 */
function initCharts() {
    // Sales Chart
    const salesChartElement = document.getElementById('salesChart');
    if (salesChartElement) {
        const salesChart = new Chart(salesChartElement, {
            type: 'line',
            data: {
                labels: salesData.labels,
                datasets: [{
                    label: 'Sales',
                    data: salesData.values,
                    backgroundColor: 'rgba(0, 123, 255, 0.2)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 2,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Category Distribution Chart
    const categoryChartElement = document.getElementById('categoryChart');
    if (categoryChartElement) {
        const categoryChart = new Chart(categoryChartElement, {
            type: 'doughnut',
            data: {
                labels: categoryData.labels,
                datasets: [{
                    data: categoryData.values,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)',
                        'rgba(199, 199, 199, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    }
}

/**
 * Initialize product image preview
 */
function initProductImagePreview() {
    const productImageInput = document.getElementById('image');
    const productImagePreview = document.getElementById('imagePreview');
    
    if (productImageInput && productImagePreview) {
        productImageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    productImagePreview.src = e.target.result;
                    productImagePreview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });
    }
}

/**
 * Initialize datepickers for date inputs
 */
function initDatepickers() {
    const dateInputs = document.querySelectorAll('.datepicker');
    if (dateInputs.length) {
        dateInputs.forEach(input => {
            // If using a datepicker library, initialize it here
            // For example: new Datepicker(input, {options});
        });
    }
}

/**
 * Initialize form validation
 */
function initFormValidation() {
    const productForm = document.getElementById('productForm');
    if (productForm) {
        productForm.addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const price = document.getElementById('price').value.trim();
            const stock = document.getElementById('stock').value.trim();
            
            let isValid = true;
            
            // Clear previous errors
            document.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });
            
            // Validate name
            if (name === '') {
                document.getElementById('name').classList.add('is-invalid');
                isValid = false;
            }
            
            // Validate price
            if (price === '' || isNaN(price) || parseFloat(price) <= 0) {
                document.getElementById('price').classList.add('is-invalid');
                isValid = false;
            }
            
            // Validate stock
            if (stock === '' || isNaN(stock) || parseInt(stock) < 0) {
                document.getElementById('stock').classList.add('is-invalid');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                showAlert('danger', 'Please correct the errors in the form.');
            }
        });
    }
}

/**
 * Show alert message
 */
function showAlert(type, message) {
    const alertContainer = document.getElementById('alertContainer');
    if (alertContainer) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        alertContainer.innerHTML = '';
        alertContainer.appendChild(alert);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            alert.classList.remove('show');
            setTimeout(() => {
                alertContainer.removeChild(alert);
            }, 150);
        }, 5000);
    }
}

/**
 * Handle AJAX for product status toggle
 */
function toggleProductStatus(productId, currentStatus) {
    // AJAX request to toggle product status
    fetch('ajax/toggle_product_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${productId}&status=${currentStatus ? 0 : 1}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI accordingly
            const statusBadge = document.querySelector(`#product-${productId} .status-badge`);
            const statusToggle = document.querySelector(`#product-${productId} .status-toggle`);
            
            if (statusBadge && statusToggle) {
                if (data.new_status) {
                    statusBadge.textContent = 'Active';
                    statusBadge.classList.remove('bg-danger');
                    statusBadge.classList.add('bg-success');
                    statusToggle.checked = true;
                } else {
                    statusBadge.textContent = 'Inactive';
                    statusBadge.classList.remove('bg-success');
                    statusBadge.classList.add('bg-danger');
                    statusToggle.checked = false;
                }
            }
            
            showAlert('success', 'Product status updated successfully');
        } else {
            showAlert('danger', 'Failed to update product status');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred. Please try again.');
    });
}