<?php
namespace NiroRoadmap\Helper\Field;

use NiroRoadmap\Abstract\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Input Field Class
 */
class Text extends Field {

	public function render() {
		$template = '<div id="%1$s" class="%2$s"><p><label for="%3$s">%4$s</label><input type="%5$s" id="%6$s" name="%7$s" value="%8$s" placeholder="%9$s" class="%10$s" %11$s %12$s />%13$s</p></div>';

		return sprintf(
			$template,
			$this->get_wrapper_id(),                // %1$s: Wrapper ID
			$this->get_wrapper_class(),             // %2$s: Wrapper class
			$this->get_field_id(),                  // %3$s: Input ID for `for` attribute in label
			$this->get_label(),                     // %4$s: Label text
			$this->get_type(),                      // %5$s: Input type
			$this->get_field_id(),                  // %6$s: Input ID
			$this->get_id(),                        // %7$s: Input name
			esc_attr( $this->get_value() ),         // %8$s: Input value
			esc_attr( $this->get_placeholder() ),   // %9$s: Placeholder text
			esc_attr( $this->get_class() ),         // %10$s: Input class
			$this->is_disabled() ? 'disabled' : '', // %11$s: Disabled attribute
			$this->is_readonly() ? 'readonly' : '', // %12$s: Readonly attribute
			$this->get_description()                // %13$s: Description
		);
	}
}
