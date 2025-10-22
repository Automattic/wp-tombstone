<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API Controller for Tombstones
 */
class WP_Tombstone_REST_Controller {

    /**
     * Constructor - registers REST routes
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {

        $defaults = array( 'comment', 'user' );
        $post_types = array_keys(get_post_types());
        $taxonomies = array_keys(get_taxonomies());

        register_rest_route('wp-tombstones/v1', '/tombstones', array(
            'methods' => 'GET',
            'callback' => array($this, 'read_tombstone'),
            'permission_callback' => array($this, 'can_read_tombstone'),
            'args' => array(
                'deleted_since' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Fetch tombstones deleted since this date (Y-m-d H:i:s format)',
                    'validate_callback' => function($param, $request, $key) {
                        if (empty($param)) {
                            return false;
                        }
                        $timestamp = strtotime($param);
                        return $timestamp !== false;
                    }
                ),
                'object_type' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Filter by object type (post, comment, user, term, attachment)',
                    'enum' => array_merge( $defaults, $post_types, $taxonomies)
                ),
                'page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                    'description' => 'Current page of the collection'
                ),
                'per_page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100,
                    'description' => 'Maximum number of items to be returned in result set'
                )
            )
        ));
    }

    /**
     * Permission callback for reading tombstones
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function can_read_tombstone($request) {
        // Check if user has permission based on object type
        $object_type = $request->get_param('object_type');
        $object_id = $request->get_param('object_id');

        switch($object_type) {
            case 'post':
            case 'page':
            case 'attachment':
                return current_user_can('edit_posts');
            case 'comment':
                return current_user_can('moderate_comments');
            case 'user':
                return current_user_can('list_users');
            case 'tag':
            case 'category':
                // The user only needs read access to terms to be able to
                // enumerate them.
                return current_user_can('assign_terms');
        }

        // If we didn't already return, this is a post or taxonomy type
        $post_type = get_post_type_object($object_type);

        if ( $post_type ) {
            return current_user_can($post_type->cap->read);
        }

        $taxonomy = get_taxonomy( $object_type );
        if ( $taxonomy ) {
            return current_user_can( $taxonomy->cap->assign_terms);
        }
    }

    /**
     * REST API callback to get tombstones
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function read_tombstone($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tombstones';

        // Get pagination parameters
        $page = $request->get_param('page') ? absint($request->get_param('page')) : 1;
        $per_page = $request->get_param('per_page') ? absint($request->get_param('per_page')) : 10;

        // Ensure per_page is within bounds
        $per_page = min(100, max(1, $per_page));

        // Build the WHERE clause
        $where_clauses = array();
        $query_params = array();

        // Filter by deleted_since
        if (!empty($request->get_param('deleted_since'))) {
            $deleted_since = $request->get_param('deleted_since');
            $where_clauses[] = 'deleted_at >= %s';
            $query_params[] = date('Y-m-d H:i:s', strtotime($deleted_since));
        }

        // Filter by object_type
        if (!empty($request->get_param('object_type'))) {
            $where_clauses[] = 'object_type = %s';
            $query_params[] = $request->get_param('object_type');
        }

        // Build WHERE clause string
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
        }

        // Get total count
        $count_query = "SELECT COUNT(*) FROM $table_name" . $where_sql;
        if (!empty($query_params)) {
            $count_query = $wpdb->prepare($count_query, $query_params);
        }
        $total_items = (int) $wpdb->get_var($count_query);

        // Calculate total pages
        $total_pages = ceil($total_items / $per_page);

        // Calculate offset
        $offset = ($page - 1) * $per_page;

        // Build main query with pagination
        $query = "SELECT * FROM $table_name" . $where_sql . " ORDER BY deleted_at DESC LIMIT %d OFFSET %d";
        $pagination_params = array_merge($query_params, array($per_page, $offset));
        $query = $wpdb->prepare($query, $pagination_params);

        // Execute query
        $results = $wpdb->get_results($query);

        // Create response
        $response = new WP_REST_Response($results);

        // Add pagination headers
        $response->header('X-WP-Total', $total_items);
        $response->header('X-WP-TotalPages', $total_pages);

        // Add Link header for pagination navigation
        $base_url = rest_url('wp-tombstones/v1/tombstones');
        $links = array();

        // Build query string for links
        $link_params = array(
            'deleted_since' => $request->get_param('deleted_since'),
            'object_type' => $request->get_param('object_type'),
            'per_page' => $per_page
        );

        if ($page > 1) {
            $prev_link = add_query_arg(array_merge($link_params, array('page' => $page - 1)), $base_url);
            $links[] = '<' . $prev_link . '>; rel="prev"';
        }

        if ($page < $total_pages) {
            $next_link = add_query_arg(array_merge($link_params, array('page' => $page + 1)), $base_url);
            $links[] = '<' . $next_link . '>; rel="next"';
        }

        if (!empty($links)) {
            $response->header('Link', implode(', ', $links));
        }

        return $response;
    }
}
