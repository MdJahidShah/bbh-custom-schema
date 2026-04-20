<?php
/**
 * Plugin Name: BBH Custom Schema – Add Custom JSON-LD to Your Website
 * Plugin URI: https://wordpress.org/plugins/bbh-custom-schema/
 * Description: Add custom JSON-LD schema to any post or page and override schema from other SEO plugins to control your structured data output.
 * Version: 1.2.3
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

$bbhcuschma_seo_file = plugin_dir_path(__FILE__) . 'includes/bbhcuschma-schema-seo.php';

if (file_exists($bbhcuschma_seo_file)) {
    require_once $bbhcuschma_seo_file;
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
    
    <div class="bbhcuschma-settings-page">
        <div class="reportpagehead">
            <h1><?php echo esc_html__('BBH Custom Schema Settings', 'bbh-custom-schema'); ?></h1>
            <p class="subheadingp"><?php echo esc_html__('Configure which post types should display the BBH Custom Schema meta box.', 'bbh-custom-schema'); ?></p>
        </div>
        <div class="wrap bbhsettingpagewrapper">
            <?php
            // Show review notice inline after the intro paragraph
            bbhcuschma_output_review_notice();
            ?>

            <form method="post" action="options.php">
                <?php
                // Output security fields for the registered setting
                settings_fields('bbhcuschma_settings_group');
                ?>
                
                <table class="form-table bbhcuschma-settings-table">
                    <tr>
                        <th class="enable-setting-text" scope="row">
                            <p style="font-size: 20px;font-weight: 700;margin: 0;padding-top: 10px;">
                                <?php echo esc_html__('Enable Meta Box For', 'bbh-custom-schema'); ?>
                            </p>
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
                                                <th style="width: 50px; padding-left: 10px;"><?php echo esc_html__('Enable', 'bbh-custom-schema'); ?></th>
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
        wp_enqueue_script('bbhcuschma-plugin', plugins_url('js/bbhcuschma-plugin.js', __FILE__), array('jquery'), '1.0.1', true);
        wp_localize_script(
            'bbhcuschma-plugin',
            'bbhcuschmaValidate',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('bbhcuschma_validate_json_nonce'),
            )
        );
    }
}
add_action('admin_enqueue_scripts', 'bbhcuschma_enqueue_admin_script');

add_action('admin_enqueue_scripts', 'bbhcuschma_enqueue_review_script');

function bbhcuschma_enqueue_review_script($hook) {

    wp_enqueue_script(
        'bbhcuschma-schema-review',
        plugin_dir_url(__FILE__) . 'js/bbhcuschma-schema-review.js',
        ['jquery'],
        '1.0',
        true
    );

    wp_localize_script(
        'bbhcuschma-schema-review',
        'bbhcuschmaSchemaReview',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('bbhcuschma_review_nonce')
        ]
    );
}


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
    
    // Output the collapsible schema input box with validate button and result container
    echo '<div id="bbhcuschma-schema-container">';
    echo '<strong id="bbhcuschma-schema-toggle" style="cursor:pointer; display:inline-block; margin-bottom:8px; color:#0073aa;">&#10148; ' . esc_html__('Custom Schema (Click to Expand)', 'bbh-custom-schema') . '</strong>';
    echo '<div id="bbhcuschma-schema-box" style="display:none; margin-top:10px;">';
    echo '<p class="impnote">'
    . wp_kses(
        __( 'Enter your custom JSON-LD schema here. Do not include <strong>&lt;script type="application/ld+json"&gt;</strong> or <strong>&lt;/script&gt;</strong> tags. Only add valid JSON content.', 'bbh-custom-schema' ),
        array(
            'strong' => array()
        )
    )
    . '</p>';
    echo '<textarea id="bbhcuschma_custom_schema" name="bbhcuschma_custom_schema" rows="10" style="width:100%;">' . esc_textarea($schema) . '</textarea>';
    echo '<div class="bbhcuschma-validate-row" style="margin-top:8px; display:flex; align-items:center; flex-wrap:wrap; gap:10px;">';
    echo '<button type="button" id="bbhcuschma_validate_btn" class="button">' . esc_html__('Validity Check', 'bbh-custom-schema') . '</button>';
    echo '<button type="button" id="bbhcuschma_combine_btn" class="button">' . esc_html__('Combine Schemas', 'bbh-custom-schema') . '</button>';
    echo '<a href="https://jahidshah.com/how-to-optimize-schema-markup/#advsotips" target="_blank" class="button button-secondary" style="text-decoration:none;">' . esc_html__('Schema Optimization Guide ↗', 'bbh-custom-schema') . '</a>';
    echo '<span id="bbhcuschma_validate_result" style="display:none; font-size:13px; line-height:1.4;"></span>';
    echo '</div>';
    echo '<div id="bbhcuschma_combine_result" style="display:none; margin-top:12px;"></div>';
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
            <div class="reportpagehead" style="width: 100%;">
                <h1 class="bbhcshhead">BBH Custom Schema - Documentation</h1>
                <p>BBH Custom Schema allows you to insert your custom schema markup into any WordPress post or page to help search engines understand your content better.</p>
            </div>
        </div>

        <?php
        // Show review notice after intro paragraph
        bbhcuschma_output_review_notice();
        ?>

        <div class="admin-bbhcshrow">
            <div class="col-bbhcsh-paragraph bbhcsh_common">
                <h2>Steps to Use:</h2>
                <ol>
                    <li>Go to <strong>Posts</strong> or <strong>Pages</strong> and create or edit a post/page.</li>
                    <li>Scroll down to find the <strong>Custom Schema (Click to Expand)</strong> section.</li>
                    <li>Click the arrow to expand the schema input box.</li>
                    <li>Paste your JSON-LD schema in the box. Only include valid JSON — no need to add <code>&lt;script type="application/ld+json"&gt;</code> or <code>&lt;/script&gt;</code> tags.</li>
                    <li>When you are using multiple schemas, click the <strong>Combine Schemas</strong> button to merge any existing schemas.</li>
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
                                "url": "author website URL"
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
                    <div>
                        <p class="bbh-bmc-btn" style="margin-top: 0;font-weight: 500;">
                            If you found this plugin helpful, you can support the developer via - <br><a href="https://www.buymeacoffee.com/jahidshah" target="_blank" rel="noopener noreferrer">Buy Me a Coffee</a>
                        </p>
                    </div>
                </div>
                <div class="leftsidebar">
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
                    <div>
                        <h5 id="title">Watch Help Video</h5>
                        <p><a href="" target="_blank" class="bbhcshyt-btn">Watch On YouTube</a></p>
                    </div>
                    <div>
                        <h5 id="title">Review This Plugin</h5>
                        <p style="margin-top: 0;">Thank you for using BBH Custom Schema. We would greatly appreciate it if you could share your experience and leave a review for us on WordPress.org. Your review inspires us to keep improving the plugin and delivering a better user experience.</p>
                        <a href="https://wordpress.org/support/plugin/bbh-custom-schema/reviews/#new-post" target="_blank" rel="noopener noreferrer" style="margin-left: 10px;">Leave a Review</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ============================================================================
// USAGE TRACKING & REVIEW REQUEST SYSTEM
// ============================================================================

/**
 * Check if current page is a plugin admin page.
 * Uses both screen ID and URL fallback for reliability.
 *
 * @return bool True if on a plugin admin page.
 */
