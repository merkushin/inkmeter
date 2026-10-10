// Run with: npm test
import { test } from 'node:test';
import assert from 'node:assert/strict';

// Stand-ins for @wordpress/i18n: English, with %s and %1$s placeholders.
globalThis.window = {
	wp: {
		i18n: {
			__: ( text ) => text,
			_n: ( single, plural, number ) => ( 1 === number ? single : plural ),
			sprintf: ( format, ...args ) => {
				let next = 0;
				return format.replace( /%(?:(\d+)\$)?s/g, ( match, position ) => String( position ? args[ position - 1 ] : args[ next++ ] ) );
			},
		},
	},
};

const { prePublishMessage, statusLine, celebration } = await import( './messages.js' );

const daily = ( fields ) => ( { streak: 0, unit: 'day', is_active_today: false, is_goal_met: false, goal_days: 1, period_days: 0, days_left: 1, next_milestone: 3, ...fields } );
const weekly = ( fields ) => ( { streak: 0, unit: 'week', is_active_today: false, is_goal_met: false, goal_days: 3, period_days: 0, days_left: 4, next_milestone: 2, ...fields } );

test( 'pre-publish, daily goal', () => {
	assert.equal( prePublishMessage( daily( { streak: 7, next_milestone: 14 } ), false ), 'Publishing this today keeps your streak going: 8 days in a row.' );
	assert.equal( prePublishMessage( daily(), false ), 'Publishing this today starts a new streak.' );
	assert.equal( prePublishMessage( daily( { streak: 1, is_active_today: true, is_goal_met: true } ), false ), 'You already published today. Your streak is 1 day.' );
	assert.equal( prePublishMessage( daily( { streak: 7 } ), true ), 'Scheduled posts count toward your streak on the day they are published.' );
} );

test( 'pre-publish, weekly goal', () => {
	assert.equal( prePublishMessage( weekly( { streak: 5, period_days: 2 } ), false ), 'This post meets this week\'s goal: 6 weeks in a row.' );
	assert.equal( prePublishMessage( weekly( { streak: 5, period_days: 1 } ), false ), 'This post makes it 2 of 3 days this week.' );
	assert.equal( prePublishMessage( weekly( { streak: 5, period_days: 3, is_goal_met: true } ), false ), 'This week\'s goal is already met. Your streak is 5 weeks.' );
	assert.equal( prePublishMessage( weekly( { streak: 5, period_days: 1, is_active_today: true } ), false ), 'You already published today, so this post doesn\'t add a day: 1 of 3 days this week.' );
} );

test( 'status line', () => {
	assert.equal( statusLine( daily( { streak: 7 } ) ), '7-day streak · publish today to keep it' );
	assert.equal( statusLine( daily( { streak: 8, is_active_today: true } ) ), '8-day streak' );
	assert.equal( statusLine( daily() ), 'Publish today to start a streak' );
	assert.equal( statusLine( weekly( { streak: 6, period_days: 2 } ) ), '6-week streak · 2 of 3 days this week' );
	assert.equal( statusLine( weekly( { period_days: 1 } ) ), '1 of 3 days this week' );
} );

test( 'celebration after publishing', () => {
	assert.deepEqual( celebration( daily( { streak: 6, next_milestone: 7 } ), daily( { streak: 7, next_milestone: 14 } ) ), { milestone: true, message: '7 days in a row! Next milestone: 14 days.' } );
	assert.deepEqual( celebration( daily( { streak: 7, next_milestone: 14 } ), daily( { streak: 8, next_milestone: 14 } ) ), { milestone: false, message: 'Your streak is now 8 days.' } );
	assert.equal( celebration( daily( { streak: 8, is_active_today: true } ), daily( { streak: 8, is_active_today: true } ) ), null );
	assert.deepEqual( celebration( weekly( { streak: 3, next_milestone: 4, period_days: 2 } ), weekly( { streak: 4, next_milestone: 8, period_days: 3, is_goal_met: true } ) ), { milestone: true, message: '4 weeks in a row! Next milestone: 8 weeks.' } );
	assert.deepEqual( celebration( weekly( { streak: 3, period_days: 1 } ), weekly( { streak: 3, period_days: 2 } ) ), { milestone: false, message: '2 of 3 days this week.' } );
} );
