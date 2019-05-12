<?php
/**
 * Class PluginTest
 *
 * @package ETH_Simple_Shortlinks
 */

/**
 * Plugin test case.
 */
class PluginTest extends WP_UnitTestCase {
	/**
	 * Post ID for published tests.
	 *
	 * @var int
	 */
	protected static $post_id_published;

	/**
	 * Post ID for draft tests.
	 *
	 * @var int
	 */
	protected static $post_id_draft;

	/**
	 * Create a post to test with.
	 */
	public function setUp(): void {
		parent::setUp();

		static::$post_id_published = $this->factory->post->create();
		static::$post_id_draft     = $this->factory->post->create(
			[
				'post_status' => 'draft',
			]
		);
	}

	/**
	 * Test shortlink overrides.
	 */
	public function test_shortlink_filters(): void {
		$expected_published = user_trailingslashit( home_url( 'p/' . static::$post_id_published ) );
		$expected_draft     = add_query_arg( 'p', static::$post_id_draft, user_trailingslashit( home_url() ) );

		$this->assertEquals( $expected_published, wp_get_shortlink( static::$post_id_published ), 'Failed to assert that published post has a simple shortlink.' );
		$this->assertEquals( $expected_draft, wp_get_shortlink( static::$post_id_draft ), 'Failed to assert that draft post did not have its shortlink modified.' );
	}

	/**
	 * Test redirect parsing for supported post.
	 */
	public function test_published_post_redirect(): void {
		$fake_request = new \stdClass();
		$fake_request->query_vars = [
			'p'             => static::$post_id_published,
			'eth-shortlink' => true,
		];

		$redirect = ETH_Simple_Shortlinks::get_instance()->get_redirect_for_request( $fake_request );

		$this->assertEquals( get_permalink( static::$post_id_published ), $redirect->destination, 'Failed to assert that redirect destination is post\'s permalink.' );
		$this->assertEquals( 301, $redirect->status_code, 'Failed to assert that redirect status code is that for a permanent redirect.' );
	}

	/**
	 * Test redirect parsing for unsupported post.
	 */
	public function test_draft_post_redirect(): void {
		$fake_request = new \stdClass();
		$fake_request->query_vars = [
			'p'             => static::$post_id_draft,
			'eth-shortlink' => true,
		];

		$redirect = ETH_Simple_Shortlinks::get_instance()->get_redirect_for_request( $fake_request );

		$this->assertNull( $redirect, 'Failed to assert that redirect is not generated for unsupported post status.' );
	}
}
