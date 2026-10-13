// What the editor says about the writing streak. Pure functions of the
// streak summary (see StreakCalculator), so they can be tested in Node.
//
// summary: { streak, unit: 'day'|'week', is_active_today, is_goal_met,
//            goal_days, period_days, days_left, next_milestone, counts_drafts }
//
// With counts_drafts, saving a draft already counts for the day, so the
// messages say "wrote" where they would say "published".

// The only module that declares __ and _n: the build keeps these names
// (config/webpack-config.js) so translate.wordpress.org finds the strings.
const { __, _n, sprintf } = window.wp.i18n;

/**
 * The pre-publish panel's title.
 *
 * @return {string} The title.
 */
export function panelTitle() {
	return __( 'Writing streak', 'inkmeter' );
}

/**
 * Shown in the pre-publish checks: what publishing this post does for the streak.
 *
 * @param {Object}  summary
 * @param {boolean} isScheduled Whether the post is scheduled for later.
 * @return {string} The message.
 */
export function prePublishMessage( summary, isScheduled ) {
	if ( isScheduled ) {
		return __( 'Scheduled posts count toward your streak on the day they are published.', 'inkmeter' );
	}

	if ( 'week' === summary.unit ) {
		if ( summary.is_goal_met ) {
			return sprintf(
				/* translators: %s: Number of weeks. */
				_n( 'This week\'s goal is already met. Your streak is %s week.', 'This week\'s goal is already met. Your streak is %s weeks.', summary.streak, 'inkmeter' ),
				summary.streak
			);
		}
		if ( summary.is_active_today && summary.counts_drafts ) {
			return sprintf(
				/* translators: 1: Days with writing so far this week. 2: Days the weekly goal asks for. */
				_n( 'You already wrote today, so publishing doesn\'t add a day: %1$s of %2$s day this week.', 'You already wrote today, so publishing doesn\'t add a day: %1$s of %2$s days this week.', summary.goal_days, 'inkmeter' ),
				summary.period_days,
				summary.goal_days
			);
		}
		if ( summary.is_active_today ) {
			return sprintf(
				/* translators: 1: Days with a post so far this week. 2: Days the weekly goal asks for. */
				_n( 'You already published today, so this post doesn\'t add a day: %1$s of %2$s day this week.', 'You already published today, so this post doesn\'t add a day: %1$s of %2$s days this week.', summary.goal_days, 'inkmeter' ),
				summary.period_days,
				summary.goal_days
			);
		}
		const days = summary.period_days + 1;
		if ( days >= summary.goal_days ) {
			return sprintf(
				/* translators: %s: Number of weeks. */
				_n( 'This post meets this week\'s goal: %s week in a row.', 'This post meets this week\'s goal: %s weeks in a row.', summary.streak + 1, 'inkmeter' ),
				summary.streak + 1
			);
		}
		return sprintf(
			/* translators: 1: Days with a post this week, counting this one. 2: Days the weekly goal asks for. */
			_n( 'This post makes it %1$s of %2$s day this week.', 'This post makes it %1$s of %2$s days this week.', summary.goal_days, 'inkmeter' ),
			days,
			summary.goal_days
		);
	}

	if ( summary.is_active_today && summary.counts_drafts ) {
		return sprintf(
			/* translators: %s: Number of days. */
			_n( 'You already wrote today. Your streak is %s day.', 'You already wrote today. Your streak is %s days.', summary.streak, 'inkmeter' ),
			summary.streak
		);
	}
	if ( summary.is_active_today ) {
		return sprintf(
			/* translators: %s: Number of days. */
			_n( 'You already published today. Your streak is %s day.', 'You already published today. Your streak is %s days.', summary.streak, 'inkmeter' ),
			summary.streak
		);
	}
	if ( 0 === summary.streak ) {
		return __( 'Publishing this today starts a new streak.', 'inkmeter' );
	}
	return sprintf(
		/* translators: %s: Number of days. */
		_n( 'Publishing this today keeps your streak going: %s day in a row.', 'Publishing this today keeps your streak going: %s days in a row.', summary.streak + 1, 'inkmeter' ),
		summary.streak + 1
	);
}

