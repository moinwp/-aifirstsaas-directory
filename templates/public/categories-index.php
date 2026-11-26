<?php
/**
 * Categories Index (/categories/).
 *
 * @package AIFirst
 * @since   0.8.5
 */

defined( 'ABSPATH' ) || exit;

get_header();

$all_cats = get_terms(
	array(
		'taxonomy'   => 'aifirst_category',
		'hide_empty' => false,
		'parent'     => 0,
		'orderby'    => 'name',
		'order'      => 'ASC',
	)
);
if ( is_wp_error( $all_cats ) ) {
	$all_cats = array();
}

$most_active = AIFirst_Query::most_active_categories( 3, 30 );
?>

<div class="aifirst-shell">
	<div class="aifirst-container">
		<?php require AIFIRST_TEMPLATES_DIR . 'public/partials/flash.php'; ?>

		<?php
		AIFirst_Components::breadcrumbs(
			array(
				array( 'label' => __( 'Home', 'aifirstsaas' ), 'url' => home_url( '/' ) ),
				array( 'label' => __( 'Categories', 'aifirstsaas' ) ),
			)
		);
		?>

		<h1 class="aifirst-h1"><?php esc_html_e( 'Categories', 'aifirstsaas' ); ?></h1>
		<p class="aifirst-lead"><?php esc_html_e( 'Every category has its own AI landscape.', 'aifirstsaas' ); ?></p>

		<!-- Filter pill row: horizontal scroller linking to each category archive -->
		<nav class="aifirst-tabs aifirst-cat-pills" aria-label="<?php esc_attr_e( 'Jump to a category', 'aifirstsaas' ); ?>" style="margin-bottom:24px;">
			<a class="aifirst-tab is-active" href="<?php echo esc_url( home_url( '/categories/' ) ); ?>"><?php esc_html_e( 'All', 'aifirstsaas' ); ?></a>
			<?php foreach ( $all_cats as $c ) :
				$meta  = AIFirst_Query::category_meta( (int) $c->term_id );
				$icon  = $meta && '' !== (string) $meta->icon ? $meta->icon : '';
				?>
				<a class="aifirst-tab" href="<?php echo esc_url( get_term_link( $c ) ); ?>">
					<?php if ( '' !== $icon ) : ?>
						<span aria-hidden="true" style="margin-right:4px;"><?php echo esc_html( $icon ); ?></span>
					<?php endif; ?>
					<?php echo esc_html( $c->name ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<?php if ( ! empty( $most_active ) ) : ?>
			<h2 class="aifirst-h3" style="margin-top:8px;"><?php esc_html_e( 'Most Active Categories', 'aifirstsaas' ); ?></h2>
			<div class="aifirst-cat-active" style="margin-bottom:28px;">
				<?php foreach ( $most_active as $row ) :
					$term = get_term( (int) $row->term_id, 'aifirst_category' );
					if ( ! $term || is_wp_error( $term ) ) { continue; }
					$meta = AIFirst_Query::category_meta( (int) $term->term_id );
					$icon = $meta && '' !== (string) $meta->icon ? $meta->icon : '';
					?>
					<a class="aifirst-card aifirst-cat-active__card" href="<?php echo esc_url( get_term_link( $term ) ); ?>">
						<div class="aifirst-cat-active__title">
							<?php if ( '' !== $icon ) : ?>
								<span aria-hidden="true" style="margin-right:8px; font-size:20px;"><?php echo esc_html( $icon ); ?></span>
							<?php endif; ?>
							<?php echo esc_html( $term->name ); ?>
						</div>
						<div class="aifirst-cat-active__count">
							<?php echo esc_html( sprintf( /* translators: %d: count */ _n( '%d change this month', '%d changes this month', (int) $row->change_count, 'aifirstsaas' ), (int) $row->change_count ) ); ?>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<h2 class="aifirst-h3"><?php esc_html_e( 'All Categories', 'aifirstsaas' ); ?></h2>
		<?php if ( empty( $all_cats ) ) : ?>
			<p class="aifirst-lead"><?php esc_html_e( 'No categories yet.', 'aifirstsaas' ); ?></p>
		<?php else : ?>
			<div class="aifirst-cat-grid">
				<?php foreach ( $all_cats as $c ) :
					$term_id   = (int) $c->term_id;
					$cat_meta  = AIFirst_Query::category_meta( $term_id );
					$icon      = $cat_meta && '' !== (string) $cat_meta->icon ? $cat_meta->icon : '';
					$stats     = AIFirst_Query::category_stats( $term_id );
					$children  = get_term_children( $term_id, 'aifirst_category' );
					$has_subs  = ! is_wp_error( $children ) && ! empty( $children );
					$sub_count = $has_subs ? count( $children ) : 0;
					?>
					<article class="aifirst-card aifirst-cat-card">
						<header class="aifirst-cat-card__header">
							<a class="aifirst-cat-card__title" href="<?php echo esc_url( get_term_link( $c ) ); ?>">
								<?php if ( '' !== $icon ) : ?>
									<span aria-hidden="true" style="margin-right:6px;"><?php echo esc_html( $icon ); ?></span>
								<?php endif; ?>
								<?php echo esc_html( $c->name ); ?>
							</a>
							<?php if ( $has_subs ) : ?>
								<span class="aifirst-cat-card__stats">
									<?php echo esc_html( sprintf( /* translators: %d: subcategory count */ _n( '%d subcategory', '%d subcategories', $sub_count, 'aifirstsaas' ), $sub_count ) ); ?>
								</span>
							<?php else : ?>
								<span class="aifirst-cat-card__stats">
									<?php echo esc_html( sprintf( /* translators: 1: tool count 2: avg maturity */ __( '%1$d tools · avg %2$s/10', 'aifirstsaas' ), (int) $stats['tool_count'], number_format_i18n( (float) $stats['avg_maturity'], 1 ) ) ); ?>
								</span>
							<?php endif; ?>
						</header>

						<?php if ( $has_subs ) : ?>
							<a class="aifirst-cat-card__cta" href="<?php echo esc_url( get_term_link( $c ) ); ?>">
								<?php esc_html_e( 'Browse subcategories →', 'aifirstsaas' ); ?>
							</a>
						<?php else :
							$top = AIFirst_Query::leaderboard( array( 'category_id' => $term_id, 'limit' => 3 ) );
							if ( ! empty( $top ) ) : ?>
								<ul class="aifirst-cat-card__tools">
									<?php foreach ( $top as $t ) : ?>
										<li>
											<a href="<?php echo esc_url( get_permalink( (int) $t->post_id ) ); ?>">
												<span class="aifirst-cat-card__tool-name"><?php echo esc_html( $t->post_title ); ?></span>
												<span class="aifirst-cat-card__tool-score"><?php echo esc_html( (int) $t->ai_maturity_score ); ?>/10</span>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php else : ?>
								<p class="aifirst-cat-card__empty"><?php esc_html_e( 'No tools yet.', 'aifirstsaas' ); ?></p>
							<?php endif; ?>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();
