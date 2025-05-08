/**
 * Admin panel functionality
 */
document.addEventListener('DOMContentLoaded', function() {
    // Toggle sidebar on mobile
    const sidebarToggle = document.querySelector('#sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.querySelector('body').classList.toggle('sidebar-toggled');
            document.querySelector('.admin-sidebar').classList.toggle('toggled');
        });
    }
    
    // Initialize datatables
    const dataTables = document.querySelectorAll('.datatable');
    
    if (dataTables.length > 0) {
        dataTables.forEach(table => {
            $(table).DataTable({
                responsive: true
            });
        });
    }
    
    // Delete confirmation
    const deleteButtons = document.querySelectorAll('.btn-delete');
    
    if (deleteButtons.length > 0) {
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    }
    
    // Image preview for file inputs
    const imageInputs = document.querySelectorAll('.image-input');
    
    if (imageInputs.length > 0) {
        imageInputs.forEach(input => {
            input.addEventListener('change', function() {
                const preview = document.querySelector(this.getAttribute('data-preview'));
                
                if (preview && this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });
    }
    
    // Sales chart (if on dashboard)
    const salesChart = document.querySelector('#salesChart');
    
    if (salesChart) {
        fetch('api/reports.php?report=sales_chart')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderSalesChart(data.labels, data.data);
                }
            })
            .catch(error => {
                console.error('Error fetching sales data:', error);
            });
    }
    
    /**
     * Render sales chart
     */
    function renderSalesChart(labels, data) {
        const ctx = salesChart.getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Sales',
                    data: data,
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    pointRadius: 3,
                    pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointBorderColor: 'rgba(78, 115, 223, 1)',
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Sales: $' + context.raw;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Category distribution pie chart (if on dashboard)
    const categoryChart = document.querySelector('#categoryChart');
    
    if (categoryChart) {
        fetch('api/reports.php?report=category_distribution')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderCategoryChart(data.labels, data.data);
                }
            })
            .catch(error => {
                console.error('Error fetching category data:', error);
            });
    }
    
    /**
     * Render category distribution chart
     */
    function renderCategoryChart(labels, data) {
        const ctx = categoryChart.getContext('2d');
        
        // Define colors for pie chart
        const backgroundColors = [
            'rgba(78, 115, 223, 0.8)',
            'rgba(28, 200, 138, 0.8)',
            'rgba(246, 194, 62, 0.8)',
            'rgba(231, 74, 59, 0.8)',
            'rgba(54, 185, 204, 0.8)',
            'rgba(133, 135, 150, 0.8)'
        ];
        
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: backgroundColors,
                    hoverBackgroundColor: backgroundColors.map(color => color.replace('0.8', '1')),
                    hoverBorderColor: 'rgba(234, 236, 244, 1)',
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return label + ': ' + percentage + '%';
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Initialize WYSIWYG editor for product descriptions
    const descriptionEditor = document.querySelector('#product_description');
    
    if (descriptionEditor) {
        ClassicEditor
            .create(descriptionEditor)
            .catch(error => {
                console.error('Error initializing WYSIWYG editor:', error);
            });
    }
    
    // Bulk action confirmation
    const bulkActionForm = document.querySelector('#bulkActionForm');
    
    if (bulkActionForm) {
        bulkActionForm.addEventListener('submit', function(e) {
            const action = this.querySelector('select[name="bulk_action"]').value;
            const checkedItems = document.querySelectorAll('input[name="selected_items[]"]:checked');
            
            if (checkedItems.length === 0) {
                e.preventDefault();
                alert('Please select at least one item to perform this action.');
                return;
            }
            
            if (action === 'delete' && !confirm('Are you sure you want to delete the selected items? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    }
    
    // Select all checkbox
    const selectAllCheckbox = document.querySelector('#selectAll');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="selected_items[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }
});
