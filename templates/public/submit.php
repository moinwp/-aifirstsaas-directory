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
					<div style="display:flex; flex-direction:column; gap:12px;">
						<label>
							<span><?php esc_html_e( 'Tool name', 'aifirstsaas' ); ?></span>
							<input type="text" name="tool_name" maxlength="190" placeholder="e.g. Attio" />
						</label>
						<label>
							<span><?php esc_html_e( 'Website', 'aifirstsaas' ); ?></span>
							<input type="url" name="website" maxlength="500" placeholder="https://…" />
						</label>
						<label>
							<span><?php esc_html_e( 'Category', 'aifirstsaas' ); ?></span>
							<select name="category">
								<option value=""><?php esc_html_e( '— pick a category —', 'aifirstsaas' ); ?></option>
								<?php foreach ( $all_cats as $c ) : ?>
									<option value="<?php echo esc_attr( $c->slug ); ?>"><?php echo esc_html( $c->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>
				</template>

				<label>
					<span><?php esc_html_e( 'AI features (one per line)', 'aifirstsaas' ); ?></span>
					<textarea name="ai_features" rows="4" maxlength="2000" placeholder="<?php esc_attr_e( 'Lead scoring, email drafting, …', 'aifirstsaas' ); ?>"></textarea>
				</label>

				<label>
					<span><?php esc_html_e( 'Source URL (docs / changelog / pricing page)', 'aifirstsaas' ); ?></span>
					<input type="url" name="source_url" maxlength="500" />
				</label>

				<label>
					<span><?php esc_html_e( 'Notes', 'aifirstsaas' ); ?></span>
					<textarea name="notes" rows="3" maxlength="2000"></textarea>
				</label>

				<label>
					<span><?php esc_html_e( 'Contact email', 'aifirstsaas' ); ?></span>
					<input type="email" name="email" required maxlength="190" placeholder="you@company.com" />
				</label>

				<button type="submit" class="aifirst-btn aifirst-btn--primary"><?php esc_html_e( 'Submit', 'aifirstsaas' ); ?></button>
			</form>
		</div>

		<?php AIFirst_Components::faq_block( $faq ); ?>
	</div>
</div>

<?php
get_footer();
