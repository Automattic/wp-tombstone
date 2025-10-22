<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin interface for Tombstones
 */
class WP_Tombstone_Admin {

    /**
     * Constructor - registers admin hooks
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        add_management_page(
            'Tombstones',
            'Tombstones',
            'manage_options',
            'wp-tombstones',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Render the admin page
     */
    public function render_admin_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tombstones';

        // Handle form submissions
        $message = '';

        // Handle re-run activation
        if (isset($_POST['wp_tombstone_reactivate']) && check_admin_referer('wp_tombstone_reactivate')) {
            WP_Tombstone_Database::create_table();
            $message = '<div class="notice notice-success is-dismissible"><p>Successfully re-ran plugin activation!</p></div>';
        }

        // Handle delete all tombstones
        if (isset($_POST['wp_tombstone_delete_all']) && check_admin_referer('wp_tombstone_delete_all')) {
            $deleted = $wpdb->query("DELETE FROM $table_name");
            $message = sprintf(
                '<div class="notice notice-success is-dismissible"><p>Successfully deleted %d tombstones!</p></div>',
                $deleted
            );
        }

        // Handle generate samples
        if (isset($_POST['wp_tombstone_generate']) && check_admin_referer('wp_tombstone_generate_samples')) {
            $count = intval($_POST['count']);
            $days_back = intval($_POST['days_back']);

            if ($count > 0 && $count <= 10000) {
                $inserted = WP_Tombstone_Database::generate_samples($count, $days_back);
                $message = sprintf(
                    '<div class="notice notice-success is-dismissible"><p>Successfully generated %d sample tombstones!</p></div>',
                    $inserted
                );
            } else {
                $message = '<div class="notice notice-error is-dismissible"><p>Please enter a valid count between 1 and 10,000.</p></div>';
            }
        }

        // Get total tombstone count
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        ?>
        <div class="wrap">
            <h1>Tombstones Manager</h1>

            <?php echo $message; ?>

            <div class="card" style="max-width: 600px;">
                <h2>Database Statistics</h2>
                <p>Total tombstones in database: <strong><?php echo number_format($total_count); ?></strong></p>
            </div>

            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2>Generate Sample Tombstones</h2>
                <p>Create sample tombstone records for testing purposes.</p>

                <form method="post" action="">
                    <?php wp_nonce_field('wp_tombstone_generate_samples'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="count">Number of Samples</label>
                            </th>
                            <td>
                                <input type="number" id="count" name="count" value="100" min="1" max="10000" class="regular-text" />
                                <p class="description">How many sample tombstones to generate (1-10,000)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="days_back">Days Back</label>
                            </th>
                            <td>
                                <input type="number" id="days_back" name="days_back" value="30" min="1" max="365" class="regular-text" />
                                <p class="description">Randomize dates within the last X days (1-365)</p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="wp_tombstone_generate" class="button button-primary" value="Generate Samples" />
                    </p>
                </form>
            </div>

            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2>Re-run Plugin Activation</h2>
                <p>Re-run the plugin activation process to recreate/update the database table.</p>
                <p>This will ensure the tombstones table exists with the latest schema.</p>

                <form method="post" action="">
                    <?php wp_nonce_field('wp_tombstone_reactivate'); ?>

                    <p class="submit">
                        <input type="submit" name="wp_tombstone_reactivate" class="button button-secondary" value="Re-run Activation" />
                    </p>
                </form>
            </div>

            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2>Delete All Tombstones</h2>
                <p>Permanently delete all tombstone records from the database.</p>
                <p><strong>Warning:</strong> This action cannot be undone!</p>

                <form method="post" action="" onsubmit="return confirm('Are you sure you want to delete ALL tombstones? This action cannot be undone!');">
                    <?php wp_nonce_field('wp_tombstone_delete_all'); ?>

                    <p class="submit">
                        <input type="submit" name="wp_tombstone_delete_all" class="button button-secondary" value="Delete All Tombstones" style="color: #b32d2e;" />
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
}
