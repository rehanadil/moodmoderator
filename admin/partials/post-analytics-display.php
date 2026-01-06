<?php
/**
 * Post analytics meta box template.
 *
 * @package    MoodModerator
 * @subpackage MoodModerator/admin/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get post sentiment data
$database = new MoodModerator_Database();
$sentiment_data = $database->get_post_average_sentiment( $post->ID );
?>

<div class="moodmoderator-post-analytics">
	<?php if ( $sentiment_data['total_comments'] === 0 ) : ?>
		<p><?php esc_html_e( 'No comments have been analyzed for this post yet.', 'moodmoderator' ); ?></p>
	<?php else : ?>
		<div class="moodmoderator-analytics-summary">
			<p>
				<strong><?php esc_html_e( 'Total Comments Analyzed:', 'moodmoderator' ); ?></strong>
				<?php echo esc_html( $sentiment_data['total_comments'] ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Average Confidence:', 'moodmoderator' ); ?></strong>
				<?php echo esc_html( round( $sentiment_data['avg_confidence'] * 100, 1 ) ); ?>%
			</p>
			<p>
				<strong><?php esc_html_e( 'Dominant Tone:', 'moodmoderator' ); ?></strong>
				<span class="moodmoderator-tone-badge moodmoderator-tone-<?php echo esc_attr( strtolower( sanitize_html_class( $sentiment_data['dominant_tone'] ) ) ); ?>">
					<?php echo esc_html( $sentiment_data['dominant_tone'] ); ?>
				</span>
			</p>
		</div>

		<?php if ( ! empty( $sentiment_data['tone_breakdown'] ) ) : ?>
			<h4><?php esc_html_e( 'Tone Distribution', 'moodmoderator' ); ?></h4>
			<table class="widefat" style="margin-top: 10px;">
				<tbody>
					<?php foreach ( $sentiment_data['tone_breakdown'] as $tone => $count ) : ?>
						<tr>
							<td>
								<span class="moodmoderator-tone-badge moodmoderator-tone-<?php echo esc_attr( strtolower( sanitize_html_class( $tone ) ) ); ?>">
									<?php echo esc_html( $tone ); ?>
								</span>
							</td>
							<td align="right">
								<strong><?php echo esc_html( $count ); ?></strong>
								<span class="description">
									(<?php echo esc_html( round( ( $count / $sentiment_data['total_comments'] ) * 100, 1 ) ); ?>%)
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<p style="margin-top: 15px;">
			<a href="<?php echo esc_url( admin_url( 'edit-comments.php?p=' . $post->ID ) ); ?>" class="button button-small">
				<?php esc_html_e( 'View Comments', 'moodmoderator' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
