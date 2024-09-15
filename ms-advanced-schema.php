<?php
/**
 * Plugin Name
 *
 * @package           ASMPlugin
 * @author            Muhammad Shoaib
 * @copyright         2024 Muhammad Shoaib
 * @license           <GPL-3>This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License.
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.</GPL-3>
 *
 * @wordpress-plugin
 * Plugin Name:       Advanced Schema Manager
 * Plugin URI:        https://github.com/topwebdesignco/advanced-schema-manager
 * Description:       Manage your custom built JSON-LD schema types for your WordPress website. This plugin automatically injects schemas into selected pages.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Author:            Muhammad Shoaib
 * Author URI:        https://github.com/topwebdesignco
 * Text Domain:       asm-advanced-schema-manager
 * License:           GPL v3
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        https://github.com/topwebdesignco/advanced-schema-manager
 */


function ms_advanced_schema_enqueue_styles() {
    wp_enqueue_style('ms-advanced-schema-styles', plugin_dir_url(__FILE__) . 'style.css', array(), time());
}
add_action('admin_enqueue_scripts', 'ms_advanced_schema_enqueue_styles');


register_activation_hook(__FILE__, 'ms_advanced_schema_create_table');
function ms_advanced_schema_create_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'ms_advanced_schemas';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        page_id bigint(20) NOT NULL,
        type varchar(255) NOT NULL,
        schema_json longtext NOT NULL,
        created_on datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_on datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// register_deactivation_hook(__FILE__, 'ms_advanced_schema_delete_table');
register_uninstall_hook(__FILE__, 'ms_advanced_schema_delete_table');
function ms_advanced_schema_delete_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'ms_advanced_schemas';
    $sql = "DROP TABLE IF EXISTS $table;";
    
    $wpdb->query($sql);
}

add_action('admin_menu', 'ms_advanced_schema_menu');
function ms_advanced_schema_menu() {
    $icon_url = plugins_url('icons/json-icon-3.svg', __FILE__);
    add_menu_page('Schema Manager', 'Schema Manager', 'edit_pages',  'ms-advanced-schema', 'ms_advanced_schema_all_schemas_page', $icon_url, 11);
    add_submenu_page('ms-advanced-schema', 'All Schemas', 'All Schemas', 'edit_pages', 'ms-advanced-schema', 'ms_advanced_schema_all_schemas_page');
    add_submenu_page('ms-advanced-schema', 'Add New Schema', 'Add New Schema', 'edit_pages', 'ms-advanced-schema-add', 'ms_advanced_schema_add_schema_page');
    add_submenu_page('ms-advanced-schema', 'Edit Schema', '', 'edit_pages', 'ms-advanced-schema-edit', 'ms_advanced_schema_edit_schema_page');
}

add_action('admin_menu', 'ms_advanced_schema_set_active_menu');
function ms_advanced_schema_set_active_menu() {
    global $parent_file, $submenu_file, $pagenow;

    // Check if we're on the "Edit Schema" page
    if (isset($_GET['page']) && $_GET['page'] === 'ms-advanced-schema-edit') {
        // Set parent file to "All Schemas"
        $parent_file = 'ms-advanced-schema';
        // Set submenu file to "All Schemas" so it's highlighted
        $submenu_file = 'ms-advanced-schema';
    }
}

