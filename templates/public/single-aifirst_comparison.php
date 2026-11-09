<?php
/**
 * Single comparison page (/vs/:slug/).
 *
 * @package AIFirst
 * @since   0.5.0
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();

$comp      = get_post();
$slugs     = AIFirst_Comparison::get_tool_ids( $comp->ID );
$verdicts  = AIFirst_Comparison::get_verdicts( $comp->ID );
$cols      = array();
foreach ( $slugs as $slug ) {
	$meta = AIFirst_Query::tool_meta_by_id( $slug );
	if ( ! $meta ) {
		continue;
	}
	$cols[] = array(
		'meta'     => $meta,
		'post'     => get_post( (int) $meta->post_id ),
		'features' => AIFirst_Query::tool_features( $slug ),
	);
}
$distinct_count = count( $cols );

$feature_union = array();
foreach ( $cols as $tool ) {
	foreach ( $tool['features'] as $f ) {
		$feature_union[ $f->feature_slug ] = $f->feature_name;
	}
}
ksort( $feature_union );

$find_feat = static function ( $tool, $slug ) {
	foreach ( $tool['features'] as $f ) {
		if ( $f->feature_slug === $slug ) { return $f; }
	}
	return null;
};
?>

<div class="aifirst-shell">
	<div class="aifirst-container">
		<?php require AIFIRST_TEMPLATES_DIR . 'public/partials/flash.php'; ?>

		<?php
		AIFirst_Components::breadcrumbs(
			array(
				array( 'label' => __( 'Home', 'aifirstsaas' ), 'url' => home_url( '/' ) ),
				array( 'label' => __( 'Compare', 'aifirstsaas' ), 'url' => home_url( '/compare/' ) ),
				array( 'label' => get_the_title( $comp ) ),
			)
		);
		?>

		<h1 class="aifirst-h1"><?php echo esc_html( get_the_title( $comp ) ); ?></h1>
		<p class="aifirst-lead">
			<?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Last reviewed %s', 'aifirstsaas' ), mysql2date( get_option( 'date_format' ), $comp->post_modified ) ) ); ?>
		</p>

		<?php if ( $distinct_count < 2 ) : ?>
			<p class="aifirst-lead"><?php esc_html_e( 'This comparison does not have enough resolved tools to render. Edit it in the admin to fix the tool slugs.', 'aifirstsaas' ); ?></p>
		<?php else :
			$col_grid = 'grid-template-columns: 180px ' . str_repeat( '1fr ', $distinct_count ) . ';'; ?>

			<div class="aifirst-grid-3" style="margin-bottom:28px;">
				<?php foreach ( $cols as $t ) : ?>
					<a class="aifirst-card" href="<?php echo esc_url( get_permalink( (int) $t['meta']->post_id ) ); ?>" style="display:flex; gap:12px; align-items:center;">
						<?php AIFirst_Components::avatar( (int) $t['meta']->post_id, $t['meta']->initials, $t['meta']->avatar_color, 40 ); ?>
						<div>
							<div style="font-weight:700;"><?php echo esc_html( get_the_title( (int) $t['meta']->post_id ) ); ?></div>
							<div style="font-size:12.5px; color:var(--aif-sub);"><?php echo esc_html( (int) $t['meta']->ai_maturity_score ); ?>/10</div>
						</div>
					</a>
				<?php endforeach; ?>
			</div>

			<?php if ( '' !== (string) $verdicts['budget'] || '' !== (string) $verdicts['enterprise'] ) : ?>
				<section class="aifirst-card" style="margin-bottom:28px;">
					<?php if ( '' !== (string) $verdicts['budget'] ) : ?>
						<p><strong><?php esc_html_e( 'For AI on a budget:', 'aifirstsaas' ); ?></strong> <?php echo esc_html( (string) $verdicts['budget'] ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== (string) $verdicts['enterprise'] ) : ?>
						<p><strong><?php esc_html_e( 'For enterprise:', 'aifirstsaas' ); ?></strong> <?php echo esc_html( (string) $verdicts['enterprise'] ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== (string) $verdicts['use_case_a'] ) : ?>
						<p><strong><?php esc_html_e( 'Leans toward tool A:', 'aifirstsaas' ); ?></strong> <?php echo esc_html( (string) $verdicts['use_case_a'] ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== (string) $verdicts['use_case_b'] ) : ?>
						<p><strong><?php esc_html_e( 'Leans toward tool B:', 'aifirstsaas' ); ?></strong> <?php echo esc_html( (string) $verdicts['use_case_b'] ); ?></p>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<div style="display:grid; <?php echo esc_attr( $col_grid ); ?> gap:0; margin-bottom:28px; border:1px solid var(--aif-border); border-radius:10px; overflow:hidden;">
				<div style="background:var(--aif-bg2); padding:10px; font-size:12px; color:var(--aif-sub); font-weight:600;"><?php esc_html_e( 'Metric', 'aifirstsaas' ); ?></div>
				<?php foreach ( $cols as $t ) : ?>
					<div style="background:var(--aif-bg2); padding:10px; font-weight:700; text-align:center;"><?php echo esc_html( $t['post']->post_title ); ?></div>
				<?php endforeach; ?>
				<?php
				$rows = array(
					array( __( 'AI Maturity', 'aifirstsaas' ),       static function ( $t ) { return (int) $t['meta']->ai_maturity_score . '/10'; } ),
					array( __( 'Total AI features', 'aifirstsaas' ), static function ( $t ) { return count( $t['features'] ); } ),
					array( __( 'Native / 3rd-party', 'aifirstsaas' ),static function ( $t ) { return (int) $t['meta']->native_ai_count . ' / ' . (int) $t['meta']->thirdparty_ai_count; } ),
					array( __( 'Free AI', 'aifirstsaas' ),           static function ( $t ) {
						$m = (int) $t['meta']->ai_on_free_plan;
						return 1 === $m ? __( 'Yes', 'aifirstsaas' ) : ( 2 === $m ? __( 'Add-on', 'aifirstsaas' ) : __( 'No', 'aifirstsaas' ) );
					} ),
					array( __( 'Starting price', 'aifirstsaas' ),    static function ( $t ) {
						return null === $t['meta']->starting_price_usd ? '—' : '$' . number_format_i18n( (float) $t['meta']->starting_price_usd, 2 );
					} ),
				);
				foreach ( $rows as $row ) : ?>
					<div style="padding:10px; border-top:1px solid var(--aif-border); font-size:12.5px; color:var(--aif-sub);"><?php echo esc_html( $row[0] ); ?></div>
					<?php foreach ( $cols as $t ) : ?>
						<div style="padding:10px; border-top:1px solid var(--aif-border); text-align:center;"><?php echo esc_html( call_user_func( $row[1], $t ) ); ?></div>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</div>

			<?php if ( ! empty( $feature_union ) ) : ?>
				<h2 class="aifirst-h3"><?php esc_html_e( 'Feature matrix', 'aifirstsaas' ); ?></h2>
				<div style="display:grid; <?php echo esc_attr( $col_grid ); ?> border:1px solid var(--aif-border); border-radius:10px; overflow:hidden; margin-bottom:28px;">
					<div style="background:var(--aif-bg2); padding:9px 10px; font-size:12px; color:var(--aif-sub); font-weight:600;"><?php esc_html_e( 'Feature', 'aifirstsaas' ); ?></div>
					<?php foreach ( $cols as $t ) : ?>
						<div style="background:var(--aif-bg2); padding:9px; text-align:center; font-weight:700;"><?php echo esc_html( $t['post']->post_title ); ?></div>
					<?php endforeach; ?>
					<?php foreach ( $feature_union as $slug => $name ) : ?>
						<div style="padding:9px 10px; border-top:1px solid var(--aif-border); font-size:12.5px;"><?php echo esc_html( $name ); ?></div>
						<?php foreach ( $cols as $t ) :
							$hit = $find_feat( $t, $slug );
							$cls = $hit ? 'aifirst-cmp-cell--yes' : 'aifirst-cmp-cell--no';
							$lbl = $hit ? ( $hit->plan ? $hit->plan : __( 'Yes', 'aifirstsaas' ) ) : '—'; ?>
							<div class="<?php echo esc_attr( $cls ); ?>" style="padding:9px; border-top:1px solid var(--aif-border);"><?php echo esc_html( $lbl ); ?></div>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<section style="max-width:900px;">
				<h2 class="aifirst-h2"><?php esc_html_e( 'What This Comparison Actually Means', 'aifirstsaas' ); ?></h2>
				<div class="aifirst-card aifirst-expert">
					<?php echo apply_filters( 'the_content', $comp->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
			</section>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();
