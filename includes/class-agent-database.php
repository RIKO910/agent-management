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
        $customer_images_table = $wpdb->prefix . 'agent_customer_images';

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
        customer_phone varchar(100) NOT NULL,
        passport_number varchar(50) NOT NULL,
        passport_image varchar(255) NOT NULL,
        visa_country varchar(100) NOT NULL,
        visa_type varchar(50) NOT NULL,
        submission_date date NOT NULL,
        total_amount decimal(14,2) DEFAULT NULL,
        deposit_amount decimal(14,2) DEFAULT NULL,
        status varchar(20) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

        // New table for additional images
        $sql_customer_images = "CREATE TABLE $customer_images_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        customer_id mediumint(9) NOT NULL,
        image_url varchar(255) NOT NULL,
        image_type varchar(50) DEFAULT 'additional',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (customer_id) REFERENCES $customers_table(id) ON DELETE CASCADE
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_agents);
        dbDelta($sql_customers);
        dbDelta($sql_customer_images);
    }

    /**
     * Adds amount columns on existing installs where they are missing.
     */
    public static function maybe_upgrade_customer_amount_columns() {
        global $wpdb;
        $exists = self::customers_table_columns_map();
        if ( isset( $exists['total_amount'] ) && isset( $exists['deposit_amount'] ) ) {
            return;
        }

        $alter_parts = array();
        if ( ! isset( $exists['total_amount'] ) ) {
            $alter_parts[] = 'ADD COLUMN total_amount decimal(14,2) DEFAULT NULL';
        }
        if ( ! isset( $exists['deposit_amount'] ) ) {
            $alter_parts[] = 'ADD COLUMN deposit_amount decimal(14,2) DEFAULT NULL';
        }
        if ( ! empty( $alter_parts ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is $wpdb->prefix + literal suffix.
            $wpdb->query( 'ALTER TABLE `' . $wpdb->prefix . 'agent_customers` ' . implode( ', ', $alter_parts ) );
        }
    }

    /**
     * Column name => Row from SHOW FULL COLUMNS.
     *
     * @return array<string, array>
     */
    private static function customers_table_columns_map() {
        global $wpdb;
        $table = $wpdb->prefix . 'agent_customers';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is $wpdb->prefix + literal suffix.
        $cols    = $wpdb->get_results( 'SHOW COLUMNS FROM `' . $table . '`', ARRAY_A );
        $map = array();
        if ( empty( $cols ) ) {
            return $map;
        }
        foreach ( $cols as $row ) {
            if ( ! empty( $row['Field'] ) ) {
                $map[ $row['Field'] ] = $row;
            }
        }
        return $map;
    }
}