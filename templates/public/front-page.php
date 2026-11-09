<?php
/**
 * Homepage — opt in by setting the option aifirst_own_home = 1.
 *
 * @package AIFirst
 * @since   0.4.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$counters  = AIFirst_Query::counters();
$recent    = AIFirst_Query::radar_recent( array( 'limit' => 6, 'days' => 14 ) );
$leaders   = AIFirst_Query::leaderboard( array( 'limit' => 8 ) );
$faq       = AIFirst_Query::faq();
$universal = get_terms(
	array(
		'taxonomy'   => 'aifirst_feature',
		'hide_empty' => true,
		'number'     => 10,
	)
);
?>

<div class="aifirst-shell">
	<div class="aifirst-container">
		<?php require AIFIRST_TEMPLATES_DIR . 'public/partials/flash.php'; ?>

		<div style="display:flex; flex-wrap:wrap; gap:40px; padding:40px 0; align-items:start;">
			<div style="flex:1 1 420px; min-width:0;">
				<h1 class="aifirst-h1" style="font-size:40px;"><?php esc_html_e( 'What AI can your SaaS tools actually do?', 'aifirstsaas' ); ?></h1>
				<p class="aifirst-lead"><?php esc_html_e( 'Verified AI feature data for every SaaS tool your team uses. Updated weekly, not guessed.', 'aifirstsaas' ); ?></p>

				<form method="get" action="<?php echo esc_url( get_post_type_archive_link( 'aifirst_tool' ) ); ?>" style="display:flex; margin-top:16px;">
					<input type="search" name="s" placeholder="<?php esc_attr_e( 'Search a tool, feature, or category…', 'aifirstsaas' ); ?>" style="flex:1; padding:14px 16px; border:1px solid var(--aif-border); border-right:0; border-radius:10px 0 0 10px; background:var(--aif-card); color:var(--aif-text); font-size:14px;" />
					<button type="submit" class="aifirst-btn aifirst-btn--primary" style="border-radius:0 10px 10px 0;"><?php esc_html_e( 'Search', 'aifirstsaas' ); ?></button>
				</form>

				<div style="display:flex; gap:36px; margin-top:28px; flex-wrap:wrap;">
					<div><div style="font-size:26px; font-weight:800;"><?php echo esc_html( number_format_i18n( $counters['tools'] ) ); ?></div><div style="font-size:12.5px; color:var(--aif-sub);"><?php esc_html_e( 'tools tracked', 'aifirstsaas' ); ?></div></div>
					<div><div style="font-size:26px; font-weight:800;"><?php echo esc_html( number_format_i18n( $counters['features'] ) ); ?></div><div style="font-size:12.5px; color:var(--aif-sub);"><?php esc_html_e( 'AI features mapped', 'aifirstsaas' ); ?></div></div>
					<div><div style="font-size:26px; font-weight:800;"><?php esc_html_e( 'Weekly', 'aifirstsaas' ); ?></div><div style="font-size:12.5px; color:var(--aif-sub);"><?php esc_html_e( 'verification cycle', 'aifirstsaas' ); ?></div></div>
				</div>

				<div style="display:flex; flex-direction:column; gap:8px; margin-top:24px;">
					<a href="<?php echo esc_url( get_post_type_archive_link( 'aifirst_tool' ) ); ?>" class="aifirst-btn"><?php echo esc_html( sprintf( /* translators: %d: count */ __( '→ Browse all %d tools', 'aifirstsaas' ), $counters['tools'] ) ); ?></a>
					<a href="<?php echo esc_url( home_url( '/compare/' ) ); ?>" class="aifirst-btn"><?php esc_html_e( '→ Compare two tools', 'aifirstsaas' ); ?></a>
					<a href="<?php echo esc_url( home_url( '/changelog/' ) ); ?>" class="aifirst-btn"><?php esc_html_e( '→ What changed this week', 'aifirstsaas' ); ?></a>
				</div>
			</div>

			<div class="aifirst-card" style="flex:1 1 480px; min-width:0; padding:0;">
				<div style="padding:18px 20px; background:var(--aif-bg2); border-bottom:1px solid var(--aif-border);">
					<div style="font-size:15px; font-weight:700;"><?php esc_html_e( 'This week in AI features', 'aifirstsaas' ); ?></div>
					<div style="font-size:12.5px; color:var(--aif-sub); margin-top:3px;"><?php esc_html_e( 'Recent movement across tracked tools', 'aifirstsaas' ); ?></div>
				</div>
				<?php if ( empty( $recent ) ) : ?>
					<div style="padding:20px; color:var(--aif-sub); font-size:13px;"><?php esc_html_e( 'No radar entries in the last 14 days.', 'aifirstsaas' ); ?></div>
				<?php else : ?>
					<?php foreach ( $recent as $r ) :
						$tm = AIFirst_Query::tool_meta_by_id( (string) $r->tool_id );
						if ( ! $tm ) { continue; } ?>
						<a href="<?php echo esc_url( get_permalink( (int) $tm->post_id ) ); ?>" style="display:flex; gap:12px; padding:14px 20px; border-bottom:1px solid var(--aif-border); color:inherit;">
							<?php AIFirst_Components::avatar( (int) $tm->post_id, $tm->initials, $tm->avatar_color, 32 ); ?>
							<div style="flex:1; min-width:0;">
								<div style="font-size:13.5px; font-weight:700;"><?php echo esc_html( get_the_title( (int) $tm->post_id ) ); ?></div>
								<div style="font-size:12px; color:var(--aif-sub);"><?php echo esc_html( $r->what_changed ); ?></div>
							</div>
							<span class="aifirst-radar-row__type aifirst-radar-row__type--<?php echo esc_attr( sanitize_html_class( $r->change_type ) ); ?>"><?php echo esc_html( $r->change_type ); ?></span>
						</a>
					<?php endforeach; ?>
				<?php endif; ?>
				<div style="padding:14px 20px; text-align:right;">
					<a href="<?php echo esc_url( home_url( '/changelog/' ) ); ?>" class="aifirst-btn aifirst-btn--primary"><?php esc_html_e( 'View full AI Radar →', 'aifirstsaas' ); ?></a>
				</div>
			</div>
		</div>

		<section style="margin:40px 0;">
			<h2 class="aifirst-h2"><?php esc_html_e( 'Explore by what AI actually does', 'aifirstsaas' ); ?></h2>
			<p class="aifirst-lead"><?php esc_html_e( 'Pick a capability, see which tools have it.', 'aifirstsaas' ); ?></p>
			<div class="aifirst-grid-4">
				<?php foreach ( $universal as $f ) :
					$count = AIFirst_Query::count_tools_with_feature( $f->slug ); ?>
					<a class="aifirst-card" href="<?php echo esc_url( get_term_link( $f ) ); ?>" style="color:inherit;">
						<span class="aifirst-dot"></span>
						<div style="font-weight:700; margin-top:6px;"><?php echo esc_html( $f->name ); ?></div>
						<div style="font-size:12px; color:var(--aif-sub);"><?php echo esc_html( sprintf( /* translators: %d: count */ __( '%d tools', 'aifirstsaas' ), $count ) ); ?></div>
					</a>
				<?php endforeach; ?>
			</div>
		</section>

		<section style="margin:40px 0;">
			<h2 class="aifirst-h2"><?php esc_html_e( 'Highest AI maturity right now', 'aifirstsaas' ); ?></h2>
			<p class="aifirst-lead"><?php esc_html_e( 'Scored on native integration, plan availability, feature stability, and workflow coverage.', 'aifirstsaas' ); ?></p>
			<?php if ( empty( $leaders ) ) : ?>
				<p class="aifirst-lead"><?php esc_html_e( 'No scored tools yet.', 'aifirstsaas' ); ?></p>
			<?php else : ?>
				<div>
					<?php foreach ( $leaders as $i => $l ) : ?>
						<a href="<?php echo esc_url( get_permalink( (int) $l->post_id ) ); ?>" style="display:flex; align-items:center; gap:14px; padding:10px 6px; border-bottom:1px solid var(--aif-border); color:inherit;">
							<span style="width:18px; color:var(--aif-sub); font-size:12px;"><?php echo esc_html( $i + 1 ); ?></span>
							<?php AIFirst_Components::avatar( (int) $l->post_id, $l->initials, $l->avatar_color, 28 ); ?>
							<div style="flex:1; min-width:0;">
								<div style="font-weight:700;"><?php echo esc_html( $l->post_title ); ?></div>
							</div>
							<?php AIFirst_Components::maturity_bars( (int) $l->ai_maturity_score ); ?>
							<span style="font-weight:700; width:36px; text-align:right;"><?php echo esc_html( (int) $l->ai_maturity_score ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>

		<section style="margin:40px 0;">
			<h2 class="aifirst-h2"><?php esc_html_e( 'Trending Comparisons', 'aifirstsaas' ); ?></h2>
			<?php AIFirst_Components::trending_comparisons( 6 ); ?>
			<p style="margin-top:14px;">
				<a href="<?php echo esc_url( home_url( '/compare/' ) ); ?>" class="aifirst-btn"><?php esc_html_e( 'Compare any two tools →', 'aifirstsaas' ); ?></a>
			</p>
		</section>

		<section class="aifirst-card" style="margin:36px 0; display:grid; grid-template-columns:1fr 1.4fr; gap:36px;">
			<div>
				<div class="aifirst-eyebrow" style="color:var(--aif-accent);"><?php esc_html_e( 'About the directory', 'aifirstsaas' ); ?></div>
				<h2 class="aifirst-h2"><?php esc_html_e( 'What is AI-First SaaS?', 'aifirstsaas' ); ?></h2>
				<p style="font-size:14px; color:var(--aif-sub); line-height:1.65;"><?php esc_html_e( 'A structured reference for AI features inside the SaaS tools teams already use — not marketing claims, but verified, comparable data you can filter, sort, and act on.', 'aifirstsaas' ); ?></p>
			</div>
			<div style="display:flex; flex-direction:column; border-left:1px solid var(--aif-border); padding-left:32px;">
				<div style="padding:12px 0; border-bottom:1px solid var(--aif-border);"><strong><?php esc_html_e( '01  Verified, not guessed', 'aifirstsaas' ); ?></strong><div style="font-size:13px; color:var(--aif-sub);"><?php esc_html_e( 'Every feature is checked against changelogs, docs, and pricing pages, then scored on a consistent 1-10 maturity scale.', 'aifirstsaas' ); ?></div></div>
				<div style="padding:12px 0; border-bottom:1px solid var(--aif-border);"><strong><?php esc_html_e( '02  Built for buyers', 'aifirstsaas' ); ?></strong><div style="font-size:13px; color:var(--aif-sub);"><?php esc_html_e( 'Compare tools side by side, track what changed this week, and see exactly which plan unlocks which AI capability.', 'aifirstsaas' ); ?></div></div>
				<div style="padding:12px 0;"><strong><?php esc_html_e( '03  Updated weekly', 'aifirstsaas' ); ?></strong><div style="font-size:13px; color:var(--aif-sub);"><?php esc_html_e( 'Rolling re-checks, with the highest-traffic tools reviewed every week.', 'aifirstsaas' ); ?></div></div>
			</div>
		</section>

		<?php AIFirst_Components::newsletter_form(); ?>

		<?php AIFirst_Components::faq_block( $faq ); ?>
	</div>
</div>

<?php
get_footer();
