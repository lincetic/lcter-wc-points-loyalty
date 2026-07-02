<?php
/**
 * Plugin business settings.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides validated access to configurable business values.
 */
class Settings {
	const OPTION_INITIAL_BONUS_POINTS       = 'lcter_wcpl_initial_bonus_points';
	const OPTION_REWARD_COST_MULTIPLIER    = 'lcter_wcpl_reward_cost_multiplier';
	const DEFAULT_INITIAL_BONUS_POINTS      = 10000;
	const DEFAULT_REWARD_COST_MULTIPLIER   = 2000;
	const MAXIMUM_CONFIGURABLE_INTEGER     = 2147483647;

	/**
	 * Return the configured initial bonus.
	 */
	public static function get_initial_bonus_points(): int {
		return self::get_positive_integer_option(
			self::OPTION_INITIAL_BONUS_POINTS,
			self::DEFAULT_INITIAL_BONUS_POINTS
		);
	}

	/**
	 * Return the configured reward cost multiplier.
	 */
	public static function get_reward_cost_multiplier(): int {
		return self::get_positive_integer_option(
			self::OPTION_REWARD_COST_MULTIPLIER,
			self::DEFAULT_REWARD_COST_MULTIPLIER
		);
	}

	/**
	 * Sanitize the initial bonus option for the Settings API.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function sanitize_initial_bonus_points( $value ): int {
		return self::sanitize_positive_integer_option(
			$value,
			self::OPTION_INITIAL_BONUS_POINTS,
			self::DEFAULT_INITIAL_BONUS_POINTS,
			__( 'Los puntos de bonus inicial deben ser un entero positivo.', LCTER_WCPL_TEXT_DOMAIN )
		);
	}

	/**
	 * Sanitize the reward multiplier option for the Settings API.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function sanitize_reward_cost_multiplier( $value ): int {
		return self::sanitize_positive_integer_option(
			$value,
			self::OPTION_REWARD_COST_MULTIPLIER,
			self::DEFAULT_REWARD_COST_MULTIPLIER,
			__( 'El multiplicador de coste de rewards debe ser un entero positivo.', LCTER_WCPL_TEXT_DOMAIN )
		);
	}

	/**
	 * Normalize a stored positive integer, falling back to its documented default.
	 */
	private static function get_positive_integer_option( string $option, int $default ): int {
		$value = get_option( $option, $default );

		return self::is_positive_integer( $value ) ? (int) $value : $default;
	}

	/**
	 * Validate one submitted Settings API value and preserve the previous valid value on error.
	 *
	 * @param mixed $value Submitted value.
	 */
	private static function sanitize_positive_integer_option( $value, string $option, int $default, string $message ): int {
		if ( self::is_positive_integer( $value ) ) {
			return (int) $value;
		}

		add_settings_error( 'lcter_wcpl_settings', $option, $message, 'error' );

		$current = get_option( $option, $default );
		return self::is_positive_integer( $current ) ? (int) $current : $default;
	}

	/**
	 * Check a decimal scalar against the positive signed INT range used by the schema.
	 *
	 * @param mixed $value Candidate value.
	 */
	private static function is_positive_integer( $value ): bool {
		if ( ! is_scalar( $value ) ) {
			return false;
		}

		$value = trim( (string) $value );
		if ( '' === $value || ! ctype_digit( $value ) ) {
			return false;
		}

		$integer = (int) $value;
		return $integer > 0 && $integer <= self::MAXIMUM_CONFIGURABLE_INTEGER;
	}
}
