<?php
/**
 * Admin area controller.
 *
 * @package    MoodModerator
 * @subpackage MoodModerator/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for enqueuing
 * the admin-specific stylesheet and JavaScript.
 */
class MoodModerator_Admin {

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
	 * Register the stylesheets for the admin area.
	 *
	 * @since 1.0.0
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_styles( $hook_suffix ) {
		// Only load on relevant pages
		$relevant_pages = array(
			'settings_page_moodmoderator',
			'tools_page_moodmoderator-logs',
			'edit-comments.php',
			'edit.php',
			'post.php',
			'index.php', // Dashboard
		);

		if ( in_array( $hook_suffix, $relevant_pages, true ) || strpos( $hook_suffix, 'moodmoderator' ) !== false ) {
			wp_enqueue_style(
				'moodmoderator-admin',
				MOODMODERATOR_PLUGIN_URL . 'admin/css/moodmoderator-admin.css',
				array(),
				MOODMODERATOR_VERSION,
				'all'
			);
		}
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since 1.0.0
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		// Only load on relevant pages
		$relevant_pages = array(
			'settings_page_moodmoderator',
			'tools_page_moodmoderator-logs',
		);

		if ( in_array( $hook_suffix, $relevant_pages, true ) || strpos( $hook_suffix, 'moodmoderator' ) !== false ) {
			wp_enqueue_script(
				'moodmoderator-admin',
				MOODMODERATOR_PLUGIN_URL . 'assets/js/moodmoderator-admin.js',
				array(), // No dependencies - vanilla JavaScript only
				MOODMODERATOR_VERSION,
				true
			);

			// Localize script with AJAX URL and nonce
			wp_localize_script(
				'moodmoderator-admin',
				'moodModeratorData',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'moodmoderator_ajax_nonce' ),
					'strings' => array(
						'confirmClearCache' => __( 'Are you sure you want to clear all sentiment caches? This will re-analyze comments on next view.', 'moodmoderator' ),
						'success'           => __( 'Success', 'moodmoderator' ),
						'error'             => __( 'Error', 'moodmoderator' ),
						'processing'        => __( 'Processing...', 'moodmoderator' ),
						'approve'           => __( 'Approve', 'moodmoderator' ),
						'reject'            => __( 'Reject', 'moodmoderator' ),
						'approvedLabel'     => __( 'Approved', 'moodmoderator' ),
						'rejectedLabel'     => __( 'Rejected', 'moodmoderator' ),
						'clearing'          => __( 'Clearing...', 'moodmoderator' ),
						'clearAllCaches'    => __( 'Clear All Caches', 'moodmoderator' ),
						'errorOccurred'     => __( 'An error occurred. Please try again.', 'moodmoderator' ),
						'approveFailed'     => __( 'Failed to approve tone', 'moodmoderator' ),
						'rejectFailed'      => __( 'Failed to reject tone', 'moodmoderator' ),
						'clearFailed'       => __( 'Failed to clear cache', 'moodmoderator' ),
					),
				)
			);
		}
	}

	/**
	 * Display admin notices.
	 *
	 * @since 1.0.0
	 */
	public function display_admin_notices() {
		// Check if API key is configured
		$api_key = get_option( 'moodmoderator_api_key', '' );

		if ( empty( $api_key ) ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %s: Settings page URL */
						wp_kses_post( __( '<strong>MoodModerator:</strong> Please configure your OpenAI API key in <a href="%s">Settings &gt; MoodModerator</a>.', 'moodmoderator' ) ),
						esc_url( admin_url( 'options-general.php?page=moodmoderator' ) )
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Add sentiment analytics meta box to post edit screen.
	 *
	 * @since 1.0.0
	 */
	public function add_sentiment_meta_box() {
		add_meta_box(
			'moodmoderator_sentiment_analytics',
			__( 'Comment Sentiment Analytics', 'moodmoderator' ),
			array( $this, 'render_sentiment_meta_box' ),
			'post',
			'side',
			'default'
		);
	}

	/**
	 * Render the sentiment analytics meta box.
	 *
	 * @since 1.0.0
	 * @param WP_Post $post The post object.
	 */
	public function render_sentiment_meta_box( $post ) {
		require_once MOODMODERATOR_PLUGIN_DIR . 'admin/partials/post-analytics-display.php';
	}

	/**
	 * AJAX handler to approve a tone suggestion.
	 *
	 * @since 1.0.0
	 */
	public function ajax_approve_tone() {
		// Security check
		check_ajax_referer( 'moodmoderator_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'moodmoderator' ) ) );
		}

		$suggestion_id = isset( $_POST['suggestion_id'] ) ? intval( $_POST['suggestion_id'] ) : 0;

		if ( ! $suggestion_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid suggestion ID', 'moodmoderator' ) ) );
		}

		$result = $this->database->update_tone_suggestion_status( $suggestion_id, 'approved' );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Tone approved', 'moodmoderator' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to approve tone', 'moodmoderator' ) ) );
		}
	}

	/**
	 * AJAX handler to reject a tone suggestion.
	 *
	 * @since 1.0.0
	 */
	public function ajax_reject_tone() {
		// Security check
		check_ajax_referer( 'moodmoderator_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'moodmoderator' ) ) );
		}

		$suggestion_id = isset( $_POST['suggestion_id'] ) ? intval( $_POST['suggestion_id'] ) : 0;

		if ( ! $suggestion_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid suggestion ID', 'moodmoderator' ) ) );
		}

		$result = $this->database->update_tone_suggestion_status( $suggestion_id, 'rejected' );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Tone rejected', 'moodmoderator' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to reject tone', 'moodmoderator' ) ) );
		}
	}

	/**
	 * AJAX handler to clear all sentiment caches.
	 *
	 * @since 1.0.0
	 */
	public function ajax_clear_cache() {
		// Security check
		check_ajax_referer( 'moodmoderator_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'moodmoderator' ) ) );
		}

		$cache = new MoodModerator_Cache();
		$count = $cache->invalidate_all_caches();

		$this->database->log(
			'cache_clear',
			sprintf(
				/* translators: %d: number of comments affected */
				__( 'Admin cleared all sentiment caches (%d comments affected)', 'moodmoderator' ),
				$count
			)
		);

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: Number of caches cleared */
				__( 'Successfully cleared %d sentiment caches', 'moodmoderator' ),
				$count
			),
			'count'   => $count,
		) );
	}
}
