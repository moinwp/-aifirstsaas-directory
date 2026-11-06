<?php
/**
 * Taxonomy registration.
 *
 * @package AIFirst
 * @since   0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the aifirst_category and aifirst_feature taxonomies.
 *
 * @since 0.1.0
 */
class AIFirst_Taxonomies {

	/**
	 * Register taxonomies.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register() {
		$this->register_category();
		$this->register_feature();
	}

	/**
	 * Register the hierarchical Category taxonomy.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	protected function register_category() {
		$labels = array(
			'name'              => _x( 'Categories', 'taxonomy general name', 'aifirstsaas' ),
			'singular_name'     => _x( 'Category', 'taxonomy singular name', 'aifirstsaas' ),
			'search_items'      => __( 'Search Categories', 'aifirstsaas' ),
			'all_items'         => __( 'All Categories', 'aifirstsaas' ),
			'parent_item'       => __( 'Parent Category', 'aifirstsaas' ),
			'parent_item_colon' => __( 'Parent Category:', 'aifirstsaas' ),
			'edit_item'         => __( 'Edit Category', 'aifirstsaas' ),
			'update_item'       => __( 'Update Category', 'aifirstsaas' ),
			'add_new_item'      => __( 'Add New Category', 'aifirstsaas' ),
			'new_item_name'     => __( 'New Category Name', 'aifirstsaas' ),
			'menu_name'         => __( 'Categories', 'aifirstsaas' ),
			'not_found'         => __( 'No categories found.', 'aifirstsaas' ),
			'back_to_items'     => __( '&larr; Back to Categories', 'aifirstsaas' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => true,
			'publicly_queryable' => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'categories',
			'query_var'         => true,
			'rewrite'           => array(
				'slug'         => 'categories',
				'with_front'   => false,
				'hierarchical' => true,
			),
		);

		register_taxonomy( 'aifirst_category', array( 'aifirst_tool' ), $args );
	}

	/**
	 * Register the flat AI Feature taxonomy.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	protected function register_feature() {
		$labels = array(
			'name'                       => _x( 'AI Features', 'taxonomy general name', 'aifirstsaas' ),
			'singular_name'              => _x( 'AI Feature', 'taxonomy singular name', 'aifirstsaas' ),
			'search_items'               => __( 'Search AI Features', 'aifirstsaas' ),
			'popular_items'              => __( 'Popular AI Features', 'aifirstsaas' ),
			'all_items'                  => __( 'All AI Features', 'aifirstsaas' ),
			'edit_item'                  => __( 'Edit AI Feature', 'aifirstsaas' ),
			'update_item'                => __( 'Update AI Feature', 'aifirstsaas' ),
			'add_new_item'               => __( 'Add New AI Feature', 'aifirstsaas' ),
			'new_item_name'              => __( 'New AI Feature Name', 'aifirstsaas' ),
			'separate_items_with_commas' => __( 'Separate AI features with commas', 'aifirstsaas' ),
			'add_or_remove_items'        => __( 'Add or remove AI features', 'aifirstsaas' ),
			'choose_from_most_used'      => __( 'Choose from the most used AI features', 'aifirstsaas' ),
			'menu_name'                  => __( 'AI Features', 'aifirstsaas' ),
			'not_found'                  => __( 'No AI features found.', 'aifirstsaas' ),
			'back_to_items'              => __( '&larr; Back to AI Features', 'aifirstsaas' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => false,
			'public'            => true,
			'publicly_queryable' => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'ai-features',
			'query_var'         => true,
			'rewrite'           => array(
				'slug'       => 'ai-features',
				'with_front' => false,
			),
		);

		register_taxonomy( 'aifirst_feature', array( 'aifirst_tool' ), $args );
	}
}
