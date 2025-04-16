<?php
/**
 * Class WP_REST_API_Log_Test_Common
 *
 * @package 
 */

/**
 * Sample test case.
 */
class WP_REST_API_Log_Test_Common extends WP_UnitTestCase {

	/**
	 * Make sure valid methods returns results
	 */
	function test_valid_methods() {
		$valid_methods = WP_REST_API_Log_Common::valid_methods();
		$this->assertTrue( ! empty( $valid_methods ) );
		$this->assertContains( 'GET', $valid_methods );
	}

	/**
	 * Test that GET is a valid method
	 */
	function test_valid_method() {
		$valid_methods = WP_REST_API_Log_Common::valid_methods();
		$this->assertTrue( WP_REST_API_Log_Common::is_valid_method( 'GET' ) );
	}

	/**
	 * Test the delete_items method
	 */
	function test_delete_items() {
		$request = new WP_REST_Request( 'DELETE', '/wp-rest-api-log/entry' );
		$request->set_param( 'older-than-seconds', DAY_IN_SECONDS * 30 );
		$response = WP_REST_API_Log_Controller::delete_items( $request );
		$this->assertNotWPError( $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test the get_items method
	 */
	function test_get_items() {
		$request = new WP_REST_Request( 'GET', '/wp-rest-api-log/entries' );
		$response = WP_REST_API_Log_Controller::get_items( $request );
		$this->assertNotWPError( $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test the get_item method
	 */
	function test_get_item() {
		$request = new WP_REST_Request( 'GET', '/wp-rest-api-log/entry/1' );
		$response = WP_REST_API_Log_Controller::get_item( $request );
		$this->assertNotWPError( $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test the purge_log method
	 */
	function test_purge_log() {
		$request = new WP_REST_Request( 'DELETE', '/wp-rest-api-log/entries' );
		$response = WP_REST_API_Log_Controller::purge_log( $request );
		$this->assertNotWPError( $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test the batch_purge_log method
	 */
	function test_batch_purge_log() {
		$request = new WP_REST_Request( 'DELETE', '/wp-rest-api-log/batch-purge-all' );
		$response = WP_REST_API_Log_Controller::batch_purge_log( $request );
		$this->assertNotWPError( $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test the download_json method
	 */
	function test_download_json() {
		$request = new WP_REST_Request( 'GET', '/wp-rest-api-log/entry/1/request/body/download' );
		$request->set_param( 'hash', wp_hash( wp_nonce_tick() . 'wp-rest-api-log-download-request-body' ) );
		$response = WP_REST_API_Log_Controller::download_json( $request );
		$this->assertNotWPError( $response );
		$this->assertEquals( 200, $response->get_status() );
	}
}
