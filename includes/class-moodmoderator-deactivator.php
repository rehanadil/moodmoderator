<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    MoodModerator
 * @subpackage MoodModerator/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 */
class MoodModerator_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * Cleans up transients and temporary data.
	 * Note: Does NOT delete tables or options - that's handled by uninstall.php.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		self::clear_transients();
		self::clear_scheduled_events();
	}

	/**
	 * Clear all plugin transients.
	 *
	 * Removes cached data stored in transients.
	 *
	 * @since 1.0.0
	 */
	private static function clear_transients() {
		// Clear dashboard widget cache
		delete_transient( 'moodmoderator_dashboard_widget' );

		// Clear API key warning transient
		delete_transient( 'moodmoderator_no_api_key_warning' );

		// Clear rate limiting transient
		delete_transient( 'moodmoderator_api_calls_count' );
	}

	/**
	 * Clear any scheduled cron events.
	 *
	 * Removes WP-Cron events if they were scheduled.
	 *
	 * @since 1.0.0
	 */
	private static function clear_scheduled_events() {
		// Clear any scheduled hooks (for future async processing feature)
		$timestamp = wp_next_scheduled( 'moodmoderator_analyze_comment' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'moodmoderator_analyze_comment' );
		}
	}
}
