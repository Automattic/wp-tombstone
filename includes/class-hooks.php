<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress action hooks for tracking deletions
 */
class WP_Tombstone_Hooks {

    /**
     * Constructor - registers all hooks
     */
    public function __construct() {
        add_action('delete_post', array($this, 'on_delete_post'), 10, 2);
        add_action('delete_comment', array($this, 'on_delete_comment'), 10, 2);
        add_action('delete_user', array($this, 'on_delete_user'), 10, 3);
        add_action('delete_term', array($this, 'on_delete_term'), 10, 5);
        add_action('delete_attachment', array($this, 'on_delete_attachment'), 10, 2);
    }

    /**
     * Handle post deletion
     */
    public function on_delete_post($post_id, $post) {
        WP_Tombstone_Database::insert('post', $post_id);
    }

    /**
     * Handle comment deletion
     */
    public function on_delete_comment($comment_id, $comment) {
        WP_Tombstone_Database::insert('comment', $comment_id);
    }

    /**
     * Handle user deletion
     */
    public function on_delete_user($user_id, $reassign, $user) {
        WP_Tombstone_Database::insert('user', $user_id);
    }

    /**
     * Handle term deletion (categories, tags, custom taxonomies)
     */
    public function on_delete_term($term_id, $tt_id, $taxonomy, $deleted_term, $object_ids) {
        WP_Tombstone_Database::insert('term', $term_id);
    }

    /**
     * Handle attachment deletion
     */
    public function on_delete_attachment($post_id, $post) {
        WP_Tombstone_Database::insert('attachment', $post_id);
    }
}
