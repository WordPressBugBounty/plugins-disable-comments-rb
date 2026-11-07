<?php
/*
 * RB Disable Comments — settings screen
 *
 * Renders a single mount point for the Shadow-DOM UI (assets/js/rb-admin-settings.js).
 * The <noscript> block below is a full working fallback: same field names,
 * same nonce, same POST handling as the pre-2.0 version, so the page still
 * functions with JS disabled or blocked by a conflicting plugin.
 */

if ( ! defined( 'WPINC' ) ) {
	die();
}

$rb_disable_comments_types = get_post_types( array( 'public' => true ), 'objects' );
foreach ( array_keys( $rb_disable_comments_types ) as $rb_disable_comments_type ) {
	if ( ! in_array( $rb_disable_comments_type, $this->modified_types, true ) && ! post_type_supports( $rb_disable_comments_type, 'comments' ) ) {
		unset( $rb_disable_comments_types[ $rb_disable_comments_type ] );
	}
}

// Fallback POST handling (no-JS path) — same logic as before, kept so the
// screen degrades gracefully rather than failing silently.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this isset() only decides whether to enter the block; check_admin_referer() on the next line verifies the nonce before any $_POST value is read.
if ( isset( $_POST['rb_dc_fallback_submit'] ) ) {
	check_admin_referer( 'disable-comments-rb-options' );

	$rb_disable_comments_mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'off';
	$this->options['remove_everywhere'] = ( 'remove_everywhere' === $rb_disable_comments_mode );

	if ( $this->options['remove_everywhere'] ) {
		$rb_disable_comments_disabled_types = array_keys( $rb_disable_comments_types );
	} else {
		$rb_disable_comments_disabled_types = array();
	}

	$this->options['disabled_post_types'] = array_intersect( $rb_disable_comments_disabled_types, array_keys( $rb_disable_comments_types ) );
	$this->save_options();
}
?>
<div class="wrap" id="rb-disable-comments-wrap">
	<div id="rb-disable-comments-root" aria-live="polite">
		<!-- Mounted by rb-admin-settings.js into a Shadow DOM. If JS fails to
		     run, this div simply stays empty and the noscript form below (or
		     a JS-off browser) is what the admin uses. -->
		<p class="description">
			<?php esc_html_e( 'Loading settings…', 'disable-comments-rb' ); ?>
		</p>
	</div>

	<noscript>
		<style>.indent{padding-left:2em}</style>
		<h1><?php esc_html_e( 'Disable Comments RB', 'disable-comments-rb' ); ?></h1>
		<p><?php esc_html_e( 'Here you can configure your disabled comments tools.', 'disable-comments-rb' ); ?></p>
		<form action="" method="post">
			<ul>
				<li>
					<label>
						<input type="radio" name="mode" value="remove_everywhere"
							<?php checked( ! empty( $this->options['remove_everywhere'] ) ); ?> />
						<strong><?php esc_html_e( 'Disable all comments', 'disable-comments-rb' ); ?></strong>
					</label>
				</li>
				<li>
					<label>
						<input type="radio" name="mode" value="rb_disable_comments_off"
							<?php checked( empty( $this->options['remove_everywhere'] ) ); ?> />
						<strong><?php esc_html_e( 'Enable all comments', 'disable-comments-rb' ); ?></strong>
					</label>
				</li>
			</ul>
			<?php wp_nonce_field( 'disable-comments-rb-options' ); ?>
			<p class="submit">
				<input class="button-primary" type="submit" name="rb_dc_fallback_submit"
					value="<?php esc_attr_e( 'Save Changes', 'disable-comments-rb' ); ?>">
			</p>
		</form>
	</noscript>
</div>
