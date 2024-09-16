<?php
/**
 * Advanced Schema Manager - WordPress Plugin
 *
 * @package         ASMPlugin
 * @author            Muhammad Shoaib
 * @copyright      2024 Muhammad Shoaib
 * @license           GPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * @wordpress-plugin
 * Plugin Name:       Advanced Schema Manager
 * Plugin URI:        https://github.com/topwebdesignco/advanced-schema-manager
 * Description:       Manage your custom built JSON-LD schema types for your WordPress website. This plugin automates schemas injection into selected pages.
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

class ASMPlugin {

    private $wpdb;
    private $table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'asm_schemas';
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
        register_activation_hook(__FILE__, [$this, 'create_table']);
        add_action('admin_menu', [$this, 'create_menu']);
        add_action('admin_menu', [$this, 'set_active_menu']);
        add_action('wp_ajax_get_posts_by_type', [$this, 'get_posts_by_type']);
        add_action('wp_head', [$this, 'inject_schema']);
    }

    public function enqueue_styles() {
        wp_enqueue_style('asm-styles', plugin_dir_url(__FILE__) . 'style.css', [], '1.0.0');
        wp_enqueue_code_editor(['type' => 'application/json']);
        wp_enqueue_script('wp-theme-plugin-editor');
        wp_enqueue_style('wp-codemirror');
    }

    public function create_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $this->table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_type varchar(255) NOT NULL,
            post_id varchar(255) NOT NULL,
            schema_type varchar(255) NOT NULL,
            schema_json longtext NOT NULL,
            created_on datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_on datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function create_menu() {
        $icon_url = plugins_url('icons/asm-icon.svg', __FILE__);
        add_menu_page('Schema Manager', 'Schema Manager', 'edit_pages',  'asm-home', [$this, 'all_schemas_page'], $icon_url, 11);
        add_submenu_page('asm-home', 'All Schemas', 'All Schemas', 'edit_pages', 'asm-home', [$this, 'all_schemas_page']);
        add_submenu_page('asm-home', 'Add New Schema', 'Add New Schema', 'edit_pages', 'asm-add-schema', [$this, 'add_schema_page']);
        add_submenu_page('asm-home', 'Edit Schema', '', 'edit_pages', 'asm-edit-schema', [$this, 'edit_schema_page']);
    }

    public function set_active_menu() {
        global $parent_file, $submenu_file, $pagenow;

        if (isset($_GET['page']) && $_GET['page'] === 'asm-edit-schema') {
            $parent_file = 'asm-home';
            $submenu_file = 'asm-home';
        }
    }

    public function all_schemas_page() {
        if (!current_user_can('edit_pages')) {
            wp_die('Unauthorized access');
        }

        if (isset($_GET['delete_id'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_schema_' . $_GET['delete_id'])) {
                wp_die('Nonce verification failed');
            }

            $delete_id = intval($_GET['delete_id']);
            $deleted = $this->wpdb->delete($this->table, ['id' => $delete_id], ['%d']);

            if ($deleted) {
                echo '<script type="text/javascript">
                         window.location.href="' . admin_url('admin.php?page=asm-home&message=deleted') . '";
                      </script>';
                exit;
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="error"><p>Failed to delete schema.</p></div>';
                });
            }
        }
        ?>
        <div id="wpbody" role="main">
            <div id="wpbody-content">
                <div class="wrap">
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
                            <?php $schemas = $this->wpdb->get_results("SELECT * FROM $this->table"); ?>
                            <table class="wp-list-table widefat striped">
                                <thead>
                                    <tr>
                                        <th>Post Type</th>
                                        <th>Post Title</th>
                                        <th>Schema Type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($schemas) : ?>
                                        <?php foreach ($schemas as $schema) : ?>
                                            <tr>
                                                <td><?php echo $schema->post_type; ?></td>
                                                <td><?php echo get_the_title($schema->post_id); ?></td>
                                                <td><?php echo $schema->schema_type; ?></td>
                                                <td>
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
                            <div id="visual_box" style="margin-bottom: 20px; padding: 10px 0px 10px 0px; background: #f9f9f9; border: 1px solid #ddd;"></div>
                            <textarea id="code_box" rows="60" cols="1" placeholder='Click "Preview" to see saved schema' readonly></textarea>
                        </div>
                        <script type="text/javascript">
                            jQuery(document).ready(function($) {
                                var editor = wp.codeEditor.initialize($('#code_box'), {
                                    codemirror: {
                                        lineNumbers: true,
                                        mode: 'application/json',
                                        readOnly: true
                                    }
                                }).codemirror;
                                var numberOfRows = 35;
                                var lineHeight = 20;
                                editor.setSize(null, numberOfRows * lineHeight + "px");

                                $('.preview-schema').on('click', function(e) {
                                    e.preventDefault();
                                    var schemaJSON = $(this).data('schema');
                                    if (typeof schemaJSON === 'string') {
                                        try {
                                            var parsedJSON = JSON.parse(schemaJSON);
                                            var formattedJSON = JSON.stringify(parsedJSON, null, 2);
                                            editor.setValue(formattedJSON);
                                        } catch (e) {
                                            console.error('Error parsing JSON:', e);
                                            editor.setValue(schemaJSON);
                                            $('#visual_box').html('<em>Error parsing schema for preview.</em>');
                                        }
                                    } else {
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

    public function add_schema_page() {
        if (!current_user_can('edit_pages')) {
            wp_die('Unauthorized access');
        }
        ?>    
        <div class="wrap">
            <h1>Add New Schema</h1>
            <?php
            var_dump($_POST['post_id'], $_POST['schema_json']);
            if (isset($_POST['post_id']) && isset($_POST['schema_json'])) {
                $post_type = sanitize_text_field($_POST['post_type']);
                $post_id = sanitize_text_field($_POST['post_id']);
                $schema_type = sanitize_text_field($_POST['schema_type']);
                $schema_json = wp_kses_post($_POST['schema_json']);
                if (!empty($post_type)  && !empty($post_id) && !empty($schema_type) && !empty($schema_json)) {
                    $inserted = $this->wpdb->insert(
                        $this->table,
                        ['post_type' => $post_type, 'post_id' => $post_id, 'schema_type' => $schema_type, 'schema_json' => $schema_json]
                    );
                    if ($inserted) {
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
            $post_types = get_post_types(array('public' => true), 'objects');
            // $page_id = isset($_GET['page_id']) ? intval($_GET['page_id']) : -1;
            ?>
            <form method="POST">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th><label for="post_type">Type</label></th>
                            <td>
                                <select id="post_type" name="post_type">
                                    <option value="">Select type</option>
                                    <!-- need to fetch custom post types and inject with loop -->
                                     <?php if (!empty($post_types)) : ?>
                                        <?php foreach ($post_types as $post_type) : ?>
                                            <?php if (!in_array($post_type->name, array('attachment'))) : ?>
                                                <option value="<?php echo esc_attr($post_type->name) ?>"><?php echo esc_html($post_type->label) ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <p class="description">First select type to get list from database.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="post_id">Target?</label></th>
                            <td>
                                <select id="post_id" name="post_id" disabled>
                                    <option value="">Select target</option>
                                </select>
                                <p class="description">Select target Page, Post, or Custom Post Type, where you want your schema to be inserted.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="schema_type">Schema Type</label></th>
                            <td>
                                <?php
                                $schema_types = ["Action", "Article", "Book", "BreadcrumbList", "Course", "CreativeWork","Dataset", "Event", "FAQ", "HowTo", "JobPosting", "LocalBusiness", "MediaObject", "MusicRecording", "NewsArticle", "Offer", "Organization", "Person", "Place", "Product", "Recipe", "Review", "Service", "SoftwareApplication", "SpeakableSpecification", "VideoObject"];
                                ?>
                                <select id="schema_type" name="schema_type" disabled>
                                    <option value="">Select type</option>
                                    <?php foreach ($schema_types as $schema_type): ?>
                                        <option value="<?php echo $schema_type; ?>"><?php echo $schema_type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Select schema structured data type to lable your saved schemas.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="schema_json">JSON-LD Data</label></th>
                            <td>
                                <textarea id="schema_json" name="schema_json" rows="10" class="large-text"  disabled></textarea>
                                <p class="description">Paste the schema JSON here.</p>
                            </td>
                        </tr>
                        <script>
                            jQuery(document).ready(function($) {                                
                                // Code editor
                                var editor = wp.codeEditor.initialize($('#schema_json'), {
                                    codemirror: {
                                        lineNumbers: true,
                                        mode: 'application/json',
                                        readOnly: false
                                    }
                                }).codemirror;
                                var numberOfRows = 22;
                                var lineHeight = 20;
                                editor.setSize(null, numberOfRows * lineHeight + "px");
                                // drop down boxes
                                $('#post_type').on('change', function() {
                                    var postType = $(this).val();
                                    $('#post_id').prop('disabled', true).html('<option value="">Loading...</option>');
                                    $('#schema_type').prop('disabled', true);
                                    $('#schema_json').prop('disabled', true);
                                    if (postType) {
                                        $.ajax({
                                            url: ajaxurl,
                                            type: 'POST',
                                            data: {
                                                action: 'get_posts_by_type',
                                                post_type: postType
                                            },
                                            success: function(response) {
                                                var options = '<option value="">Select a target</option>';
                                                if (response.success && response.data.length > 0) {
                                                    $.each(response.data, function(index, post) {
                                                        options += '<option value="' + post.ID + '">' + post.post_title + '</option>';
                                                    });
                                                } else {
                                                    options = '<option value="">Nothing found</option>';
                                                }
                                                $('#post_id').html(options).prop('disabled', false);
                                            },
                                            error: function() {
                                                $('#post_id').html('<option value="">Error loading</option>').prop('disabled', false);
                                            }
                                        });
                                    } else {
                                        $('#post_id').html('<option value="">Select type first</option>').prop('disabled', true);
                                    }
                                });
                                $('#post_id').on('change', function() {
                                    var postID = $(this).val();
                                    $('#schema_type').prop('disabled', true);
                                    $('#schema_json').prop('disabled', true);
                                    if (postID != '') {
                                        $('#schema_type').prop('disabled', false);
                                    } else {
                                        $('#schema_type').prop('disabled', true);
                                    }
                                });
                                $('#schema_type').on('change', function() {
                                    var schemaType = $(this).val();
                                    $('#schema_json').prop('disabled', true);
                                    if (schemaType != '') {
                                        $('#schema_json').prop('disabled', false);
                                    } else {
                                        $('#schema_json').prop('disabled', true);
                                    }
                                });
                            });
                        </script>
                    </tbody>
                </table>
                <?php submit_button('Save Schema'); ?>
            </form>
        </div>
        <?php
    }

    public function edit_schema_page() {
        if (!current_user_can('edit_pages')) {
            wp_die('Unauthorized access');
        }
        $schema_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
        if (!$schema_id) {
            wp_redirect(admin_url('admin.php?page=asm-home'));
            exit;
        }

        $schema = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $this->table WHERE id = %d", $schema_id));
        if (!$schema) {
            wp_die('Schema not found.');
        }

        if (isset($_POST['schema_json']) && isset($_POST['page_id'])) {
            $page_id = sanitize_text_field($_POST['page_id']);
            $schema_type = sanitize_text_field($_POST['schema_type']);
            $schema_json = wp_kses_post($_POST['schema_json']);
            if (!empty($schema_type) && !empty($schema_json)) {
                $updated = $this->wpdb->update(
                    $this->table,
                    ['page_id' => $page_id, 'schema_type' => $schema_type, 'schema_json' => $schema_json],
                    ['id' => $schema_id]
                );
                if ($updated !== false) {
                    echo '<script type="text/javascript">
                                window.location.href="' . admin_url('admin.php?page=asm-home&message=updated') . '";
                            </script>';
                    exit;
                } else {
                    echo '<div class="error"><p>Failed to update schema.</p></div>';
                }
            } else {
                echo '<div class="error"><p>Please select a page and enter schema data.</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1>Edit Schema</h1>
            <form method="POST">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th><label for="page_id">Select Page</label></th>
                            <td>
                                <select id="page_id" name="page_id">
                                    <?php $pages = get_pages(); ?>
                                    <option value="-1" <?php selected($schema->page_id, -1); ?>>All Pages</option>
                                    <?php foreach ($pages as $page) : ?>
                                        <option value="<?php echo $page->ID; ?>" <?php selected($schema->page_id, $page->ID); ?>><?php echo $page->post_title; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="schema_type">Slect Schema Type</label></th>
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
                                        <option value="<?php echo $schema_type; ?>" <?php selected($schema->schema_type, $schema_type); ?>><?php echo $schema_type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="schema_json">Schema JSON</label></th>
                            <td>
                                <textarea id="code_box" name="schema_json" rows="10" class="large-text"><?php echo esc_textarea(stripslashes($schema->schema_json)); ?></textarea>
                            </td>
                            <script type="text/javascript">
                                jQuery(document).ready(function($) {
                                    var editor = wp.codeEditor.initialize($('#code_box'), {
                                        codemirror: {
                                            lineNumbers: true,
                                            mode: 'application/json',
                                            readOnly: false
                                        }
                                    }).codemirror;
                                    var numberOfRows = 25;
                                    var lineHeight = 20;
                                    editor.setSize(null, numberOfRows * lineHeight + "px");

                                    $('.preview-schema').on('click', function(e) {
                                        e.preventDefault();
                                        var schemaJSON = $(this).data('schema');
                                        if (typeof schemaJSON === 'string') {
                                            try {
                                                var parsedJSON = JSON.stringify(JSON.parse(schemaJSON), null, 2);
                                                editor.setValue(parsedJSON);
                                            } catch (e) {
                                                console.error('Error parsing JSON:', e);
                                                editor.setValue(schemaJSON);
                                            }
                                        } else {
                                            editor.setValue(JSON.stringify(schemaJSON, null, 2));
                                        }
                                    });
                                });
                            </script>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button('Update Schema'); ?>
            </form>
        </div>
        <?php
    }

    public function get_posts_by_type() {
        // Check for the post_type parameter
        if (isset($_POST['post_type'])) {
            $post_type = sanitize_text_field($_POST['post_type']);    
            $posts = get_posts(array(
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'post_status' => 'publish',
            ));    
            if (!empty($posts)) {
                $response = array();
                foreach ($posts as $post) {
                    $response[] = array(
                        'ID' => $post->ID,
                        'post_title' => $post->post_title,
                    );
                }    
                wp_send_json_success($response);
            } else {
                wp_send_json_error('Nothing found');
            }
        } else {
            wp_send_json_error('Invalid type');
        }    
        wp_die();
    }

    public function inject_schema() {
        if (!is_page() && !is_single()) return;
        echo "\n<!-- Schema structured data added by Advanced Schema Manager WordPress plugin developed by Muhammad Shoaib -->\n";
        $schemas = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM $this->table WHERE page_id = -1"));
        if ($schemas) {
            foreach ($schemas as $schema) {
                $clean_schema_json = stripslashes($schema->schema_json);
                $decoded_schema = json_decode($clean_schema_json, true);
                echo "<script type=\"application/ld+json\">" . wp_json_encode($decoded_schema, JSON_UNESCAPED_SLASHES) . "</script>\n";
            }
        }
        $page_id = get_the_ID();
        $schemas = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM $this->table WHERE page_id = %d", $page_id));
        if ($schemas) {
            foreach ($schemas as $schema) {
                $clean_schema_json = stripslashes($schema->schema_json);
                $decoded_schema = json_decode($clean_schema_json, true);
                echo "<script type=\"application/ld+json\">" . wp_json_encode($decoded_schema, JSON_UNESCAPED_SLASHES) . "</script>\n";
            }
        }
        echo "\n";
    }
}
new ASMPlugin();
?>
