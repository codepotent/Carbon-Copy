<?php

/**
 * -----------------------------------------------------------------------------
 * Plugin Name: Carbon Copy
 * Description: One-click duplication of ClassicPress posts, pages, and custom post types. Copies taxonomy associations and metadata, too!
 * Version: 1.1.0
 * Author: Simone Fioravanti
 * Author URI: https://software.gieffeedizioni.it
 * Plugin URI: https://software.gieffeedizioni.it
 * Text Domain: codepotent-carbon-copy
 * Domain Path: /languages
 * -----------------------------------------------------------------------------
 * This is free software released under the terms of the General Public License,
 * version 2, or later. It is distributed WITHOUT ANY WARRANTY; without even the
 * implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. Full
 * text of the license is available at https://www.gnu.org/licenses/gpl-2.0.txt.
 * -----------------------------------------------------------------------------
 * Copyright 2021, John Alarcon (Code Potent)
 * -----------------------------------------------------------------------------
 * Adopted by Simone Fioravanti, 06/01/2021
 * -----------------------------------------------------------------------------
 */

// Declare the namespace.
namespace CodePotent\CarbonCopy;

// Prevent direct access.
if (!defined('ABSPATH')) {
	die();
}

/**
 * Carbon Copy
 *
 * A class to insert a "Copy" link in the admin rows of posts, pages, and custom
 * post types. When clicked, the item is duplicated along with its taxonomy data
 * and any meta data associated with it.
 *
 * @author John Alarcon
 *
 */
class CarbonCopy {

	/**
	 * Plugin prefix for hooks.
	 *
	 * @var string
	 */
	public $prefix = 'codepotent_carbon_copy';

	/**
	 * If a lightweight constructor steps on your foot, you won't even feel it.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Initialize the plugin.
		$this->init();

	}

	/**
	 * Initialize the plugin.
	 *
	 * This method adds actions and filters to hook the plugin into the system.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 */
	public function init() {

		// Load constants.
		require_once(plugin_dir_path(__FILE__).'includes/constants.php');

		// Load update client.
		require_once(PATH_CLASSES.'/UpdateClient.class.php');

		// Hook the duplication method into the admin request process.
		add_action('admin_action_'.$this->prefix, [$this, 'generate_copy']);

		// Inject duplication link into non-hierarchical item rows (post, CPT).
		add_filter('post_row_actions', [$this, 'register_action_link'], 10, 2);

		// Inject duplication link into hierarchical item rows (page, CPT).
		add_filter('page_row_actions', [$this, 'register_action_link'], 10, 2);

		// Inject duplication button into the post/page/CPT publishing meta box.
		add_action('post_submitbox_misc_actions', [$this, 'register_action_button']);

		// POST-ADOPTION: Remove these actions before pushing your next update.
		add_action('upgrader_process_complete', [$this, 'enable_adoption_notice'], 10, 2);
		add_action('admin_notices', [$this, 'display_adoption_notice']);

	}

	// POST-ADOPTION: Remove this method before pushing your next update.
	public function enable_adoption_notice($upgrader_object, $options) {
		if ($options['action'] === 'update') {
			if ($options['type'] === 'plugin') {
				if (!empty($options['plugins'])) {
					if (in_array(plugin_basename(__FILE__), $options['plugins'])) {
						set_transient(PLUGIN_PREFIX.'_adoption_complete', 1);
					}
				}
			}
		}
	}

	// POST-ADOPTION: Remove this method before pushing your next update.
	public function display_adoption_notice() {
		if (get_transient(PLUGIN_PREFIX.'_adoption_complete')) {
			delete_transient(PLUGIN_PREFIX.'_adoption_complete');
			echo '<div class="notice notice-success is-dismissible">';
			echo '<h3 style="margin:25px 0 15px;padding:0;color:#e53935;">IMPORTANT <span style="color:#aaa;">information about the <strong style="color:#333;">'.PLUGIN_NAME.'</strong> plugin</h3>';
			echo '<p style="margin:0 0 15px;padding:0;font-size:14px;">The <strong>'.PLUGIN_NAME.'</strong> plugin has been officially adopted and is now managed by <a href="'.PLUGIN_AUTHOR_URL.'" rel="noopener" target="_blank" style="text-decoration:none;">'.PLUGIN_AUTHOR.'<span class="dashicons dashicons-external" style="display:inline;font-size:98%;"></span></a>, a longstanding and trusted ClassicPress developer and community member. While it has been wonderful to serve the ClassicPress community with free plugins, tutorials, and resources for nearly 3 years, it\'s time that I move on to other endeavors. This notice is to inform you of the change, and to assure you that the plugin remains in good hands. I\'d like to extend my heartfelt thanks to you for making my plugins a staple within the community, and wish you great success with ClassicPress!</p>';
			echo '<p style="margin:0 0 15px;padding:0;font-size:14px;font-weight:600;">All the best!</p>';
			echo '<p style="margin:0 0 15px;padding:0;font-size:14px;">~ John Alarcon <span style="color:#aaa;">(Code Potent)</span></p>';
			echo '</div>';
		}
	}

