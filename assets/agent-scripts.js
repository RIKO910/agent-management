jQuery(document).ready(function($) {
    let currentPage = 1;
    let isLoading = false;
    let hasMore = true;
    let totalPages = 1;

    // Login form handling
    $('#agentLoginForm').on('submit', function(e) {
        e.preventDefault();

        const submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true).html('Continue...');

        let formData = {
            action: 'agent_login',
            nonce: agent_dashboard_ajax.nonce,
            username: $('input[name="login_username"]').val(),
            password: $('input[name="login_password"]').val()
        };

        // Send AJAX request
        $.post(agent_dashboard_ajax.ajaxurl, formData, function(response) {
            if (response.success) {
                $('#loginMessage').html('<div class="success">' + response.data + '</div>');
                // Redirect after successful login
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            } else {
                $('#loginMessage').html('<div class="error">' + response.data + '</div>');
                submitButton.prop('disabled', false).html('Login');
            }
        }).fail(function() {
            $('#loginMessage').html('<div class="error">Login failed. Please try again.</div>');
            submitButton.prop('disabled', false).html('Login');
        });
    });

    // Signup form handling
    $('#agent-create-submit').on('click', function(e) {
        e.preventDefault();

        const submitButton = $(this);
        submitButton.prop('disabled', true).html('Continue...');

        // Simple password check before request
        let password = $('input[name="sign_password"]').val();
        let confirmPassword = $('input[name="sign_confirm_password"]').val();

        if (password !== confirmPassword) {
            $('#signupMessage').html('<div class="error">Passwords do not match.</div>');
            return;
        }

        if (password.length < 6) {
            $('#signupMessage').html('<div class="error">Password must be at least 6 characters long.</div>');
            return;
        }

        // Collect form data
        let formData = {
            action: 'agent_signup',
            nonce: agent_dashboard_ajax.nonce,
            company_name: $('input[name="sign_company_name"]').val(),
            username: $('input[name="sign_username"]').val(),
            email: $('input[name="sign_email"]').val(),
            phone: $('input[name="sign_phone"]').val(),
            address: $('textarea[name="sign_address"]').val(),
            license_number: $('input[name="sign_license_number"]').val(),
            password: password,
            confirm_password: confirmPassword
        };

        // Send AJAX request
        $.post(agent_dashboard_ajax.ajaxurl, formData, function(response) {
            if (response.success) {
                $('#signupMessage').html('<div class="success">' + response.data + '</div>');
                $('#agentSignupForm')[0].reset();
                // Optionally switch back to login form
                setTimeout(function() {
                    showLogin();
                    submitButton.prop('disabled', false).html('Sign Up');
                }, 2000);
            } else {
                $('#signupMessage').html('<div class="error">' + response.data + '</div>');
                submitButton.prop('disabled', false).html('Sign Up');
            }
        }).fail(function() {
            $('#signupMessage').html('<div class="error">Registration failed. Please try again.</div>');
            submitButton.prop('disabled', false).html('Sign Up');
        });
    });

    // Tab functionality
    $('.tab-button').click(function() {
        $('.tab-button').removeClass('active');
        $('.tab-content').removeClass('active');

        $(this).addClass('active');
        $('#' + $(this).data('tab')).addClass('active');

        // Refresh customer list when switching to that tab
        if ($(this).data('tab') === 'customer-list') {
            resetPagination();
            loadCustomerList();
        }
    });

    // Initialize lightbox
    if (typeof $.prettyPhoto !== 'undefined') {
        $('a[rel^="prettyPhoto"]').prettyPhoto({
            social_tools: false,
            theme: 'pp_woocommerce',
            horizontal_padding: 20,
            opacity: 0.8,
            deeplinking: false
        });
    }

    // Add multiple image fields
    $('#add-image-btn').click(function() {
        $('.multiple-image-upload-section').append(
            '<div class="additional-image-field form-group">' +
            '<label>Additional Image</label>' +
            '<input type="file" name="additional_images[]" accept="image/*">' +
            '<button type="button" class="remove-image-btn">Remove</button>' +
            '</div>'
        );
    });

    // Remove image fields
    $(document).on('click', '.remove-image-btn', function() {
        $(this).closest('.additional-image-field').remove();
    });

    $(document).on('change', 'input[type="file"]', function() {
        var file = this.files[0];
        if (file) {
            var maxSize = 5 * 1024 * 1024; // 5MB in bytes
            if (file.size > maxSize) {
                alert('File size must be less than 5MB');
                $(this).val('');
            }
        }
    });

    $('#customerForm').submit(function(e) {
        e.preventDefault();

        var formData = new FormData(this);

        // Add the action parameter correctly
        formData.append('action', 'submit_customer_form');

        $.ajax({
            url: agent_dashboard_ajax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#formMessage').html('<div class="success">' + response.data + '</div>');
                    $('#customerForm')[0].reset();
                    $('.additional-image-field').remove();

                    // Refresh customer list and reset pagination
                    resetPagination();
                    if (typeof loadCustomerList === 'function') {
                        loadCustomerList();
                    }
                } else {
                    $('#formMessage').html('<div class="error">' + response.data + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                $('#formMessage').html('<div class="error">An error occurred. Please try again.</div>');
            }
        });
    });

    // Add some interactive effects
    $('.form-group input, .form-group select, .form-group textarea').on('focus', function() {
        $(this).parent().addClass('focused');
    }).on('blur', function() {
        $(this).parent().removeClass('focused');
    });

    // Auto-hide messages after 5 seconds
    $(document).on('click', '#formMessage .success, #formMessage .error', function() {
        $(this).fadeOut(300);
    });

    setTimeout(function() {
        $('#formMessage .success, #formMessage .error').fadeOut(300);
    }, 5000);

    // Add animation to table rows
    $(document).on('mouseenter', '.customer-table tbody tr', function() {
        $(this).css('transform', 'scale(1.02)');
    }).on('mouseleave', '.customer-table tbody tr', function() {
        $(this).css('transform', 'scale(1)');
    });

});

