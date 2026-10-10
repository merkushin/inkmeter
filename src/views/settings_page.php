<?php
/**
 * Settings → Inkmeter: connect to Inkmeter Pro and manage it.
 *
 * @var string|null $notice_type      'success', 'warning' or 'error'; null when there's no message.
 * @var string      $notice_message   Message about the last action.
 * @var bool        $is_connected
 * @var bool        $is_reachable     False when the Inkmeter API couldn't be reached.
 * @var string      $email            The connected account's email.
 * @var bool        $is_pro           Whether the account has Pro reminders.
 * @var bool        $reminder_enabled
 * @var int         $reminder_hour    0-23, in the site's timezone.
 * @var string      $timezone         The site's timezone, e.g. "America/Mexico_City" or "+03:00".
 * @var string|null $synced_ago       How long ago the days were last synced, e.g. "5 mins"; null if never.
 * @var int|null    $streak           The streak Inkmeter has, across the account's sites; null if unknown.
 * @var string      $streak_unit      What the streak counts: 'day' or 'week'.
 * @var int|null    $freezes_left     Streak freezes left this month; null without them.
 * @var string      $site_url         This site's address.
 * @var string      $privacy_url      Inkmeter's privacy policy.
 * @var string      $action_url       admin-post.php.
 * @var array{trial_days: int, plans: array<int, array{id: string, price: string}>} $offer What Inkmeter Pro costs; plans is empty when it can't be bought yet.
 * @var \Merkushin\Inkmeter\Pro\SettingsPage $this
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap">
	<h1><?php esc_html_e( 'Inkmeter', 'inkmeter' ); ?></h1>

	<?php if ( null !== $notice_type ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible"><p><?php echo esc_html( $notice_message ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! $is_connected ) : ?>
		<h2><?php esc_html_e( 'Inkmeter Pro', 'inkmeter' ); ?></h2>
		<p><?php esc_html_e( 'Never lose a streak by accident. With Inkmeter Pro:', 'inkmeter' ); ?></p>
		<ul class="ul-disc">
			<li><?php esc_html_e( 'You get an email at the hour you choose when you haven\'t published yet that day.', 'inkmeter' ); ?></li>
			<li><?php esc_html_e( 'Your streak history is kept in your Inkmeter account, so it survives reinstalls and site moves.', 'inkmeter' ); ?></li>
		</ul>
		<p>
			<?php
			printf(
				/* translators: %s: Link to Inkmeter's privacy policy. */
				esc_html__( 'Connecting sends Inkmeter this site\'s address, its timezone and the dates you published on, never your posts. Nothing is sent until you connect. %s', 'inkmeter' ),
				'<a href="' . esc_url( $privacy_url ) . '">' . esc_html__( 'Privacy policy', 'inkmeter' ) . '</a>'
			);
			?>
		</p>
		<form method="post" action="<?php echo esc_url( $action_url ); ?>">
			<input type="hidden" name="action" value="inkmeter_connect" />
			<?php wp_nonce_field( 'inkmeter_connect' ); ?>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Connect to Inkmeter', 'inkmeter' ); ?></button></p>
		</form>
	<?php else : ?>
		<?php if ( ! $is_reachable ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Inkmeter couldn\'t be reached, so some details are missing. Please reload the page in a minute.', 'inkmeter' ); ?></p></div>
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Account', 'inkmeter' ); ?></th>
				<td><?php echo esc_html( $email ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Plan', 'inkmeter' ); ?></th>
				<td><?php echo esc_html( $is_pro ? __( 'Pro', 'inkmeter' ) : __( 'Free', 'inkmeter' ) ); ?></td>
			</tr>
			<?php if ( null !== $streak ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Streak in your account', 'inkmeter' ); ?></th>
					<td>
						<?php
						if ( 'week' === $streak_unit ) {
							/* translators: %s: Number of weeks. */
							echo esc_html( sprintf( _n( '%s week', '%s weeks', $streak, 'inkmeter' ), (string) $streak ) );
						} else {
							/* translators: %s: Number of days. */
							echo esc_html( sprintf( _n( '%s day', '%s days', $streak, 'inkmeter' ), (string) $streak ) );
						}
						?>
					</td>
				</tr>
			<?php endif; ?>
			<?php if ( null !== $freezes_left ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Streak freezes', 'inkmeter' ); ?></th>
					<td>
						<?php
						/* translators: %s: Number of streak freezes. */
						echo esc_html( sprintf( _n( '%s left this month', '%s left this month', $freezes_left, 'inkmeter' ), (string) $freezes_left ) );
						?>
						<p class="description"><?php esc_html_e( 'If you miss a day (or a week, with a weekly goal), a freeze keeps your streak going. You get 2 each month.', 'inkmeter' ); ?></p>
					</td>
				</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Last synced', 'inkmeter' ); ?></th>
				<td>
					<?php
					if ( null === $synced_ago ) {
						esc_html_e( 'Not yet', 'inkmeter' );
					} else {
						/* translators: %s: Time since the last sync, e.g. "5 mins". */
						echo esc_html( sprintf( __( '%s ago', 'inkmeter' ), $synced_ago ) );
					}
					?>
				</td>
			</tr>
		</table>

		<?php if ( $is_pro ) : ?>
			<h2><?php esc_html_e( 'Reminders', 'inkmeter' ); ?></h2>
			<form method="post" action="<?php echo esc_url( $action_url ); ?>">
				<input type="hidden" name="action" value="inkmeter_reminders" />
				<?php wp_nonce_field( 'inkmeter_reminders' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Email reminder', 'inkmeter' ); ?></th>
						<td>
							<label for="inkmeter-reminder-enabled">
								<input type="checkbox" id="inkmeter-reminder-enabled" name="reminder_enabled" value="1"<?php checked( $reminder_enabled ); ?> />
								<?php
								/* translators: %s: Account email address. */
								echo esc_html( sprintf( __( 'Email %s when I haven\'t published yet', 'inkmeter' ), $email ) );
								?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="inkmeter-reminder-hour"><?php esc_html_e( 'Send it at', 'inkmeter' ); ?></label></th>
						<td>
							<select id="inkmeter-reminder-hour" name="reminder_hour">
								<?php $this->render_hour_options( $reminder_hour ); ?>
							</select>
							<p class="description">
								<?php
								/* translators: %s: The site's timezone, e.g. "America/Mexico_City". */
								echo esc_html( sprintf( __( 'In your site\'s timezone (%s).', 'inkmeter' ), $timezone ) );
								?>
							</p>
						</td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save reminders', 'inkmeter' ); ?></button></p>
			</form>

			<form method="post" action="<?php echo esc_url( $action_url ); ?>">
				<input type="hidden" name="action" value="inkmeter_manage" />
				<?php wp_nonce_field( 'inkmeter_manage' ); ?>
				<p><button type="submit" class="button"><?php esc_html_e( 'Manage subscription', 'inkmeter' ); ?></button></p>
			</form>
		<?php else : ?>
			<h2>
				<?php
				if ( $offer['trial_days'] > 0 ) {
					/* translators: %s: Number of days. */
					echo esc_html( sprintf( _n( 'Try Inkmeter Pro free for %s day', 'Try Inkmeter Pro free for %s days', $offer['trial_days'], 'inkmeter' ), (string) $offer['trial_days'] ) );
				} else {
					esc_html_e( 'Upgrade to Inkmeter Pro', 'inkmeter' );
				}
				?>
			</h2>
			<p><?php esc_html_e( 'Get an email at the hour you choose when you haven\'t published yet that day.', 'inkmeter' ); ?></p>
			<?php if ( [] === $offer['plans'] ) : ?>
				<p><?php esc_html_e( 'Inkmeter Pro can\'t be bought just yet. Please check back soon.', 'inkmeter' ); ?></p>
			<?php else : ?>
				<?php if ( $offer['trial_days'] > 0 ) : ?>
					<p class="description"><?php esc_html_e( 'Cancel before the trial ends and you won\'t be charged.', 'inkmeter' ); ?></p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( $action_url ); ?>">
					<input type="hidden" name="action" value="inkmeter_upgrade" />
					<?php wp_nonce_field( 'inkmeter_upgrade' ); ?>
					<p><?php $this->render_plan_buttons( $offer['plans'] ); ?></p>
				</form>
			<?php endif; ?>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Disconnect', 'inkmeter' ); ?></h2>
		<p>
			<?php
			/* translators: %s: This site's address. */
			echo esc_html( sprintf( __( 'Stops sending %s\'s publishing days to Inkmeter. Your streak history stays in your account, and connecting again picks it up.', 'inkmeter' ), $site_url ) );
			?>
		</p>
		<form method="post" action="<?php echo esc_url( $action_url ); ?>">
			<input type="hidden" name="action" value="inkmeter_disconnect" />
			<?php wp_nonce_field( 'inkmeter_disconnect' ); ?>
			<p><button type="submit" class="button"><?php esc_html_e( 'Disconnect', 'inkmeter' ); ?></button></p>
		</form>
	<?php endif; ?>
</div>
