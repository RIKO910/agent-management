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

        // Check if we're updating an existing customer
        const customerId = $('#customer_id_hidden').val();
        if (customerId) {
            formData.append('action', 'update_customer');
            formData.append('customer_id', customerId);
        } else {
            formData.append('action', 'submit_customer_form');
        }

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
                    $('#customer_id_hidden').remove();
                    $('#customerForm button[type="submit"]').text('Submit Customer Information');

                    // Refresh customer list
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

    $(document).on('click', '.delete-customer-btn', function() {
        if (!confirm('Are you sure you want to delete this customer?')) {
            return;
        }

        const customerId = $(this).data('customer-id');
        const $row = $(this).closest('tr');

        $.ajax({
            url: agent_dashboard_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'delete_customer',
                customer_id: customerId,
                nonce: agent_dashboard_ajax.nonce
            },
            beforeSend: function() {
                $row.css('opacity', '0.5');
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        // Reload the current page to update counts
                        loadCustomerList();
                    });
                    alert('Customer deleted successfully');
                } else {
                    alert('Error: ' + response.data);
                    $row.css('opacity', '1');
                }
            },
            error: function() {
                alert('Failed to delete customer. Please try again.');
                $row.css('opacity', '1');
            }
        });
    });

    // Edit customer handler
    $(document).on('click', '.edit-customer-btn', function() {
        const customerId = $(this).data('customer-id');
        const editButton = $(this);

        // Store original text
        const originalText = editButton.text();

        // Disable button and show loading state
        editButton.prop('disabled', true).html('Loading...');

        $.ajax({
            url: agent_dashboard_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_customer_details',
                customer_id: customerId,
                nonce: agent_dashboard_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log(response.data);
                    populateEditForm(response.data);
                    // Switch to customer form tab
                    $('.tab-button[data-tab="customer-form"]').click();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Failed to load customer details. Please try again.');
            },
            complete: function() {
                // Re-enable button and restore original text
                editButton.prop('disabled', false).html(originalText);
            }
        });
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


// Enhanced populateEditForm function with image preview
function populateEditForm(customer) {
    const form = jQuery('#customerForm');

    // Add hidden field for customer ID
    if (jQuery('#customer_id_hidden').length === 0) {
        form.prepend('<input type="hidden" id="customer_id_hidden" name="customer_id" value="' + customer.id + '">');
    } else {
        jQuery('#customer_id_hidden').val(customer.id);
    }

    // Populate form fields
    form.find('input[name="customer_name"]').val(customer.customer_name);
    form.find('input[name="customer_phone"]').val(customer.customer_phone);
    form.find('input[name="passport_number"]').val(customer.passport_number);
    form.find('input[name="visa_country"]').val(customer.visa_country);
    form.find('select[name="visa_type"]').val(customer.visa_type);
    form.find('input[name="submission_date"]').val(customer.submission_date);

    // Handle passport image preview
    const passportInput = form.find('input[name="passport_image"]');
    passportInput.removeAttr('required');

    // Remove any existing preview
    jQuery('.passport-image-preview').remove();

    // Add current passport image preview
    if (customer.passport_image) {
        const previewHTML = `
            <div class="passport-image-preview" style="margin: 10px 0; padding: 10px; background: #f5f5f5; border-radius: 5px;">
                <p style="margin: 0 0 10px 0; font-weight: bold; color: #666;">Current Passport Image:</p>
                <img src="${customer.passport_image}" style="max-width: 200px; height: auto; border-radius: 4px; border: 2px solid #ddd;">
                <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">Upload a new image to replace this one (optional)</p>
            </div>
        `;
        passportInput.parent().find('label').after(previewHTML);
    }

    // Handle additional images preview
    jQuery('.additional-images-preview').remove();

    if (customer.additional_images && customer.additional_images.length > 0) {
        let additionalImagesHTML = `
            <div class="additional-images-preview" style="margin: 10px 0; padding: 10px; background: #f5f5f5; border-radius: 5px;">
                <p style="margin: 0 0 10px 0; font-weight: bold; color: #666;">Current Additional Images:</p>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
        `;

        customer.additional_images.forEach(function(image) {
            additionalImagesHTML += `
                <div class="preview-image-item" style="position: relative;">
                    <img src="${image.image_url}" style="width: 100px; height: 100px; object-fit: cover; border-radius: 4px; border: 2px solid #ddd;">
                    <button type="button" class="delete-additional-image" data-image-id="${image.id}" 
                            style="position: absolute; top: -5px; right: -5px; background: #f44336; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 14px; line-height: 1;">×</button>
                </div>
            `;
        });

        additionalImagesHTML += `
                </div>
                <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">You can delete images above or add new ones below</p>
            </div>
        `;

        form.find('.multiple-image-upload-section').before(additionalImagesHTML);
    }

    // Change submit button text
    form.find('button[type="submit"]').text('Update Customer Information');

    // Show message
    jQuery('#formMessage').html('<div class="info">Editing customer: <strong>' + customer.customer_name + '</strong><br>Make your changes and click Update to save.</div>');

    // Scroll to form
    jQuery('html, body').animate({
        scrollTop: form.offset().top - 100
    }, 500);
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


    // Add handler for deleting additional images
    jQuery(document).on('click', '.delete-additional-image', function() {
        if (!confirm('Are you sure you want to delete this image?')) {
            return;
        }

        const imageId = jQuery(this).data('image-id');
        const $imageItem = jQuery(this).closest('.preview-image-item');

        jQuery.ajax({
            url: agent_dashboard_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'delete_customer_image',
                image_id: imageId,
                nonce: agent_dashboard_ajax.nonce
            },
            beforeSend: function() {
                $imageItem.css('opacity', '0.5');
            },
            success: function(response) {
                if (response.success) {
                    $imageItem.fadeOut(300, function() {
                        jQuery(this).remove();
                        // Check if no more images
                        if (jQuery('.preview-image-item').length === 0) {
                            jQuery('.additional-images-preview').remove();
                        }
                    });
                } else {
                    alert('Error: ' + response.data);
                    $imageItem.css('opacity', '1');
                }
            },
            error: function() {
                alert('Failed to delete image. Please try again.');
                $imageItem.css('opacity', '1');
            }
        });
    });

// Add image preview on file selection
    jQuery(document).on('change', 'input[name="passport_image"]', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Remove old preview
                jQuery('.new-passport-preview').remove();

                // Add new preview
                const previewHTML = `
                <div class="new-passport-preview" style="margin: 10px 0; padding: 10px; background: #e8f5e9; border-radius: 5px;">
                    <p style="margin: 0 0 10px 0; font-weight: bold; color: #2e7d32;">New Passport Image Preview:</p>
                    <img src="${e.target.result}" style="max-width: 200px; height: auto; border-radius: 4px; border: 2px solid #4caf50;">
                </div>
            `;
                jQuery('input[name="passport_image"]').parent().append(previewHTML);
            };
            reader.readAsDataURL(file);
        }
    });

