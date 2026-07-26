<?php
/**
 * Effective checkout reward catalogue tests.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Tests\Unit;

use LCTER_WCPL\Repositories\Rewards_Repository;
use LCTER_WCPL\Services\Rewards_Service;
use LCTER_WCPL\Settings;
use PHPUnit\Framework\TestCase;

final class RewardCatalogServiceTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['lcter_wcpl_test_options']  = array();
		$GLOBALS['lcter_wcpl_test_products'] = array();
	}

	public function test_default_limit_returns_first_twelve_in_repository_order(): void {
		$rows = $this->reward_rows( 15 );
		$this->publish_products( 15 );
		$service = new Rewards_Service( new Catalog_Repository_Fake( $rows ) );

		self::assertSame( range( 1, 12 ), array_map( 'intval', array_column( $service->get_available_checkout_rewards(), 'id' ) ) );
	}

	public function test_configured_limit_and_changed_order_are_applied(): void {
		$GLOBALS['lcter_wcpl_test_options'][ Settings::OPTION_MAX_CHECKOUT_REWARDS ] = 20;
		$rows = array_reverse( $this->reward_rows( 15 ) );
		$this->publish_products( 15 );
		$service = new Rewards_Service( new Catalog_Repository_Fake( $rows ) );

		self::assertCount( 15, $service->get_available_checkout_rewards() );
		self::assertSame( 15, (int) $service->get_available_checkout_rewards()[0]['id'] );
	}

	public function test_deleted_unpublished_unpurchasable_and_out_of_stock_products_are_excluded(): void {
		$rows = $this->reward_rows( 5 );
		$GLOBALS['lcter_wcpl_test_products'] = array(
			1 => new Catalog_Product_Fake(),
			2 => new Catalog_Product_Fake( 'draft' ),
			3 => new Catalog_Product_Fake( 'publish', false ),
			4 => new Catalog_Product_Fake( 'publish', true, true, false ),
		);
		$service = new Rewards_Service( new Catalog_Repository_Fake( $rows ) );

		self::assertSame( array( 1 ), array_map( 'intval', array_column( $service->get_checkout_eligible_rewards(), 'id' ) ) );
	}

	public function test_limit_sanitizer_rejects_values_outside_one_to_fifty(): void {
		self::assertSame( 12, Settings::get_max_checkout_rewards() );
		$GLOBALS['lcter_wcpl_test_options'][ Settings::OPTION_MAX_CHECKOUT_REWARDS ] = 50;
		self::assertSame( 50, Settings::get_max_checkout_rewards() );
		$GLOBALS['lcter_wcpl_test_options'][ Settings::OPTION_MAX_CHECKOUT_REWARDS ] = 51;
		self::assertSame( 12, Settings::get_max_checkout_rewards() );
	}

	private function reward_rows( int $count ): array {
		$rows = array();
		for ( $id = 1; $id <= $count; ++$id ) {
			$rows[] = array( 'id' => $id, 'product_id' => $id, 'points_cost' => 100, 'active' => 1, 'visible_in_checkout' => 1, 'sort_order' => $id );
		}
		return $rows;
	}

	private function publish_products( int $count ): void {
		for ( $id = 1; $id <= $count; ++$id ) {
			$GLOBALS['lcter_wcpl_test_products'][ $id ] = new Catalog_Product_Fake();
		}
	}
}

final class Catalog_Repository_Fake extends Rewards_Repository {
	public function __construct( private array $rows ) {}
	public function find_checkout_candidates(): array {
		return $this->rows;
	}
}

final class Catalog_Product_Fake {
	public function __construct(
		private string $status = 'publish',
		private bool $purchasable = true,
		private bool $managing_stock = false,
		private bool $in_stock = true
	) {}
	public function get_status(): string { return $this->status; }
	public function is_purchasable(): bool { return $this->purchasable; }
	public function managing_stock(): bool { return $this->managing_stock; }
	public function is_in_stock(): bool { return $this->in_stock; }
}