function ms_advanced_schema_all_schemas_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'ms_advanced_schemas';
    
    if (!current_user_can('edit_pages')) {
        wp_die('Unauthorized access');
    }

    // Handle delete request
    if (isset($_GET['delete_id'])) {
        // Verify the nonce for security
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_schema_' . $_GET['delete_id'])) {
            wp_die('Nonce verification failed');
        }

        // Delete the schema
        $delete_id = intval($_GET['delete_id']);
        $deleted = $wpdb->delete($table, ['id' => $delete_id], ['%d']);
        
        if ($deleted) {
            echo '<script type="text/javascript">
                     window.location.href="' . admin_url('admin.php?page=ms-advanced-schema&message=deleted') . '";
                  </script>';
            exit;
        } else {
            // If delete failed, display an error (after redirect part)
            add_action('admin_notices', function() {
                echo '<div class="error"><p>Failed to delete schema.</p></div>';
            });
        }
    }
    
    ?>
    <div id="wpbody" role="main">
        <div id="wpbody-content">
            <div class="wrap">
                <!-- Display success message after deletion -->
                <?php if (isset($_GET['message']) && $_GET['message'] == 'saved'): ?>
                    <div class="updated"><p>Successfully Saved.</p></div>
                <?php endif; ?>
                <?php if (isset($_GET['message']) && $_GET['message'] == 'updated'): ?>
                    <div class="updated"><p>Successfully Updated.</p></div>
                <?php endif; ?>
                <?php if (isset($_GET['message']) && $_GET['message'] == 'deleted'): ?>
                    <div class="error"><p>Successfully Deleted.</p></div>
                <?php endif; ?>
                <h1 class="wp-heading-inline">Schemas</h1>
                <a class="page-title-action" href="?page=ms-advanced-schema-add">Add New Schema</a>
                <hr class="wp-header-end">
                <div class="asm flex-container">
                    <div class="table-column">
                        <?php $schemas = $wpdb->get_results("SELECT * FROM $table"); ?>
                        <table class="wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th>Page Title</th>
                                    <th>Schema Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($schemas) : ?>
                                    <?php foreach ($schemas as $schema) : ?>
                                        <tr>
                                            <td><a href=""><?php echo get_the_title($schema->page_id); ?></a></td>
                                            <td><?php echo stripslashes($schema->type); ?></td>
                                            <td>
                                                <a href="?page=ms-advanced-schema-edit&edit_id=<?php echo $schema->id; ?>">Edit</a> |
                                                <a href="<?php echo wp_nonce_url('?page=ms-advanced-schema&delete_id=' . $schema->id, 'delete_schema_' . $schema->id); ?>" onclick="return confirm('Are you sure you want to delete this schema?');">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr><td colspan="4">No schemas found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>                        
                    </div>
                    <div class="preview-column">
                        <textarea id="preview_box" rows="40" cols="1" placeholder="Select schema to preview JSON" readonly></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>    
    <?php
}
function ms_advanced_schema_add_schema_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'ms_advanced_schemas';

    if (!current_user_can('edit_pages')) {
        wp_die('Unauthorized access');
    }
    ?>    
    <div class="wrap">
        <h1>Add New Schema</h1>
        <?php
        // Insert data in to DB
        if (isset($_POST['custom_schema_json']) && isset($_POST['custom_schema_page_id'])) {
            $page_id = sanitize_text_field($_POST['custom_schema_page_id']);
            $type = sanitize_text_field($_POST['custom_schema_type']);
            $schema_json = wp_kses_post($_POST['custom_schema_json']);
            // Ensure the page ID and schema JSON are not empty
            if (!empty($page_id) && !empty($schema_json)) {
                    // Insert new schema
                    $inserted = $wpdb->insert(
                        $table,
                        array('page_id' => $page_id, 'type' => $type, 'schema_json' => $schema_json)
                    );
                    // Display message
                    if ($inserted) {
                        // echo '<div class="updated"><p>Schema saved successfully.</p></div>';
                        echo '<script type="text/javascript">
                                    window.location.href="' . admin_url('admin.php?page=ms-advanced-schema&message=saved') . '";
                                </script>';
                        exit;
                    } else {
                        echo '<div class="error"><p>Failed to save schema.</p></div>';
                    }
            } else {
                echo '<div class="error"><p>Please select a page and enter schema data.</p></div>';
            }
        }
        // Display the form
        $pages = get_pages();
        $selected_page = isset($_GET['page_id']) ? intval($_GET['page_id']) : '';
        $schema_id = isset($_GET['schema_id']) ? intval($_GET['schema_id']) : '';
        $custom_schema = '';        
        if (!empty($schema_id)) {
            $schema = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $schema_id));
            if ($schema) {
                $selected_page = $schema->page_id;
                $custom_schema = $schema->schema_json;
            }
        }
        ?>
        <form method="post" method="">
            <input type="hidden" name="action" value="ms_advanced_schema_submit">
            <input type="hidden" name="schema_id" value="<?php echo esc_attr($schema_id); ?>">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="custom_schema_page_id">Select Page</label></th>
                    <td>
                        <select name="custom_schema_page_id" id="custom_schema_page_id">
                            <option value="">Select a page</option>
                            <?php foreach ($pages as $page) : ?>
                                <option value="<?php echo $page->ID; ?>" <?php selected($selected_page, $page->ID); ?>>
                                    <?php echo $page->post_title; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="custom_schema_type">Schema Type</label></th>
                    <td>
                        <?php
                        $schema_types = [
                                "Action", "Article", "Book", "BreadcrumbList", "Course", "CreativeWork",
                                "Dataset", "Event", "FAQ", "HowTo", "JobPosting", "LocalBusiness", 
                                "MediaObject", "MusicRecording", "NewsArticle", "Offer", "Organization", 
                                "Person", "Place", "Product", "Recipe", "Review", "Service", 
                                "SoftwareApplication", "SpeakableSpecification", "VideoObject"
                            ];
                        ?>
                        <select name="custom_schema_type" id="custom_schema_type">
                            <option value="">Select a type</option>
                            <?php foreach ($schema_types as $type): ?>
                                <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="custom_schema_json">JSON Schema Markup</label></th>
                    <td>
                        <textarea name="custom_schema_json" id="custom_schema_json" rows="30" cols="1" class="large-text" placeholder="Paste JSON schema markup here."><?php echo esc_textarea($custom_schema); ?></textarea>
                        <p class="description">Only JSON schema markup without script tag. Example: &lt;script type="application/ld+json"&gt;&lt;/script&gt;</p>
                    </td>
                </tr>
            </table>
            <?php submit_button($schema_id ? 'Update Schema' : 'Save'); ?>
        </form>
    </div>
    <?php
}
function ms_advanced_schema_edit_schema_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'ms_advanced_schemas';
    $charset_collate = $wpdb->get_charset_collate();
    
    if (!current_user_can('edit_pages')) {
        wp_die('Unauthorized access');
    }

    $edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
    $schema = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $edit_id));

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_schema'])) {
        $type = sanitize_text_field($_POST['custom_schema_type']);
        $schema_json = wp_kses_post($_POST['schema_json']);

        // Update the schema
        $updated = $wpdb->update(
            $table,
            [
                'type' => $type,
                'schema_json' => $schema_json,
                'updated_on' => current_time('mysql')
            ],
            ['id' => $edit_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($updated) {
            echo '<script type="text/javascript">
                     window.location.href="' . admin_url('admin.php?page=ms-advanced-schema&message=updated') . '";
                  </script>';
            exit;
        } else {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>Failed to update schema.</p></div>';
            });
        }
    }
    
    ?>
    <div id="wpbody" role="main">
        <div id="wpbody-content">
            <div class="wrap">
                <h1 class="wp-heading-inline">Edit Schema</h1>
                <form method="post" action="">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="type">Type</label>
                            </th>
                            <td>
                                <?php
                                $schema_types = [
                                        "Action", "Article", "Book", "BreadcrumbList", "Course", "CreativeWork",
                                        "Dataset", "Event", "FAQ", "HowTo", "JobPosting", "LocalBusiness", 
                                        "MediaObject", "MusicRecording", "NewsArticle", "Offer", "Organization", 
                                        "Person", "Place", "Product", "Recipe", "Review", "Service", 
                                        "SoftwareApplication", "SpeakableSpecification", "VideoObject"
                                    ];
                                ?>
                                <select name="custom_schema_type" id="custom_schema_type">
                                    <option value="">Select a type</option>
                                    <?php foreach ($schema_types as $type): ?>
                                        <option value="<?php echo $type; ?>" <?php echo ($schema->type == $type) ? 'selected' : ''; ?>><?php echo $type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="schema_json">Schema JSON</label></th>
                            <td>
                                <textarea name="schema_json" id="schema_json" rows="30" class="large-text"><?php echo stripslashes(esc_textarea($schema->schema_json)); ?></textarea>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Update Schema', 'primary', 'update_schema'); ?>
                </form>
            </div>
        </div>
    </div>
    <?php
}


add_action('wp_head', 'ms_advanced_schema_inject');
function ms_advanced_schema_inject() {
    global $wpdb, $post;
    $table = $wpdb->prefix . 'ms_advanced_schemas';

    if (is_page()) {
        // Retrieve the schema row from the database
        $schema = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE page_id = %d", $post->ID));

        if ($schema && !empty($schema->schema_json)) {

            $clean_schema_json = stripslashes($schema->schema_json);
            $decoded_schema = json_decode($clean_schema_json, true);

            // Check for JSON decoding errors
            if (json_last_error() === JSON_ERROR_NONE) {
                // Output the properly formatted JSON for schema
                echo '<script type="application/ld+json">' . wp_json_encode($decoded_schema, JSON_UNESCAPED_SLASHES) . '</script>';
            } else {
                // Handle the JSON decoding error if any
                echo '<!-- Error decoding schema JSON: ' . json_last_error_msg() . ' -->';
            }
        }
    }
}

