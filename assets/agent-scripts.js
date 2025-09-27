jQuery(document).ready(function($) {
    // Login form handling
    $('#agentLoginForm').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();

        $.post(agent_dashboard_ajax.ajaxurl, {
            action: 'agent_login',
            nonce: agent_dashboard_ajax.nonce,
            ...formData
        }, function(response) {
            if (response.success) {
                $('#loginMessage').html('<div class="success">' + response.data + '</div>');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                $('#loginMessage').html('<div class="error">' + response.data + '</div>');
            }
        });
    });

    // Signup form handling
    $('#agent-create-submit').on('click', function(e) {
        e.preventDefault();

        $.post(agent_dashboard_ajax.ajaxurl, {
            action: 'agent_signup',
            nonce: agent_dashboard_ajax.nonce,
            company_name:()
        }, function(response) {
            if (response.success) {
                $('#signupMessage').html('<div class="success">' + response.data + '</div>');
            } else {
                $('#signupMessage').html('<div class="error">' + response.data + '</div>');
            }
        });
    });

    // Customer form handling
    $('#customerForm').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);

        $.ajax({
            url: agent_dashboard_ajax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            data: {
                action: 'submit_customer_form',
                ...Object.fromEntries(formData)
            },
            success: function(response) {
                if (response.success) {
                    $('#formMessage').html('<div class="success">' + response.data + '</div>');
                    $('#customerForm')[0].reset();
                } else {
                    $('#formMessage').html('<div class="error">' + response.data + '</div>');
                }
            }
        });
    });
});