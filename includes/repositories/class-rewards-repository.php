<?php
/**
 * Reward catalog persistence.
 *
 * @package LCTER_WC_Points_Loyalty
 */

declare( strict_types=1 );

namespace LCTER_WCPL\Repositories;

use LCTER_WCPL\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewards_Repository {
	public function find_active( int $limit ): array {
		global $wpdb;

		$limit        = max( 1, min( 100, $limit ) );
		$current_time = current_time( 'mysql' );

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table_name() . '
				WHERE active = 1
				AND (starts_at IS NULL OR starts_at <= %s)
				AND (ends_at IS NULL OR ends_at >= %s)
				ORDER BY sort_order ASC, id ASC
				LIMIT %d',
				$current_time,
				$current_time,
				$limit
			),
			ARRAY_A
		);
	}

	public function find( int $reward_id ): ?array {
		global $wpdb;

		$reward = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table_name() . ' WHERE id = %d', $reward_id ),
			ARRAY_A
		);

		return $reward ?: null;
	}

	public function find_by_product( int $product_id ): ?array {
		global $wpdb;

		$reward = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table_name() . ' WHERE product_id = %d', $product_id ),
			ARRAY_A
		);

		return $reward ?: null;
	}

	public function save( array $reward ): int {
		global $wpdb;

		$reward_id   = isset( $reward['id'] ) ? absint( $reward['id'] ) : 0;
		$product_id  = isset( $reward['product_id'] ) ? absint( $reward['product_id'] ) : 0;
		$points_cost = isset( $reward['points_cost'] ) ? absint( $reward['points_cost'] ) : 0;

		if ( $product_id <= 0 || $points_cost <= 0 ) {
			return 0;
		}

		$starts_at = $this->normalize_datetime( $reward['starts_at'] ?? null );
		$ends_at   = $this->normalize_datetime( $reward['ends_at'] ?? null );

		if (
			( ! empty( $reward['starts_at'] ) && null === $starts_at ) ||
			( ! empty( $reward['ends_at'] ) && null === $ends_at ) ||
			( $starts_at && $ends_at && $starts_at > $ends_at )
		) {
			return 0;
		}

		$data    = array(
			'product_id'  => $product_id,
			'points_cost' => $points_cost,
			'active'      => empty( $reward['active'] ) ? 0 : 1,
			'sort_order'  => isset( $reward['sort_order'] ) ? (int) $reward['sort_order'] : 0,
			'starts_at'   => $starts_at,
			'ends_at'     => $ends_at,
		);
		$formats = array( '%d', '%d', '%d', '%d', '%s', '%s' );

		if ( 0 === $reward_id ) {
			$existing  = $this->find_by_product( $product_id );
			$reward_id = $existing ? (int) $existing['id'] : 0;
		}

		if ( $reward_id > 0 ) {
			$updated = $wpdb->update( $this->table_name(), $data, array( 'id' => $reward_id ), $formats, array( '%d' ) );

			return false === $updated ? 0 : $reward_id;
		}

		$saved = $wpdb->insert( $this->table_name(), $data, $formats );

		return false === $saved ? 0 : (int) $wpdb->insert_id;
	}

	public function delete( int $reward_id ): bool {
		global $wpdb;

		if ( $reward_id <= 0 ) {
			return false;
		}

		return (bool) $wpdb->delete( $this->table_name(), array( 'id' => $reward_id ), array( '%d' ) );
	}

	public function deactivate( int $reward_id ): bool {
		global $wpdb;

		if ( $reward_id <= 0 ) {
			return false;
		}

		$updated = $wpdb->update(
			$this->table_name(),
			array( 'active' => 0 ),
			array( 'id' => $reward_id ),
			array( '%d' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	private function normalize_datetime( $datetime ): ?string {
		if ( empty( $datetime ) || ! is_string( $datetime ) ) {
			return null;
		}

		$datetime = sanitize_text_field( $datetime );

		foreach ( array( 'Y-m-d H:i:s', 'Y-m-d\TH:i' ) as $format ) {
			$date = \DateTimeImmutable::createFromFormat( '!' . $format, $datetime );
			if ( $date && $date->format( $format ) === $datetime ) {
				return $date->format( 'Y-m-d H:i:s' );
			}
		}

		return null;
	}

	private function table_name(): string {
		return Database::get_table_name( Database::TABLE_SUFFIX_REWARDS );
	}
}
