<?php
/**
 * Flash banner for submit/report redirects.
 *
 * @package AIFirst
 * @since   0.4.0
 */

defined( 'ABSPATH' ) || exit;

$aifirst_flash_kind = '';
if ( isset( $_GET['aifirst_submit'] ) ) {
	$aifirst_flash_kind = sanitize_key( wp_unslash( $_GET['aifirst_submit'] ) );
} elseif ( isset( $_GET['aifirst_report'] ) ) {
	$aifirst_flash_kind = sanitize_key( wp_unslash( $_GET['aifirst_report'] ) );
}
$aifirst_flash_msg = isset( $_GET['aifirst_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['aifirst_msg'] ) ) : '';

if ( in_array( $aifirst_flash_kind, array( 'ok', 'error' ), true ) && '' !== $aifirst_flash_msg ) :
	$aifirst_flash_class = 'ok' === $aifirst_flash_kind ? 'aifirst-flash--ok' : 'aifirst-flash--error';
	?>
	<div class="aifirst-flash <?php echo esc_attr( $aifirst_flash_class ); ?>" role="status">
		<?php echo esc_html( $aifirst_flash_msg ); ?>
	</div>
	<?php
endif;