/**
 * A short line for the editor's Status panel.
 *
 * @param {Object} summary
 * @return {string} The line.
 */
export function statusLine( summary ) {
	if ( 'week' === summary.unit ) {
		const thisWeek = sprintf(
			/* translators: 1: Days with a post so far this week. 2: Days the weekly goal asks for. */
			_n( '%1$s of %2$s day this week', '%1$s of %2$s days this week', summary.goal_days, 'inkmeter' ),
			summary.period_days,
			summary.goal_days
		);
		if ( 0 === summary.streak ) {
			return thisWeek;
		}
		return sprintf(
			/* translators: 1: Streak in weeks, e.g. "6-week streak". 2: This week's progress, e.g. "2 of 3 days this week". */
			__( '%1$s · %2$s', 'inkmeter' ),
			sprintf(
				/* translators: %s: Number of weeks. */
				_n( '%s-week streak', '%s-week streak', summary.streak, 'inkmeter' ),
				summary.streak
			),
			thisWeek
		);
	}

	if ( 0 === summary.streak ) {
		return summary.counts_drafts
			? __( 'Write today to start a streak', 'inkmeter' )
			: __( 'Publish today to start a streak', 'inkmeter' );
	}
	const streak = sprintf(
		/* translators: %s: Number of days. */
		_n( '%s-day streak', '%s-day streak', summary.streak, 'inkmeter' ),
		summary.streak
	);
	if ( summary.is_active_today ) {
		return streak;
	}
	if ( summary.counts_drafts ) {
		return sprintf(
			/* translators: %s: Streak in days, e.g. "7-day streak". */
			__( '%s · write today to keep it', 'inkmeter' ),
			streak
		);
	}
	return sprintf(
		/* translators: %s: Streak in days, e.g. "7-day streak". */
		__( '%s · publish today to keep it', 'inkmeter' ),
		streak
	);
}

/**
 * The note after publishing, comparing the streak before and after;
 * null when publishing didn't change it.
 *
 * @param {Object} before
 * @param {Object} after
 * @return {?{message: string, milestone: boolean}} The note.
 */
export function celebration( before, after ) {
	const weekly = 'week' === after.unit;

	if ( after.streak > before.streak ) {
		if ( after.streak >= before.next_milestone ) {
			return {
				milestone: true,
				message: weekly
					? sprintf(
						/* translators: 1: Number of weeks. 2: Next milestone in weeks. */
						_n( '%1$s week in a row! Next milestone: %2$s weeks.', '%1$s weeks in a row! Next milestone: %2$s weeks.', after.streak, 'inkmeter' ),
						after.streak,
						after.next_milestone
					)
					: sprintf(
						/* translators: 1: Number of days. 2: Next milestone in days. */
						_n( '%1$s day in a row! Next milestone: %2$s days.', '%1$s days in a row! Next milestone: %2$s days.', after.streak, 'inkmeter' ),
						after.streak,
						after.next_milestone
					),
			};
		}
		return {
			milestone: false,
			message: weekly
				? sprintf(
					/* translators: %s: Number of weeks. */
					_n( 'This week\'s goal is met: %s week in a row.', 'This week\'s goal is met: %s weeks in a row.', after.streak, 'inkmeter' ),
					after.streak
				)
				: sprintf(
					/* translators: %s: Number of days. */
					_n( 'Your streak is now %s day.', 'Your streak is now %s days.', after.streak, 'inkmeter' ),
					after.streak
				),
		};
	}

	if ( weekly && after.period_days > before.period_days ) {
		return {
			milestone: false,
			message: sprintf(
				/* translators: 1: Days with a post this week. 2: Days the weekly goal asks for. */
				_n( '%1$s of %2$s day this week.', '%1$s of %2$s days this week.', after.goal_days, 'inkmeter' ),
				after.period_days,
				after.goal_days
			),
		};
	}

	return null;
}
