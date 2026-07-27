<?php
/**
 * WooCommerce order CRUD adapter tests.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Tests\Unit;

use LCTER_WCPL\Adapters\WooCommerce_Orders_Adapter;
use LCTER_WCPL\Adapters\WooCommerce_Checkout_Adapter;
use LCTER_WCPL\Services\Points_Service;
use PHPUnit\Framework\TestCase;

/**
 * Verifies order state is read and persisted exclusively through WC_Order CRUD.
 */
final class WooCommerceOrdersAdapterCRUDTest extends TestCase {
	/**
	 * A paid order writes accounting metadata and saves it once.
	 */
	public function test_paid_order_reads_and_writes_metadata_through_order_crud(): void {
		$order = new HPOS_Order_Fake();
		$GLOBALS['lcter_wcpl_test_orders'][41] = $order;
		$points = new HPOS_Points_Service_Fake();
		$adapter = new WooCommerce_Orders_Adapter( $points );

		$adapter->on_order_paid( 41 );

		self::assertSame( 41, $points->order_id );
		self::assertSame( 1, $points->cycle );
		self::assertSame( 10000, $order->get_meta( WooCommerce_Orders_Adapter::ORDER_POINTS_AWARDED_META ) );
		self::assertSame( WooCommerce_Orders_Adapter::LOYALTY_STATE_APPLIED, $order->get_meta( WooCommerce_Orders_Adapter::LOYALTY_MOVEMENTS_STATE_META ) );
		self::assertSame( 1, $order->save_calls );
	}

	/**
	 * Loyalty cycle is read from WC_Order metadata and normalized.
	 */
	public function test_loyalty_cycle_uses_order_metadata(): void {
		$order = new HPOS_Order_Fake();
		$order->update_meta_data( WooCommerce_Orders_Adapter::LOYALTY_CYCLE_META, 3 );

		self::assertSame( 3, WooCommerce_Orders_Adapter::get_order_loyalty_cycle( $order ) );
	}

	/**
	 * Reward line metadata is written through WC_Order_Item CRUD.
	 */
	public function test_reward_line_uses_order_item_crud(): void {
		$item    = new HPOS_Order_Item_Fake();
		$adapter = new WooCommerce_Checkout_Adapter();

		$adapter->add_order_item_metadata(
			$item,
			'cart-key',
			array(
				'_lcter_wcpl_is_reward'         => '1',
				'_lcter_wcpl_reward_id'         => 8,
				'_lcter_wcpl_points_cost_each'  => 500,
				'_lcter_wcpl_points_cost_total' => 1000,
			),
			new \stdClass()
		);

		self::assertSame( '1', $item->meta['_lcter_wcpl_is_reward'] );
		self::assertSame( 8, $item->meta['_lcter_wcpl_reward_id'] );
		self::assertSame( 1000, $item->meta['_lcter_wcpl_points_cost_total'] );
		self::assertSame( 0, $item->total );
	}
}

/**
 * Minimal WC_Order_Item-compatible test double.
 */
final class HPOS_Order_Item_Fake {
	public array $meta = array();
	public int $total = -1;

	public function set_subtotal( $value ): void { unset( $value ); }
	public function set_total( $value ): void { $this->total = (int) $value; }
	public function set_subtotal_tax( $value ): void { unset( $value ); }
	public function set_total_tax( $value ): void { unset( $value ); }
	public function set_taxes( array $taxes ): void { unset( $taxes ); }
	public function add_meta_data( string $key, $value, bool $unique ): void {
		unset( $unique );
		$this->meta[ $key ] = $value;
	}
}

/**
 * Minimal WC_Order-compatible test double.
 */
final class HPOS_Order_Fake {
	private array $meta = array();
	public int $save_calls = 0;

	public function is_paid(): bool { return true; }
	public function get_customer_id(): int { return 7; }
	public function get_total(): string { return '120.00'; }
	public function get_shipping_total(): string { return '15.00'; }
	public function get_shipping_tax(): string { return '5.00'; }
	public function get_order_number(): string { return '41'; }
	public function get_meta( string $key ) { return $this->meta[ $key ] ?? ''; }
	public function update_meta_data( string $key, $value ): void { $this->meta[ $key ] = $value; }
	public function save(): void { ++$this->save_calls; }
}

/**
 * Captures the application call made from a paid WC_Order.
 */
final class HPOS_Points_Service_Fake extends Points_Service {
	public int $order_id = 0;
	public int $cycle = 0;

	public function award_points_for_order( int $customer_id, int $points, int $order_id, ?string $description = null, $metadata = null, int $cycle = 1 ): bool {
		unset( $customer_id, $points, $description, $metadata );
		$this->order_id = $order_id;
		$this->cycle    = $cycle;
		return true;
	}
}
