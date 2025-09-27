<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class AgentDatabase {

    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Agents table
        $agents_table = $wpdb->prefix . 'agents';
        $customers_table = $wpdb->prefix . 'agent_customers';

        $sql_agents = "CREATE TABLE $agents_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            company_name varchar(100) NOT NULL,
            phone varchar(20) NOT NULL,
            address text NOT NULL,
            license_number varchar(50) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        $sql_customers = "CREATE TABLE $customers_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            agent_id mediumint(9) NOT NULL,
            customer_name varchar(100) NOT NULL,
            customer_phone varchar(20) NOT NULL,
            passport_number varchar(50) NOT NULL,
            passport_image varchar(255) NOT NULL,
            visa_country varchar(50) NOT NULL,
            visa_type varchar(50) NOT NULL,
            submission_date date NOT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_agents);
        dbDelta($sql_customers);
    }
}