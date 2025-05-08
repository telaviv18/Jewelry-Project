/**
 * Main JS file for the Jewelry Online Management System
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Quantity selector for product detail page
    const quantityMinusBtn = document.querySelector('.quantity-minus');
    const quantityPlusBtn = document.querySelector('.quantity-plus');
    const quantityInput = document.querySelector('.quantity-input');

    if (quantityMinusBtn && quantityPlusBtn && quantityInput) {
        quantityMinusBtn.addEventListener('click', function() {
            let value = parseInt(quantityInput.value);
            if (value > 1) {
                quantityInput.value = value - 1;
            }
        });

        quantityPlusBtn.addEventListener('click', function() {
            let value = parseInt(quantityInput.value);
            let max = parseInt(quantityInput.getAttribute('max') || 99);
            if (value < max) {
                quantityInput.value = value + 1;
            }
        });

        quantityInput.addEventListener('change', function() {
            let value = parseInt(quantityInput.value);
            let min = parseInt(quantityInput.getAttribute('min') || 1);
            let max = parseInt(quantityInput.getAttribute('max') || 99);
            
            if (value < min) {
                quantityInput.value = min;
            } else if (value > max) {
                quantityInput.value = max;
            }
        });
    }

    // Initialize image zoom effect on product detail page
    const productMainImage = document.querySelector('.product-main-image');
    if (productMainImage) {
        productMainImage.addEventListener('mousemove', function(e) {
            const x = e.clientX - e.target.offsetLeft;
            const y = e.clientY - e.target.offsetTop;
            
            const width = this.offsetWidth;
            const height = this.offsetHeight;
            
            const xperc = ((x / width) * 100);
            const yperc = ((y / height) * 100);
            
            this.style.transformOrigin = `${xperc}% ${yperc}%`;
        });
        
        productMainImage.addEventListener('mouseenter', function() {
            this.style.transform = "scale(1.5)";
        });
        
        productMainImage.addEventListener('mouseleave', function() {
            this.style.transform = "scale(1)";
            this.style.transformOrigin = "center center";
        });
    }

    // Product thumbnail gallery
    const productThumbnails = document.querySelectorAll('.product-thumbnail');
    if (productThumbnails.length > 0 && productMainImage) {
        productThumbnails.forEach(thumbnail => {
            thumbnail.addEventListener('click', function() {
                const imgSrc = this.getAttribute('data-img-src');
                productMainImage.setAttribute('src', imgSrc);
                
                // Remove active class from all thumbnails and add to clicked one
                productThumbnails.forEach(item => item.classList.remove('active'));
                this.classList.add('active');
            });
        });
    }

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });

    // Back to top button
    const backToTopButton = document.querySelector('.back-to-top');
    
    if (backToTopButton) {
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.add('show');
            } else {
                backToTopButton.classList.remove('show');
            }
        });
        
        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
});
