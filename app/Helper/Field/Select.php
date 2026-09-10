<?php
namespace NiroRoadmap\Helper\Field;

use NiroRoadmap\Abstract\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Select Field Class
 */
class Select extends Field {

	public function render() {
		$template = '
        <div id="%1$s" class="%2$s">
            <label for="%3$s">%4$s</label>
            <select id="%3$s" name="%5$s%6$s" %7$s %8$s>%9$s</select>
            <p>%10$s</p>
        </div>';

		$options_html = '';
		$disabled     = $this->is_disabled();

		foreach ( $this->get_options() as $key => $value ) {
			$is_option_disabled = is_array( $disabled ) ? in_array( $key, $disabled ) : $disabled;

			$options_html .= sprintf(
				'<option value="%1$s" %2$s %3$s>%4$s</option>',
				$key,
				selected( $this->get_value(), $key, false ),
				$is_option_disabled ? 'disabled' : '',
				$value
			);
		}

		return sprintf(
			$template,
			$this->get_wrapper_id(),                            // %1$s: Wrapper ID
			$this->get_wrapper_class(),                         // %2$s: Wrapper class
			$this->get_field_id(),                              // %3$s: Wrapper class
			$this->get_label(),                                 // %4$s: Label text
			$this->get_id(),                                    // %5$s: Label text
			$this->is_multiple() ? '[]' : '',                   // %6$s: Multiple attribute for name
			$this->is_disabled() === true ? 'disabled' : '',    // %7$s: Disabled attribute
			$this->is_multiple() ? 'multiple' : '',             // %8$s: Multiple attribute
			$options_html,                                      // %9$s: Options HTML
			$this->get_description()                            // %10$s: Description
		);
	}
}
