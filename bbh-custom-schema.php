<?php
/**
 * Plugin Name: BBH Custom Schema
 * Plugin URI: https://wordpress.org/plugins/bbh-custom-schema/
 * Description: Allows custom schema injection per post/page. Overrides other SEO plugin schemas if used.
 * Version: 1.2.0
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Author: Jahid Shah
 * Author URI: https://jahidshah.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bbh-custom-schema
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// INCLUDE REPORT FILE
// ============================================================================

$bbhcuschma_report_file = plugin_dir_path(__FILE__) . 'includes/bbhcuschma-report.php';

if (file_exists($bbhcuschma_report_file)) {
    require_once $bbhcuschma_report_file;
}

// ============================================================================
// DEFAULT SETTINGS
// ============================================================================

/**
 * Default enabled post types for the BBH Custom Schema meta box.
 * By default, Posts and Pages are enabled.
 */
function bbhcuschma_get_default_enabled_post_types() {
    return ['post', 'page'];
}

/**
 * Get the enabled post types from plugin options.
 * Falls back to default if not set.
 *
 * @return array Array of enabled post type slugs.
 */
function bbhcuschma_get_enabled_post_types() {
    $enabled = get_option('bbhcuschma_enabled_post_types', []);
    
    // If option is empty or not an array, return defaults
    if (empty($enabled) || !is_array($enabled)) {
        return bbhcuschma_get_default_enabled_post_types();
    }
    
    return array_map('sanitize_text_field', $enabled);
}

// ============================================================================
// ADMIN MENU SETUP
// ============================================================================

/**
 * Add admin menu items for BBH Custom Schema.
 * Creates main menu (Report), plus submenus for Report, Documentation, and Settings.
 */
function bbhcuschma_add_admin_menu() {
    // Main Menu → points to Report
    add_menu_page(
        'Content Schema Report',
        'BBH Custom Schema',
        'manage_options',
        'bbhcuschma-report',
        'bbhcuschma_report_page_callback',
        'dashicons-media-document',
        80
    );

    // Submenu 1: Report (same as main)
    add_submenu_page(
        'bbhcuschma-report',
        'Content Schema Report',
        'Content Schema Report',
        'manage_options',
        'bbhcuschma-report',
        'bbhcuschma_report_page_callback'
    );

    // Submenu 2: Settings (New)
    add_submenu_page(
        'bbhcuschma-report',
        'BBH Schema Settings',
        'Settings',
        'manage_options',
        'bbhcuschma-settings',
        'bbhcuschma_settings_page_callback'
    );

    // Submenu 3: Documentation
    add_submenu_page(
        'bbhcuschma-report',
        'Documentation',
        'Documentation',
        'manage_options',
        'bbhcuschma-documentation',
        'bbhcuschma_custom_schema_documentation_page'
    );

}
add_action('admin_menu', 'bbhcuschma_add_admin_menu');

// ============================================================================
// SETTINGS PAGE
// ============================================================================

/**
 * Register and initialize plugin settings.
 */
function bbhcuschma_register_settings() {
    // Register the option that stores enabled post types
    register_setting(
        'bbhcuschma_settings_group',
        'bbhcuschma_enabled_post_types',
        [
            'type'              => 'array',
            'sanitize_callback' => 'bbhcuschma_sanitize_enabled_post_types',
            'default'           => bbhcuschma_get_default_enabled_post_types(),
        ]
    );
}
add_action('admin_init', 'bbhcuschma_register_settings');

/**
 * Sanitize the enabled post types array.
 * Ensures only valid, public post types are saved.
 *
 * Note: Nonce verification is handled by WordPress Settings API before this
 * callback is called. The form uses settings_fields() which includes nonce.
 *
 * @param mixed $post_types The input array of post types.
 * @return array Sanitized array of valid post type slugs.
 */
