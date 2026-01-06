<?php
/**
 * Posts table modifications handler.
 *
 * @package    MoodModerator
 * @subpackage MoodModerator/admin
 */

/**
 * Handles modifications to the Posts admin table.
 *
 * This class adds the Average Sentiment column to the Posts table.
 */
class MoodModerator_Posts_Table {

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
	 * Add Average Sentiment column to Posts table.
	 *
	 * @since 1.0.0
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_sentiment_column( $columns ) {
		$columns['moodmoderator_sentiment'] = __( 'Avg Sentiment', 'moodmoderator' );
		return $columns;
	}

	/**
	 * Display Average Sentiment column content.
	 *
	 * @since 1.0.0
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function display_sentiment_column( $column, $post_id ) {
		if ( $column !== 'moodmoderator_sentiment' ) {
			return;
		}

		$sentiment_data = $this->database->get_post_average_sentiment( $post_id );

		if ( $sentiment_data['total_comments'] === 0 ) {
			echo '<span class="moodmoderator-sentiment-none">' . esc_html__( 'No comments', 'moodmoderator' ) . '</span>';
			return;
		}

		$dominant_tone = $sentiment_data['dominant_tone'];
		$avg_confidence = $sentiment_data['avg_confidence'];
		$total_comments = $sentiment_data['total_comments'];
		$tone_class = 'moodmoderator-tone-' . strtolower( sanitize_html_class( $dominant_tone ) );

		printf(
			'<span class="moodmoderator-tone-badge %s" title="%s">%s <span class="count">(%d)</span></span>',
			esc_attr( $tone_class ),
			esc_attr( sprintf( __( '%d comments analyzed, %.0f%% avg confidence', 'moodmoderator' ), $total_comments, $avg_confidence * 100 ) ),
			esc_html( $dominant_tone ),
			$total_comments
		);
	}

	/**
	 * Make the sentiment column sortable.
	 *
	 * @since 1.0.0
	 * @param array $columns Sortable columns.
	 * @return array Modified columns.
	 */
	public function make_column_sortable( $columns ) {
		$columns['moodmoderator_sentiment'] = 'moodmoderator_sentiment';
		return $columns;
	}

	/**
	 * Handle sorting by sentiment.
	 *
	 * @since 1.0.0
	 * @param WP_Query $query The query object.
	 */
	public function sort_by_sentiment( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( $orderby === 'moodmoderator_sentiment' ) {
			// Note: Sorting by sentiment is complex as it requires calculating averages
			// For now, we'll sort by comment count as a proxy
			$query->set( 'orderby', 'comment_count' );
		}
	}
}
