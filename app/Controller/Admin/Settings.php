<?php
namespace NiroRoadmap\Controller\Admin;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Trait\Hook;
use NiroRoadmap\Trait\Menu;
use NiroRoadmap\Helper\Settings as Settings_Helper;

class Settings {

	use Hook;
	use Menu;

	const SLUG = 'niroroadmap-settings';

	/**
	 * Constructor to add all hooks.
	 */
	public function __construct() {
		$this->action( 'admin_menu', array( $this, 'add_menu_page' ) );
		$this->action( 'admin_init', array( $this, 'register_settings' ) );
		$this->action( 'admin_post_niroroadmap_reset_settings', array( $this, 'reset' ) );
		$this->filter( 'plugin_action_links_' . plugin_basename( NIROROADMAP_FILE ), array( $this, 'add_action_link' ) );
	}

	public function add_menu_page() {
		$this->add_submenu(
			'edit.php?post_type=niroroadmap_item',
			__( 'NiroRoadmap Settings', 'niroroadmap' ),
			__( 'Settings', 'niroroadmap' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Let the Settings API do the nonce, capability check and saving; sanitizing is ours.
	 */
	public function register_settings() {
		register_setting(
			'niroroadmap_settings',
			Settings_Helper::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings_Helper::class, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	public function add_action_link( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->tab_url( 'general' ) ),
				esc_html__( 'Settings', 'niroroadmap' )
			)
		);

		return $links;
	}

	/**
	 * Reset every setting to its default.
	 */
	public function reset() {
		check_admin_referer( 'niroroadmap_reset_settings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'niroroadmap' ), '', array( 'response' => 403 ) );
		}

		delete_option( Settings_Helper::OPTION );

		wp_safe_redirect( add_query_arg( 'niroroadmap-reset', '1', $this->tab_url( 'advanced' ) ) );
		exit;
	}

	private function tab_url( $tab ) {
		return add_query_arg(
			array(
				'post_type' => 'niroroadmap_item',
				'page'      => self::SLUG,
				'tab'       => $tab,
			),
			admin_url( 'edit.php' )
		);
	}

	private function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks which tab to show.

		return isset( Settings_Helper::tabs()[ $tab ] ) ? $tab : 'general';
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = $this->current_tab();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NiroRoadmap Settings', 'niroroadmap' ); ?></h1>

			<?php
			settings_errors( Settings_Helper::OPTION );

			if ( isset( $_GET['niroroadmap-reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
				?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings were reset to their defaults.', 'niroroadmap' ); ?></p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings sections', 'niroroadmap' ); ?>">
				<?php foreach ( Settings_Helper::tabs() as $slug => $label ) : ?>
					<a href="<?php echo esc_url( $this->tab_url( $slug ) ); ?>" class="nav-tab<?php echo $slug === $tab ? ' nav-tab-active' : ''; ?>"<?php echo $slug === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( 'niroroadmap_settings' ); ?>
				<input type="hidden" name="<?php echo esc_attr( Settings_Helper::OPTION ); ?>[_tab]" value="<?php echo esc_attr( $tab ); ?>">

				<table class="form-table" role="presentation">
					<?php
					foreach ( Settings_Helper::fields() as $key => $field ) {
						if ( $field['tab'] === $tab ) {
							$this->render_field( $key, $field );
						}
					}
					?>
				</table>

				<?php submit_button(); ?>
			</form>

			<?php if ( 'advanced' === $tab ) : ?>
				<hr>
				<h2><?php esc_html_e( 'Reset', 'niroroadmap' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="niroroadmap_reset_settings">
					<?php wp_nonce_field( 'niroroadmap_reset_settings' ); ?>
					<p><?php esc_html_e( 'Restore every setting, on all tabs, to its default. Roadmap items are not affected.', 'niroroadmap' ); ?></p>
					<?php submit_button( __( 'Reset to defaults', 'niroroadmap' ), 'secondary', 'submit', false, array( 'onclick' => 'return confirm(' . wp_json_encode( __( 'Reset all NiroRoadmap settings to their defaults?', 'niroroadmap' ) ) . ');' ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Output one settings row.
	 */
	private function render_field( $key, $field ) {
		$name  = Settings_Helper::OPTION . '[' . $key . ']';
		$id    = 'niroroadmap-' . $key;
		$value = Settings_Helper::get( $key );
		$desc  = $field['description'];
		$attrs = $desc ? ' aria-describedby="' . esc_attr( $id ) . '-desc"' : '';
		?>
		<tr>
			<th scope="row">
				<?php if ( 'checkbox' === $field['type'] ) : ?>
					<?php echo esc_html( $field['label'] ); ?>
				<?php else : ?>
					<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
				<?php endif; ?>
			</th>
			<td>
				<?php if ( 'checkbox' === $field['type'] ) : ?>
					<label for="<?php echo esc_attr( $id ); ?>">
						<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $value ); ?><?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above. ?>>
						<?php echo esc_html( $field['label'] ); ?>
					</label>
				<?php elseif ( 'select' === $field['type'] ) : ?>
					<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php foreach ( $field['options']() as $option => $label ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( (string) $value, (string) $option ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php elseif ( 'color' === $field['type'] ) : ?>
					<input type="color" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php elseif ( 'email' === $field['type'] ) : ?>
					<input type="email" class="regular-text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php elseif ( 'number' === $field['type'] ) : ?>
					<input type="number" class="small-text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $field['min'] ); ?>" max="<?php echo esc_attr( $field['max'] ); ?>" step="1"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php elseif ( 'textarea' === $field['type'] ) : ?>
					<textarea class="large-text code" rows="8" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( $value ); ?></textarea>
				<?php endif; ?>

				<?php if ( $desc ) : ?>
					<p class="description" id="<?php echo esc_attr( $id ); ?>-desc"><?php echo esc_html( $desc ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}
}
