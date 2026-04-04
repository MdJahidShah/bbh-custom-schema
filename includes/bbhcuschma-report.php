<?php
if (!defined('ABSPATH')) exit;

/**
 * Get the list of post types to include in the report.
 * Uses the enabled post types from plugin settings.
 *
 * @return array Array of post type slugs.
 */
function bbhcuschma_get_report_post_types() {
    $enabled = bbhcuschma_get_enabled_post_types();
    
    if (empty($enabled)) {
        return array('post', 'page');
    }
    
    return $enabled;
}

/**
 * Render the content schema report page.
 */
function bbhcuschma_report_page_callback() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Get pagination parameter
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $paged = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1;
    $paged = max(1, $paged);

    // Get enabled post types for the report
    $post_types = bbhcuschma_get_report_post_types();

    // Query all posts from enabled post types
    $all_posts = get_posts(array(
        'post_type'      => $post_types,
        'post_status'    => 'publish',
        'numberposts'    => -1,
        'posts_per_page' => -1,
    ));

    // Separate posts with and without schema
    $pending = array();
    $done = array();

    foreach ($all_posts as $post) {
        $schema = get_post_meta($post->ID, '_bbhcuschma_custom_schema', true);
        if (!empty($schema)) {
            $done[] = $post;
        } else {
            $pending[] = $post;
        }
    }

    // Pagination for pending posts
    $per_page = 10;
    $total_pending = count($pending);
    $offset = ($paged - 1) * $per_page;
    $pending_page = array_slice($pending, $offset, $per_page);

    // Report Page Title & Intro
    echo '<div class="wrap reportpagehead"><h1>Content Schema Report</h1><p>This report shows the status of content schema implementation across your posts and pages.</p></div>';

    // Render Pending Table
    echo '<div class="ppwoshead"><h2>Posts/Pages Without Schema</h2></div>';
    echo '<table class="widefat"><thead><tr><th>Title</th><th>Type</th><th>URL</th><th>Action</th></tr></thead><tbody>';
    
    if (!empty($pending_page)) {
        foreach ($pending_page as $post) {
            $title = get_the_title($post->ID);
            $url = get_permalink($post->ID);
            $edit_url = get_edit_post_link($post->ID);
            $post_type_obj = get_post_type_object($post->post_type);
            $post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type;
            
            echo '<tr>';
            echo '<td>' . esc_html($title) . '</td>';
            echo '<td>' . esc_html($post_type_label) . '</td>';
            echo '<td><a href="' . esc_url($url) . '" target="_blank">' . esc_html($url) . '</a></td>';
            echo '<td><a class="button button-primary" href="' . esc_url($edit_url) . '">Edit</a></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="4">' . esc_html__('No posts without schema found.', 'bbh-custom-schema') . '</td></tr>';
    }
    echo '</tbody></table>';

    // Pagination for Pending
    $big = 999999999;
    $total_pages = ceil($total_pending / $per_page);
    
    if ($total_pages > 1) {
        $pagination = paginate_links(array(
            'base'      => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
            'format'    => '?paged=%#%',
            'current'   => $paged,
            'total'     => $total_pages,
            'prev_text' => '&laquo; Previous',
            'next_text' => 'Next &raquo;',
        ));
        
        if ($pagination) {
            echo '<div class="tablenav table-flex"><div class="tablenav-pages">' . wp_kses_post($pagination) . '</div></div>';
        }
    }

    // Done table
    echo '<div class="ppwshead"><h2>Posts/Pages with Schema</h2></div>';
    echo '<table class="widefat"><thead><tr><th>Title</th><th>Type</th><th>URL</th><th>Status</th></tr></thead><tbody>';
    
    if (!empty($done)) {
        foreach ($done as $post) {
            $title = get_the_title($post->ID);
            $url = get_permalink($post->ID);
            $post_type_obj = get_post_type_object($post->post_type);
            $post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type;
            
            echo '<tr>';
            echo '<td>' . esc_html($title) . '</td>';
            echo '<td>' . esc_html($post_type_label) . '</td>';
            echo '<td><a href="' . esc_url($url) . '" target="_blank">' . esc_html($url) . '</a></td>';
            echo '<td><span class="dashicons dashicons-yes"></span></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="4">' . esc_html__('No posts with schema found.', 'bbh-custom-schema') . '</td></tr>';
    }
    echo '</tbody></table>';
}
