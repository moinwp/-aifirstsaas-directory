<?php
/**
 * AI Features index (/features/).
 *
 * @package AIFirst
 * @since   0.4.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

global $wpdb;
$meta_table = $wpdb->prefix . 'aifirst_feature_meta';
$tt         = $wpdb->term_taxonomy;
$terms_t    = $wpdb->terms;

$rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT t.term_id, t.name, t.slug, m.feature_type, m.color
		FROM {$terms_t} t
		INNER JOIN {$tt} tt ON tt.term_id = t.term_id
		LEFT JOIN {$meta_table} m ON m.term_id = t.term_id
		WHERE tt.taxonomy = %s
		ORDER BY t.name ASC",
		'aifirst_feature'
	)
);

$universal = array();
$specific  = array();
foreach ( $rows as $r ) {
	if ( 'universal' === $r->feature_type ) {
		$universal[] = $r;
	} else {
		$specific[] = $r;
	}
}
?>

<div class="aifirst-shell">
	<div class="aifirst-container">
		<?php require AIFIRST_TEMPLATES_DIR . 'public/partials/flash.php'; ?>

		<h1 class="aifirst-h1"><?php esc_html_e( 'AI Feature Types', 'aifirstsaas' ); ?></h1>
		<p class="aifirst-lead"><?php esc_html_e( 'Capabilities we track across the directory, grouped by how widely they apply.', 'aifirstsaas' ); ?></p>

		<?php if ( ! empty( $universal ) ) : ?>
			<h2 class="aifirst-h3"><?php esc_html_e( 'Universal', 'aifirstsaas' ); ?></h2>
			<div class="aifirst-grid-4" style="margin-bottom:32px;">
				<?php foreach ( $universal as $f ) :
					$count = AIFirst_Query::count_tools_with_feature( $f->slug );
					$color = '' !== (string) $f->color ? $f->color : '#2563EB';
					?>
					<a class="aifirst-card" href="<?php echo esc_url( get_term_link( (int) $f->term_id, 'aifirst_feature' ) ); ?>" style="color:inherit;">
						<span class="aifirst-dot" style="background: <?php echo esc_attr( $color ); ?>;"></span>
						<div style="font-weight:700; margin-top:6px;"><?php echo esc_html( $f->name ); ?></div>
						<div style="font-size:12px; color:var(--aif-sub); margin-top:2px;">
							<?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Found in %d tools', 'aifirstsaas' ), $count ) ); ?>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $specific ) ) : ?>
			<h2 class="aifirst-h3"><?php esc_html_e( 'Category-Specific', 'aifirstsaas' ); ?></h2>
			<div class="aifirst-grid-4">
				<?php foreach ( $specific as $f ) :
					$count = AIFirst_Query::count_tools_with_feature( $f->slug );
					$color = '' !== (string) $f->color ? $f->color : '#7C3AED';
					?>
					<a class="aifirst-card" href="<?php echo esc_url( get_term_link( (int) $f->term_id, 'aifirst_feature' ) ); ?>" style="color:inherit;">
						<span class="aifirst-dot" style="background: <?php echo esc_attr( $color ); ?>;"></span>
						<div style="font-weight:700; margin-top:6px;"><?php echo esc_html( $f->name ); ?></div>
						<div style="font-size:12px; color:var(--aif-sub); margin-top:2px;">
							<?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Found in %d tools', 'aifirstsaas' ), $count ) ); ?>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( empty( $universal ) && empty( $specific ) ) : ?>
			<p class="aifirst-lead"><?php esc_html_e( 'No AI features registered yet.', 'aifirstsaas' ); ?></p>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();
