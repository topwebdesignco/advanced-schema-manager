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

defined('ABSPATH') or die();

add_action('admin_enqueue_scripts', 'asm_enqueue_styles');
function asm_enqueue_styles() {
    wp_enqueue_style('asm-styles', plugin_dir_url(__FILE__) . 'style.css', array(), time());
    wp_enqueue_code_editor(array('type' => 'application/json'));
    wp_enqueue_script('wp-theme-plugin-editor');
    wp_enqueue_style('wp-codemirror');
}

register_activation_hook(__FILE__, 'asm_create_table');
function asm_create_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'asm_schemas';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        page_id bigint(20) NOT NULL,
        schema_type varchar(255) NOT NULL,
        schema_json longtext NOT NULL,
        created_on datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_on datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// register_deactivation_hook(__FILE__, 'asm_delete_table');
register_uninstall_hook(__FILE__, 'asm_delete_table');
function asm_delete_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'asm_schemas';
    $sql = "DROP TABLE IF EXISTS $table;";
    
    $wpdb->query($sql);
}

add_action('admin_menu', 'asm_create_menu');
function asm_create_menu() {
    $icon_url = plugins_url('icons/asm-icon.svg', __FILE__);
    add_menu_page('Schema Manager', 'Schema Manager', 'edit_pages',  'asm-home', 'asm_all_schemas_page', $icon_url, 11);
    add_submenu_page('asm-home', 'All Schemas', 'All Schemas', 'edit_pages', 'asm-home', 'asm_all_schemas_page');
    add_submenu_page('asm-home', 'Add New Schema', 'Add New Schema', 'edit_pages', 'asm-add-schema', 'asm_add_schema_page');
    add_submenu_page('asm-home', 'Edit Schema', '', 'edit_pages', 'asm-edit-schema', 'asm_edit_schema_page');
}

add_action('admin_menu', 'asm_set_active_menu');
function asm_set_active_menu() {
    global $parent_file, $submenu_file, $pagenow;

    // Check if we're on the "Edit Schema" page
    if (isset($_GET['page']) && $_GET['page'] === 'asm-edit-schema') {
        // Set parent file to "All Schemas"
        $parent_file = 'asm-home';
        // Set submenu file to "All Schemas" so it's highlighted
        $submenu_file = 'asm-home';
    }
}

