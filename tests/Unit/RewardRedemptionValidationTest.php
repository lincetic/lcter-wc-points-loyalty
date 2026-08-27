<?php
/**
 * Server-side reward selection validation tests.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Tests\Unit;

use LCTER_WCPL\Repositories\Order_Rewards_Repository;
use LCTER_WCPL\Services\Points_Service;
use LCTER_WCPL\Services\Reward_Redemption_Service;
use LCTER_WCPL\Services\Rewards_Service;
use PHPUnit\Framework\TestCase;

final class RewardRedemptionValidationTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['lcter_wcpl_test_products'] = array(
			101 => new Redemption_Product_Fake(),
			102 => new Redemption_Product_Fake(),
		);
	}

	public function test_manipulated_combined_selection_above_balance_is_rejected(): void {
		$service = $this->service( 20000 );

		$result = $service->validate_selection( 7, array( 1 => 2, 2 => 1 ), 6015 );

		self::assertFalse( $result['valid'] );
		self::assertSame( 'insufficient_balance', $result['error'] );
	}

	public function test_combined_selection_equal_to_balance_is_valid(): void {
		$service = $this->service( 20000 );

		$result = $service->validate_selection( 7, array( 1 => 2 ), 6015, 4000 );

		self::assertTrue( $result['valid'] );
		self::assertSame( 16000, $result['total_points'] );
	}

	public function test_reward_without_enough_stock_is_rejected_at_final_validation(): void {
		$GLOBALS['lcter_wcpl_test_products'][101] = new Redemption_Product_Fake( 1 );

		$result = $this->service( 20000 )->validate_selection( 7, array( 1 => 2 ), 6015 );

		self::assertFalse( $result['valid'] );
		self::assertSame( 'reward_unavailable', $result['error'] );
	}

	private function service( int $balance ): Reward_Redemption_Service {
		return new Reward_Redemption_Service(
			new Redemption_Rewards_Service_Fake(),
			new Redemption_Points_Service_Fake( $balance ),
			new Redemption_Order_Rewards_Repository_Fake()
		);
	}
}

final class Redemption_Rewards_Service_Fake extends Rewards_Service {
	public function __construct() {}

	public function get_available_rewards(): array {
		return array(
			array( 'id' => 1, 'product_id' => 101, 'points_cost' => 8000 ),
			array( 'id' => 2, 'product_id' => 102, 'points_cost' => 5000 ),
		);
	}
}

final class Redemption_Points_Service_Fake extends Points_Service {
	public function __construct( private int $balance ) {}

	public function get_balance( int $customer_id ): int {
		unset( $customer_id );
		return $this->balance;
	}
}

final class Redemption_Order_Rewards_Repository_Fake extends Order_Rewards_Repository {
	public function __construct() {}
}

final class Redemption_Product_Fake {
	public function __construct( private int $stock = 10 ) {}

	public function is_purchasable(): bool { return true; }
	public function is_in_stock(): bool { return true; }
	public function has_enough_stock( int $quantity ): bool { return $quantity <= $this->stock; }
}
