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
        wp_enqueue_style('asm-styles', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], '1.0.0');
        wp_enqueue_script('asm-scripts', plugin_dir_url(__FILE__) . 'assets/js/scripts.js', [], '1.0.0', true);
        wp_enqueue_code_editor(['type' => 'application/json']);
        wp_enqueue_script('wp-theme-plugin-editor');
        wp_enqueue_style('wp-codemirror');
    }

    public function create_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $this->table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            postType varchar(255) NOT NULL,
            postID varchar(255) NOT NULL,
            schemaType varchar(255) NOT NULL,
            schemaJson longtext NOT NULL,
            createdOn datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedOn datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function create_menu() {
        $icon_url = plugins_url('assets/icons/asm-icon.svg', __FILE__);
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
            if ( isset($_GET['_wpnonce']) && !empty($_GET['_wpnonce']) ) {
                $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
                $delete_id = isset($_GET['delete_id']) ? absint(wp_unslash($_GET['delete_id'])) : 0;
                if ( wp_verify_nonce( $nonce, 'delete_schema_' . $delete_id ) ) {
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
                } else {
                    wp_die('Nonce verification failed');
                }
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
                            <?php
                            $table_name = esc_sql($this->table);
                            $query = $this->wpdb->prepare("SELECT * FROM $table_name");
                            $schemas = $this->wpdb->get_results($query);
                            ?>
                            <table class="wp-list-table widefat striped">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Target</th>
                                        <th>Schema Type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($schemas) : ?>
                                        <?php foreach ($schemas as $schema) : ?>
                                            <tr>
                                                <td><?php echo $schema->postType; ?></td>
                                                <?php if ($schema->postID == 'pages') : ?>
                                                    <td>All Pages</td>
                                                <?php else : ?>
                                                    <td><a href="<?php echo the_permalink($schema->postID); ?>" target="_blank"><?php echo get_the_title($schema->postID); ?></a></td>
                                                <?php endif; ?>
                                                <td><?php echo $schema->schemaType; ?></td>
                                                <td>
                                                    <a href="#" class="preview-schema" data-schema="<?php echo esc_attr(stripslashes($schema->schemaJson)); ?>">View</a> |
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
                            <textarea id="code_box" rows="60" cols="1" placeholder='Click "Preview" to see saved schema' readonly></textarea>
                        </div>
                        <!-- <script type="text/javascript">
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
                                        }
                                    } else {
                                        editor.setValue(JSON.stringify(schemaJSON, null, 2));
                                    }
                                });
                            });
                        </script> -->
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
            if (isset($_POST['postType']) && isset($_POST['postID']) && isset($_POST['schemaType']) && isset($_POST['schemaJson'])) {
                if (isset($_POST['save_schema_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['save_schema_nonce'])), 'save_schema_action')) {
                    $postType = sanitize_text_field(wp_unslash($_POST['postType']));
                    $postID = sanitize_text_field(wp_unslash($_POST['postID']));
                    $schemaType = sanitize_text_field(wp_unslash($_POST['schemaType']));
                    $schemaJson = wp_kses_post(wp_unslash($_POST['schemaJson']));
                    if (!empty($postType)  && !empty($postID) && !empty($schemaType) && !empty($schemaJson)) {
                        $inserted = $this->wpdb->insert(
                            $this->table,
                            ['postType' => $postType, 'postID' => $postID, 'schemaType' => $schemaType, 'schemaJson' => $schemaJson]
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
                } else {
                    wp_die('Invalid nonce.');
                }
            }
            $postTypes = get_post_types(array('public' => true), 'objects');
            ?>
            <form method="POST">
                <?php wp_nonce_field('save_schema_action', 'save_schema_nonce'); ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th><label for="postType">Type</label></th>
                            <td>
                                <select id="postType" name="postType">
                                    <option value="">Select type</option>
                                     <?php if (!empty($postTypes)) : ?>
                                        <?php foreach ($postTypes as $postType) : ?>
                                            <?php if (!in_array($postType->name, array('attachment'))) : ?>
                                                <option value="<?php echo esc_attr($postType->name) ?>"><?php echo esc_html($postType->label) ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <p class="description">First select post type.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="postID">Target?</label></th>
                            <td>
                                <select id="postID" name="postID" disabled>
                                    <option value="">Select target</option>
                                </select>
                                <p class="description">Select target Page, Post, or Custom Post Type, where you want your schema to be inserted.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="schemaType">Schema Type</label></th>
                            <td>
                                <?php
                                $schemaTypes = ["Action", "Article", "Book", "BreadcrumbList", "Course", "CreativeWork","Dataset", "Event", "FAQ", "HowTo", "JobPosting", "LocalBusiness", "MediaObject", "MusicRecording", "NewsArticle", "Offer", "Organization", "Person", "Place", "Product", "Recipe", "Review", "Service", "SoftwareApplication", "SpeakableSpecification", "VideoObject"];
                                ?>
                                <select id="schemaType" name="schemaType" disabled>
                                    <option value="">Select type</option>
                                    <?php foreach ($schemaTypes as $schemaType): ?>
                                        <option value="<?php echo $schemaType; ?>"><?php echo $schemaType; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Select schema structured data type to lable your saved schemas.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="schemaJson">JSON-LD Data</label></th>
                            <td>
                                <textarea id="schemaJson" name="schemaJson" rows="10" class="large-text"></textarea>
                                <p class="description">Paste the schema JSON here.</p>
                            </td>
                        </tr>
                        <!-- <script>
                            jQuery(document).ready(function($) {
                                // Code editor
                                var editor = wp.codeEditor.initialize($('#schemaJson'), {
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
                                $('#postType').on('change', function() {
                                    var postType = $(this).val();
                                    $('#postID').prop('disabled', true).html('<option value="">Loading...</option>');
                                    $('#schemaType').prop('disabled', true);
                                    $('#schemaJson').prop('disabled', true);
                                    if (postType) {
                                        $.ajax({
                                            url: ajaxurl,
                                            type: 'POST',
                                            data: {
                                                action: 'get_posts_by_type',
                                                post_type: postType
                                            },
                                            success: function(response) {
                                                var options = '<option value="">Select target</option>';
                                                if (response.success && response.data.length > 0) {
                                                    if (postType == 'page') {
                                                        options += '<option value="pages">All Pages</option>';
                                                    }
                                                    $.each(response.data, function(index, post) {
                                                        options += '<option value="' + post.ID + '">' + post.post_title + '</option>';
                                                    });
                                                } else {
                                                    options = '<option value="">Nothing found</option>';
                                                }
                                                $('#postID').html(options).prop('disabled', false);
                                            },
                                            error: function() {
                                                $('#postID').html('<option value="">Error loading</option>').prop('disabled', false);
                                            }
                                        });
                                    } else {
                                        $('#postID').html('<option value="">Select type first</option>').prop('disabled', true);
                                    }
                                });
                                $('#postID').on('change', function() {
                                    var postID = $(this).val();
                                    $('#schemaType').prop('disabled', true);
                                    $('#schemaJson').prop('disabled', true);
                                    if (postID != '') {
                                        $('#schemaType').prop('disabled', false);
                                    } else {
                                        $('#schemaType').prop('disabled', true);
                                    }
                                });
                                $('#schemaType').on('change', function() {
                                    var schemaType = $(this).val();
                                    $('#schemaJson').prop('disabled', true);
                                    if (schemaType != '') {
                                        $('#schemaJson').prop('disabled', false);
                                    } else {
                                        $('#schemaJson').prop('disabled', true);
                                    }
                                });
                            });
                        </script> -->
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
        $table_name = esc_sql($this->table);
        $schema_id = absint($schema_id);
        $query = $this->wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $schema_id);
        $schema = $this->wpdb->get_row($query);
        if (!$schema) {
            wp_die('Schema not found.');
        }
        if (isset($_POST['postType']) && isset($_POST['postID']) && isset($_POST['schemaType']) && isset($_POST['schemaJson'])) {
            if (isset($_POST['update_schema_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['update_schema_nonce'])), 'update_schema_action')) {
                $postType = sanitize_text_field(wp_unslash($_POST['postType']));
                $postID = sanitize_text_field(wp_unslash($_POST['postID']));
                $schemaType = sanitize_text_field(wp_unslash($_POST['schemaType']));
                $schemaJson = wp_kses_post(wp_unslash($_POST['schemaJson']));
                if (!empty($postType)  && !empty($postID) && !empty($schemaType) && !empty($schemaJson)) {
                    $updated = $this->wpdb->update(
                        $this->table,
                        ['postType' => $postType, 'postID' => $postID, 'schemaType' => $schemaType, 'schemaJson' => $schemaJson],
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
                    echo '<div class="error"><p>All fields are required.</p></div>';
                }
            } else {
                wp_die('Invalid nonce.');
            }
        }
        $postTypes = get_post_types(array('public' => true), 'objects');
        ?>
        <div class="wrap">
            <h1>Edit Schema</h1>
            <form method="POST">
                <?php wp_nonce_field('update_schema_action', 'update_schema_nonce'); ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th><label for="postType">Type</label></th>
                            <td>
                                <select id="postType" name="postType">
                                    <option value="">Select type</option>
                                     <?php if (!empty($postTypes)) : ?>
                                        <?php foreach ($postTypes as $postType) : ?>
                                            <?php if (!in_array($postType->name, array('attachment'))) : ?>
                                                <option value="<?php echo esc_attr($postType->name) ?>" <?php selected($postType->name, $schema->postType) ?>><?php echo esc_html($postType->label) ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <p class="description">First select post type.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="postID">Target?</label></th>
                            <td>
                                <select id="postID" name="postID">
                                    <option value="<?php echo $schema->postID ?>"><?php echo get_the_title($schema->postID) ?></option>
                                </select>
                                <p class="description">Select target Page, Post, or Custom Post Type, where you want your schema to be injected.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="schemaType">Schema Type</label></th>
                            <td>
                                <?php
                                $schemaTypes = ["Action", "Article", "Book", "BreadcrumbList", "Course", "CreativeWork","Dataset", "Event", "FAQ", "HowTo", "JobPosting", "LocalBusiness", "MediaObject", "MusicRecording", "NewsArticle", "Offer", "Organization", "Person", "Place", "Product", "Recipe", "Review", "Service", "SoftwareApplication", "SpeakableSpecification", "VideoObject"];
                                ?>
                                <select id="schemaType" name="schemaType">
                                <option value="">Select type</option>
                                    <?php foreach ($schemaTypes as $schemaType): ?>
                                        <option value="<?php echo $schemaType; ?>" <?php selected($schemaType, $schema->schemaType) ?>><?php echo $schemaType; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Select schema structured data type to lable your saved schemas.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="schemaJson">JSON-LD Data</label></th>
                            <td>
                                <textarea id="schemaJson" name="schemaJson" rows="10" class="large-text"><?php echo stripslashes($schema->schemaJson) ?></textarea>
                                <p class="description">Paste the schema JSON here.</p>
                            </td>
                        </tr>
                        <!-- <script>
                            jQuery(document).ready(function($) {                                
                                // Code editor
                                var editor = wp.codeEditor.initialize($('#schemaJson'), {
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
                                $('#postType').on('change', function() {
                                    var postType = $(this).val();
                                    $('#postID').prop('disabled', true).html('<option value="">Loading...</option>');
                                    $('#schemaType').prop('disabled', true);
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
                                                $('#postID').html(options).prop('disabled', false);
                                            },
                                            error: function() {
                                                $('#postID').html('<option value="">Error loading</option>').prop('disabled', false);
                                            }
                                        });
                                    } else {
                                        $('#postID').html('<option value="">Select type first</option>').prop('disabled', true);
                                    }
                                });
                                $('#postID').on('change', function() {
                                    var postID = $(this).val();
                                    $('#schemaType').prop('disabled', true);
                                    if (postID != '') {
                                        $('#schemaType').prop('disabled', false);
                                    } else {
                                        $('#schemaType').prop('disabled', true);
                                    }
                                });
                                // $('#schemaType').on('change', function() {
                                //     var schemaType = $(this).val();
                                //     $('#schemaJson').prop('disabled', true);
                                //     if (schemaType != '') {
                                //         $('#schemaJson').prop('disabled', false);
                                //     } else {
                                //         $('#schemaJson').prop('disabled', true);
                                //     }
                                // });
                            });
                        </script> -->
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
            $post_type = sanitize_text_field(wp_unslash($_POST['post_type']));
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
        if (is_page()) {
            echo "\n<!-- Schema structured data added by Advanced Schema Manager WP plugin developed by Muhammad Shoaib -->\n";
            $postID = 'pages';
            $table_name = esc_sql($this->table);
            $query = $this->wpdb->prepare("SELECT * FROM $table_name WHERE postID = %s", $postID);
            $schemas = $this->wpdb->get_results($query);
            if ($schemas) {
                foreach ($schemas as $schema) {
                    $clean_schemaJson = stripslashes($schema->schemaJson);
                    $decoded_schema = json_decode($clean_schemaJson, true);
                    echo "<script type=\"application/ld+json\">" . wp_json_encode($decoded_schema, JSON_UNESCAPED_SLASHES) . "</script>\n";
                }
            }
            $postID = get_the_ID();
            $table_name = esc_sql($this->table);
            $postID = absint($postID);
            $query = $this->wpdb->prepare("SELECT * FROM $table_name WHERE postID = %d", $postID);
            $schemas = $this->wpdb->get_results($query);
            if ($schemas) {
                foreach ($schemas as $schema) {
                    $clean_schemaJson = stripslashes($schema->schemaJson);
                    $decoded_schema = json_decode($clean_schemaJson, true);
                    echo "<script type=\"application/ld+json\">" . wp_json_encode($decoded_schema, JSON_UNESCAPED_SLASHES) . "</script>\n";
                }
            }
            echo "\n";
        } elseif (is_single()) {
            echo "\n<!-- Schema structured data added by Advanced Schema Manager WP plugin developed by Muhammad Shoaib -->\n";
            $postID = get_the_ID();
            $table_name = esc_sql($this->table);
            $postID = absint($postID);
            $query = $this->wpdb->prepare("SELECT * FROM $table_name WHERE postID = %d", $postID);
            $schemas = $this->wpdb->get_results($query);
            if ($schemas) {
                foreach ($schemas as $schema) {
                    $clean_schemaJson = stripslashes($schema->schemaJson);
                    $decoded_schema = json_decode($clean_schemaJson, true);
                    echo "<script type=\"application/ld+json\">" . wp_json_encode($decoded_schema, JSON_UNESCAPED_SLASHES) . "</script>\n";
                }
            }
            echo "\n";
        }
    }
}
new ASMPlugin();
?>
