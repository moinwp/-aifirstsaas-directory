<?php
/**
 * Reusable render functions for frontend templates.
 *
 * Every function echoes safely-escaped HTML. Keep logic here, not inline in
 * the template files.
 *
 * @package AIFirst
 * @since   0.4.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Components.
 *
 * @since 0.4.0
 */
class AIFirst_Components {

	/**
	 * Render a 10-segment maturity bar given a score 0-10.
	 *
	 * @since 0.4.0
	 * @param int $score 0-10.
	 * @return void
	 */
	public static function maturity_bars( $score ) {
		$score = max( 0, min( 10, (int) $score ) );
		echo '<span class="aifirst-bars" aria-label="' . esc_attr( sprintf( /* translators: %d: score */ __( '%d of 10', 'aifirstsaas' ), $score ) ) . '">';
		for ( $i = 1; $i <= 10; $i++ ) {
			$cls = $i <= $score ? 'aifirst-bar aifirst-bar--on' : 'aifirst-bar';
			echo '<span class="' . esc_attr( $cls ) . '"></span>';
		}
		echo '</span>';
	}

	/**
	 * Render the Free-AI tri-state badge.
	 *
	 * @since 0.4.0
	 * @param int $state 0 no, 1 yes, 2 add-on.
	 * @return void
	 */
	public static function free_ai_badge( $state ) {
		$state = (int) $state;
		$map   = array(
			0 => array( 'no',     __( 'No', 'aifirstsaas' ) ),
			1 => array( 'yes',    __( 'Yes', 'aifirstsaas' ) ),
			2 => array( 'addon',  __( 'Add-on', 'aifirstsaas' ) ),
		);
		if ( ! isset( $map[ $state ] ) ) {
			$state = 0;
		}
		list( $cls, $label ) = $map[ $state ];
		printf(
			'<span class="aifirst-free-ai aifirst-free-ai--%1$s">%2$s %3$s</span>',
			esc_attr( $cls ),
			esc_html__( 'Free AI:', 'aifirstsaas' ),
			esc_html( $label )
		);
	}

	/**
	 * Render a plan pill (Free / Starter / Pro / Enterprise / …).
	 *
	 * @since 0.4.0
	 * @param string $plan Plan name.
	 * @return void
	 */
	public static function plan_pill( $plan ) {
		$plan = sanitize_text_field( (string) $plan );
		if ( '' === $plan ) {
			return;
		}
		$slug = sanitize_html_class( strtolower( str_replace( ' ', '-', $plan ) ) );
		printf(
			'<span class="aifirst-pill aifirst-pill--plan aifirst-plan--%1$s">%2$s</span>',
			esc_attr( $slug ),
			esc_html( $plan )
		);
	}

