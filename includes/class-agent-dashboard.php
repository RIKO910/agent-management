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
        add_action('wp_ajax_delete_customer', array($this, 'handle_delete_customer'));
        add_action('wp_ajax_get_customer_details', array($this, 'handle_get_customer_details'));
        add_action('wp_ajax_update_customer', array($this, 'handle_update_customer'));
        add_action('wp_ajax_delete_customer_image', array($this, 'handle_delete_customer_image'));
        add_action('wp_ajax_get_customer_countries', array($this, 'handle_get_customer_countries'));
        add_action('wp_ajax_get_customers_by_country', array($this, 'handle_get_customers_by_country'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Get list of all countries
     */
    public function get_countries_list() {
        return array(
            'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua and Barbuda', 'Argentina', 'Armenia',
            'Australia', 'Austria', 'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium',
            'Belize', 'Benin', 'Bhutan', 'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria',
            'Burkina Faso', 'Burundi', 'Cabo Verde', 'Cambodia', 'Cameroon', 'Canada', 'Central African Republic', 'Chad',
            'Chile', 'China', 'Colombia', 'Comoros', 'Congo', 'Costa Rica', 'Croatia', 'Cuba', 'Cyprus', 'Czech Republic',
            'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic', 'Ecuador', 'Egypt', 'El Salvador', 'Equatorial Guinea',
            'Eritrea', 'Estonia', 'Eswatini', 'Ethiopia', 'Fiji', 'Finland', 'France', 'Gabon', 'Gambia', 'Georgia',
            'Germany', 'Ghana', 'Greece', 'Grenada', 'Guatemala', 'Guinea', 'Guinea-Bissau', 'Guyana', 'Haiti', 'Honduras',
            'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy', 'Jamaica', 'Japan',
            'Jordan', 'Kazakhstan', 'Kenya', 'Kiribati', 'Korea, North', 'Korea, South', 'Kosovo', 'Kuwait', 'Kyrgyzstan',
            'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg',
            'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands', 'Mauritania', 'Mauritius',
            'Mexico', 'Micronesia', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar',
            'Namibia', 'Nauru', 'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Macedonia',
            'Norway', 'Oman', 'Pakistan', 'Palau', 'Palestine', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru',
            'Philippines', 'Poland', 'Portugal', 'Qatar', 'Romania', 'Russia', 'Rwanda', 'Saint Kitts and Nevis',
            'Saint Lucia', 'Saint Vincent and the Grenadines', 'Samoa', 'San Marino', 'Sao Tome and Principe',
            'Saudi Arabia', 'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia',
            'Solomon Islands', 'Somalia', 'South Africa', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname',
            'Sweden', 'Switzerland', 'Syria', 'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Timor-Leste', 'Togo',
            'Tonga', 'Trinidad and Tobago', 'Tunisia', 'Turkey', 'Turkmenistan', 'Tuvalu', 'Uganda', 'Ukraine',
            'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay', 'Uzbekistan', 'Vanuatu', 'Vatican City',
            'Venezuela', 'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe'
        );
    }

    public function dashboard_shortcode() {
        if (!is_user_logged_in() || !current_user_can('agent')) {
            return '<p>Please login as an agent to access the dashboard.</p>';
        }

        ob_start();
        $logout_redirect = apply_filters('agent_dashboard_logout_redirect', home_url('/'));
        ?>
        <div class="agent-dashboard">
            <div class="agent-dashboard-header">
                <h2>Agent Dashboard</h2>
                <a href="<?php echo esc_url(wp_logout_url($logout_redirect)); ?>" class="agent-dashboard-logout"><?php esc_html_e('Log out', 'agent-management'); ?></a>
            </div>

            <div class="dashboard-tabs">
                <button class="tab-button active" data-tab="customer-form">Add Customer</button>
                <button class="tab-button" data-tab="customer-list">Customer List</button>
                <button class="tab-button" data-tab="customer-countries">Countries</button>
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
                            <select name="visa_country" required>
                                <option value="">Select Country</option>
                                <?php foreach ($this->get_countries_list() as $country): ?>
                                    <option value="<?php echo esc_attr($country); ?>"><?php echo esc_html($country); ?></option>
                                <?php endforeach; ?>
                            </select>
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

                    <div class="form-buttons" style="display: flex; gap: 10px;">
                        <button type="submit">Submit Customer Information</button>
                        <button type="button" id="cancel-edit-btn" style="display: none; background: #757575;">Cancel Edit</button>
                    </div>
                </form>
                <div id="formMessage"></div>
            </div>

            <div id="customer-list" class="tab-content">
                <h3>Customer List</h3>
                <div class="search-bar-wrapper">
                    <div class="search-input-wrap">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="10" cy="10" r="7"/>
                            <line x1="21" y1="21" x2="15" y2="15"/>
                        </svg>
                        <input type="text" id="customer-search-input" placeholder="Search by name, phone, passport number, or country...">
                    </div>
                    <button type="button" id="search-clear-btn" class="btn-search-clear">Clear</button>
                </div>
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
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr><td colspan="8" style="text-align:center;">Loading customers...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="customer-countries" class="tab-content">
                <h3>Customers by Country</h3>
                <div id="country-tab-content">
                    <div style="text-align:center;padding:40px;">
                        <div class="loading-spinner-large"></div>
                        <p style="margin-top:12px;">Loading countries...</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function get_customer_list() {
        $result = $this->get_customer_list_paginated(1, 10);
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
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'File upload error: ' . $file['error']);
        }

        $file_type = wp_check_filetype($file['name']);
        if (!$file_type['type']) {
            return new WP_Error('invalid_file_type', 'Invalid file type');
        }

        $max_size = 5 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', 'File size exceeds 5MB limit');
        }

        $upload = wp_handle_upload($file, array('test_form' => false));

        if (isset($upload['error'])) {
            return new WP_Error('upload_failed', $upload['error']);
        }

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

        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        return $attachment_id;
    }

    public function handle_get_customer_list() {
        check_ajax_referer('agent_auth_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('agent')) {
            wp_send_json_error('Access denied');
        }
        wp_send_json_success($this->get_customer_list());
    }

    public function get_customer_list_paginated($page = 1, $per_page = 10, $search = '') {
        if (!is_user_logged_in() || !current_user_can('agent')) {
            return array(
                'html' => '<tr><td colspan="8">Access denied</td></tr>',
                'has_more' => false,
                'total' => 0
            );
        }

        global $wpdb;
        $current_user_id = get_current_user_id();
        $agents_table = $wpdb->prefix . 'agents';
        $customers_table = $wpdb->prefix . 'agent_customers';
        $customer_images_table = $wpdb->prefix . 'agent_customer_images';

        $agent = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $agents_table WHERE user_id = %d", $current_user_id
        ));

        if (!$agent) {
            return array(
                'html' => '<tr><td colspan="8">Agent not found</td></tr>',
                'has_more' => false,
                'total' => 0
            );
        }

        $offset = ($page - 1) * $per_page;

        // Build search query
        $search_condition = '';
        $search_params = array($agent->id);

        if (!empty($search)) {
            $search_condition = " AND (customer_name LIKE %s OR customer_phone LIKE %s OR passport_number LIKE %s OR visa_country LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $search_params = array_merge($search_params, array($search_term, $search_term, $search_term, $search_term));
        }

        // Get total count
        $count_query = "SELECT COUNT(*) FROM $customers_table WHERE agent_id = %d" . $search_condition;
        $total_customers = $wpdb->get_var($wpdb->prepare($count_query, $search_params));

        // Get paginated customers
        $query = $wpdb->prepare(
            "SELECT * FROM $customers_table 
             WHERE agent_id = %d" . $search_condition . "
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            array_merge($search_params, array($per_page, $offset))
        );

        $customers = $wpdb->get_results($query);

        if (empty($customers)) {
            return array(
                'html' => '<tr><td colspan="8">No customers found</td></tr>',
                'has_more' => false,
                'total' => 0
            );
        }

        $output = '';

        foreach ($customers as $customer) {
            $additional_images = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $customer_images_table WHERE customer_id = %d", $customer->id
            ));

            $images_html = '';

            if (!empty($additional_images) || $customer->passport_image) {
                $images_html = '<div class="customer-images">';

                if ($customer->passport_image) {
                    $images_html .= '<a href="' . esc_url($customer->passport_image) . '" rel="prettyPhoto[gallery_' . $customer->id . ']" title="Passport Image">';
                    $images_html .= '<img src="' . esc_url($customer->passport_image) . '" width="50" height="50" style="object-fit: cover; margin: 2px;">';
                    $images_html .= '</a>';
                }

                foreach ($additional_images as $image) {
                    $images_html .= '<a href="' . esc_url($image->image_url) . '" rel="prettyPhoto[gallery_' . $customer->id . ']" title="Additional Image">';
                    $images_html .= '<img src="' . esc_url($image->image_url) . '" width="50" height="50" style="object-fit: cover; margin: 2px;">';
                    $images_html .= '</a>';
                }

                $images_html .= '</div>';
            } else {
                $images_html = 'No images';
            }

            $status_class = 'status-' . $customer->status;
            $status_display = ucfirst($customer->status);

            $actions_html = '<div class="customer-actions" style="display: flex; gap: 5px;">';
            $actions_html .= '<button class="edit-customer-btn btn-edit" data-customer-id="' . $customer->id . '">Edit</button>';
            $actions_html .= '<button class="delete-customer-btn btn-delete" data-customer-id="' . $customer->id . '">Delete</button>';
            $actions_html .= '</div>';

            $output .= '<tr>
                <td>' . esc_html($customer->customer_name) . '</td>
                <td>' . esc_html($customer->customer_phone) . '</td>
                <td>' . esc_html($customer->passport_number) . '</td>
                <td>' . esc_html($customer->visa_country) . '</td>
                <td>' . esc_html($customer->submission_date) . '</td>
                <td><span class="' . $status_class . '">' . $status_display . '</span></td>
                <td>' . $images_html . '</td>
                <td>' . $actions_html . '</td>
            </tr>';
        }

        $has_more = ($total_customers > ($offset + $per_page));

        return array(
            'html' => $output,
            'has_more' => $has_more,
            'total' => $total_customers
        );
    }

    public function handle_load_more_customers() {
        check_ajax_referer('agent_auth_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('agent')) {
            wp_send_json_error('Access denied');
        }

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 10;

        $result = $this->get_customer_list_paginated($page, $per_page);

        wp_send_json_success($result);
    }

    public function handle_get_customer_list_paginated() {
        check_ajax_referer('agent_auth_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('agent')) {
            wp_send_json_error('Access denied');
        }

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $result = $this->get_customer_list_paginated($page, $per_page, $search);
        wp_send_json_success($result);
    }

    /**
     * Get customers grouped by country
     */
    public function handle_get_customer_countries() {
        check_ajax_referer('agent_auth_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('agent')) {
            wp_send_json_error('Access denied');
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

        $countries = $wpdb->get_results($wpdb->prepare(
            "SELECT visa_country as country, COUNT(*) as count 
             FROM $customers_table 
             WHERE agent_id = %d 
             GROUP BY visa_country 
             ORDER BY visa_country ASC",
            $agent->id
        ));

        wp_send_json_success(array('countries' => $countries));
    }

    /**
     * Get customers by country with pagination
     */
    public function handle_get_customers_by_country() {
        check_ajax_referer('agent_auth_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('agent')) {
            wp_send_json_error('Access denied');
        }

        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;

        if (empty($country)) {
            wp_send_json_error('Country is required');
        }

        global $wpdb;
        $current_user_id = get_current_user_id();
        $agents_table = $wpdb->prefix . 'agents';
        $customers_table = $wpdb->prefix . 'agent_customers';
        $customer_images_table = $wpdb->prefix . 'agent_customer_images';

        $agent = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $agents_table WHERE user_id = %d", $current_user_id
        ));

        if (!$agent) {
            wp_send_json_error('Agent not found');
        }

        $offset = ($page - 1) * $per_page;

        // Get total count for this country
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $customers_table WHERE agent_id = %d AND visa_country = %s",
            $agent->id, $country
        ));

        // Get customers for this country
        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $customers_table 
             WHERE agent_id = %d AND visa_country = %s 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $agent->id, $country, $per_page, $offset
        ));

        $html = '';
        foreach ($customers as $customer) {
            $additional_images = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $customer_images_table WHERE customer_id = %d", $customer->id
            ));

            $images_html = '<div class="customer-images">';
            if ($customer->passport_image) {
                $images_html .= '<a href="' . esc_url($customer->passport_image) . '" rel="prettyPhoto[gallery_country_' . $customer->id . ']" title="Passport Image">';
                $images_html .= '<img src="' . esc_url($customer->passport_image) . '" width="50" height="50" style="object-fit: cover;">';
                $images_html .= '</a>';
            }
            foreach ($additional_images as $image) {
                $images_html .= '<a href="' . esc_url($image->image_url) . '" rel="prettyPhoto[gallery_country_' . $customer->id . ']" title="Additional Image">';
                $images_html .= '<img src="' . esc_url($image->image_url) . '" width="50" height="50" style="object-fit: cover;">';
                $images_html .= '</a>';
            }
            $images_html .= '</div>';

            $status_class = 'status-' . $customer->status;
            $status_display = ucfirst($customer->status);

            $actions_html = '<div class="customer-actions">';
            $actions_html .= '<button class="edit-customer-btn btn-edit" data-customer-id="' . $customer->id . '">Edit</button>';
            $actions_html .= '<button class="delete-customer-btn btn-delete" data-customer-id="' . $customer->id . '">Delete</button>';
            $actions_html .= '</div>';

            $html .= '<tr>
                <td>' . esc_html($customer->customer_name) . '</td>
                <td>' . esc_html($customer->customer_phone) . '</td>
                <td>' . esc_html($customer->passport_number) . '</td>
                <td>' . esc_html($customer->visa_type) . '</td>
                <td>' . esc_html($customer->submission_date) . '</td>
                <td><span class="' . $status_class . '">' . $status_display . '</span></td>
                <td>' . $images_html . '</td>
                <td>' . $actions_html . '</td>
            </tr>';
        }

        wp_send_json_success(array(
            'html' => $html,
            'total' => $total,
            'total_pages' => ceil($total / $per_page),
            'current_page' => $page
        ));
    }

    public function handle_delete_customer() {
        check_ajax_referer('agent_auth_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('agent')) {
            wp_send_json_error('Access denied');
        }

        if (empty($_POST['customer_id'])) {
            wp_send_json_error('Customer ID is required');
        }

        global $wpdb;
        $customer_id = intval($_POST['customer_id']);
        $current_user_id = get_current_user_id();
        $agents_table = $wpdb->prefix . 'agents';
        $customers_table = $wpdb->prefix . 'agent_customers';
        $customer_images_table = $wpdb->prefix . 'agent_customer_images';

        $agent = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $agents_table WHERE user_id = %d", $current_user_id
        ));

        if (!$agent) {
            wp_send_json_error('Agent not found');
        }

        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $customers_table WHERE id = %d AND agent_id = %d",
            $customer_id, $agent->id
        ));

        if (!$customer) {
            wp_send_json_error('Customer not found or access denied');
        }

        $wpdb->delete($customer_images_table, array('customer_id' => $customer_id));
        $result = $wpdb->delete($customers_table, array('id' => $customer_id));

        if ($result === false) {
            wp_send_json_error('Failed to delete customer');
        }

        wp_send_json_success('Customer deleted successfully');
    }

    public function handle_get_customer_details() {
        check_ajax_referer('agent_auth_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('agent')) {
            wp_send_json_error('Access denied');
        }

        if (empty($_POST['customer_id'])) {
            wp_send_json_error('Customer ID is required');
        }

        global $wpdb;
        $customer_id = intval($_POST['customer_id']);
        $current_user_id = get_current_user_id();
        $agents_table = $wpdb->prefix . 'agents';
        $customers_table = $wpdb->prefix . 'agent_customers';
        $customer_images_table = $wpdb->prefix . 'agent_customer_images';

        $agent = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $agents_table WHERE user_id = %d", $current_user_id
        ));

        if (!$agent) {
            wp_send_json_error('Agent not found');
        }

        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $customers_table WHERE id = %d AND agent_id = %d",
            $customer_id, $agent->id
        ));

        if (!$customer) {
            wp_send_json_error('Customer not found or access denied');
        }

        $additional_images = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $customer_images_table WHERE customer_id = %d",
            $customer_id
        ));

        $customer->additional_images = $additional_images;

        wp_send_json_success($customer);
    }

    public function handle_update_customer() {
        check_ajax_referer('customer_form_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('agent')) {
            wp_send_json_error('Access denied');
        }

        if (empty($_POST['customer_id'])) {
            wp_send_json_error('Customer ID is required');
        }

        global $wpdb;
        $customer_id = intval($_POST['customer_id']);
        $current_user_id = get_current_user_id();
        $agents_table = $wpdb->prefix . 'agents';
        $customers_table = $wpdb->prefix . 'agent_customers';
        $customer_images_table = $wpdb->prefix . 'agent_customer_images';

        $agent = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $agents_table WHERE user_id = %d", $current_user_id
        ));

        if (!$agent) {
            wp_send_json_error('Agent not found');
        }

        $existing_customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $customers_table WHERE id = %d AND agent_id = %d",
            $customer_id, $agent->id
        ));

        if (!$existing_customer) {
            wp_send_json_error('Customer not found or access denied');
        }

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

        $customer_data = array(
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_phone' => sanitize_text_field($_POST['customer_phone']),
            'passport_number' => sanitize_text_field($_POST['passport_number']),
            'visa_country' => sanitize_text_field($_POST['visa_country']),
            'visa_type' => sanitize_text_field($_POST['visa_type']),
            'submission_date' => sanitize_text_field($_POST['submission_date'])
        );

        if (!empty($_FILES['passport_image']) && $_FILES['passport_image']['error'] === UPLOAD_ERR_OK) {
            $passport_attachment_id = $this->upload_to_media_library($_FILES['passport_image']);
            if (!is_wp_error($passport_attachment_id) && $passport_attachment_id) {
                $passport_image_url = wp_get_attachment_url($passport_attachment_id);
                if ($passport_image_url) {
                    $customer_data['passport_image'] = $passport_image_url;
                }
            }
        }

        $result = $wpdb->update(
            $customers_table,
            $customer_data,
            array('id' => $customer_id)
        );

        if ($result === false) {
            wp_send_json_error('Failed to update customer: ' . $wpdb->last_error);
        }

        if (!empty($_FILES['additional_images'])) {
            $additional_images = $_FILES['additional_images'];

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

        wp_send_json_success('Customer information updated successfully');
    }

    public function handle_delete_customer_image() {
        check_ajax_referer('agent_auth_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('agent')) {
            wp_send_json_error('Access denied');
        }

        if (empty($_POST['image_id'])) {
            wp_send_json_error('Image ID is required');
        }

        global $wpdb;
        $image_id = intval($_POST['image_id']);
        $current_user_id = get_current_user_id();
        $agents_table = $wpdb->prefix . 'agents';
        $customers_table = $wpdb->prefix . 'agent_customers';
        $customer_images_table = $wpdb->prefix . 'agent_customer_images';

        $agent = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $agents_table WHERE user_id = %d", $current_user_id
        ));

        if (!$agent) {
            wp_send_json_error('Agent not found');
        }

        $image = $wpdb->get_row($wpdb->prepare(
            "SELECT ci.* FROM $customer_images_table ci
             INNER JOIN $customers_table c ON ci.customer_id = c.id
             WHERE ci.id = %d AND c.agent_id = %d",
            $image_id, $agent->id
        ));

        if (!$image) {
            wp_send_json_error('Image not found or access denied');
        }

        $result = $wpdb->delete($customer_images_table, array('id' => $image_id));

        if ($result === false) {
            wp_send_json_error('Failed to delete image');
        }

        wp_send_json_success('Image deleted successfully');
    }

    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('agent-scripts', AGENT_MANAGEMENT_PLUGIN_URL . 'assets/agent-scripts.js', array('jquery'), '1.1', true);
        wp_enqueue_style('agent-styles', AGENT_MANAGEMENT_PLUGIN_URL . 'assets/agent-styles.css', array(), '1.2');

        if (class_exists('WooCommerce')) {
            wp_enqueue_script('prettyPhoto', WC()->plugin_url() . '/assets/js/prettyPhoto/jquery.prettyPhoto.min.js', array('jquery'), '1.0.0', true);
            wp_enqueue_script('prettyPhoto-init', WC()->plugin_url() . '/assets/js/prettyPhoto/jquery.prettyPhoto.init.min.js', array('jquery', 'prettyPhoto'), '1.0.0', true);
            wp_enqueue_style('woocommerce_prettyPhoto_css', WC()->plugin_url() . '/assets/css/prettyPhoto.css');
        } else {
            wp_enqueue_script('prettyPhoto', 'https://cdnjs.cloudflare.com/ajax/libs/prettyPhoto/3.1.6/js/jquery.prettyPhoto.min.js', array('jquery'), '3.1.6', true);
            wp_enqueue_style('prettyPhoto-css', 'https://cdnjs.cloudflare.com/ajax/libs/prettyPhoto/3.1.6/css/prettyPhoto.min.css');
        }

        wp_localize_script('agent-scripts', 'agent_dashboard_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('agent_auth_nonce')
        ));
    }
}