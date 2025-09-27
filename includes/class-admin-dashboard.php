<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class AdminDashboard {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_update_agent_status', array($this, 'update_agent_status'));
        add_action('wp_ajax_update_customer_status', array($this, 'update_customer_status'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Agent Management',
            'Agent Management',
            'manage_options',
            'agent-management',
            array($this, 'admin_dashboard_page'),
            'dashicons-groups',
            30
        );
    }

    public function admin_dashboard_page() {
        ?>
        <div class="wrap">
            <h1>Agent Management System</h1>

            <div class="admin-tabs">
                <h2 class="nav-tab-wrapper">
                    <a href="#agents" class="nav-tab nav-tab-active">Agents</a>
                    <a href="#customers" class="nav-tab">Customers</a>
                </h2>

                <div id="agents" class="tab-content active">
                    <h3>Registered Agents</h3>
                    <?php $this->display_agents_table(); ?>
                </div>

                <div id="customers" class="tab-content">
                    <h3>All Customers</h3>
                    <?php $this->display_customers_table(); ?>
                </div>
            </div>
        </div>

        <style>
            .admin-tabs .tab-content { display: none; }
            .admin-tabs .tab-content.active { display: block; }
            .widefat { margin-top: 20px; }
            .status-pending { color: #ffb900; }
            .status-approved { color: #46b450; }
            .status-rejected { color: #dc3232; }
        </style>

        <script>
            jQuery(document).ready(function($) {
                $('.nav-tab').click(function(e) {
                    e.preventDefault();
                    $('.nav-tab').removeClass('nav-tab-active');
                    $('.tab-content').removeClass('active');

                    $(this).addClass('nav-tab-active');
                    $($(this).attr('href')).addClass('active');
                });
            });
        </script>
        <?php
    }

    private function display_agents_table() {
        global $wpdb;
        $agents_table = $wpdb->prefix . 'agents';
        $users_table = $wpdb->prefix . 'users';

        $agents = $wpdb->get_results("
            SELECT a.*, u.user_email, u.user_login 
            FROM $agents_table a 
            LEFT JOIN $users_table u ON a.user_id = u.ID 
            ORDER BY a.created_at DESC
        ");

        echo '<table class="widefat fixed striped">';
        echo '<thead>
            <tr>
                <th>Company</th>
                <th>Email</th>
                <th>Phone</th>
                <th>License No.</th>
                <th>Status</th>
                <th>Registration Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($agents as $agent) {
            echo '<tr>
                <td>' . esc_html($agent->company_name) . '</td>
                <td>' . esc_html($agent->user_email) . '</td>
                <td>' . esc_html($agent->phone) . '</td>
                <td>' . esc_html($agent->license_number) . '</td>
                <td><span class="status-' . esc_attr($agent->status) . '">' . esc_html($agent->status) . '</span></td>
                <td>' . esc_html($agent->created_at) . '</td>
                <td>
                    <select onchange="updateAgentStatus(' . $agent->id . ', this.value)">
                        <option value="pending" ' . selected($agent->status, 'pending', false) . '>Pending</option>
                        <option value="approved" ' . selected($agent->status, 'approved', false) . '>Approve</option>
                        <option value="rejected" ' . selected($agent->status, 'rejected', false) . '>Reject</option>
                    </select>
                </td>
            </tr>';
        }

        echo '</tbody></table>';
        ?>
        <script>
            function updateAgentStatus(agentId, status) {
                jQuery.post(ajaxurl, {
                    action: 'update_agent_status',
                    agent_id: agentId,
                    status: status,
                    nonce: '<?php echo wp_create_nonce('update_agent_status'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Status updated successfully');
                        location.reload();
                    } else {
                        alert('Error updating status');
                    }
                });
            }
        </script>
        <?php
    }

    private function display_customers_table() {
        global $wpdb;
        $customers_table = $wpdb->prefix . 'agent_customers';
        $agents_table = $wpdb->prefix . 'agents';
        $users_table = $wpdb->prefix . 'users';

        $customers = $wpdb->get_results("
            SELECT c.*, a.company_name, u.user_email 
            FROM $customers_table c 
            LEFT JOIN $agents_table a ON c.agent_id = a.id 
            LEFT JOIN $users_table u ON a.user_id = u.ID 
            ORDER BY c.created_at DESC
        ");

        echo '<table class="widefat fixed striped">';
        echo '<thead>
            <tr>
                <th>Customer Name</th>
                <th>Phone</th>
                <th>Passport No.</th>
                <th>Visa Country</th>
                <th>Agent</th>
                <th>Submission Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($customers as $customer) {
            echo '<tr>
                <td>' . esc_html($customer->customer_name) . '</td>
                <td>' . esc_html($customer->customer_phone) . '</td>
                <td>' . esc_html($customer->passport_number) . '</td>
                <td>' . esc_html($customer->visa_country) . '</td>
                <td>' . esc_html($customer->company_name) . '</td>
                <td>' . esc_html($customer->submission_date) . '</td>
                <td><span class="status-' . esc_attr($customer->status) . '">' . esc_html($customer->status) . '</span></td>
                <td>
                    <select onchange="updateCustomerStatus(' . $customer->id . ', this.value)">
                        <option value="pending" ' . selected($customer->status, 'pending', false) . '>Pending</option>
                        <option value="approved" ' . selected($customer->status, 'approved', false) . '>Approve</option>
                        <option value="rejected" ' . selected($customer->status, 'rejected', false) . '>Reject</option>
                    </select>
                </td>
            </tr>';
        }

        echo '</tbody></table>';
        ?>
        <script>
            function updateCustomerStatus(customerId, status) {
                jQuery.post(ajaxurl, {
                    action: 'update_customer_status',
                    customer_id: customerId,
                    status: status,
                    nonce: '<?php echo wp_create_nonce('update_customer_status'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Status updated successfully');
                        location.reload();
                    } else {
                        alert('Error updating status');
                    }
                });
            }
        </script>
        <?php
    }

    public function update_agent_status() {
        check_ajax_referer('update_agent_status', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }

        global $wpdb;
        $agents_table = $wpdb->prefix . 'agents';

        $wpdb->update($agents_table,
            array('status' => sanitize_text_field($_POST['status'])),
            array('id' => intval($_POST['agent_id']))
        );

        wp_send_json_success();
    }

    public function update_customer_status() {
        check_ajax_referer('update_customer_status', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }

        global $wpdb;
        $customers_table = $wpdb->prefix . 'agent_customers';

        $wpdb->update($customers_table,
            array('status' => sanitize_text_field($_POST['status'])),
            array('id' => intval($_POST['customer_id']))
        );

        wp_send_json_success();
    }
}
?>