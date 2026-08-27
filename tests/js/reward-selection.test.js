'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const selection = require( '../../assets/js/reward-selection.js' );

test( 'shared balance constrains every reward together', () => {
	const result = selection.calculate( 20000, [
		{ id: 1, cost: 8000, quantity: 2 },
		{ id: 2, cost: 5000, quantity: 0 },
		{ id: 3, cost: 4000, quantity: 0 }
	], 1 );

	assert.equal( result.selected, 16000 );
	assert.equal( result.remaining, 4000 );
	assert.equal( result.rewards[ 1 ].canIncrement, false );
	assert.equal( result.rewards[ 2 ].maximum, 1 );
} );

test( 'reducing a reward enables newly affordable rewards', () => {
	const result = selection.calculate( 20000, [
		{ id: 1, cost: 8000, quantity: 1 },
		{ id: 2, cost: 5000, quantity: 0 }
	], 1 );

	assert.equal( result.remaining, 12000 );
	assert.equal( result.rewards[ 1 ].canIncrement, true );
	assert.equal( result.rewards[ 1 ].maximum, 2 );
} );

test( 'a manipulated quantity is clamped before it can exceed balance', () => {
	const result = selection.calculate( 20000, [
		{ id: 1, cost: 8000, quantity: 2 },
		{ id: 2, cost: 5000, quantity: 1 }
	], 2 );

	assert.equal( result.rewards[ 1 ].quantity, 0 );
	assert.equal( result.selected, 16000 );
	assert.ok( result.selected <= result.balance );
} );
