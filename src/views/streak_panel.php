<?php
/**
 * Writing streak panel shown above the Posts list.
 *
 * @var string      $accent_class
 * @var string      $status_key           One of 'start', 'on_fire', 'alive', 'out_of_reach'.
 * @var int         $streak
 * @var string      $streak_label         $streak formatted for the locale.
 * @var bool        $is_weekly            Whether the goal, and so the streak, is counted in weeks.
 * @var bool        $is_goal_met          Whether today (daily goal) or this week (weekly goal) met the goal.
 * @var int         $goal_days            Days with a post the goal asks for each week; 1 for the daily goal.
 * @var string      $goal_days_label      $goal_days formatted for the locale.
 * @var int         $period_days          Days with a post so far this week.
 * @var string      $period_days_label    $period_days formatted for the locale.
 * @var int         $days_needed          More days with a post this week's goal needs.
 * @var string      $days_needed_label    $days_needed formatted for the locale.
 * @var bool        $is_out_of_reach      Whether this week can no longer meet the weekly goal.
 * @var string|null $last_post_label      Day of the last published post, formatted for the site; null if none.
 * @var int         $next_milestone
 * @var string      $next_milestone_label $next_milestone formatted for the locale.
 * @var int         $progress             Milestone progress, 0-100.
 * @var string      $progress_label       $progress formatted for the locale.
 * @var bool        $is_panel_visible     False when the user hid the panel in Screen Options.
 * @var string|null $reminders_url        Settings page for Pro reminders; null when they're set up, dismissed or the user can't manage them.
 * @var int|null    $freezes_left         Inkmeter Pro streak freezes left this month; null without them.
 * @var string      $freezes_left_label   $freezes_left formatted for the locale.
 * @var bool        $saved_by_freeze      Whether a streak freeze covered yesterday (or last week) and kept the streak.
 * @var bool        $counts_drafts        Whether days spent on a draft count, so the panel says "wrote" rather than "published".
 * @var bool        $across_sites         Whether the streak and calendar count the account's other sites (Inkmeter Pro).
 * @var array<string, mixed> $history     HistoryCalendar::build() for the calendar.
 * @var string      $history_published    Days with a post shown in the calendar, formatted for the locale.
 * @var string      $history_days         Days shown in the calendar up to today, formatted for the locale.
 * @var bool        $history_has_frozen   Whether a streak freeze shows in the calendar.
 * @var \Merkushin\Inkmeter\Goal $goal
 * @var bool        $can_change_goal      Whether the user may change the site's goal.
 * @var string      $goal_action_url      Where the goal form posts.
 * @var \Merkushin\Inkmeter\Plugin $this
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="inkmeter-panel" class="inkmeter-panel notice <?php echo esc_attr( $accent_class ); ?>"<?php echo $is_panel_visible ? '' : ' hidden'; ?>>
	<div class="inkmeter-panel__lead">
		<div class="inkmeter-panel__eyebrow"><?php esc_html_e( 'Writing momentum', 'inkmeter' ); ?></div>
		<h2 class="inkmeter-panel__title"><?php esc_html_e( 'Protect the streak. Build the habit.', 'inkmeter' ); ?></h2>
		<p class="inkmeter-panel__description">
			<?php
			if ( 'start' === $status_key ) {
				esc_html_e( 'Start your next streak', 'inkmeter' );
			} elseif ( 'on_fire' === $status_key ) {
				if ( $is_weekly ) {
					esc_html_e( 'You met your goal this week', 'inkmeter' );
				} else {
					esc_html_e( 'You are on fire today', 'inkmeter' );
				}
			} elseif ( 'out_of_reach' === $status_key ) {
				esc_html_e( 'Not enough days are left to meet this week\'s goal. A new streak starts next week.', 'inkmeter' );
			} elseif ( $is_weekly ) {
				/* translators: %s: Number of days. */
				echo esc_html(
					$counts_drafts
						/* translators: %s: Number of days. */
						? sprintf( _n( 'Write on %s more day this week to keep it going', 'Write on %s more days this week to keep it going', $days_needed, 'inkmeter' ), $days_needed_label )
						/* translators: %s: Number of days. */
						: sprintf( _n( 'Publish on %s more day this week to keep it going', 'Publish on %s more days this week to keep it going', $days_needed, 'inkmeter' ), $days_needed_label )
				);
			} elseif ( $counts_drafts ) {
				esc_html_e( 'You are still alive, write today to keep it going', 'inkmeter' );
			} else {
				esc_html_e( 'You are still alive, publish today to keep it going', 'inkmeter' );
			}
			?>
		</p>
		<div class="inkmeter-panel__goal">
			<span><?php echo esc_html( $this->goal_label( $goal ) ); ?></span>
			<?php if ( $can_change_goal ) : ?>
				<details class="inkmeter-panel__goal-change">
					<summary><?php esc_html_e( 'Change goal', 'inkmeter' ); ?></summary>
					<form method="post" action="<?php echo esc_url( $goal_action_url ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( \Merkushin\Inkmeter\GoalForm::ACTION ); ?>" />
						<?php wp_nonce_field( \Merkushin\Inkmeter\GoalForm::ACTION ); ?>
						<label class="screen-reader-text" for="inkmeter-goal"><?php esc_html_e( 'Writing goal', 'inkmeter' ); ?></label>
						<select id="inkmeter-goal" name="<?php echo esc_attr( \Merkushin\Inkmeter\GoalForm::FIELD ); ?>">
							<?php $this->render_goal_options( $goal ); ?>
						</select>
						<label class="inkmeter-panel__drafts" for="inkmeter-count-drafts">
							<input type="checkbox" id="inkmeter-count-drafts" name="<?php echo esc_attr( \Merkushin\Inkmeter\GoalForm::DRAFTS_FIELD ); ?>" value="1"<?php checked( $counts_drafts ); ?> />
							<?php esc_html_e( 'Count days I work on a draft, not only days I publish', 'inkmeter' ); ?>
						</label>
						<button type="submit" class="button"><?php esc_html_e( 'Save goal', 'inkmeter' ); ?></button>
					</form>
				</details>
			<?php endif; ?>
		</div>
		<?php if ( $across_sites ) : ?>
			<p class="inkmeter-panel__sites"><?php esc_html_e( 'Counting posts on all your connected sites', 'inkmeter' ); ?></p>
		<?php endif; ?>
		<?php if ( null !== $freezes_left ) : ?>
			<p class="inkmeter-panel__freezes">
				<?php
				if ( $saved_by_freeze ) {
					echo esc_html( $is_weekly ? __( 'A streak freeze saved last week.', 'inkmeter' ) : __( 'A streak freeze saved yesterday.', 'inkmeter' ) );
					echo ' ';
				}
				/* translators: %s: Number of streak freezes. */
				echo esc_html( sprintf( _n( '%s streak freeze left this month', '%s streak freezes left this month', $freezes_left, 'inkmeter' ), $freezes_left_label ) );
				?>
			</p>
		<?php endif; ?>
		<?php if ( null !== $reminders_url ) : ?>
			<p class="inkmeter-panel__reminders" id="inkmeter-pro-link">
				<a href="<?php echo esc_url( $reminders_url ); ?>"><?php esc_html_e( 'Inkmeter Pro: get an email before your streak breaks', 'inkmeter' ); ?></a>
				<button type="button" class="inkmeter-panel__dismiss" id="inkmeter-pro-link-dismiss" aria-label="<?php esc_attr_e( 'Hide this link', 'inkmeter' ); ?>">&times;</button>
			</p>
		<?php endif; ?>
		<div class="inkmeter-panel__stats">
			<div class="inkmeter-panel__stat">
				<span class="inkmeter-panel__stat-label"><?php esc_html_e( 'Current run', 'inkmeter' ); ?></span>
				<span class="inkmeter-panel__stat-value">
					<?php
					if ( $is_weekly ) {
						/* translators: %s: Number of weeks. */
						echo esc_html( sprintf( _n( '%s week', '%s weeks', $streak, 'inkmeter' ), $streak_label ) );
					} else {
						/* translators: %s: Number of days. */
						echo esc_html( sprintf( _n( '%s day', '%s days', $streak, 'inkmeter' ), $streak_label ) );
					}
					?>
				</span>
			</div>
			<?php if ( $is_weekly ) : ?>
				<div class="inkmeter-panel__stat">
					<span class="inkmeter-panel__stat-label"><?php esc_html_e( 'This week', 'inkmeter' ); ?></span>
					<span class="inkmeter-panel__stat-value">
						<?php
						/* translators: 1: Days with a post so far this week. 2: Days the weekly goal asks for. */
						echo esc_html( sprintf( _n( '%1$s of %2$s day', '%1$s of %2$s days', $goal_days, 'inkmeter' ), $period_days_label, $goal_days_label ) );
						?>
					</span>
				</div>
			<?php else : ?>
				<div class="inkmeter-panel__stat">
					<span class="inkmeter-panel__stat-label"><?php echo esc_html( $counts_drafts ? __( 'Last writing day', 'inkmeter' ) : __( 'Last published', 'inkmeter' ) ); ?></span>
					<span class="inkmeter-panel__stat-value"><?php echo esc_html( $last_post_label ?? ( $counts_drafts ? __( 'No writing days yet', 'inkmeter' ) : __( 'No published posts yet', 'inkmeter' ) ) ); ?></span>
				</div>
			<?php endif; ?>
			<div class="inkmeter-panel__stat">
				<span class="inkmeter-panel__stat-label"><?php esc_html_e( 'Next milestone', 'inkmeter' ); ?></span>
				<span class="inkmeter-panel__stat-value">
					<?php
					if ( $is_weekly ) {
						/* translators: %s: Number of weeks. */
						echo esc_html( sprintf( _n( '%s week', '%s weeks', $next_milestone, 'inkmeter' ), $next_milestone_label ) );
					} else {
						/* translators: %s: Number of days. */
						echo esc_html( sprintf( _n( '%s day', '%s days', $next_milestone, 'inkmeter' ), $next_milestone_label ) );
					}
					?>
				</span>
			</div>
		</div>
	</div>
	<div class="inkmeter-panel__meta">
		<div>
			<div class="inkmeter-panel__score">
				<span class="inkmeter-panel__score-value"><?php echo esc_html( $streak_label ); ?></span>
				<span class="inkmeter-panel__score-unit">
					<?php
					if ( $is_weekly ) {
						/* translators: Unit shown after the large streak number, e.g. "6 weeks". */
						echo esc_html( _nx( 'week', 'weeks', $streak, 'streak counter unit', 'inkmeter' ) );
					} else {
						/* translators: Unit shown after the large streak number, e.g. "12 days". */
						echo esc_html( _nx( 'day', 'days', $streak, 'streak counter unit', 'inkmeter' ) );
					}
					?>
				</span>
			</div>
			<div class="inkmeter-panel__status">
				<span class="inkmeter-panel__status-dot"></span>
				<span>
					<?php
					if ( ! $is_weekly ) {
						if ( $counts_drafts ) {
							echo esc_html( $is_goal_met ? __( 'Wrote today', 'inkmeter' ) : __( 'Needs writing today', 'inkmeter' ) );
						} else {
							echo esc_html( $is_goal_met ? __( 'Published today', 'inkmeter' ) : __( 'Needs a post today', 'inkmeter' ) );
						}
					} elseif ( $is_goal_met ) {
						esc_html_e( 'Goal met this week', 'inkmeter' );
					} elseif ( $is_out_of_reach ) {
						esc_html_e( 'Next week is a fresh start', 'inkmeter' );
					} else {
						/* translators: %s: Number of days. */
						echo esc_html( sprintf( _n( '%s more day this week', '%s more days this week', $days_needed, 'inkmeter' ), $days_needed_label ) );
					}
					?>
				</span>
			</div>
		</div>
		<div>
			<div class="inkmeter-panel__progress-copy">
				<span><?php esc_html_e( 'Milestone progress', 'inkmeter' ); ?></span>
				<span>
					<?php
					/* translators: %s: Progress towards the next milestone, a number from 0 to 100. */
					echo esc_html( sprintf( __( '%s%%', 'inkmeter' ), $progress_label ) );
					?>
				</span>
			</div>
			<div class="inkmeter-panel__progress-track">
				<div class="inkmeter-panel__progress-bar" style="width:<?php echo esc_attr( (string) $progress ); ?>%;"></div>
			</div>
		</div>
	</div>
	<div class="inkmeter-panel__history">
		<div class="inkmeter-history__heading">
			<span class="inkmeter-history__title"><?php esc_html_e( 'Past year', 'inkmeter' ); ?></span>
			<span class="inkmeter-history__summary">
				<?php
				echo esc_html(
					$counts_drafts
						/* translators: 1: Days with writing (a published post or work on a draft). 2: Days in the period shown. */
						? sprintf( _n( 'Wrote on %1$s of %2$s day', 'Wrote on %1$s of %2$s days', (int) $history['days'], 'inkmeter' ), $history_published, $history_days )
						/* translators: 1: Days with a published post. 2: Days in the period shown. */
						: sprintf( _n( 'Published on %1$s of %2$s day', 'Published on %1$s of %2$s days', (int) $history['days'], 'inkmeter' ), $history_published, $history_days )
				);
				?>
			</span>
		</div>
		<div class="inkmeter-history__grid">
			<?php $this->render_history( $history ); ?>
		</div>
		<div class="inkmeter-history__legend" aria-hidden="true">
			<span><span class="inkmeter-history__day is-published"></span><?php echo esc_html( $counts_drafts ? __( 'Wrote', 'inkmeter' ) : __( 'Published', 'inkmeter' ) ); ?></span>
			<?php if ( $history_has_frozen ) : ?>
				<span><span class="inkmeter-history__day is-frozen"></span><?php esc_html_e( 'Streak freeze', 'inkmeter' ); ?></span>
			<?php endif; ?>
			<span><span class="inkmeter-history__day is-empty"></span><?php echo esc_html( $counts_drafts ? __( 'No writing', 'inkmeter' ) : __( 'No post', 'inkmeter' ) ); ?></span>
		</div>
	</div>
</div>
