<?php
/**
 * Comment processing handler.
 *
 * @package    MoodModerator
 * @subpackage MoodModerator/includes
 */

/**
 * Handles comment sentiment analysis and moderation.
 *
 * This class coordinates the cache, AI, and database to process comments
 * and apply moderation rules based on detected sentiment.
 */
class MoodModerator_Comment_Handler {

	/**
	 * Database handler.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    MoodModerator_Database
	 */
	private $database;

	/**
	 * Cache handler.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    MoodModerator_Cache
	 */
	private $cache;

	/**
	 * AI handler.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    MoodModerator_AI
	 */
	private $ai;

	/**
	 * Temporary storage for sentiment data between hooks.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private static $pending_sentiments = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param MoodModerator_Database $database Database handler instance.
	 * @param MoodModerator_Cache    $cache    Cache handler instance.
	 * @param MoodModerator_AI       $ai       AI handler instance.
	 */
	public function __construct( $database, $cache, $ai ) {
		$this->database = $database;
		$this->cache    = $cache;
		$this->ai       = $ai;
	}

	/**
	 * Process comment for sentiment analysis.
	 *
	 * Hooked to preprocess_comment filter (priority 11, after Akismet).
	 *
	 * @since 1.0.0
	 * @param array $comment_data Comment data array.
	 * @return array Modified comment data.
	 */
	public function process_comment( $comment_data ) {
		// Check if we should analyze this comment
		if ( ! $this->should_analyze_comment( $comment_data ) ) {
			return $comment_data;
		}

		// Check if API key is configured
		if ( ! $this->ai->has_valid_api_key() ) {
			// Log once per session to avoid spam
			if ( ! get_transient( 'moodmoderator_no_api_key_warning' ) ) {
				$this->database->log( 'config_error', __( 'No API key configured', 'moodmoderator' ) );
				set_transient( 'moodmoderator_no_api_key_warning', true, HOUR_IN_SECONDS );
			}
			return $comment_data;
		}

		// Generate content hash for caching
		$content_hash = $this->cache->generate_content_hash(
			$comment_data['comment_content'],
			$comment_data['comment_author_email']
		);

		// Check if we have a valid cached result
		// Note: For new comments, there won't be a comment_ID yet, so cache won't apply
		// Cache is mainly for edited comments or re-processing
		$comment_id = isset( $comment_data['comment_ID'] ) ? intval( $comment_data['comment_ID'] ) : 0;

		if ( $comment_id && $this->cache->is_cache_valid( $comment_id, $content_hash ) ) {
			$this->database->log( 'cache_hit', __( 'Used cached sentiment', 'moodmoderator' ), $comment_id );
			return $comment_data;
		}

		// Analyze sentiment with AI
		$context = array();
		if ( isset( $comment_data['comment_post_ID'] ) ) {
			$post = get_post( $comment_data['comment_post_ID'] );
			if ( $post ) {
				$context['post_title'] = $post->post_title;
			}
		}

		$sentiment = $this->ai->analyze_sentiment( $comment_data['comment_content'], $context );

		// Store sentiment data temporarily (will be saved via comment_post hook)
		// We can't save meta here because comment doesn't exist yet
		$sentiment_data = array(
			'tone'         => $sentiment['tone'],
			'confidence'   => $sentiment['confidence'],
			'reasoning'    => $sentiment['reasoning'],
			'content_hash' => $content_hash,
		);

		// Store in static variable for retrieval in save_sentiment_meta and filter_comment_approval
		// Use content hash as key for lookup
		self::$pending_sentiments[ $content_hash ] = $sentiment_data;

		// Register hook to save meta after comment is inserted
		add_action( 'comment_post', array( $this, 'save_sentiment_meta' ), 10, 2 );

		return $comment_data;
	}

	/**
	 * Filter comment approval status based on sentiment analysis.
	 *
	 * Hooked to pre_comment_approved filter.
	 *
	 * @since 1.0.0
	 * @param int|string|WP_Error $approved    The approval status.
	 * @param array               $commentdata The comment data.
	 * @return int|string|WP_Error Modified approval status.
	 */
	public function filter_comment_approval( $approved, $commentdata ) {
		// Don't override spam or WP_Error
		if ( 'spam' === $approved || is_wp_error( $approved ) ) {
			return $approved;
		}

		// Generate content hash to look up stored sentiment
		$content_hash = $this->cache->generate_content_hash(
			$commentdata['comment_content'],
			$commentdata['comment_author_email']
		);

		// Check if we have sentiment data for this comment
		if ( ! isset( self::$pending_sentiments[ $content_hash ] ) ) {
			return $approved; // No sentiment data, use WordPress default
		}

		$sentiment = self::$pending_sentiments[ $content_hash ];

		// Apply moderation rules
		$should_hold = $this->should_hold_comment( $sentiment['tone'], $sentiment['confidence'] );

		if ( $should_hold ) {
			// Hold comment for moderation
			$this->database->log(
				'moderation_decision',
				sprintf(
					/* translators: 1: detected tone, 2: confidence score */
					__( 'Comment held for moderation due to tone: %1$s (%2$.2f confidence)', 'moodmoderator' ),
					$sentiment['tone'],
					$sentiment['confidence']
				),
				null,
				array(
					'tone'       => $sentiment['tone'],
					'confidence' => $sentiment['confidence'],
					'action'     => 'hold',
				)
			);

			return 0; // Hold for moderation
		}

		return $approved; // Use WordPress default approval status
	}

