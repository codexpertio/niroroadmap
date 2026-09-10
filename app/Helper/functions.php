<?php
use NiroRoadmap\Helper\Utility;

/**
 * Returns the home URL of the WordPress site.
 *
 * @param string $path    Optional. Path relative to the home URL.
 * @param int    $blog_id Optional. ID of the blog in a multisite installation.
 *
 * @return string Home URL with optional path appended.
 */
function niroroadmap_home_url( $path = '', $blog_id = null ) {
	return get_home_url( $blog_id, $path );
}

function niroroadmap_settings_menus() {

	$pages = Utility::get_posts( array( 'post_type' => 'page' ) );

	return apply_filters(
		'niroroadmap_settings_menus',
		array(
			'general' => array(
				'label'    => __( 'General', 'niroroadmap' ),
				'desc'     => __( 'General settings', 'niroroadmap' ),
				'icon'     => '',
				'submenus' => array(
					'pages' => array(
						'label'    => __( 'Pages', 'niroroadmap' ),
						'desc'     => __( 'Page Settings', 'niroroadmap' ),
						'sections' => array(
							'main_pages' => array(
								'label'  => __( 'Main Pages', 'niroroadmap' ),
								'desc'   => __( 'Main Pages Settings', 'niroroadmap' ),
								'fields' => array(
									array(
										'id'      => 'homepage',
										'type'    => 'select',
										'label'   => __( 'Homepage', 'niroroadmap' ),
										'options' => $pages,
									),
									array(
										'id'      => 'landing_page',
										'type'    => 'select',
										'label'   => __( 'Landing Page', 'niroroadmap' ),
										'options' => $pages,
									),
								),
							),
						),
					),
				),
			),
			'email'   => array(
				'label'    => __( 'Email', 'niroroadmap' ),
				'desc'     => __( 'Email settings', 'niroroadmap' ),
				'icon'     => '',
				'submenus' => array(
					'new_ticket'    => array(
						'label'    => __( 'New Ticket', 'niroroadmap' ),
						'desc'     => __( 'New Ticket Notification', 'niroroadmap' ),
						'sections' => array(
							'agent_email'  => array(
								'label'  => __( 'Agent Email', 'niroroadmap' ),
								'desc'   => __( 'Email to an Agent', 'niroroadmap' ),
								'fields' => array(
									array(
										'id'    => 'agent_header',
										'type'  => 'text',
										'label' => __( 'Header', 'niroroadmap' ),
									),
									array(
										'id'    => 'agent_subject',
										'type'  => 'text',
										'label' => __( 'Subject', 'niroroadmap' ),
									),
									array(
										'id'    => 'agent_body',
										'type'  => 'wysiwyg',
										'label' => __( 'Body', 'niroroadmap' ),
									),
								),
							),
							'client_email' => array(
								'label'  => __( 'Client Email', 'niroroadmap' ),
								'desc'   => __( 'Email to a Client', 'niroroadmap' ),
								'fields' => array(
									array(
										'id'    => 'client_header',
										'type'  => 'text',
										'label' => __( 'Header', 'niroroadmap' ),
									),
									array(
										'id'    => 'client_subject',
										'type'  => 'text',
										'label' => __( 'Subject', 'niroroadmap' ),
									),
									array(
										'id'    => 'client_body',
										'type'  => 'wysiwyg',
										'label' => __( 'Body', 'niroroadmap' ),
									),
								),
							),
						),
					),
					'agent_replied' => array(
						'label'    => __( 'Agent Reply', 'niroroadmap' ),
						'desc'     => __( 'Agent Reply Notification', 'niroroadmap' ),
						'sections' => array(
							'agent_email_reply' => array(
								'label'  => __( 'Agent Reply Email', 'niroroadmap' ),
								'desc'   => __( 'Email to a Client', 'niroroadmap' ),
								'fields' => array(
									array(
										'id'    => 'client_header',
										'type'  => 'text',
										'label' => __( 'Header', 'niroroadmap' ),
									),
									array(
										'id'    => 'client_subject',
										'type'  => 'text',
										'label' => __( 'Subject', 'niroroadmap' ),
									),
									array(
										'id'    => 'client_body',
										'type'  => 'wysiwyg',
										'label' => __( 'Body', 'niroroadmap' ),
									),
								),
							),
						),
					),
				),
			),
		)
	);
}

function niroroadmap_get_field_factory( $type ) {

	if ( $type == 'switch' ) {
		$type = 'switcher';
	} elseif ( $type == 'wysiwyg' ) {
		$type = 'WYSIWYG';
	}

	return '\\NiroRoadmap\\Helper\\Field\\' . ucfirst( $type );
}

function niroroadmap_product_post_type() {
	return 'niroroadmap_item';
}

function niroroadmap_get_random_color() {
	$colors = array( '#FF9999', '#FFCC99', '#FFCC66', '#FFD700', '#FF9966', '#FF6666', '#FF9966', '#FFB266', '#FFDAB9', '#FF8C66', '#FFC1A1', '#FFE5B4', '#B3E5FC', '#81D4FA', '#4FC3F7', '#4DB6AC', '#81C784', '#AED581', '#DCE775', '#FFE082', '#FF8A65', '#F48FB1', '#E57373', '#BA68C8', '#9575CD', '#7986CB' );

	return $colors[ array_rand( $colors ) ];
}
