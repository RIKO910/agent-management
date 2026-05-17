(function ($) {
    'use strict';

    function escAttr(s) {
        return $('<div />').text(s).html();
    }

    /* --- Lightbox --- */
    let $overlay = null;
    let $caption = null;

    function ensureLightbox() {
        if ($overlay && $overlay.length) return;
        $overlay = $(
            '<div class="amg-lightbox-overlay" role="dialog" aria-modal="true" aria-hidden="true">' +
                '<div class="amg-lightbox-inner">' +
                    '<button type="button" class="amg-lightbox-close" aria-label="Close">&times;</button>' +
                    '<img src="" alt="">' +
                    '<p class="amg-lightbox-caption"></p>' +
                '</div>' +
            '</div>'
        );
        $caption = $overlay.find('.amg-lightbox-caption');
        // Last in <body> so fixed positioning stacks above WP admin content.
        $(document.body).append($overlay);
        $overlay.css({
            position: 'fixed',
            zIndex: 2147483000,
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
        });

        $overlay.on('click', function (e) {
            if (e.target === this) closeLightbox();
        });
        $overlay.find('.amg-lightbox-close').on('click', closeLightbox);
        $overlay.on('click', '.amg-lightbox-inner', function (e) {
            e.stopPropagation();
        });

        $(document).on('keydown.amgLb', function (e) {
            if (e.key === 'Escape') closeLightbox();
        });
    }

    function openLightbox(fullUrl, title) {
        ensureLightbox();
        $overlay.detach().appendTo(document.body);
        $overlay.css({ zIndex: 2147483000 });
        const img = $overlay.find('img');
        img.attr('src', fullUrl).attr('alt', title || '');
        $caption.text(title || '');
        $overlay.addClass('amg-open').attr('aria-hidden', 'false');
    }

    function closeLightbox() {
        if (!$overlay) return;
        $overlay.removeClass('amg-open').attr('aria-hidden', 'true');
        $overlay.find('img').attr('src', '');
        $caption.text('');
    }

    $(document).on('click', '.amg-lightbox-trigger', function (e) {
        e.preventDefault();
        const url = $(this).data('full') || $(this).attr('href');
        const title = $(this).data('title') || '';
        if (url) openLightbox(url, title);
    });

    /* --- Agent customers modal --- */
    let $modalOverlay = null;

    function ensureModal() {
        if ($modalOverlay && $modalOverlay.length) return;
        $modalOverlay = $(
            '<div class="amg-modal-overlay" aria-hidden="true">' +
                '<div class="amg-modal" role="dialog" aria-modal="true" aria-labelledby="amg-modal-title">' +
                    '<div class="amg-modal-header">' +
                        '<h2 id="amg-modal-title"></h2>' +
                        '<button type="button" class="amg-btn amg-btn-secondary amg-modal-close">Close</button>' +
                    '</div>' +
                    '<div class="amg-modal-body"></div>' +
                '</div>' +
            '</div>'
        );
        $(document.body).append($modalOverlay);
        $modalOverlay.css({
            position: 'fixed',
            zIndex: 2147482000,
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
        });
        $modalOverlay.on('click', function (e) {
            if (e.target === this) closeModal();
        });
        $modalOverlay.find('.amg-modal-close').on('click', closeModal);
        $(document).on('keydown.amgModal', function (e) {
            if (e.key === 'Escape' && $modalOverlay.hasClass('amg-open')) closeModal();
        });
    }

    function closeModal() {
        if (!$modalOverlay) return;
        $modalOverlay.removeClass('amg-open').attr('aria-hidden', 'true');
        $modalOverlay.find('.amg-modal-body').empty();
    }

    $(document).on('click', '.amg-btn-customers', function (e) {
        e.preventDefault();
        const agentId = $(this).data('agent-id');
        const company = $(this).data('company') || 'Agent';
        if (!agentId || !window.amgAdmin || !amgAdmin.ajaxurl || !amgAdmin.nonce) return;

        ensureModal();
        $modalOverlay.detach().appendTo(document.body);
        $modalOverlay.css({ zIndex: 2147482000 });
        $modalOverlay.find('#amg-modal-title').text('Customers — ' + company);
        $modalOverlay.find('.amg-modal-body').html('<p class="amg-modal-loading">Loading…</p>');
        $modalOverlay.addClass('amg-open').attr('aria-hidden', 'false');

        $.post(amgAdmin.ajaxurl, {
            action: 'agent_management_get_agent_customers',
            nonce: amgAdmin.nonce,
            agent_id: agentId,
        })
            .done(function (res) {
                var title = 'Customers — ' + company;
                $modalOverlay.find('#amg-modal-title').text(title);
                if (res.success && res.data && res.data.html) {
                    $modalOverlay.find('.amg-modal-body').html(res.data.html);
                } else {
                    var msg =
                        res.data && res.data.message
                            ? String(res.data.message)
                            : 'Could not load customers.';
                    $modalOverlay.find('.amg-modal-body').html(
                        '<p class="amg-modal-error">' + escAttr(msg) + '</p>'
                    );
                }
            })
            .fail(function () {
                $modalOverlay.find('#amg-modal-title').text('Customers — ' + company);
                $modalOverlay
                    .find('.amg-modal-body')
                    .html('<p class="amg-modal-error">Request failed. Please try again.</p>');
            });
    });
    $(document).on('click', '.amg-fe-mirror .country-pill', function (e) {
        e.preventDefault();
        var country = $(this).attr('data-country');
        var $root = $(this).closest('.amg-fe-mirror');
        if (!country || !$root.length) return;

        $root.find('.country-pill').removeClass('active');
        $(this).addClass('active');

        $root.find('.amg-country-panel').each(function () {
            var panelCountry = $(this).attr('data-country-panel');
            if (panelCountry === country) {
                $(this).removeAttr('hidden');
            } else {
                $(this).attr('hidden', 'hidden');
            }
        });
    });

    /* --- Admin edit forms (agent + customer) --- */
    var $formModal = null;

    function ensureFormModal() {
        if ($formModal && $formModal.length) return;
        $formModal = $(
            '<div class="amg-modal-overlay amg-form-modal-overlay" aria-hidden="true">' +
                '<div class="amg-modal amg-form-modal" role="dialog" aria-modal="true">' +
                    '<div class="amg-modal-header">' +
                        '<h2 class="amg-form-modal-title"></h2>' +
                        '<button type="button" class="amg-btn amg-btn-secondary amg-form-modal-close">Close</button>' +
                    '</div>' +
                    '<div class="amg-modal-body amg-form-modal-body"></div>' +
                '</div>' +
            '</div>'
        );
        $(document.body).append($formModal);
        $formModal.css({
            position: 'fixed',
            zIndex: 2147482000,
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
        });
        $formModal.on('click', function (e) {
            if (e.target === this) closeFormModal();
        });
        $formModal.find('.amg-form-modal-close').on('click', closeFormModal);
        $(document).on('keydown.amgFormModal', function (e) {
            if (e.key === 'Escape' && $formModal.hasClass('amg-open')) closeFormModal();
        });
    }

    function closeFormModal() {
        if (!$formModal) return;
        $formModal.removeClass('amg-open').attr('aria-hidden', 'true');
        $formModal.find('.amg-form-modal-body').empty();
    }

    function openFormModal(title, html) {
        ensureFormModal();
        $formModal.detach().appendTo(document.body);
        $formModal.find('.amg-form-modal-title').text(title);
        $formModal.find('.amg-form-modal-body').html(html);
        $formModal.addClass('amg-open').attr('aria-hidden', 'false');
    }

    function countryOptionsHtml(selected) {
        var opts = '';
        var countries = (window.amgAdmin && amgAdmin.countries) || [];
        var found = false;
        for (var i = 0; i < countries.length; i++) {
            var c = countries[i];
            if (c === selected) {
                found = true;
            }
            var sel = c === selected ? ' selected' : '';
            opts += '<option value="' + escAttr(c) + '"' + sel + '>' + escAttr(c) + '</option>';
        }
        if (selected && !found) {
            opts =
                '<option value="' + escAttr(selected) + '" selected>' + escAttr(selected) + '</option>' + opts;
        }
        return opts;
    }

    function visaOptionsHtml(selected) {
        var types = [
            { v: 'tourist', l: 'Tourist' },
            { v: 'student', l: 'Student' },
            { v: 'work', l: 'Work' },
            { v: 'business', l: 'Business' },
        ];
        var html = '<option value="">—</option>';
        for (var i = 0; i < types.length; i++) {
            var sel = types[i].v === selected ? ' selected' : '';
            html +=
                '<option value="' +
                escAttr(types[i].v) +
                '"' +
                sel +
                '>' +
                escAttr(types[i].l) +
                '</option>';
        }
        return html;
    }

    function statusOptionsHtml(selected) {
        var st = ['pending', 'approved', 'rejected'];
        var html = '';
        for (var i = 0; i < st.length; i++) {
            var sel = st[i] === selected ? ' selected' : '';
            html += '<option value="' + st[i] + '"' + sel + '>' + escAttr(st[i].charAt(0).toUpperCase() + st[i].slice(1)) + '</option>';
        }
        return html;
    }

    $(document).on('click', '.amg-edit-agent', function (e) {
        e.preventDefault();
        var id = $(this).data('agent-id');
        if (!id || !window.amgAdmin) return;

        $.post(amgAdmin.ajaxurl, {
            action: 'agent_management_admin_get_agent',
            nonce: amgAdmin.nonce,
            agent_id: id,
        })
            .done(function (res) {
                if (!res.success || !res.data) {
                    var m = res.data && res.data.message ? res.data.message : (amgAdmin.i18n && amgAdmin.i18n.loadFailed) || 'Error';
                    alert(m);
                    return;
                }
                var a = res.data;
                var html =
                    '<form class="amg-edit-form" id="amg-agent-edit-form">' +
                    '<p class="amg-readonly-email"><strong>Email:</strong> ' +
                    escAttr(a.user_email || '') +
                    '</p>' +
                    '<label class="amg-label">' +
                    escAttr('Company name') +
                    '</label>' +
                    '<input type="text" name="company_name" class="amg-input" required value="' +
                    escAttr(a.company_name || '') +
                    '">' +
                    '<label class="amg-label">' +
                    escAttr('Phone') +
                    '</label>' +
                    '<input type="text" name="phone" class="amg-input" required value="' +
                    escAttr(a.phone || '') +
                    '">' +
                    '<label class="amg-label">' +
                    escAttr('Address') +
                    '</label>' +
                    '<textarea name="address" class="amg-textarea" rows="3" required>' +
                    escAttr(a.address || '') +
                    '</textarea>' +
                    '<label class="amg-label">' +
                    escAttr('License number') +
                    '</label>' +
                    '<input type="text" name="license_number" class="amg-input" required value="' +
                    escAttr(a.license_number || '') +
                    '">' +
                    '<label class="amg-label">' +
                    escAttr('Status') +
                    '</label>' +
                    '<select name="status" class="amg-select amg-input-select">' +
                    statusOptionsHtml(a.status || 'pending') +
                    '</select>' +
                    '<div class="amg-form-actions">' +
                    '<button type="submit" class="amg-btn amg-btn-primary">' +
                    escAttr('Save') +
                    '</button>' +
                    '</div>' +
                    '<p class="amg-form-msg" style="display:none;"></p>' +
                    '</form>';

                openFormModal('Edit agent', html);
                $('#amg-agent-edit-form').data('agent-id', id);
            })
            .fail(function () {
                alert((amgAdmin.i18n && amgAdmin.i18n.loadFailed) || 'Error');
            });
    });

    $(document).on('submit', '#amg-agent-edit-form', function (e) {
        e.preventDefault();
        var $f = $(this);
        var agentId = $f.data('agent-id');
        var $msg = $f.find('.amg-form-msg');
        $msg.hide().text('');
     
        $.post(amgAdmin.ajaxurl, {
            action: 'agent_management_admin_save_agent',
            nonce: amgAdmin.nonce,
            agent_id: agentId,
            company_name: $f.find('[name="company_name"]').val(),
            phone: $f.find('[name="phone"]').val(),
            address: $f.find('[name="address"]').val(),
            license_number: $f.find('[name="license_number"]').val(),
            status: $f.find('[name="status"]').val(),
        })
            .done(function (res) {
                if (res.success) {
                    alert((res.data && res.data.message) || (amgAdmin.i18n && amgAdmin.i18n.saved) || 'OK');
                    closeFormModal();
                    window.location.reload();
                } else {
                    var m = res.data && res.data.message ? res.data.message : (amgAdmin.i18n && amgAdmin.i18n.saveFailed) || 'Error';
                    $msg.css('color', '#b91c1c').text(m).show();
                }
            })
            .fail(function () {
                $msg.css('color', '#b91c1c').text((amgAdmin.i18n && amgAdmin.i18n.saveFailed) || 'Error').show();
            });
    });

    $(document).on('click', '.amg-delete-agent', function (e) {
        e.preventDefault();
        var id = $(this).data('agent-id');
        var company = $(this).data('company') || '';
        if (!id || !window.amgAdmin) return;
        var q = (amgAdmin.i18n && amgAdmin.i18n.confirmDeleteAgent) || 'Delete this agent?';
        if (company) q = q + '\n\n' + company;
        if (!window.confirm(q)) return;

        $.post(amgAdmin.ajaxurl, {
            action: 'agent_management_admin_delete_agent',
            nonce: amgAdmin.nonce,
            agent_id: id,
        })
            .done(function (res) {
                if (res.success) {
                    window.location.reload();
                } else {
                    var m = res.data && res.data.message ? res.data.message : 'Error';
                    alert(m);
                }
            })
            .fail(function () {
                alert('Error');
            });
    });

    $(document).on('click', '.amg-edit-customer', function (e) {
        e.preventDefault();
        var id = $(this).data('customer-id');
        if (!id || !window.amgAdmin) return;

        $.post(amgAdmin.ajaxurl, {
            action: 'agent_management_admin_get_customer',
            nonce: amgAdmin.nonce,
            customer_id: id,
        })
            .done(function (res) {
                if (!res.success || !res.data) {
                    var m = res.data && res.data.message ? res.data.message : (amgAdmin.i18n && amgAdmin.i18n.loadFailed) || 'Error';
                    alert(m);
                    return;
                }
                var c = res.data;
                var tot = c.total_amount != null && c.total_amount !== '' ? String(c.total_amount) : '';
                var dep = c.deposit_amount != null && c.deposit_amount !== '' ? String(c.deposit_amount) : '';

                var html =
                    '<form class="amg-edit-form" id="amg-customer-edit-form">' +
                    '<p class="amg-readonly-email"><strong>Agent:</strong> ' +
                    escAttr(c.company_name || '') +
                    '</p>' +
                    '<label class="amg-label">Customer name</label>' +
                    '<input type="text" name="customer_name" class="amg-input" required value="' +
                    escAttr(c.customer_name || '') +
                    '">' +
                    '<label class="amg-label">Phone</label>' +
                    '<input type="text" name="customer_phone" class="amg-input" required value="' +
                    escAttr(c.customer_phone || '') +
                    '">' +
                    '<label class="amg-label">Passport number</label>' +
                    '<input type="text" name="passport_number" class="amg-input" required value="' +
                    escAttr(c.passport_number || '') +
                    '">' +
                    '<label class="amg-label">Visa country</label>' +
                    '<select name="visa_country" class="amg-select amg-input-select" required>' +
                    countryOptionsHtml(c.visa_country || '') +
                    '</select>' +
                    '<label class="amg-label">Visa type</label>' +
                    '<select name="visa_type" class="amg-select amg-input-select" required>' +
                    visaOptionsHtml(c.visa_type || '') +
                    '</select>' +
                    '<label class="amg-label">Submission date</label>' +
                    '<input type="date" name="submission_date" class="amg-input" required value="' +
                    escAttr(c.submission_date || '') +
                    '">' +
                    '<label class="amg-label">Total amount</label>' +
                    '<input type="number" name="total_amount" class="amg-input" step="0.01" min="0" placeholder="0.00" value="' +
                    escAttr(tot) +
                    '">' +
                    '<label class="amg-label">Deposit amount</label>' +
                    '<input type="number" name="deposit_amount" class="amg-input" step="0.01" min="0" placeholder="0.00" value="' +
                    escAttr(dep) +
                    '">' +
                    '<label class="amg-label">Status</label>' +
                    '<select name="status" class="amg-select amg-input-select">' +
                    statusOptionsHtml(c.status || 'pending') +
                    '</select>' +
                    '<div class="amg-form-actions">' +
                    '<button type="submit" class="amg-btn amg-btn-primary">Save</button>' +
                    '</div>' +
                    '<p class="amg-form-msg" style="display:none;"></p>' +
                    '</form>';

                openFormModal('Edit customer', html);
                $('#amg-customer-edit-form').data('customer-id', id);
            })
            .fail(function () {
                alert((amgAdmin.i18n && amgAdmin.i18n.loadFailed) || 'Error');
            });
    });

    $(document).on('submit', '#amg-customer-edit-form', function (e) {
        e.preventDefault();
        var $f = $(this);
        var customerId = $f.data('customer-id');
        var $msg = $f.find('.amg-form-msg');
        $msg.hide().text('');

        $.post(amgAdmin.ajaxurl, {
            action: 'agent_management_admin_save_customer',
            nonce: amgAdmin.nonce,
            customer_id: customerId,
            customer_name: $f.find('[name="customer_name"]').val(),
            customer_phone: $f.find('[name="customer_phone"]').val(),
            passport_number: $f.find('[name="passport_number"]').val(),
            visa_country: $f.find('[name="visa_country"]').val(),
            visa_type: $f.find('[name="visa_type"]').val(),
            submission_date: $f.find('[name="submission_date"]').val(),
            total_amount: $f.find('[name="total_amount"]').val(),
            deposit_amount: $f.find('[name="deposit_amount"]').val(),
            status: $f.find('[name="status"]').val(),
        })
            .done(function (res) {
                if (res.success) {
                    alert((res.data && res.data.message) || (amgAdmin.i18n && amgAdmin.i18n.saved) || 'OK');
                    closeFormModal();
                    window.location.reload();
                } else {
                    var m = res.data && res.data.message ? res.data.message : (amgAdmin.i18n && amgAdmin.i18n.saveFailed) || 'Error';
                    $msg.css('color', '#b91c1c').text(m).show();
                }
            })
            .fail(function () {
                $msg.css('color', '#b91c1c').text((amgAdmin.i18n && amgAdmin.i18n.saveFailed) || 'Error').show();
            });
    });

    $(document).on('click', '.amg-delete-customer', function (e) {
        e.preventDefault();
        var id = $(this).data('customer-id');
        var name = $(this).data('customer-name') || '';
        if (!id || !window.amgAdmin) return;
        var q = (amgAdmin.i18n && amgAdmin.i18n.confirmDeleteCustomer) || 'Delete this customer?';
        if (name) q = q + '\n\n' + name;
        if (!window.confirm(q)) return;

        $.post(amgAdmin.ajaxurl, {
            action: 'agent_management_admin_delete_customer',
            nonce: amgAdmin.nonce,
            customer_id: id,
        })
            .done(function (res) {
                if (res.success) {
                    window.location.reload();
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Error');
                }
            })
            .fail(function () {
                alert('Error');
            });
    });
})(jQuery);
