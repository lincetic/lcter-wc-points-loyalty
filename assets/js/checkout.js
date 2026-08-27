( function ( $ ) {
	'use strict';

	var snapshot = null;
	var api = window.lcterWcplRewardSelection;

	function numberText( value ) {
		return Number( value ).toLocaleString( document.documentElement.lang || undefined );
	}

	function rootElement() {
		return $( '.lcter-wcpl-checkout-rewards' ).first();
	}

	function readRewards( $root ) {
		return $root.find( '.lcter-wcpl-reward-option' ).map( function () {
			var $option = $( this );
			return {
				id: $option.data( 'reward-id' ),
				cost: $option.data( 'points-cost' ),
				quantity: $option.find( 'input[type="number"]' ).val()
			};
		} ).get();
	}

	function saveSnapshot() {
		var $root = rootElement();
		if ( ! $root.length ) {
			return;
		}
		snapshot = {
			choice: $root.find( 'input[name="lcter_wcpl_redemption_choice"]:checked' ).val() || 'none',
			quantities: readRewards( $root ).reduce( function ( values, reward ) {
				values[ String( reward.id ) ] = reward.quantity;
				return values;
			}, {} )
		};
	}

	function applySnapshot( $root ) {
		if ( ! snapshot ) {
			return;
		}
		$root.find( 'input[name="lcter_wcpl_redemption_choice"][value="' + snapshot.choice + '"]' ).prop( 'checked', true );
		$root.find( '.lcter-wcpl-reward-option' ).each( function () {
			var $option = $( this );
			var id = String( $option.data( 'reward-id' ) );
			if ( Object.prototype.hasOwnProperty.call( snapshot.quantities, id ) ) {
				$option.find( 'input[type="number"]' ).val( snapshot.quantities[ id ] );
			}
		} );
	}

	function refresh( changedId, userInitiated ) {
		var $root = rootElement();
		if ( ! $root.length || ! api ) {
			return;
		}

		var redeeming = $root.find( 'input[name="lcter_wcpl_redemption_choice"]:checked' ).val() === 'redeem';
		var $catalog = $root.find( '.lcter-wcpl-reward-catalog' );
		$catalog.prop( 'hidden', ! redeeming );

		if ( ! redeeming ) {
			$root.find( '.lcter-wcpl-reward-option input[type="number"]' ).val( 0 );
		}

		var result = api.calculate( $root.data( 'balance' ), readRewards( $root ), changedId );
		result.rewards.forEach( function ( reward ) {
			var $option = $root.find( '.lcter-wcpl-reward-option[data-reward-id="' + reward.id + '"]' );
			var $input = $option.find( 'input[type="number"]' );
			$input.val( reward.quantity ).attr( 'max', reward.maximum );
			$option.toggleClass( 'is-disabled', ! reward.canIncrement ).attr( 'data-increment-disabled', reward.canIncrement ? 'false' : 'true' );
			$option.find( '.lcter-wcpl-reward-availability' ).text( reward.canIncrement ? '' : $root.data( 'insufficient-message' ) );
		} );

		$root.find( '[data-lcter-wcpl-balance]' ).text( numberText( result.balance ) );
		$root.find( '[data-lcter-wcpl-selected]' ).text( numberText( result.selected ) );
		$root.find( '[data-lcter-wcpl-remaining]' ).text( numberText( result.remaining ) );

		var $feedback = $root.find( '.lcter-wcpl-reward-feedback' );
		if ( ! userInitiated && $feedback.hasClass( 'is-error' ) && $feedback.text().trim() ) {
			return;
		}
		if ( ! redeeming || result.selected === 0 ) {
			$feedback.text( '' ).removeClass( 'is-error is-valid' );
		} else {
			$feedback.text( $root.data( 'valid-message' ) ).removeClass( 'is-error' ).addClass( 'is-valid' );
		}
	}

	$( document.body )
		.off( '.lcterWcplCheckout' )
		.on( 'change.lcterWcplCheckout input.lcterWcplCheckout', '.lcter-wcpl-reward-option input[type="number"]', function () {
			refresh( $( this ).closest( '.lcter-wcpl-reward-option' ).data( 'reward-id' ), true );
		} )
		.on( 'change.lcterWcplCheckout', 'input[name="lcter_wcpl_redemption_choice"]', function () {
			refresh( null, true );
		} )
		.on( 'update_checkout.lcterWcplCheckout', saveSnapshot )
		.on( 'updated_checkout.lcterWcplCheckout', function () {
			var $root = rootElement();
			applySnapshot( $root );
			refresh();
		} )
		.on( 'checkout_error.lcterWcplCheckout', function () {
			var $root = rootElement();
			var message = '';
			$( '.woocommerce-error li' ).each( function () {
				var candidate = $( this ).text().trim();
				if ( ! message && /puntos|regalos|canjear|recompensas/i.test( candidate ) ) {
					message = candidate;
				}
			} );
			if ( $root.length && message ) {
				$root.find( '.lcter-wcpl-reward-feedback' ).text( message ).removeClass( 'is-valid' ).addClass( 'is-error' ).trigger( 'focus' );
			}
		} );

	$( function () {
		refresh();
	} );
}( jQuery ) );
