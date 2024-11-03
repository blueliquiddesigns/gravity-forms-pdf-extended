<?php

namespace GFPDF\Rest;

use GFPDF\Helper\Helper_Data;
use GFPDF\Helper\Helper_Form;
use GFPDF\Helper\Helper_Misc;
use GFPDF\Helper\Helper_Options_Fields;
use GFPDF\Helper\Helper_Templates;
use GFPDF\Statics\Kses;
use GPDFAPI;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * @package     Gravity PDF
 * @copyright   Copyright (c) 2024, Blue Liquid Designs
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

/* Exit if accessed directly */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 7.0
 */
class Rest_Form_Settings extends WP_REST_Controller {

	/**
	 * @var string
	 * @since 7.0
	 */
	public const NAMESPACE = Helper_Data::REST_API_BASENAME . 'v1';

	/**
	 * @var string
	 * @since 7.0
	 */
	public const API_BASE = '/form';

	/**
	 * @var string[]
	 * @since 7.0
	 */
	public static $endpoints = [
		'pdf-settings'      => self::API_BASE . '/(?P<form>[\d]+)',
		'pdf-settings-item' => self::API_BASE . '/(?P<form>[\d]+)/(?P<pdf>[a-fA-F0-9]{13})',
		'template-schema'   => self::API_BASE . '/(?P<form>[\d]+)/schema',
	];

	/**
	 * @var Helper_Options_Fields
	 * @since 7.0
	 */
	protected $options;

	/**
	 * @var Helper_Form
	 * @since 7.0
	 */
	protected $gform;

	/**
	 * @var Helper_Misc
	 * @since 7.0
	 */
	protected $misc;

	/**
	 * @var Helper_Templates
	 * @since 7.0
	 */
	protected $templates;

	/**
	 * @param Helper_Options_Fields $options
	 * @param Helper_Form $gform
	 * @param Helper_Misc $misc
	 * @param Helper_Templates $templates
	 *
	 * @since 7.0
	 */
	public function __construct( $options, $gform, $misc, $templates ) {
		$this->options   = $options;
		$this->gform     = $gform;
		$this->misc      = $misc;
		$this->templates = $templates;
	}

