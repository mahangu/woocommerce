<?php
declare( strict_types = 1 );

use Automattic\WooCommerce\Enums\OrderStatus;
use Automattic\WooCommerce\RestApi\UnitTests\Helpers\OrderHelper;

/**
 * Tests for the Review Order checkout endpoint, gating handler, and `wc_get_review_order_url()` helper.
 */
class WC_Review_Order_Endpoint_Test extends WC_Unit_Test_Case {

	/**
	 * Reset $_GET and the global query between tests.
	 */
	public function tearDown(): void {
		$_GET = array();
		global $wp_query;
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->is_404 = false;
		}
		parent::tearDown();
	}

	/**
	 * Invoke the private review_order() handler with output captured.
	 *
	 * @param int $order_id Order id to dispatch.
	 * @return string Rendered HTML.
	 */
	private function dispatch( int $order_id ): string {
		$reflection = new ReflectionMethod( WC_Shortcode_Checkout::class, 'review_order' );
		$reflection->setAccessible( true );

		ob_start();
		$reflection->invoke( null, $order_id );
		return (string) ob_get_clean();
	}

	/**
	 * @testdox WC_Query registers the review-order query var.
	 */
	public function test_query_var_registered(): void {
		$query_vars = WC()->query->get_query_vars();
		$this->assertArrayHasKey( 'review-order', $query_vars );
		$this->assertSame( 'review-order', $query_vars['review-order'] );
	}

	/**
	 * @testdox WC_Query exposes a translated title for the review-order endpoint.
	 */
	public function test_endpoint_title_set(): void {
		$this->assertSame( 'Review your order', WC()->query->get_endpoint_title( 'review-order' ) );
	}

	/**
	 * @testdox wc_get_review_order_url returns a tokenized URL pointing at review-order.
	 */
	public function test_helper_returns_tokenized_url(): void {
		$order = OrderHelper::create_order();
		$url   = wc_get_review_order_url( $order );

		$this->assertMatchesRegularExpression( '#review-order[/=]' . $order->get_id() . '#', $url );
		$this->assertStringContainsString( 'key=' . $order->get_order_key(), $url );
	}

	/**
	 * @testdox wc_get_review_order_url returns empty string for non-order input.
	 */
	public function test_helper_empty_for_non_order(): void {
		$this->assertSame( '', wc_get_review_order_url( null ) );
		$this->assertSame( '', wc_get_review_order_url( 0 ) );
		$this->assertSame( '', wc_get_review_order_url( new stdClass() ) );
	}

	/**
	 * @testdox woocommerce_review_order_url filter can replace the helper output.
	 */
	public function test_helper_filterable(): void {
		$order    = OrderHelper::create_order();
		$override = static function () {
			return 'https://example.test/custom';
		};
		add_filter( 'woocommerce_review_order_url', $override );

		$this->assertSame( 'https://example.test/custom', wc_get_review_order_url( $order ) );

		remove_filter( 'woocommerce_review_order_url', $override );
	}

	/**
	 * @testdox Gating 404s when the order id does not resolve.
	 */
	public function test_404_when_order_missing(): void {
		$this->dispatch( 999999 );

		global $wp_query;
		$this->assertTrue( $wp_query->is_404 );
	}

	/**
	 * @testdox Gating 404s when no key query arg is supplied.
	 */
	public function test_404_when_key_missing(): void {
		$order = OrderHelper::create_order();
		$order->set_status( OrderStatus::COMPLETED );
		$order->save();
		$_GET = array();

		$this->dispatch( $order->get_id() );

		global $wp_query;
		$this->assertTrue( $wp_query->is_404 );
	}

	/**
	 * @testdox Gating 404s when the supplied key does not match the order key.
	 */
	public function test_404_when_key_mismatched(): void {
		$order = OrderHelper::create_order();
		$order->set_status( OrderStatus::COMPLETED );
		$order->save();
		$_GET = array( 'key' => 'wc_order_definitelywrong' );

		$this->dispatch( $order->get_id() );

		global $wp_query;
		$this->assertTrue( $wp_query->is_404 );
	}

	/**
	 * @testdox Gating 404s when the order status is not in the eligible set.
	 */
	public function test_404_when_status_ineligible(): void {
		$order = OrderHelper::create_order();
		$order->set_status( OrderStatus::PROCESSING );
		$order->save();
		$_GET = array( 'key' => $order->get_order_key() );

		$this->dispatch( $order->get_id() );

		global $wp_query;
		$this->assertTrue( $wp_query->is_404 );
	}

	/**
	 * @testdox Gating 404s when a logged-in user does not own the order.
	 */
	public function test_404_when_logged_in_customer_mismatch(): void {
		$customer_id = self::factory()->user->create();
		$other_id    = self::factory()->user->create();

		$order = OrderHelper::create_order( $customer_id );
		$order->set_status( OrderStatus::COMPLETED );
		$order->save();

		wp_set_current_user( $other_id );
		$_GET = array( 'key' => $order->get_order_key() );

		$this->dispatch( $order->get_id() );

		global $wp_query;
		$this->assertTrue( $wp_query->is_404 );

		wp_set_current_user( 0 );
	}

	/**
	 * @testdox Gating renders the template for a valid completed-order link.
	 */
	public function test_renders_template_on_success(): void {
		$order = OrderHelper::create_order();
		$order->set_status( OrderStatus::COMPLETED );
		$order->save();
		$_GET = array( 'key' => $order->get_order_key() );

		$html = $this->dispatch( $order->get_id() );

		global $wp_query;
		$this->assertFalse( $wp_query->is_404 );
		$this->assertStringContainsString( 'woocommerce-review-order', $html );
		$this->assertStringContainsString( 'Review your order', $html );
		$this->assertStringContainsString( 'Order #' . $order->get_order_number(), $html );
	}

	/**
	 * @testdox woocommerce_review_order_eligible_statuses filter widens the eligible set.
	 */
	public function test_eligible_statuses_filter_widens_set(): void {
		$order = OrderHelper::create_order();
		$order->set_status( OrderStatus::PROCESSING );
		$order->save();
		$_GET = array( 'key' => $order->get_order_key() );

		$widen = static function () {
			return array( OrderStatus::COMPLETED, OrderStatus::PROCESSING );
		};
		add_filter( 'woocommerce_review_order_eligible_statuses', $widen );

		$html = $this->dispatch( $order->get_id() );

		remove_filter( 'woocommerce_review_order_eligible_statuses', $widen );

		global $wp_query;
		$this->assertFalse( $wp_query->is_404 );
		$this->assertStringContainsString( 'woocommerce-review-order', $html );
	}
}
