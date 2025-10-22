<?php

/*
 * Plugin Name: wp_tombstone
 * Description: Creates tombstones for deleted database objects
 * Version: 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_TOMBSTONE_PATH', plugin_dir_path(__FILE__));
define('WP_TOMBSTONE_URL', plugin_dir_url(__FILE__));

// Include class files
require_once WP_TOMBSTONE_PATH . 'includes/class-admin.php';
require_once WP_TOMBSTONE_PATH . 'includes/class-database.php';
require_once WP_TOMBSTONE_PATH . 'includes/class-hooks.php';
require_once WP_TOMBSTONE_PATH . 'includes/class-rest-controller.php';

// Register activation hook
register_activation_hook(__FILE__, array('WP_Tombstone_Database', 'create_table'));

// Initialize plugin components
new WP_Tombstone_Hooks();
new WP_Tombstone_Admin();
new WP_Tombstone_REST_Controller();
