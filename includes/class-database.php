<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database management for Tombstones
 */
class WP_Tombstone_Database {

    /**
     * Create the tombstones table
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tombstones';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            tombstone_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            object_type varchar(50) NOT NULL,
            object_id bigint(20) unsigned NOT NULL,
            deleted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (tombstone_id),
            UNIQUE KEY `object_type_id` (`object_type`,`object_id`) USING BTREE,
            KEY deleted_at (deleted_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);

        // Store the database version
        add_option('wp_tombstone_db_version', '1.0');
    }

    
    /**
     * Insert a tombstone record for a deleted object
     *
     * @param string $object_type Type of object (post, comment, user, term, attachment)
     * @param int $object_id ID of the deleted object
     * @param string|null $deleted_at Optional datetime string. Defaults to current time.
     * @return int|false The number of rows inserted, or false on error
     */
    public static function insert($object_type, $object_id, $deleted_at = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tombstones';

        if ($deleted_at === null) {
            $deleted_at = current_time('mysql');
        }

        return $wpdb->insert(
            $table_name,
            array(
                'object_type' => $object_type,
                'object_id' => $object_id,
                'deleted_at' => $deleted_at
            ),
            array('%s', '%d', '%s')
        );
    }

    /**
     * Generate sample tombstone records with randomized dates
     * Creates and deletes real WordPress objects to trigger tombstone creation
     *
     * @param int $count Number of sample tombstones to generate
     * @param int $days_back Number of days in the past to randomize dates (default: 30)
     * @return int Number of tombstones successfully created
     */
    public static function generate_samples($count, $days_back = 30) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tombstones';
        $object_types = array('post', 'comment', 'user', 'term', 'attachment');
        $created = 0;

        for ($i = 0; $i < $count; $i++) {
            $object_type = $object_types[array_rand($object_types)];
            $object_id = null;

            // Create and delete objects based on type
            switch ($object_type) {
                case 'post':
                    $post_id = wp_insert_post(array(
                        'post_title' => 'Sample Post ' . time() . rand(1, 9999),
                        'post_content' => 'This is a sample post for tombstone testing.',
                        'post_status' => 'publish',
                        'post_type' => 'post'
                    ));
                    if ($post_id && !is_wp_error($post_id)) {
                        wp_delete_post($post_id, true);
                        $object_id = $post_id;
                    }
                    break;

                case 'comment':
                    // Need a valid post to attach comment to
                    $posts = get_posts(array('posts_per_page' => 1, 'post_status' => 'publish'));
                    if (!empty($posts)) {
                        $comment_id = wp_insert_comment(array(
                            'comment_post_ID' => $posts[0]->ID,
                            'comment_author' => 'Sample User',
                            'comment_author_email' => 'sample' . time() . '@example.com',
                            'comment_content' => 'This is a sample comment for tombstone testing.',
                            'comment_approved' => 1
                        ));
                        if ($comment_id && !is_wp_error($comment_id)) {
                            wp_delete_comment($comment_id, true);
                            $object_id = $comment_id;
                        }
                    }
                    break;

                case 'user':
                    $username = 'sampleuser' . time() . rand(1, 9999);
                    $user_id = wp_insert_user(array(
                        'user_login' => $username,
                        'user_email' => $username . '@example.com',
                        'user_pass' => wp_generate_password(),
                        'role' => 'subscriber'
                    ));
                    if ($user_id && !is_wp_error($user_id)) {
                        require_once(ABSPATH . 'wp-admin/includes/user.php');
                        wp_delete_user($user_id);
                        $object_id = $user_id;
                    }
                    break;

                case 'term':
                    $term = wp_insert_term(
                        'Sample Term ' . time() . rand(1, 9999),
                        'category'
                    );
                    if (!is_wp_error($term) && isset($term['term_id'])) {
                        wp_delete_term($term['term_id'], 'category');
                        $object_id = $term['term_id'];
                    }
                    break;

                case 'attachment':
                    $attachment_id = wp_insert_attachment(array(
                        'post_title' => 'Sample Attachment ' . time() . rand(1, 9999),
                        'post_content' => '',
                        'post_status' => 'inherit',
                        'post_mime_type' => 'image/jpeg'
                    ));
                    if ($attachment_id && !is_wp_error($attachment_id)) {
                        wp_delete_attachment($attachment_id, true);
                        $object_id = $attachment_id;
                    }
                    break;
            }

            // If object was created and deleted, update the tombstone timestamp
            if ($object_id) {
                // Random date within the last X days
                $random_seconds = rand(0, $days_back * 24 * 60 * 60);
                $deleted_at = date('Y-m-d H:i:s', time() - $random_seconds);

                // Update the most recent tombstone for this object
                $wpdb->update(
                    $table_name,
                    array('deleted_at' => $deleted_at),
                    array('object_type' => $object_type, 'object_id' => $object_id),
                    array('%s'),
                    array('%s', '%d')
                );

                $created++;
            }
        }

        return $created;
    }
}
