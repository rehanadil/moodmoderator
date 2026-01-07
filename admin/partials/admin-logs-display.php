<?php
/**
 * Logs page template.
 *
 * @package    MoodModerator
 * @subpackage MoodModerator/admin/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get logs data
$logs_handler = new MoodModerator_Logs( new MoodModerator_Database() );
$logs_data = $logs_handler->get_filtered_logs();
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php
	$nonce = wp_create_nonce( 'moodmoderator_filter_logs' );
	$nonce_valid = isset( $_GET['moodmoderator_logs_nonce'] )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['moodmoderator_logs_nonce'] ) ), 'moodmoderator_filter_logs' );

	$current_log_type = $nonce_valid && isset( $_GET['log_type'] )
		? sanitize_text_field( wp_unslash( $_GET['log_type'] ) )
		: '';
	$start_date = $nonce_valid && isset( $_GET['start_date'] )
		? sanitize_text_field( wp_unslash( $_GET['start_date'] ) )
		: '';
	$end_date = $nonce_valid && isset( $_GET['end_date'] )
		? sanitize_text_field( wp_unslash( $_GET['end_date'] ) )
		: '';
	?>

	<div class="moodmoderator-logs-filters">
		<form method="get" action="">
			<input type="hidden" name="page" value="moodmoderator-logs">
			<input type="hidden" name="moodmoderator_logs_nonce" value="<?php echo esc_attr( $nonce ); ?>">

			<select name="log_type">
				<option value=""><?php esc_html_e( 'All Types', 'moodmoderator' ); ?></option>
				<option value="api_call" <?php selected( $current_log_type, 'api_call' ); ?>>
					<?php esc_html_e( 'API Calls', 'moodmoderator' ); ?>
				</option>
				<option value="api_error" <?php selected( $current_log_type, 'api_error' ); ?>>
					<?php esc_html_e( 'API Errors', 'moodmoderator' ); ?>
				</option>
				<option value="moderation_decision" <?php selected( $current_log_type, 'moderation_decision' ); ?>>
					<?php esc_html_e( 'Moderation Decisions', 'moodmoderator' ); ?>
				</option>
				<option value="cache_hit" <?php selected( $current_log_type, 'cache_hit' ); ?>>
					<?php esc_html_e( 'Cache Hits', 'moodmoderator' ); ?>
				</option>
			</select>

			<input type="date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" placeholder="<?php esc_attr_e( 'Start Date', 'moodmoderator' ); ?>">
			<input type="date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" placeholder="<?php esc_attr_e( 'End Date', 'moodmoderator' ); ?>">

			<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'moodmoderator' ); ?>">
			<a href="<?php echo esc_url( admin_url( 'tools.php?page=moodmoderator-logs' ) ); ?>" class="button">
				<?php esc_html_e( 'Clear Filters', 'moodmoderator' ); ?>
			</a>
		</form>
	</div>

	<?php if ( empty( $logs_data['logs'] ) ) : ?>
		<p><?php esc_html_e( 'No logs found.', 'moodmoderator' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 180px;"><?php esc_html_e( 'Date/Time', 'moodmoderator' ); ?></th>
					<th style="width: 120px;"><?php esc_html_e( 'Type', 'moodmoderator' ); ?></th>
					<th><?php esc_html_e( 'Message', 'moodmoderator' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Comment', 'moodmoderator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs_data['logs'] as $log ) : ?>
					<tr>
						<td><?php echo esc_html( $log->created_at ); ?></td>
						<td>
							<span class="moodmoderator-log-type moodmoderator-log-<?php echo esc_attr( $log->log_type ); ?>">
								<?php echo esc_html( $log->log_type ); ?>
							</span>
						</td>
						<td>
							<?php echo esc_html( $log->message ); ?>
							<?php if ( $log->metadata ) : ?>
								<button type="button" class="button button-small moodmoderator-view-metadata" data-metadata="<?php echo esc_attr( $log->metadata ); ?>">
									<?php esc_html_e( 'View Details', 'moodmoderator' ); ?>
								</button>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $log->comment_id ) : ?>
								<a href="<?php echo esc_url( admin_url( 'comment.php?action=editcomment&c=' . $log->comment_id ) ); ?>">
									#<?php echo esc_html( $log->comment_id ); ?>
								</a>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $logs_data['total_pages'] > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post( paginate_links( array(
						'base'      => add_query_arg(
							array(
								'moodmoderator_logs_nonce' => $nonce,
								'paged'                   => '%#%',
							)
						),
						'format'    => '',
						'prev_text' => __( '&laquo;', 'moodmoderator' ),
						'next_text' => __( '&raquo;', 'moodmoderator' ),
						'total'     => $logs_data['total_pages'],
						'current'   => $logs_data['page'],
					) ) );
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
