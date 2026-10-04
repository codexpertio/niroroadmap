<?php
/**
 * The "Suggest an idea" dialog. Printed once per page, after the first board that offers it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$products    = \NiroRoadmap\Model\Submission::products();
$identity    = niroroadmap_get_setting( 'submissions_identity' );
$logged_in   = is_user_logged_in();
$login_only  = niroroadmap_get_setting( 'submissions_login' );
$max_title   = \NiroRoadmap\Model\Submission::max_title();
$max_desc    = \NiroRoadmap\Model\Submission::max_description();
$ask_who     = ! $logged_in && 'off' !== $identity;
$who_needed  = 'required' === $identity;

?>
<div class="nr-modal-overlay nr-suggest-overlay" id="nr-suggest-overlay" style="display: none;">
	<div class="nr-modal nr-suggest-modal" id="nr-suggest" role="dialog" aria-modal="true" aria-labelledby="nr-suggest-title">
		<button type="button" class="nr-close-modal" id="nr-suggest-close" aria-label="<?php esc_attr_e( 'Close', 'niroroadmap' ); ?>">&times;</button>

		<h2 id="nr-suggest-title"><?php esc_html_e( 'Suggest an idea', 'niroroadmap' ); ?></h2>

		<?php if ( $login_only && ! $logged_in ) : ?>
			<p class="nr-suggest-login">
				<a href="<?php echo esc_url( wp_login_url( (string) get_permalink() ) ); ?>"><?php esc_html_e( 'Log in to suggest an idea.', 'niroroadmap' ); ?></a>
			</p>
		<?php else : ?>
		<form class="nr-suggest-form" id="nr-suggest-form" novalidate>
			<input type="hidden" name="product" id="nr-suggest-product-fixed" value="" disabled>

			<div class="nr-field">
				<label for="nr-suggest-name-title"><?php esc_html_e( 'Title', 'niroroadmap' ); ?> <span class="nr-required" aria-hidden="true">*</span></label>
				<input type="text" name="title" id="nr-suggest-name-title" maxlength="<?php echo esc_attr( $max_title ); ?>" required autocomplete="off" aria-describedby="nr-suggest-similar-label">
			</div>

			<div class="nr-suggest-similar" id="nr-suggest-similar" hidden>
				<p id="nr-suggest-similar-label"><?php esc_html_e( 'Already suggested? Check these first:', 'niroroadmap' ); ?></p>
				<ul id="nr-suggest-similar-list"></ul>
			</div>

			<div class="nr-field">
				<label for="nr-suggest-description"><?php esc_html_e( 'Details (optional)', 'niroroadmap' ); ?></label>
				<textarea name="description" id="nr-suggest-description" rows="5" maxlength="<?php echo esc_attr( $max_desc ); ?>"></textarea>
			</div>

			<?php if ( $products ) : ?>
				<div class="nr-field" id="nr-suggest-product-field">
					<label for="nr-suggest-product"><?php esc_html_e( 'Product', 'niroroadmap' ); ?></label>
					<select name="product" id="nr-suggest-product">
						<option value=""><?php esc_html_e( '— Choose —', 'niroroadmap' ); ?></option>
						<?php foreach ( $products as $product_id => $product_name ) : ?>
							<option value="<?php echo esc_attr( $product_id ); ?>"><?php echo esc_html( $product_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<?php if ( $ask_who ) : ?>
				<div class="nr-field">
					<label for="nr-suggest-name"><?php esc_html_e( 'Name', 'niroroadmap' ); ?><?php if ( $who_needed ) : ?> <span class="nr-required" aria-hidden="true">*</span><?php endif; ?></label>
					<input type="text" name="name" id="nr-suggest-name" autocomplete="name" maxlength="100" data-required="<?php echo $who_needed ? '1' : '0'; ?>">
				</div>
				<div class="nr-field">
					<label for="nr-suggest-email"><?php esc_html_e( 'Email', 'niroroadmap' ); ?><?php if ( $who_needed ) : ?> <span class="nr-required" aria-hidden="true">*</span><?php endif; ?></label>
					<input type="email" name="email" id="nr-suggest-email" autocomplete="email" maxlength="100" aria-describedby="nr-suggest-email-hint" data-required="<?php echo $who_needed ? '1' : '0'; ?>">
					<small id="nr-suggest-email-hint"><?php esc_html_e( 'Never shown publicly.', 'niroroadmap' ); ?></small>
				</div>
			<?php endif; ?>

			<?php // Spam trap: hidden from people, tempting to bots. ?>
			<div class="nr-hp" aria-hidden="true">
				<label for="nr-suggest-website"><?php esc_html_e( 'Website', 'niroroadmap' ); ?></label>
				<input type="text" name="website" id="nr-suggest-website" tabindex="-1" autocomplete="off">
			</div>

			<p class="nr-form-error" id="nr-suggest-error" role="alert"></p>
			<button type="submit" class="nr-submit-btn" id="nr-suggest-submit"><?php esc_html_e( 'Send idea', 'niroroadmap' ); ?></button>
		</form>
		<?php endif; ?>

		<div class="nr-suggest-done" id="nr-suggest-done" role="status" tabindex="-1" hidden>
			<h3><?php esc_html_e( 'Thank you!', 'niroroadmap' ); ?></h3>
			<p id="nr-suggest-done-message"></p>
			<button type="button" class="nr-submit-btn" id="nr-suggest-done-close"><?php esc_html_e( 'Close', 'niroroadmap' ); ?></button>
		</div>
	</div>
</div>
