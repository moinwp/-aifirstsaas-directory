<?php
/**
 * My Stack page (/stack/ or /stack/<slug>+<slug>+…/).
 *
 * @package AIFirst
 * @since   0.6.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$resolved_req = AIFirst_Query::stack_slugs_from_request();
$slugs        = $resolved_req['slugs'];
$source       = $resolved_req['source'];
$is_url_mode  = ( 'url' === $source );

$rows = AIFirst_Query::stack_resolve_tool_ids( $slugs );

$empty = empty( $rows );

if ( ! $empty ) {
	$aggregate = AIFirst_Query::stack_aggregate_features( $rows );
	$missing   = AIFirst_Query::stack_missing_features( $rows, 10 );
	$gaps      = AIFirst_Query::stack_category_gaps( $rows, 10 );
}

$share_url = home_url( '/stack/' . rawurlencode( implode( '+', array_map( static function ( $r ) { return $r->tool_id; }, array_values( $rows ) ) ) ) . '/' );
?>

<div class="aifirst-shell">
	<div class="aifirst-container">
		<?php require AIFIRST_TEMPLATES_DIR . 'public/partials/flash.php'; ?>

		<?php
		AIFirst_Components::breadcrumbs(
			array(
				array( 'label' => __( 'Home', 'aifirstsaas' ), 'url' => home_url( '/' ) ),
				array( 'label' => __( 'My Stack', 'aifirstsaas' ) ),
			)
		);
		?>

		<header style="display:flex; align-items:baseline; justify-content:space-between; flex-wrap:wrap; gap:10px;">
			<h1 class="aifirst-h1"><?php esc_html_e( 'My Stack', 'aifirstsaas' ); ?></h1>
			<?php if ( $is_url_mode && ! $empty ) :
				$aifirst_save_cfg = array(
					'slugs'       => array_values( array_map( static function ( $r ) { return $r->tool_id; }, $rows ) ),
					'successText' => __( 'Saved to your stack.', 'aifirstsaas' ),
					'fullText'    => __( 'Saved what fit — stack is full at 50 tools.', 'aifirstsaas' ),
				);
				?>
				<button type="button" class="aifirst-btn"
					x-data="AIFirstSaveStack(<?php echo esc_attr( wp_json_encode( $aifirst_save_cfg ) ); ?>)"
					@click="save()">
					<?php esc_html_e( 'Save this stack', 'aifirstsaas' ); ?>
				</button>
			<?php endif; ?>
		</header>

		<p class="aifirst-lead">
			<?php
			if ( $is_url_mode ) {
				echo esc_html__( 'You are viewing a shared stack. Save it to this browser, or build your own from any tool page.', 'aifirstsaas' );
			} elseif ( $empty ) {
				echo esc_html__( 'Add tools to your stack from any tool page to see what AI features you already cover and where the gaps are.', 'aifirstsaas' );
			} else {
				echo esc_html( sprintf( /* translators: %d: count */ _n( '%d tool in your stack.', '%d tools in your stack.', count( $rows ), 'aifirstsaas' ), count( $rows ) ) );
			}
			?>
		</p>

		<!-- Section 1 — Your stack -->
		<section style="margin-top:28px;">
			<h2 class="aifirst-h2"><?php esc_html_e( 'Your stack', 'aifirstsaas' ); ?></h2>

			<?php if ( $empty ) : ?>
				<div class="aifirst-card">
					<p style="color:var(--aif-sub); margin:0 0 12px;"><?php esc_html_e( 'Your stack is empty. Add tools from any tool page.', 'aifirstsaas' ); ?></p>
					<?php
					$popular = AIFirst_Query::leaderboard( array( 'limit' => 5 ) );
					if ( ! empty( $popular ) ) :
						?>
						<p style="font-size:12.5px; color:var(--aif-sub); margin:0 0 8px;"><?php esc_html_e( 'Popular picks to get you started:', 'aifirstsaas' ); ?></p>
						<div style="display:flex; flex-direction:column; gap:10px;">
							<?php foreach ( $popular as $p ) :
								$post = get_post( (int) $p->post_id );
								if ( ! $post ) { continue; }
								AIFirst_Components::tool_card( $post, $p );
							endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div style="display:flex; flex-direction:column; gap:10px;">
					<?php foreach ( $rows as $post_id => $meta ) :
						$post = get_post( (int) $post_id );
						if ( ! $post ) { continue; }
						AIFirst_Components::tool_card( $post, $meta );
					endforeach; ?>
				</div>

				<?php
				$aifirst_share_cfg = array(
					'shareUrl'    => esc_url_raw( $share_url ),
					'successText' => __( 'Share URL copied to clipboard.', 'aifirstsaas' ),
					'promptText'  => __( 'Copy this URL:', 'aifirstsaas' ),
				);
				?>
				<div style="display:flex; gap:10px; margin-top:14px; flex-wrap:wrap;"
					x-data="AIFirstShareStack(<?php echo esc_attr( wp_json_encode( $aifirst_share_cfg ) ); ?>)">
					<button type="button" class="aifirst-btn aifirst-btn--primary" @click="share()">
						<span x-show="!copied"><?php esc_html_e( 'Share this stack', 'aifirstsaas' ); ?></span>
						<span x-show="copied" x-cloak>✓ <?php esc_html_e( 'Copied', 'aifirstsaas' ); ?></span>
					</button>
					<?php if ( ! $is_url_mode ) : ?>
						<button type="button" class="aifirst-btn"
							@click="if (confirm(<?php echo esc_attr( wp_json_encode( esc_html__( 'Clear your stack?', 'aifirstsaas' ) ) ); ?>)) { window.AIFirstStack.clear(); $store.aifStack.slugs.splice(0, $store.aifStack.slugs.length); $store.aifStack.count = 0; window.location.reload(); }">
							<?php esc_html_e( 'Clear stack', 'aifirstsaas' ); ?>
						</button>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</section>

		<?php if ( ! $empty ) : ?>

		<!-- Section 2 — What you're already paying for -->
		<section style="margin-top:36px;">
			<h2 class="aifirst-h2"><?php esc_html_e( "What you're already paying for", 'aifirstsaas' ); ?></h2>
			<div class="aifirst-stat-strip">
				<span>
					<?php echo esc_html( number_format_i18n( $aggregate['total_unique'] ) ); ?>
					<?php esc_html_e( 'unique AI features', 'aifirstsaas' ); ?>
				</span>
				<span class="aifirst-stat-strip__sep">·</span>
				<span>
					<?php echo esc_html( number_format_i18n( $aggregate['native'] ) ); ?>
					<?php esc_html_e( 'native', 'aifirstsaas' ); ?>
				</span>
				<span class="aifirst-stat-strip__sep">·</span>
				<span>
					<?php echo esc_html( number_format_i18n( $aggregate['third_party'] ) ); ?>
					<?php esc_html_e( 'third-party', 'aifirstsaas' ); ?>
				</span>
				<span class="aifirst-stat-strip__sep">·</span>
				<span>
					<?php echo esc_html( number_format_i18n( $aggregate['tools_with_free_ai'] ) ); ?>
					<?php esc_html_e( 'tools with free AI', 'aifirstsaas' ); ?>
				</span>
				<?php if ( $aggregate['starting_price_sum'] > 0 ) : ?>
					<span class="aifirst-stat-strip__sep">·</span>
					<span>
						<?php esc_html_e( 'Starting at', 'aifirstsaas' ); ?>
						$<?php echo esc_html( number_format_i18n( $aggregate['starting_price_sum'], 2 ) ); ?>
						<?php esc_html_e( '/mo (sum of starting prices)', 'aifirstsaas' ); ?>
					</span>
				<?php endif; ?>
			</div>
		</section>

		<!-- Section 3 — Missing features -->
		<section style="margin-top:36px;">
			<h2 class="aifirst-h2"><?php esc_html_e( 'AI features you might be missing', 'aifirstsaas' ); ?></h2>
			<?php if ( empty( $missing ) ) : ?>
				<p class="aifirst-lead"><?php esc_html_e( 'Nothing obvious is missing — your stack covers the common features for its categories.', 'aifirstsaas' ); ?></p>
			<?php else : ?>
				<div style="display:flex; flex-direction:column; gap:10px;">
					<?php foreach ( $missing as $m ) : ?>
						<div class="aifirst-card" style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;">
							<div>
								<div style="font-weight:700;"><?php echo esc_html( $m->feature_name ); ?></div>
								<div style="font-size:12.5px; color:var(--aif-sub);">
									<?php echo esc_html( sprintf( /* translators: %d: tool count */ _n( 'Found in %d tool', 'Found in %d tools', (int) $m->tool_count, 'aifirstsaas' ), (int) $m->tool_count ) ); ?>
								</div>
							</div>
							<?php if ( ! empty( $m->top_tools ) ) : ?>
								<div style="font-size:12.5px;">
									<?php esc_html_e( 'Try:', 'aifirstsaas' ); ?>
									<?php
									$links = array();
									foreach ( $m->top_tools as $t ) {
										$links[] = sprintf(
											'<a href="%s">%s</a>',
											esc_url( $t->permalink ),
											esc_html( $t->name )
										);
									}
									echo wp_kses( implode( ', ', $links ), array( 'a' => array( 'href' => array() ) ) );
									?>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>

		<!-- Section 4 — Category gaps -->
		<section style="margin:36px 0;">
			<h2 class="aifirst-h2"><?php esc_html_e( 'Gaps in your stack', 'aifirstsaas' ); ?></h2>
			<?php if ( empty( $gaps ) ) : ?>
				<p class="aifirst-lead"><?php esc_html_e( 'Your stack already touches every category we track.', 'aifirstsaas' ); ?></p>
			<?php else : ?>
				<div class="aifirst-grid-3">
					<?php foreach ( $gaps as $g ) : ?>
						<div class="aifirst-card">
							<div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:8px;">
								<a style="font-weight:700;" href="<?php echo esc_url( get_term_link( $g->term ) ); ?>"><?php echo esc_html( $g->term->name ); ?></a>
								<span style="font-size:12px; color:var(--aif-sub);"><?php echo esc_html( sprintf( /* translators: %d: count */ _n( '%d tool', '%d tools', (int) $g->tool_count, 'aifirstsaas' ), (int) $g->tool_count ) ); ?></span>
							</div>
							<?php foreach ( $g->top_tools as $tt ) : ?>
								<div style="display:flex; justify-content:space-between; font-size:12.5px; padding:4px 0;">
									<a href="<?php echo esc_url( get_permalink( (int) $tt->post_id ) ); ?>"><?php echo esc_html( get_the_title( (int) $tt->post_id ) ); ?></a>
									<span style="color:var(--aif-sub);"><?php echo esc_html( (int) $tt->ai_maturity_score ); ?>/10</span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>

		<?php endif; // ! $empty ?>

	</div>
</div>

<?php
get_footer();