function bbhcuschma_is_plugin_page() {
    // Check by screen ID first
    $screen = get_current_screen();
    if ( $screen ) {
        $plugin_pages = array(
            'toplevel_page_bbhcuschma-report',
            'bbh-custom-schema_page_bbhcuschma-report',
            'bbh-custom-schema_page_bbhcuschma-settings',
            'bbh-custom-schema_page_bbhcuschma-documentation',
        );

        if ( in_array( $screen->id, $plugin_pages, true ) ) {
            return true;
        }
    }

    // Fallback: check URL query parameter
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( isset( $_GET['page'] ) ) {
        $plugin_slugs = array(
            'bbhcuschma-report',
            'bbhcuschma-settings',
            'bbhcuschma-documentation',
        );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return in_array( sanitize_text_field( wp_unslash( $_GET['page'] ) ), $plugin_slugs, true );
    }

    return false;
}

/**
 * Get the current usage count.
 *
 * @return int Number of times plugin has been used.
 */
function bbhcuschma_get_usage_count() {
    return (int) get_option( 'bbhcuschma_usage_count', 0 );
}

/**
 * Increment the usage count.
 *
 * @param int $post_id Post ID.
 */
function bbhcuschma_increment_usage( $post_id ) {
    $schema = get_post_meta( $post_id, '_bbhcuschma_custom_schema', true );
    if ( ! empty( $schema ) ) {
        $count = bbhcuschma_get_usage_count();
        update_option( 'bbhcuschma_usage_count', $count + 1, false );
    }
}