function bbhcuschma_sanitize_enabled_post_types( $post_types ) {
    // Add success message using WordPress Settings API
    // Note: WordPress handles nonce verification before this callback is called
    add_settings_error(
        'bbhcuschma_settings_group',
        'bbhcuschma_settings_updated',
        esc_html__( 'Settings saved successfully!', 'bbh-custom-schema' ),
        'success'
    );

    // Get all public post types
    $public_post_types = bbhcuschma_get_public_post_types();
    $valid_slugs = array_keys( $public_post_types );
    
    // If not an array, return defaults
    if ( ! is_array( $post_types ) ) {
        return bbhcuschma_get_default_enabled_post_types();
    }
    
    // Sanitize each value and filter to only valid post types
    $sanitized = array_map( 'sanitize_text_field', (array) $post_types );
    $sanitized = array_filter( $sanitized, function( $slug ) use ( $valid_slugs ) {
        return in_array( $slug, $valid_slugs, true );
    });
    
    // Ensure at least one post type is selected
    if ( empty( $sanitized ) ) {
        return bbhcuschma_get_default_enabled_post_types();
    }
    
    return array_values( $sanitized );
}

/**
 * Get all public post types (built-in and custom).
 * Used to populate the settings checkboxes.
 *
 * @return array Associative array of post_type => post_type_name.
 */
function bbhcuschma_get_public_post_types() {
    $post_types = get_post_types(['public' => true], 'objects');
    
    $result = [];
    foreach ($post_types as $post_type) {
        // Skip built-in post types that shouldn't have meta boxes
        if (in_array($post_type->name, ['attachment', 'revision', 'nav_menu_item'], true)) {
            continue;
        }
        $result[$post_type->name] = $post_type->labels->singular_name;
    }
    
    return $result;
}

/**
 * Render the settings page HTML.
 * Allows admin to select which post types should display the BBH Custom Schema meta box.
 */
