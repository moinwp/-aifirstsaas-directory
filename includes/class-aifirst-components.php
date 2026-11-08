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
		$cat  = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0] : null;

		echo '<a class="aifirst-tool-card" href="' . esc_url( get_permalink( $post ) ) . '">';
		self::avatar( $post->ID, $meta ? $meta->initials : '', $meta ? $meta->avatar_color : '', 38 );
		echo '<div class="aifirst-tool-card__body">';
		echo '<div class="aifirst-tool-card__title">' . esc_html( get_the_title( $post ) ) . '</div>';
		echo '<div class="aifirst-tool-card__cat">' . esc_html( $cat ? $cat->name : '' ) . '</div>';
		echo '</div>';

		echo '<div class="aifirst-tool-card__meta">';
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
		echo '</div>';
		echo '</a>';
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
	public static function newsletter_form() {
		?>
		<form class="aifirst-newsletter" x-data="{ sent: false, email: '' }" @submit.prevent="sent = true">
			<div class="aifirst-newsletter__copy">
				<strong><?php esc_html_e( 'Get weekly AI feature updates', 'aifirstsaas' ); ?></strong>
				<span><?php esc_html_e( 'New features, plan changes, and maturity shifts — every Friday.', 'aifirstsaas' ); ?></span>
			</div>
			<div class="aifirst-newsletter__fields" x-show="!sent">
				<input type="email" required placeholder="you@company.com" x-model="email" />
				<button type="submit"><?php esc_html_e( 'Subscribe', 'aifirstsaas' ); ?></button>
			</div>
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
		$tool_id = (string) $tool_id;
		?>
		<div x-data="{ open: false, sent: false }" class="aifirst-report">
			<button type="button" class="aifirst-report__trigger" @click="open = true">
				<?php esc_html_e( 'Report a change', 'aifirstsaas' ); ?>
			</button>

			<div class="aifirst-modal" x-show="open" x-cloak @keydown.escape.window="open = false">
				<div class="aifirst-modal__backdrop" @click="open = false"></div>
				<div class="aifirst-modal__dialog" role="dialog" aria-modal="true">
					<template x-if="!sent">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
							@submit="sent = true">
							<input type="hidden" name="action" value="aifirst_submit_report" />
							<input type="hidden" name="tool_id" value="<?php echo esc_attr( $tool_id ); ?>" />
							<?php wp_nonce_field( 'aifirst_report_' . $tool_id, 'aifirst_report_nonce' ); ?>
							<p><input type="text" name="hp" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;" aria-hidden="true" /></p>
							<h2><?php esc_html_e( 'Report a change', 'aifirstsaas' ); ?></h2>
							<label>
								<span><?php esc_html_e( 'What needs fixing?', 'aifirstsaas' ); ?></span>
								<select name="kind" required>
									<option value="incorrect-maturity"><?php esc_html_e( 'Incorrect maturity score', 'aifirstsaas' ); ?></option>
									<option value="feature-missing"><?php esc_html_e( 'Feature missing', 'aifirstsaas' ); ?></option>
									<option value="feature-removed"><?php esc_html_e( 'Feature removed', 'aifirstsaas' ); ?></option>
									<option value="pricing-changed"><?php esc_html_e( 'Plan / pricing changed', 'aifirstsaas' ); ?></option>
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
							<div class="aifirst-modal__actions">
								<button type="button" @click="open = false" class="aifirst-btn aifirst-btn--ghost"><?php esc_html_e( 'Cancel', 'aifirstsaas' ); ?></button>
								<button type="submit" class="aifirst-btn aifirst-btn--primary"><?php esc_html_e( 'Send report', 'aifirstsaas' ); ?></button>
							</div>
						</form>
					</template>
					<template x-if="sent">
						<div class="aifirst-modal__sent">
							<div class="aifirst-modal__check">✓</div>
							<p><?php esc_html_e( 'Thanks — we\'ll review this shortly.', 'aifirstsaas' ); ?></p>
							<button type="button" @click="open = false; sent = false" class="aifirst-btn"><?php esc_html_e( 'Close', 'aifirstsaas' ); ?></button>
						</div>
					</template>
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
}
