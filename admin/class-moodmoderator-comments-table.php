<?php
/**
 * Comments table modifications handler.
 *
 * @package    MoodModerator
 * @subpackage MoodModerator/admin
 */

/**
 * Handles modifications to the Comments admin table.
 *
 * This class adds the Tone column and filtering capabilities to the Comments table.
 */
class MoodModerator_Comments_Table {

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
	 * Add Tone column to Comments table.
	 *
	 * @since 1.0.0
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_tone_column( $columns ) {
		$columns['moodmoderator_tone'] = __( 'Tone', 'moodmoderator' );
		return $columns;
	}

	/**
	 * Display Tone column content.
	 *
	 * @since 1.0.0
	 * @param string $column  Column name.
	 * @param int    $comment_id Comment ID.
	 */
	public function display_tone_column( $column, $comment_id ) {
		if ( $column !== 'moodmoderator_tone' ) {
			return;
		}

		$sentiment = $this->database->get_comment_sentiment( $comment_id );

		if ( ! $sentiment ) {
			echo '<span class="moodmoderator-tone-badge moodmoderator-tone-unknown">' . esc_html__( 'Not analyzed', 'moodmoderator' ) . '</span>';
			return;
		}

		$tone = $sentiment['tone'];
		$confidence = $sentiment['confidence'];
		$tone_class = 'moodmoderator-tone-' . strtolower( sanitize_html_class( $tone ) );

		printf(
			'<span class="moodmoderator-tone-badge %s" title="%s">%s <span class="confidence">(%.0f%%)</span></span>',
			esc_attr( $tone_class ),
			esc_attr( sprintf( __( 'Confidence: %.0f%%', 'moodmoderator' ), $confidence * 100 ) ),
			esc_html( $tone ),
			$confidence * 100
		);
	}

	/**
	 * Add tone filter dropdown to Comments table.
	 *
	 * @since 1.0.0
	 */
	public function add_tone_filter() {
		global $wpdb;

		// Get all unique tones
		$tones = $wpdb->get_col(
			"SELECT DISTINCT meta_value
			FROM {$wpdb->commentmeta}
			WHERE meta_key = 'moodmoderator_tone'
			ORDER BY meta_value ASC"
		);

		if ( empty( $tones ) ) {
			return;
		}

		$selected = isset( $_GET['moodmoderator_tone_filter'] ) ? sanitize_text_field( $_GET['moodmoderator_tone_filter'] ) : '';

		echo '<select name="moodmoderator_tone_filter" id="moodmoderator_tone_filter">';
		echo '<option value="">' . esc_html__( 'All Tones', 'moodmoderator' ) . '</option>';

		foreach ( $tones as $tone ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $tone ),
				selected( $selected, $tone, false ),
				esc_html( $tone )
			);
		}

		echo '</select>';
	}

	/**
	 * Filter comments by tone.
	 *
	 * @since 1.0.0
	 * @param array $clauses  SQL clauses.
	 * @param WP_Comment_Query $query Comment query object.
	 * @return array Modified clauses.
	 */
	public function filter_comments_by_tone( $clauses, $query ) {
		global $wpdb;

		if ( ! is_admin() || ! isset( $_GET['moodmoderator_tone_filter'] ) || empty( $_GET['moodmoderator_tone_filter'] ) ) {
			return $clauses;
		}

		$tone = sanitize_text_field( $_GET['moodmoderator_tone_filter'] );

		// Add JOIN to commentmeta
		$clauses['join'] .= $wpdb->prepare(
			" INNER JOIN {$wpdb->commentmeta} AS mm_meta ON {$wpdb->comments}.comment_ID = mm_meta.comment_id AND mm_meta.meta_key = 'moodmoderator_tone' AND mm_meta.meta_value = %s",
			$tone
		);

		return $clauses;
	}
}