	/**
	 * Save sentiment metadata after comment is posted.
	 *
	 * @since 1.0.0
	 * @param int   $comment_id       Comment ID.
	 * @param mixed $comment_approved Comment approval status.
	 */
	public function save_sentiment_meta( $comment_id, $comment_approved ) {
		// Get the comment
		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			return;
		}

		// Generate content hash to look up stored sentiment
		$content_hash = $this->cache->generate_content_hash(
			$comment->comment_content,
			$comment->comment_author_email
		);

		// Try to get sentiment from static storage
		$sentiment = false;
		if ( isset( self::$pending_sentiments[ $content_hash ] ) ) {
			$sentiment = self::$pending_sentiments[ $content_hash ];

			// Clean up after retrieval
			unset( self::$pending_sentiments[ $content_hash ] );
		}

		// Fallback: analyze now if not found in storage
		if ( ! $sentiment ) {
			$context = array();
			$post = get_post( $comment->comment_post_ID );
			if ( $post ) {
				$context['post_title'] = $post->post_title;
			}

			$analysis = $this->ai->analyze_sentiment( $comment->comment_content, $context );
			$sentiment = array(
				'tone'         => $analysis['tone'],
				'confidence'   => $analysis['confidence'],
				'reasoning'    => $analysis['reasoning'],
				'content_hash' => $content_hash,
			);
		}

		// Save to database
		$this->database->save_comment_sentiment(
			$comment_id,
			$sentiment['tone'],
			$sentiment['confidence'],
			isset( $sentiment['reasoning'] ) ? $sentiment['reasoning'] : '',
			$sentiment['content_hash']
		);
	}

	/**
	 * Determine if comment should be analyzed.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $comment_data Comment data.
	 * @return bool True if should analyze, false otherwise.
	 */
	private function should_analyze_comment( $comment_data ) {
		// Skip if already marked as spam
		if ( isset( $comment_data['comment_approved'] ) && $comment_data['comment_approved'] === 'spam' ) {
			return false;
		}

		// Skip trackbacks and pingbacks
		if ( isset( $comment_data['comment_type'] ) && in_array( $comment_data['comment_type'], array( 'trackback', 'pingback' ), true ) ) {
			return false;
		}

		// Skip empty comments
		if ( empty( $comment_data['comment_content'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Determine if comment should be held for moderation based on tone.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $tone       Detected tone.
	 * @param  float  $confidence AI confidence score.
	 * @return bool True if should hold, false otherwise.
	 */
	private function should_hold_comment( $tone, $confidence ) {
		// Only act on high-confidence predictions
		if ( $confidence < 0.7 ) {
			return false;
		}

		$strictness = get_option( 'moodmoderator_strictness', 'medium' );

		switch ( $strictness ) {
			case 'low':
				return in_array( $tone, array( 'Toxic', 'Angry' ), true );

			case 'medium':
				return in_array( $tone, array( 'Toxic', 'Angry', 'Sarcastic' ), true );

			case 'high':
				return ! in_array( $tone, array( 'Friendly', 'Questioning', 'Neutral' ), true );

			case 'custom':
				$custom_tones = get_option( 'moodmoderator_custom_tones', array() );
				return in_array( $tone, $custom_tones, true );

			default:
				return false;
		}
	}

	/**
	 * Invalidate cache when comment is edited.
	 *
	 * @since 1.0.0
	 * @param int $comment_id Comment ID.
	 */
	public function invalidate_cache_on_edit( $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			return;
		}

		// Generate new content hash
		$new_hash = $this->cache->generate_content_hash(
			$comment->comment_content,
			$comment->comment_author_email
		);

		// Get old hash
		$old_hash = get_comment_meta( $comment_id, 'moodmoderator_content_hash', true );

		// If content changed, invalidate cache and re-analyze
		if ( $new_hash !== $old_hash ) {
			$this->cache->invalidate_cache( $comment_id );

			$this->database->log(
				'cache_invalidation',
				__( 'Comment edited, cache cleared', 'moodmoderator' ),
				$comment_id
			);

			// Re-analyze the edited comment
			$this->analyze_and_save( $comment );
		}
	}

	/**
	 * Analyze and save sentiment for an existing comment.
	 *
	 * Used for edited comments and manual re-analysis.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  WP_Comment $comment Comment object.
	 */
	private function analyze_and_save( $comment ) {
		if ( ! $this->ai->has_valid_api_key() ) {
			return;
		}

		// Get post context
		$context = array();
		$post = get_post( $comment->comment_post_ID );
		if ( $post ) {
			$context['post_title'] = $post->post_title;
		}

		// Analyze
		$sentiment = $this->ai->analyze_sentiment( $comment->comment_content, $context );

		// Generate content hash
		$content_hash = $this->cache->generate_content_hash(
			$comment->comment_content,
			$comment->comment_author_email
		);

		// Save
		$this->database->save_comment_sentiment(
			$comment->comment_ID,
			$sentiment['tone'],
			$sentiment['confidence'],
			$sentiment['reasoning'],
			$content_hash
		);

		// Apply moderation rules
		$should_hold = $this->should_hold_comment( $sentiment['tone'], $sentiment['confidence'] );

		if ( $should_hold && $comment->comment_approved === '1' ) {
			// Hold previously approved comment
			wp_set_comment_status( $comment->comment_ID, 'hold' );

			$this->database->log(
				'moderation_decision',
				sprintf(
					/* translators: %s: detected tone */
					__( 'Previously approved comment held after edit due to tone: %s', 'moodmoderator' ),
					$sentiment['tone']
				),
				$comment->comment_ID,
				array(
					'tone'       => $sentiment['tone'],
					'confidence' => $sentiment['confidence'],
					'action'     => 'hold_on_edit',
				)
			);
		}
	}
}