// Add preview for additional images
    jQuery(document).on('change', 'input[name="additional_images[]"]', function() {
        const file = this.files[0];
        const $parent = jQuery(this).parent();

        // Remove existing preview in this field
        $parent.find('.additional-image-preview').remove();

        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewHTML = `
                <div class="additional-image-preview" style="margin: 10px 0;">
                    <img src="${e.target.result}" style="width: 100px; height: 100px; object-fit: cover; border-radius: 4px; border: 2px solid #4caf50;">
                </div>
            `;
                $parent.append(previewHTML);
            };
            reader.readAsDataURL(file);
        }
    });

    // Update form reset to clear all edit-related elements
    jQuery('#customerForm').on('reset', function() {
        jQuery('#customer_id_hidden').remove();
        jQuery('.passport-image-preview').remove();
        jQuery('.new-passport-preview').remove();
        jQuery('.additional-images-preview').remove();
        jQuery('.additional-image-preview').remove();
        jQuery('#customerForm button[type="submit"]').text('Submit Customer Information');
        jQuery('input[name="passport_image"]').attr('required', 'required');
        jQuery('#formMessage').empty();
    });

    // Add cancel edit button functionality
    jQuery(document).on('click', '#cancel-edit-btn', function() {
        if (confirm('Are you sure you want to cancel editing? Any unsaved changes will be lost.')) {
            jQuery('#customerForm')[0].reset();
            jQuery('#customerForm').trigger('reset');
        }
    });

});