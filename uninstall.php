<?php
/**
 * Removes the data Inkmeter stores when the plugin is deleted.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// A closure keeps these variables out of the global scope.
( static function (): void {
	delete_transient( 'inkmeter_summary' );
	delete_option( 'inkmeter_goal' );
	delete_option( 'inkmeter_count_drafts' );

	// Inkmeter Pro. Revoke the site's token, best effort, so it can't be used again.
	$connection = get_option( 'inkmeter_connection' );
	if ( is_array( $connection ) && ! empty( $connection['token'] ) && is_string( $connection['token'] ) ) {
		$api_url = defined( 'INKMETER_API_URL' ) ? (string) INKMETER_API_URL : 'https://api.streakfire.org';
		wp_remote_request(
			$api_url . '/v1/site',
			[
				'method'  => 'DELETE',
				'timeout' => 5,
				'headers' => [ 'Authorization' => 'Bearer ' . $connection['token'] ],
			]
		);
	}
	delete_option( 'inkmeter_connection' );
	wp_clear_scheduled_hook( 'inkmeter_sync_days' );
	wp_clear_scheduled_hook( 'inkmeter_sync_days_daily' );
} )();
