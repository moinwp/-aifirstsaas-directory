<?php
/**
 * Transient cache coordinator.
 *
 * Every public query helper that wants caching builds its key with
 * `AIFirst_Cache::key( $raw )`, which prefixes a version counter from the
 * `aifirst_cache_version` option. Invalidation just bumps that counter —
 * all old keys become orphaned (transient expiry handles cleanup). Cheaper
 * than iterating `wp_options` to delete every matching transient.
 *
 * @package AIFirst
 * @since   0.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Cache.
 *
 * @since 0.7.0
 */
class AIFirst_Cache {

	const VERSION_OPT = 'aifirst_cache_version';

	/**
	 * Hook invalidation to post / term events.
	 *
	 * @since 0.7.0
	 * @return void
	 */
	public function register() {
		add_action( 'save_post_aifirst_tool', array( __CLASS__, 'flush_tool_caches' ) );
		add_action( 'deleted_post', array( __CLASS__, 'on_post_delete' ), 10, 2 );

		add_action( 'save_post_aifirst_comparison', array( __CLASS__, 'flush_comparison_caches' ) );

		add_action( 'edited_aifirst_category', array( __CLASS__, 'flush_tool_caches' ) );
		add_action( 'edited_aifirst_feature', array( __CLASS__, 'flush_tool_caches' ) );
	}

	/**
	 * Build a versioned transient key.
	 *
	 * @since 0.7.0
	 * @param string $raw Un-versioned key.
	 * @return string
	 */
	public static function key( $raw ) {
		$v = (int) get_option( self::VERSION_OPT, 1 );
		return 'aifirst_v' . $v . '_' . $raw;
	}

	/**
	 * Bump the version counter. Every previously-cached value is now
	 * unreachable (the running key prefix changed).
	 *
	 * @since 0.7.0
	 * @return void
	 */
	public static function bump_version() {
		$v = (int) get_option( self::VERSION_OPT, 1 );
		update_option( self::VERSION_OPT, $v + 1, false );
	}

	/**
	 * Alias with intent: flush everything.
	 *
	 * @since 0.7.0
	 * @return void
	 */
	public static function flush_all() {
		self::bump_version();
	}

	/**
	 * Flush queries that depend on tool rows.
	 *
	 * @since 0.7.0
	 * @return void
	 */
	public static function flush_tool_caches() {
		// Tool caches include: leaderboard, category stats, category/feature
		// counts, radar_recent, comparisons_for_tool. Bumping the shared
		// version invalidates them all in one write. If/when we want
		// narrower scopes we can split the counter.
		self::bump_version();
	}

	/**
	 * Flush queries that depend on comparison rows.
	 *
	 * @since 0.7.0
	 * @return void
	 */
	public static function flush_comparison_caches() {
		self::bump_version();
	}

	/**
	 * Route the generic deleted_post hook to the right flusher.
	 *
	 * @since 0.7.0
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post being deleted.
	 * @return void
	 */
	public static function on_post_delete( $post_id, $post ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( 'aifirst_tool' === $post->post_type ) {
			self::flush_tool_caches();
		} elseif ( 'aifirst_comparison' === $post->post_type ) {
			self::flush_comparison_caches();
		}
	}
}
