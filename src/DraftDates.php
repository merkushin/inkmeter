<?php declare( strict_types=1 );

namespace Merkushin\Inkmeter;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the days someone worked on a post before publishing it, for sites
 * that count drafts as writing: the saved revisions of drafts, pending and
 * scheduled posts, and of published posts from before they went out, plus
 * the last save of unpublished posts, which covers sites without revisions.
 * Opening the editor alone (an auto-draft) doesn't count.
 */
class DraftDates {
	/**
	 * @return string[] Distinct days (Y-m-d, site timezone), newest first.
	 */
	public function get_dates(): array {
		global $wpdb;

		// The result is cached by Streak, like PublishedPostDates'.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$dates = $wpdb->get_col(
			"SELECT DATE(r.post_date) AS day
			FROM {$wpdb->posts} r
			INNER JOIN {$wpdb->posts} p ON p.ID = r.post_parent
			WHERE r.post_type = 'revision' AND p.post_type = 'post'
				AND ( p.post_status IN ( 'draft', 'pending', 'future' )
					OR ( p.post_status = 'publish' AND r.post_date < p.post_date ) )
			UNION
			SELECT DATE(post_modified) AS day
			FROM {$wpdb->posts}
			WHERE post_type = 'post' AND post_status IN ( 'draft', 'pending', 'future' )
			ORDER BY day DESC"
		);

		return array_map( 'strval', (array) $dates );
	}
}
