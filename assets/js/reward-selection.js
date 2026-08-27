( function ( root, factory ) {
	'use strict';

	if ( typeof module === 'object' && module.exports ) {
		module.exports = factory();
	} else {
		root.lcterWcplRewardSelection = factory();
	}
}( typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	function integer( value ) {
		var parsed = Number.parseInt( value, 10 );
		return Number.isFinite( parsed ) && parsed > 0 ? parsed : 0;
	}

	function calculate( balance, rewards, changedId ) {
		var available = integer( balance );
		var normalized = rewards.map( function ( reward ) {
			return {
				id: String( reward.id ),
				cost: integer( reward.cost ),
				quantity: integer( reward.quantity )
			};
		} );

		if ( changedId !== undefined && changedId !== null ) {
			normalized.forEach( function ( reward ) {
				if ( reward.id !== String( changedId ) || reward.cost <= 0 ) {
					return;
				}
				var otherTotal = normalized.reduce( function ( total, candidate ) {
					return total + ( candidate.id === reward.id ? 0 : candidate.cost * candidate.quantity );
				}, 0 );
				reward.quantity = Math.min( reward.quantity, Math.floor( Math.max( 0, available - otherTotal ) / reward.cost ) );
			} );
		} else {
			var allocated = 0;
			normalized.forEach( function ( reward ) {
				var maximum = reward.cost > 0 ? Math.floor( Math.max( 0, available - allocated ) / reward.cost ) : 0;
				reward.quantity = Math.min( reward.quantity, maximum );
				allocated += reward.cost * reward.quantity;
			} );
		}

		var selected = normalized.reduce( function ( total, reward ) {
			return total + reward.cost * reward.quantity;
		}, 0 );
		var remaining = Math.max( 0, available - selected );

		normalized.forEach( function ( reward ) {
			reward.maximum = reward.quantity + ( reward.cost > 0 ? Math.floor( remaining / reward.cost ) : 0 );
			reward.canIncrement = reward.cost > 0 && remaining >= reward.cost;
		} );

		return {
			balance: available,
			selected: selected,
			remaining: remaining,
			rewards: normalized
		};
	}

	return { calculate: calculate };
} ) );
