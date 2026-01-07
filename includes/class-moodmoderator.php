<?php
/**
 * The core plugin class.
 *
 * @package    MoodModerator
 * @subpackage MoodModerator/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 */
class MoodModerator {

	/**
	 * The database handler.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    MoodModerator_Database
	 */
	protected $database;

	/**
	 * The cache handler.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    MoodModerator_Cache
	 */
	protected $cache;

	/**
	 * The AI handler.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    MoodModerator_AI
	 */
	protected $ai;

	/**
	 * The comment handler.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    MoodModerator_Comment_Handler
	 */
	protected $comment_handler;

	/**
	 * The admin controller.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    MoodModerator_Admin
	 */
	protected $admin;

	/**
	 * Initialize the plugin.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->init_core_components();
		$this->define_admin_hooks();
		$this->define_comment_hooks();
		$this->check_for_upgrades();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function load_dependencies() {
		// Core functionality
		require_once MOODMODERATOR_PLUGIN_DIR . 'includes/class-moodmoderator-database.php';
		require_once MOODMODERATOR_PLUGIN_DIR . 'includes/class-moodmoderator-cache.php';
		require_once MOODMODERATOR_PLUGIN_DIR . 'includes/class-moodmoderator-ai.php';
		require_once MOODMODERATOR_PLUGIN_DIR . 'includes/class-moodmoderator-comment-handler.php';

		// Admin functionality (only load in admin context)
		if ( is_admin() ) {
			require_once MOODMODERATOR_PLUGIN_DIR . 'admin/class-moodmoderator-admin.php';
			require_once MOODMODERATOR_PLUGIN_DIR . 'admin/class-moodmoderator-settings.php';
			require_once MOODMODERATOR_PLUGIN_DIR . 'admin/class-moodmoderator-logs.php';
			require_once MOODMODERATOR_PLUGIN_DIR . 'admin/class-moodmoderator-dashboard-widget.php';
			require_once MOODMODERATOR_PLUGIN_DIR . 'admin/class-moodmoderator-comments-table.php';
			require_once MOODMODERATOR_PLUGIN_DIR . 'admin/class-moodmoderator-posts-table.php';
		}
	}

	/**
	 * Initialize core components.
	 *
	 * Creates instances of all core classes with proper dependency injection.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function init_core_components() {
		// Initialize in order of dependencies
		$this->database = new MoodModerator_Database();
		$this->cache    = new MoodModerator_Cache();
		$this->ai       = new MoodModerator_AI( $this->database );
		$this->comment_handler = new MoodModerator_Comment_Handler(
			$this->database,
			$this->cache,
			$this->ai
		);

		// Initialize admin components if in admin context
		if ( is_admin() ) {
			$this->admin = new MoodModerator_Admin( $this->database );
		}
	}

	/**
	 * Register all hooks related to admin functionality.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_admin_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		// Admin controller hooks
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_scripts' ) );
		add_action( 'admin_notices', array( $this->admin, 'display_admin_notices' ) );

		// Settings page
		$settings = new MoodModerator_Settings( $this->database );
		add_action( 'admin_menu', array( $settings, 'add_settings_page' ) );
		add_action( 'admin_init', array( $settings, 'register_settings' ) );

		// Logs page
		$logs = new MoodModerator_Logs( $this->database );
		add_action( 'admin_menu', array( $logs, 'add_logs_page' ) );

		// Dashboard widget
		$dashboard_widget = new MoodModerator_Dashboard_Widget( $this->database );
		add_action( 'wp_dashboard_setup', array( $dashboard_widget, 'register_widget' ) );

		// Comments table modifications
		$comments_table = new MoodModerator_Comments_Table( $this->database );
		add_filter( 'manage_edit-comments_columns', array( $comments_table, 'add_tone_column' ) );
		add_action( 'manage_comments_custom_column', array( $comments_table, 'display_tone_column' ), 10, 2 );
		add_action( 'restrict_manage_comments', array( $comments_table, 'add_tone_filter' ) );
		add_filter( 'comments_clauses', array( $comments_table, 'filter_comments_by_tone' ), 10, 2 );

		// Posts table modifications
		$posts_table = new MoodModerator_Posts_Table( $this->database );
		add_filter( 'manage_posts_columns', array( $posts_table, 'add_sentiment_column' ) );
		add_action( 'manage_posts_custom_column', array( $posts_table, 'display_sentiment_column' ), 10, 2 );
		add_filter( 'manage_edit-posts_sortable_columns', array( $posts_table, 'make_column_sortable' ) );
		add_action( 'pre_get_posts', array( $posts_table, 'sort_by_sentiment' ) );

		// Post edit screen meta box
		add_action( 'add_meta_boxes', array( $this->admin, 'add_sentiment_meta_box' ) );

		// AJAX handlers
		add_action( 'wp_ajax_moodmoderator_approve_tone', array( $this->admin, 'ajax_approve_tone' ) );
		add_action( 'wp_ajax_moodmoderator_reject_tone', array( $this->admin, 'ajax_reject_tone' ) );
		add_action( 'wp_ajax_moodmoderator_clear_cache', array( $this->admin, 'ajax_clear_cache' ) );
	}

	/**
	 * Register all hooks related to comment processing.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_comment_hooks() {
		// Main sentiment analysis hook (priority 11 to run after Akismet)
		add_filter( 'preprocess_comment', array( $this->comment_handler, 'process_comment' ), 11, 1 );

		// Control comment approval based on sentiment
		add_filter( 'pre_comment_approved', array( $this->comment_handler, 'filter_comment_approval' ), 10, 2 );

		// Cache invalidation on comment edit
		add_action( 'edit_comment', array( $this->comment_handler, 'invalidate_cache_on_edit' ), 10, 1 );
	}

	/**
	 * Check for database upgrades.
	 *
	 * Runs on every page load to check if database needs upgrading.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function check_for_upgrades() {
		$current_version = get_option( 'moodmoderator_version', '0.0.0' );

		if ( version_compare( $current_version, MOODMODERATOR_VERSION, '<' ) ) {
			$this->upgrade_database( $current_version );
		}
	}

	/**
	 * Upgrade database schema if needed.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $from_version The version we're upgrading from.
	 */
	private function upgrade_database( $from_version ) {
		// Future version upgrades will go here
		// Example:
		// if ( version_compare( $from_version, '1.1.0', '<' ) ) {
		//     // Upgrade to 1.1.0
		// }

		// Update version number
		update_option( 'moodmoderator_version', MOODMODERATOR_VERSION );
	}

	/**
	 * Run the plugin.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		// Plugin is initialized in constructor
		// This method exists for explicit execution if needed
	}
}
