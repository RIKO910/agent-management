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
})(jQuery);
