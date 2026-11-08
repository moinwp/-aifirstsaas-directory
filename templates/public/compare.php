<?php
/**
 * Compare page (/compare/:a/:b/:c?).
 *
 * @package AIFirst
 * @since   0.4.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$slug_a = (string) get_query_var( AIFirst_Public::QV_CMP_A, '' );
$slug_b = (string) get_query_var( AIFirst_Public::QV_CMP_B, '' );
$slug_c = (string) get_query_var( AIFirst_Public::QV_CMP_C, '' );

$all_tools = get_posts(
	array(
		'post_type'      => 'aifirst_tool',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);

/**
 * Build a {meta, post, features} tuple for a tool slug.
 */
$load = function ( $slug ) {
	if ( '' === $slug ) {
		return null;
	}
	$meta = AIFirst_Query::tool_meta_by_id( $slug );
	if ( ! $meta ) {
		return null;
	}
	return array(
		'meta'     => $meta,
		'post'     => get_post( (int) $meta->post_id ),
		'features' => AIFirst_Query::tool_features( $slug ),
	);
};

$a = $load( $slug_a );
$b = $load( $slug_b );
$c = $load( $slug_c );

/**
 * Collect the union of feature slugs across up to three tools.
 */
$feature_union = array();
foreach ( array( $a, $b, $c ) as $tool ) {
	if ( ! $tool ) { continue; }
	foreach ( $tool['features'] as $f ) {
		$feature_union[ $f->feature_slug ] = $f->feature_name;
	}
}
ksort( $feature_union );

/**
 * Look up a feature row within a tool's features list.
 */
$find_feat = function ( $tool, $slug ) {
	if ( ! $tool ) { return null; }
	foreach ( $tool['features'] as $f ) {
		if ( $f->feature_slug === $slug ) { return $f; }
	}
	return null;
};
?>