	/**
	 * Render a category pill (link to category page).
	 *
	 * @since 0.4.0
	 * @param WP_Term|null $term Term.
	 * @return void
	 */
	public static function category_pill( $term ) {
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}
		printf(
			'<a class="aifirst-pill aifirst-pill--category" href="%1$s">%2$s</a>',
			esc_url( get_term_link( $term ) ),
			esc_html( $term->name )
		);
	}

	/**
	 * Avatar circle (initials + color, falls back on the featured image).
	 *
	 * @since 0.4.0
	 * @param int    $post_id Post ID (optional, for featured image).
	 * @param string $initials Short initials.
	 * @param string $color   Hex color.
	 * @param int    $size    Pixel size.
	 * @return void
	 */
	public static function avatar( $post_id, $initials, $color, $size = 36 ) {
		$initials = sanitize_text_field( (string) $initials );
		$color    = preg_match( '/^#[A-Fa-f0-9]{6}$/', (string) $color ) ? $color : '#374151';

		if ( $post_id && has_post_thumbnail( $post_id ) ) {
			printf(
				'<span class="aifirst-avatar aifirst-avatar--image" style="width:%1$dpx;height:%1$dpx;">%2$s</span>',
				(int) $size,
				get_the_post_thumbnail( $post_id, array( (int) $size, (int) $size ) )
			);
			return;
		}

		printf(
			'<span class="aifirst-avatar" style="width:%1$dpx;height:%1$dpx;background:%2$s;font-size:%3$dpx;">%4$s</span>',
			(int) $size,
			esc_attr( $color ),
			(int) max( 10, round( $size * 0.36 ) ),
			esc_html( '' !== $initials ? $initials : '—' )
		);
	}

	/**
	 * Render a tool card (list / grid item).
	 *
	 * @since 0.4.0
	 * @param WP_Post $post Post.
	 * @param object  $meta tool_meta row.
	 * @return void
	 */
	public static function tool_card( $post, $meta ) {
		$cats = get_the_terms( $post->ID, 'aifirst_category' );
		$cats = ( $cats && ! is_wp_error( $cats ) ) ? $cats : array();
		$cat  = ! empty( $cats ) ? $cats[0] : null;

		// On a category archive, surface the category the viewer is looking at
		// (if this tool is in it). Avoids every card showing the alphabetically
		// first category regardless of the page we are rendering.
		// @since 0.4.1
		if ( is_tax( 'aifirst_category' ) ) {
			$current = get_queried_object();
			if ( $current instanceof WP_Term ) {
				foreach ( $cats as $t ) {
					if ( (int) $t->term_id === (int) $current->term_id ) {
						$cat = $t;
						break;
					}
				}
			}
		}

		// 0.6.0: cards are now <div> containing a link-area and a sibling
		// stack-toggle button. Buttons cannot be legal children of an <a>,
		// so the card is composed, not nested.
		$tool_id  = $meta ? (string) $meta->tool_id : '';
		$permalink = get_permalink( $post );

		echo '<div class="aifirst-tool-card">';
		echo '<a class="aifirst-tool-card__link" href="' . esc_url( $permalink ) . '">';
		self::avatar( $post->ID, $meta ? $meta->initials : '', $meta ? $meta->avatar_color : '', 38 );
		echo '<span class="aifirst-tool-card__body">';
		echo '<span class="aifirst-tool-card__title">' . esc_html( get_the_title( $post ) ) . '</span>';
		echo '<span class="aifirst-tool-card__cat">' . esc_html( $cat ? $cat->name : '' ) . '</span>';
		echo '</span>';

		echo '<span class="aifirst-tool-card__meta">';
		if ( $meta ) {
			echo '<span class="aifirst-tool-card__score">' . esc_html( (int) $meta->ai_maturity_score ) . '/10</span>';
			self::maturity_bars( (int) $meta->ai_maturity_score );
			echo '<span class="aifirst-tool-card__freeai">';
			self::free_ai_badge( (int) $meta->ai_on_free_plan );
			echo '</span>';
			if ( $meta->last_verified ) {
				echo '<span class="aifirst-tool-card__verified">' . esc_html( sprintf( /* translators: %s: date */ __( 'Updated %s', 'aifirstsaas' ), mysql2date( 'M j', $meta->last_verified ) ) ) . '</span>';
			}
		}
		echo '<span class="aifirst-tool-card__view">' . esc_html__( 'View →', 'aifirstsaas' ) . '</span>';
		echo '</span>';
		echo '</a>';

		if ( '' !== $tool_id ) {
			self::stack_toggle_button( $tool_id, 'card' );
		}

		echo '</div>';
	}

	/**
	 * Alpine-powered FAQ accordion.
	 *
	 * @since 0.4.0
	 * @param array  $faq   Array of rows with question / answer.
	 * @param string $title Section title.
	 * @return void
	 */
	public static function faq_block( $faq, $title = '' ) {
		if ( empty( $faq ) ) {
			return;
		}
		if ( '' === $title ) {
			$title = __( 'Frequently Asked Questions', 'aifirstsaas' );
		}
		echo '<section class="aifirst-faq" x-data="{ open: null }">';
		echo '<h2 class="aifirst-faq__title">' . esc_html( $title ) . '</h2>';
		echo '<div class="aifirst-faq__list">';
		foreach ( $faq as $idx => $f ) {
			$i = (int) $idx;
			echo '<div class="aifirst-faq__item">';
			printf(
				'<button type="button" class="aifirst-faq__q" @click="open = open === %1$d ? null : %1$d" :aria-expanded="open === %1$d">',
				$i
			);
			echo '<span>' . esc_html( (string) $f->question ) . '</span>';
			echo '<span class="aifirst-faq__caret" x-text="open === ' . $i . ' ? \'−\' : \'+\'">+</span>';
			echo '</button>';
			echo '<div class="aifirst-faq__a" x-show="open === ' . $i . '" x-cloak>';
			echo wp_kses_post( (string) $f->answer );
			echo '</div>';
			echo '</div>';
		}
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Breadcrumb trail for the tool detail page.
	 *
	 * @since 0.4.0
	 * @param array $trail Array of {label,url}.
	 * @return void
	 */
	public static function breadcrumbs( $trail ) {
		if ( empty( $trail ) ) {
			return;
		}
		echo '<nav class="aifirst-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumbs', 'aifirstsaas' ) . '">';
		$count = count( $trail );
		foreach ( $trail as $i => $crumb ) {
			$is_last = ( $i === $count - 1 );
			if ( ! empty( $crumb['url'] ) && ! $is_last ) {
				printf( '<a href="%s">%s</a>', esc_url( $crumb['url'] ), esc_html( $crumb['label'] ) );
			} else {
				printf( '<span>%s</span>', esc_html( $crumb['label'] ) );
			}
			if ( ! $is_last ) {
				echo '<span class="aifirst-breadcrumbs__sep">/</span>';
			}
		}
		echo '</nav>';
	}

	/**
	 * Newsletter signup form (stub — no backend yet).
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public static function newsletter_form( $source = 'home', $scope = 'all', $scope_target = '' ) {
		$ajax_url = admin_url( 'admin-ajax.php' );
		// Factory config for the newsletter Alpine component.
		// @since 0.5.0
		$aifirst_cfg = array(
			'ajaxUrl'          => esc_url_raw( $ajax_url ),
			'errorText'        => __( 'Could not subscribe. Please try again.', 'aifirstsaas' ),
			'successText'      => __( 'Subscribed — check your inbox.', 'aifirstsaas' ),
			'networkErrorText' => __( 'Network error. Please try again.', 'aifirstsaas' ),
		);
		?>
		<form class="aifirst-newsletter" x-data="AIFirstNewsletter(<?php echo esc_attr( wp_json_encode( $aifirst_cfg ) ); ?>)" @submit.prevent="submit($event)" novalidate>
			<?php wp_nonce_field( 'aifirst_subscribe', 'aifirst_subscribe_nonce' ); ?>
			<input type="hidden" name="scope" value="<?php echo esc_attr( $scope ); ?>" />
			<input type="hidden" name="scope_target" value="<?php echo esc_attr( $scope_target ); ?>" />
			<input type="hidden" name="source" value="<?php echo esc_attr( $source ); ?>" />
			<p><input type="text" name="hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;" /></p>

			<div class="aifirst-newsletter__copy">
				<strong><?php esc_html_e( 'Get weekly AI feature updates', 'aifirstsaas' ); ?></strong>
				<span><?php esc_html_e( 'New features, plan changes, and maturity shifts — every Friday.', 'aifirstsaas' ); ?></span>
			</div>
			<div class="aifirst-newsletter__fields" x-show="!sent">
				<input type="email" name="email" required placeholder="you@company.com" x-model="email" :disabled="busy" />
				<button type="submit" :disabled="busy">
					<span x-show="!busy"><?php esc_html_e( 'Subscribe', 'aifirstsaas' ); ?></span>
					<span x-show="busy" x-cloak><?php esc_html_e( 'Sending…', 'aifirstsaas' ); ?></span>
				</button>
			</div>
			<p class="aifirst-newsletter__error" x-show="error" x-text="error" x-cloak></p>
			<div class="aifirst-newsletter__done" x-show="sent" x-cloak>
				✓ <?php esc_html_e( 'Subscribed! Check your inbox.', 'aifirstsaas' ); ?>
			</div>
		</form>
		<?php
	}

	/**
	 * Render the "Report a change" trigger + modal shell. The form posts to
	 * admin-post.php (see AIFirst_Reports).
	 *
	 * @since 0.4.0
	 * @param string $tool_id Slug.
	 * @return void
	 */
	public static function report_modal_trigger( $tool_id ) {
		$tool_id  = (string) $tool_id;
		$ajax_url = admin_url( 'admin-ajax.php' );

		// Factory config — JSON-safe, single-attribute. The multi-line
		// variant blew up in 0.4.1 because the encoded strings carried
		// double quotes that terminated the x-data attribute early.
		// @since 0.4.2
		$aifirst_cfg = array(
			'ajaxUrl'          => esc_url_raw( $ajax_url ),
			'errorText'        => __( 'Something went wrong. Please try again.', 'aifirstsaas' ),
			'successText'      => __( 'Thanks — your report was recorded.', 'aifirstsaas' ),
			'networkErrorText' => __( 'Network error. Please try again.', 'aifirstsaas' ),
		);
		?>
		<div class="aifirst-report" x-data="AIFirstReportModal(<?php echo esc_attr( wp_json_encode( $aifirst_cfg ) ); ?>)">
			<button type="button" class="aifirst-report__trigger" @click="open = true">
				<?php esc_html_e( 'Report a change', 'aifirstsaas' ); ?>
			</button>

			<div class="aifirst-modal" x-show="open" x-cloak @keydown.escape.window="open = false">
				<div class="aifirst-modal__backdrop" @click="open = false"></div>
				<div class="aifirst-modal__dialog" role="dialog" aria-modal="true">
					<form @submit.prevent="submit($event)" novalidate>
						<input type="hidden" name="tool_id" value="<?php echo esc_attr( $tool_id ); ?>" />
						<?php wp_nonce_field( 'aifirst_report_' . $tool_id, 'aifirst_report_nonce' ); ?>
						<p><input type="text" name="hp" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;" aria-hidden="true" /></p>
						<h2><?php esc_html_e( 'Report a change', 'aifirstsaas' ); ?></h2>
						<label>
							<span><?php esc_html_e( 'What needs fixing?', 'aifirstsaas' ); ?></span>
							<select name="kind" required>
								<option value="wrong_info"><?php esc_html_e( 'Wrong info', 'aifirstsaas' ); ?></option>
								<option value="pricing_changed"><?php esc_html_e( 'Pricing changed', 'aifirstsaas' ); ?></option>
								<option value="feature_added"><?php esc_html_e( 'Feature added', 'aifirstsaas' ); ?></option>
								<option value="feature_removed"><?php esc_html_e( 'Feature removed', 'aifirstsaas' ); ?></option>
								<option value="other"><?php esc_html_e( 'Other', 'aifirstsaas' ); ?></option>
							</select>
						</label>
						<label>
							<span><?php esc_html_e( 'Details', 'aifirstsaas' ); ?></span>
							<textarea name="body" rows="4" required maxlength="2000"></textarea>
						</label>
						<label>
							<span><?php esc_html_e( 'Your email (optional)', 'aifirstsaas' ); ?></span>
							<input type="email" name="email" maxlength="190" />
						</label>
						<p class="aifirst-modal__error" x-show="error" x-text="error" x-cloak></p>
						<div class="aifirst-modal__actions">
							<button type="button" @click="open = false" class="aifirst-btn aifirst-btn--ghost"><?php esc_html_e( 'Cancel', 'aifirstsaas' ); ?></button>
							<button type="submit" class="aifirst-btn aifirst-btn--primary" :disabled="busy">
								<span x-show="!busy"><?php esc_html_e( 'Send report', 'aifirstsaas' ); ?></span>
								<span x-show="busy" x-cloak><?php esc_html_e( 'Sending…', 'aifirstsaas' ); ?></span>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Format a date YYYY-MM-DD for display.
	 *
	 * @since 0.4.0
	 * @param string $date YYYY-MM-DD.
	 * @return string
	 */
	public static function date_display( $date ) {
		$date = (string) $date;
		if ( '' === $date ) {
			return '';
		}
		return mysql2date( get_option( 'date_format', 'M j, Y' ), $date );
	}

	/**
	 * Comparison card used in the tool sidebar and the home trending rail.
	 *
	 * @since 0.5.0
	 * @param WP_Post $post Comparison post.
	 * @return void
	 */
	public static function comparison_link_row( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$slugs = AIFirst_Comparison::get_tool_ids( $post->ID );
		$names = array();
		foreach ( $slugs as $slug ) {
			$m = AIFirst_Query::tool_meta_by_id( $slug );
			if ( $m ) {
				$names[] = get_the_title( (int) $m->post_id );
			}
		}
		$label = ! empty( $names ) ? implode( ' vs ', $names ) : get_the_title( $post );
		printf(
			'<a class="aifirst-cmp-row" href="%s"><span>%s</span><span class="aifirst-cmp-row__arrow">→</span></a>',
			esc_url( get_permalink( $post ) ),
			esc_html( $label )
		);
	}

	/**
	 * Tool sidebar: list up to N published comparisons that include this tool.
	 *
	 * @since 0.5.0
	 * @param string $tool_id tool_id slug.
	 * @param int    $limit   Max rows.
	 * @return void
	 */
	public static function tool_comparisons_sidebar( $tool_id, $limit = 5 ) {
		$list = AIFirst_Query::comparisons_for_tool( $tool_id, $limit );
		if ( empty( $list ) ) {
			return;
		}
		echo '<div class="aifirst-card"><strong>' . esc_html__( 'Related comparisons', 'aifirstsaas' ) . '</strong>';
		foreach ( $list as $p ) {
			self::comparison_link_row( $p );
		}
		echo '</div>';
	}

	/**
	 * Home "Trending Comparisons" section.
	 *
	 * @since 0.5.0
	 * @param int $limit Max rows.
	 * @return void
	 */
	public static function trending_comparisons( $limit = 6 ) {
		$list = AIFirst_Query::trending_comparisons( $limit );
		if ( empty( $list ) ) {
			echo '<p class="aifirst-lead">' . esc_html__( 'No comparisons published yet.', 'aifirstsaas' ) . '</p>';
			return;
		}
		echo '<div class="aifirst-trending">';
		foreach ( $list as $p ) {
			self::comparison_link_row( $p );
		}
		echo '</div>';
	}

	/**
	 * Related AI tools block (used on core WP posts).
	 *
	 * @since 0.5.0
	 * @param string[] $tool_ids tool_id slugs.
	 * @return void
	 */
	/**
	 * Add-to-stack button. Reads/writes the aifirst_stack cookie entirely
	 * on the client via the AIFirstStackToggle Alpine factory and the
	 * shared Alpine.store('stack'). No AJAX, no server round-trip.
	 *
	 * @since 0.6.0
	 * @param string $tool_id tool_id slug.
	 * @param string $variant 'card' or 'hero'.
	 * @return void
	 */
	public static function stack_toggle_button( $tool_id, $variant = 'hero' ) {
		$tool_id = sanitize_title( (string) $tool_id );
		if ( '' === $tool_id ) {
			return;
		}
		$cfg = array(
			'toolId'      => $tool_id,
			'addLabel'    => __( '+ Add to my stack', 'aifirstsaas' ),
			'inLabel'     => __( '✓ In your stack', 'aifirstsaas' ),
			'fullMsg'     => __( 'Your stack is full (max 50 tools).', 'aifirstsaas' ),
			'addedMsg'    => __( 'Added to your stack.', 'aifirstsaas' ),
			'removedMsg'  => __( 'Removed from your stack.', 'aifirstsaas' ),
		);
		$class = 'aifirst-stack-btn aifirst-stack-btn--' . sanitize_html_class( $variant );
		printf(
			'<button type="button" class="%1$s" x-data="AIFirstStackToggle(%2$s)" @click.stop.prevent="toggle()" :class="inStack && \'is-in\'" x-text="inStack ? cfg.inLabel : cfg.addLabel">%3$s</button>',
			esc_attr( $class ),
			esc_attr( wp_json_encode( $cfg ) ),
			esc_html( $cfg['addLabel'] )
		);
	}

	/**
	 * Floating header badge linking to /stack/. Count comes from the
	 * shared Alpine.store('stack') so it updates live when any button
	 * toggles.
	 *
	 * @since 0.6.0
	 * @return void
	 */
	public static function stack_header_badge() {
		$url = esc_url( home_url( '/stack/' ) );
		?>
		<a class="aifirst-stack-badge" href="<?php echo $url; // phpcs:ignore WordPress.Security.EscapeOutput ?>"
			x-data="{}"
			x-show="$store.aifStack && $store.aifStack.count > 0"
			x-cloak>
			<?php esc_html_e( 'My Stack', 'aifirstsaas' ); ?>
			<span class="aifirst-stack-badge__count" x-text="$store.aifStack.count">0</span>
		</a>
		<?php
	}

	public static function related_tools_block( $tool_ids ) {
		if ( empty( $tool_ids ) || ! is_array( $tool_ids ) ) {
			return;
		}
		echo '<section class="aifirst-related-tools">';
		echo '<h2 class="aifirst-h3">' . esc_html__( 'Related AI tools', 'aifirstsaas' ) . '</h2>';
		echo '<div class="aifirst-related-tools__grid">';
		foreach ( $tool_ids as $slug ) {
			$slug = sanitize_title( (string) $slug );
			if ( '' === $slug ) { continue; }
			$meta = AIFirst_Query::tool_meta_by_id( $slug );
			if ( ! $meta ) { continue; }
			$post = get_post( (int) $meta->post_id );
			if ( ! $post ) { continue; }
			self::tool_card( $post, $meta );
		}
		echo '</div></section>';
	}
}
