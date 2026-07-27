<?php
/**
 * WooCommerce HPOS compatibility declaration.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Declares storage compatibility without requiring WooCommerce to be active.
 */
class HPOS_Compatibility {
	/**
	 * Declare compatibility with WooCommerce custom order tables.
	 *
	 * @param string $plugin_file Absolute main plugin file.
	 * @return bool Whether WooCommerce received the declaration.
	 */
	public static function declare( string $plugin_file ): bool {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return false;
		}

		FeaturesUtil::declare_compatibility( 'custom_order_tables', $plugin_file, true );
		return true;
	}
}