<div class="aifirst-shell">
	<div class="aifirst-container">
		<?php require AIFIRST_TEMPLATES_DIR . 'public/partials/flash.php'; ?>

		<h1 class="aifirst-h1"><?php esc_html_e( 'Compare Tools', 'aifirstsaas' ); ?></h1>

		<form method="get" action="<?php echo esc_url( home_url( '/compare/' ) ); ?>" onsubmit="
			event.preventDefault();
			var a=this.a.value, b=this.b.value, c=this.c.value;
			if(!a||!b){return false;}
			window.location.href='<?php echo esc_url( home_url( '/compare/' ) ); ?>'
				+ encodeURIComponent(a)+'/'
				+ encodeURIComponent(b)+'/'
				+ (c?encodeURIComponent(c)+'/':'');
			return false;
		" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:24px;">
			<select name="a">
				<option value=""><?php esc_html_e( '— pick tool A —', 'aifirstsaas' ); ?></option>
				<?php foreach ( $all_tools as $t ) :
					$s = get_post_meta( $t->ID, '_aifirst_tool_id', true ) ?: $t->post_name; ?>
					<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $s, $slug_a ); ?>><?php echo esc_html( $t->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<strong style="color:var(--aif-accent);">VS</strong>
			<select name="b">
				<option value=""><?php esc_html_e( '— pick tool B —', 'aifirstsaas' ); ?></option>
				<?php foreach ( $all_tools as $t ) :
					$s = get_post_meta( $t->ID, '_aifirst_tool_id', true ) ?: $t->post_name; ?>
					<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $s, $slug_b ); ?>><?php echo esc_html( $t->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<strong style="color:var(--aif-accent);">VS</strong>
			<select name="c">
				<option value=""><?php esc_html_e( '— optional —', 'aifirstsaas' ); ?></option>
				<?php foreach ( $all_tools as $t ) :
					$s = get_post_meta( $t->ID, '_aifirst_tool_id', true ) ?: $t->post_name; ?>
					<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $s, $slug_c ); ?>><?php echo esc_html( $t->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="aifirst-btn aifirst-btn--primary"><?php esc_html_e( 'Compare', 'aifirstsaas' ); ?></button>
		</form>

		<?php if ( ! $a || ! $b ) : ?>
			<p class="aifirst-lead"><?php esc_html_e( 'Pick two tools (optionally a third) above to see a side-by-side comparison.', 'aifirstsaas' ); ?></p>
		<?php else :
			$cols       = array_filter( array( $a, $b, $c ) );
			$col_count  = count( $cols );
			$col_grid   = 'grid-template-columns: 180px ' . str_repeat( '1fr ', $col_count ) . ';'; ?>

			<div style="display:grid; <?php echo esc_attr( $col_grid ); ?> gap:0; margin-bottom:28px; border:1px solid var(--aif-border); border-radius:10px; overflow:hidden;">
				<div style="background:var(--aif-bg2); padding:10px; font-size:12px; color:var(--aif-sub); font-weight:600;"><?php esc_html_e( 'Metric', 'aifirstsaas' ); ?></div>
				<?php foreach ( $cols as $t ) : ?>
					<div style="background:var(--aif-bg2); padding:10px; font-weight:700; text-align:center;"><?php echo esc_html( $t['post']->post_title ); ?></div>
				<?php endforeach; ?>

				<?php
				$rows = array(
					array( __( 'AI Maturity', 'aifirstsaas' ),       function ( $t ) { return (int) $t['meta']->ai_maturity_score . '/10'; } ),
					array( __( 'Total AI features', 'aifirstsaas' ), function ( $t ) { return count( $t['features'] ); } ),
					array( __( 'Native / 3rd-party', 'aifirstsaas' ),function ( $t ) { return (int) $t['meta']->native_ai_count . ' / ' . (int) $t['meta']->thirdparty_ai_count; } ),
					array( __( 'Free AI', 'aifirstsaas' ),           function ( $t ) {
						$m = (int) $t['meta']->ai_on_free_plan;
						return 1 === $m ? __( 'Yes', 'aifirstsaas' ) : ( 2 === $m ? __( 'Add-on', 'aifirstsaas' ) : __( 'No', 'aifirstsaas' ) );
					} ),
					array( __( 'Starting price', 'aifirstsaas' ),    function ( $t ) {
						return null === $t['meta']->starting_price_usd ? '—' : '$' . number_format_i18n( (float) $t['meta']->starting_price_usd, 2 );
					} ),
					array( __( 'AI since', 'aifirstsaas' ),          function ( $t ) { return $t['meta']->ai_since ? (int) $t['meta']->ai_since : '—'; } ),
				);
				foreach ( $rows as $row ) : ?>
					<div style="padding:10px; border-top:1px solid var(--aif-border); font-size:12.5px; color:var(--aif-sub);"><?php echo esc_html( $row[0] ); ?></div>
					<?php foreach ( $cols as $t ) : ?>
						<div style="padding:10px; border-top:1px solid var(--aif-border); text-align:center;"><?php echo esc_html( call_user_func( $row[1], $t ) ); ?></div>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</div>

			<h2 class="aifirst-h3"><?php esc_html_e( 'Feature matrix', 'aifirstsaas' ); ?></h2>
			<?php if ( empty( $feature_union ) ) : ?>
				<p class="aifirst-lead"><?php esc_html_e( 'No features catalogued on these tools yet.', 'aifirstsaas' ); ?></p>
			<?php else : ?>
				<div style="display:grid; <?php echo esc_attr( $col_grid ); ?> border:1px solid var(--aif-border); border-radius:10px; overflow:hidden;">
					<div style="background:var(--aif-bg2); padding:9px 10px; font-size:12px; color:var(--aif-sub); font-weight:600;"><?php esc_html_e( 'Feature', 'aifirstsaas' ); ?></div>
					<?php foreach ( $cols as $t ) : ?>
						<div style="background:var(--aif-bg2); padding:9px; text-align:center; font-weight:700;"><?php echo esc_html( $t['post']->post_title ); ?></div>
					<?php endforeach; ?>

					<?php foreach ( $feature_union as $slug => $name ) : ?>
						<div style="padding:9px 10px; border-top:1px solid var(--aif-border); font-size:12.5px;"><?php echo esc_html( $name ); ?></div>
						<?php foreach ( $cols as $t ) :
							$hit = $find_feat( $t, $slug );
							if ( $hit ) {
								$cls   = 'aifirst-cmp-cell--yes';
								$label = esc_html( $hit->plan ? $hit->plan : __( 'Yes', 'aifirstsaas' ) );
							} else {
								$cls   = 'aifirst-cmp-cell--no';
								$label = '—';
							} ?>
							<div class="<?php echo esc_attr( $cls ); ?>" style="padding:9px; border-top:1px solid var(--aif-border);"><?php echo wp_kses_post( $label ); ?></div>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<section style="margin-top:28px; max-width:900px;">
				<h2 class="aifirst-h2"><?php esc_html_e( 'What This Comparison Actually Means', 'aifirstsaas' ); ?></h2>
				<div class="aifirst-card" style="color:var(--aif-sub);">
					<?php esc_html_e( 'Curated side-by-side analysis coming in a later session. For now, use the metric strip and feature matrix above to see where each tool has coverage.', 'aifirstsaas' ); ?>
				</div>
			</section>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();
