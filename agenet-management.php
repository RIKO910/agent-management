<?php
/**
 * Plugin Name: Agent Management System
 * Description: Agent management with customer information tracking
 * Version: 1.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AGENT_MANAGEMENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AGENT_MANAGEMENT_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include required files
require_once AGENT_MANAGEMENT_PLUGIN_PATH . 'includes/class-agent-database.php';
require_once AGENT_MANAGEMENT_PLUGIN_PATH . 'includes/class-agent-auth.php';
require_once AGENT_MANAGEMENT_PLUGIN_PATH . 'includes/class-agent-dashboard.php';
require_once AGENT_MANAGEMENT_PLUGIN_PATH . 'includes/class-admin-dashboard.php';

class AgentManagementSystem {

    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('init', array($this, 'init'));
    }

    public function activate() {
        AgentDatabase::create_tables();
    }

    public function deactivate() {
        // Cleanup if needed
    }

    public function init() {
        new AgentAuth();
        new AgentDashboard();
        new AdminDashboard();
    }
}

new AgentManagementSystem();
