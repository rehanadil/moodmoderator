<?php
/**
 * Dashboard widget handler.
 *
 * @package    MoodModerator
 * @subpackage MoodModerator/admin
 */

/**
 * Handles the dashboard widget display.
 *
 * This class manages the sentiment summary widget shown on the WordPress dashboard.
 */
class MoodModerator_Dashboard_Widget {

	/**
	 * Database handler.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    MoodModerator_Database
	 */
	private $database;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param MoodModerator_Database $database Database handler instance.
	 */
	public function __construct( $database ) {
		$this->database = $database;
	}

	/**
	 * Register the dashboard widget.
	 *
	 * @since 1.0.0
	 */
	public function register_widget() {
		wp_add_dashboard_widget(
			'moodmoderator_sentiment_widget',
			__( 'Comment Sentiment Summary (30 Days)', 'moodmoderator' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Render the dashboard widget.
	 *
	 * @since 1.0.0
	 */
	public function render_widget() {
		require_once MOODMODERATOR_PLUGIN_DIR . 'admin/partials/dashboard-widget-display.php';
	}

	/**
	 * Get widget data.
	 *
	 * Uses transient caching for performance.
	 *
	 * @since 1.0.0
	 * @return array Dashboard statistics.
	 */
	public function get_widget_data() {
		$cache_key = 'moodmoderator_dashboard_widget';
		$data = get_transient( $cache_key );

		if ( false === $data ) {
			$data = $this->database->get_dashboard_stats( 30 );
			set_transient( $cache_key, $data, HOUR_IN_SECONDS );
		}

		return $data;
	}
}