	/**
	 * @return void
	 * @since 7.0
	 */
	public function init() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'rest_pre_dispatch', [ $this, 'maybe_set_template_schema' ], 10, 3 );
	}

	/**
	 * Register the routes for the API
	 *
	 * @return void
	 * @since 7.0
	 */
	public function register_routes() {

		/*
		 * Routes to list all form PDF settings, or create a new one
		 */
		register_rest_route(
			static::NAMESPACE,
			static::$endpoints['pdf-settings'],
			[
				'args'   => [
					'form' => [
						'description'       => __( 'The unique identifier for the Gravity Forms form.', 'gravity-pdf' ),
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => [ $this, 'check_form_is_valid' ],
					],
				],

				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => array_merge(
						$this->get_collection_params(),
						[
							'entry' => [
								'description'       => __( 'The unique identifier for the Gravity Forms entry. Include to filter out PDFs that are not active for the entry.', 'gravity-pdf' ),
								'type'              => 'integer',
								'required'          => false,
								'validate_callback' => [ $this, 'check_entry_is_valid' ],
							],
						]
					),
				],

				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				],

				'schema' => [ $this, 'get_public_item_schema' ],
			],
			true
		);

		/*
		 * Routes to handle read/update/delete of individual form PDF settings
		 */
		register_rest_route(
			static::NAMESPACE,
			static::$endpoints['pdf-settings-item'],
			[
				'args'   => [
					'form' => [
						'description'       => __( 'The unique identifier for the Gravity Forms form.', 'gravity-pdf' ),
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => [ $this, 'check_form_is_valid' ],
					],

					'pdf'  => [
						'description'       => __( 'The identifier for the PDF', 'gravity-pdf' ),
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => function( $param, $request ) {
							$pdf = GPDFAPI::get_pdf( $request->get_param( 'form' ), $param );

							if ( ! is_wp_error( $pdf ) ) {
								return true;
							}

							$pdfs = GPDFAPI::get_form_pdfs( $request->get_param( 'form' ) );

							/* translators: 1: Parameter, 2: List of valid values. */

							return new WP_Error( 'rest_not_in_enum', wp_sprintf( __( '%1$s is not one of %2$l.', 'default' ), $param, ! is_wp_error( $pdfs ) ? array_keys( $pdfs ) : '' ) );
						},
					],
				],

				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
					'args'                => [
						'context' => $this->get_context_param( [ 'default' => 'edit' ] ),
					],
				],

				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				],

				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'delete_item_permissions_check' ],
				],

				'schema' => [ $this, 'get_public_item_schema' ],
			],
			true
		);

		/* Get the template schema */
		register_rest_route(
			static::NAMESPACE,
			static::$endpoints['template-schema'],
			[
				'args' => [
					'form'     => [
						'description'       => __( 'The unique identifier for the Gravity Forms form.', 'gravity-pdf' ),
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => [ $this, 'check_form_is_valid' ],
					],

					'template' => [
						'description' => __( 'A PDF template installed on the website.', 'gravity-pdf' ),
						'type'        => 'string',
						'required'    => true,
						'enum'        => $this->misc->flatten_array( $this->templates->get_all_templates_by_group() ),
					],

					'context'  => $this->get_context_param( [ 'default' => 'edit' ] ),
				],

				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_schema_for_template' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
				],
			],
			true
		);
	}

	/**
	 * Each template has different schema. For requests to this namespace automatically set the template schema before the API call is processed
	 *
	 * @param mixed $results
	 * @param WP_REST_Server $server
	 * @param WP_REST_Request $request
	 *
	 * @return mixed
	 *
	 * @since 7.0
	 */
	public function maybe_set_template_schema( $results, $server, $request ) {
		/* Look for current endpoints */
		if ( strpos( $request->get_route(), '/' . static::get_route_basepath() ) !== 0 ) {
			return $results;
		}

		/* Match request to handler and set the URL params (which is normally done after this hook) */
		$path = $request->get_route();
		foreach ( static::$endpoints as $route ) {
			$match = preg_match( '@^/' . static::NAMESPACE . $route . '$@i', $path, $matches );

			if ( ! $match ) {
				continue;
			}

			$args = [];
			foreach ( $matches as $param => $value ) {
				if ( ! is_int( $param ) ) {
					$args[ $param ] = $value;
				}
			}

			$request->set_url_params( $args );

			break;
		}

		/*
		 * Make data available in appropriate superglobals
		 * This allows code to register template settings which rely on knowing the form
		 */
		$data = [
			'form' => (int) $request->get_param( 'form' ),
		];

		switch ( $request->get_method() ) {
			case 'GET':
				foreach ( $data as $key => $value ) {
					if ( ! empty( $value ) ) {
						$_GET[ $key ]     = $value;
						$_REQUEST[ $key ] = $value;
					}
				}
				break;

			case 'POST':
			case 'PATCH':
			case 'PUT':
				foreach ( $data as $key => $value ) {
					if ( ! empty( $value ) ) {
						$_POST[ $key ]    = $value;
						$_REQUEST[ $key ] = $value;
					}
				}
				break;
		}

		$this->schema = null;

		/* If template is known reregister the schema */
		$template = $request->get_param( 'template' );
		if ( ! empty( $template ) ) {
			$this->get_template_schema( sanitize_html_class( $template ) );
		}

		$this->register_routes();

		return $results;
	}

	/**
	 * @return string
	 *
	 * @since 7.0
	 */
	public static function get_route_basepath() {
		return static::NAMESPACE . static::API_BASE;
	}

	/**
	 * Check if the form is valid
	 *
	 * @param int $form_id
	 *
	 * @return true|WP_Error
	 *
	 * @since 7.0.0
	 */
	public function check_form_is_valid( $form_id ) {
		if ( is_array( $this->gform->get_form( $form_id ) ) ) {
			return true;
		}

		return new WP_Error(
			'rest_invalid_param',
			sprintf( __( 'Invalid form ID %d provided.', 'gravity-pdf' ), $form_id ),
			[ 'status' => 400 ]
		);
	}

	/**
	 * Check if the entry is valid
	 *
	 * @param int $entry_id
	 * @param WP_REST_Request $request
	 *
	 * @return true|WP_Error
	 *
	 * @since 7.0.0
	 */
	public function check_entry_is_valid( $entry_id, $request ) {
		$entry = \GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) ) {
			return new WP_Error(
				'rest_invalid_param',
				sprintf( __( 'Invalid entry ID %d provided.', 'gravity-pdf' ), $entry_id ),
				[ 'status' => 400 ]
			);
		}

		if ( (int) ( $entry['form_id'] ?? 0 ) !== (int) $request->get_param( 'form' ) ) {
			return new WP_Error(
				'rest_invalid_param',
				sprintf( __( 'Entry ID %d is not associated with the current form.', 'gravity-pdf' ), $entry_id ),
				[ 'status' => 400 ]
			);
		}

		return true;
	}
	/**
	 * Permissions check for getting all form PDFs
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has read access, otherwise WP_Error object.
	 *
	 * @since 7.0.0
	 */
	public function get_items_permissions_check( $request ) {
		return $this->get_item_permissions_check( $request );
	}

	/**
	 * Retrieves all PDFs for a form
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 *
	 * @since 7.0.0
	 */
	public function get_items( $request ) {
		$entry_id = $request->get_param( 'entry' );
		if ( ! empty( $entry_id ) ) {
			$pdfs = \GPDFAPI::get_entry_pdfs( $entry_id );
		} else {
			$pdfs = \GPDFAPI::get_form_pdfs( $request->get_param( 'form' ) );
		}

		if ( is_wp_error( $pdfs ) ) {
			$pdfs->add_data( [ 'status' => 400 ] );

			return $pdfs;
		}

		$output = [];
		foreach ( $pdfs as $pdf ) {
			$this->get_template_schema( $pdf['template'] );
			$request->set_param( 'pdf', $pdf['id'] );

			$output[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $pdf, $request ) );
		}

		return rest_ensure_response( $output );
	}

	/**
	 * Checks if a given request has access to read a PDF.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has read access for the item, otherwise WP_Error object.
	 *
	 * @since 7.0.0
	 */
	public function get_item_permissions_check( $request ) {
		if ( $this->gform->has_capability( 'gravityforms_view_settings' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_cannot_view',
			__( 'Sorry, you do not have access to this endpoint.', 'gravity-pdf' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}

	/**
	 * Retrieves a single PDF
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 *
	 * @since 7.0.0
	 */
	public function get_item( $request ) {
		$pdf = \GPDFAPI::get_pdf( $request->get_param( 'form' ), $request->get_param( 'pdf' ) );
		if ( is_wp_error( $pdf ) ) {
			return $pdf;
		}

		$this->get_template_schema( $pdf['template'] );

		$response = rest_ensure_response( $this->prepare_item_for_response( $pdf, $request ) );

		return $response;
	}

	/**
	 * Checks if a given request has access to create PDFs
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has access to create items, WP_Error object otherwise.
	 *
	 * @since 7.0.0
	 */
	public function create_item_permissions_check( $request ) {
		if ( $this->gform->has_capability( 'gravityforms_edit_settings' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden_context',
			__( 'Sorry, you do not have access to this endpoint.', 'gravity-pdf' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}

	/**
	 * Creates a single PDF for a form.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 *
	 * @since 7.0.0
	 */
	public function create_item( $request ) {
		if ( ! empty( $request->get_param( 'pdf' ) ) ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'Cannot create existing PDF.', 'gravity-pdf' ),
				[ 'status' => 400 ]
			);
		}

		$form_id = $request->get_param( 'form' );

		$this->get_template_schema( $request->get_param( 'template' ) );

		$pdf_id = \GPDFAPI::add_pdf( $form_id, $this->prepare_item_for_database( $request ) );
		if ( $pdf_id === false ) {
			return new WP_Error(
				'gfpdf_form_settings_rest_pdf_creation_error',
				__( 'Cannot create PDF.', 'gravity-pdf' ),
				[ 'status' => 500 ]
			);
		}

		$pdf = \GPDFAPI::get_pdf( $form_id, $pdf_id );
		if ( is_wp_error( $pdf ) ) {
			/*
			 * The PDF was successfully created in the last step and should be retrievable.
			 * That it could not be retrieved suggests a database problem, and why the 500
			 * server error.
			 */
			$pdf->add_data( [ 'status' => 500 ] );

			return $pdf;
		}

		do_action( 'gfpdf_form_settings_rest_insert_pdf', $pdf, $request, true );

		$this->update_additional_fields_for_object( $pdf, $request );
		$request->set_param( 'context', 'edit' );

		do_action( 'gfpdf_form_settings_rest_after_insert_pdf', $pdf, $request, true );

		$response = $this->prepare_item_for_response( $pdf, $request );
		$response = rest_ensure_response( $response );

		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%d/%s', static::get_route_basepath(), $form_id, $pdf_id ) ) );

		return $response;
	}

	/**
	 * Checks if a given request has access to update a PDF.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has access to update the item, WP_Error object otherwise.
	 *
	 * @since 7.0.0
	 */
	public function update_item_permissions_check( $request ) {
		return $this->create_item_permissions_check( $request );
	}

	/**
	 * Updates a single PDF.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 *
	 * @since 7.0.0
	 */
	public function update_item( $request ) {
		$form_id = $request->get_param( 'form' );
		$pdf_id  = $request->get_param( 'pdf' );
		$pdf     = \GPDFAPI::get_pdf( $form_id, $pdf_id );
		if ( is_wp_error( $pdf ) ) {
			$pdf->add_data( [ 'status' => 410 ] );

			return $pdf;
		}

		$id = $pdf['id'];

		$this->get_template_schema( $request->get_param( 'template' ) ?? $pdf['template'] );

		$database_pdf = $this->prepare_item_for_database( $request );

		/* Ensure we're operating on the original PDF */
		$database_pdf['id'] = $id;

		$results = \GPDFAPI::update_pdf( $form_id, $pdf_id, $database_pdf );
		if ( ! $results ) {
			return new \WP_Error(
				'gfpdf_form_settings_rest_cannot_update_pdf',
				__( 'The PDF cannot be updated.', 'gravity-pdf' ),
				[ 'status' => 500 ]
			);
		}

		$pdf = \GPDFAPI::get_pdf( $form_id, $pdf_id );

		do_action( 'gfpdf_form_settings_rest_update_pdf', $pdf, $request, false );

		$this->update_additional_fields_for_object( $pdf, $request );
		$request->set_param( 'context', 'edit' );

		do_action( 'gfpdf_form_settings_rest_after_update_pdf', $pdf, $request, false );

		$response = $this->prepare_item_for_response( $pdf, $request );
		$response = rest_ensure_response( $response );

		return $response;
	}

	/**
	 * Checks if a given request has access delete a PDF
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has access to delete the item, WP_Error object otherwise.
	 *
	 * @since 7.0.0
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->create_item_permissions_check( $request );
	}

	/**
	 * Deletes a single PDF
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 *
	 * @since 7.0.0
	 */
	public function delete_item( $request ) {
		$pdf = \GPDFAPI::get_pdf( $request->get_param( 'form' ), $request->get_param( 'pdf' ) );
		if ( is_wp_error( $pdf ) ) {
			$pdf->add_data( [ 'status' => 410 ] );

			return $pdf;
		}

		$request->set_param( 'context', 'edit' );
		$this->get_template_schema( $pdf['template'] );

		$previous = $this->prepare_item_for_response( $pdf, $request );

		$result = \GPDFAPI::delete_pdf( $request->get_param( 'form' ), $request->get_param( 'pdf' ) );
		if ( ! $result ) {
			return new \WP_Error(
				'gfpdf_form_settings_rest_cannot_delete_pdf',
				__( 'The PDF cannot be deleted.', 'gravity-pdf' ),
				[ 'status' => 500 ]
			);
		}

		$response = new \WP_REST_Response();
		$response->set_data(
			[
				'deleted'  => true,
				'previous' => $previous->get_data(),
			]
		);

		do_action( 'gfpdf_form_settings_rest_delete_pdf', $pdf, $response, $request );

		return $response;
	}

	/**
	 * Prepares a single PDF output for response.
	 *
	 * @param array            $item    PDF object.
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response Response object.
	 *
	 * @since 7.0.0
	 */
	public function prepare_item_for_response( $item, $request ) {
		/* Restores the more descriptive, specific name for use within this method (PHP 8 fix). */
		$pdf    = $item;
		$fields = $this->get_fields_for_response( $request );
		$schema = $this->get_item_schema();

		$data = [];

		/* Always include the PDF ID */
		$data['id'] = $pdf['id'];

		/* Dynamically process schema data */
		foreach ( $fields as $id ) {
			/* Skip if $id not found in schema */
			if ( ! isset( $schema['properties'][ $id ] ) ) {
				continue;
			}

			/* Skip if $id is not a requested field */
			if ( ! rest_is_field_included( $id, $fields ) ) {
				continue;
			}

			$property = $schema['properties'][ $id ];
			$value    = $pdf[ $id ] ?? $property['default'] ?? '';

			/* Handle arrays */
			if ( $this->has_property_type( 'array', $property['type'] ) ) {
				$data[ $id ] = is_array( $value ) ? array_values( $value ) : [];

				continue;
			}

			/* Convert integers/numbers into actual int/float values */
			if ( $this->has_property_type( [ 'integer', 'number' ], $property['type'] ) ) {
				$data[ $id ] = is_numeric( $value ) ? +$value : 0;

				continue;
			}

			/* Handle toggle switch */
			if ( $this->has_property_type( 'boolean', $property['type'] ) ) {
				$data[ $id ] = in_array( $value, [ 'Enable', 'Yes', '1', 1, true ], true );

				continue;
			}

			/* Let the field format its response */
			if ( isset( $property['arg_options']['get_callback'] ) ) {
				$value = call_user_func( $property['arg_options']['get_callback'], $item, $id, $request, $this->get_object_type() );
			}

			$data[ $id ] = $value;
		}

		/* Add form ID */
		if ( rest_is_field_included( 'form', $fields ) ) {
			$data['form'] = +$request->get_param( 'form' );
		}

		/* Fix up the order of data to match the schema */
		$reordered = [];
		foreach ( array_keys( $schema['properties'] ) as $property_id ) {
			if ( array_key_exists( $property_id, $data ) ) {
				$reordered[ $property_id ] = $data[ $property_id ];
				unset( $data[ $property_id ] );
			}
		}

		/* Add any extra values not found in the schema */
		$reordered = array_merge( $reordered, $data );

		$data = $reordered;

		$context = ! empty( $request->get_param( 'context' ) ) ? $request->get_param( 'context' ) : 'edit';

		$data = $this->add_additional_fields_to_object( $data, $request );

		/* Get the links before the data is filtered */
		$links = $this->prepare_links( $data, $request );

		$data     = $this->filter_response_by_context( $data, $context );
		$data     = apply_filters( 'gfpdf_form_settings_rest_prepared_response', $data, $request );
		$response = rest_ensure_response( $data );

		if ( rest_is_field_included( '_links', $fields ) || rest_is_field_included( '_embedded', $fields ) ) {
			$response->add_links( $links );
		}

		return $response;
	}

	/**
	 * Prepares a single PDF for creation or update.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return array PDF settings
	 *
	 * @since 7.0.0
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_pdf = [];

		/* Updating an existing PDF, save to $prepared_pdf */
		if ( $request->get_param( 'pdf' ) ) {
			$existing_pdf = \GPDFAPI::get_pdf( $request->get_param( 'form' ), $request->get_param( 'pdf' ) );
			if ( is_wp_error( $existing_pdf ) ) {
				$existing_pdf->add_data( [ 'status' => 410 ] );

				return $existing_pdf;
			}

			$prepared_pdf = $existing_pdf;
		}

		$schema = $this->get_item_schema();

		/* Sanitize fields */
		foreach ( $schema['properties'] as $id => $property ) {
			if ( ! empty( $property['readonly'] ) ) {
				continue;
			}

			/* skip over if updating a PDF and no data is being set */
			if ( $request->get_param( 'pdf' ) && ! array_key_exists( $id, $request->get_params() ) ) {
				continue;
			}

			$value = $request->get_param( $id ) ?? '';

			/* Handle arrays */
			if ( $this->has_property_type( 'array', $property['type'] ) ) {
				/* if the value isn't an array, set to an empty one */
				if ( ! is_array( $value ) ) {
					$value = [];
				}

				/* if not an empty array, use the same keys as values */
				if ( ! empty( $value ) ) {
					$value = array_combine( $value, $value );
				}
			}

			/* Handle Toggle values */
			if ( $this->has_property_type( 'boolean', $property['type'] ) && ( $property['format'] ?? '' ) === 'yes-no' ) {
				$value = $value === true ? 'Yes' : 'No';
			}

			/* Handle checkbox values */
			if ( $this->has_property_type( 'boolean', $property['type'] ) && ( $property['format'] ?? '' ) === 'checkbox' ) {
				$value = $value === true ? '1' : '';
			}

			$prepared_pdf[ $id ] = $value;
		}

		/* Let fields prepare themselves for the database */
		foreach ( $schema['properties'] as $id => $property ) {
			if ( isset( $property['arg_options']['update_callback'] ) ) {
				$prepared_pdf[ $id ] = call_user_func( $property['arg_options']['update_callback'], $prepared_pdf, $id, $request, $this->get_object_type() );
			}
		}

		return apply_filters( 'gfpdf_form_settings_rest_pre_insert_pdf', $prepared_pdf, $request );
	}

	/**
	 * Retrieves the query params for collections.
	 *
	 * @return array Collection parameters.
	 *
	 * @since 7.0.0
	 */
	public function get_collection_params() {
		return [
			'context' => $this->get_context_param( [ 'default' => 'edit' ] ),
		];
	}

	/**
	 * Retrieves the PDF schema, conforming to JSON Schema.
	 *
	 * @return array Item schema data.
	 *
	 * @since 7.0.0
	 */
	public function get_item_schema() {
		/* returned cached schema + additional fields */
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = [
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'gravity-pdf',
			'description'          => __( 'Individual Form PDF Settings for Gravity PDF.', 'gravity-pdf' ),
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => [
				'id'     => [
					'description' => __( 'Unique identifier for the PDF.', 'gravity-pdf' ),
					'type'        => 'string',
					'context'     => [ 'edit' ],
					'readonly'    => true,
					'pattern'     => '[a-fA-F0-9]{13}',
				],

				'form'   => [
					'description' => __( 'The Gravity Forms ID the PDF is configured on.', 'gravity-pdf' ),
					'type'        => 'integer',
					'context'     => [ 'edit' ],
					'readonly'    => true,
				],

				'active' => [
					'description' => __( 'The current state of the PDF.', 'gravity-pdf' ),
					'type'        => 'boolean',
					'default'     => true,
					'context'     => [ 'edit' ],
				],
			],
		];

		$all_gravitypdf_settings = $this->options->get_registered_fields();

		$schema['properties'] = array_merge(
			$schema['properties'],
			/* General-specific fields */
			$this->get_section_schema( $all_gravitypdf_settings['form_settings'] ?? [], 'general' ),
			/* Appearance-specific fields */
			$this->get_section_schema( $all_gravitypdf_settings['form_settings_appearance'] ?? [], 'appearance' ),
			/* Template-specific fields */
			$this->get_section_schema( $all_gravitypdf_settings['form_settings_custom_appearance'] ?? [], 'template' ),
			/* Advanced/Security fields */
			$this->get_section_schema( $all_gravitypdf_settings['form_settings_advanced'] ?? [], 'advanced' ),
		);

		$this->schema = apply_filters( 'gfpdf_form_settings_rest_schema', $schema );

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Get Rest API schema for specific form/template
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 *
	 * @since 7.0
	 */
	public function get_schema_for_template( $request ) {
		$context  = $request->get_param( 'context' );
		$template = $request->get_param( 'template' );

		$schema = $this->get_template_schema( $template );

		if ( empty( $context ) ) {
			return rest_ensure_response( $schema );
		}

		/* filter out properties that don't match the context */
		foreach ( $schema['properties'] as $id => $property ) {
			if ( empty( $property['context'] ) || ! in_array( $context, $property['context'], true ) ) {
				unset( $schema['properties'][ $id ] );
			}
		}

		return rest_ensure_response( $schema );
	}

	/**
	 * Get specific template schema
	 *
	 * @param string $template
	 *
	 * @return array
	 *
	 * @since 7.0
	 */
	public function get_template_schema( $template ) {
		$current_template = function( $item ) use ( $template ) {
			return $template;
		};

		add_filter( 'gfpdf_template_for_current_page', $current_template );

		$this->schema = null;
		$schema       = $this->get_item_schema();

		remove_filter( 'gfpdf_template_for_current_page', $current_template );

		return $schema;
	}

	/**
	 * Get the schema for a specific Template PDF
	 *
	 * @param array $settings
	 * @param string $group
	 *
	 * @return array
	 *
	 * @since 7.0.0
	 */
	protected function get_section_schema( $settings, $group ) {
		$generic_description = __( 'Content for the specific property.', 'gravity-pdf' );

		$schema = [];
		foreach ( $settings as $id => $value ) {
			/* Skip fields that should be excluded */
			if ( isset( $value['show_in_rest'] ) && $value['show_in_rest'] === false ) {
				continue;
			}

			/* if schema defined with a different type set it now */
			if ( isset( $value['rest_type'] ) ) {
				$value['type'] = $value['rest_type'];
			}

			$default = $value['std'] ?? null;

			$schema[ $id ] = [
				'description' => ! empty( $value['desc'] ) ? wp_strip_all_tags( $value['desc'] ) : $generic_description,
				'type'        => 'string',
				'default'     => $default,
				'context'     => [ 'edit', $group ],
				'arg_options' => [
					'sanitize_callback' => function( $param, $request, $key ) {
						return is_array( $param ) ? array_map( 'sanitize_text_field', $param ) : sanitize_text_field( $param );
					},

					'validate_callback' => 'rest_validate_request_arg',
				],
			];

			switch ( $value['type'] ) {
				case 'number':
					$schema[ $id ]['type'] = 'number';

					if ( isset( $value['min'] ) ) {
						$schema[ $id ]['minimum'] = $value['min'];
					}

					if ( isset( $value['max'] ) ) {
						$schema[ $id ]['maximum'] = $value['max'];
					}

					$schema[ $id ]['arg_options']['sanitize_callback'] = 'rest_sanitize_request_arg';

					/* If there's a default number value cast to int/float */
					if ( ! is_null( $default ) ) {
						$schema[ $id ]['default'] = is_numeric( $default ) ? $default + 0 : null;
					}

					break;

				case 'multicheck':
					$schema[ $id ]['type']                             = 'array';
					$schema[ $id ]['items']                            = [
						'type' => 'string',

						/* cast all values to a string to prevent a type validation error */
						'enum' => array_map( 'strval', $this->misc->flatten_array( $value['options'] ?? [] ) ),
					];
					$schema[ $id ]['arg_options']['sanitize_callback'] = 'rest_sanitize_request_arg';
					break;

				case 'radio':
				case 'select':
					$schema[ $id ]['arg_options']['sanitize_callback'] = 'rest_sanitize_request_arg';

					/* cast all values to a string to prevent a type validation error */
					$enum = array_map( 'strval', $this->misc->flatten_array( $value['options'] ?? [] ) );

					if ( ! empty( $value['multiple'] ) ) {
						/* multiselect field */
						$schema[ $id ]['type']  = 'array';
						$schema[ $id ]['items'] = [
							'type' => 'string',
							'enum' => $enum,
						];
					} else {
						$schema[ $id ]['enum'] = $enum;
					}
					break;

				case 'textarea':
					$schema[ $id ]['format'] = 'textarea';
					break;

				case 'rich_editor':
					$schema[ $id ]['format']                           = 'rich_text';
					$schema[ $id ]['arg_options']['sanitize_callback'] = function( $param, $request, $key ) {
						return $this->sanitize_rich_text( $param );
					};
					break;

				case 'color':
					$schema[ $id ]['format']                           = 'hex-color';
					$schema[ $id ]['arg_options']['sanitize_callback'] = 'rest_sanitize_request_arg';
					break;

				case 'checkbox':
					$schema[ $id ]['type']                             = 'boolean';
					$schema[ $id ]['format']                           = 'checkbox';
					$schema[ $id ]['default']                          = in_array( $default, [ 'Yes', '1', 1, 'true', true ], true );
					$schema[ $id ]['arg_options']['sanitize_callback'] = 'rest_sanitize_request_arg';
				break;

				case 'toggle':
					$schema[ $id ]['type']                             = 'boolean';
					$schema[ $id ]['format']                           = 'yes-no';
					$schema[ $id ]['default']                          = in_array( $default, [ 'Yes', '1', 'true', true ], true );
					$schema[ $id ]['arg_options']['sanitize_callback'] = 'rest_sanitize_request_arg';

					break;
			}

			/* Mark field as required */
			if ( isset( $value['required'] ) ) {
				$schema[ $id ]['required'] = $value['required'];
			}

			/* Merge any REST API-specific settings, if exists */
			if ( isset( $value['schema'] ) && is_array( $value['schema'] ) ) {
				$schema[ $id ] = array_replace_recursive( $schema[ $id ], $value['schema'] );

				/* don't merge any default array values */
				if ( isset( $value['schema']['default'] ) ) {
					$schema[ $id ]['default'] = $value['schema']['default'];
				}
			}
		}

		return $schema;
	}

	/**
	 * Add links for PDF setting
	 *
	 * @param array $data
	 * @param WP_REST_Request $request
	 *
	 * @return array[]
	 *
	 * @since 7.0.0
	 */
	protected function prepare_links( $data, $request ) {

		$form_id  = $data['form'] ?? $request->get_param( 'form' );
		$pdf_id   = $data['id'] ?? $request->get_param( 'pdf' );
		$entry_id = $data['entry'] ?? $request->get_param( 'entry' );

		if ( empty( $form_id ) || empty( $pdf_id ) ) {
			return [];
		}

		$links = [
			'self'       => [
				'href' => rest_url( sprintf( '%s/%d/%s', static::get_route_basepath(), $form_id, $pdf_id ) ),
			],
			'admin'      => [
				'href' => admin_url( sprintf( 'admin.php?page=gf_edit_forms&view=settings&subview=PDF&id=%d&pid=%s', $form_id, $pdf_id ) ),
			],
			'collection' => [
				'href' => rest_url( sprintf( '%s/%d', static::get_route_basepath(), $form_id ) ),
			],
		];

		if ( ! class_exists( 'GFWebAPI' ) ) {
			return $links;
		}

		/* If GF REST API enabled, include direct links to form endpoint */
		$gfwebapi = \GFWebAPI::get_instance();
		if ( ! $gfwebapi->is_v2_enabled( $gfwebapi->get_plugin_settings() ) ) {
			return $links;
		}

		$links['form'] = [
			'href'       => rest_url( sprintf( 'gf/v2/forms/%d', $form_id ) ),
			'embeddable' => true,
		];

		if ( ! empty( $entry_id ) ) {
			$links['entry'] = [
				'href'       => rest_url( sprintf( 'gf/v2/entries/%d', $entry_id ) ),
				'embeddable' => true,
			];
		}

		return $links;
	}

	/**
	 * Sanitize Rich Text fields
	 *
	 * So merge tags can be used inside HTML attributes (like src="{tag:20}"), but the rich editor content
	 * can still be sanitized, we'll replace the opening { tag with a valid protocol that isn't very common.
	 * Doing this ensures the merge tag does not get malformed during initial sanitizing. Once the merge tags
	 * are replaced, the values will be sanitized again before display.
	 *
	 * @param string $html
	 *
	 * @return string
	 *
	 * @since 7.0.0
	 */
	protected function sanitize_rich_text( $html ) {
		if ( strpos( $html, 'telnet://{' ) !== false ) {
			return Kses::parse( $html );
		}

		$pattern = '([^{]*?})';
		$html    = preg_replace( "/=\"\{$pattern\"/mi", '="telnet://$1"', $html );
		$html    = Kses::parse( $html );
		$html    = preg_replace( "/=\"telnet:\/\/$pattern\"/mi", '="{$1"', $html );

		return $html;
	}

	/**
	 * Check if the schema item is of a specific type
	 *
	 * @param string|array $type
	 * @param string|array $property
	 *
	 * @return bool
	 */
	protected function has_property_type( $type, $property ) {
		if ( ! is_array( $property ) ) {
			$property = [ $property ];
		}

		if ( ! is_array( $type ) ) {
			$type = [ $type ];
		}

		return count( array_intersect( $property, $type ) ) > 0;
	}
}
