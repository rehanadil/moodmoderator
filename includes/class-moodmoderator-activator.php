<?php
/**
 * Fired during plugin activation.
 *
 * @package    MoodModerator
 * @subpackage MoodModerator/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class MoodModerator_Activator {

	/**
	 * Activate the plugin.
	 *
	 * Creates database tables and sets default options.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		self::save_version();
	}

	/**
	 * Create custom database tables.
	 *
	 * Creates the logs table and tone suggestions table with proper indexes.
	 *
	 * @since 1.0.0
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Table for logs
		$logs_table_name = $wpdb->prefix . 'moodmoderator_logs';

		$logs_sql = "CREATE TABLE $logs_table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			log_type varchar(50) NOT NULL,
			comment_id bigint(20) UNSIGNED NULL,
			post_id bigint(20) UNSIGNED NULL,
			message text NOT NULL,
			metadata longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY log_type (log_type),
			KEY comment_id (comment_id),
			KEY created_at (created_at)
		) $charset_collate;";

		// Table for tone suggestions
		$suggestions_table_name = $wpdb->prefix . 'moodmoderator_tone_suggestions';

		$suggestions_sql = "CREATE TABLE $suggestions_table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tone_name varchar(100) NOT NULL,
			frequency int UNSIGNED DEFAULT 1,
			first_seen datetime NOT NULL,
			last_seen datetime NOT NULL,
			status varchar(20) DEFAULT 'pending',
			PRIMARY KEY  (id),
			UNIQUE KEY tone_name (tone_name),
			KEY status (status)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $logs_sql );
		dbDelta( $suggestions_sql );
	}

	/**
	 * Set default plugin options.
	 *
	 * Sets up predefined tones and default configuration.
	 *
	 * @since 1.0.0
	 */
	private static function set_default_options() {
		// Don't override existing options on reactivation
		if ( get_option( 'moodmoderator_version' ) ) {
			return;
		}

		// Predefined tones for hybrid approach
		$predefined_tones = array( 'Friendly', 'Toxic', 'Sarcastic', 'Questioning', 'Angry', 'Neutral' );
		add_option( 'moodmoderator_predefined_tones', $predefined_tones );

		// Default strictness level (Medium)
		add_option( 'moodmoderator_strictness', 'medium' );

		// Custom tones (empty by default)
		add_option( 'moodmoderator_custom_tones', array() );

		// Enable logging by default
		add_option( 'moodmoderator_enable_logging', true );

		// Cache duration in hours (24 hours)
		add_option( 'moodmoderator_cache_duration', 24 );

		// API key (empty by default - must be configured)
		add_option( 'moodmoderator_api_key', '' );
	}

	/**
	 * Save plugin version.
	 *
	 * Stores the current version for future upgrade checks.
	 *
	 * @since 1.0.0
	 */
	private static function save_version() {
		update_option( 'moodmoderator_version', MOODMODERATOR_VERSION );
	}
}
