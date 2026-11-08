<?php
/**
 * AI Radar (/changelog/).
 *
 * @package AIFirst
 * @since   0.4.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$tool_f    = isset( $_GET['tool'] )   ? sanitize_title( wp_unslash( $_GET['tool'] ) ) : '';
$type_f    = isset( $_GET['type'] )   ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';
$period_f  = isset( $_GET['days'] )   ? max( 0, (int) $_GET['days'] ) : 0;

$args = array( 'limit' => 50 );
if ( '' !== $tool_f ) { $args['tool_id']     = $tool_f; }
if ( '' !== $type_f ) { $args['change_type'] = $type_f; }
if ( $period_f > 0 )  { $args['days']        = $period_f; }

$rows = AIFirst_Query::radar_recent( $args );
$faq  = AIFirst_Query::faq();
?>

<div class="aifirst-shell">
	<div class="aifirst-container">
		<?php require AIFIRST_TEMPLATES_DIR . 'public/partials/flash.php'; ?>

		<?php
		AIFirst_Components::breadcrumbs(
			array(
				array( 'label' => __( 'Home', 'aifirstsaas' ), 'url' => home_url( '/' ) ),
				array( 'label' => __( 'AI Radar', 'aifirstsaas' ) ),
			)
		);
		?>

		<h1 class="aifirst-h1"><?php esc_html_e( 'AI Radar', 'aifirstsaas' ); ?></h1>
		<p class="aifirst-lead"><?php esc_html_e( 'Every verified change across the catalogue, newest first.', 'aifirstsaas' ); ?></p>

		<form method="get" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
			<input type="text" name="tool" placeholder="<?php esc_attr_e( 'Filter by tool slug…', 'aifirstsaas' ); ?>" value="<?php echo esc_attr( $tool_f ); ?>" />
			<select name="type">
				<option value=""><?php esc_html_e( 'Any change type', 'aifirstsaas' ); ?></option>
				<option value="new-feature" <?php selected( $type_f, 'new-feature' ); ?>><?php esc_html_e( 'New Feature', 'aifirstsaas' ); ?></option>
				<option value="upgraded" <?php selected( $type_f, 'upgraded' ); ?>><?php esc_html_e( 'Upgraded', 'aifirstsaas' ); ?></option>
				<option value="pricing-change" <?php selected( $type_f, 'pricing-change' ); ?>><?php esc_html_e( 'Plan Change', 'aifirstsaas' ); ?></option>
				<option value="deprecated" <?php selected( $type_f, 'deprecated' ); ?>><?php esc_html_e( 'Removed', 'aifirstsaas' ); ?></option>
				<option value="launch" <?php selected( $type_f, 'launch' ); ?>><?php esc_html_e( 'Launch', 'aifirstsaas' ); ?></option>
			</select>
			<select name="days">
				<option value="0" <?php selected( $period_f, 0 ); ?>><?php esc_html_e( 'All time', 'aifirstsaas' ); ?></option>
				<option value="7" <?php selected( $period_f, 7 ); ?>><?php esc_html_e( 'Last 7 days', 'aifirstsaas' ); ?></option>
				<option value="30" <?php selected( $period_f, 30 ); ?>><?php esc_html_e( 'Last 30 days', 'aifirstsaas' ); ?></option>
				<option value="90" <?php selected( $period_f, 90 ); ?>><?php esc_html_e( 'Last 3 months', 'aifirstsaas' ); ?></option>
			</select>
			<button type="submit" class="aifirst-btn aifirst-btn--primary"><?php esc_html_e( 'Apply', 'aifirstsaas' ); ?></button>
		</form>

		<div class="aifirst-twocol">
			<main>
				<?php if ( empty( $rows ) ) : ?>
					<p class="aifirst-lead"><?php esc_html_e( 'No changes match these filters.', 'aifirstsaas' ); ?></p>
				<?php else : ?>
					<?php foreach ( $rows as $r ) :
						$tm   = AIFirst_Query::tool_meta_by_id( (string) $r->tool_id );
						$name = $tm ? get_the_title( (int) $tm->post_id ) : $r->tool_id;
						?>
						<div class="aifirst-radar-row">
							<div class="aifirst-radar-row__date"><?php echo esc_html( AIFirst_Components::date_display( $r->change_date ) ); ?></div>
							<div class="aifirst-radar-row__body">
								<div class="aifirst-radar-row__title">
									<?php if ( $tm ) : ?>
										<a href="<?php echo esc_url( get_permalink( (int) $tm->post_id ) ); ?>"><strong><?php echo esc_html( $name ); ?></strong></a>
									<?php else : ?>
										<strong><?php echo esc_html( $name ); ?></strong>
									<?php endif; ?>
									— <?php echo esc_html( $r->what_changed ); ?>
								</div>
								<?php if ( '' !== (string) $r->why_it_matters ) : ?>
									<div class="aifirst-radar-row__why"><?php echo esc_html( sprintf( /* translators: %s: reason */ __( 'Why it matters: %s', 'aifirstsaas' ), $r->why_it_matters ) ); ?></div>
								<?php endif; ?>
							</div>
							<div class="aifirst-radar-row__type aifirst-radar-row__type--<?php echo esc_attr( sanitize_html_class( $r->change_type ) ); ?>"><?php echo esc_html( $r->change_type ); ?></div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</main>
			<aside>
				<div class="aifirst-card">
					<?php AIFirst_Components::newsletter_form(); ?>
				</div>
			</aside>
		</div>

		<?php AIFirst_Components::faq_block( $faq ); ?>
	</div>
</div>

<?php
get_footer();
