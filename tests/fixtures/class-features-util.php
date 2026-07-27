<?php
/**
 * WooCommerce FeaturesUtil test double.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Utilities;

/**
 * Captures feature compatibility declarations.
 */
class FeaturesUtil {
	/** @var array<int,array<int,mixed>> */
	public static array $calls = array();

	/**
	 * Capture a declaration.
	 *
	 * @param string $feature Feature ID.
	 * @param string $plugin_file Main plugin file.
	 * @param bool   $compatible Compatibility value.
	 */
	public static function declare_compatibility( string $feature, string $plugin_file, bool $compatible ): void {
		self::$calls[] = array( $feature, $plugin_file, $compatible );
	}
}
