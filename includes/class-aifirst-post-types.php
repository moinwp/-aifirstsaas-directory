<?php
/**
 * Custom post type registration.
 *
 * @package AIFirst
 * @since   0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the aifirst_tool CPT.
 *
 * @since 0.1.0
 */
class AIFirst_Post_Types {

	/**
	 * Register post types.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register() {
		$labels = array(
			'name'                  => _x( 'Tools', 'post type general name', 'aifirstsaas' ),
			'singular_name'         => _x( 'Tool', 'post type singular name', 'aifirstsaas' ),
			'menu_name'             => _x( 'Tools', 'admin menu', 'aifirstsaas' ),
			'name_admin_bar'        => _x( 'Tool', 'add new on admin bar', 'aifirstsaas' ),
			'add_new'               => __( 'Add New', 'aifirstsaas' ),
			'add_new_item'          => __( 'Add New Tool', 'aifirstsaas' ),
			'new_item'              => __( 'New Tool', 'aifirstsaas' ),
			'edit_item'             => __( 'Edit Tool', 'aifirstsaas' ),
			'view_item'             => __( 'View Tool', 'aifirstsaas' ),
			'all_items'             => __( 'All Tools', 'aifirstsaas' ),
			'search_items'          => __( 'Search Tools', 'aifirstsaas' ),
			'parent_item_colon'     => __( 'Parent Tools:', 'aifirstsaas' ),
			'not_found'             => __( 'No tools found.', 'aifirstsaas' ),
			'not_found_in_trash'    => __( 'No tools found in Trash.', 'aifirstsaas' ),
			'featured_image'        => __( 'Tool Logo', 'aifirstsaas' ),
			'set_featured_image'    => __( 'Set tool logo', 'aifirstsaas' ),
			'remove_featured_image' => __( 'Remove tool logo', 'aifirstsaas' ),
			'use_featured_image'    => __( 'Use as tool logo', 'aifirstsaas' ),
			'archives'              => __( 'Tool archives', 'aifirstsaas' ),
			'insert_into_item'      => __( 'Insert into tool', 'aifirstsaas' ),
			'uploaded_to_this_item' => __( 'Uploaded to this tool', 'aifirstsaas' ),
			'filter_items_list'     => __( 'Filter tools list', 'aifirstsaas' ),
			'items_list_navigation' => __( 'Tools list navigation', 'aifirstsaas' ),
			'items_list'            => __( 'Tools list', 'aifirstsaas' ),
		);

		$args = array(
			'labels'              => $labels,
			'description'         => __( 'SaaS tools catalogued in the AI First directory.', 'aifirstsaas' ),
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'show_in_rest'        => true,
			'rest_base'           => 'tools',
			'query_var'           => true,
			'rewrite'             => array(
				'slug'       => 'tools',
				'with_front' => false,
			),
			'capability_type'     => 'post',
			'has_archive'         => true,
			'hierarchical'        => false,
			'menu_position'       => 20,
			'menu_icon'           => 'dashicons-screenoptions',
			'supports'            => array( 'title', 'editor', 'thumbnail', 'revisions', 'excerpt' ),
		);

		register_post_type( 'aifirst_tool', $args );
	}
}
