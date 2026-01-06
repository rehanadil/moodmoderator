<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package MoodModerator
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete custom tables
$logs_table = $wpdb->prefix . 'moodmoderator_logs';
$suggestions_table = $wpdb->prefix . 'moodmoderator_tone_suggestions';

$wpdb->query( "DROP TABLE IF EXISTS $logs_table" );
$wpdb->query( "DROP TABLE IF EXISTS $suggestions_table" );

// Delete plugin options
delete_option( 'moodmoderator_api_key' );
delete_option( 'moodmoderator_strictness' );
delete_option( 'moodmoderator_custom_tones' );
delete_option( 'moodmoderator_predefined_tones' );
delete_option( 'moodmoderator_enable_logging' );
delete_option( 'moodmoderator_cache_duration' );
delete_option( 'moodmoderator_version' );

// Delete all comment meta
$wpdb->query( "DELETE FROM {$wpdb->commentmeta} WHERE meta_key LIKE 'moodmoderator_%'" );

// Delete transients
delete_transient( 'moodmoderator_dashboard_widget' );
delete_transient( 'moodmoderator_no_api_key_warning' );
delete_transient( 'moodmoderator_api_calls_count' );

// Clear any scheduled hooks
wp_clear_scheduled_hook( 'moodmoderator_analyze_comment' );
