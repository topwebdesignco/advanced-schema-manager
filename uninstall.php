<?php
/**
 * @package ASMPlugin
 */

 defined('WP_UNINSTALL_PLUGIN') or die;

 global $wpdb;
 $table = $wpdb->prefix . 'asm_schemas';
 $sql = "DROP TABLE IF EXISTS $table;";
 $wpdb->query($sql);