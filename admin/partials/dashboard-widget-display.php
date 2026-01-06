<?php
/**
 * Dashboard widget template.
 *
 * @package    MoodModerator
 * @subpackage MoodModerator/admin/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get widget data
$widget = new MoodModerator_Dashboard_Widget( new MoodModerator_Database() );
$data = $widget->get_widget_data();
?>

<div class="moodmoderator-dashboard-widget">
	<?php if ( $data['total_analyzed'] === 0 ) : ?>
		<p><?php esc_html_e( 'No comments analyzed in the last 30 days.', 'moodmoderator' ); ?></p>
	<?php else : ?>
		<div class="moodmoderator-widget-stats">
			<div class="stat">
				<span class="stat-value"><?php echo esc_html( $data['total_analyzed'] ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Analyzed', 'moodmoderator' ); ?></span>
			</div>
			<div class="stat">
				<span class="stat-value"><?php echo esc_html( $data['total_held'] ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Held for Moderation', 'moodmoderator' ); ?></span>
			</div>
		</div>

		<h4><?php esc_html_e( 'Tone Breakdown', 'moodmoderator' ); ?></h4>
		<table class="widefat">
			<tbody>
				<?php foreach ( $data['tone_breakdown'] as $tone => $count ) : ?>
					<tr>
						<td>
							<span class="moodmoderator-tone-badge moodmoderator-tone-<?php echo esc_attr( strtolower( sanitize_html_class( $tone ) ) ); ?>">
								<?php echo esc_html( $tone ); ?>
							</span>
						</td>
						<td align="right">
							<strong><?php echo esc_html( $count ); ?></strong>
							<span class="description">
								(<?php echo esc_html( round( ( $count / $data['total_analyzed'] ) * 100, 1 ) ); ?>%)
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( ! empty( $data['recent_comments'] ) ) : ?>
			<h4><?php esc_html_e( 'Recent Analyzed Comments', 'moodmoderator' ); ?></h4>
			<ul class="moodmoderator-recent-comments">
				<?php foreach ( $data['recent_comments'] as $comment ) : ?>
					<li>
						<a href="<?php echo esc_url( admin_url( 'comment.php?action=editcomment&c=' . $comment['comment_id'] ) ); ?>">
							<?php echo esc_html( $comment['content_excerpt'] ); ?>
						</a>
						<br>
						<span class="moodmoderator-tone-badge moodmoderator-tone-<?php echo esc_attr( strtolower( sanitize_html_class( $comment['tone'] ) ) ); ?>">
							<?php echo esc_html( $comment['tone'] ); ?>
						</span>
						<span class="description">
							<?php
							printf(
								/* translators: %s: Author name */
								esc_html__( 'by %s', 'moodmoderator' ),
								esc_html( $comment['author'] )
							);
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<p>
			<a href="<?php echo esc_url( admin_url( 'edit-comments.php' ) ); ?>" class="button button-small">
				<?php esc_html_e( 'View All Comments', 'moodmoderator' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=moodmoderator' ) ); ?>" class="button button-small">
				<?php esc_html_e( 'Settings', 'moodmoderator' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