/**
 * Check if user has permanently dismissed the review request.
 * Allows notice to show again after 30 days if dismissed.
 *
 * @return bool True if should stay dismissed.
 */
function bbhcuschma_is_review_dismissed() {
    $dismissed = get_option( 'bbhcuschma_review_dismissed', false );
    
    // If not dismissed at all, return false
    if ( empty( $dismissed ) || 'false' === $dismissed ) {
        return false;
    }

    // If dismissed with timestamp, check if 30 days have passed
    if ( is_numeric( $dismissed ) ) {
        $days_since_dismiss = ( time() - (int) $dismissed ) / DAY_IN_SECONDS;
        // Show again after 30 days
        if ( $days_since_dismiss > 30 ) {
            return false;
        }
    }

    return true;
}

/**
 * Check if review notice should be shown.
 * Shows after 3 days (with 1+ usages) or 7 days (with 7+ usages).
 * For testing: Shows immediately when activated within last 5 minutes.
 *
 * @return bool True if notice should be shown.
 */
function bbhcuschma_should_show_review_notice() {
    // Only show on plugin pages
    if ( ! bbhcuschma_is_plugin_page() ) {
        return false;
    }

    // Don't show if permanently dismissed
    if ( bbhcuschma_is_review_dismissed() ) {
        return false;
    }

    // Don't show if user clicked "Maybe Later" (snoozed)
    $snoozed = get_user_meta( get_current_user_id(), 'bbhcuschma_review_snoozed', true );
    if ( ! empty( $snoozed ) ) {
        // Clear snooze after 24 hours so it shows again
        $hours_since_snooze = ( time() - (int) $snoozed ) / HOUR_IN_SECONDS;
        if ( $hours_since_snooze < 24 ) {
            return false;
        }
        // Snooze expired, clear it
        delete_user_meta( get_current_user_id(), 'bbhcuschma_review_snoozed' );
    }

    // Don't show to users who can't leave reviews
    if ( ! current_user_can( 'manage_options' ) ) {
        return false;
    }

    // Set activation time if not set
    $activation_date = get_option( 'bbhcuschma_activated_time', 0 );
    if ( ! $activation_date ) {
        update_option( 'bbhcuschma_activated_time', time(), false );
        return false;
    }

    $seconds_since_activation = time() - (int) $activation_date;
    $days_since_activation = $seconds_since_activation / DAY_IN_SECONDS;
    $usage_count = bbhcuschma_get_usage_count();

    // FOR TESTING: Show immediately if activated within last 5 minutes (300 seconds)
    // Change 300 to 0 for production (requires actual days to pass)
    if ( $seconds_since_activation <= 300 ) {
        return true;
    }

    // Show after 3 days if user has used schema at least once
    if ( $days_since_activation >= 3 && $usage_count >= 3 ) {
        return true;
    }

    // Show after 7 days if user has used schema at least 7 times
    if ( $days_since_activation >= 7 && $usage_count >= 7 ) {
        return true;
    }

    return false;
}

/**
 * Set the plugin activation time on activation.
 * Runs on every activation (not just first time).
 */
