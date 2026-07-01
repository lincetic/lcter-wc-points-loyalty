<?php
/**
 * Plugin deactivation class.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivator class.
 */
class Deactivator {

	/**
	 * Run deactivation tasks.
	 */
	public static function deactivate(): void {

		// No scheduled task or rewrite rule is registered by the plugin.
	}
}
