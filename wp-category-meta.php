<?php
/*
 * Plugin Name: wp-category-meta
 * Plugin URI: #
 * Description: Add the ability to attach meta to the Wordpress categories
 * Version: 0.0.1
 * Author: Eric Le Bail
 * Author URI: #
 *
 * This plugin has been developped and tested with Wordpress Version 2.6
 *
 * Copyright 2009  Eric Le Bail (email : eric_lebail@hotmail.com)
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 *
 */
// Please configure the meta you want to display here:
// you can confgure a list of meta with
// 'meta name' => 'meta type'
// type 'text' display a single line text input.
// type 'textarea' display a multi line texte area input.
// Any other type will be ingnored.
$metaList = array(
	'keyword' => 'text',
	'description' => 'text',
	'long-description' => 'textarea'
	); 

    // Initialization and Hooks
    global $wpdb;
    global $wptm_version;
    global $wptm_db_version;
    global $wptm_table_name;
    $wptm_version = '0.0.1';
    $wptm_db_version = '0.0.1';
    $wptm_table_name = $wpdb->prefix.'termsmeta';

    register_activation_hook(__FILE__,'wptm_install');
    register_deactivation_hook(__FILE__,'wptm_uninstall');

    // Actions
    add_action('init', 'wptm_init');
    add_action('create_category', 'wptm_save_meta_tags');
    add_action('edit_category', 'wptm_save_meta_tags');
    add_action('delete_category', 'wptm_delete_meta_tags');
    add_action('edit_category_form', 'wptm_add_meta_textinput');

    /**
     * Function called when installing or updgrading the plugin.
     * @return void.
     */
    function wptm_install()
    {
        global $wpdb;
        global $wptm_table_name;
        global $wptm_db_version;

        // create table on first install
        if($wpdb->get_var("show tables like '$wptm_table_name'") != $wptm_table_name) {

            wptm_createTable($wpdb, $wptm_table_name);
            add_option("wptm_db_version", $wptm_db_version);
        }

        // On plugin update only the version nulmber is updated.
        $installed_ver = get_option( "wptm_db_version" );
        if( $installed_ver != $wptm_db_version ) {

            update_option( "wptm_db_version", $wptm_db_version );
        }

    }

    /**
     * Function called when un-installing the plugin.
     * @return void.
     */
    function wptm_uninstall()
    {
        global $wpdb;
        global $wptm_table_name;

        // delete table
        if($wpdb->get_var("show tables like '$wptm_table_name'") == $wptm_table_name) {

            wptm_dropTable($wpdb, $wptm_table_name);
        }
        delete_option("wptm_db_version");
    }

    /**
     * Function that creates the wptm table.
     *
     * @param $wpdb : database manipulation object.
     * @param $table_name : name of the table to create.
     * @return void.
     */
    function wptm_createTable($wpdb, $table_name)
    {
        $sql = "CREATE TABLE  ".$table_name." (
          meta_id bigint(20) NOT NULL auto_increment,
          terms_id bigint(20) NOT NULL default '0',
          meta_key varchar(255) default NULL,
          meta_value longtext,
          PRIMARY KEY  (`meta_id`),
          KEY `terms_id` (`terms_id`),
          KEY `meta_key` (`meta_key`)
        ) ENGINE=MyISAM AUTO_INCREMENT=6887 DEFAULT CHARSET=utf8;";

        $results = $wpdb->query($sql);
    }

    /**
     * Function that drops the plugin table.
     *
     * @param $wpdb : database manipulation object.
     * @param $table_name : name of the table to create.
     * @return void.
     */
    function wptm_dropTable($wpdb, $table_name)
    {
        $sql = "DROP TABLE  ".$table_name." ;";

        $results = $wpdb->query($sql);
    }

    /**
     * Function that initialise the plugin.
     * It loads the translation files.
     *
     * @return void.
     */
    function wptm_init() {
        if (function_exists('load_plugin_textdomain')) {
            load_plugin_textdomain('wp-category-meta', 'wp-content/plugins/wp-category-meta');
        }
    }

    /**
     * add_terms_meta() - adds metadata for terms
     *
     *
     * @param int $terms_id terms (category/tag...) ID
     * @param string $key The meta key to add
     * @param mixed $value The meta value to add
     * @param bool $unique whether to check for a value with the same key
     * @return bool
     */
    function add_terms_meta($terms_id, $meta_key, $meta_value, $unique = false) {

        global $wpdb;
        global $wptm_table_name;

        // expected_slashed ($meta_key)
        $meta_key = stripslashes( $meta_key );
        $meta_value = stripslashes( $meta_value );

        if ( $unique && $wpdb->get_var( $wpdb->prepare( "SELECT meta_key FROM $wptm_table_name WHERE meta_key = %s AND terms_id = %d", $meta_key, $terms_id ) ) )
        return false;

        $meta_value = maybe_serialize($meta_value);

        $wpdb->insert( $wptm_table_name, compact( 'terms_id', 'meta_key', 'meta_value' ) );

        wp_cache_delete($terms_id, 'terms_meta');

        return true;
    }

    /**
     * delete_terms_meta() - delete terms metadata
     *
     *
     * @param int $terms_id terms (category/tag...) ID
     * @param string $key The meta key to delete
     * @param mixed $value
     * @return bool
     */
    function delete_terms_meta($terms_id, $key, $value = '') {

        global $wpdb;
        global $wptm_table_name;

        // expected_slashed ($key, $value)
        $key = stripslashes( $key );
        $value = stripslashes( $value );

        if ( empty( $value ) )
        {
            $sql1 = $wpdb->prepare( "SELECT meta_id FROM $wptm_table_name WHERE terms_id = %d AND meta_key = %s", $terms_id, $key );
            $meta_id = $wpdb->get_var( $sql1 );
        } else {
            $sql2 = $wpdb->prepare( "SELECT meta_id FROM $wptm_table_name WHERE terms_id = %d AND meta_key = %s AND meta_value = %s", $terms_id, $key, $value );
            $meta_id = $wpdb->get_var( $sql2 );
        }

        if ( !$meta_id )
        return false;

        if ( empty( $value ) )
        $wpdb->query( $wpdb->prepare( "DELETE FROM $wptm_table_name WHERE terms_id = %d AND meta_key = %s", $terms_id, $key ) );
        else
        $wpdb->query( $wpdb->prepare( "DELETE FROM $wptm_table_name WHERE terms_id = %d AND meta_key = %s AND meta_value = %s", $terms_id, $key, $value ) );

        wp_cache_delete($terms_id, 'terms_meta');

        return true;
    }

    /**
     * get_terms_meta() - Get a terms meta field
     *
     *
     * @param int $terms_id terms (category/tag...) ID
     * @param string $key The meta key to retrieve
     * @param bool $single Whether to return a single value
     * @return mixed The meta value or meta value list
     */
    function get_terms_meta($terms_id, $key, $single = false) {

        $terms_id = (int) $terms_id;

        $meta_cache = wp_cache_get($terms_id, 'terms_meta');

        if ( !$meta_cache ) {
            update_termsmeta_cache($terms_id);
            $meta_cache = wp_cache_get($terms_id, 'terms_meta');
        }

        if ( isset($meta_cache[$key]) ) {
            if ( $single ) {
                return maybe_unserialize( $meta_cache[$key][0] );
            } else {
                return array_map('maybe_unserialize', $meta_cache[$key]);
            }
        }

        return '';
    }

    /**
     * get_all_terms_meta() - Get all meta fields for a terms (category/tag...)
     *
     *
     * @param int $terms_id terms (category/tag...) ID
     * @return array The meta (key => value) list
     */
    function get_all_terms_meta($terms_id) {

        $terms_id = (int) $terms_id;

        $meta_cache = wp_cache_get($terms_id, 'terms_meta');

        if ( !$meta_cache ) {
            update_termsmeta_cache($terms_id);
            $meta_cache = wp_cache_get($terms_id, 'terms_meta');
        }

        return maybe_unserialize( $meta_cache );

    }

    /**
     * update_terms_meta() - Update a terms meta field
     *
     *
     * @param int $terms_id terms (category/tag...) ID
     * @param string $key The meta key to update
     * @param mixed $value The meta value to update
     * @param mixed $prev_value previous value (for differentiating between meta fields with the same key and terms ID)
     * @return bool
     */
    function update_terms_meta($terms_id, $meta_key, $meta_value, $prev_value = '') {

        global $wpdb;
        global $wptm_table_name;

        // expected_slashed ($meta_key)
        $meta_key = stripslashes( $meta_key );
        $meta_value = stripslashes( $meta_value );

        if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT meta_key FROM $wptm_table_name WHERE meta_key = %s AND terms_id = %d", $meta_key, $terms_id ) ) ) {
            return add_post_meta($terms_id, $meta_key, $meta_value);
        }

        $meta_value = maybe_serialize($meta_value);

        $data  = compact( 'meta_value' );
        $where = compact( 'meta_key', 'terms_id' );

        if ( !empty( $prev_value ) ) {
            $prev_value = maybe_serialize($prev_value);
            $where['meta_value'] = $prev_value;
        }

        $wpdb->update( $wptm_table_name, $data, $where );
        wp_cache_delete($terms_id, 'terms_meta');
        return true;
    }

    /**
     * update_termsmeta_cache()
     *
     *
     * @uses $wpdb
     *
     * @param array $category_ids
     * @return bool|array Returns false if there is nothing to update or an array of metadata
     */
    function update_termsmeta_cache($terms_ids) {

        global $wpdb;
        global $wptm_table_name;

        if ( empty( $terms_ids ) )
        return false;

        if ( !is_array($terms_ids) ) {
            $terms_ids = preg_replace('|[^0-9,]|', '', $terms_ids);
            $terms_ids = explode(',', $terms_ids);
        }

        $terms_ids = array_map('intval', $terms_ids);

        $ids = array();
        foreach ( (array) $terms_ids as $id ) {
            if ( false === wp_cache_get($id, 'terms_meta') )
            $ids[] = $id;
        }

        if ( empty( $ids ) )
        return false;

        // Get terms-meta info
        $id_list = join(',', $ids);
        $cache = array();
        if ( $meta_list = $wpdb->get_results("SELECT terms_id, meta_key, meta_value FROM $wptm_table_name WHERE terms_id IN ($id_list) ORDER BY terms_id, meta_key", ARRAY_A) ) {
            foreach ( (array) $meta_list as $metarow) {
                $mpid = (int) $metarow['terms_id'];
                $mkey = $metarow['meta_key'];
                $mval = $metarow['meta_value'];

                // Force subkeys to be array type:
                if ( !isset($cache[$mpid]) || !is_array($cache[$mpid]) )
                $cache[$mpid] = array();
                if ( !isset($cache[$mpid][$mkey]) || !is_array($cache[$mpid][$mkey]) )
                $cache[$mpid][$mkey] = array();

                // Add a value to the current pid/key:
                $cache[$mpid][$mkey][] = $mval;
            }
        }

        foreach ( (array) $ids as $id ) {
            if ( ! isset($cache[$id]) )
            $cache[$id] = array();
        }

        foreach ( array_keys($cache) as $terms)
        wp_cache_set($terms, $cache[$terms], 'terms_meta');

        return $cache;
    }

    /**
     * Function that saves the meta from form.
     *
     * @param $id : terms (category) ID
     * @return void;
     */
    function wptm_save_meta_tags($id) {

        global $metaList;
        // Check that the meta form is posted
        $wptm_edit = $_POST["wptm_edit"];
        if (isset($wptm_edit) && !empty($wptm_edit)) {

            foreach($metaList as $inputName => $inputType)
            {
                $inputValue = $_POST['wptm_'.$inputName];
                delete_terms_meta($id, $inputName);
                if (isset($inputValue) && !empty($inputValue)) {
                    add_terms_meta($id, $inputName, $inputValue);
                }
            }
        }
    }

    /**
     * Function that deletes the meta for a terms (category/..)
     *
     * @param $id : terms (category) ID
     * @return void
     */
    function wptm_delete_meta_tags($id) {

        global $metaList;
        foreach($metaList as $inputName => $inputType)
        {
            delete_terms_meta($id, $inputName);
        }
    }

    /**
     * Function that display the meta text input.
     *
     * @return void.
     */
    function wptm_add_meta_textinput()
    {
        global $category;
        global $metaList;
        $category_id = $category;
        if (is_object($category_id)) {
            $category_id = $category_id->term_id;
        }

        ?>
<input
	value="wptm_edit" type="hidden" name="wptm_edit" />
<table class="form-table">
	<tr>
		<th style="text-align: left;" colspan="2"><?php _e('Category meta', 'wp-category-meta');?></th>
	</tr>
	<?php
	foreach($metaList as $inputName => $inputType)
	{
	    $inputValue = htmlspecialchars(stripcslashes(get_terms_meta($category_id, $inputName, true)));
	    if($inputType == 'text')
	    {
	        ?>
	<tr>
		<th scope="row" valign="top"><label for="category_nicename"><?php echo $inputName;?></label></th>
		<td><input value="<?php echo $inputValue ?>" type="text" size="40"
			name="<?php echo 'wptm_'.$inputName;?>" /><br />
			<?php _e('This additionnal data is attached to the current category', 'wp-category-meta');?></td>
	</tr>
	<?php } elseif($inputType == 'textarea') { ?>
	<tr>
		<th scope="row" valign="top"><label for="category_nicename"><?php echo $inputName;?></label></th>
		<td><textarea name="<?php echo "wptm_".$inputName?>" rows="5"
			cols="50" style="width: 97%;"><?php echo $inputValue ?></textarea> <br />
			<?php _e('This additionnal data is attached to the current category', 'wp-category-meta');?></td>
	</tr>
	<?php }
	} ?>
</table>
	<?php
    }
    ?>