function bbhcuschma_set_activation_time() {
    // Always update activation time
    update_option( 'bbhcuschma_activated_time', time(), false );
    
    // Always set redirect flag
    update_option( 'bbhcuschma_do_activation_redirect', true, false );
    
    // Always reset welcome message flag
    update_option( 'bbhcuschma_show_welcome', true, false );
    
    // Reset review dismissed so it can show again
    delete_option( 'bbhcuschma_review_dismissed' );

    // Clear welcome dismissed user meta for current user
    $user_id = get_current_user_id();
    if ( $user_id ) {
        delete_user_meta( $user_id, 'bbhcuschma_welcome_dismissed' );
    }
}
register_activation_hook( __FILE__, 'bbhcuschma_set_activation_time' );

/**
 * Redirect to plugin dashboard after activation.
 */
function bbhcuschma_activation_redirect() {
    // Check if redirect flag is set
    if ( ! get_option( 'bbhcuschma_do_activation_redirect', false ) ) {
        return;
    }

    // Delete the flag so it only redirects once
    delete_option( 'bbhcuschma_do_activation_redirect' );

    // Only redirect on plugin activation, not on all admin pages
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( isset( $_GET['activate-multi'] ) ) {
        return;
    }

    // Set welcome message flag
    update_option( 'bbhcuschma_show_welcome', true, false );

    // Redirect to the plugin dashboard (report page)
    wp_safe_redirect( admin_url( 'admin.php?page=bbhcuschma-report' ) );
    exit;
}
add_action( 'admin_init', 'bbhcuschma_activation_redirect' );

/**
 * Show welcome notice after first activation.
 * Shows at the very top of admin pages using admin_notices hook.
 */
