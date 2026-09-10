<?php
namespace NiroRoadmap\Helper\Field;

use NiroRoadmap\Abstract\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Textarea Field Class
 */
class Textarea extends Field {

	public function render() {
		$template = '<div id="%1$s" class="%2$s"><p><label for="%3$s">%4$s</label><textarea id="%3$s" name="%5$s" placeholder="%6$s" class="%7$s" %8$s %9$s>%10$s</textarea>%11$s</p></div>';

		return sprintf(
			$template,
			$this->get_wrapper_id(),                // %1$s: Wrapper ID
			$this->get_wrapper_class(),             // %2$s: Wrapper class
			$this->get_field_id(),                  // %3$s: Textarea ID for `for` and `id`
			$this->get_label(),                     // %4$s: Label text
			$this->get_id(),                        // %5$s: Textarea name
			esc_attr( $this->get_placeholder() ),   // %6$s: Placeholder text
			esc_attr( $this->get_class() ),         // %7$s: Textarea class
			$this->is_disabled() ? 'disabled' : '', // %8$s: Disabled attribute
			$this->is_readonly() ? 'readonly' : '', // %9$s: Readonly attribute
			esc_textarea( $this->get_value() ),     // %10$s: Textarea value
			$this->get_description()                // %11$s: Description
		);
	}
}
