<?php
/**
 * Settings page template.
 *
 * @package    MoodModerator
 * @subpackage MoodModerator/admin/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get rate limit status
$ai = new MoodModerator_AI( new MoodModerator_Database() );
$rate_limit = $ai->get_rate_limit_status();

// Get cache stats
$cache = new MoodModerator_Cache();
$cache_stats = $cache->get_cache_stats();
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors(); ?>

	<div class="moodmoderator-settings-header">
		<p class="description">
			<?php esc_html_e( 'Configure MoodModerator to automatically analyze comment sentiment using OpenAI and moderate negative comments.', 'moodmoderator' ); ?>
		</p>
	</div>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'moodmoderator_settings' );
		do_settings_sections( 'moodmoderator' );
		submit_button();
		?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'System Status', 'moodmoderator' ); ?></h2>

	<table class="widefat" style="max-width: 600px;">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'API Status', 'moodmoderator' ); ?></th>
				<td>
					<?php if ( $ai->has_valid_api_key() ) : ?>
						<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
						<?php esc_html_e( 'Configured', 'moodmoderator' ); ?>
					<?php else : ?>
						<span class="dashicons dashicons-warning" style="color: orange;"></span>
						<?php esc_html_e( 'Not configured', 'moodmoderator' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Rate Limit', 'moodmoderator' ); ?></th>
				<td>
					<?php
					printf(
						/* translators: %1$d: calls made, %2$d: limit, %3$d: remaining */
						esc_html__( '%1$d / %2$d API calls this hour (%3$d remaining)', 'moodmoderator' ),
						$rate_limit['calls_made'],
						$rate_limit['limit'],
						$rate_limit['remaining']
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Cached Comments', 'moodmoderator' ); ?></th>
				<td>
					<?php
					printf(
						/* translators: %1$d: valid caches, %2$d: total, %3$d: expired */
						esc_html__( '%1$d valid / %2$d total (%3$d expired)', 'moodmoderator' ),
						$cache_stats['valid_caches'],
						$cache_stats['total_cached'],
						$cache_stats['expired_caches']
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Plugin Version', 'moodmoderator' ); ?></th>
				<td><?php echo esc_html( MOODMODERATOR_VERSION ); ?></td>
			</tr>
		</tbody>
	</table>

	<p>
		<a href="<?php echo esc_url( admin_url( 'tools.php?page=moodmoderator-logs' ) ); ?>" class="button">
			<?php esc_html_e( 'View Logs', 'moodmoderator' ); ?>
		</a>
		<button type="button" class="button" id="moodmoderator-clear-cache">
			<?php esc_html_e( 'Clear All Caches', 'moodmoderator' ); ?>
		</button>
	</p>
</div>
