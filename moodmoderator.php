<?php
/**
 * Plugin Name:       MoodModerator
 * Plugin URI:        https://github.com/yourusername/moodmoderator
 * Description:       AI-powered comment sentiment analysis using OpenAI. Automatically moderates negative comments and provides comprehensive sentiment analytics.
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://yourwebsite.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       moodmoderator
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package MoodModerator
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'MOODMODERATOR_VERSION', '1.0.0' );

/**
 * Plugin directory path.
 */
define( 'MOODMODERATOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'MOODMODERATOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'MOODMODERATOR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_moodmoderator() {
	require_once MOODMODERATOR_PLUGIN_DIR . 'includes/class-moodmoderator-activator.php';
	MoodModerator_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_moodmoderator() {
	require_once MOODMODERATOR_PLUGIN_DIR . 'includes/class-moodmoderator-deactivator.php';
	MoodModerator_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_moodmoderator' );
register_deactivation_hook( __FILE__, 'deactivate_moodmoderator' );

/**
 * The core plugin class.
 */
require MOODMODERATOR_PLUGIN_DIR . 'includes/class-moodmoderator.php';

/**
 * Begins execution of the plugin.
 */
function run_moodmoderator() {
	$plugin = new MoodModerator();
	$plugin->run();
}
run_moodmoderator();
