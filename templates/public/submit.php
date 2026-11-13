<?php
/**
 * Submit page (/submit/).
 *
 * @package AIFirst
 * @since   0.4.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$all_tools = get_posts(
	array(
		'post_type'      => 'aifirst_tool',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);
$all_cats = get_terms( array( 'taxonomy' => 'aifirst_category', 'hide_empty' => false ) );
$faq      = AIFirst_Query::faq();
?>

<div class="aifirst-shell">
	<div class="aifirst-container" style="max-width:720px;">
		<?php require AIFIRST_TEMPLATES_DIR . 'public/partials/flash.php'; ?>

		<h1 class="aifirst-h1"><?php esc_html_e( 'Submit', 'aifirstsaas' ); ?></h1>
		<p class="aifirst-lead"><?php esc_html_e( 'Tell us about a tool we should track, or flag changes on an existing listing. Our team verifies new submissions within 3-5 business days.', 'aifirstsaas' ); ?></p>

		<div x-data="{ tab: 'new' }">
			<div style="display:flex; gap:0; border:1px solid var(--aif-border); border-radius:8px; overflow:hidden; margin-bottom:24px; width:fit-content;">
				<button type="button" class="aifirst-tab" :class="tab === 'new' && 'is-active'" @click="tab = 'new'" style="padding:9px 18px; border-bottom:0;"><?php esc_html_e( 'Submit a new tool', 'aifirstsaas' ); ?></button>
				<button type="button" class="aifirst-tab" :class="tab === 'update' && 'is-active'" @click="tab = 'update'" style="padding:9px 18px; border-bottom:0;"><?php esc_html_e( 'Update an existing listing', 'aifirstsaas' ); ?></button>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aifirst-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( AIFirst_Submissions::ACTION ); ?>" />
				<input type="hidden" name="kind" :value="tab" />
				<?php wp_nonce_field( AIFirst_Submissions::NONCE, 'aifirst_submit_nonce' ); ?>
				<p><input type="text" name="hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute; left:-9999px;" /></p>

				<template x-if="tab === 'update'">
					<label>
						<span><?php esc_html_e( 'Which tool?', 'aifirstsaas' ); ?></span>
						<select name="existing_tool_id">
							<option value=""><?php esc_html_e( '— pick a tool —', 'aifirstsaas' ); ?></option>
							<?php foreach ( $all_tools as $t ) :
								$s = get_post_meta( $t->ID, '_aifirst_tool_id', true ) ?: $t->post_name; ?>
								<option value="<?php echo esc_attr( $s ); ?>"><?php echo esc_html( $t->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</template>

				<template x-if="tab === 'new'">
					<div class="aifirst-submit-group" x-data="{ hasFree: false, cat: '' }">
						<h2 class="aifirst-h3"><?php esc_html_e( 'About the tool', 'aifirstsaas' ); ?></h2>
						<label>
							<span><?php esc_html_e( 'Tool name *', 'aifirstsaas' ); ?></span>
							<input type="text" name="tool_name" maxlength="190" placeholder="e.g. Attio" />
						</label>
						<label>
							<span><?php esc_html_e( 'Website *', 'aifirstsaas' ); ?></span>
							<input type="url" name="website" maxlength="500" placeholder="https://…" />
						</label>
						<label>
							<span><?php esc_html_e( 'Pricing page URL', 'aifirstsaas' ); ?></span>
							<input type="url" name="pricing_url" maxlength="500" placeholder="https://.../pricing" />
						</label>
						<label>
							<span><?php esc_html_e( 'Logo URL', 'aifirstsaas' ); ?></span>
							<input type="url" name="logo_url" maxlength="500" placeholder="https://…/logo.svg" />
							<small style="color:var(--aif-sub); font-size:12px;"><?php esc_html_e( 'Direct URL to a square logo image (SVG or PNG).', 'aifirstsaas' ); ?></small>
						</label>
						<label>
							<span><?php esc_html_e( 'Company name', 'aifirstsaas' ); ?></span>
							<input type="text" name="company_name" maxlength="190" />
						</label>
						<label>
							<span><?php esc_html_e( 'Category *', 'aifirstsaas' ); ?></span>
							<select name="category" x-model="cat">
								<option value=""><?php esc_html_e( '— pick a category —', 'aifirstsaas' ); ?></option>
								<?php foreach ( $all_cats as $c ) : ?>
									<option value="<?php echo esc_attr( $c->slug ); ?>"><?php echo esc_html( $c->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label x-show="cat === 'wordpress'" x-cloak>
							<span><?php esc_html_e( 'Subcategory', 'aifirstsaas' ); ?></span>
							<select name="subcategory">
								<option value=""><?php esc_html_e( '— pick a subcategory —', 'aifirstsaas' ); ?></option>
								<?php
								$wp_subs = array( 'seo', 'security', 'forms', 'ecommerce', 'page-builder', 'backup', 'performance', 'analytics' );
								foreach ( $wp_subs as $s ) {
									printf( '<option value="%1$s">%2$s</option>', esc_attr( $s ), esc_html( ucwords( str_replace( '-', ' ', $s ) ) ) );
								}
								?>
							</select>
						</label>
						<label>
							<span><?php esc_html_e( 'One-sentence excerpt *', 'aifirstsaas' ); ?></span>
							<textarea name="excerpt" rows="2" maxlength="240" placeholder="<?php esc_attr_e( 'One sentence describing what this tool does', 'aifirstsaas' ); ?>"></textarea>
						</label>

						<h2 class="aifirst-h3" style="margin-top:12px;"><?php esc_html_e( 'About the AI features', 'aifirstsaas' ); ?></h2>
						<label>
							<span><?php esc_html_e( 'AI features *', 'aifirstsaas' ); ?></span>
							<textarea name="ai_features" rows="4" maxlength="2000" placeholder="<?php esc_attr_e( 'Comma-separated list of AI features, e.g. AI Writing, Brand Voice, Templates', 'aifirstsaas' ); ?>"></textarea>
						</label>
						<label>
							<span><?php esc_html_e( 'Pricing model', 'aifirstsaas' ); ?></span>
							<select name="pricing_model">
								<option value=""><?php esc_html_e( '— pick one —', 'aifirstsaas' ); ?></option>
								<option value="freemium"><?php esc_html_e( 'Freemium', 'aifirstsaas' ); ?></option>
								<option value="subscription"><?php esc_html_e( 'Subscription', 'aifirstsaas' ); ?></option>
								<option value="one-time"><?php esc_html_e( 'One-time', 'aifirstsaas' ); ?></option>
								<option value="free"><?php esc_html_e( 'Free', 'aifirstsaas' ); ?></option>
								<option value="usage-based"><?php esc_html_e( 'Usage-based', 'aifirstsaas' ); ?></option>
							</select>
						</label>
						<label class="aifirst-check">
							<input type="checkbox" name="has_free_plan" value="1" x-model="hasFree" />
							<span><?php esc_html_e( 'Has a free plan', 'aifirstsaas' ); ?></span>
						</label>
						<label class="aifirst-check" :style="!hasFree && 'opacity:0.5;'">
							<input type="checkbox" name="ai_on_free_plan" value="1" :disabled="!hasFree" />
							<span><?php esc_html_e( 'AI features available on the free plan', 'aifirstsaas' ); ?></span>
						</label>
						<label>
							<span><?php esc_html_e( 'Starting price (USD / month)', 'aifirstsaas' ); ?></span>
							<input type="number" name="starting_price_usd" min="0" step="0.01" placeholder="e.g. 29" />
						</label>
						<label>
							<span><?php esc_html_e( "Why is this tool AI-first? *", 'aifirstsaas' ); ?></span>
							<textarea name="why_ai_first" rows="5" maxlength="1500" placeholder="<?php esc_attr_e( 'Why is this tool genuinely AI-first vs. a traditional tool with AI tacked on? Help us understand what makes it different.', 'aifirstsaas' ); ?>"></textarea>
						</label>

						<h2 class="aifirst-h3" style="margin-top:12px;"><?php esc_html_e( 'About you', 'aifirstsaas' ); ?></h2>
						<label>
							<span><?php esc_html_e( 'Your name', 'aifirstsaas' ); ?></span>
							<input type="text" name="submitter_name" maxlength="190" />
						</label>
						<label>
							<span><?php esc_html_e( 'Contact email *', 'aifirstsaas' ); ?></span>
							<input type="email" name="email" required maxlength="190" placeholder="you@company.com" />
						</label>
						<label>
							<span><?php esc_html_e( 'Your relationship to this tool', 'aifirstsaas' ); ?></span>
							<select name="relationship">
								<option value=""><?php esc_html_e( '— pick one —', 'aifirstsaas' ); ?></option>
								<option value="builder"><?php esc_html_e( 'I built this tool', 'aifirstsaas' ); ?></option>
								<option value="employee"><?php esc_html_e( 'I work at this company', 'aifirstsaas' ); ?></option>
								<option value="user"><?php esc_html_e( 'I use this tool', 'aifirstsaas' ); ?></option>
								<option value="other"><?php esc_html_e( "I'm recommending someone else's tool", 'aifirstsaas' ); ?></option>
							</select>
						</label>
						<label>
							<span><?php esc_html_e( 'Changelog, press release, or launch post', 'aifirstsaas' ); ?></span>
							<input type="url" name="source_url" maxlength="500" />
						</label>
						<label>
							<span><?php esc_html_e( 'Anything else you want the reviewer to know', 'aifirstsaas' ); ?></span>
							<textarea name="notes" rows="3" maxlength="2000"></textarea>
						</label>
					</div>
				</template>

				<template x-if="tab === 'update'">
					<div class="aifirst-submit-group">
						<label>
							<span><?php esc_html_e( 'Contact email *', 'aifirstsaas' ); ?></span>
							<input type="email" name="email" required maxlength="190" placeholder="you@company.com" />
						</label>
						<label>
							<span><?php esc_html_e( "What should change? *", 'aifirstsaas' ); ?></span>
							<textarea name="notes" rows="5" maxlength="2000" required></textarea>
						</label>
						<label>
							<span><?php esc_html_e( 'Source URL', 'aifirstsaas' ); ?></span>
							<input type="url" name="source_url" maxlength="500" />
						</label>
					</div>
				</template>

				<button type="submit" class="aifirst-btn aifirst-btn--primary" style="margin-top:12px;"><?php esc_html_e( 'Submit', 'aifirstsaas' ); ?></button>
			</form>
		</div>

		<?php AIFirst_Components::faq_block( $faq ); ?>
	</div>
</div>

<?php
get_footer();