function bbhcuschma_settings_page_callback() {
    // Check user capability
    if (!current_user_can('manage_options')) {
        return;
    }

    // Get current enabled post types
    $enabled_post_types = bbhcuschma_get_enabled_post_types();
    
    // Get all public post types
    $public_post_types = bbhcuschma_get_public_post_types();

    // Display settings errors/notices
    // Nonce verification is handled by WordPress Settings API automatically
    settings_errors( 'bbhcuschma_settings_group' );

    ?>
    <div class="wrap bbhcuschma-settings-page">
        <h1><?php echo esc_html__('BBH Custom Schema Settings', 'bbh-custom-schema'); ?></h1>
        <p class="subheadingp"><?php echo esc_html__('Configure which post types should display the BBH Custom Schema meta box.', 'bbh-custom-schema'); ?></p>
        
        <form method="post" action="options.php">
            <?php
            // Output security fields for the registered setting
            settings_fields('bbhcuschma_settings_group');
            ?>
            
            <table class="form-table bbhcuschma-settings-table">
                <tr>
                    <th class="enable-setting-text" scope="row">
                        <?php echo esc_html__('Enable Meta Box For', 'bbh-custom-schema'); ?>
                        <p class="description">
                            <?php echo esc_html__('Check the post types where you want the BBH Custom Schema meta box to appear:', 'bbh-custom-schema'); ?>
                        </p>
                    </th>
                </tr>
                <tr>
                    <td>
                        <fieldset>
                            <?php if (!empty($public_post_types)) : ?>
                                <table class="widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px; padding-left: 5px;"><?php echo esc_html__('Enable', 'bbh-custom-schema'); ?></th>
                                            <th><?php echo esc_html__('Post Type', 'bbh-custom-schema'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($public_post_types as $slug => $name) : ?>
                                            <tr>
                                                <td>
                                                    <input 
                                                        type="checkbox" 
                                                        name="bbhcuschma_enabled_post_types[]" 
                                                        id="bbhcuschma_post_type_<?php echo esc_attr($slug); ?>" 
                                                        value="<?php echo esc_attr($slug); ?>"
                                                        <?php checked(in_array($slug, $enabled_post_types, true)); ?>
                                                    >
                                                </td>
                                                <td>
                                                    <label for="bbhcuschma_post_type_<?php echo esc_attr($slug); ?>">
                                                        <?php echo esc_html($name); ?>
                                                        <span style="color: #666; font-size: 12px;">(<?php echo esc_html($slug); ?>)</span>
                                                    </label>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else : ?>
                                <p><?php echo esc_html__('No public post types found.', 'bbh-custom-schema'); ?></p>
                            <?php endif; ?>
                            
                            <br>
                            <p class="description">
                                <strong><?php echo esc_html__('Note:', 'bbh-custom-schema'); ?></strong>
                                <?php echo esc_html__('Existing schema data on your posts and pages will not be affected. Only the visibility of the meta box will change based on your selection.', 'bbh-custom-schema'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <?php
            // Output save button
            submit_button(__('Save Settings', 'bbh-custom-schema'));
            ?>
        </form>
    </div>
    <?php
}

// ============================================================================
// ENQUEUE ADMIN STYLES
// ============================================================================

/**
 * Enqueue admin stylesheets for BBH Custom Schema.
 */
function bbhcuschma_enqueue_admin_style() {
    $css_path = plugin_dir_path(__FILE__) . 'css/bbhcuschma-admin-style.css';
    $version = file_exists($css_path) ? filemtime($css_path) : '1.0';

    wp_register_style('bbhcuschma-admin-style', plugins_url('css/bbhcuschma-admin-style.css', __FILE__), array(), $version);
    wp_enqueue_style('bbhcuschma-admin-style');
}
add_action('admin_enqueue_scripts', 'bbhcuschma_enqueue_admin_style');

// ============================================================================
// ENQUEUE FRONTEND STYLES
// ============================================================================

/**
 * Enqueue frontend stylesheets for BBH Custom Schema.
 */
function bbhcuschma_enqueue_style() {
    $version = file_exists(plugin_dir_path(__FILE__) . 'css/bbhcuschma-style.css') ? filemtime(plugin_dir_path(__FILE__) . 'css/bbhcuschma-style.css') : '1.0';
    wp_register_style('bbhcuschma-style', plugins_url('css/bbhcuschma-style.css', __FILE__), array(), $version);
    wp_enqueue_style('bbhcuschma-style');
}
add_action('wp_enqueue_scripts', 'bbhcuschma_enqueue_style');

// ============================================================================
// ENQUEUE ADMIN SCRIPTS
// ============================================================================

/**
 * Enqueue JavaScript files for the admin area.
 * Only loads on post edit screens.
 *
 * @param string $hook The current admin page hook.
 */
function bbhcuschma_enqueue_admin_script($hook) {
    if (in_array($hook, ['post.php', 'post-new.php'], true)) {
        wp_enqueue_script('bbhcuschma-plugin', plugins_url('js/bbhcuschma-plugin.js', __FILE__), array('jquery'), '1.0.0', true);
    }
}
add_action('admin_enqueue_scripts', 'bbhcuschma_enqueue_admin_script');

// ============================================================================
// META BOX REGISTRATION
// ============================================================================

/**
 * Add the BBH Custom Schema meta box to enabled post types.
 * The meta box is only shown on post types selected in the plugin settings.
 */
function bbhcuschma_add_custom_schema_meta_box() {
    // Get enabled post types from settings
    $enabled_post_types = bbhcuschma_get_enabled_post_types();
    
    // Only add meta box if there are enabled post types
    if (!empty($enabled_post_types) && is_array($enabled_post_types)) {
        add_meta_box(
            'bbhcuschma_custom_schema_box',
            __('BBH Custom Schema', 'bbh-custom-schema'),
            'bbhcuschma_render_meta_box',
            $enabled_post_types,
            'normal',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'bbhcuschma_add_custom_schema_meta_box');

// ============================================================================
// META BOX RENDERING
// ============================================================================

/**
 * Render the BBH Custom Schema meta box in the post editor.
 *
 * @param WP_Post $post The post object being edited.
 */
function bbhcuschma_render_meta_box($post) {
    // Output nonce field for security
    wp_nonce_field('bbhcuschma_save_schema', 'bbhcuschma_nonce');
    
    // Retrieve existing schema value for this post
    $schema = get_post_meta($post->ID, '_bbhcuschma_custom_schema', true);
    
    // Output the collapsible schema input box
    echo '<div id="bbhcuschma-schema-container">';
    echo '<strong id="bbhcuschma-schema-toggle" style="cursor:pointer; display:inline-block; margin-bottom:8px; color:#0073aa;">&#10148; ' . esc_html__('Custom Schema (Click to Expand)', 'bbh-custom-schema') . '</strong>';
    echo '<div id="bbhcuschma-schema-box" style="display:none; margin-top:10px;">';
    echo '<textarea id="bbhcuschma_custom_schema" name="bbhcuschma_custom_schema" rows="10" style="width:100%;">' . esc_textarea($schema) . '</textarea>';
    echo '</div></div>';
}

// ============================================================================
// SAVE META BOX DATA
// ============================================================================

/**
 * Save the custom schema data when a post is saved.
 *
 * @param int $post_id The ID of the post being saved.
 */
function bbhcuschma_save_schema_meta_box($post_id) {
    // Verify nonce field
    if (!isset($_POST['bbhcuschma_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['bbhcuschma_nonce']));
    if (!wp_verify_nonce($nonce, 'bbhcuschma_save_schema')) {
        return;
    }

    // Skip auto-saves
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Verify user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save the schema data
    if (isset($_POST['bbhcuschma_custom_schema'])) {
        update_post_meta(
            $post_id,
            '_bbhcuschma_custom_schema',
            wp_kses_post(wp_unslash($_POST['bbhcuschma_custom_schema']))
        );
    }
}
add_action('save_post', 'bbhcuschma_save_schema_meta_box');

// ============================================================================
// SCHEMA INJECTION INTO <head>
// ============================================================================

/**
 * Inject custom schema into the <head> section of the frontend.
 * Only runs on singular posts/pages that have custom schema defined.
 */
function bbhcuschma_inject_custom_schema() {
    if (!is_singular()) {
        return;
    }

    global $post;
    
    // Get schema data for this post
    $schema = get_post_meta($post->ID, '_bbhcuschma_custom_schema', true);
    
    if (!empty($schema)) {
        // Output the schema JSON-LD in the head
        echo "<script type='application/ld+json'>" . wp_kses($schema, []) . "</script>\n";
    }
}
add_action('wp_head', 'bbhcuschma_inject_custom_schema', 0);

// ============================================================================
// DISABLE OTHER SEO PLUGIN SCHEMA
// ============================================================================

/**
 * Disable Yoast and RankMath schema when BBH Custom Schema exists.
 * This prevents duplicate schema markup and ensures BBH schema takes priority.
 */
function bbhcuschma_disable_other_schema() {
    if (!is_singular()) {
        return;
    }

    global $post;

    // Check if this post has BBH custom schema
    $schema = get_post_meta($post->ID, '_bbhcuschma_custom_schema', true);

    if (empty($schema)) {
        return;
    }

    // Disable Yoast schema
    add_filter('wpseo_json_ld_output', '__return_false', 99);

    // Disable RankMath schema
    add_filter('rank_math/json_ld', '__return_empty_array', 99);
    add_filter('rank_math/frontend/disable_json_ld', '__return_true', 99);
}
add_action('wp', 'bbhcuschma_disable_other_schema');

// ============================================================================
// DOCUMENTATION PAGE (DEPRECATED WRAPPER)
// ============================================================================

/**
 * Deprecated function - redirects to the new documentation page.
 * 
 * @deprecated 1.1.0 Use bbhcuschma_custom_schema_documentation_page() instead.
 */
function bbh_custom_schema_documentation_page() {
    _deprecated_function(__FUNCTION__, '1.1.0', 'bbhcuschma_custom_schema_documentation_page');
    bbhcuschma_custom_schema_documentation_page();
}

// ============================================================================
// DOCUMENTATION PAGE CALLBACK
// ============================================================================

/**
 * Render the Documentation page in the admin area.
 */
function bbhcuschma_custom_schema_documentation_page() {
    ?>
    <div class="bbhcsh-wrap">
        <div class="admin-bbhcshrow">
            <div class="reportpagehead">
                <h1 class="bbhcshhead">BBH Custom Schema - Documentation</h1>
                <p>BBH Custom Schema allows you to insert your custom schema markup into any WordPress post or page to help search engines understand your content better.</p>
            </div>
        </div>
        <div class="admin-bbhcshrow">
            <div class="col-bbhcsh-paragraph bbhcsh_common">
                <h2>Steps to Use:</h2>
                <ol>
                    <li>Go to <strong>Posts</strong> or <strong>Pages</strong> and create or edit a post/page.</li>
                    <li>Scroll down to find the <strong>Custom Schema (Click to Expand)</strong> section.</li>
                    <li>Click the arrow to expand the schema input box.</li>
                    <li>Paste your schema code in the box. Make sure it's properly formatted with <code>&lt;script type="application/ld+json"&gt;...&lt;/script&gt;</code>.</li>
                    <li>Click the <strong>Publish</strong> or <strong>Update</strong> button to save your changes.</li>
                </ol>

                <h2>Example:</h2>
                <p>Here's a sample schema you can use as a starting point:</p>

                <pre>
                    <code>
                        &lt;script type="application/ld+json"&gt;
                            {
                            "@context": "https://schema.org",
                            "@type": "BlogPosting",
                            "mainEntityOfPage": {
                                "@type": "WebPage",
                                "@id": "website URL of your post"
                            },
                            "headline": "My New Post",
                            "description": "This image is about my new post.",
                            "image": "your website image URL",  
                            "author": {
                                "@type": "Organization",
                                "name": "BBH",
                                "url": "author website URL",
                            },  
                            "publisher": {
                                "@type": "Organization",
                                "name": "BBH",
                                "logo": {
                                "@type": "ImageObject",
                                "url": "poster image URL"
                                }
                            },
                            "datePublished": "2025-05-06",
                            "dateModified": "2025-05-06"
                            }
                        &lt;/script&gt;
                    </code>
                </pre>

                <h2>How do I view the custom schema markup?</h2>
                <p>Schema markup is for search engines and not displayed to visitors. To see it:</p>
                <ol>
                    <li>Press <strong>Ctrl + U</strong> (View Page Source) on your post or page</li>
                    <li>Press <strong>Ctrl + F</strong> and search for <strong>schema</strong> or <strong>ld+json</strong> to find the script</li>
                </ol>

                <h2>Support:</h2>
                <p>Need help or want to validate your schema? Visit <a href="https://validator.schema.org/" target="_blank" rel="noopener noreferrer">Schema Validator</a> or <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener noreferrer">Google Rich Results Test</a>.</p>
            </div>


            <div class="col-bbhcsh_sidebar_area bbhcsh_common">
                <h3 id="title">About Author</h3>
                <div class="bbhcsh-author-box">
                    <div class="plugin-author-img"></div>
                    <p>
                        I'm <strong><a href="https://jahidshah.com/" target="_blank" rel="noopener noreferrer">Jahid Shah</a></strong>,
                        a front-end developer with specialized skills in WordPress theme development and WordPress Security.
                        I'm passionate about creating error-free, secure websites and achieving 100% client satisfaction.
                        Solving real-world problems is my passion.
                    </p>
                    <br>
                    <div>
                        <p>
                            If you found this plugin helpful, you can support the developer via <a href="https://www.buymeacoffee.com/jahidshah" target="_blank" rel="noopener noreferrer">Buy Me a Coffee</a>.
                        </p>
                    </div>
                </div>
                <div class="leftsidebar">
                    <div>
                        <h5 id="title">Watch Help Video</h5>
                        <p><a href="" target="_blank" class="bbhcshyt-btn">Watch On YouTube</a></p>
                    </div>
                    <div>
                        <h5 id="title">Our All Plugins</h5>
                        <ul>
                            <li><a href="https://wordpress.org/plugins/aj-card-element/" target="_blank" rel="noopener noreferrer">AJ Card Element</a></li>
                            <li><a href="https://wordpress.org/plugins/aj-category-posts/" target="_blank" rel="noopener noreferrer">AJ Category Posts</a></li>
                            <li><a href="https://wordpress.org/plugins/aj-faq-block/" target="_blank" rel="noopener noreferrer">AJ FAQ Block</a></li>
                            <li><a href="https://wordpress.org/plugins/aj-square-testimonial-slider/" target="_blank" rel="noopener noreferrer">AJ Square Testimonial Slider</a></li>
                            <li><a href="https://wordpress.org/plugins/ajx-filter-for-woo/" target="_blank" rel="noopener noreferrer">AJx Filter for WooCommerce</a></li>
                            <li><a href="https://wordpress.org/plugins/bbh-custom-schema/" target="_blank" rel="noopener noreferrer">BBH Custom Schema</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
