<?php
/**
 * Settings page — AI provider + API key.
 *
 * @package AIFirst
 * @since   0.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Settings.
 *
 * @since 0.2.0
 */
class AIFirst_Settings {

	const OPTION_NAME  = 'aifirst_settings';
	const OPTION_GROUP = 'aifirst_settings_group';
	const KEY_PRESERVE = '__AIFIRST_KEEP__';

	/**
	 * Register the setting + fields.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function register() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(
					'provider' => 'anthropic',
					'model'    => '',
					'api_key'  => '',
				),
			)
		);
	}

	/**
	 * Sanitize the aifirst_settings option.
	 *
	 * @since 0.2.0
	 * @param array $input Raw.
	 * @return array
	 */
	public function sanitize( $input ) {
		$existing = get_option( self::OPTION_NAME, array() );
		$out      = array(
			'provider' => 'anthropic',
			'model'    => '',
			'api_key'  => isset( $existing['api_key'] ) ? (string) $existing['api_key'] : '',
		);

		if ( ! is_array( $input ) ) {
			return $out;
		}

		$provider = isset( $input['provider'] ) ? sanitize_key( (string) $input['provider'] ) : 'anthropic';
		if ( ! in_array( $provider, array( 'anthropic', 'openai' ), true ) ) {
			$provider = 'anthropic';
		}
		$out['provider'] = $provider;

		$out['model'] = isset( $input['model'] ) ? sanitize_text_field( (string) $input['model'] ) : '';

		$key_in = isset( $input['api_key'] ) ? (string) $input['api_key'] : '';
		$key_in = trim( $key_in );
		if ( self::KEY_PRESERVE === $key_in ) {
			// Unchanged submission marker — keep what's already stored.
			$out['api_key'] = isset( $existing['api_key'] ) ? (string) $existing['api_key'] : '';
		} else {
			$out['api_key'] = sanitize_text_field( $key_in );
		}

		return $out;
	}

	/**
	 * Return the effective key source: 'constant', 'option', or 'none'.
	 *
	 * @since 0.2.0
	 * @return array{source:string, last4:string}
	 */
	public static function key_status() {
		if ( defined( 'AIFIRST_AI_API_KEY' ) && '' !== (string) AIFIRST_AI_API_KEY ) {
			$k = (string) AIFIRST_AI_API_KEY;
			return array(
				'source' => 'constant',
				'last4'  => substr( $k, -4 ),
			);
		}
		$opt = get_option( self::OPTION_NAME, array() );
		$k   = isset( $opt['api_key'] ) ? (string) $opt['api_key'] : '';
		if ( '' === $k ) {
			return array(
				'source' => 'none',
				'last4'  => '',
			);
		}
		return array(
			'source' => 'option',
			'last4'  => substr( $k, -4 ),
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( AIFirst_Admin::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aifirstsaas' ) );
		}

		$opt      = get_option( self::OPTION_NAME, array() );
		$provider = isset( $opt['provider'] ) ? (string) $opt['provider'] : 'anthropic';
		$model    = isset( $opt['model'] ) ? (string) $opt['model'] : '';
		$status   = self::key_status();
		?>
		<div class="wrap aifirst-wrap">
			<h1><?php esc_html_e( 'AI First Settings', 'aifirstsaas' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row">
							<label for="aifirst-provider"><?php esc_html_e( 'Provider', 'aifirstsaas' ); ?></label>
						</th>
						<td>
							<select id="aifirst-provider" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[provider]">
								<option value="anthropic" <?php selected( $provider, 'anthropic' ); ?>>Anthropic</option>
								<option value="openai" <?php selected( $provider, 'openai' ); ?>>OpenAI</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="aifirst-model"><?php esc_html_e( 'Model', 'aifirstsaas' ); ?></label>
						</th>
						<td>
							<input id="aifirst-model" type="text" class="regular-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[model]"
								value="<?php echo esc_attr( $model ); ?>"
								placeholder="e.g. claude-sonnet-5-5 or gpt-4o-mini" />
							<p class="description">
								<?php esc_html_e( 'Leave blank to use a reasonable default per provider.', 'aifirstsaas' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="aifirst-api-key"><?php esc_html_e( 'API key', 'aifirstsaas' ); ?></label>
						</th>
						<td>
							<?php if ( 'constant' === $status['source'] ) : ?>
								<p>
									<strong><?php esc_html_e( 'Set via wp-config.', 'aifirstsaas' ); ?></strong>
									<?php
									printf(
										/* translators: %s: masked key */
										esc_html__( 'Using %s', 'aifirstsaas' ),
										'<code>••••' . esc_html( $status['last4'] ) . '</code>'
									);
									?>
								</p>
								<p class="description">
									<?php esc_html_e( 'The AIFIRST_AI_API_KEY constant overrides this field.', 'aifirstsaas' ); ?>
								</p>
							<?php else : ?>
								<input id="aifirst-api-key" type="password" class="regular-text"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[api_key]"
									value="<?php echo 'option' === $status['source'] ? esc_attr( self::KEY_PRESERVE ) : ''; ?>"
									autocomplete="new-password" />
								<?php if ( 'option' === $status['source'] ) : ?>
									<p class="description">
										<?php
										printf(
											/* translators: %s: masked key */
											esc_html__( 'Current key ends in %s. Leave this field unchanged to keep it, or paste a new key to replace it.', 'aifirstsaas' ),
											'<code>••••' . esc_html( $status['last4'] ) . '</code>'
										);
										?>
									</p>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Test connection', 'aifirstsaas' ); ?></th>
						<td>
							<button type="button" id="aifirst-test-connection" class="button">
								<?php esc_html_e( 'Run test', 'aifirstsaas' ); ?>
							</button>
							<span id="aifirst-test-result" class="aifirst-test-result"></span>
							<p class="description">
								<?php esc_html_e( 'Saves nothing. Sends one minimal call to verify the key works.', 'aifirstsaas' ); ?>
							</p>
						</td>
					</tr>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
