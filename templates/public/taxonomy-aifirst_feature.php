<?php
/**
 * Feature detail (aifirst_feature taxonomy archive).
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

$fm         = AIFirst_Query::feature_meta( $term->term_id );
$evaluate   = $fm ? AIFirst_Query::json_list( $fm->evaluate ) : array();
$related    = $fm ? AIFirst_Query::json_list( $fm->related_features ) : array();
$vendors_dt = $fm ? AIFirst_Query::json_list( $fm->vendors_dont_tell_you ) : array();
$checklist  = $fm ? AIFirst_Query::json_list( $fm->buyers_checklist ) : array();

$haves    = AIFirst_Query::tools_with_feature( $term->slug );
$have_not = AIFirst_Query::tools_without_feature( $term->slug, 12 );
$faq      = AIFirst_Query::faq( array( 'category_id' => $term->slug ) );
?>

<div class="aifirst-shell">
	<div class="aifirst-container">
		<?php require AIFIRST_TEMPLATES_DIR . 'public/partials/flash.php'; ?>

		<?php
		AIFirst_Components::breadcrumbs(
			array(
				array( 'label' => __( 'Home', 'aifirstsaas' ), 'url' => home_url( '/' ) ),
				array( 'label' => __( 'AI Features', 'aifirstsaas' ), 'url' => home_url( '/features/' ) ),
				array( 'label' => $term->name ),
			)
		);
		?>

		<h1 class="aifirst-h1">
			<?php if ( $fm && '' !== (string) $fm->color ) : ?>
				<span class="aifirst-dot" style="background: <?php echo esc_attr( $fm->color ); ?>;"></span>
			<?php endif; ?>
			<?php echo esc_html( $term->name ); ?>
		</h1>

		<p class="aifirst-lead" style="max-width:720px;">
			<?php if ( $fm ) : ?>
				<?php echo wp_kses_post( (string) $fm->description ); ?>
				<?php if ( '' !== (string) $fm->why_matters ) : ?>
					<?php echo wp_kses_post( ' ' . $fm->why_matters ); ?>
				<?php endif; ?>
			<?php else : ?>
				<?php echo esc_html( (string) $term->description ); ?>
			<?php endif; ?>
		</p>

		<h2 class="aifirst-h3"><?php esc_html_e( 'Tools that have it', 'aifirstsaas' ); ?></h2>
		<?php if ( empty( $haves ) ) : ?>
			<p class="aifirst-lead"><?php esc_html_e( 'No tools listed as having this feature yet.', 'aifirstsaas' ); ?></p>
		<?php else : ?>
			<table class="aifirst-table" style="margin-bottom:28px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Tool', 'aifirstsaas' ); ?></th>
						<th><?php esc_html_e( 'Plan', 'aifirstsaas' ); ?></th>
						<th><?php esc_html_e( 'Status', 'aifirstsaas' ); ?></th>
						<th><?php esc_html_e( 'Type', 'aifirstsaas' ); ?></th>
						<th><?php esc_html_e( 'Maturity', 'aifirstsaas' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $haves as $t ) : ?>
						<tr>
							<td style="font-weight:600;">
								<a href="<?php echo esc_url( get_permalink( (int) $t->post_id ) ); ?>"><?php echo esc_html( $t->post_title ); ?></a>
							</td>
							<td><?php AIFirst_Components::plan_pill( $t->feat_plan ); ?></td>
							<td><span class="aifirst-status--<?php echo esc_attr( sanitize_html_class( $t->feat_status ) ); ?>"><?php echo esc_html( $t->feat_status ); ?></span></td>
							<td><?php echo $t->feat_is_native ? esc_html__( 'Native', 'aifirstsaas' ) : esc_html__( 'Third-party', 'aifirstsaas' ); ?></td>
							<td><?php echo esc_html( (int) $t->ai_maturity_score ); ?>/10</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2 class="aifirst-h3"><?php esc_html_e( "Tools that don't have it yet", 'aifirstsaas' ); ?></h2>
		<?php if ( empty( $have_not ) ) : ?>
			<p class="aifirst-lead"><?php esc_html_e( 'Every catalogued tool has this feature.', 'aifirstsaas' ); ?></p>
		<?php else : ?>
			<div style="display:flex; flex-direction:column; gap:6px; margin-bottom:28px;">
				<?php foreach ( $have_not as $t ) : ?>
					<a href="<?php echo esc_url( get_permalink( (int) $t->post_id ) ); ?>" style="display:flex; justify-content:space-between; padding:8px 12px; border:1px solid var(--aif-border); border-radius:8px; color:inherit;">
						<span><?php echo esc_html( $t->post_title ); ?></span>
						<span style="color:var(--aif-sub); font-size:12px;"><?php esc_html_e( 'not listed', 'aifirstsaas' ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $evaluate ) ) : ?>
			<h2 class="aifirst-h3"><?php esc_html_e( 'How to evaluate this feature', 'aifirstsaas' ); ?></h2>
			<div style="margin-bottom:28px;">
				<?php foreach ( $evaluate as $q ) : ?>
					<div style="font-size:13.5px; margin:4px 0;">→ <?php echo esc_html( (string) $q ); ?></div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $related ) ) : ?>
			<h2 class="aifirst-h3"><?php esc_html_e( 'Related features', 'aifirstsaas' ); ?></h2>
			<div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:28px;">
				<?php foreach ( $related as $slug ) :
					$rel_term = get_term_by( 'slug', sanitize_title( (string) $slug ), 'aifirst_feature' );
					if ( ! $rel_term ) { continue; } ?>
					<a class="aifirst-pill" href="<?php echo esc_url( get_term_link( $rel_term ) ); ?>"><?php echo esc_html( $rel_term->name ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( $fm && ( '' !== (string) $fm->how_it_works_surface || '' !== (string) $fm->how_it_works_deeper ) ) : ?>
			<section style="margin-top:12px; max-width:900px;">
				<h2 class="aifirst-h2"><?php echo esc_html( sprintf( /* translators: %s: feature name */ __( 'How %s Actually Works', 'aifirstsaas' ), $term->name ) ); ?></h2>
				<div class="aifirst-card">
					<?php if ( '' !== (string) $fm->how_it_works_surface ) : ?>
						<p><strong><?php esc_html_e( 'Surface level:', 'aifirstsaas' ); ?></strong> <?php echo wp_kses_post( (string) $fm->how_it_works_surface ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== (string) $fm->how_it_works_deeper ) : ?>
						<p style="margin-top:10px;"><strong><?php esc_html_e( 'Deeper level:', 'aifirstsaas' ); ?></strong> <?php echo wp_kses_post( (string) $fm->how_it_works_deeper ); ?></p>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $vendors_dt ) ) : ?>
			<section class="aifirst-card" style="margin-top:14px; max-width:900px; border-color: var(--aif-warn);">
				<strong><?php esc_html_e( "What vendors don't tell you", 'aifirstsaas' ); ?></strong>
				<ul style="padding:10px 0 0; margin:0; list-style:none; display:flex; flex-direction:column; gap:6px;">
					<?php foreach ( $vendors_dt as $line ) : ?>
						<li>• <?php echo esc_html( (string) $line ); ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $checklist ) ) : ?>
			<section class="aifirst-card" style="margin-top:14px; max-width:900px; background: var(--aif-bg2);">
				<strong><?php esc_html_e( "Buyer's Checklist", 'aifirstsaas' ); ?></strong>
				<ul style="padding:10px 0 0; margin:0; list-style:none; display:flex; flex-direction:column; gap:8px;">
					<?php foreach ( $checklist as $item ) : ?>
						<li>☐ <?php echo esc_html( (string) $item ); ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php AIFirst_Components::faq_block( $faq ); ?>
	</div>
</div>

<?php
get_footer();
