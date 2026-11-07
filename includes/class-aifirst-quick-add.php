<?php
/**
 * Quick Add admin page: Paste & Parse + Bulk CSV.
 *
 * @package AIFirst
 * @since   0.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Quick_Add.
 *
 * @since 0.2.0
 */
class AIFirst_Quick_Add {

	/**
	 * Render the page.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( AIFirst_Admin::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aifirstsaas' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'paste';
		if ( ! in_array( $tab, array( 'paste', 'csv' ), true ) ) {
			$tab = 'paste';
		}

		$base_url = admin_url( 'edit.php?post_type=aifirst_tool&page=' . AIFirst_Admin::QUICK_ADD_SLUG );
		$client   = AIFirst_AI_Client::from_settings();
		$has_key  = $client->is_configured();

		$csv_result = $this->maybe_handle_csv_upload();

		?>
		<div class="wrap aifirst-wrap">
			<h1><?php esc_html_e( 'Quick Add — AI First', 'aifirstsaas' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'paste', $base_url ) ); ?>"
					class="nav-tab <?php echo 'paste' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Paste & Parse', 'aifirstsaas' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'csv', $base_url ) ); ?>"
					class="nav-tab <?php echo 'csv' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Bulk CSV import', 'aifirstsaas' ); ?>
				</a>
			</h2>

			<?php
			if ( 'paste' === $tab ) {
				$this->render_paste_tab( $has_key );
			} else {
				$this->render_csv_tab( $csv_result );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render the Paste & Parse tab.
	 *
	 * @since 0.2.0
	 * @param bool $has_key Whether an AI key is configured.
	 * @return void
	 */
	protected function render_paste_tab( $has_key ) {
		?>
		<div id="aifirst-paste" class="aifirst-tab-pane">
			<p class="description">
				<?php esc_html_e( 'Paste raw text about a tool. Optionally parse it with AI into a structured draft, then review and save.', 'aifirstsaas' ); ?>
			</p>

			<?php if ( ! $has_key ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php
						printf(
							/* translators: %s: settings link */
							esc_html__( 'No AI key configured. The Parse button is disabled — fill the form by hand, or add a key on %s.', 'aifirstsaas' ),
							'<a href="' . esc_url( admin_url( 'edit.php?post_type=aifirst_tool&page=' . AIFirst_Admin::SETTINGS_SLUG ) ) . '">' . esc_html__( 'Settings', 'aifirstsaas' ) . '</a>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<div class="aifirst-paste-grid">
				<div class="aifirst-paste-input">
					<h2><?php esc_html_e( '1. Paste', 'aifirstsaas' ); ?></h2>
					<label for="aifirst-raw-text" class="screen-reader-text"><?php esc_html_e( 'Raw text', 'aifirstsaas' ); ?></label>
					<textarea id="aifirst-raw-text" rows="12" class="large-text code" placeholder="<?php esc_attr_e( 'Paste tool description, homepage copy, pricing text…', 'aifirstsaas' ); ?>"></textarea>

					<p>
						<label>
							<?php esc_html_e( 'Website URL (optional)', 'aifirstsaas' ); ?>
							<input type="url" id="aifirst-website-url" class="regular-text" placeholder="https://example.com" />
						</label>
					</p>

					<p>
						<button type="button" class="button button-primary" id="aifirst-parse-btn" <?php disabled( ! $has_key ); ?>>
							<?php esc_html_e( 'Parse with AI', 'aifirstsaas' ); ?>
						</button>
						<span id="aifirst-parse-status" class="aifirst-status"></span>
					</p>
				</div>

				<div class="aifirst-paste-review">
					<h2><?php esc_html_e( '2. Review & Save', 'aifirstsaas' ); ?></h2>

					<form id="aifirst-record-form" autocomplete="off">
						<?php wp_nonce_field( AIFirst_Admin::NONCE_AJAX, 'aifirst_nonce' ); ?>

						<fieldset class="aifirst-section">
							<legend><?php esc_html_e( 'Identity', 'aifirstsaas' ); ?></legend>
							<?php $this->field( 'tool.title', __( 'Title', 'aifirstsaas' ), 'text', array( 'required' => true ) ); ?>
							<?php $this->field( 'tool.tool_id', __( 'Tool ID (slug)', 'aifirstsaas' ), 'text', array( 'help' => __( 'Lowercase, hyphens. Derived from title if blank.', 'aifirstsaas' ) ) ); ?>
							<?php $this->field( 'tool.excerpt', __( 'Excerpt', 'aifirstsaas' ), 'textarea' ); ?>
							<?php $this->field( 'tool.content', __( 'Content (HTML allowed)', 'aifirstsaas' ), 'textarea', array( 'rows' => 6 ) ); ?>
							<?php $this->field( 'tool.categories', __( 'Categories (comma-separated)', 'aifirstsaas' ), 'text' ); ?>
							<?php $this->field( 'tool.ai_features', __( 'AI features (comma-separated)', 'aifirstsaas' ), 'text' ); ?>
							<?php $this->field( 'tool.status', __( 'Status', 'aifirstsaas' ), 'select', array( 'options' => array( 'draft' => __( 'Draft', 'aifirstsaas' ), 'publish' => __( 'Publish', 'aifirstsaas' ) ) ) ); ?>
						</fieldset>

						<fieldset class="aifirst-section">
							<legend><?php esc_html_e( 'Company', 'aifirstsaas' ); ?></legend>
							<?php $this->field( 'meta.company_name', __( 'Company name', 'aifirstsaas' ), 'text' ); ?>
							<?php $this->field( 'meta.website_url', __( 'Website URL', 'aifirstsaas' ), 'url' ); ?>
							<?php $this->field( 'meta.pricing_url', __( 'Pricing URL', 'aifirstsaas' ), 'url' ); ?>
							<?php $this->field( 'meta.headquarters', __( 'Headquarters', 'aifirstsaas' ), 'text' ); ?>
							<?php $this->field( 'meta.founded_year', __( 'Founded year', 'aifirstsaas' ), 'number' ); ?>
							<?php $this->field( 'meta.initials', __( 'Initials', 'aifirstsaas' ), 'text', array( 'maxlength' => 8 ) ); ?>
							<?php $this->field( 'meta.avatar_color', __( 'Avatar color (#RRGGBB)', 'aifirstsaas' ), 'text' ); ?>
							<?php $this->field( 'meta.logo_url', __( 'Logo URL', 'aifirstsaas' ), 'url' ); ?>
							<?php $this->field( 'meta.wp_org_slug', __( 'WordPress.org slug', 'aifirstsaas' ), 'text' ); ?>
							<?php $this->field( 'meta.subcategory', __( 'Subcategory', 'aifirstsaas' ), 'text' ); ?>
						</fieldset>

						<fieldset class="aifirst-section">
							<legend><?php esc_html_e( 'Pricing', 'aifirstsaas' ); ?></legend>
							<?php
							$this->field(
								'meta.pricing_model',
								__( 'Pricing model', 'aifirstsaas' ),
								'select',
								array(
									'options' => array(
										''              => '—',
										'free'          => 'free',
										'freemium'      => 'freemium',
										'paid-only'     => 'paid-only',
										'subscription'  => 'subscription',
										'one-time'      => 'one-time',
										'bundle'        => 'bundle',
										'discontinued'  => 'discontinued',
									),
								)
							);
							$this->field(
								'meta.has_free_plan',
								__( 'Has free plan', 'aifirstsaas' ),
								'select',
								array(
									'options' => array(
										'0' => __( 'No', 'aifirstsaas' ),
										'1' => __( 'Yes', 'aifirstsaas' ),
										'2' => __( 'Add-on', 'aifirstsaas' ),
									),
								)
							);
							$this->field( 'meta.starting_price_usd', __( 'Starting price (USD)', 'aifirstsaas' ), 'number', array( 'step' => '0.01' ) );
							$this->field( 'meta.pricing_note', __( 'Pricing note', 'aifirstsaas' ), 'textarea' );
							?>
						</fieldset>

						<fieldset class="aifirst-section">
							<legend><?php esc_html_e( 'AI', 'aifirstsaas' ); ?></legend>
							<?php
							$this->field(
								'meta.ai_type',
								__( 'AI type', 'aifirstsaas' ),
								'select',
								array(
									'options' => array(
										''            => '—',
										'native'      => 'native',
										'third-party' => 'third-party',
										'both'        => 'both',
									),
								)
							);
							$this->field( 'meta.ai_maturity_score', __( 'AI maturity (1-10)', 'aifirstsaas' ), 'number', array( 'min' => 1, 'max' => 10 ) );
							$this->field( 'meta.ai_since', __( 'AI since (year)', 'aifirstsaas' ), 'number' );
							$this->field( 'meta.ai_on_free_plan', __( 'AI on free plan', 'aifirstsaas' ), 'checkbox' );
							$this->field( 'meta.total_ai_features', __( 'Total AI features', 'aifirstsaas' ), 'number' );
							$this->field( 'meta.native_ai_count', __( 'Native AI count', 'aifirstsaas' ), 'number' );
							$this->field( 'meta.thirdparty_ai_count', __( 'Third-party AI count', 'aifirstsaas' ), 'number' );
							$this->field( 'meta.last_ai_update', __( 'Last AI update (YYYY-MM-DD)', 'aifirstsaas' ), 'text' );
							$this->field( 'meta.last_verified', __( 'Last verified (YYYY-MM-DD)', 'aifirstsaas' ), 'text' );
							?>
						</fieldset>

						<fieldset class="aifirst-section">
							<legend><?php esc_html_e( 'Audience', 'aifirstsaas' ); ?></legend>
							<?php
							$this->field( 'meta.company_size_smb', __( 'SMB', 'aifirstsaas' ), 'checkbox' );
							$this->field( 'meta.company_size_mid', __( 'Mid-market', 'aifirstsaas' ), 'checkbox' );
							$this->field( 'meta.company_size_ent', __( 'Enterprise', 'aifirstsaas' ), 'checkbox' );
							$this->field( 'meta.notable_customers', __( 'Notable customers', 'aifirstsaas' ), 'textarea' );
							$this->field( 'meta.best_for', __( 'Best for', 'aifirstsaas' ), 'textarea' );
							$this->field( 'meta.expert_take', __( 'Expert take', 'aifirstsaas' ), 'textarea' );
							?>
						</fieldset>

						<fieldset class="aifirst-section">
							<legend><?php esc_html_e( 'SEO', 'aifirstsaas' ); ?></legend>
							<?php
							$this->field( 'meta.meta_title', __( 'Meta title', 'aifirstsaas' ), 'text' );
							$this->field( 'meta.meta_description', __( 'Meta description', 'aifirstsaas' ), 'text' );
							$this->field( 'meta.h1', __( 'H1', 'aifirstsaas' ), 'text' );
							?>
						</fieldset>

						<?php $this->render_repeater( 'plans', __( 'Pricing plans', 'aifirstsaas' ), $this->plan_columns() ); ?>
						<?php $this->render_repeater( 'faq', __( 'FAQ', 'aifirstsaas' ), $this->faq_columns() ); ?>
						<?php $this->render_repeater( 'radar', __( 'AI Radar entries', 'aifirstsaas' ), $this->radar_columns() ); ?>
						<?php $this->render_repeater( 'cannot_do', __( 'Cannot-do (limitations)', 'aifirstsaas' ), $this->cannot_columns() ); ?>
						<?php $this->render_repeater( 'relationships', __( 'Relationships', 'aifirstsaas' ), $this->rel_columns() ); ?>

						<p class="aifirst-actions">
							<button type="button" class="button" data-aifirst-save="draft"><?php esc_html_e( 'Save as Draft', 'aifirstsaas' ); ?></button>
							<button type="button" class="button button-primary" data-aifirst-save="publish"><?php esc_html_e( 'Publish', 'aifirstsaas' ); ?></button>
							<span id="aifirst-save-status" class="aifirst-status"></span>
						</p>

						<div id="aifirst-save-result" class="aifirst-result" style="display:none"></div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Columns for the plans repeater.
	 *
	 * @since 0.2.0
	 * @return array
	 */
	protected function plan_columns() {
		return array(
			array( 'key' => 'plan_name', 'label' => __( 'Plan name', 'aifirstsaas' ), 'type' => 'text' ),
			array( 'key' => 'tier_order', 'label' => __( 'Tier', 'aifirstsaas' ), 'type' => 'number' ),
			array( 'key' => 'price_usd_monthly', 'label' => __( 'Monthly', 'aifirstsaas' ), 'type' => 'number', 'step' => '0.01' ),
			array( 'key' => 'price_usd_annual', 'label' => __( 'Annual', 'aifirstsaas' ), 'type' => 'number', 'step' => '0.01' ),
			array(
				'key'     => 'billing_cycle',
				'label'   => __( 'Billing', 'aifirstsaas' ),
				'type'    => 'select',
				'options' => array(
					''         => '—',
					'monthly'  => 'monthly',
					'annual'   => 'annual',
					'one-time' => 'one-time',
					'free'     => 'free',
				),
			),
			array( 'key' => 'user_limit', 'label' => __( 'User limit', 'aifirstsaas' ), 'type' => 'text' ),
			array( 'key' => 'feature_list', 'label' => __( 'Features', 'aifirstsaas' ), 'type' => 'textarea' ),
			array( 'key' => 'notes', 'label' => __( 'Notes', 'aifirstsaas' ), 'type' => 'textarea' ),
			array( 'key' => 'is_free', 'label' => __( 'Free?', 'aifirstsaas' ), 'type' => 'checkbox' ),
			array( 'key' => 'is_most_popular', 'label' => __( 'Popular?', 'aifirstsaas' ), 'type' => 'checkbox' ),
		);
	}

	/**
	 * Columns for the FAQ repeater.
	 *
	 * @since 0.2.0
	 * @return array
	 */
	protected function faq_columns() {
		return array(
			array( 'key' => 'faq_order', 'label' => __( 'Order', 'aifirstsaas' ), 'type' => 'number' ),
			array( 'key' => 'question', 'label' => __( 'Question', 'aifirstsaas' ), 'type' => 'text' ),
			array( 'key' => 'answer', 'label' => __( 'Answer', 'aifirstsaas' ), 'type' => 'textarea' ),
			array( 'key' => 'category_id', 'label' => __( 'Category ID', 'aifirstsaas' ), 'type' => 'text' ),
		);
	}

	/**
	 * Columns for the radar repeater.
	 *
	 * @since 0.2.0
	 * @return array
	 */
	protected function radar_columns() {
		return array(
			array( 'key' => 'change_date', 'label' => __( 'Date', 'aifirstsaas' ), 'type' => 'text' ),
			array(
				'key'     => 'change_type',
				'label'   => __( 'Type', 'aifirstsaas' ),
				'type'    => 'select',
				'options' => array(
					''               => '—',
					'launch'         => 'launch',
					'new-feature'    => 'new-feature',
					'upgraded'       => 'upgraded',
					'deprecated'     => 'deprecated',
					'pricing-change' => 'pricing-change',
				),
			),
			array( 'key' => 'what_changed', 'label' => __( 'What changed', 'aifirstsaas' ), 'type' => 'textarea' ),
			array( 'key' => 'why_it_matters', 'label' => __( 'Why it matters', 'aifirstsaas' ), 'type' => 'textarea' ),
			array( 'key' => 'source_url', 'label' => __( 'Source URL', 'aifirstsaas' ), 'type' => 'url' ),
			array( 'key' => 'version_label', 'label' => __( 'Version', 'aifirstsaas' ), 'type' => 'text' ),
			array(
				'key'     => 'confidence',
				'label'   => __( 'Confidence', 'aifirstsaas' ),
				'type'    => 'select',
				'options' => array( 'low' => 'low', 'medium' => 'medium', 'high' => 'high' ),
			),
			array(
				'key'     => 'row_status',
				'label'   => __( 'Status', 'aifirstsaas' ),
				'type'    => 'select',
				'options' => array( 'draft' => 'draft', 'verified' => 'verified', 'published' => 'published' ),
			),
		);
	}

	/**
	 * Columns for the cannot-do repeater.
	 *
	 * @since 0.2.0
	 * @return array
	 */
	protected function cannot_columns() {
		return array(
			array( 'key' => 'order_index', 'label' => __( 'Order', 'aifirstsaas' ), 'type' => 'number' ),
			array( 'key' => 'limitation_text', 'label' => __( 'Limitation', 'aifirstsaas' ), 'type' => 'textarea' ),
		);
	}

	/**
	 * Columns for the relationships repeater.
	 *
	 * @since 0.2.0
	 * @return array
	 */
	protected function rel_columns() {
		return array(
			array( 'key' => 'target_tool_id', 'label' => __( 'Target tool_id', 'aifirstsaas' ), 'type' => 'text' ),
			array(
				'key'     => 'relationship_type',
				'label'   => __( 'Type', 'aifirstsaas' ),
				'type'    => 'select',
				'options' => array(
					''            => '—',
					'alternative' => 'alternative',
					'complement'  => 'complement',
					'competitor'  => 'competitor',
					'integration' => 'integration',
				),
			),
			array( 'key' => 'notes', 'label' => __( 'Notes', 'aifirstsaas' ), 'type' => 'textarea' ),
		);
	}

	/**
	 * Render a single field with a data-aifirst-field attribute.
	 *
	 * @since 0.2.0
	 * @param string $path  Dot path (e.g. tool.title).
	 * @param string $label Label.
	 * @param string $type  Input type.
	 * @param array  $args  Extras (options, help, required, step, min, max, rows, maxlength).
	 * @return void
	 */
	protected function field( $path, $label, $type, $args = array() ) {
		$id       = 'aifirst-f-' . sanitize_html_class( str_replace( '.', '-', $path ) );
		$required = ! empty( $args['required'] );
		$help     = isset( $args['help'] ) ? (string) $args['help'] : '';
		?>
		<p class="aifirst-field aifirst-field-<?php echo esc_attr( $type ); ?>">
			<label for="<?php echo esc_attr( $id ); ?>">
				<span class="aifirst-label"><?php echo esc_html( $label ); ?><?php echo $required ? ' *' : ''; ?></span>
				<?php
				switch ( $type ) {
					case 'textarea':
						$rows = isset( $args['rows'] ) ? (int) $args['rows'] : 3;
						printf(
							'<textarea id="%s" data-aifirst-field="%s" rows="%d" class="large-text"></textarea>',
							esc_attr( $id ),
							esc_attr( $path ),
							esc_attr( (string) $rows )
						);
						break;

					case 'select':
						$options = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();
						printf(
							'<select id="%s" data-aifirst-field="%s">',
							esc_attr( $id ),
							esc_attr( $path )
						);
						foreach ( $options as $value => $display ) {
							printf(
								'<option value="%s">%s</option>',
								esc_attr( (string) $value ),
								esc_html( (string) $display )
							);
						}
						echo '</select>';
						break;

					case 'checkbox':
						printf(
							'<input id="%s" data-aifirst-field="%s" type="checkbox" value="1" />',
							esc_attr( $id ),
							esc_attr( $path )
						);
						break;

					default:
						$extra = '';
						foreach ( array( 'step', 'min', 'max', 'maxlength' ) as $attr ) {
							if ( isset( $args[ $attr ] ) ) {
								$extra .= ' ' . $attr . '="' . esc_attr( (string) $args[ $attr ] ) . '"';
							}
						}
						// $extra values are escaped individually above.
						printf(
							'<input id="%s" data-aifirst-field="%s" type="%s" class="regular-text"%s />',
							esc_attr( $id ),
							esc_attr( $path ),
							esc_attr( $type ),
							$extra // phpcs:ignore WordPress.Security.EscapeOutput
						);
				}
				?>
			</label>
			<?php if ( '' !== $help ) : ?>
				<span class="description"><?php echo esc_html( $help ); ?></span>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Render a repeater block. JS clones .aifirst-row-template rows into .aifirst-rows.
	 *
	 * @since 0.2.0
	 * @param string $group   Group key (plans, faq, …).
	 * @param string $label   Legend text.
	 * @param array  $columns Column specs.
	 * @return void
	 */
	protected function render_repeater( $group, $label, $columns ) {
		?>
		<fieldset class="aifirst-section aifirst-repeater" data-aifirst-repeater="<?php echo esc_attr( $group ); ?>">
			<legend><?php echo esc_html( $label ); ?></legend>
			<div class="aifirst-rows"></div>

			<template class="aifirst-row-template">
				<div class="aifirst-row">
					<?php $this->render_repeater_row( $columns ); ?>
					<p><button type="button" class="button-link aifirst-remove-row"><?php esc_html_e( 'Remove', 'aifirstsaas' ); ?></button></p>
				</div>
			</template>

			<p>
				<button type="button" class="button aifirst-add-row"><?php esc_html_e( 'Add row', 'aifirstsaas' ); ?></button>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Row template contents for a repeater.
	 *
	 * @since 0.2.0
	 * @param array $columns Column specs.
	 * @return void
	 */
	protected function render_repeater_row( $columns ) {
		foreach ( $columns as $col ) {
			$key  = (string) $col['key'];
			$type = (string) $col['type'];
			?>
			<label class="aifirst-row-field">
				<span class="aifirst-label"><?php echo esc_html( (string) $col['label'] ); ?></span>
				<?php
				switch ( $type ) {
					case 'textarea':
						printf( '<textarea data-aifirst-col="%s" rows="2"></textarea>', esc_attr( $key ) );
						break;
					case 'select':
						$options = isset( $col['options'] ) ? $col['options'] : array();
						printf( '<select data-aifirst-col="%s">', esc_attr( $key ) );
						foreach ( $options as $value => $display ) {
							printf( '<option value="%s">%s</option>', esc_attr( (string) $value ), esc_html( (string) $display ) );
						}
						echo '</select>';
						break;
					case 'checkbox':
						printf( '<input data-aifirst-col="%s" type="checkbox" value="1" />', esc_attr( $key ) );
						break;
					default:
						$step = isset( $col['step'] ) ? ' step="' . esc_attr( (string) $col['step'] ) . '"' : '';
						printf(
							'<input data-aifirst-col="%s" type="%s"%s />',
							esc_attr( $key ),
							esc_attr( $type ),
							$step // phpcs:ignore WordPress.Security.EscapeOutput
						);
				}
				?>
			</label>
			<?php
		}
	}

	/**
	 * Handle a CSV POST, if one arrived.
	 *
	 * @since 0.2.0
	 * @return array|null
	 */
	protected function maybe_handle_csv_upload() {
		if ( empty( $_POST['aifirst_csv_submit'] ) ) {
			return null;
		}

		check_admin_referer( 'aifirst_csv_import', 'aifirst_csv_nonce' );

		if ( ! current_user_can( AIFirst_Admin::CAP ) ) {
			return array(
				'results' => array(),
				'errors'  => array( __( 'Permission denied.', 'aifirstsaas' ) ),
			);
		}

		if ( empty( $_FILES['aifirst_csv_file']['tmp_name'] ) ) {
			return array(
				'results' => array(),
				'errors'  => array( __( 'No file uploaded.', 'aifirstsaas' ) ),
			);
		}

		$tmp = $_FILES['aifirst_csv_file']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! is_uploaded_file( $tmp ) ) {
			return array(
				'results' => array(),
				'errors'  => array( __( 'Invalid upload.', 'aifirstsaas' ) ),
			);
		}

		$importer = new AIFirst_CSV_Importer();
		return $importer->import_file( $tmp );
	}

	/**
	 * Render the CSV tab.
	 *
	 * @since 0.2.0
	 * @param array|null $csv_result Previous import result, if any.
	 * @return void
	 */
	protected function render_csv_tab( $csv_result ) {
		?>
		<div id="aifirst-csv" class="aifirst-tab-pane">
			<p class="description">
				<?php esc_html_e( 'Upload a CSV. One row per tool. See the expected header and sub-row format in NOTES.md.', 'aifirstsaas' ); ?>
			</p>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'aifirst_csv_import', 'aifirst_csv_nonce' ); ?>
				<input type="hidden" name="aifirst_csv_submit" value="1" />

				<p>
					<label for="aifirst-csv-file"><strong><?php esc_html_e( 'CSV file', 'aifirstsaas' ); ?></strong></label><br />
					<input type="file" name="aifirst_csv_file" id="aifirst-csv-file" accept=".csv,text/csv" required />
				</p>

				<?php submit_button( __( 'Import CSV', 'aifirstsaas' ) ); ?>
			</form>

			<?php if ( is_array( $csv_result ) ) : ?>
				<h3><?php esc_html_e( 'Import results', 'aifirstsaas' ); ?></h3>

				<?php if ( ! empty( $csv_result['errors'] ) ) : ?>
					<div class="notice notice-error inline">
						<?php foreach ( $csv_result['errors'] as $err ) : ?>
							<p><?php echo esc_html( (string) $err ); ?></p>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $csv_result['results'] ) ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Row', 'aifirstsaas' ); ?></th>
								<th><?php esc_html_e( 'Tool', 'aifirstsaas' ); ?></th>
								<th><?php esc_html_e( 'Result', 'aifirstsaas' ); ?></th>
								<th><?php esc_html_e( 'Notes', 'aifirstsaas' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $csv_result['results'] as $row_num => $row ) : ?>
								<tr>
									<td><?php echo esc_html( (string) $row_num ); ?></td>
									<td>
										<?php
										if ( ! empty( $row['post_id'] ) ) {
											$edit_url = get_edit_post_link( (int) $row['post_id'] );
											printf(
												'<a href="%s">%s</a>',
												esc_url( $edit_url ),
												esc_html( (string) $row['tool_id'] )
											);
										} else {
											echo esc_html( (string) $row['tool_id'] );
										}
										?>
									</td>
									<td><?php echo esc_html( (string) $row['status'] ); ?></td>
									<td><?php echo esc_html( implode( '; ', (array) $row['messages'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
