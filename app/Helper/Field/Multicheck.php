<?php
namespace NiroRoadmap\Helper\Field;

use NiroRoadmap\Abstract\Field;

defined( 'ABSPATH' ) || exit;

class Multicheck extends Field {

	protected $option_type = 'checkbox';

	public function render() {
		$template = '<div id="%1$s" class="%2$s">%3$s<p>%4$s</p></div>';

		$inputs   = '';
		$disabled = $this->is_disabled();

		foreach ( $this->get_options() as $key => $value ) {
			$is_field_disabled = is_array( $disabled ) ? in_array( $key, $disabled ) : $disabled;

			$inputs .= sprintf(
				'<label><input type="%5$s" id="%1$s_%2$s" name="%6$s%7$s" value="%2$s" %3$s %4$s /> %8$s</label><br>',
				$this->get_field_id(),                          // %1$s: Input ID base
				$key,                                           // %2$s: Option key
				checked( $this->get_value(), $key, false ),     // %3$s: Checked attribute
				$is_field_disabled ? 'disabled' : '',           // %4$s: Disabled attribute
				$this->option_type,                             // %5$s: Input type
				$this->get_id(),                                // %6$s: Input name
				$this->option_type === 'checkbox' ? '[]' : '',  // %7$s: Multiple attribute for name
				$value                                          // %8$s: Option label
			);
		}

		return sprintf(
			$template,
			$this->get_wrapper_id(),                // %1$s: Wrapper ID
			$this->get_wrapper_class(),             // %2$s: Wrapper class
			$inputs,                                // %3$s: Inputs HTML
			$this->get_description()                // %4$s: Description
		);
	}
}
