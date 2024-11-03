<?php

namespace GFPDF\Tests;

use GFPDF\Rest\Rest_Form_Settings;
use WP_REST_Request;

/**
 *
 * @package     Gravity PDF
 * @copyright   Copyright (c) 2024, Blue Liquid Designs
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

/**
 * @group api
 */
class Test_Rest_Form_Settings extends Test_Rest {

	/**
	 * @var Rest_Form_Settings
	 */
	protected $api;

	function set_up() {
		global $gfpdf;

		$this->api = new Rest_Form_Settings( $gfpdf->options, $gfpdf->gform, $gfpdf->misc, $gfpdf->templates );

		parent::set_up();
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();

		foreach ( $this->api::$endpoints as $route ) {
			$this->assertArrayHasKey( '/' . $this->api::NAMESPACE . $route, $routes );
		}
	}

	public function test_context_param() {
		wp_set_current_user( self::$admin_id );

		/* Collection */
		$request  = new WP_REST_Request( 'OPTIONS', '/gravity-pdf/v1/form/' . $this->form_id );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 'edit', $data['endpoints'][0]['args']['context']['default'] );

		$contexts = [ 'advanced', 'appearance', 'edit', 'general', 'template' ];

		$enum = $data['endpoints'][0]['args']['context']['enum'];
		sort( $enum );
		$this->assertSame( $contexts, $enum );

