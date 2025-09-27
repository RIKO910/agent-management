<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class AgentDashboard {

    public function __construct() {
        add_shortcode('agent_dashboard', array($this, 'dashboard_shortcode'));
        add_action('wp_ajax_submit_customer_form', array($this, 'handle_customer_submission'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('agent-scripts', AGENT_MANAGEMENT_PLUGIN_URL . 'assets/agent-scripts.js', array('jquery'), '1.0', true);
        wp_enqueue_style('agent-styles', AGENT_MANAGEMENT_PLUGIN_URL . 'assets/agent-styles.css');

        wp_localize_script('agent-scripts', 'agent_dashboard_ajax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('agent_auth_nonce')
        ));
    }

    public function dashboard_shortcode() {
        if (!is_user_logged_in() || !current_user_can('agent')) {
            return '<p>Please login as an agent to access the dashboard.</p>';
        }

        ob_start();
        ?>
        <div class="agent-dashboard">
            <h2>Agent Dashboard</h2>

            <div class="dashboard-tabs">
                <button class="tab-button active" data-tab="customer-form">Add Customer</button>
                <button class="tab-button" data-tab="customer-list">Customer List</button>
            </div>

            <div id="customer-form" class="tab-content active">
                <h3>Customer Information Form</h3>
                <form id="customerForm" enctype="multipart/form-data">
                    <?php wp_nonce_field('customer_form_nonce', 'nonce'); ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Customer Name *</label>
                            <input type="text" name="customer_name" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number *</label>
                            <input type="text" name="customer_phone" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Passport Number *</label>
                            <input type="text" name="passport_number" required>
                        </div>
                        <div class="form-group">
                            <label>Visa Country *</label>
                            <input type="text" name="visa_country" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Visa Type *</label>
                            <select name="visa_type" required>
                                <option value="">Select Visa Type</option>
                                <option value="tourist">Tourist</option>
                                <option value="student">Student</option>
                                <option value="work">Work</option>
                                <option value="business">Business</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Submission Date *</label>
                            <input type="date" name="submission_date" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Passport Image *</label>
                        <input type="file" name="passport_image" accept="image/*" required>
                    </div>

                    <button type="submit">Submit Customer Information</button>
                </form>
                <div id="formMessage"></div>
            </div>

            <div id="customer-list" class="tab-content">
                <h3>Customer List</h3>
                <div class="customer-table-container">
                    <table class="customer-table">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Passport No.</th>
                            <th>Visa Country</th>
                            <th>Submission Date</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php echo $this->get_customer_list(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('.tab-button').click(function() {
                    $('.tab-button').removeClass('active');
                    $('.tab-content').removeClass('active');

                    $(this).addClass('active');
                    $('#' + $(this).data('tab')).addClass('active');
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }

    private function get_customer_list() {
        global $wpdb;
        $current_user_id = get_current_user_id();
        $agents_table = $wpdb->prefix . 'agents';
        $customers_table = $wpdb->prefix . 'agent_customers';

        $agent = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $agents_table WHERE user_id = %d", $current_user_id
        ));

        if (!$agent) return '<tr><td colspan="6">No agent data found</td></tr>';

        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $customers_table WHERE agent_id = %d ORDER BY created_at DESC", $agent->id
        ));

        if (empty($customers)) {
            return '<tr><td colspan="6">No customers found</td></tr>';
        }

        $html = '';
        foreach ($customers as $customer) {
            $html .= '<tr>
                <td>' . esc_html($customer->customer_name) . '</td>
                <td>' . esc_html($customer->customer_phone) . '</td>
                <td>' . esc_html($customer->passport_number) . '</td>
                <td>' . esc_html($customer->visa_country) . '</td>
                <td>' . esc_html($customer->submission_date) . '</td>
                <td><span class="status-' . esc_attr($customer->status) . '">' . esc_html($customer->status) . '</span></td>
            </tr>';
        }

        return $html;
    }

    public function handle_customer_submission() {
        check_ajax_referer('customer_form_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('agent')) {
            wp_send_json_error('Access denied');
        }

        // Handle file upload
        if (!empty($_FILES['passport_image'])) {
            $upload = wp_upload_bits($_FILES['passport_image']['name'], null, file_get_contents($_FILES['passport_image']['tmp_name']));

            if ($upload['error']) {
                wp_send_json_error('File upload failed');
            }

            $passport_image = $upload['url'];
        }

        global $wpdb;
        $current_user_id = get_current_user_id();
        $agents_table = $wpdb->prefix . 'agents';
        $customers_table = $wpdb->prefix . 'agent_customers';

        $agent = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $agents_table WHERE user_id = %d", $current_user_id
        ));

        if (!$agent) {
            wp_send_json_error('Agent not found');
        }

        $wpdb->insert($customers_table, array(
            'agent_id' => $agent->id,
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_phone' => sanitize_text_field($_POST['customer_phone']),
            'passport_number' => sanitize_text_field($_POST['passport_number']),
            'passport_image' => $passport_image,
            'visa_country' => sanitize_text_field($_POST['visa_country']),
            'visa_type' => sanitize_text_field($_POST['visa_type']),
            'submission_date' => sanitize_text_field($_POST['submission_date'])
        ));

        wp_send_json_success('Customer information submitted successfully');
    }
}
?>