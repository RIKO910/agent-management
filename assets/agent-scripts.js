/* global agent_dashboard_ajax, jQuery */
jQuery(document).ready(function ($) {

    /* ================================================================
       STATE
    ================================================================ */
    let currentPage   = 1;
    let isLoading     = false;
    let hasMore       = true;
    let totalPages    = 1;
    let searchTimer   = null;
    let currentSearch = '';

    let countryPage   = 1;
    let countryTotalPages = 1;
    let currentCountry    = '';
    let countryLoading    = false;

    /* ================================================================
       LOGIN
    ================================================================ */
    $('#agentLoginForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html('<span class="loading-spinner"></span> Signing in…');

        $.post(agent_dashboard_ajax.ajaxurl, {
            action:   'agent_login',
            nonce:    agent_dashboard_ajax.nonce,
            username: $('input[name="login_username"]').val(),
            password: $('input[name="login_password"]').val()
        }, function (res) {
            if (res.success) {
                $('#loginMessage').html('<div class="success">✓ ' + res.data + ' — Redirecting…</div>');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                $('#loginMessage').html('<div class="error">✕ ' + res.data + '</div>');
                btn.prop('disabled', false).text('Sign In');
            }
        }).fail(function () {
            $('#loginMessage').html('<div class="error">Login failed. Please try again.</div>');
            btn.prop('disabled', false).text('Sign In');
        });
    });

    /* ================================================================
       SIGNUP
    ================================================================ */
    $('#agent-create-submit').on('click', function (e) {
        e.preventDefault();
        const btn = $(this);
        const password = $('input[name="sign_password"]').val();
        const confirm  = $('input[name="sign_confirm_password"]').val();

        if (password !== confirm) {
            $('#signupMessage').html('<div class="error">Passwords do not match.</div>'); return;
        }
        if (password.length < 6) {
            $('#signupMessage').html('<div class="error">Password must be at least 6 characters.</div>'); return;
        }

        btn.prop('disabled', true).html('<span class="loading-spinner"></span> Creating account…');

        $.post(agent_dashboard_ajax.ajaxurl, {
            action:         'agent_signup',
            nonce:          agent_dashboard_ajax.nonce,
            company_name:   $('input[name="sign_company_name"]').val(),
            username:       $('input[name="sign_username"]').val(),
            email:          $('input[name="sign_email"]').val(),
            phone:          $('input[name="sign_phone"]').val(),
            address:        $('textarea[name="sign_address"]').val(),
            license_number: $('input[name="sign_license_number"]').val(),
            password,
            confirm_password: confirm
        }, function (res) {
            if (res.success) {
                $('#signupMessage').html('<div class="success">✓ ' + res.data + '</div>');
                $('#agentSignupForm')[0].reset();
                setTimeout(() => { showLogin(); btn.prop('disabled', false).text('Create Account'); }, 2200);
            } else {
                $('#signupMessage').html('<div class="error">✕ ' + res.data + '</div>');
                btn.prop('disabled', false).text('Create Account');
            }
        }).fail(function () {
            $('#signupMessage').html('<div class="error">Registration failed. Please try again.</div>');
            btn.prop('disabled', false).text('Create Account');
        });
    });

    /* ================================================================
       TABS
    ================================================================ */
    $('.tab-button').on('click', function () {
        $('.tab-button').removeClass('active');
        $('.tab-content').removeClass('active');
        $(this).addClass('active');
        $('#' + $(this).data('tab')).addClass('active');

        const tab = $(this).data('tab');
        if (tab === 'customer-list') {
            resetPagination();
            loadCustomerList();
        } else if (tab === 'customer-countries') {
            loadCountryTab();
        }
    });

    /* ================================================================
       FILE SIZE VALIDATION
    ================================================================ */
    $(document).on('change', 'input[type="file"]', function () {
        const file = this.files[0];
        if (file && file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5 MB.');
            $(this).val('');
        }
    });

    /* ================================================================
       ADD / REMOVE ADDITIONAL IMAGES
    ================================================================ */
    $('#add-image-btn').on('click', function () {
        $('.multiple-image-upload-section').append(
            '<div class="additional-image-field">' +
            '<div class="form-group" style="flex:1;margin:0;">' +
            '<label>Additional Image</label>' +
            '<input type="file" name="additional_images[]" accept="image/*">' +
            '</div>' +
            '<button type="button" class="remove-image-btn">Remove</button>' +
            '</div>'
        );
    });
    $(document).on('click', '.remove-image-btn', function () {
        $(this).closest('.additional-image-field').remove();
    });

    /* ================================================================
       CUSTOMER FORM SUBMIT (add + update)
    ================================================================ */
    $('#customerForm').on('submit', function (e) {
        e.preventDefault();
        const formData   = new FormData(this);
        const submitBtn  = $('#customerForm button[type="submit"]');
        const customerId = $('#customer_id_hidden').val();

        if (customerId) {
            formData.append('action', 'update_customer');
            formData.append('customer_id', customerId);
            submitBtn.prop('disabled', true).html('<span class="loading-spinner"></span> Updating…');
        } else {
            formData.append('action', 'submit_customer_form');
            submitBtn.prop('disabled', true).html('<span class="loading-spinner"></span> Submitting…');
        }

        $.ajax({
            url: agent_dashboard_ajax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                if (res.success) {
                    $('#formMessage').html('<div class="success">✓ ' + res.data + '</div>');
                    $('#customerForm')[0].reset();
                    clearEditState();
                    resetPagination();
                    loadCustomerList();
                    autoHideMessage('#formMessage');
                } else {
                    $('#formMessage').html('<div class="error">✕ ' + res.data + '</div>');
                }
            },
            error: function () {
                $('#formMessage').html('<div class="error">An error occurred. Please try again.</div>');
            },
            complete: function () {
                const editMode = $('#customer_id_hidden').length > 0;
                submitBtn.prop('disabled', false).text(editMode ? 'Update Customer' : 'Submit Customer');
            }
        });
    });

    function clearEditState() {
        $('#customer_id_hidden').remove();
        $('.passport-image-preview, .new-passport-preview, .additional-images-preview, .additional-image-preview').remove();
        $('input[name="passport_image"]').attr('required', true);
        $('#customerForm button[type="submit"]').text('Submit Customer');
        $('#cancel-edit-btn').hide();
        $('.additional-image-field').remove();
    }

    /* ================================================================
       CANCEL EDIT
    ================================================================ */
    $(document).on('click', '#cancel-edit-btn', function () {
        if (confirm('Cancel editing? Unsaved changes will be lost.')) {
            $('#customerForm')[0].reset();
            clearEditState();
            $('#formMessage').empty();
        }
    });

    /* ================================================================
       DELETE CUSTOMER
    ================================================================ */
    $(document).on('click', '.delete-customer-btn', function () {
        if (!confirm('Delete this customer? This cannot be undone.')) return;
        const customerId = $(this).data('customer-id');
        const $row = $(this).closest('tr');

        $.ajax({
            url: agent_dashboard_ajax.ajaxurl,
            type: 'POST',
            data: { action: 'delete_customer', customer_id: customerId, nonce: agent_dashboard_ajax.nonce },
            beforeSend () { $row.css('opacity', '.4'); },
            success (res) {
                if (res.success) {
                    $row.fadeOut(250, function () { $(this).remove(); loadCustomerList(); });
                } else {
                    alert('Error: ' + res.data); $row.css('opacity', '1');
                }
            },
            error () { alert('Failed to delete. Please try again.'); $row.css('opacity', '1'); }
        });
    });

    /* ================================================================
       EDIT CUSTOMER
    ================================================================ */
    $(document).on('click', '.edit-customer-btn', function () {
        const customerId = $(this).data('customer-id');
        const btn = $(this);
        btn.prop('disabled', true).text('Loading…');

        $.ajax({
            url: agent_dashboard_ajax.ajaxurl,
            type: 'POST',
            data: { action: 'get_customer_details', customer_id: customerId, nonce: agent_dashboard_ajax.nonce },
            success (res) {
                if (res.success) {
                    populateEditForm(res.data);
                    $('.tab-button[data-tab="customer-form"]').trigger('click');
                } else {
                    alert('Error: ' + res.data);
                }
            },
            error () { alert('Failed to load customer details.'); },
            complete () { btn.prop('disabled', false).text('Edit'); }
        });
    });

    /* ================================================================
       PAGINATION BUTTONS
    ================================================================ */
    $(document).on('click', '.pagination-btn', function () {
        if ($(this).hasClass('disabled') || isLoading) return;
        const action = $(this).data('action');
        if (action === 'prev' && currentPage > 1)         { currentPage--; loadCustomerList(); }
        else if (action === 'next' && currentPage < totalPages) { currentPage++; loadCustomerList(); }
    });

    /* ================================================================
       SEARCH (debounced)
    ================================================================ */
    $(document).on('input', '#customer-search-input', function () {
        clearTimeout(searchTimer);
        const q = $(this).val().trim();
        searchTimer = setTimeout(function () {
            currentSearch = q;
            resetPagination();
            loadCustomerList();
        }, 400);
    });

    $(document).on('click', '#search-clear-btn', function () {
        $('#customer-search-input').val('');
        currentSearch = '';
        resetPagination();
        loadCustomerList();
    });

    /* ================================================================
       DELETE ADDITIONAL IMAGE
    ================================================================ */
    $(document).on('click', '.delete-additional-image', function () {
        if (!confirm('Delete this image?')) return;
        const imageId  = $(this).data('image-id');
        const $item    = $(this).closest('.preview-image-item');

        $.ajax({
            url: agent_dashboard_ajax.ajaxurl,
            type: 'POST',
            data: { action: 'delete_customer_image', image_id: imageId, nonce: agent_dashboard_ajax.nonce },
            beforeSend () { $item.css('opacity', '.4'); },
            success (res) {
                if (res.success) {
                    $item.fadeOut(250, function () {
                        $(this).remove();
                        if ($('.preview-image-item').length === 0) $('.additional-images-preview').remove();
                    });
                } else {
                    alert('Error: ' + res.data); $item.css('opacity', '1');
                }
            },
            error () { alert('Failed to delete image.'); $item.css('opacity', '1'); }
        });
    });

    /* ================================================================
       PASSPORT IMAGE PREVIEW
    ================================================================ */
    $(document).on('change', 'input[name="passport_image"]', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (ev) {
            $('.new-passport-preview').remove();
            $('input[name="passport_image"]').parent().append(
                '<div class="new-passport-preview">' +
                '<p>New Passport Image Preview:</p>' +
                '<img src="' + ev.target.result + '">' +
                '</div>'
            );
        };
        reader.readAsDataURL(file);
    });

    /* ================================================================
       ADDITIONAL IMAGE PREVIEW
    ================================================================ */
    $(document).on('change', 'input[name="additional_images[]"]', function () {
        const file = this.files[0];
        const $parent = $(this).closest('.additional-image-field');
        $parent.find('.additional-image-preview').remove();
        if (!file) return;
        const reader = new FileReader();
        reader.onload = ev => {
            $parent.append(
                '<div class="additional-image-preview" style="margin-top:8px;">' +
                '<img src="' + ev.target.result + '" style="width:80px;height:80px;object-fit:cover;border-radius:var(--radius-sm);border:2px solid #4ade80;">' +
                '</div>'
            );
        };
        reader.readAsDataURL(file);
    });

    /* ================================================================
       FORM RESET
    ================================================================ */
    $('#customerForm').on('reset', function () { clearEditState(); $('#formMessage').empty(); });

    /* ================================================================
       COUNTRY TAB — initial load
    ================================================================ */
    function loadCountryTab() {
        const $wrap = $('#country-tab-content');
        $wrap.html('<div style="text-align:center;padding:40px;"><div class="loading-spinner-large"></div></div>');

        $.ajax({
            url: agent_dashboard_ajax.ajaxurl,
            type: 'POST',
            data: { action: 'get_customer_countries', nonce: agent_dashboard_ajax.nonce },
            success (res) {
                if (res.success) renderCountryTab(res.data);
                else $wrap.html('<p style="color:var(--text-muted)">Failed to load countries.</p>');
            },
            error () { $wrap.html('<p style="color:var(--text-muted)">Network error.</p>'); }
        });
    }

    function renderCountryTab(data) {
        const countries = data.countries || [];
        const $wrap = $('#country-tab-content');

        if (countries.length === 0) {
            $wrap.html(
                '<div class="country-empty-state">' +
                '<svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 2a10 10 0 100 20A10 10 0 0012 2zm0 0v20M2 12h20"/></svg>' +
                '<p>No customers added yet.</p>' +
                '</div>'
            );
            return;
        }

        // Build pills
        let pillsHTML = '<div class="country-filter-list">';
        countries.forEach((c, i) => {
            pillsHTML +=
                '<button class="country-pill' + (i === 0 ? ' active' : '') + '" data-country="' + escHtml(c.country) + '">' +
                escHtml(c.country) +
                '<span class="pill-count">' + c.count + '</span>' +
                '</button>';
        });
        pillsHTML += '</div>';

        $wrap.html(pillsHTML + '<div id="country-customers-wrap"></div>');

        // Load first country
        if (countries.length > 0) loadCountryCustomers(countries[0].country);
    }

    // Click on country pill
    $(document).on('click', '.country-pill', function () {
        if ($(this).hasClass('active')) return;
        $('.country-pill').removeClass('active');
        $(this).addClass('active');
        countryPage = 1;
        loadCountryCustomers($(this).data('country'));
    });

    // Country pagination
    $(document).on('click', '.country-pagination-btn', function () {
        if ($(this).hasClass('disabled') || countryLoading) return;
        const action = $(this).data('action');
        if (action === 'prev' && countryPage > 1)                   { countryPage--; loadCountryCustomers(currentCountry); }
        else if (action === 'next' && countryPage < countryTotalPages) { countryPage++; loadCountryCustomers(currentCountry); }
    });

    function loadCountryCustomers(country) {
        if (countryLoading) return;
        currentCountry = country;
        countryLoading = true;
        const $wrap = $('#country-customers-wrap');
        $wrap.html('<div style="text-align:center;padding:30px;"><div class="loading-spinner-large"></div></div>');

        $.ajax({
            url: agent_dashboard_ajax.ajaxurl,
            type: 'POST',
            data: {
                action:   'get_customers_by_country',
                country,
                page:     countryPage,
                per_page: 10,
                nonce:    agent_dashboard_ajax.nonce
            },
            success (res) {
                countryLoading = false;
                if (res.success) {
                    countryTotalPages = res.data.total_pages || 1;
                    renderCountryCustomers(res.data, country);
                } else {
                    $wrap.html('<p style="color:var(--text-muted)">Failed to load customers.</p>');
                }
            },
            error () {
                countryLoading = false;
                $wrap.html('<p style="color:var(--text-muted)">Network error.</p>');
            }
        });
    }

    function renderCountryCustomers(data, country) {
        const $wrap = $('#country-customers-wrap');
        const total = data.total || 0;

        let html =
            '<div class="country-customers-section">' +
            '<h4>Customers in <span class="country-badge">' + escHtml(country) + '</span> — ' + total + ' total</h4>';

        if (data.html) {
            html +=
                '<div class="customer-table-container">' +
                '<table class="customer-table">' +
                '<thead><tr>' +
                '<th>Name</th><th>Phone</th><th>Passport No.</th>' +
                '<th>Visa Type</th><th>Submission Date</th><th>Status</th><th>Images</th><th>Actions</th>' +
                '</tr></thead>' +
                '<tbody>' + data.html + '</tbody>' +
                '</table>' +
                '</div>';
        } else {
            html +=
                '<div class="country-empty-state">' +
                '<svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>' +
                '<p>No customers found in ' + escHtml(country) + '.</p>' +
                '</div>';
        }

        // Country pagination
        if (countryTotalPages > 1) {
            html +=
                '<div class="pagination-container" style="margin-top:16px;">' +
                '<div class="pagination-info">Page ' + countryPage + ' of ' + countryTotalPages + ' (' + total + ' customers)</div>' +
                '<div class="pagination-buttons">' +
                '<button class="country-pagination-btn pagination-btn ' + (countryPage <= 1 ? 'disabled' : '') + '" data-action="prev">← Previous</button>' +
                '<button class="country-pagination-btn pagination-btn ' + (countryPage >= countryTotalPages ? 'disabled' : '') + '" data-action="next">Next →</button>' +
                '</div>' +
                '</div>';
        }

        html += '</div>';
        $wrap.html(html);
        initializeLightbox();
    }

    /* ================================================================
       HELPERS
    ================================================================ */
    function escHtml(str) {
        return String(str)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function autoHideMessage(selector) {
        setTimeout(function () {
            $(selector).fadeOut(300, function () { $(this).empty().show(); });
        }, 5000);
    }

    function initializeLightbox() {
        if (typeof $.fn.prettyPhoto !== 'undefined') {
            $('a[rel^="prettyPhoto"]').prettyPhoto({
                social_tools: false,
                theme: 'pp_woocommerce',
                horizontal_padding: 20,
                opacity: 0.8,
                deeplinking: false
            });
        }
    }

    /* ================================================================
       EXPOSED GLOBALS (used in PHP-generated onclick / other functions)
    ================================================================ */
    window.resetPagination = function () {
        currentPage = 1; isLoading = false; hasMore = true; totalPages = 1;
        $('.customer-table tbody').empty();
        $('.pagination-container').remove();
    };

    window.loadCustomerList = function () {
        if (isLoading) return;
        isLoading = true;
        const $tbody = $('.customer-table tbody');

        $tbody.fadeOut(150, function () {
            $tbody.html(
                '<tr id="temp-loading"><td colspan="8" style="text-align:center;padding:40px;">' +
                '<div class="loading-spinner-large"></div>' +
                '<p style="margin-top:12px;color:var(--text-muted);">Loading customers…</p>' +
                '</td></tr>'
            ).fadeIn(150);
            $('.pagination-container').remove();

            $.ajax({
                url: agent_dashboard_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action:   'get_customer_list_paginated',
                    page:     currentPage,
                    per_page: 10,
                    search:   currentSearch,
                    nonce:    agent_dashboard_ajax.nonce
                },
                success (res) {
                    isLoading = false;
                    if (res.success) {
                        $tbody.fadeOut(150, function () {
                            $(this).html(res.data.html).fadeIn(250, function () {
                                initializeLightbox();
                                hasMore   = res.data.has_more;
                                const tot = res.data.total || 0;
                                totalPages = Math.ceil(tot / 10);
                                addPaginationControls(tot);
                            });
                        });
                    } else {
                        showTableError('Failed to load customers.');
                    }
                },
                error () {
                    isLoading = false;
                    showTableError('Network error. Please try again.');
                }
            });
        });
    };

    function addPaginationControls(total) {
        $('.pagination-container').remove();
        if (total === 0) return;
        const html =
            '<div class="pagination-container">' +
            '<div class="pagination-info">Page ' + currentPage + ' of ' + totalPages + ' (' + total + ' customers)</div>' +
            '<div class="pagination-buttons">' +
            '<button class="pagination-btn ' + (currentPage <= 1 ? 'disabled' : '') + '" data-action="prev">← Previous</button>' +
            '<button class="pagination-btn ' + (currentPage >= totalPages ? 'disabled' : '') + '" data-action="next">Next →</button>' +
            '</div>' +
            '</div>';
        $('.customer-table-container').after(html);
    }

    function showTableError(msg) {
        $('.customer-table tbody').html(
            '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--danger);">' +
            '⚠ ' + msg +
            '<br><button onclick="loadCustomerList()" style="margin-top:10px;padding:6px 16px;background:var(--accent);color:#fff;border:none;border-radius:var(--radius-sm);cursor:pointer;font-family:var(--font);">Retry</button>' +
            '</td></tr>'
        );
        $('.pagination-container').remove();
    }

    window.populateEditForm = function (customer) {
        const form = $('#customerForm');

        if ($('#customer_id_hidden').length === 0) {
            form.prepend('<input type="hidden" id="customer_id_hidden" name="customer_id" value="' + customer.id + '">');
        } else {
            $('#customer_id_hidden').val(customer.id);
        }

        form.find('input[name="customer_name"]').val(customer.customer_name);
        form.find('input[name="customer_phone"]').val(customer.customer_phone);
        form.find('input[name="passport_number"]').val(customer.passport_number);
        form.find('select[name="visa_country"]').val(customer.visa_country);
        form.find('select[name="visa_type"]').val(customer.visa_type);
        form.find('input[name="submission_date"]').val(customer.submission_date);

        // Passport image
        $('input[name="passport_image"]').removeAttr('required');
        $('.passport-image-preview').remove();
        if (customer.passport_image) {
            $('input[name="passport_image"]').parent().find('label').after(
                '<div class="passport-image-preview">' +
                '<p>Current Passport Image</p>' +
                '<img src="' + customer.passport_image + '">' +
                '<p style="margin-top:8px;font-size:.78rem;color:var(--text-muted);">Upload a new image to replace (optional)</p>' +
                '</div>'
            );
        }

        // Additional images
        $('.additional-images-preview').remove();
        if (customer.additional_images && customer.additional_images.length > 0) {
            let previewHTML =
                '<div class="additional-images-preview">' +
                '<p>Current Additional Images</p>' +
                '<div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px;">';
            customer.additional_images.forEach(img => {
                previewHTML +=
                    '<div class="preview-image-item">' +
                    '<img src="' + img.image_url + '">' +
                    '<button type="button" class="delete-additional-image" data-image-id="' + img.id + '">×</button>' +
                    '</div>';
            });
            previewHTML += '</div><p style="margin-top:10px;font-size:.78rem;color:var(--text-muted);">Delete images above or add new ones below</p></div>';
            form.find('.multiple-image-upload-section').before(previewHTML);
        }

        form.find('button[type="submit"]').text('Update Customer');
        $('#cancel-edit-btn').show();

        $('#formMessage').html(
            '<div class="info">✎ Editing: <strong>' + customer.customer_name + '</strong> — make your changes and click Update.</div>'
        );

        $('html, body').animate({ scrollTop: form.offset().top - 80 }, 400);
    };

    window.showSignup = function () {
        $('.agent-login-form').hide();
        $('.agent-signup-form').show();
    };
    window.showLogin = function () {
        $('.agent-signup-form').hide();
        $('.agent-login-form').show();
    };
});