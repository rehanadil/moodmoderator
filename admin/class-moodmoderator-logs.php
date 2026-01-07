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
		$nonce = isset( $_GET['moodmoderator_logs_nonce'] )
			? sanitize_text_field( wp_unslash( $_GET['moodmoderator_logs_nonce'] ) )
			: '';
		$nonce_valid = $nonce && wp_verify_nonce( $nonce, 'moodmoderator_filter_logs' );

		$page = 1;
		if ( $nonce_valid && isset( $_GET['paged'] ) ) {
			$page = max( 1, intval( wp_unslash( $_GET['paged'] ) ) );
		}
		$offset = ( $page - 1 ) * $per_page;

		$has_filters = $nonce_valid && ( ! empty( $_GET['log_type'] ) || ! empty( $_GET['start_date'] ) || ! empty( $_GET['end_date'] ) );

		$filters = array(
			'limit'  => $per_page,
			'offset' => $offset,
		);

		// Apply filters from GET params
		if ( $has_filters && ! empty( $_GET['log_type'] ) ) {
			$filters['log_type'] = sanitize_text_field( wp_unslash( $_GET['log_type'] ) );
		}

		if ( $has_filters && ! empty( $_GET['start_date'] ) ) {
			$filters['start_date'] = sanitize_text_field( wp_unslash( $_GET['start_date'] ) );
		}

		if ( $has_filters && ! empty( $_GET['end_date'] ) ) {
			$filters['end_date'] = sanitize_text_field( wp_unslash( $_GET['end_date'] ) );
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
