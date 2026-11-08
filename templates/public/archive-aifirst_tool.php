<?php
/**
 * All Tools archive.
 *
 * @package AIFirst
 * @since   0.4.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$cat_f  = isset( $_GET['cat'] )  ? sanitize_text_field( wp_unslash( $_GET['cat'] ) ) : '';
$free_f = isset( $_GET['free'] ) ? sanitize_key( wp_unslash( $_GET['free'] ) ) : 'any';
$min_f  = isset( $_GET['min'] )  ? max( 0, min( 10, (int) $_GET['min'] ) ) : 0;

$args = array(
	'post_type'      => 'aifirst_tool',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => 'title',
	'order'          => 'ASC',
);
if ( '' !== $cat_f ) {
	$args['tax_query'] = array(
		array(
			'taxonomy' => 'aifirst_category',
			'field'    => 'slug',
			'terms'    => $cat_f,
		),
	);
}
$q = new WP_Query( $args );

// Group by first letter + apply free/min filters.
$groups = array();
foreach ( $q->posts as $p ) {
	$m = AIFirst_Query::tool_meta( $p->ID );
	if ( ! $m ) { continue; }
	if ( 'yes'   === $free_f && 1 !== (int) $m->ai_on_free_plan ) { continue; }
	if ( 'addon' === $free_f && 2 !== (int) $m->ai_on_free_plan ) { continue; }
	if ( 'no'    === $free_f && 0 !== (int) $m->ai_on_free_plan ) { continue; }
	if ( $min_f > 0 && (int) $m->ai_maturity_score < $min_f ) { continue; }
	$letter = strtoupper( substr( get_the_title( $p ), 0, 1 ) );
	if ( '' === $letter || ! ctype_alpha( $letter ) ) { $letter = '#'; }
	$groups[ $letter ][] = array( $p, $m );
}
ksort( $groups );
$total = array_sum( array_map( 'count', $groups ) );

$all_cats = get_terms( array( 'taxonomy' => 'aifirst_category', 'hide_empty' => false ) );
$faq      = AIFirst_Query::faq();
?>

<div class="aifirst-shell">
	<div class="aifirst-container">
		<?php require AIFIRST_TEMPLATES_DIR . 'public/partials/flash.php'; ?>

		<h1 class="aifirst-h1"><?php esc_html_e( 'All Tools', 'aifirstsaas' ); ?></h1>
		<p class="aifirst-lead"><?php echo esc_html( sprintf( /* translators: %d: count */ __( '%d tools tracked', 'aifirstsaas' ), $total ) ); ?></p>

		<div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:20px;">
			<?php foreach ( range( 'A', 'Z' ) as $letter ) :
				$active = isset( $groups[ $letter ] ); ?>
				<a href="#aifirst-letter-<?php echo esc_attr( $letter ); ?>"
					style="width:26px; height:26px; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:12px; background:<?php echo $active ? 'var(--aif-accent)' : 'var(--aif-bg2)'; ?>; color:<?php echo $active ? '#fff' : 'var(--aif-sub)'; ?>;">
					<?php echo esc_html( $letter ); ?>
				</a>
			<?php endforeach; ?>
		</div>

		<div class="aifirst-threecol">
			<aside class="aifirst-filters">
				<form method="get">
					<strong><?php esc_html_e( 'Filters', 'aifirstsaas' ); ?></strong>

					<div class="aifirst-filters__h"><?php esc_html_e( 'Category', 'aifirstsaas' ); ?></div>
					<select name="cat" onchange="this.form.submit()">
						<option value=""><?php esc_html_e( 'Any', 'aifirstsaas' ); ?></option>
						<?php foreach ( $all_cats as $c ) : ?>
							<option value="<?php echo esc_attr( $c->slug ); ?>" <?php selected( $cat_f, $c->slug ); ?>><?php echo esc_html( $c->name ); ?></option>
						<?php endforeach; ?>
					</select>

					<div class="aifirst-filters__h"><?php esc_html_e( 'AI on Free Plan', 'aifirstsaas' ); ?></div>
					<?php foreach ( array( 'any' => __( 'Any', 'aifirstsaas' ), 'yes' => __( 'Yes', 'aifirstsaas' ), 'addon' => __( 'Add-on', 'aifirstsaas' ), 'no' => __( 'No', 'aifirstsaas' ) ) as $k => $l ) : ?>
						<label class="aifirst-filters__option <?php echo $free_f === $k ? 'is-active' : ''; ?>">
							<input type="radio" name="free" value="<?php echo esc_attr( $k ); ?>" <?php checked( $free_f, $k ); ?> onchange="this.form.submit()" style="margin-right:6px;" />
							<?php echo esc_html( $l ); ?>
						</label>
					<?php endforeach; ?>

					<div class="aifirst-filters__h"><?php esc_html_e( 'Min AI Maturity', 'aifirstsaas' ); ?></div>
					<input type="number" name="min" min="0" max="10" value="<?php echo esc_attr( $min_f ); ?>" onchange="this.form.submit()" style="width:80px;" />

					<p style="margin-top:14px;">
						<a href="<?php echo esc_url( get_post_type_archive_link( 'aifirst_tool' ) ); ?>" style="font-size:12.5px;">
							<?php esc_html_e( 'Clear all filters', 'aifirstsaas' ); ?>
						</a>
					</p>
				</form>
			</aside>

			<main style="grid-column: 2 / span 2;">
				<?php if ( empty( $groups ) ) : ?>
					<p class="aifirst-lead"><?php esc_html_e( 'No tools match these filters.', 'aifirstsaas' ); ?></p>
				<?php else : ?>
					<?php foreach ( $groups as $letter => $rows ) : ?>
						<h2 class="aifirst-letter" id="aifirst-letter-<?php echo esc_attr( $letter ); ?>"><?php echo esc_html( $letter ); ?></h2>
						<div style="display:flex; flex-direction:column; gap:10px;">
							<?php foreach ( $rows as $row ) : ?>
								<?php AIFirst_Components::tool_card( $row[0], $row[1] ); ?>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</main>
		</div>

		<?php AIFirst_Components::faq_block( $faq ); ?>
	</div>
</div>

<?php
get_footer();