function asm_all_schemas_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'asm_schemas';
    
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
                     window.location.href="' . admin_url('admin.php?page=asm-home&message=deleted') . '";
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
                <a class="page-title-action" href="?page=asm-add-schema">Add New Schema</a>
                <hr class="wp-header-end">
                <div class="asm flex-container">
                    <div class="table-column">
                        <?php $schemas = $wpdb->get_results("SELECT * FROM $table"); ?>
                        <table class="wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th>Target Page</th>
                                    <th>Schema Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($schemas) : ?>
                                    <?php foreach ($schemas as $schema) : ?>
                                        <tr>
                                            <td><a href="#"><?php echo ($schema->page_id == -1) ? 'All Pages' : get_the_title($schema->page_id); ?></a></td>
                                            <td><?php echo stripslashes($schema->schema_type); ?></td>
                                            <td>
                                                <?php $json = stripslashes($schema->schema_json); ?>
                                                <a href="#" class="preview-schema" data-schema="<?php echo esc_attr(stripslashes($schema->schema_json)); ?>">Preview</a> |
                                                <a href="?page=asm-edit-schema&edit_id=<?php echo $schema->id; ?>">Edit</a> |
                                                <a href="<?php echo wp_nonce_url('?page=asm-home&delete_id=' . $schema->id, 'delete_schema_' . $schema->id); ?>" onclick="return confirm('Are you sure you want to delete this schema?');">Delete</a>
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
                        <textarea id="preview_box" rows="40" cols="1" placeholder='Click "Preview" to see saved schema' readonly></textarea>
                    </div>
                    <script type="text/javascript">
                        jQuery(document).ready(function($) {
                            // Initialize CodeMirror
                            var editor = wp.codeEditor.initialize($('#preview_box'), {
                                codemirror: {
                                    lineNumbers: true,
                                    mode: 'application/json',
                                    readOnly: true
                                }
                            }).codemirror;

                            // Handle click event on preview links
                            $('.preview-schema').on('click', function(e) {
                                e.preventDefault();
                                // Get the schema JSON from data attribute
                                var schemaJSON = $(this).data('schema');
                                // Check if the schemaJSON is already an object or needs parsing
                                if (typeof schemaJSON === 'string') {
                                    try {
                                        // Parse and re-stringify to format the JSON nicely for the editor
                                        var parsedJSON = JSON.stringify(JSON.parse(schemaJSON), null, 2);
                                        editor.setValue(parsedJSON);
                                    } catch (e) {
                                        // If parsing fails, set the raw value
                                        console.error('Error parsing JSON:', e);
                                        editor.setValue(schemaJSON);
                                    }
                                } else {
                                    // If schemaJSON is already an object, re-stringify it
                                    editor.setValue(JSON.stringify(schemaJSON, null, 2));
                                }
                            });
                        });
                    </script>
                </div>
            </div>
        </div>
    </div>    
    <?php
}
function asm_add_schema_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'asm_schemas';

    if (!current_user_can('edit_pages')) {
        wp_die('Unauthorized access');
    }
    ?>    
    <div class="wrap">
        <h1>Add New Schema</h1>
        <?php
        // Insert data in to DB
        if (isset($_POST['schema_json']) && isset($_POST['page_id'])) {
            $page_id = sanitize_text_field($_POST['page_id']);
            $schema_type = sanitize_text_field($_POST['schema_type']);
            $schema_json = wp_kses_post($_POST['schema_json']);
            // Ensure the page ID and schema JSON are not empty
            if (!empty($schema_type) && !empty($schema_json)) {
                // Insert new schema
                $inserted = $wpdb->insert(
                    $table,
                    array('page_id' => $page_id, 'schema_type' => $schema_type, 'schema_json' => $schema_json)
                );
                // Display message
                if ($inserted) {
                    // echo '<div class="updated"><p>Schema saved successfully.</p></div>';
                    echo '<script type="text/javascript">
                                window.location.href="' . admin_url('admin.php?page=asm-home&message=saved') . '";
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
        $page_id = isset($_GET['page_id']) ? intval($_GET['page_id']) : '';
        $schema_id = isset($_GET['schema_id']) ? intval($_GET['schema_id']) : '';
        $custom_schema = '';        
        if (!empty($schema_id)) {
            $schema = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $schema_id));
            if ($schema) {
                $page_id = $schema->page_id;
                $custom_schema = $schema->schema_json;
            }
        }
        ?>
        <form method="post" method="">
            <input type="hidden" name="action" value="asm_schema_submit">
            <input type="hidden" name="schema_id" value="<?php echo esc_attr($schema_id); ?>">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="page_id">Select Page</label></th>
                    <td>
                        <select name="page_id" id="page_id">
                            <option value="">Select a page</option>
                            <option value="-1" <?php selected($page_id, 0); ?>>All Pages</option>
                            <?php foreach ($pages as $page) : ?>
                                <option value="<?php echo $page->ID; ?>" <?php selected($page_id, $page->ID); ?>>
                                    <?php echo $page->post_title; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="schema_type">Schema Type</label></th>
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
                        <select name="schema_type" id="schema_type">
                            <option value="">Select a type</option>
                            <?php foreach ($schema_types as $schema_type): ?>
                                <option value="<?php echo $schema_type; ?>"><?php echo $schema_type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="schema_json">JSON Schema Markup</label></th>
                    <td>
                        <textarea name="schema_json" id="schema_json" rows="30" cols="1" class="large-text" placeholder="Paste JSON schema markup here."><?php echo esc_textarea($custom_schema); ?></textarea>
                        <p class="description">Only JSON schema markup without script tag. Example: &lt;script type="application/ld+json"&gt;&lt;/script&gt;</p>
                    </td>
                </tr>
            </table>
            <?php submit_button($schema_id ? 'Update Schema' : 'Save'); ?>
        </form>
    </div>
    <?php
}
function asm_edit_schema_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'asm_schemas';
    $charset_collate = $wpdb->get_charset_collate();
    
    if (!current_user_can('edit_pages')) {
        wp_die('Unauthorized access');
    }

    $edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
    $schema = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $edit_id));

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_schema'])) {
        $schema_type = sanitize_text_field($_POST['schema_type']);
        $schema_json = wp_kses_post($_POST['schema_json']);

        // Update the schema
        $updated = $wpdb->update(
            $table,
            [
                'type' => $schema_type,
                'schema_json' => $schema_json,
                'updated_on' => current_time('mysql')
            ],
            ['id' => $edit_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($updated) {
            echo '<script type="text/javascript">
                     window.location.href="' . admin_url('admin.php?page=asm-home&message=updated') . '";
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
                                <select name="schema_type" id="schema_type">
                                    <option value="">Select a type</option>
                                    <?php foreach ($schema_types as $schema_type): ?>
                                        <option value="<?php echo $schema_type; ?>" <?php echo ($schema->type == $schema_type) ? 'selected' : ''; ?>><?php echo $schema_type; ?></option>
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

add_action('wp_head', 'asm_inject_schema');
function asm_inject_schema() {
    global $wpdb, $post;
    $table = $wpdb->prefix . 'asm_schemas';

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

