<?php
/**
 * Category detail (aifirst_category taxonomy archive).
 *
 * @package AIFirst
 * @since   0.4.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$term = get_queried_object();
if ( ! $term || is_wp_error( $term ) ) {
	get_footer();
	return;
}

$stats     = AIFirst_Query::category_stats( $term->term_id );
$cat_meta  = AIFirst_Query::category_meta( $term->term_id );
$checklist = $cat_meta ? AIFirst_Query::json_list( $cat_meta->buyers_checklist ) : array();
$faq       = AIFirst_Query::faq( array( 'category_id' => $term->slug ) );

$sort = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'maturity';
$free = isset( $_GET['free'] ) ? sanitize_key( wp_unslash( $_GET['free'] ) ) : 'any';
$min  = isset( $_GET['min'] )  ? max( 0, min( 10, (int) $_GET['min'] ) ) : 0;

$query = new WP_Query(
	array(
		'post_type'      => 'aifirst_tool',
		'posts_per_page' => 24,
		'post_status'    => 'publish',
		'paged'          => max( 1, (int) get_query_var( 'paged', 1 ) ),
		'tax_query'      => array(
			array(
				'taxonomy' => 'aifirst_category',
				'field'    => 'term_id',
				'terms'    => (int) $term->term_id,
			),
		),
	)
);

$cat_tool_ids = array();
foreach ( $query->posts as $p ) {
	$slug = get_post_meta( $p->ID, '_aifirst_tool_id', true );
	if ( '' === $slug ) {
		$slug = $p->post_name;
	}
	$cat_tool_ids[] = $slug;
}
$cat_radar = ! empty( $cat_tool_ids ) ? AIFirst_Query::radar_recent( array( 'tool_ids' => $cat_tool_ids, 'limit' => 6 ) ) : array();
?>

<div class="aifirst-shell">
	<div class="aifirst-container">
		<?php require AIFIRST_TEMPLATES_DIR . 'public/partials/flash.php'; ?>

		<?php
		AIFirst_Components::breadcrumbs(
			array(
				array( 'label' => __( 'Home', 'aifirstsaas' ), 'url' => home_url( '/' ) ),
				array( 'label' => __( 'Categories', 'aifirstsaas' ) ),
				array( 'label' => $term->name ),
			)
		);
		?>

		<header style="display:flex; align-items:baseline; gap:10px; flex-wrap:wrap;">
			<h1 class="aifirst-h1">
				<?php if ( $cat_meta && '' !== (string) $cat_meta->icon ) : ?>
					<span style="margin-right:8px;" aria-hidden="true"><?php echo esc_html( $cat_meta->icon ); ?></span>
				<?php endif; ?>
				<?php echo esc_html( $term->name ); ?> — <?php esc_html_e( 'AI Features', 'aifirstsaas' ); ?>
			</h1>
			<span style="color:var(--aif-sub);"><?php echo esc_html( number_format_i18n( $stats['tool_count'] ) ); ?> <?php esc_html_e( 'tools', 'aifirstsaas' ); ?></span>
		</header>

		<?php if ( '' !== trim( (string) $term->description ) ) : ?>
			<p class="aifirst-lead"><?php echo esc_html( $term->description ); ?></p>
		<?php endif; ?>

		<div class="aifirst-stat-strip">
			<span><?php echo esc_html( number_format_i18n( $stats['tool_count'] ) ); ?> <?php esc_html_e( 'tools tracked', 'aifirstsaas' ); ?></span>
			<span class="aifirst-stat-strip__sep">·</span>
			<span><?php echo esc_html( $stats['free_pct'] ); ?>% <?php esc_html_e( 'have free AI', 'aifirstsaas' ); ?></span>
			<span class="aifirst-stat-strip__sep">·</span>
			<span><?php esc_html_e( 'Avg maturity', 'aifirstsaas' ); ?> <?php echo esc_html( $stats['avg_maturity'] ); ?></span>
			<?php if ( $cat_meta && '' !== (string) $cat_meta->most_common_ai ) : ?>
				<span class="aifirst-stat-strip__sep">·</span>
				<span><?php esc_html_e( 'Most common:', 'aifirstsaas' ); ?> <?php echo esc_html( $cat_meta->most_common_ai ); ?></span>
			<?php endif; ?>
			<?php if ( $cat_meta && '' !== (string) $cat_meta->fastest_growing ) : ?>
				<span class="aifirst-stat-strip__sep">·</span>
				<span><?php esc_html_e( 'Fastest growing:', 'aifirstsaas' ); ?> <?php echo esc_html( $cat_meta->fastest_growing ); ?></span>
			<?php endif; ?>
		</div>

		<form method="get" style="display:flex; gap:12px; margin-bottom:18px; flex-wrap:wrap; align-items:center;">
			<label><?php esc_html_e( 'Sort', 'aifirstsaas' ); ?>:
				<select name="sort" onchange="this.form.submit()">
					<option value="maturity" <?php selected( $sort, 'maturity' ); ?>><?php esc_html_e( 'AI Maturity (high → low)', 'aifirstsaas' ); ?></option>
					<option value="alpha" <?php selected( $sort, 'alpha' ); ?>><?php esc_html_e( 'Alphabetical', 'aifirstsaas' ); ?></option>
					<option value="updated" <?php selected( $sort, 'updated' ); ?>><?php esc_html_e( 'Recently Updated', 'aifirstsaas' ); ?></option>
				</select>
			</label>
			<label><?php esc_html_e( 'Free AI', 'aifirstsaas' ); ?>:
				<select name="free" onchange="this.form.submit()">
					<option value="any" <?php selected( $free, 'any' ); ?>><?php esc_html_e( 'Any', 'aifirstsaas' ); ?></option>
					<option value="yes" <?php selected( $free, 'yes' ); ?>><?php esc_html_e( 'Yes', 'aifirstsaas' ); ?></option>
					<option value="addon" <?php selected( $free, 'addon' ); ?>><?php esc_html_e( 'Add-on', 'aifirstsaas' ); ?></option>
					<option value="no" <?php selected( $free, 'no' ); ?>><?php esc_html_e( 'No', 'aifirstsaas' ); ?></option>
				</select>
			</label>
			<label><?php esc_html_e( 'Min maturity', 'aifirstsaas' ); ?>:
				<input type="number" name="min" min="0" max="10" value="<?php echo esc_attr( $min ); ?>" onchange="this.form.submit()" style="width:70px;" />
			</label>
		</form>

		<div class="aifirst-twocol">
			<main>
				<?php
				// Build rows with meta so we can filter+sort in PHP; dataset per
				// category is small enough.
				$rows = array();
				foreach ( $query->posts as $p ) {
					$m = AIFirst_Query::tool_meta( $p->ID );
					if ( ! $m ) {
						continue;
					}
					if ( 'yes'   === $free && 1 !== (int) $m->ai_on_free_plan ) { continue; }
					if ( 'addon' === $free && 2 !== (int) $m->ai_on_free_plan ) { continue; }
					if ( 'no'    === $free && 0 !== (int) $m->ai_on_free_plan ) { continue; }
					if ( $min > 0 && (int) $m->ai_maturity_score < $min ) { continue; }
					$rows[] = array( $p, $m );
				}
				usort( $rows, function ( $a, $b ) use ( $sort ) {
					list( $pa, $ma ) = $a;
					list( $pb, $mb ) = $b;
					if ( 'alpha' === $sort )   { return strcasecmp( $pa->post_title, $pb->post_title ); }
					if ( 'updated' === $sort ) { return strcmp( (string) $mb->updated_at, (string) $ma->updated_at ); }
					return (int) $mb->ai_maturity_score - (int) $ma->ai_maturity_score;
				} );

				if ( empty( $rows ) ) : ?>
					<p class="aifirst-lead"><?php esc_html_e( 'No tools match these filters.', 'aifirstsaas' ); ?></p>
				<?php else : ?>
					<div style="display:flex; flex-direction:column; gap:10px;">
						<?php foreach ( $rows as $row ) : ?>
							<?php AIFirst_Components::tool_card( $row[0], $row[1] ); ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( $cat_meta ) : ?>
					<section style="margin-top:36px;">
						<h2 class="aifirst-h2"><?php echo esc_html( sprintf( /* translators: %s: category */ __( 'Understanding AI in %s', 'aifirstsaas' ), $term->name ) ); ?></h2>
						<div class="aifirst-grid-3">
							<div class="aifirst-card">
								<div class="aifirst-eyebrow" style="color:var(--aif-good);"><?php esc_html_e( 'What AI does well', 'aifirstsaas' ); ?></div>
								<p style="margin:8px 0 0;"><?php echo wp_kses_post( (string) $cat_meta->what_ai_does_well ); ?></p>
							</div>
							<div class="aifirst-card">
								<div class="aifirst-eyebrow" style="color:var(--aif-warn);"><?php esc_html_e( "What's still immature", 'aifirstsaas' ); ?></div>
								<p style="margin:8px 0 0;"><?php echo wp_kses_post( (string) $cat_meta->whats_immature ); ?></p>
							</div>
							<div class="aifirst-card">
								<div class="aifirst-eyebrow"><?php esc_html_e( 'The honest observation', 'aifirstsaas' ); ?></div>
								<p style="margin:8px 0 0;"><?php echo wp_kses_post( (string) $cat_meta->honest_observation ); ?></p>
							</div>
						</div>
						<?php if ( ! empty( $checklist ) ) : ?>
							<div class="aifirst-card" style="margin-top:14px; background: var(--aif-bg2);">
								<strong><?php esc_html_e( "Buyer's Checklist", 'aifirstsaas' ); ?></strong>
								<ul style="list-style:none; padding:10px 0 0; margin:0; display:flex; flex-direction:column; gap:8px;">
									<?php foreach ( $checklist as $item ) : ?>
										<li>☐ <?php echo esc_html( (string) $item ); ?></li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<?php AIFirst_Components::faq_block( $faq ); ?>
			</main>

			<aside>
				<div class="aifirst-card">
					<strong><?php echo esc_html( sprintf( /* translators: %s: category */ __( 'Recent AI updates in %s', 'aifirstsaas' ), $term->name ) ); ?></strong>
					<?php if ( empty( $cat_radar ) ) : ?>
						<p style="color:var(--aif-sub); font-size:12.5px; margin-top:8px;"><?php esc_html_e( 'No recent updates.', 'aifirstsaas' ); ?></p>
					<?php else : ?>
						<?php foreach ( $cat_radar as $r ) :
							$tm = AIFirst_Query::tool_meta_by_id( (string) $r->tool_id );
							if ( ! $tm ) { continue; } ?>
							<div style="padding:9px 0; border-bottom:1px solid var(--aif-border);">
								<div style="font-size:12.5px;">
									<a href="<?php echo esc_url( get_permalink( (int) $tm->post_id ) ); ?>"><strong><?php echo esc_html( get_the_title( (int) $tm->post_id ) ); ?></strong></a>
									<?php echo esc_html( $r->what_changed ); ?>
								</div>
								<div style="font-size:11px; color:var(--aif-sub); margin-top:2px;"><?php echo esc_html( AIFirst_Components::date_display( $r->change_date ) ); ?></div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</aside>
		</div>
	</div>
</div>

<?php
get_footer();