function resetPagination() {
    currentPage = 1;
    isLoading = false;
    hasMore = true;
    totalPages = 1;
    jQuery('.customer-table tbody').empty();
    jQuery('.pagination-container').remove();
}

function loadCustomerList() {
    if (isLoading) return;

    isLoading = true;

    // Show loading with fade effect
    const $tbody = jQuery('.customer-table tbody');
    const $tableContainer = jQuery('.customer-table-container');

    // Fade out existing content
    $tbody.fadeOut(200, function() {
        // Add loading spinner
        $tbody.html(
            '<tr id="temp-loading"><td colspan="7" style="text-align: center; padding: 40px;">' +
            '<div class="loading-spinner-large"></div>' +
            '<p style="margin-top: 10px; color: #666;">Loading your customers...</p>' +
            '</td></tr>'
        ).fadeIn(200);

        // Remove existing pagination
        jQuery('.pagination-container').remove();

        // Make AJAX call - FIXED: Using the correct action
        jQuery.ajax({
            url: agent_dashboard_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_customer_list_paginated', // FIXED ACTION NAME
                page: currentPage,
                per_page: 5,
                nonce: agent_dashboard_ajax.nonce
            },
            success: function(response) {
                isLoading = false;

                if (response.success) {
                    $tbody.fadeOut(200, function() {
                        jQuery(this).html(response.data.html).fadeIn(300, function() {
                            initializeLightbox();
                            hasMore = response.data.has_more;

                            // Calculate total pages based on total count
                            const totalCustomers = response.data.total || 0;
                            totalPages = Math.ceil(totalCustomers / 5);

                            // Add pagination controls
                            addPaginationControls(totalCustomers);
                        });
                    });
                } else {
                    showTableError('Failed to load customers.');
                }
            },
            error: function(xhr, status, error) {
                isLoading = false;
                console.error('AJAX Error:', error);
                showTableError('Network error. Please try again.');
            }
        });
    });
}

function addPaginationControls(totalCustomers) {
    const $tableContainer = jQuery('.customer-table-container');

    // Remove existing pagination
    jQuery('.pagination-container').remove();

    if (totalCustomers === 0) {
        // Don't show pagination if no customers
        return;
    }

    const paginationHTML = `
        <div class="pagination-container" style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
            <div class="pagination-info" style="color: #666; font-size: 14px;">
                Showing page ${currentPage} of ${totalPages} (${totalCustomers} total customers)
            </div>
            <div class="pagination-buttons" style="display: flex; gap: 10px;">
                <button class="pagination-btn ${currentPage === 1 ? 'disabled' : ''}" 
                        data-action="prev" 
                        style="padding: 8px 16px; border: 1px solid #ddd; background: ${currentPage === 1 ? '#f5f5f5' : '#fff'}; color: ${currentPage === 1 ? '#999' : '#333'}; border-radius: 4px; cursor: ${currentPage === 1 ? 'not-allowed' : 'pointer'};">
                    ← Previous
                </button>
                <button class="pagination-btn ${currentPage >= totalPages ? 'disabled' : ''}" 
                        data-action="next" 
                        style="padding: 8px 16px; border: 1px solid #ddd; background: ${currentPage >= totalPages ? '#f5f5f5' : '#fff'}; color: ${currentPage >= totalPages ? '#999' : '#333'}; border-radius: 4px; cursor: ${currentPage >= totalPages ? 'not-allowed' : 'pointer'};">
                    Next →
                </button>
            </div>
        </div>
    `;

    $tableContainer.after(paginationHTML);
}

function showTableError(message) {
    jQuery('.customer-table tbody').html(
        '<tr><td colspan="7" style="text-align: center; padding: 30px; color: #d32f2f;">' +
        '<div style="margin-bottom: 10px;">⚠️</div>' +
        message +
        '<br><button onclick="loadCustomerList()" style="margin-top: 10px; padding: 5px 15px; background: #2196f3; color: white; border: none; border-radius: 3px; cursor: pointer;">Retry</button>' +
        '</td></tr>'
    );

    // Remove pagination on error
    jQuery('.pagination-container').remove();
}

function initializeLightbox() {
    if (typeof jQuery.prettyPhoto !== 'undefined') {
        jQuery('a[rel^="prettyPhoto"]').prettyPhoto({
            social_tools: false,
            theme: 'pp_woocommerce',
            horizontal_padding: 20,
            opacity: 0.8,
            deeplinking: false
        });
    }
}

jQuery(document).ready(function($) {
    // Pagination button handlers
    $(document).on('click', '.pagination-btn', function() {
        if ($(this).hasClass('disabled') || isLoading) return;

        const action = $(this).data('action');
        if (action === 'prev' && currentPage > 1) {
            currentPage--;
            loadCustomerList();
        } else if (action === 'next' && currentPage < totalPages) {
            currentPage++;
            loadCustomerList();
        }
    });
});