	/**
	 * Inject duplication link.
	 *
	 * This method injects the duplication link into the admin list table rows.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 *
	 * @param array $actions Actions currently associated with the item type.
	 * @param array $post The current post object.
	 *
	 * @return array Possibly-amended array of actions.
	 */
	public function register_action_link($actions, $post) {

		// If user can't edit posts, bail.
		if (!current_user_can('edit_posts')) {
			$actions;
		}

		// Add the duplication link to the $actions array.
		$actions['duplicate'] = sprintf(
			'<a href="%s" rel="permalink">%s</a>',
			admin_url('admin.php?action='.$this->prefix.'&post='.$post->ID),
			esc_html__('Duplicate', 'codepotent-carbon-copy')
		);

		// Return the amended actions array.
		return $actions;

	}

	/**
	 * Inject duplication button.
	 *
	 * This method injects the duplication button into the publishing meta box.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 *
	 * @param object $item A post, page, or custom post type item.
	 */
	public function register_action_button($item) {

		// Print a section container and duplication button.
		echo '<div class="misc-pub-section">';
		echo '<a href="'.admin_url('admin.php?action='.$this->prefix.'&post='.$item->ID).'" class="button button-secondary">'.esc_html__('Duplicate', 'codepotent-carbon-copy').'</a>';
		echo '</div>';

	}

	/**
	 * Copy an item.
	 *
	 * This method duplicates a page, post, or custom post type. The duplication
	 * process also covers any taxonomy and meta data.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 */
	public function generate_copy(){

		// If user can't edit items, send'em off into the weeds.
		if (!current_user_can('edit_posts')) {
			wp_die(esc_html__('A higher level of permission is required to perform this action.', 'codepotent-carbon-copy'));
		}

		// If no item id is present, bail.
		if (!isset($_REQUEST['post'])) {
			wp_die(esc_html__('Item could not be duplicated.', 'codepotent-carbon-copy'));
		}

		// Extract the post id from the request.
		$source_id = (isset($_GET['post']) ? (int)$_GET['post'] : (int)$_POST['post']);

		// Get source item.
		$source = get_post($source_id);

		// If source item wasn't retrieved, bail.
		if (!is_object($source)) {
			wp_die(esc_html__('Source item could not be found.', 'codepotent-carbon-copy'));
		}

		// Copy item and retrieve id of new item.
		$target_id = $this->copy_item($source);

		// Copy any taxonomy data over to the target id.
		$this->copy_taxonomies($source, $target_id);

		// Copy any meta data over to the target id.
		$this->copy_meta($source, $target_id);

		// Redirect user to the new item's edit screen.
		wp_redirect(admin_url('post.php?action=edit&post='.$target_id));

		// Exit stage left.
		exit;

	}

	/**
	 * Copy source item.
	 *
	 * This method duplicates the given post/page/cpt and returns the new item's
	 * id number.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 *
	 * @param object $source A post, page, or custom post type item.
	 *
	 * @return int Id of the newly created item.
	 */
	private function copy_item($source) {

		// Array of data for the new item; only the relevant bits.
		$target = [
			'post_author'    => $source->post_author,
			'post_content'   => $source->post_content,
			'post_excerpt'   => $source->post_excerpt,
			'post_name'      => $source->post_name,
			'post_parent'    => $source->post_parent,
			'post_password'  => $source->post_password,
			'post_status'    => 'draft',
			'post_title'     => $source->post_title,
			'post_type'      => $source->post_type,
			'comment_status' => $source->comment_status,
			'ping_status'    => $source->ping_status,
			'to_ping'        => $source->to_ping,
			'menu_order'     => $source->menu_order
		];

		// Insert new item.
		$target_id = wp_insert_post($target);

		// Return the new item's id.
		return $target_id;

	}

	/**
	 * Copy source item's taxonomy data.
	 *
	 * The method duplicates any existing taxonomy data associated with the item
	 * being copied.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 *
	 * @param object $source A post, page, or custom post type item.
	 * @param int $target_id Id of new item to which taxonomy is associated.
	 *
	 * @return void
	 */
	private function copy_taxonomies($source, $target_id) {

		// Retrieve any taxonomies associated with the source item.
		$taxonomies = get_object_taxonomies($source->post_type);

		// Iterate over any found taxonomies.
		foreach ($taxonomies as $taxonomy) {

			// Get the slugs of the terms associated with current taxonomy.
			$terms = wp_get_object_terms($source->ID, $taxonomy, ['fields'=>'slugs']);

			// Copy the source term slugs to the target item.
			wp_set_object_terms($target_id, $terms, $taxonomy, false);

		}

	}

	/**
	 * Copy source item's metadata.
	 *
	 * This method duplicates existing metadata associated with the item that is
	 * being copied.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 *
	 * @param object $source A post, page, or custom post type item.
	 * @param int $target_id Id of new item to which metadata is associated.
	 *
	 * @return void
	 */
	private function copy_meta($source, $target_id) {

		// Retrieve meta data associated with the source item.
		$metadata = get_post_custom($source->ID);

		// Iterate and duplicate any found meta data.
		foreach ($metadata as $key=>$values) {
			foreach ($values as $value) {
				add_post_meta($target_id, $key, $value);
			}
		}

	}


}

// We're out of toner again!
new CarbonCopy;