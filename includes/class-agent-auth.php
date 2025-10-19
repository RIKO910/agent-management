<?php

class AgentAuth {

    public function __construct() {
        add_shortcode('agent_login', array($this, 'login_shortcode'));
        add_action('wp_ajax_agent_login', array($this, 'handle_login'));
        add_action('wp_ajax_nopriv_agent_login', array($this, 'handle_login'));
        add_action('wp_ajax_agent_signup', array($this, 'handle_signup'));
        add_action('wp_ajax_nopriv_agent_signup', array($this, 'handle_signup'));
    }

    public function login_shortcode() {
        if (is_user_logged_in() && $this->is_agent()) {
            return do_shortcode('[agent_dashboard]');
        }

        ob_start();
        ?>
        <div class="agent-login-form">
            <h3>Agent Login</h3>
            <form id="agentLoginForm">
                <?php wp_nonce_field('agent_auth_nonce', 'login_nonce'); ?>
                <div class="form-group">
                    <label>Username/Email</label>
                    <input type="text" name="login_username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="login_password" required>
                </div>
                <button type="submit">Login</button>
            </form>
            <div id="loginMessage"></div>
            <p>Don't have an account? <a href="#" onclick="showSignup()">Sign up here</a></p>
        </div>

        <div class="agent-signup-form" style="display:none">
            <h3>Agent Sign Up</h3>
            <form id="agentSignupForm">
                <?php wp_nonce_field('agent_auth_nonce', 'signup_nonce'); ?>
                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text" name="sign_company_name" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="sign_username" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="sign_email" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="sign_phone" required>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="sign_address" required></textarea>
                </div>
                <div class="form-group">
                    <label>License Number</label>
                    <input type="text" name="sign_license_number" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="sign_password" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="sign_confirm_password" required>
                </div>
                <button type="button" id="agent-create-submit">Sign Up</button>
            </form>
            <div id="signupMessage"></div>
            <p>Already have an account? <a href="#" onclick="showLogin()">Login here</a></p>
        </div>

        <script>
            function showSignup() {
                jQuery('.agent-login-form').hide();
                jQuery('.agent-signup-form').show();
            }
            function showLogin() {
                jQuery('.agent-signup-form').hide();
                jQuery('.agent-login-form').show();
            }
        </script>
        <?php
        return ob_get_clean();
    }

    public function handle_login() {
        global $wpdb;
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'agent_auth_nonce')) {
            wp_send_json_error('Security verification failed');
        }

        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];

        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            wp_send_json_error('Invalid credentials');
        }

        // Check if user has agent role or is admin
        if (!$this->is_agent($user) && !user_can($user, 'administrator')) {
            wp_send_json_error('Access denied. Agent role required.');
        }


        // For agents (non-admins), check their status in the agents table
        if ($this->is_agent($user) && !user_can($user, 'administrator')) {
            $agents_table = $wpdb->prefix . 'agents';

            $agent_status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM $agents_table WHERE user_id = %d",
                $user->ID
            ));

            // If no record found or status not approved, deny access
            if (!$agent_status || $agent_status !== 'approved') {
                wp_send_json_error('Your agent account is pending approval. Please contact administrator.');
            }
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);

        wp_send_json_success('Login successful');
    }

    public function handle_signup() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'agent_auth_nonce')) {
            wp_send_json_error('Security verification failed');
        }

        // Validate passwords match
        if ($_POST['password'] !== $_POST['confirm_password']) {
            wp_send_json_error('Passwords do not match.');
        }

        $userdata = array(
            'user_login' => sanitize_text_field($_POST['username']),
            'user_email' => sanitize_email($_POST['email']),
            'user_pass' => $_POST['password'],
            'role' => 'agent' // This should now work with our created role
        );

        $user_id = wp_insert_user($userdata);

        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }

        // Save agent meta
        global $wpdb;
        $agents_table = $wpdb->prefix . 'agents';

        $result = $wpdb->insert($agents_table, array(
            'user_id' => $user_id,
            'company_name' => sanitize_text_field($_POST['company_name']),
            'phone' => sanitize_text_field($_POST['phone']),
            'address' => sanitize_textarea_field($_POST['address']),
            'license_number' => sanitize_text_field($_POST['license_number'])
        ));

        if ($result === false) {
            wp_send_json_error('Failed to save agent information.');
        }

        wp_send_json_success('Registration successful. Please wait for admin approval.');
    }

    private function is_agent($user = null) {
        if (!$user) {
            $user = wp_get_current_user();
        }

        return in_array('agent', (array) $user->roles);
    }
}
?>