function bbhcuschma_welcome_notice() {
    // Check if welcome message should be shown
    if ( ! get_option( 'bbhcuschma_show_welcome', false ) ) {
        return;
    }

    // Check if already dismissed for this user
    $dismissed = get_user_meta( get_current_user_id(), 'bbhcuschma_welcome_dismissed', true );
    if ( ! empty( $dismissed ) ) {
        return;
    }

    $dismiss_url = wp_nonce_url( add_query_arg( 'bbhcuschma_dismiss_welcome', '1', admin_url() ), 'bbhcuschma_dismiss_welcome' );
    ?>
    <div class="notice notice-success bbhcuschma-schema-welcome-notice" style="border-left-color: #00a32a; position: relative;">
        <p style="margin: 0 0 10px 0; font-size: 14px;">
            <strong><?php esc_html_e( 'Welcome to BBH Custom Schema!', 'bbh-custom-schema' ); ?></strong>
        </p>
        <p style="margin: 0 0 15px 0; font-size: 13px; color: #3c434a;">
            <?php
            printf(
                '%s <a href="%s">%s</a> %s <a href="%s">%s</a>.',
                esc_html__( 'Thank you for installing BBH Custom Schema. To get started, simply add your custom JSON-LD schema to any post or page. Check out the', 'bbh-custom-schema' ),
                esc_url( admin_url( 'admin.php?page=bbhcuschma-settings' ) ),
                esc_html__( 'Settings', 'bbh-custom-schema' ),
                esc_html__( 'page to configure post types, or visit the', 'bbh-custom-schema' ),
                esc_url( admin_url( 'admin.php?page=bbhcuschma-documentation' ) ),
                esc_html__( 'Documentation', 'bbh-custom-schema' )
            );
            ?>
        </p>
        <p style="margin: 0;">
            <a href="<?php echo esc_url( $dismiss_url ); ?>" class="button button-primary">
                <?php esc_html_e( 'Get Started', 'bbh-custom-schema' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=bbhcuschma-documentation' ) ); ?>" class="button" style="margin-left: 8px;">
                <?php esc_html_e( 'View Documentation', 'bbh-custom-schema' ); ?>
            </a>
            <a href="<?php echo esc_url( $dismiss_url ); ?>" class="bbh-welcome-dismiss" style="margin-left: 15px; color: #72777c; text-decoration: none; font-size: 12px;">
                <?php esc_html_e( 'Dismiss', 'bbh-custom-schema' ); ?>
            </a>
        </p>
    </div>
    <?php
}
add_action( 'admin_notices', 'bbhcuschma_welcome_notice' );

/**
 * Handle welcome notice dismissal.
 */
function bbhcuschma_handle_welcome_dismiss() {
    if ( ! isset( $_GET['bbhcuschma_dismiss_welcome'] ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( ! check_admin_referer( 'bbhcuschma_dismiss_welcome' ) ) {
        return;
    }

    // Delete the welcome flag
    delete_option( 'bbhcuschma_show_welcome' );

    // Mark as dismissed for this user
    update_user_meta( get_current_user_id(), 'bbhcuschma_welcome_dismissed', time() );

    // Redirect back to the same page without the query args
    $redirect_url = remove_query_arg( array( 'bbhcuschma_dismiss_welcome', '_wpnonce' ), wp_get_referer() );
    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_init', 'bbhcuschma_handle_welcome_dismiss' );

/**
 * Track usage when schema is saved to a post.
 *
 * @param int $post_id Post ID.
 */
function bbhcuschma_track_usage_on_save( $post_id ) {
    // Don't count autosaves
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Don't count revisions
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }

    // Check if this post type is enabled for schema
    $post = get_post( $post_id );
    if ( ! $post ) {
        return;
    }

    $enabled_types = bbhcuschma_get_enabled_post_types();
    if ( ! in_array( $post->post_type, $enabled_types, true ) ) {
        return;
    }

    // Check if schema is being saved
    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( isset( $_POST['bbhcuschma_custom_schema'] ) ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $schema = sanitize_text_field( wp_unslash( $_POST['bbhcuschma_custom_schema'] ) );
        if ( ! empty( $schema ) ) {
            $count = bbhcuschma_get_usage_count();
            update_option( 'bbhcuschma_usage_count', $count + 1, false );
        }
    }
}
add_action( 'save_post', 'bbhcuschma_track_usage_on_save' );

/**
 * Output the review request notice HTML.
 * Shows inline after intro paragraphs on plugin pages.
 */
function bbhcuschma_output_review_notice() {
    // Check if notice should be shown
    if ( ! bbhcuschma_should_show_review_notice() ) {
        return;
    }

    $review_url = 'https://wordpress.org/support/plugin/bbh-custom-schema/reviews/#new-post';
    $dismiss_url = wp_nonce_url( admin_url( '?bbhcuschma_dismiss_review=1' ), 'bbhcuschma_dismiss_review' );
    $dismiss_permanent_url = wp_nonce_url( admin_url( '?bbhcuschma_dismiss_permanent=1' ), 'bbhcuschma_dismiss_permanent' );
    ?>
    <div class="notice notice-info bbhcuschma-schema-review-notice" style="border-left-color: #0073aa; margin: 15px 0;">
        <p style="margin: 0 0 12px 0; font-size: 14px;">
            <strong><?php esc_html_e( 'Enjoying BBH Custom Schema?', 'bbh-custom-schema' ); ?></strong>
        </p>
        <p style="margin: 0 0 15px 0; font-size: 13px; color: #3c434a;">
            <?php
$text = __( 'Thank you for using BBH Custom Schema. If the plugin has been helpful for you, we would truly appreciate it if you could take a moment to share your experience by leaving a review on WordPress.org. Your feedback helps us continue improving the plugin and delivering a better experience for the community.', 'bbh-custom-schema' );

printf(
    esc_html( $text ),
    absint( bbhcuschma_get_usage_count() )
);
?>
        </p>
        <p style="margin: 0;">
            <a href="<?php echo esc_url( $review_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary" style="margin-right: 8px;">
                <?php esc_html_e( 'Yes', 'bbh-custom-schema' ); ?>
            </a>
            <a href="<?php echo esc_url( $dismiss_url ); ?>" class="button" style="margin-right: 8px;">
                <?php esc_html_e( 'Maybe Later', 'bbh-custom-schema' ); ?>
            </a>
            <a href="<?php echo esc_url( $dismiss_permanent_url ); ?>" style="color: #72777c; text-decoration: none; font-size: 12px; line-height: 28px;">
                <?php esc_html_e( 'Do not show again', 'bbh-custom-schema' ); ?>
            </a>
        </p>
    </div>
    <?php
}

/**
 * Handle review notice dismissal (Maybe Later).
 */
function bbhcuschma_handle_dismiss() {
    // Handle "Maybe Later" - temporarily dismiss until next page visit
    if ( isset( $_GET['bbhcuschma_dismiss_review'] ) && '1' === $_GET['bbhcuschma_dismiss_review'] ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! check_admin_referer( 'bbhcuschma_dismiss_review' ) ) {
            return;
        }

        // Set user meta to temporarily hide the notice
        update_user_meta( get_current_user_id(), 'bbhcuschma_review_snoozed', time() );

        wp_safe_redirect( remove_query_arg( array( 'bbhcuschma_dismiss_review', '_wpnonce' ), wp_get_referer() ) );
        exit;
    }

    // Handle "Do not show again" - permanently dismisses
    if ( isset( $_GET['bbhcuschma_dismiss_permanent'] ) && '1' === $_GET['bbhcuschma_dismiss_permanent'] ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! check_admin_referer( 'bbhcuschma_dismiss_permanent' ) ) {
            return;
        }

        // Save timestamp so notice can show again after 30 days
        update_option( 'bbhcuschma_review_dismissed', time(), false );

        // Also clear the snoozed meta
        delete_user_meta( get_current_user_id(), 'bbhcuschma_review_snoozed' );

        wp_safe_redirect( remove_query_arg( array( 'bbhcuschma_dismiss_permanent', '_wpnonce' ), wp_get_referer() ) );
        exit;
    }
}
add_action( 'admin_init', 'bbhcuschma_handle_dismiss' );

// ============================================================================
// AJAX JSON VALIDATION HANDLER
// ============================================================================

/**
 * AJAX handler for validating JSON-LD schema.
 *
 * @since 1.2.2
 */
function bbhcuschma_ajax_validate_json() {
    check_ajax_referer( 'bbhcuschma_validate_json_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bbh-custom-schema' ) ) );
    }

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $json = isset( $_POST['json'] ) ? wp_unslash( $_POST['json'] ) : '';
    
    if ( empty( $json ) ) {
        wp_send_json_error( array( 'message' => __( 'No JSON provided.', 'bbh-custom-schema' ) ) );
    }

    json_decode( $json );
    $error = json_last_error();

    if ( JSON_ERROR_NONE === $error ) {
        wp_send_json_success( array( 'message' => __( 'Valid JSON', 'bbh-custom-schema' ) ) );
    } else {
        $error_msg = json_last_error_msg();
        /* translators: %s: JSON error message */
        wp_send_json_error( array( 'message' => sprintf( __( 'Invalid JSON: %s', 'bbh-custom-schema' ), $error_msg ) ) );
    }
}
add_action( 'wp_ajax_bbhcuschma_validate_json', 'bbhcuschma_ajax_validate_json' );

/**
 * AJAX handler for combining multiple JSON-LD schemas.
 *
 * @since 1.3.5
 */
function bbhcuschma_ajax_combine_schema() {
    check_ajax_referer( 'bbhcuschma_validate_json_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bbh-custom-schema' ) ) );
    }

    $json = isset( $_POST['json'] ) ? sanitize_textarea_field( wp_unslash( $_POST['json'] ) ) : '';

    if ( empty( $json ) ) {
        wp_send_json_error( array( 'message' => __( 'No schema provided.', 'bbh-custom-schema' ) ) );
    }

    $result = bbhcuschma_combine_schema( $json );
    wp_send_json_success( $result );
}
add_action( 'wp_ajax_bbhcuschma_combine_schema', 'bbhcuschma_ajax_combine_schema' );
