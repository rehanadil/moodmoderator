<?php
/**
 * Logs viewer page handler.
 *
 * @package    MoodModerator
 * @subpackage MoodModerator/admin
 */

/**
 * Handles the logs viewer page.
 *
 * This class manages the Tools > MoodModerator Logs page where
 * users can view and filter plugin activity logs.
 */
class MoodModerator_Logs {

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
	 * Add logs page to WordPress admin menu.
	 *
	 * @since 1.0.0
	 */
	public function add_logs_page() {
		add_management_page(
			__( 'MoodModerator Logs', 'moodmoderator' ),
			__( 'MoodModerator Logs', 'moodmoderator' ),
			'manage_options',
			'moodmoderator-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Render the logs page.
	 *
	 * @since 1.0.0
	 */
	public function render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'moodmoderator' ) );
		}

		require_once MOODMODERATOR_PLUGIN_DIR . 'admin/partials/admin-logs-display.php';
	}

	/**
	 * Get filtered logs for display.
	 *
	 * @since 1.0.0
	 * @return array {
	 *     Logs data.
	 *
	 *     @type array $logs       Array of log objects.
	 *     @type int   $total      Total count.
	 *     @type int   $per_page   Items per page.
	 *     @type int   $page       Current page.
	 *     @type int   $total_pages Total pages.
	 * }
	 */
	public function get_filtered_logs() {
		$per_page = 50;
		$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset = ( $page - 1 ) * $per_page;

		$filters = array(
			'limit'  => $per_page,
			'offset' => $offset,
		);

		// Apply filters from GET params
		if ( ! empty( $_GET['log_type'] ) ) {
			$filters['log_type'] = sanitize_text_field( $_GET['log_type'] );
		}

		if ( ! empty( $_GET['start_date'] ) ) {
			$filters['start_date'] = sanitize_text_field( $_GET['start_date'] );
		}

		if ( ! empty( $_GET['end_date'] ) ) {
			$filters['end_date'] = sanitize_text_field( $_GET['end_date'] );
		}

		$logs = $this->database->get_logs( $filters );
		$total = $this->database->get_logs_count( $filters );

		return array(
			'logs'        => $logs,
			'total'       => $total,
			'per_page'    => $per_page,
			'page'        => $page,
			'total_pages' => ceil( $total / $per_page ),
		);
	}
}
