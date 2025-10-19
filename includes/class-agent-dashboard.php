<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class AgentDashboard {

    public function __construct() {
        add_shortcode('agent_dashboard', array($this, 'dashboard_shortcode'));
        add_action('wp_ajax_submit_customer_form', array($this, 'handle_customer_submission'));
        add_action('wp_ajax_get_customer_list', array($this, 'handle_get_customer_list'));
        add_action('wp_ajax_load_more_customers', array($this, 'handle_load_more_customers'));
        add_action('wp_ajax_get_customer_list_paginated', array($this, 'handle_get_customer_list_paginated'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function dashboard_shortcode() {
        if (!is_user_logged_in() || !current_user_can('agent')) {
            return '<p>Please login as an agent to access the dashboard.</p>';
        }

        // Enqueue WooCommerce lightbox scripts


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

                    <div class="image-form-group">
                        <div class="form-group">
                            <label>Passport Image *</label>
                            <input type="file" name="passport_image" accept="image/*" required>
                        </div>

                        <div class="multiple-image-upload-section">
                            <!-- Additional images will be added here dynamically -->
                        </div>

                        <button type="button" id="add-image-btn">+ Add Another Image</button>
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
                            <th>Images</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php echo $this->get_customer_list(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function get_customer_list() {
        $result = $this->get_customer_list_paginated(1, 5);
        return $result['html'];
    }

    public function handle_customer_submission() {
        check_ajax_referer('customer_form_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('agent')) {
            wp_send_json_error('Access denied');
        }

        global $wpdb;
        $current_user_id = get_current_user_id();
        $agents_table = $wpdb->prefix . 'agents';
        $customers_table = $wpdb->prefix . 'agent_customers';
        $customer_images_table = $wpdb->prefix . 'agent_customer_images';

        // Get agent
        $agent = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $agents_table WHERE user_id = %d", $current_user_id
        ));

        if (!$agent) {
            wp_send_json_error('Agent not found');
        }

        // Validate required fields
        $required_fields = array(
            'customer_name',
            'customer_phone',
            'passport_number',
            'visa_country',
            'visa_type',
            'submission_date'
        );

        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error('Please fill all required fields');
            }
        }

        // Handle main passport image upload
        if (empty($_FILES['passport_image']) || $_FILES['passport_image']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Passport image is required');
        }

        // Upload passport image to WordPress media library
        $passport_attachment_id = $this->upload_to_media_library($_FILES['passport_image']);
        if (is_wp_error($passport_attachment_id) || !$passport_attachment_id) {
            error_log('Passport upload error: ' . $passport_attachment_id->get_error_message());
            wp_send_json_error('Passport image upload failed: ' . $passport_attachment_id->get_error_message());
        }

        $passport_image_url = wp_get_attachment_url($passport_attachment_id);

        if (!$passport_image_url) {
            wp_send_json_error('Failed to get passport image URL');
        }

        // Insert customer data
        $customer_data = array(
            'agent_id' => $agent->id,
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_phone' => sanitize_text_field($_POST['customer_phone']),
            'passport_number' => sanitize_text_field($_POST['passport_number']),
            'passport_image' => $passport_image_url,
            'visa_country' => sanitize_text_field($_POST['visa_country']),
            'visa_type' => sanitize_text_field($_POST['visa_type']),
            'submission_date' => sanitize_text_field($_POST['submission_date']),
            'created_at' => current_time('mysql')
        );

        $result = $wpdb->insert($customers_table, $customer_data);

        if ($result === false) {
            wp_send_json_error('Failed to save customer information: ' . $wpdb->last_error);
        }

        $customer_id = $wpdb->insert_id;

        // Handle additional images
        if (!empty($_FILES['additional_images'])) {
            $additional_images = $_FILES['additional_images'];

            // Check if it's multiple files
            if (is_array($additional_images['name'])) {
                foreach ($additional_images['name'] as $key => $name) {
                    if ($additional_images['error'][$key] === UPLOAD_ERR_OK) {
                        $file = array(
                            'name' => $name,
                            'type' => $additional_images['type'][$key],
                            'tmp_name' => $additional_images['tmp_name'][$key],
                            'error' => $additional_images['error'][$key],
                            'size' => $additional_images['size'][$key]
                        );

                        $attachment_id = $this->upload_to_media_library($file);

                        if (!is_wp_error($attachment_id) && $attachment_id) {
                            $wpdb->insert($customer_images_table, array(
                                'customer_id' => $customer_id,
                                'image_url' => wp_get_attachment_url($attachment_id),
                                'image_type' => 'additional',
                                'created_at' => current_time('mysql')
                            ));
                        }
                    }
                }
            }
        }

        wp_send_json_success('Customer information submitted successfully');
    }

    // Helper function to upload files to WordPress media library
    private function upload_to_media_library($file) {
        // Check if required functions are available
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        // Check file upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'File upload error: ' . $file['error']);
        }

        // Validate file type
        $file_type = wp_check_filetype($file['name']);
        if (!$file_type['type']) {
            return new WP_Error('invalid_file_type', 'Invalid file type');
        }

        // Validate file size (5MB limit)
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', 'File size exceeds 5MB limit');
        }

        // Prepare file for upload
        $upload = wp_handle_upload($file, array('test_form' => false));

        if (isset($upload['error'])) {
            return new WP_Error('upload_failed', $upload['error']);
        }

        // Create attachment post
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($upload['file'])),
            'post_content' => '',
            'post_status' => 'inherit',
            'guid' => $upload['url']
        );

        $attachment_id = wp_insert_attachment($attachment, $upload['file']);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Generate attachment metadata
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        return $attachment_id;
    }

    // Add this new method
    public function handle_get_customer_list() {
        check_ajax_referer('agent_auth_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('agent')) {
            wp_send_json_error('Access denied');
        }
        wp_send_json_success($this->get_customer_list());
    }

    // Add this method to handle paginated customer list
    public function get_customer_list_paginated($page = 1, $per_page = 5) {
        if (!is_user_logged_in() || !current_user_can('agent')) {
            return array(
                'html' => '<tr><td colspan="7">Access denied</td></tr>',
                'has_more' => false
            );
        }

        global $wpdb;
        $current_user_id = get_current_user_id();
        $agents_table = $wpdb->prefix . 'agents';
        $customers_table = $wpdb->prefix . 'agent_customers';
        $customer_images_table = $wpdb->prefix . 'agent_customer_images';

        // Get agent
        $agent = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $agents_table WHERE user_id = %d", $current_user_id
        ));

        if (!$agent) {
            return array(
                'html' => '<tr><td colspan="7">Agent not found</td></tr>',
                'has_more' => false
            );
        }

        // Calculate offset
        $offset = ($page - 1) * $per_page;

        // Get total count
        $total_customers = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $customers_table WHERE agent_id = %d", $agent->id
        ));

        // Get paginated customers
        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $customers_table 
         WHERE agent_id = %d 
         ORDER BY created_at DESC 
         LIMIT %d OFFSET %d",
            $agent->id, $per_page, $offset
        ));

        if (empty($customers)) {
            return array(
                'html' => '<tr><td colspan="7">No customers found</td></tr>',
                'has_more' => false
            );
        }

        $output = '';

        foreach ($customers as $customer) {
            // Get additional images for this customer
            $additional_images = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $customer_images_table WHERE customer_id = %d", $customer->id
            ));

            $images_html = '';

            if (!empty($additional_images) || $customer->passport_image) {
                $images_html = '<div class="customer-images">';

                // Add passport image
                if ($customer->passport_image) {
                    $images_html .= '<a href="' . esc_url($customer->passport_image) . '" rel="prettyPhoto[gallery_' . $customer->id . ']" title="Passport Image">';
                    $images_html .= '<img src="' . esc_url($customer->passport_image) . '" width="50" height="50" style="object-fit: cover; margin: 2px;">';
                    $images_html .= '</a>';
                }

                // Add additional images
                foreach ($additional_images as $image) {
                    $images_html .= '<a href="' . esc_url($image->image_url) . '" rel="prettyPhoto[gallery_' . $customer->id . ']" title="Additional Image">';
                    $images_html .= '<img src="' . esc_url($image->image_url) . '" width="50" height="50" style="object-fit: cover; margin: 2px;">';
                    $images_html .= '</a>';
                }

                $images_html .= '</div>';
            } else {
                $images_html = 'No images';
            }

            $output .= '<tr>
            <td>' . esc_html($customer->customer_name) . '</td>
            <td>' . esc_html($customer->customer_phone) . '</td>
            <td>' . esc_html($customer->passport_number) . '</td>
            <td>' . esc_html($customer->visa_country) . '</td>
            <td>' . esc_html($customer->submission_date) . '</td>
            <td>' . esc_html($customer->status) . '</td>
            <td>' . $images_html . '</td>
        </tr>';
        }

        $has_more = ($total_customers > ($offset + $per_page));

        return array(
            'html' => $output,
            'has_more' => $has_more,
            'total' => $total_customers
        );
    }


    // Add AJAX handler for loading more customers
    public function handle_load_more_customers() {
        check_ajax_referer('agent_auth_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('agent')) {
            wp_send_json_error('Access denied');
        }

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 5;

        $result = $this->get_customer_list_paginated($page, $per_page);

        wp_send_json_success($result);
    }

    // Add this method to handle paginated customer list requests
    public function handle_get_customer_list_paginated() {
        check_ajax_referer('agent_auth_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('agent')) {
            wp_send_json_error('Access denied');
        }

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 5;

        $result = $this->get_customer_list_paginated($page, $per_page);
        wp_send_json_success($result);
    }

    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('agent-scripts', AGENT_MANAGEMENT_PLUGIN_URL . 'assets/agent-scripts.js', array('jquery'), '1.0', true);
        wp_enqueue_style('agent-styles', AGENT_MANAGEMENT_PLUGIN_URL . 'assets/agent-styles.css');

        // Enqueue WooCommerce lightbox scripts if WooCommerce is active
        if (class_exists('WooCommerce')) {
            wp_enqueue_script('prettyPhoto', WC()->plugin_url() . '/assets/js/prettyPhoto/jquery.prettyPhoto.min.js', array('jquery'), '1.0.0', true);
            wp_enqueue_script('prettyPhoto-init', WC()->plugin_url() . '/assets/js/prettyPhoto/jquery.prettyPhoto.init.min.js', array('jquery', 'prettyPhoto'), '1.0.0', true);
            wp_enqueue_style('woocommerce_prettyPhoto_css', WC()->plugin_url() . '/assets/css/prettyPhoto.css');
        } else {
            // Fallback: Enqueue prettyPhoto from CDN if WooCommerce is not available
            wp_enqueue_script('prettyPhoto', 'https://cdnjs.cloudflare.com/ajax/libs/prettyPhoto/3.1.6/js/jquery.prettyPhoto.min.js', array('jquery'), '3.1.6', true);
            wp_enqueue_style('prettyPhoto-css', 'https://cdnjs.cloudflare.com/ajax/libs/prettyPhoto/3.1.6/css/prettyPhoto.min.css');
        }

        wp_localize_script('agent-scripts', 'agent_dashboard_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('agent_auth_nonce')
        ));
    }
}