		/* Single */
		$pdf_id   = $this->factory->pdf->create();
		$request  = new WP_REST_Request( 'OPTIONS', '/gravity-pdf/v1/form/' . $this->form_id . '/' . $pdf_id );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 'edit', $data['endpoints'][0]['args']['context']['default'] );

		$enum = $data['endpoints'][0]['args']['context']['enum'];
		sort( $enum );
		$this->assertSame( $contexts, $enum );
	}

	public function test_get_items() {
		$this->factory->pdf->create_many( 5, [
			'template' => 'rubix',
		] );

		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/gravity-pdf/v1/form/' . $this->form_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 5, $data );
		$this->test_for_pdf_settings( $data[0] );
	}

	public function test_get_items_for_entry() {
		$this->factory->pdf->create_many( 2, [
			'template' => 'rubix',
		] );

		$this->factory->pdf->create( [ 'active' => false ] );
		$this->factory->pdf->create( [
			'conditional'      => 1,
			'conditionalLogic' => [
				'actionType' => 'show',
				'logicType'  => 'all',
				'rules'      => [
					[
						'fieldId'  => 1,
						'operator' => 'is',
						'value'    => 'something',
					],
				],
			],
		] );

		/* Test we get all PDFs back */
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/gravity-pdf/v1/form/' . $this->form_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 4, $data );

		/* Test we only get 2 PDFs back */
		$entry_id = $this->factory->entry->create( [ 'form_id' => $this->form_id ] );
		$request->set_query_params( [
			'entry' => $entry_id,
		] );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 2, $data );

		$this->assertArrayHasKey( 'entry', $data[0]['_links'] );

		/* Test we get 3 PDFs back */
		$entry_id = $this->factory->entry->create( [ 'form_id' => $this->form_id, '1' => 'something' ] );
		$request->set_query_params( [
			'entry' => $entry_id,
		] );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 3, $data );
	}

	/**
	 * @param $args
	 *
	 * @return void
	 */
	protected function test_for_pdf_settings( $args ): void {
		$this->assertArrayHasKey( 'form', $args );
		$this->assertArrayHasKey( 'active', $args );
		$this->assertArrayHasKey( 'name', $args );
		$this->assertArrayHasKey( 'filename', $args );
		$this->assertArrayHasKey( 'notification', $args );
		$this->assertArrayHasKey( 'conditionalLogic', $args );
		$this->assertArrayHasKey( 'font', $args );
		$this->assertArrayHasKey( 'font_size', $args );
		$this->assertArrayHasKey( 'font_colour', $args );
		$this->assertArrayHasKey( 'rtl', $args );
		$this->assertArrayHasKey( 'format', $args );
		$this->assertArrayHasKey( 'security', $args );
		$this->assertArrayHasKey( 'privileges', $args );
		$this->assertArrayHasKey( 'password', $args );
		$this->assertArrayHasKey( 'master_password', $args );
		$this->assertArrayHasKey( 'image_dpi', $args );
		$this->assertArrayHasKey( 'public_access', $args );
		$this->assertArrayHasKey( 'restrict_owner', $args );
		$this->assertArrayHasKey( 'template', $args );
		$this->assertArrayHasKey( 'pdf_size', $args );
		$this->assertArrayHasKey( 'custom_pdf_size', $args );
		$this->assertArrayHasKey( 'orientation', $args );
		$this->assertArrayHasKey( 'rubix_container_background_colour', $args );
		$this->assertArrayHasKey( 'show_form_title', $args );
		$this->assertArrayHasKey( 'show_page_names', $args );
		$this->assertArrayHasKey( 'show_html', $args );
		$this->assertArrayHasKey( 'show_section_content', $args );
		$this->assertArrayHasKey( 'enable_conditional', $args );
		$this->assertArrayHasKey( 'show_empty', $args );
		$this->assertArrayHasKey( 'background_color', $args );
		$this->assertArrayHasKey( 'background_image', $args );
		$this->assertArrayHasKey( 'header', $args );
		$this->assertArrayHasKey( 'first_header', $args );
		$this->assertArrayHasKey( 'footer', $args );
		$this->assertArrayHasKey( 'first_footer', $args );
	}

	public function test_get_items_with_filters() {
		$this->factory->pdf->create( [ 'template' => 'rubix' ] );
		$this->factory->pdf->create( [ 'template' => 'focus-gravity' ] );
		$this->factory->pdf->create( [ 'template' => 'zadani' ] );
		$this->factory->pdf->create( [ 'template' => 'blank-slate' ] );

		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'GET', '/gravity-pdf/v1/form/' . $this->form_id );

		/* Filter by context */
		$request->set_query_params( [ 'context' => 'template' ] );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertCount( 4, $data );

		$this->assertArrayNotHasKey( 'name', $data[0] );
		$this->assertArrayHasKey( 'rubix_container_background_colour', $data[0] );

		$this->assertArrayNotHasKey( 'name', $data[1] );
		$this->assertArrayHasKey( 'focusgravity_accent_colour', $data[1] );

		$this->assertArrayNotHasKey( 'name', $data[2] );
		$this->assertArrayHasKey( 'zadani_border_colour', $data[2] );

		$this->assertArrayNotHasKey( 'name', $data[3] );
		$this->assertArrayHasKey( 'header', $data[3] );

		/* Filter using special _filter query param */
		$request->set_query_params( [ '_fields' => 'name,filename,template' ] );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertCount( 4, $data );
		$this->assertCount( 4, $data[0] );
		$this->assertCount( 4, $data[1] );

		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertArrayHasKey( 'name', $data[0] );
		$this->assertArrayHasKey( 'filename', $data[0] );
		$this->assertArrayHasKey( 'template', $data[0] );
	}

	public function test_get_items_invalid_permission() {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/gravity-pdf/v1/form/' . $this->form_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );

		wp_set_current_user( self::$editor_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );

		$GLOBALS['current_user']->add_cap( 'gravityforms_view_settings' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_get_item() {
		$template_pdf_config = [
			'name'                              => 'My First PDF Template',
			'template'                          => 'rubix',
			'filename'                          => 'Filename-{Text:5}',
			'notification'                      => [ '52246fd7af858' ],
			'conditional'                       => '1',
			'conditionalLogic'                  => [
				'actionType' => 'show',
				'logicType'  => 'any',
				'rules'      => [
					[ 'fieldId' => '7', 'operator' => 'is', 'value' => 'Albania' ],
				],
			],
			'pdf_size'                          => 'custom',
			'custom_pdf_size'                   => [ '150', '300', 'millimeters' ],
			'orientation'                       => 'landscape',
			'font'                              => 'dejavusans',
			'font_size'                         => 12,
			'font_colour'                       => '#929292',
			'format'                            => 'PDFA1B',
			'rubix_container_background_colour' => '#1C2C1C',
			'rtl'                               => 'Yes',
			'security'                          => 'Yes',
			'password'                          => 'my password',
			'master_password'                   => 'test',
			'privileges'                        => [ 'copy', 'print' ],
			'image_dpi'                         => '300',
			'show_html'                         => 'Yes',
			'public_access'                     => 'Yes',
			'restrict_owner'                    => 'Yes',
			'background_image'                  => 'http://test.com/image.jpg',
			'header'                            => '<p>This is a header {Single:5}</p>',
			'first_header'                      => 'My First Page Header <img src="{Single:5}" />',
			'footer'                            => 'my footer',
			'first_footer'                      => 'footer on the first page',
		];

		$template_pdf_id = $this->factory->pdf->create( $template_pdf_config );

		wp_set_current_user( self::$admin_id );

		/* Test Template PDF */
		$request = new WP_REST_Request( 'GET', '/gravity-pdf/v1/form/' . $this->form_id . '/' . $template_pdf_id );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->test_for_pdf_settings( $data );

		$this->assertSame( $this->form_id, $data['form'] );
		$this->assertTrue( $data['active'] );
		$this->assertSame( $template_pdf_config['name'], $data['name'] );
		$this->assertSame( $template_pdf_config['filename'], $data['filename'] );
		$this->assertSame( $template_pdf_config['notification'], $data['notification'] );
		$this->assertSame( $template_pdf_config['conditionalLogic'], $data['conditionalLogic'] );
		$this->assertSame( $template_pdf_config['font'], $data['font'] );
		$this->assertEquals( $template_pdf_config['font_size'], $data['font_size'] );
		$this->assertSame( $template_pdf_config['font_colour'], $data['font_colour'] );
		$this->assertTrue( $data['rtl'] );
		$this->assertSame( 'PDFA1B', $data['format'] );
		$this->assertTrue( $data['security'] );
		$this->assertSame( $template_pdf_config['privileges'], $data['privileges'] );
		$this->assertSame( $template_pdf_config['password'], $data['password'] );
		$this->assertSame( $template_pdf_config['master_password'], $data['master_password'] );
		$this->assertEquals( $template_pdf_config['image_dpi'], $data['image_dpi'] );
		$this->assertTrue( $data['public_access'] );
		$this->assertTrue( $data['restrict_owner'] ); /* can't be enabled at the same time as public access */
		$this->assertSame( $template_pdf_config['template'], $data['template'] );
		$this->assertSame( $template_pdf_config['pdf_size'], $data['pdf_size'] );
		$this->assertEquals( $template_pdf_config['custom_pdf_size'][0], $data['custom_pdf_size']['width'] );
		$this->assertEquals( $template_pdf_config['custom_pdf_size'][1], $data['custom_pdf_size']['height'] );
		$this->assertSame( $template_pdf_config['custom_pdf_size'][2], str_replace( [ 'mm', 'in' ], [ 'millimeters', 'inches' ], $data['custom_pdf_size']['unit'] ) );
		$this->assertSame( $template_pdf_config['orientation'], $data['orientation'] );
		$this->assertSame( '#1C2C1C', $data['rubix_container_background_colour'] );
		$this->assertTrue( $data['show_form_title'] );
		$this->assertFalse( $data['show_page_names'] );
		$this->assertTrue( $data['show_html'] );
		$this->assertFalse( $data['show_section_content'] );
		$this->assertFalse( $data['show_empty'] );
		$this->assertTrue( $data['enable_conditional'] );
		$this->assertSame( '#ffffff', $data['background_color'] );
		$this->assertSame( $template_pdf_config['background_image'], $data['background_image'] );
		$this->assertSame( $template_pdf_config['header'], $data['header'] );
		$this->assertSame( $template_pdf_config['first_header'], $data['first_header'] );
		$this->assertSame( $template_pdf_config['footer'], $data['footer'] );
		$this->assertSame( $template_pdf_config['first_footer'], $data['first_footer'] );

		$links = $response->get_links();
		$this->assertArrayHasKey( 'self', $links );
		$this->assertArrayHasKey( 'admin', $links );
		$this->assertArrayHasKey( 'collection', $links );
		$this->assertArrayHasKey( 'form', $links );

		$this->assertStringContainsString( 'admin.php?page=gf_edit_forms&view=settings&subview=PDF&id=', $response->get_links()['admin'][0]['href'] );
	}

	public function test_get_item_invalid_permission() {
		wp_set_current_user( 0 );

		$pdf_id = $this->factory->pdf->create();

		$request  = new WP_REST_Request( 'GET', '/gravity-pdf/v1/form/' . $this->form_id . '/' . $pdf_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );

		wp_set_current_user( self::$editor_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );

		$GLOBALS['current_user']->add_cap( 'gravityforms_view_settings' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_get_item_with_invalid_form() {
		$pdf_id = $this->factory->pdf->create();

		wp_set_current_user( self::$admin_id );

		/* Test with invalid form */
		$request  = new WP_REST_Request( 'GET', '/gravity-pdf/v1/form/4587/' . $pdf_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $data['data']['details']['form']['code'] );
	}

	public function test_get_item_with_invalid_pdf() {
		/* Test with invalid PDF */
		$request  = new WP_REST_Request( 'GET', '/gravity-pdf/v1/form/' . $this->form_id . '/' . uniqid() );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'Invalid parameter(s): pdf', $data['message'] );
	}

	public function test_create_item() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/gravity-pdf/v1/form/' . $this->form_id );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		/* Barebones PDF */
		$request->set_body_params( [
			'name'     => 'Simple PDF',
			'template' => 'rubix',
		] );

		$response = rest_get_server()->dispatch( $request );
		$this->check_pdf( $response );

		/* Complex PDF */
		$request->set_body_params( [
			'name'             => 'Advanced PDF Template',
			'template'         => 'rubix',
			'filename'         => 'Filename-{Text:5}',
			'notification'     => [ '52246fd7af858' ],
			'conditionalLogic' => [
				'actionType' => 'show',
				'logicType'  => 'any',
				'rules'      => [
					[ 'fieldId' => '7', 'operator' => 'is', 'value' => 'Albania' ],
				],
			],
			'pdf_size'         => 'CUSTOM',
			'custom_pdf_size'  => [
				'width'  => '150',
				'height' => '300',
				'unit'   => 'mm',
			],
			'orientation'      => 'landscape',
			'font'             => 'dejavusans',
			'font_size'        => 12,
			'font_colour'      => '#929292',
			'format'           => 'PDFA1B',
			'rtl'              => true,
			'security'         => true,
			'password'         => 'my password',
			'master_password'  => 'test',
			'privileges'       => [ 'copy', 'print' ],
			'image_dpi'        => 300,
			'public_access'    => true,
			'restrict_owner'   => true,

			'rubix_container_background_colour' => '#1C2C1C',
			'background_image'                  => 'http://test.com/image.jpg',
			'header'                            => '<p>This is a header {Single:5}</p>',
			'first_header'                      => 'My First Page Header <img src="{Single:5}" />',
			'footer'                            => 'my footer',
			'first_footer'                      => 'footer on the first page',
		] );

		$response = rest_get_server()->dispatch( $request );
		$this->check_pdf( $response );
	}

	/**
	 * @param \WP_REST_Response $response
	 *
	 * @return void
	 */
	protected function check_pdf( $response ) {
		$this->assertSame( 201, $response->get_status() );
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'Location', $headers );

		$data = $response->get_data();
		$pdf  = \GPDFAPI::get_pdf( $this->form_id, $data['id'] );

		$this->check_template_pdf_data( $pdf, $data, 'edit', $response->get_links() );
	}

	protected function check_template_pdf_data( $pdf, $data, $context, $links ) {
		$this->test_for_pdf_settings( $data );
		$this->check_common_pdf_data( $pdf, $data, $context, $links );

		$this->assertSame( $pdf['pdf_size'], $data['pdf_size'] );
		if ( is_array( $data['custom_pdf_size'] ) ) {
			$this->assertEquals( $pdf['custom_pdf_size'][0], $data['custom_pdf_size']['width'] );
			$this->assertEquals( $pdf['custom_pdf_size'][1], $data['custom_pdf_size']['height'] );
			$this->assertEquals(
				$pdf['custom_pdf_size'][2],
				str_replace(
					[ 'mm', 'in' ],
					[ 'millimeters', 'inches' ],
					$data['custom_pdf_size']['unit']
				)
			);
		}

		$this->assertSame( $pdf['orientation'], $data['orientation'] );

		$this->assertSame( $pdf['rubix_container_background_colour'], $data['rubix_container_background_colour'] );
		$this->assertSame( $pdf['show_form_title'], $data['show_form_title'] ? 'Yes' : 'No' );
		$this->assertSame( $pdf['show_page_names'], $data['show_page_names'] ? 'Yes' : 'No' );
		$this->assertSame( $pdf['show_html'], $data['show_html'] ? 'Yes' : 'No' );
		$this->assertSame( $pdf['show_section_content'], $data['show_section_content'] ? 'Yes' : 'No' );
		$this->assertSame( $pdf['enable_conditional'], $data['enable_conditional'] ? 'Yes' : 'No' );
		$this->assertSame( $pdf['show_empty'], $data['show_empty'] ? 'Yes' : 'No' );
		$this->assertSame( $pdf['background_color'], $data['background_color'] );

		$this->assertSame( $pdf['background_image'], $data['background_image'] );
		$this->assertSame( $pdf['header'], $data['header'] );
		$this->assertSame( $pdf['first_header'], $data['first_header'] );
		$this->assertSame( $pdf['footer'], $data['footer'] );
		$this->assertSame( $pdf['first_footer'], $data['first_footer'] );
	}

	protected function check_common_pdf_data( $pdf, $data, $context, $links ) {
		$this->assertSame( $pdf['id'], $data['id'] );
		$this->assertSame( $pdf['name'], $data['name'] );
		$this->assertSame( $pdf['active'], $data['active'] );

		if ( $context === 'edit' ) {
			$this->assertSame( $pdf['filename'], $data['filename'] );
		} else {
			$this->assertArrayNotHasKey( 'raw', $data['filename'] );
		}

		$this->assertSame( array_values( $pdf['notification'] ), $data['notification'] );

		$this->assertSame( $pdf['conditionalLogic'], $data['conditionalLogic'] );
		$this->assertEquals( $pdf['conditional'] === 'Yes', $data['conditional'] );

		$this->assertSame( $pdf['font'], $data['font'] );
		$this->assertEquals( $pdf['font_size'], $data['font_size'] );
		$this->assertSame( $pdf['font_colour'], $data['font_colour'] );

		$this->assertSame( $pdf['rtl'], $data['rtl'] ? 'Yes' : 'No' );

		$this->assertSame( $pdf['format'], $data['format'] );

		$this->assertSame( $pdf['security'], $data['security'] ? 'Yes' : 'No' );
		$this->assertSame( array_values( $pdf['privileges'] ), $data['privileges'] );
		$this->assertSame( $pdf['password'], $data['password'] );
		$this->assertSame( $pdf['master_password'], $data['master_password'] );

		$this->assertEquals( $pdf['image_dpi'], $data['image_dpi'] );
		$this->assertSame( $pdf['public_access'], $data['public_access'] ? 'Yes' : 'No' );
		$this->assertSame( $pdf['restrict_owner'], $data['restrict_owner'] ? 'Yes' : 'No' );

		if ( $links ) {
			$links = test_rest_expand_compact_links( $links );

			$this->assertSame( $links['self'][0]['href'], rest_url( 'gravity-pdf/v1/form/' . $this->form_id . '/' . $data['id'] ) );
			$this->assertSame( $links['collection'][0]['href'], rest_url( 'gravity-pdf/v1/form/' . $this->form_id ) );
			$this->assertSame( $links['form'][0]['href'], rest_url( 'gf/v2/forms/' . $this->form_id ) );
		}
	}

	public function test_create_item_invalid_permission() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/gravity-pdf/v1/form/' . $this->form_id );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		/* Barebones PDF */
		$request->set_body_params( [
			'name'     => 'Simple PDF',
			'template' => 'rubix',
		] );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );

		wp_set_current_user( self::$editor_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );

		$GLOBALS['current_user']->add_cap( 'gravityforms_view_settings' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );

		$GLOBALS['current_user']->add_cap( 'gravityforms_edit_settings' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
	}

	public function test_create_item_with_existing_pdf() {
		$pdf_id = $this->factory->pdf->create();

		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/gravity-pdf/v1/form/' . $this->form_id );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		/* Barebones PDF */
		$request->set_body_params( [
			'pdf'      => $pdf_id,
			'name'     => 'Simple PDF',
			'template' => 'rubix',
		] );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'Cannot create existing PDF.', $response->get_data()['message'] );
	}

	public function test_update_item() {
		$template_pdf_id = $this->factory->pdf->create( [
			'template'                          => 'rubix',
			'filename'                          => 'Filename-{Text:5}',
			'pdf_size'                          => 'LETTER',
			'rubix_container_background_colour' => '#1C2C1C',
		] );

		wp_set_current_user( self::$admin_id );

		/* Get original PDF */
		$request = new WP_REST_Request( 'GET', '/gravity-pdf/v1/form/' . $this->form_id . '/' . $template_pdf_id );
		$request->set_param( 'context', 'edit' );
		$response      = rest_get_server()->dispatch( $request );
		$original_data = $response->get_data();

		/* Update PDF */
		$request = new WP_REST_Request( 'PUT', '/gravity-pdf/v1/form/' . $this->form_id . '/' . $template_pdf_id );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		/* Override a couple of the settings */
		$request->set_body_params( [
			'filename'                          => 'Zadani Document',
			'pdf_size'                          => 'A3',
			'rubix_container_background_colour' => '#000000',
		] );

		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();

		$this->assertSame( $template_pdf_id, $data['id'] );
		$this->assertSame( 'Zadani Document', $data['filename'] );
		$this->assertSame( 'A3', $data['pdf_size'] );
		$this->assertSame( '#000000', $data['rubix_container_background_colour'] );

		unset( $original_data['filename'], $original_data['pdf_size'], $original_data['rubix_container_background_colour'] );
		unset( $data['filename'], $data['pdf_size'], $data['rubix_container_background_colour'] );

		$this->assertSame( $original_data, $data );

		$pdf = \GPDFAPI::get_pdf( $this->form_id, $template_pdf_id );
		$this->assertSame( 'Zadani Document', $pdf['filename'] );
		$this->assertSame( 'A3', $pdf['pdf_size'] );
		$this->assertSame( '#000000', $pdf['rubix_container_background_colour'] );
	}

	public function test_update_item_invalid_permission() {
		wp_set_current_user( 0 );

		$pdf_id = $this->factory->pdf->create( [] );

		$request = new WP_REST_Request( 'PUT', '/gravity-pdf/v1/form/' . $this->form_id . '/' . $pdf_id );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		/* Barebones PDF */
		$request->set_body_params( [
			'name' => 'Simple PDF',
		] );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );

		wp_set_current_user( self::$editor_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );

		$GLOBALS['current_user']->add_cap( 'gravityforms_view_settings' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );

		$GLOBALS['current_user']->add_cap( 'gravityforms_edit_settings' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_update_item_with_invalid_pdf() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'PUT', '/gravity-pdf/v1/form/' . $this->form_id . '/' . uniqid() );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		/* Barebones PDF */
		$request->set_body_params( [
			'name'     => 'Simple PDF',
			'template' => 'rubix',
		] );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'Invalid parameter(s): pdf', $response->get_data()['message'] );
	}

	public function test_delete_item() {
		$template_pdf_id = $this->factory->pdf->create( [ 'template' => 'rubix', 'header' => 'Custom Header' ] );
		$this->factory->pdf->create( [ 'template' => 'rubix' ] );

		wp_set_current_user( self::$admin_id );

		$this->assertCount( 2, \GPDFAPI::get_form_pdfs( $this->form_id ) );

		/* Test Template PDF */
		$request  = new WP_REST_Request( 'DELETE', '/gravity-pdf/v1/form/' . $this->form_id . '/' . $template_pdf_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['deleted'] );
		$this->test_for_pdf_settings( $data['previous'] );
		$this->assertSame( 'Custom Header', $data['previous']['header'] );

		$this->assertCount( 1, \GPDFAPI::get_form_pdfs( $this->form_id ) );
	}

	public function test_delete_item_invalid_permission() {
		wp_set_current_user( 0 );

		$pdf_id = $this->factory->pdf->create();

		$request  = new WP_REST_Request( 'DELETE', '/gravity-pdf/v1/form/' . $this->form_id . '/' . $pdf_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );

		wp_set_current_user( self::$editor_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );

		$GLOBALS['current_user']->add_cap( 'gravityforms_view_settings' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );

		$GLOBALS['current_user']->add_cap( 'gravityforms_edit_settings' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['deleted'] );
	}

	public function test_delete_item_with_invalid_pdf() {
		wp_set_current_user( self::$admin_id );

		/* Test Template PDF */
		$request  = new WP_REST_Request( 'DELETE', '/gravity-pdf/v1/form/' . $this->form_id . '/' . uniqid() );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'Invalid parameter(s): pdf', $response->get_data()['message'] );
	}

	public function test_prepare_item() {
		$template_pdf_id = $this->factory->pdf->create( [
			'template'                          => 'rubix',
			'filename'                          => 'Filename-{Text:5}',
			'pdf_size'                          => 'LETTER',
			'rubix_container_background_colour' => '#1C2C1C',
		] );

		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', '/gravity-pdf/v1/form/' . $this->form_id . '/' . $template_pdf_id );

		/* Test contexts */
		foreach ( [ 'edit' ] as $context ) {
			$request->set_param( 'context', $context );
			$response = rest_get_server()->dispatch( $request );

			$data   = $response->get_data();
			$fields = array_filter( $this->api->get_fields_for_response( $request ), function ( $item ) {
				return ! in_array( $item, [ '_links', '_embedded' ], true );
			} );

			$this->assertCount( count( $fields ), $data, 'Current context: ' . $context );
		}

		/* Test field filter */
		$request->set_param( 'context', 'edit' );
		$request->set_param( '_fields', 'id,name,active' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertCount( 3, $data );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertArrayHasKey( 'name', $data );
		$this->assertArrayHasKey( 'active', $data );
	}

	public function test_get_item_schema() {
		$request = new WP_REST_Request( 'OPTIONS', '/gravity-pdf/v1/form/' . $this->form_id );
		$request->set_query_params( [ 'template' => 'rubix' ] );
		$response = rest_do_request( $request );
		$data     = $response->get_data();
		$args     = $data['endpoints'][1]['args'];

		$this->test_for_pdf_settings( $args );

		$this->assertContains( 'zadani', $args['template']['enum'] );
		$this->assertContains( 'rubix', $args['template']['enum'] );

		$this->assertArrayHasKey( 'type', $args['filename'] );

		$this->assertArrayHasKey( 'additionalProperties', $args['conditionalLogic'] );
		$this->assertArrayHasKey( 'actionType', $args['conditionalLogic']['properties'] );
		$this->assertArrayHasKey( 'logicType', $args['conditionalLogic']['properties'] );
		$this->assertArrayHasKey( 'rules', $args['conditionalLogic']['properties'] );

		$this->assertContains( 'A4', $args['pdf_size']['enum'] );
		$this->assertContains( 'CUSTOM', $args['pdf_size']['enum'] );

		$this->assertArrayHasKey( 'additionalProperties', $args['custom_pdf_size'] );
		$this->assertArrayHasKey( 'width', $args['custom_pdf_size']['properties'] );
		$this->assertArrayHasKey( 'height', $args['custom_pdf_size']['properties'] );
		$this->assertArrayHasKey( 'unit', $args['custom_pdf_size']['properties'] );

		$this->assertContains( 'landscape', $args['orientation']['enum'] );

		$this->assertSame( 'boolean', $args['rtl']['type'] );
		$this->assertSame( 'yes-no', $args['rtl']['format'] );

		$this->assertContains( 'Standard', $args['format']['enum'] );
		$this->assertContains( 'PDFX1A', $args['format']['enum'] );

		$this->assertArrayHasKey( 'type', $args['privileges']['items'] );
		$this->assertArrayHasKey( 'enum', $args['privileges']['items'] );
		$this->assertArrayHasKey( 'type', $args['password'] );
	}

	/**
	 * Check the REST API auto-validates inputs on the CREATE endpoint
	 */
	public function test_input_validation_create() {
		wp_set_current_user( self::$admin_id );

		/* Check valid form ID */
		$request = new WP_REST_Request( 'POST', '/gravity-pdf/v1/form/4751' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( [
			'name'     => 'Label',
			'template' => 'rubix',
		] );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $data['data']['details']['form']['code'] );

		/* Check body params */
		$request = new WP_REST_Request( 'POST', '/gravity-pdf/v1/form/' . $this->form_id );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		$request->set_body_params( [
			'template'         => 'non-existent-template',
			'active'           => 1,
			'notification'     => [
				'nothing',
			],
			'conditionalLogic' => [
				'rule1',
				'rule2',
			],
			'font_size'        => '20px',
			'font_colour'      => 'red',
		] );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );

		$this->assertSame( 'rest_not_in_enum', $data['data']['details']['template']['code'] );
		$this->assertSame( 'rest_not_in_enum', $data['data']['details']['notification']['code'] );
		$this->assertSame( 'rest_property_required', $data['data']['details']['conditionalLogic']['code'] );
		$this->assertSame( 'rest_invalid_type', $data['data']['details']['font_size']['code'] );
		$this->assertSame( 'rest_invalid_hex_color', $data['data']['details']['font_colour']['code'] );
	}

	/**
	 * Check the REST API auto-validates inputs on the UPDATE endpoint
	 */
	public function test_input_validation_update() {
		wp_set_current_user( self::$admin_id );

		/* Check valid form and PDF ID */
		$request = new WP_REST_Request( 'PUT', '/gravity-pdf/v1/form/4751/abcdef1234567' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( [
			'name'     => 'Label',
			'template' => 'rubix',
		] );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $data['data']['details']['form']['code'] );
		$this->assertSame( 'rest_not_in_enum', $data['data']['details']['pdf']['code'] );

		/* Create a valid PDF */
		$request = new WP_REST_Request( 'POST', '/gravity-pdf/v1/form/' . $this->form_id );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( [
			'name'     => 'Label',
			'template' => 'rubix',
		] );

		$response = rest_get_server()->dispatch( $request );

		$pdf_id = $response->get_data()['id'];

		/* Check valid body params */
		$request = new WP_REST_Request( 'PUT', '/gravity-pdf/v1/form/' . $this->form_id . '/' . $pdf_id );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		$request->set_body_params( [
			'template'         => 'non-existent-template',
			'active'           => 1,
			'notification'     => [
				'nothing',
			],
			'conditionalLogic' => [
				'rule1',
				'rule2',
			],
			'font_size'        => '20px',
			'font_colour'      => 'red',
		] );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );

		$this->assertSame( 'rest_not_in_enum', $data['data']['details']['template']['code'] );
		$this->assertSame( 'rest_not_in_enum', $data['data']['details']['notification']['code'] );
		$this->assertSame( 'rest_property_required', $data['data']['details']['conditionalLogic']['code'] );
		$this->assertSame( 'rest_invalid_type', $data['data']['details']['font_size']['code'] );
		$this->assertSame( 'rest_invalid_hex_color', $data['data']['details']['font_colour']['code'] );
	}

	/**
	 * Check the REST API auto-validates inputs on the DELETE endpoint
	 */
	public function test_input_validation_delete() {
		wp_set_current_user( self::$admin_id );

		/* Check valid form and PDF ID */
		$request  = new WP_REST_Request( 'DELETE', '/gravity-pdf/v1/form/4751/abcdef1234567' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $data['data']['details']['form']['code'] );
		$this->assertSame( 'rest_not_in_enum', $data['data']['details']['pdf']['code'] );
	}

	/**
	 * Check the REST API is auto-sanitizing inputs on CREATE
	 */
	public function test_input_sanitizing_create() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/gravity-pdf/v1/form/' . $this->form_id );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( [
			'name'          => '<strong>My Label {:20:value}</strong> %10',
			'template'      => 'rubix',
			'font_size'     => '20',
			'header'        => '<a href="{:20}">My link</a><barcode code="{Field:10.5:value}" /> <span onclick="javascript:alert()">Item</span>',
			'public_access' => true,
		] );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'My Label {:20:value}', $data['name'] );
		$this->assertSame( 20.0, $data['font_size'] );
		$this->assertSame( '<a href="{:20}">My link</a><barcode code="{Field:10.5:value}" /> <span>Item</span>', $data['header'] );
		$this->assertTrue( $data['public_access'] );

		/* Check underlying data structure */
		$pdf = \GPDFAPI::get_pdf( $this->form_id, $data['id'] );

		$this->assertSame( 'My Label {:20:value}', $pdf['name'] );
		$this->assertSame( 20.0, $pdf['font_size'] );
		$this->assertSame( '<a href="{:20}">My link</a><barcode code="{Field:10.5:value}" /> <span>Item</span>', $pdf['header'] );
		$this->assertSame( 'Yes', $pdf['public_access'] );
	}

	/**
	 * Check the REST API is auto-sanitizing inputs on CREATE
	 */
	public function test_input_sanitizing_update() {
		wp_set_current_user( self::$admin_id );

		/* Create valid PDF */
		$request = new WP_REST_Request( 'POST', '/gravity-pdf/v1/form/' . $this->form_id );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( [
			'name'     => 'Label',
			'template' => 'rubix',
		] );

		$response = rest_get_server()->dispatch( $request );

		$pdf_id = $response->get_data()['id'];

		/* Check valid form ID */
		$request = new WP_REST_Request( 'PUT', '/gravity-pdf/v1/form/' . $this->form_id . '/' . $pdf_id );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( [
			'name'          => '<strong>My Label {:20:value}</strong> %10',
			'font_size'     => '20',
			'header'        => '<a href="{:20}">My link</a><barcode code="{Field:10.5:value}" /> <span onclick="javascript:alert()">Item</span>',
			'public_access' => true,
		] );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'My Label {:20:value}', $data['name'] );
		$this->assertSame( 20.0, $data['font_size'] );
		$this->assertSame( '<a href="{:20}">My link</a><barcode code="{Field:10.5:value}" /> <span>Item</span>', $data['header'] );
		$this->assertTrue( $data['public_access'] );

		/* Check underlying data structure */
		$pdf = \GPDFAPI::get_pdf( $this->form_id, $data['id'] );

		$this->assertSame( 'My Label {:20:value}', $pdf['name'] );
		$this->assertSame( 20.0, $pdf['font_size'] );
		$this->assertSame( '<a href="{:20}">My link</a><barcode code="{Field:10.5:value}" /> <span>Item</span>', $pdf['header'] );
		$this->assertSame( 'Yes', $pdf['public_access'] );
	}

	/**
	 * Check the REST API is auto-formatting inputs and outputs
	 */
	public function test_get_and_update_callbacks() {
		wp_set_current_user( self::$admin_id );

		/* Check custom PDF size is not saved if pdf_size is not custom */
		$request = new WP_REST_Request( 'POST', '/gravity-pdf/v1/form/' . $this->form_id );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( [
			'name'            => 'Label',
			'template'        => 'rubix',
			'pdf_size'        => 'A4',
			'custom_pdf_size' => [
				'width'  => 300,
				'height' => 200,
				'unit'   => 'mm',
			],
		] );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertNull( $data['custom_pdf_size'] );
		$pdf = \GPDFAPI::get_pdf( $this->form_id, $data['id'] );
		$this->assertSame( '', $pdf['custom_pdf_size'] );

		/* Check custom PDF size is correctly saved */
		$request = new WP_REST_Request( 'POST', '/gravity-pdf/v1/form/' . $this->form_id );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( [
			'name'            => 'Label',
			'template'        => 'rubix',
			'pdf_size'        => 'CUSTOM',
			'custom_pdf_size' => [
				'width'  => 300,
				'height' => 200,
				'unit'   => 'mm',
			],
		] );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 300.0, $data['custom_pdf_size']['width'] );
		$this->assertSame( 200.0, $data['custom_pdf_size']['height'] );
		$this->assertSame( 'mm', $data['custom_pdf_size']['unit'] );

		$pdf = \GPDFAPI::get_pdf( $this->form_id, $data['id'] );
		$this->assertSame( 300.0, $pdf['custom_pdf_size'][0] );
		$this->assertSame( 200.0, $pdf['custom_pdf_size'][1] );
		$this->assertSame( 'millimeters', $pdf['custom_pdf_size'][2] );
	}

	public function test_default_custom_pdf_size() {
		/* Update the default plugin options */
		\GPDFAPI::update_plugin_option( 'default_pdf_size', 'CUSTOM' );
		\GPDFAPI::update_plugin_option( 'default_custom_pdf_size', [ 5, 7, 'inches' ] );

		/* Reregister the endpoints with new schema */
		$this->api->register_routes();

		/* Run the API request */
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/gravity-pdf/v1/form/' . $this->form_id );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( [
			'name' => 'Label',
		] );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 5.0, $data['custom_pdf_size']['width'] );
		$this->assertSame( 7.0, $data['custom_pdf_size']['height'] );
		$this->assertSame( 'in', $data['custom_pdf_size']['unit'] );

		$pdf = \GPDFAPI::get_pdf( $this->form_id, $data['id'] );

		$this->assertSame( 5.0, $pdf['custom_pdf_size'][0] );
		$this->assertSame( 7.0, $pdf['custom_pdf_size'][1] );
		$this->assertSame( 'inches', $pdf['custom_pdf_size'][2] );
	}

	public function test_custom_pdf_size() {
		wp_set_current_user( self::$admin_id );

		/* test for an invalid option */
		$request = new WP_REST_Request( 'POST', '/gravity-pdf/v1/form/' . $this->form_id );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( [
			'name'            => 'Label',
			'template'        => 'rubix',
			'pdf_size'        => 'CUSTOM',
			'custom_pdf_size' => [ 5, 7, 'inches' ],
		] );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );

		/* test valid option */
		$request = new WP_REST_Request( 'POST', '/gravity-pdf/v1/form/' . $this->form_id );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( [
			'name'            => 'Label',
			'template'        => 'rubix',
			'pdf_size'        => 'CUSTOM',
			'custom_pdf_size' => [
				'width'  => 5.0,
				'height' => 4.0,
				'unit'   => 'in',
			],
		] );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
	}

	public function test_assign_template_for_schema() {
		$this->api->get_template_schema( 'focus-gravity' );

		$schema = $this->api->get_item_schema();

		/* Check the template-specific properties are included */
		$this->assertArrayHasKey( 'show_form_title', $schema['properties'] );
		$this->assertArrayHasKey( 'show_html', $schema['properties'] );
		$this->assertArrayHasKey( 'show_section_content', $schema['properties'] );
		$this->assertArrayHasKey( 'header', $schema['properties'] );
		$this->assertArrayHasKey( 'first_header', $schema['properties'] );
		$this->assertArrayHasKey( 'background_color', $schema['properties'] );
		$this->assertArrayHasKey( 'focusgravity_accent_colour', $schema['properties'] );
	}

	public function test_get_schema_for_template() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', '/' . $this->api::get_route_basepath() . '/' . $this->form_id . '/schema' );
		$request->set_query_params( [
				'template' => 'rubix',
			]
		);

		$response = rest_get_server()->dispatch( $request );
		$schema   = $response->get_data();

		$this->test_for_pdf_settings( $schema['properties'] );

		/* Filter fields by context */
		$request->set_query_params( [
			'template' => 'focus-gravity',
			'context'  => 'template',
		] );

		$response = rest_get_server()->dispatch( $request );
		$schema   = $response->get_data();

		$this->assertArrayHasKey( 'show_form_title', $schema['properties'] );
		$this->assertArrayHasKey( 'show_html', $schema['properties'] );
		$this->assertArrayHasKey( 'show_section_content', $schema['properties'] );
		$this->assertArrayHasKey( 'header', $schema['properties'] );
		$this->assertArrayHasKey( 'first_header', $schema['properties'] );
		$this->assertArrayHasKey( 'background_color', $schema['properties'] );
		$this->assertArrayHasKey( 'focusgravity_accent_colour', $schema['properties'] );

		$this->assertArrayNotHasKey( 'form', $schema['properties'] );
		$this->assertArrayNotHasKey( 'active', $schema['properties'] );
		$this->assertArrayNotHasKey( 'name', $schema['properties'] );
		$this->assertArrayNotHasKey( 'filename', $schema['properties'] );
		$this->assertArrayNotHasKey( 'notification', $schema['properties'] );
		$this->assertArrayNotHasKey( 'conditionalLogic', $schema['properties'] );
	}
}
