<?php
namespace NiroRoadmap\Helper\Field;

use NiroRoadmap\Abstract\Field;

defined( 'ABSPATH' ) || exit;

/**
 * WYSIWYG Field Class
 */
class WYSIWYG extends Field {

	/**
	 * Renders the WYSIWYG field.
	 */
	public function render() {

		ob_start();
		wp_editor( $this->get_value(), $this->get_id(), array() );
		$editor = ob_get_contents();
		ob_end_clean();

		$template = '<div id="%1$s" class="%2$s"><label for="%3$s">%4$s</label>%5$s<p>%6$s</p></div>';

		return sprintf(
			$template,
			$this->get_wrapper_id(),                // %1$s: Wrapper ID
			$this->get_wrapper_class(),             // %2$s: Wrapper class
			$this->get_field_id(),                  // %3$s: Textarea ID for `for` attribute in label
			$this->get_label(),                     // %4$s: Label text
			$editor,                                // %5$s: Editor
			$this->get_description()                // %6$s: Description
		);
	}
}
