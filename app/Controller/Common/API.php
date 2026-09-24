<?php
namespace NiroRoadmap\Controller\Common;

defined( 'ABSPATH' ) || exit;

use WP_REST_Server;
use NiroRoadmap\API\Option;
use NiroRoadmap\API\Task;
use NiroRoadmap\API\Stage;
use NiroRoadmap\Trait\Hook;
use NiroRoadmap\Trait\Auth;
use NiroRoadmap\Trait\Rest;

class API {

	use Hook;
	use Auth;
	use Rest;

	/**
	 * Constructor to add all hooks.
	 */
	public function __construct() {
		$this->action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	public function register_endpoints() {

		/**
		 * Tasks related APIs
		 */
		register_rest_route(
			$this->namespace,
			'/tasks/(?P<id>\d+)/move',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( new Task(), 'move' ),
				'args'                => array(
					'id'    => array(
						'description' => __( 'The `task` ID', 'niroroadmap' ),
						'required'    => true,
					),
					'stage' => array(
						'description' => __( 'The `stage` ID', 'niroroadmap' ),
						'required'    => true,
					),
				),
				'permission_callback' => array( $this, 'is_editor' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/tasks/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( new Task(), 'get' ),
				'args'                => array(
					'id' => array(
						'description' => __( 'The `task` ID', 'niroroadmap' ),
						'required'    => true,
					),
				),
				'permission_callback' => array( $this, 'is_user' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/tasks/(?P<id>\d+)/vote',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( new Task(), 'vote' ),
				'args'                => array(
					'id'   => array(
						'description' => __( 'The `task` ID', 'niroroadmap' ),
						'required'    => true,
					),
					'type' => array(
						'description' => __( 'The vote type- upvote or downvote', 'niroroadmap' ),
						'required'    => true,
						'type'        => 'string',
						'enum'        => array( 'upvote', 'downvote' ),
					),
				),
				'permission_callback' => array( $this, 'is_user' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/tasks/order',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( new Task(), 'order' ),
				'args'                => array(
					'order' => array(
						'description' => __( 'The order', 'niroroadmap' ),
						'required'    => true,
					),
				),
				'permission_callback' => array( $this, 'is_editor' ),
			)
		);

		/**
		 * Stage related APIs
		 */
		register_rest_route(
			$this->namespace,
			'/stages/order',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( new Stage(), 'order' ),
				'args'                => array(
					'order' => array(
						'description' => __( 'The order', 'niroroadmap' ),
						'required'    => true,
					),
				),
				'permission_callback' => array( $this, 'is_editor' ),
			)
		);

		/**
		 * Options related APIs
		 */
		register_rest_route(
			$this->namespace,
			'/option',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( new Option(), 'get' ),
				'args'                => array(
					'key' => array(
						'description' => __( 'The option `key` name', 'niroroadmap' ),
						'required'    => true,
					),
				),
				'permission_callback' => array( $this, 'is_admin' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/option',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( new Option(), 'update' ),
				'args'                => array(
					'key'   => array(
						'description' => __( 'The option `key` name', 'niroroadmap' ),
						'required'    => true,
					),
					'value' => array(
						'description' => __( 'The option `value`', 'niroroadmap' ),
						'required'    => true,
					),
				),
				'permission_callback' => array( $this, 'is_admin' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/option',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( new Option(), 'delete' ),
				'args'                => array(
					'key' => array(
						'description' => __( 'The option `key` name', 'niroroadmap' ),
						'required'    => true,
					),
				),
				'permission_callback' => array( $this, 'is_admin' ),
			)
		);
	}
}
