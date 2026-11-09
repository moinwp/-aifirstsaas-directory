<?php
/**
 * Single tool page.
 *
 * @package AIFirst
 * @since   0.4.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

the_post();
$post_obj = get_post();
$tool_id  = get_post_meta( $post_obj->ID, AIFirst_Intake::TOOL_ID_META_KEY, true );
if ( '' === $tool_id ) {
	$tool_id = $post_obj->post_name;
}

$meta = AIFirst_Query::tool_meta( $post_obj->ID );
if ( ! $meta ) {
	echo '<div class="aifirst-shell"><div class="aifirst-container">';
	echo '<h1>' . esc_html( get_the_title() ) . '</h1>';
	echo '<p>' . esc_html__( 'This tool has no structured metadata yet.', 'aifirstsaas' ) . '</p>';
	echo '</div></div>';
	get_footer();
	return;
}

$features = AIFirst_Query::tool_features( $tool_id );
$plans    = AIFirst_Query::plans( $tool_id );
$maturity = AIFirst_Query::maturity_breakdown( $tool_id );
$cannot   = AIFirst_Query::cannot_do( $tool_id );
$inds     = AIFirst_Query::industries( $tool_id );
$senti    = AIFirst_Query::sentiment( $tool_id );
$quotes   = AIFirst_Query::quotes( $tool_id );
$radar    = AIFirst_Query::radar_for_tool( $tool_id );
$rels     = AIFirst_Query::relationships( $tool_id );
$same_co  = AIFirst_Query::same_company( $tool_id, (string) $meta->company_group_id );
$faq      = AIFirst_Query::faq( array( 'tool_id' => $tool_id ) );

$cats = get_the_terms( $post_obj->ID, 'aifirst_category' );
$cat  = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0] : null;
?>

<div class="aifirst-shell">
	<div class="aifirst-container">
		<?php require AIFIRST_TEMPLATES_DIR . 'public/partials/flash.php'; ?>

		<?php
		// Build breadcrumb: Home / Tools / <first category (link)> / <tool title>.
		// On a single, no queried-term "preference" exists — just use the
		// tool's first category by term order (same as the hero pill).
		// @since 0.4.1
		$aifirst_crumbs = array(
			array(
				'label' => __( 'Home', 'aifirstsaas' ),
				'url'   => home_url( '/' ),
			),
			array(
				'label' => __( 'Tools', 'aifirstsaas' ),
				'url'   => get_post_type_archive_link( 'aifirst_tool' ),
			),
		);
		if ( $cat instanceof WP_Term ) {
			$aifirst_crumbs[] = array(
				'label' => $cat->name,
				'url'   => get_term_link( $cat ),
			);
		}
		$aifirst_crumbs[] = array(
			'label' => get_the_title( $post_obj ),
		);
		AIFirst_Components::breadcrumbs( $aifirst_crumbs );
		?>

		<header style="display:flex; align-items:flex-start; gap:18px; flex-wrap:wrap; margin: 6px 0 18px;">
			<?php AIFirst_Components::avatar( $post_obj->ID, $meta->initials, $meta->avatar_color, 64 ); ?>
			<div style="flex:1; min-width:0;">
				<h1 class="aifirst-h1" style="margin-bottom:6px;"><?php echo esc_html( get_the_title() ); ?></h1>
				<div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
					<?php AIFirst_Components::category_pill( $cat ); ?>
					<?php if ( '' !== (string) $meta->subcategory ) : ?>
						<span class="aifirst-pill"><?php echo esc_html( $meta->subcategory ); ?></span>
					<?php endif; ?>
					<span style="font-weight:700;"><?php echo esc_html( (int) $meta->ai_maturity_score ); ?>/10</span>
					<?php AIFirst_Components::maturity_bars( (int) $meta->ai_maturity_score ); ?>
				</div>
			</div>
			<div style="display:flex; gap:8px; flex-wrap:wrap;">
				<?php if ( '' !== (string) $meta->website_url ) : ?>
					<a href="<?php echo esc_url( $meta->website_url ); ?>" target="_blank" rel="noopener" class="aifirst-btn aifirst-btn--primary">
						<?php esc_html_e( 'Visit Website ↗', 'aifirstsaas' ); ?>
					</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( home_url( '/compare/' . rawurlencode( $tool_id ) . '/' ) ); ?>" class="aifirst-btn">
					<?php esc_html_e( '+ Compare', 'aifirstsaas' ); ?>
				</a>
			</div>
		</header>

		<div style="display:flex; align-items:center; gap:14px; margin-bottom:18px; color:var(--aif-sub); font-size:12.5px;">
			<?php if ( $meta->last_verified ) : ?>
				<span><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Last verified: %s', 'aifirstsaas' ), AIFirst_Components::date_display( $meta->last_verified ) ) ); ?></span>
			<?php endif; ?>
			<?php AIFirst_Components::report_modal_trigger( $tool_id ); ?>
		</div>

		<div class="aifirst-stat-strip">
			<span><?php echo esc_html( count( $features ) ); ?> <?php esc_html_e( 'AI Features', 'aifirstsaas' ); ?></span>
			<span class="aifirst-stat-strip__sep">·</span>
			<span><?php echo esc_html( (int) $meta->native_ai_count ); ?> <?php esc_html_e( 'Native', 'aifirstsaas' ); ?></span>
			<span class="aifirst-stat-strip__sep">·</span>
			<span><?php echo esc_html( (int) $meta->thirdparty_ai_count ); ?> <?php esc_html_e( 'Third-party', 'aifirstsaas' ); ?></span>
			<span class="aifirst-stat-strip__sep">·</span>
			<?php AIFirst_Components::free_ai_badge( (int) $meta->ai_on_free_plan ); ?>
			<?php if ( $meta->ai_since ) : ?>
				<span class="aifirst-stat-strip__sep">·</span>
				<span><?php echo esc_html( sprintf( /* translators: %d: year */ __( 'AI since %d', 'aifirstsaas' ), (int) $meta->ai_since ) ); ?></span>
			<?php endif; ?>
		</div>

		<div class="aifirst-twocol">
			<main>
				<?php if ( $cat ) :
					$cat_stats = AIFirst_Query::category_stats( $cat->term_id ); ?>
					<section class="aifirst-card" style="margin-bottom:20px;">
						<strong><?php echo esc_html( sprintf( /* translators: 1: tool 2: category */ __( 'How %1$s compares to %2$s average', 'aifirstsaas' ), get_the_title(), $cat->name ) ); ?></strong>
						<table class="aifirst-table" style="margin-top:8px;">
							<tbody>
								<tr>
									<td><?php esc_html_e( 'AI maturity', 'aifirstsaas' ); ?></td>
									<td><?php echo esc_html( (int) $meta->ai_maturity_score ); ?>/10</td>
									<td><?php echo esc_html( sprintf( /* translators: %s: value */ __( 'vs avg %s', 'aifirstsaas' ), $cat_stats['avg_maturity'] ) ); ?></td>
								</tr>
								<tr>
									<td><?php esc_html_e( 'Free AI', 'aifirstsaas' ); ?></td>
									<td><?php AIFirst_Components::free_ai_badge( (int) $meta->ai_on_free_plan ); ?></td>
									<td><?php echo esc_html( sprintf( /* translators: %s: value */ __( 'cat avg %s%%', 'aifirstsaas' ), $cat_stats['free_pct'] ) ); ?></td>
								</tr>
								<tr>
									<td><?php esc_html_e( 'Total features', 'aifirstsaas' ); ?></td>
									<td colspan="2"><?php echo esc_html( count( $features ) ); ?></td>
								</tr>
							</tbody>
						</table>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $maturity ) ) : ?>
					<section class="aifirst-card" style="margin-bottom:20px;">
						<strong><?php esc_html_e( 'AI Maturity breakdown', 'aifirstsaas' ); ?></strong>
						<?php
						foreach ( $maturity as $row ) :
							// Respect the stored score_color (green|amber|red), default to amber.
							// @since 0.4.1
							$aifirst_sc = strtolower( (string) $row->score_color );
							if ( ! in_array( $aifirst_sc, array( 'green', 'amber', 'red' ), true ) ) {
								$aifirst_sc = 'amber';
							}
							?>
							<div style="display:flex; justify-content:space-between; padding:6px 0; font-size:13px;">
								<span><?php echo esc_html( $row->criterion ); ?></span>
								<span class="aifirst-score--<?php echo esc_attr( $aifirst_sc ); ?>"><?php echo esc_html( $row->score_display ); ?></span>
							</div>
						<?php endforeach; ?>
						<div style="display:flex; justify-content:space-between; padding:8px 0 0; border-top:1px solid var(--aif-border); margin-top:6px; font-weight:700;">
							<span><?php esc_html_e( 'Total', 'aifirstsaas' ); ?></span>
							<span><?php echo esc_html( (int) $meta->ai_maturity_score ); ?>/10</span>
						</div>
					</section>
				<?php endif; ?>

				<div x-data="{ tab: 'features' }">
					<nav class="aifirst-tabs">
						<button class="aifirst-tab" :class="tab === 'features' && 'is-active'" @click="tab = 'features'"><?php esc_html_e( 'AI Features', 'aifirstsaas' ); ?></button>
						<button class="aifirst-tab" :class="tab === 'plans' && 'is-active'" @click="tab = 'plans'"><?php esc_html_e( 'Plans', 'aifirstsaas' ); ?></button>
						<button class="aifirst-tab" :class="tab === 'cannot' && 'is-active'" @click="tab = 'cannot'"><?php esc_html_e( 'Cannot-do', 'aifirstsaas' ); ?></button>
						<button class="aifirst-tab" :class="tab === 'usage' && 'is-active'" @click="tab = 'usage'"><?php esc_html_e( 'Usage', 'aifirstsaas' ); ?></button>
						<button class="aifirst-tab" :class="tab === 'sentiment' && 'is-active'" @click="tab = 'sentiment'"><?php esc_html_e( 'Sentiment', 'aifirstsaas' ); ?></button>
						<button class="aifirst-tab" :class="tab === 'changelog' && 'is-active'" @click="tab = 'changelog'"><?php esc_html_e( 'Changelog', 'aifirstsaas' ); ?></button>
					</nav>

					<div x-show="tab === 'features'">
						<?php if ( empty( $features ) ) : ?>
							<p class="aifirst-lead"><?php esc_html_e( 'No AI features catalogued yet.', 'aifirstsaas' ); ?></p>
						<?php else : ?>
							<table class="aifirst-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Feature', 'aifirstsaas' ); ?></th>
										<th><?php esc_html_e( 'Type', 'aifirstsaas' ); ?></th>
										<th><?php esc_html_e( 'Plan', 'aifirstsaas' ); ?></th>
										<th><?php esc_html_e( 'Status', 'aifirstsaas' ); ?></th>
										<th><?php esc_html_e( 'Since', 'aifirstsaas' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $features as $f ) : ?>
										<tr>
											<td style="font-weight:600;"><?php echo esc_html( $f->feature_name ); ?></td>
											<td>
												<span class="aifirst-dot" style="background:<?php echo $f->is_native ? 'var(--aif-accent)' : 'var(--aif-warn)'; ?>;"></span>
												<?php echo $f->is_native ? esc_html__( 'Native', 'aifirstsaas' ) : esc_html__( 'Third-party', 'aifirstsaas' ); ?>
											</td>
											<td><?php AIFirst_Components::plan_pill( $f->plan ); ?></td>
											<td><span class="aifirst-status--<?php echo esc_attr( sanitize_html_class( $f->status ) ); ?>"><?php echo esc_html( $f->status ); ?></span></td>
											<td><?php echo esc_html( $f->since ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>

					<div x-show="tab === 'plans'" x-cloak>
						<?php if ( empty( $plans ) ) : ?>
							<p class="aifirst-lead"><?php esc_html_e( 'No pricing plans catalogued yet.', 'aifirstsaas' ); ?></p>
						<?php else : ?>
							<div class="aifirst-grid-4">
								<?php foreach ( $plans as $plan ) : ?>
									<div class="aifirst-card">
										<div style="font-weight:700; margin-bottom:6px;"><?php echo esc_html( $plan->plan_name ); ?></div>
										<?php if ( null !== $plan->price_usd_monthly ) : ?>
											<div style="font-size:18px; font-weight:800;">$<?php echo esc_html( number_format_i18n( (float) $plan->price_usd_monthly, 2 ) ); ?> <span style="font-size:12px; font-weight:500; color:var(--aif-sub);">/ <?php echo esc_html( $plan->billing_cycle ? $plan->billing_cycle : 'month' ); ?></span></div>
										<?php endif; ?>
										<?php if ( '' !== (string) $plan->user_limit ) : ?>
											<div style="font-size:12.5px; color:var(--aif-sub); margin-top:4px;"><?php echo esc_html( $plan->user_limit ); ?></div>
										<?php endif; ?>
										<?php if ( '' !== (string) $plan->feature_list ) : ?>
											<div style="font-size:12.5px; margin-top:8px; white-space:pre-line;"><?php echo esc_html( $plan->feature_list ); ?></div>
										<?php endif; ?>
										<?php if ( (int) $plan->is_most_popular ) : ?>
											<div style="margin-top:8px;"><span class="aifirst-pill" style="background:var(--aif-accent-weak); color:var(--aif-accent); border-color:var(--aif-accent);"><?php esc_html_e( 'Most popular', 'aifirstsaas' ); ?></span></div>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>

					<div x-show="tab === 'cannot'" x-cloak>
						<?php if ( empty( $cannot ) ) : ?>
							<p class="aifirst-lead"><?php esc_html_e( 'No limitations catalogued yet.', 'aifirstsaas' ); ?></p>
						<?php else : ?>
							<div style="display:flex; flex-direction:column; gap:10px;">
								<?php foreach ( $cannot as $c ) : ?>
									<div class="aifirst-card">
										<div style="font-weight:600;">✗ <?php echo esc_html( $c->limitation_text ); ?></div>
										<?php if ( '' !== (string) $c->workaround ) : ?>
											<div style="font-size:12.5px; color:var(--aif-sub); margin-top:4px;"><?php echo esc_html( sprintf( /* translators: %s: workaround text */ __( 'Workaround: %s', 'aifirstsaas' ), $c->workaround ) ); ?></div>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>

					<div x-show="tab === 'usage'" x-cloak>
						<h3 class="aifirst-h3"><?php esc_html_e( 'Company size distribution', 'aifirstsaas' ); ?></h3>
						<?php
						$size_rows = array(
							array( __( 'Small / SMB', 'aifirstsaas' ), (int) $meta->company_size_smb ),
							array( __( 'Mid-market', 'aifirstsaas' ), (int) $meta->company_size_mid ),
							array( __( 'Enterprise', 'aifirstsaas' ), (int) $meta->company_size_ent ),
						);
						foreach ( $size_rows as $row ) :
							$pct = max( 0, min( 100, (int) $row[1] ) );
							?>
							<div class="aifirst-pctbar">
								<div class="aifirst-pctbar__label"><span><?php echo esc_html( $row[0] ); ?></span><span><?php echo esc_html( $pct ); ?>%</span></div>
								<div class="aifirst-pctbar__track"><div class="aifirst-pctbar__fill" style="width:<?php echo esc_attr( $pct ); ?>%;"></div></div>
							</div>
						<?php endforeach; ?>

						<?php if ( ! empty( $inds ) ) : ?>
							<h3 class="aifirst-h3" style="margin-top:20px;"><?php esc_html_e( 'Top industries', 'aifirstsaas' ); ?></h3>
							<?php foreach ( $inds as $ind ) :
								$pct = max( 0, min( 100, (int) $ind->pct ) );
								?>
								<div class="aifirst-pctbar">
									<div class="aifirst-pctbar__label"><span><?php echo esc_html( $ind->name ); ?></span><span><?php echo esc_html( $pct ); ?>%</span></div>
									<div class="aifirst-pctbar__track"><div class="aifirst-pctbar__fill" style="width:<?php echo esc_attr( $pct ); ?>%;"></div></div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>

						<?php if ( '' !== trim( (string) $meta->notable_customers ) ) : ?>
							<h3 class="aifirst-h3" style="margin-top:20px;"><?php esc_html_e( 'Notable customers', 'aifirstsaas' ); ?></h3>
							<div style="display:flex; gap:8px; flex-wrap:wrap;">
								<?php foreach ( preg_split( '/[,\n]+/', (string) $meta->notable_customers ) as $cust ) :
									$cust = trim( $cust );
									if ( '' === $cust ) { continue; } ?>
									<span class="aifirst-pill"><?php echo esc_html( $cust ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>

					<div x-show="tab === 'sentiment'" x-cloak>
						<?php if ( empty( $senti ) && empty( $quotes ) ) : ?>
							<p class="aifirst-lead"><?php esc_html_e( 'No review data catalogued yet.', 'aifirstsaas' ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $senti ) ) : ?>
							<div style="display:flex; gap:14px; flex-wrap:wrap; margin-bottom:18px;">
								<?php foreach ( $senti as $s ) : ?>
									<?php $outer = '' !== (string) $s->source_url ? 'a' : 'div'; ?>
									<<?php echo esc_html( $outer ); ?> class="aifirst-card" <?php echo 'a' === $outer ? 'href="' . esc_url( $s->source_url ) . '" target="_blank" rel="noopener"' : ''; ?> style="min-width:160px;">
										<div style="font-weight:700;"><?php echo esc_html( $s->source ); ?></div>
										<div style="font-size:18px; font-weight:800; color:var(--aif-warn);">★ <?php echo esc_html( null === $s->score ? '—' : number_format_i18n( (float) $s->score, 1 ) ); ?></div>
										<div style="font-size:11.5px; color:var(--aif-sub);"><?php echo esc_html( number_format_i18n( (int) $s->review_count ) ); ?> <?php esc_html_e( 'reviews', 'aifirstsaas' ); ?></div>
									</<?php echo esc_html( $outer ); ?>>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<?php foreach ( $quotes as $q ) : ?>
							<div style="border-left:2px solid var(--aif-accent); padding: 8px 0 8px 14px; margin-bottom:12px;">
								<div style="font-style:italic;">&ldquo;<?php echo esc_html( $q->quote_text ); ?>&rdquo;</div>
								<?php if ( '' !== (string) $q->role ) : ?>
									<div style="font-size:12px; color:var(--aif-sub); margin-top:4px;">— <?php echo esc_html( $q->role ); ?></div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>

					<div x-show="tab === 'changelog'" x-cloak>
						<?php if ( empty( $radar ) ) : ?>
							<p class="aifirst-lead"><?php esc_html_e( 'No radar entries yet.', 'aifirstsaas' ); ?></p>
						<?php else : ?>
							<?php foreach ( $radar as $r ) : ?>
								<div class="aifirst-radar-row">
									<div class="aifirst-radar-row__date"><?php echo esc_html( AIFirst_Components::date_display( $r->change_date ) ); ?></div>
									<div class="aifirst-radar-row__body">
										<div class="aifirst-radar-row__title"><?php echo esc_html( $r->what_changed ); ?></div>
										<?php if ( '' !== (string) $r->why_it_matters ) : ?>
											<div class="aifirst-radar-row__why"><?php echo esc_html( sprintf( /* translators: %s: why it matters */ __( 'Why it matters: %s', 'aifirstsaas' ), $r->why_it_matters ) ); ?></div>
										<?php endif; ?>
									</div>
									<div class="aifirst-radar-row__type aifirst-radar-row__type--<?php echo esc_attr( sanitize_html_class( $r->change_type ) ); ?>"><?php echo esc_html( $r->change_type ); ?></div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( '' !== trim( (string) $meta->expert_take ) ) : ?>
					<section style="margin-top:28px;">
						<h2 class="aifirst-h2"><?php esc_html_e( 'Expert Take', 'aifirstsaas' ); ?></h2>
						<div class="aifirst-expert">
							<?php echo wp_kses_post( wpautop( (string) $meta->expert_take ) ); ?>
						</div>
					</section>
				<?php endif; ?>

				<?php AIFirst_Components::faq_block( $faq ); ?>
			</main>

			<aside style="display:flex; flex-direction:column; gap:16px;">
				<?php
				$similar = array_filter( $rels, function ( $r ) { return in_array( $r->relationship_type, array( 'alternative', 'competitor' ), true ); } );
				if ( ! empty( $similar ) ) : ?>
					<div class="aifirst-card">
						<strong><?php echo esc_html( sprintf( /* translators: %s: category */ __( 'Similar %s tools', 'aifirstsaas' ), $cat ? $cat->name : 'other' ) ); ?></strong>
						<?php foreach ( $similar as $r ) :
							$target = AIFirst_Query::tool_meta_by_id( $r->target_tool_id );
							if ( ! $target ) { continue; } ?>
							<div style="display:flex; justify-content:space-between; font-size:12.5px; padding:6px 0;">
								<a href="<?php echo esc_url( get_permalink( (int) $target->post_id ) ); ?>"><?php echo esc_html( get_the_title( (int) $target->post_id ) ); ?></a>
								<span style="color:var(--aif-sub);"><?php echo esc_html( (int) $target->ai_maturity_score ); ?>/10</span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="aifirst-card">
					<strong><?php echo esc_html( sprintf( /* translators: %s: tool name */ __( 'Compare %s with', 'aifirstsaas' ), get_the_title() ) ); ?></strong>
					<?php foreach ( $similar as $r ) :
						$target = AIFirst_Query::tool_meta_by_id( $r->target_tool_id );
						if ( ! $target ) { continue; } ?>
						<div style="font-size:12.5px; padding:5px 0;">
							<a href="<?php echo esc_url( home_url( '/compare/' . rawurlencode( $tool_id ) . '/' . rawurlencode( $r->target_tool_id ) . '/' ) ); ?>">
								<?php echo esc_html( get_the_title() ); ?> vs <?php echo esc_html( get_the_title( (int) $target->post_id ) ); ?>
							</a>
						</div>
					<?php endforeach; ?>
				</div>

				<?php AIFirst_Components::tool_comparisons_sidebar( $tool_id ); ?>

				<?php if ( ! empty( $same_co ) ) : ?>
					<div class="aifirst-card">
						<strong><?php esc_html_e( 'Same company', 'aifirstsaas' ); ?></strong>
						<?php foreach ( $same_co as $row ) : ?>
							<div style="font-size:12.5px; padding:5px 0;">
								<a href="<?php echo esc_url( get_permalink( (int) $row->post_id ) ); ?>"><?php echo esc_html( get_the_title( (int) $row->post_id ) ); ?></a>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</aside>
		</div>

		<?php AIFirst_Components::newsletter_form(); ?>
	</div>
</div>

<?php
get_footer();
