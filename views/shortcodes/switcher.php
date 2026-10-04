<?php
/**
 * The Board / List / Timeline switcher. Hidden until the script runs: without it the buttons would do nothing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$views = $args['views'];

?>
<div class="nr-view-switch" role="group" aria-label="<?php esc_attr_e( 'Choose a view', 'niroroadmap' ); ?>" data-nr-switch hidden>
<?php foreach ( $views['views'] as $slug => $label ) : ?>
	<button type="button" class="nr-view-btn" data-nr-view-btn="<?php echo esc_attr( $slug ); ?>" aria-pressed="<?php echo $slug === $views['default'] ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
<?php endforeach; ?>
</div>
