<?php
/**
 * Database operations handler.
 *
 * @package    MoodModerator
 * @subpackage MoodModerator/includes
 */

/**
 * Handles all database operations for the plugin.
 *
 * This class defines methods for CRUD operations on custom tables,
 * comment meta management, and data aggregation.
 */
class MoodModerator_Database {

	/**
	 * Log an event.
	 *
	 * @since 1.0.0
	 * @param string $type       Log type ('api_call', 'api_error', 'moderation_decision', 'cache_hit', etc).
	 * @param string $message    Log message.
	 * @param int    $comment_id Optional. Related comment ID.
	 * @param array  $metadata   Optional. Additional data to store as JSON.
	 * @return int|false The log ID on success, false on failure.
	 */
	public function log( $type, $message, $comment_id = null, $metadata = array() ) {
		// Check if logging is enabled
		if ( ! get_option( 'moodmoderator_enable_logging', true ) ) {
			return false;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'moodmoderator_logs';

		// Get post_id from comment_id if available
		$post_id = null;
		if ( $comment_id ) {
			$comment = get_comment( $comment_id );
			if ( $comment ) {
				$post_id = $comment->comment_post_ID;
			}
		}

		$result = $wpdb->insert(
			$table_name,
			array(
				'log_type'   => sanitize_text_field( $type ),
				'comment_id' => $comment_id ? intval( $comment_id ) : null,
				'post_id'    => $post_id ? intval( $post_id ) : null,
				'message'    => sanitize_text_field( $message ),
				'metadata'   => ! empty( $metadata ) ? wp_json_encode( $metadata ) : null,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get logs with optional filtering.
	 *
	 * @since 1.0.0
	 * @param array $filters {
	 *     Optional. Filter parameters.
	 *
	 *     @type string $log_type   Filter by log type.
	 *     @type int    $comment_id Filter by comment ID.
	 *     @type int    $post_id    Filter by post ID.
	 *     @type string $start_date Start date (Y-m-d format).
	 *     @type string $end_date   End date (Y-m-d format).
	 *     @type int    $limit      Number of results to return.
	 *     @type int    $offset     Offset for pagination.
	 * }
	 * @return array Array of log objects.
	 */
	public function get_logs( $filters = array() ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'moodmoderator_logs';

		$where = array( '1=1' );
		$values = array();

		// Build WHERE clause
		if ( ! empty( $filters['log_type'] ) ) {
			$where[] = 'log_type = %s';
			$values[] = $filters['log_type'];
		}

		if ( ! empty( $filters['comment_id'] ) ) {
			$where[] = 'comment_id = %d';
			$values[] = intval( $filters['comment_id'] );
		}

		if ( ! empty( $filters['post_id'] ) ) {
			$where[] = 'post_id = %d';
			$values[] = intval( $filters['post_id'] );
		}

		if ( ! empty( $filters['start_date'] ) ) {
			$where[] = 'created_at >= %s';
			$values[] = $filters['start_date'] . ' 00:00:00';
		}

		if ( ! empty( $filters['end_date'] ) ) {
			$where[] = 'created_at <= %s';
			$values[] = $filters['end_date'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );

		// Build query
		$query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY created_at DESC";

		// Add LIMIT and OFFSET
		if ( isset( $filters['limit'] ) ) {
			$query .= $wpdb->prepare( ' LIMIT %d', intval( $filters['limit'] ) );
		}

		if ( isset( $filters['offset'] ) ) {
			$query .= $wpdb->prepare( ' OFFSET %d', intval( $filters['offset'] ) );
		}

		// Prepare and execute query
		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		return $wpdb->get_results( $query );
	}

	/**
	 * Get total count of logs (for pagination).
	 *
	 * @since 1.0.0
	 * @param array $filters Optional. Same filters as get_logs().
	 * @return int Total count.
	 */
	public function get_logs_count( $filters = array() ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'moodmoderator_logs';

		$where = array( '1=1' );
		$values = array();

		// Build WHERE clause (same as get_logs)
		if ( ! empty( $filters['log_type'] ) ) {
			$where[] = 'log_type = %s';
			$values[] = $filters['log_type'];
		}

		if ( ! empty( $filters['comment_id'] ) ) {
			$where[] = 'comment_id = %d';
			$values[] = intval( $filters['comment_id'] );
		}

		if ( ! empty( $filters['post_id'] ) ) {
			$where[] = 'post_id = %d';
			$values[] = intval( $filters['post_id'] );
		}

		if ( ! empty( $filters['start_date'] ) ) {
			$where[] = 'created_at >= %s';
			$values[] = $filters['start_date'] . ' 00:00:00';
		}

		if ( ! empty( $filters['end_date'] ) ) {
			$where[] = 'created_at <= %s';
			$values[] = $filters['end_date'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );
		$query = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		return intval( $wpdb->get_var( $query ) );
	}

	/**
	 * Save comment sentiment data.
	 *
	 * @since 1.0.0
	 * @param int    $comment_id Comment ID.
	 * @param string $tone       Detected tone.
	 * @param float  $confidence AI confidence score (0-1).
	 * @param string $reasoning  AI reasoning text.
	 * @param string $content_hash Content hash for cache invalidation.
	 * @return bool True on success, false on failure.
	 */
	public function save_comment_sentiment( $comment_id, $tone, $confidence, $reasoning = '', $content_hash = '' ) {
		$comment_id = intval( $comment_id );

		// Save all meta fields
		update_comment_meta( $comment_id, 'moodmoderator_tone', sanitize_text_field( $tone ) );
		update_comment_meta( $comment_id, 'moodmoderator_confidence', floatval( $confidence ) );
		update_comment_meta( $comment_id, 'moodmoderator_analyzed_at', current_time( 'mysql' ) );

		if ( $reasoning ) {
			update_comment_meta( $comment_id, 'moodmoderator_ai_reasoning', sanitize_text_field( $reasoning ) );
		}

		if ( $content_hash ) {
			update_comment_meta( $comment_id, 'moodmoderator_content_hash', sanitize_text_field( $content_hash ) );
		}

		return true;
	}

	/**
	 * Get comment sentiment data.
	 *
	 * @since 1.0.0
	 * @param int $comment_id Comment ID.
	 * @return array|false Sentiment data array or false if not found.
	 */
	public function get_comment_sentiment( $comment_id ) {
		$comment_id = intval( $comment_id );

		$tone = get_comment_meta( $comment_id, 'moodmoderator_tone', true );

		if ( ! $tone ) {
			return false;
		}

		return array(
			'tone'         => $tone,
			'confidence'   => floatval( get_comment_meta( $comment_id, 'moodmoderator_confidence', true ) ),
			'analyzed_at'  => get_comment_meta( $comment_id, 'moodmoderator_analyzed_at', true ),
			'reasoning'    => get_comment_meta( $comment_id, 'moodmoderator_ai_reasoning', true ),
			'content_hash' => get_comment_meta( $comment_id, 'moodmoderator_content_hash', true ),
		);
	}

	/**
	 * Get average sentiment for a post.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return array {
	 *     Average sentiment data.
	 *
	 *     @type float  $avg_confidence Average confidence score.
	 *     @type int    $total_comments Total comments analyzed.
	 *     @type string $dominant_tone  Most common tone.
	 *     @type array  $tone_breakdown Breakdown by tone.
	 * }
	 */
	public function get_post_average_sentiment( $post_id ) {
		$post_id = intval( $post_id );

		// Get all approved comments for this post
		$comments = get_comments( array(
			'post_id' => $post_id,
			'status'  => 'approve',
		) );

		if ( empty( $comments ) ) {
			return array(
				'avg_confidence'  => 0,
				'total_comments'  => 0,
				'dominant_tone'   => '',
				'tone_breakdown'  => array(),
			);
		}

		$comment_ids = wp_list_pluck( $comments, 'comment_ID' );

		// Prime meta cache
		update_meta_cache( 'comment', $comment_ids );

		$total_confidence = 0;
		$analyzed_count   = 0;
		$tone_counts      = array();

		foreach ( $comments as $comment ) {
			$tone = get_comment_meta( $comment->comment_ID, 'moodmoderator_tone', true );

			if ( ! $tone ) {
				continue;
			}

			$confidence = floatval( get_comment_meta( $comment->comment_ID, 'moodmoderator_confidence', true ) );

			$total_confidence += $confidence;
			$analyzed_count++;

			if ( ! isset( $tone_counts[ $tone ] ) ) {
				$tone_counts[ $tone ] = 0;
			}
			$tone_counts[ $tone ]++;
		}

		// Find dominant tone
		$dominant_tone = '';
		if ( ! empty( $tone_counts ) ) {
			arsort( $tone_counts );
			$dominant_tone = array_key_first( $tone_counts );
		}

		return array(
			'avg_confidence'  => $analyzed_count > 0 ? round( $total_confidence / $analyzed_count, 2 ) : 0,
			'total_comments'  => $analyzed_count,
			'dominant_tone'   => $dominant_tone,
			'tone_breakdown'  => $tone_counts,
		);
	}

	/**
	 * Get dashboard statistics for the last N days.
	 *
	 * @since 1.0.0
	 * @param int $days Number of days to look back (default 30).
	 * @return array {
	 *     Dashboard statistics.
	 *
	 *     @type int   $total_analyzed  Total comments analyzed.
	 *     @type int   $total_held      Total comments held for moderation.
	 *     @type array $tone_breakdown  Count by tone.
	 *     @type array $recent_comments Recent analyzed comments.
	 * }
	 */
	public function get_dashboard_stats( $days = 30 ) {
		global $wpdb;

		// Get all comments from the last N days with sentiment data
		$date_threshold = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$comments = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.comment_ID, c.comment_post_ID, c.comment_author, c.comment_content, c.comment_approved
			FROM {$wpdb->comments} c
			INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
			WHERE cm.meta_key = 'moodmoderator_tone'
			AND c.comment_date >= %s
			ORDER BY c.comment_date DESC",
			$date_threshold
		) );

		if ( empty( $comments ) ) {
			return array(
				'total_analyzed'  => 0,
				'total_held'      => 0,
				'tone_breakdown'  => array(),
				'recent_comments' => array(),
			);
		}

		// Prime meta cache
		$comment_ids = wp_list_pluck( $comments, 'comment_ID' );
		update_meta_cache( 'comment', $comment_ids );

		$total_held = 0;
		$tone_counts = array();
		$recent_comments = array();

		foreach ( $comments as $comment ) {
			$tone = get_comment_meta( $comment->comment_ID, 'moodmoderator_tone', true );

			if ( ! isset( $tone_counts[ $tone ] ) ) {
				$tone_counts[ $tone ] = 0;
			}
			$tone_counts[ $tone ]++;

			// Count held comments (status 0 or 'hold')
			if ( $comment->comment_approved === '0' || $comment->comment_approved === 'hold' ) {
				$total_held++;
			}

			// Collect recent comments (limit to 10)
			if ( count( $recent_comments ) < 10 ) {
				$recent_comments[] = array(
					'comment_id'      => $comment->comment_ID,
					'post_id'         => $comment->comment_post_ID,
					'author'          => $comment->comment_author,
					'content_excerpt' => wp_trim_words( $comment->comment_content, 20 ),
					'tone'            => $tone,
					'confidence'      => floatval( get_comment_meta( $comment->comment_ID, 'moodmoderator_confidence', true ) ),
				);
			}
		}

		// Sort tone counts
		arsort( $tone_counts );

		return array(
			'total_analyzed'  => count( $comments ),
			'total_held'      => $total_held,
			'tone_breakdown'  => $tone_counts,
			'recent_comments' => $recent_comments,
		);
	}

	/**
	 * Save a new tone suggestion.
	 *
	 * @since 1.0.0
	 * @param string $tone_name The AI-suggested tone name.
	 * @return int|false The suggestion ID on success, false on failure.
	 */
	public function save_tone_suggestion( $tone_name ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'moodmoderator_tone_suggestions';

		$tone_name = sanitize_text_field( $tone_name );

		// Check if tone already exists
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE tone_name = %s",
			$tone_name
		) );

		if ( $existing ) {
			// Update frequency and last_seen
			$wpdb->update(
				$table_name,
				array(
					'frequency' => intval( $existing->frequency ) + 1,
					'last_seen' => current_time( 'mysql' ),
				),
				array( 'id' => $existing->id ),
				array( '%d', '%s' ),
				array( '%d' )
			);

			return $existing->id;
		} else {
			// Insert new suggestion
			$result = $wpdb->insert(
				$table_name,
				array(
					'tone_name'  => $tone_name,
					'frequency'  => 1,
					'first_seen' => current_time( 'mysql' ),
					'last_seen'  => current_time( 'mysql' ),
					'status'     => 'pending',
				),
				array( '%s', '%d', '%s', '%s', '%s' )
			);

			return $result ? $wpdb->insert_id : false;
		}
	}

	/**
	 * Get tone suggestions by status.
	 *
	 * @since 1.0.0
	 * @param string $status Optional. Filter by status ('pending', 'approved', 'rejected'). Default 'pending'.
	 * @return array Array of suggestion objects.
	 */
	public function get_tone_suggestions( $status = 'pending' ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'moodmoderator_tone_suggestions';

		$query = $wpdb->prepare(
			"SELECT * FROM $table_name WHERE status = %s ORDER BY frequency DESC, last_seen DESC",
			$status
		);

		return $wpdb->get_results( $query );
	}

	/**
	 * Update tone suggestion status.
	 *
	 * @since 1.0.0
	 * @param int    $suggestion_id Suggestion ID.
	 * @param string $status        New status ('approved', 'rejected').
	 * @return bool True on success, false on failure.
	 */
	public function update_tone_suggestion_status( $suggestion_id, $status ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'moodmoderator_tone_suggestions';

		$result = $wpdb->update(
			$table_name,
			array( 'status' => sanitize_text_field( $status ) ),
			array( 'id' => intval( $suggestion_id ) ),
			array( '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Get approved tone names.
	 *
	 * @since 1.0.0
	 * @return array Array of approved tone names.
	 */
	public function get_approved_tones() {
		$suggestions = $this->get_tone_suggestions( 'approved' );
		return wp_list_pluck( $suggestions, 'tone_name' );
	}

	/**
	 * Clear old logs.
	 *
	 * @since 1.0.0
	 * @param int $days Delete logs older than this many days.
	 * @return int Number of rows deleted.
	 */
	public function clear_old_logs( $days = 90 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'moodmoderator_logs';

		$date_threshold = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		return $wpdb->query( $wpdb->prepare(
			"DELETE FROM $table_name WHERE created_at < %s",
			$date_threshold
		) );
	}
}
