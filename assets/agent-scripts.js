jQuery(document).ready(function($) {
    // Login form handling
    $('#agentLoginForm').on('submit', function(e) {
        e.preventDefault();

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
            }
        }).fail(function() {
            $('#loginMessage').html('<div class="error">Login failed. Please try again.</div>');
        });
    });

    // Signup form handling
    $('#agent-create-submit').on('click', function(e) {
        e.preventDefault();

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
                }, 2000);
            } else {
                $('#signupMessage').html('<div class="error">' + response.data + '</div>');
            }
        }).fail(function() {
            $('#signupMessage').html('<div class="error">Registration failed. Please try again.</div>');
        });
    });
});