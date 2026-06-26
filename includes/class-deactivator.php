<?php
/**
 * Plugin deactivation class.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL;

use function flush_rewrite_rules;

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
		flush_rewrite_rules();
	}
}
