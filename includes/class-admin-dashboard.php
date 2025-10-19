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
                    <p class="agent-status-update" style="display: none">Agent Status Update...</p>
                    <?php $this->display_agents_table(); ?>
                </div>

                <div id="customers" class="tab-content">
                    <h3>All Customers</h3>
                    <p class="customer-status-update" style="display: none">Customer Status Update...</p>
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
            .pagination { margin-top: 15px; text-align: center; }
            .pagination a, .pagination span {
                display: inline-block;
                padding: 5px 10px;
                margin: 0 2px;
                border: 1px solid #ccc;
                text-decoration: none;
            }
            .pagination a:hover { background-color: #f0f0f0; }
            .pagination .current { background-color: #0073aa; color: white; border-color: #0073aa; }
            .agent-status-update, .customer-status-update{
                width: 90%;
                padding: 10px;
                border-radius: 5px;
                background: #d1ecf1;
                color: #0c5460;
                border: 1px solid #bee5eb;
            }
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

        // Pagination parameters
        $per_page = 50;
        $current_page = isset($_GET['agents_page']) ? max(1, intval($_GET['agents_page'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Get total count
        $total_agents = $wpdb->get_var("SELECT COUNT(*) FROM $agents_table");
        $total_pages = ceil($total_agents / $per_page);

        // Get paginated results
        $agents = $wpdb->get_results($wpdb->prepare("
            SELECT a.*, u.user_email, u.user_login 
            FROM $agents_table a 
            LEFT JOIN $users_table u ON a.user_id = u.ID 
            ORDER BY a.created_at DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset));

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

        if (empty($agents)) {
            echo '<tr><td colspan="7" style="text-align: center;">No agents found.</td></tr>';
        } else {
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
        }

        echo '</tbody></table>';

        // Display pagination
        $this->display_pagination($current_page, $total_pages, 'agents_page');
        ?>
        <script>
            function updateAgentStatus(agentId, status) {
                jQuery('.agent-status-update').show();
                jQuery.post(ajaxurl, {
                    action: 'update_agent_status',
                    agent_id: agentId,
                    status: status,
                    nonce: '<?php echo wp_create_nonce('update_agent_status'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Status updated successfully');
                        location.reload();
                        jQuery('.agent-status-update').hide();
                    } else {
                        alert('Error updating status');
                        jQuery('.agent-status-update').hide();
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

        // Pagination parameters
        $per_page = 50;
        $current_page = isset($_GET['customers_page']) ? max(1, intval($_GET['customers_page'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Get total count
        $total_customers = $wpdb->get_var("SELECT COUNT(*) FROM $customers_table");
        $total_pages = ceil($total_customers / $per_page);

        // Get paginated results
        $customers = $wpdb->get_results($wpdb->prepare("
            SELECT c.*, a.company_name, u.user_email 
            FROM $customers_table c 
            LEFT JOIN $agents_table a ON c.agent_id = a.id 
            LEFT JOIN $users_table u ON a.user_id = u.ID 
            ORDER BY c.created_at DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset));

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

        if (empty($customers)) {
            echo '<tr><td colspan="8" style="text-align: center;">No customers found.</td></tr>';
        } else {
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
        }

        echo '</tbody></table>';

        // Display pagination
        $this->display_pagination($current_page, $total_pages, 'customers_page');
        ?>
        <script>
            function updateCustomerStatus(customerId, status) {
                jQuery('.customer-status-update').show();
                jQuery.post(ajaxurl, {
                    action: 'update_customer_status',
                    customer_id: customerId,
                    status: status,
                    nonce: '<?php echo wp_create_nonce('update_customer_status'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Status updated successfully');
                        location.reload();
                        jQuery('.customer-status-update').hide();
                    } else {
                        alert('Error updating status');
                        jQuery('.customer-status-update').hide();
                    }
                });
            }
        </script>
        <?php
    }

    private function display_pagination($current_page, $total_pages, $page_param) {
        if ($total_pages <= 1) return;

        echo '<div class="pagination">';

        // Previous button
        if ($current_page > 1) {
            echo '<a href="' . esc_url(add_query_arg($page_param, $current_page - 1)) . '">&laquo; Previous</a>';
        }

        // Page numbers
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == $current_page) {
                echo '<span class="current">' . $i . '</span>';
            } else {
                echo '<a href="' . esc_url(add_query_arg($page_param, $i)) . '">' . $i . '</a>';
            }
        }

        // Next button
        if ($current_page < $total_pages) {
            echo '<a href="' . esc_url(add_query_arg($page_param, $current_page + 1)) . '">Next &raquo;</a>';
        }

        echo '</div>';
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