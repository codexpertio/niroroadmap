<?php
namespace NiroRoadmap\Helper\Field;

use NiroRoadmap\Abstract\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Checkbox Field Class
 */
class Checkbox extends Field {

	public function render() {
		$template = '<div id="%1$s" class="%2$s"><label><input type="checkbox" id="%3$s" name="%4$s" value="1" %5$s %6$s /> %7$s</label><p>%8$s</p></div>';

		return sprintf(
			$template,
			$this->get_wrapper_id(),                // %1$s: Wrapper ID
			$this->get_wrapper_class(),             // %2$s: Wrapper class
			$this->get_field_id(),                  // %3$s: Input ID
			$this->get_id(),                        // %4$s: Input name
			$this->get_value() ? 'checked' : '',    // %5$s: Checked attribute
			$this->is_disabled() ? 'disabled' : '', // %6$s: Disabled attribute
			$this->get_label(),                     // %7$s: Label text
			$this->get_description()                // %8$s: Description
		);
	}
}
