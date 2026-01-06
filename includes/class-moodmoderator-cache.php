<?php
/**
 * Cache management handler.
 *
 * @package    MoodModerator
 * @subpackage MoodModerator/includes
 */

/**
 * Handles caching logic for sentiment analysis.
 *
 * This class manages content hashing, cache validation, and invalidation
 * to minimize API calls to OpenAI.
 */
class MoodModerator_Cache {

	/**
	 * Generate a content hash for caching.
	 *
	 * Creates an MD5 hash from comment content and author email to uniquely
	 * identify the comment for caching purposes.
	 *
	 * @since 1.0.0
	 * @param string $comment_text   The comment content.
	 * @param string $author_email   The comment author's email.
	 * @return string MD5 hash.
	 */
	public function generate_content_hash( $comment_text, $author_email = '' ) {
		$combined = $comment_text . '|' . $author_email;
		return md5( $combined );
	}

	/**
	 * Check if cached sentiment is still valid.
	 *
	 * Validates that:
	 * 1. Comment has sentiment data
	 * 2. Content hash matches (content hasn't changed)
	 * 3. Cache hasn't expired based on configured duration
	 *
	 * @since 1.0.0
	 * @param int    $comment_id    Comment ID.
	 * @param string $content_hash  Current content hash.
	 * @return bool True if cache is valid, false otherwise.
	 */
	public function is_cache_valid( $comment_id, $content_hash ) {
		$comment_id = intval( $comment_id );

		// Get existing sentiment data
		$existing_tone = get_comment_meta( $comment_id, 'moodmoderator_tone', true );

		if ( ! $existing_tone ) {
			return false; // No cached data
		}

		// Check if content hash matches
		$stored_hash = get_comment_meta( $comment_id, 'moodmoderator_content_hash', true );

		if ( $stored_hash !== $content_hash ) {
			return false; // Content has changed
		}

		// Check if cache has expired
		$analyzed_at = get_comment_meta( $comment_id, 'moodmoderator_analyzed_at', true );

		if ( ! $analyzed_at ) {
			return false; // No analysis timestamp
		}

		$cache_duration_hours = $this->get_cache_duration();
		$cache_expiry = strtotime( $analyzed_at ) + ( $cache_duration_hours * HOUR_IN_SECONDS );

		if ( time() > $cache_expiry ) {
			return false; // Cache has expired
		}

		return true; // Cache is valid
	}

	/**
	 * Invalidate cache for a comment.
	 *
	 * Removes all sentiment metadata, forcing re-analysis on next view.
	 *
	 * @since 1.0.0
	 * @param int $comment_id Comment ID.
	 * @return bool True on success.
	 */
	public function invalidate_cache( $comment_id ) {
		$comment_id = intval( $comment_id );

		delete_comment_meta( $comment_id, 'moodmoderator_tone' );
		delete_comment_meta( $comment_id, 'moodmoderator_confidence' );
		delete_comment_meta( $comment_id, 'moodmoderator_analyzed_at' );
		delete_comment_meta( $comment_id, 'moodmoderator_content_hash' );
		delete_comment_meta( $comment_id, 'moodmoderator_ai_reasoning' );

		return true;
	}

	/**
	 * Invalidate all comment caches.
	 *
	 * Bulk invalidation useful for clearing all caches at once.
	 *
	 * @since 1.0.0
	 * @return int Number of caches invalidated.
	 */
	public function invalidate_all_caches() {
		global $wpdb;

		// Get all comment IDs with sentiment data
		$comment_ids = $wpdb->get_col(
			"SELECT DISTINCT comment_id
			FROM {$wpdb->commentmeta}
			WHERE meta_key = 'moodmoderator_tone'"
		);

		$count = 0;
		foreach ( $comment_ids as $comment_id ) {
			$this->invalidate_cache( $comment_id );
			$count++;
		}

		return $count;
	}

	/**
	 * Get the configured cache duration in hours.
	 *
	 * @since 1.0.0
	 * @return int Cache duration in hours.
	 */
	public function get_cache_duration() {
		return intval( get_option( 'moodmoderator_cache_duration', 24 ) );
	}

	/**
	 * Set the cache duration.
	 *
	 * @since 1.0.0
	 * @param int $hours Cache duration in hours.
	 * @return bool True on success.
	 */
	public function set_cache_duration( $hours ) {
		return update_option( 'moodmoderator_cache_duration', intval( $hours ) );
	}

	/**
	 * Get cache statistics.
	 *
	 * Returns information about cached comments.
	 *
	 * @since 1.0.0
	 * @return array {
	 *     Cache statistics.
	 *
	 *     @type int $total_cached     Total comments with cached sentiment.
	 *     @type int $valid_caches     Number of valid (non-expired) caches.
	 *     @type int $expired_caches   Number of expired caches.
	 * }
	 */
	public function get_cache_stats() {
		global $wpdb;

		// Get all comments with sentiment data
		$cached_comments = $wpdb->get_results(
			"SELECT comment_id, meta_value as analyzed_at
			FROM {$wpdb->commentmeta}
			WHERE meta_key = 'moodmoderator_analyzed_at'"
		);

		$total_cached = count( $cached_comments );
		$valid_caches = 0;
		$expired_caches = 0;

		$cache_duration_hours = $this->get_cache_duration();
		$cache_expiry_threshold = time() - ( $cache_duration_hours * HOUR_IN_SECONDS );

		foreach ( $cached_comments as $cache ) {
			$analyzed_timestamp = strtotime( $cache->analyzed_at );

			if ( $analyzed_timestamp > $cache_expiry_threshold ) {
				$valid_caches++;
			} else {
				$expired_caches++;
			}
		}

		return array(
			'total_cached'   => $total_cached,
			'valid_caches'   => $valid_caches,
			'expired_caches' => $expired_caches,
		);
	}
}
