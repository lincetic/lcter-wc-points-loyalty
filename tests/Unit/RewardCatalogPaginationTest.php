<?php
/**
 * Reward catalogue pagination URL tests.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Tests\Unit;

use LCTER_WCPL\Admin_UI\Reward_Catalog_Admin;
use PHPUnit\Framework\TestCase;

/**
 * Verifies query-string pagination independently from permalink settings.
 */
final class RewardCatalogPaginationTest extends TestCase {
	/**
	 * Pagination must preserve catalogue state and replace only paged.
	 */
	public function test_base_preserves_filters_search_and_sorting(): void {
		$base = Reward_Catalog_Admin::get_pagination_base(
			array(
				'page'    => 'wrong-page',
				'paged'   => '7',
				's'       => 'camiseta azul',
				'status'  => 'visible',
				'orderby' => 'sort_order',
				'order'   => 'ASC',
			)
		);

		self::assertStringStartsWith( 'https://example.test/wp-admin/admin.php?', $base );
		self::assertStringContainsString( 'page=lcter-wcpl-reward-catalog', $base );
		self::assertStringContainsString( 's=camiseta%20azul', $base );
		self::assertStringContainsString( 'status=visible', $base );
		self::assertStringContainsString( 'orderby=sort_order', $base );
		self::assertStringContainsString( 'order=ASC', $base );
		self::assertStringContainsString( 'paged=%#%', $base );
		self::assertStringNotContainsString( 'paged=7', $base );
		self::assertStringNotContainsString( '/page/', $base );
	}

	/**
	 * Replacing the native placeholder produces first, intermediate and last URLs.
	 */
	public function test_placeholder_generates_query_parameter_for_every_page(): void {
		$base = Reward_Catalog_Admin::get_pagination_base(
			array(
				'page'   => 'lcter-wcpl-reward-catalog',
				'status' => 'active',
			)
		);

		self::assertStringContainsString( 'paged=1', str_replace( '%#%', '1', $base ) );
		self::assertStringContainsString( 'paged=3', str_replace( '%#%', '3', $base ) );
		self::assertStringContainsString( 'paged=8', str_replace( '%#%', '8', $base ) );
	}
}
