<?php
/**
 * OpenAI API integration handler.
 *
 * @package    MoodModerator
 * @subpackage MoodModerator/includes
 */

/**
 * Handles all AI operations for sentiment analysis.
 *
 * This class manages OpenAI API calls, prompt engineering, response parsing,
 * and the hybrid tone approach.
 */
class MoodModerator_AI {

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
	 * Analyze comment sentiment using OpenAI.
	 *
	 * @since 1.0.0
	 * @param string $comment_text The comment text to analyze.
	 * @param array  $context      Optional. Additional context (post title, etc).
	 * @return array {
	 *     Sentiment analysis result.
	 *
	 *     @type string $tone        Detected tone.
	 *     @type float  $confidence  Confidence score (0-1).
	 *     @type string $reasoning   AI reasoning.
	 *     @type bool   $is_new_tone Whether this is a new AI-suggested tone.
	 * }
	 */
	public function analyze_sentiment( $comment_text, $context = array() ) {
		// Check if API key is configured
		if ( ! $this->has_valid_api_key() ) {
			return array(
				'tone'        => 'Unknown',
				'confidence'  => 0.0,
				'reasoning'   => __( 'API key not configured', 'moodmoderator' ),
				'is_new_tone' => false,
			);
		}

		// Truncate very long comments to avoid token limits
		$max_length = 4000;
		$original_length = mb_strlen( $comment_text );

		if ( $original_length > $max_length ) {
			$comment_text = mb_substr( $comment_text, 0, $max_length );
			$this->database->log(
				'truncation',
				sprintf( __( 'Comment truncated from %d to %d characters', 'moodmoderator' ), $original_length, $max_length ),
				null,
				array( 'original_length' => $original_length, 'truncated_length' => $max_length )
			);
		}

		// Build the prompt
		$prompt = $this->build_prompt( $comment_text, $context );

		// Make API call
		$response = $this->make_api_call( $prompt );

		// Handle errors
		if ( is_wp_error( $response ) ) {
			$this->database->log( 'api_error', $response->get_error_message(), null, array( 'comment_text' => mb_substr( $comment_text, 0, 200 ) ) );

			return array(
				'tone'        => 'Unknown',
				'confidence'  => 0.0,
				'reasoning'   => $response->get_error_message(),
				'is_new_tone' => false,
			);
		}

		// Parse response
		$result = $this->parse_response( $response );

		// Validate tone (hybrid approach)
		$result['is_new_tone'] = $this->validate_tone( $result['tone'] );

		// Log successful API call
		$this->database->log(
			'api_call',
			sprintf( __( 'Sentiment analyzed: %s (%.2f)', 'moodmoderator' ), $result['tone'], $result['confidence'] ),
			null,
			array(
				'tone'       => $result['tone'],
				'confidence' => $result['confidence'],
				'reasoning'  => $result['reasoning'],
			)
		);

		return $result;
	}

	/**
	 * Build the prompt for OpenAI.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $comment_text The comment text.
	 * @param  array  $context      Optional context.
	 * @return array Messages array for OpenAI API.
	 */
	private function build_prompt( $comment_text, $context = array() ) {
		$predefined_tones = $this->get_predefined_tones();
		$tones_list = implode( ', ', $predefined_tones );

		$system_message = sprintf(
			__( 'You are a comment sentiment analyzer. Analyze the tone of the comment and classify it. Preferred tones: %s. If the tone does not fit any of these categories well, suggest a new appropriate tone name. Return your analysis in JSON format with the following structure: {"tone": "tone_name", "confidence": 0.95, "reasoning": "brief explanation", "is_new_tone": false}. The confidence should be a float between 0 and 1. Set is_new_tone to true only if you are suggesting a tone that is not in the preferred list.', 'moodmoderator' ),
			$tones_list
		);

		$user_message = sprintf(
			__( 'Analyze the tone of this comment:\n\n"%s"', 'moodmoderator' ),
			$comment_text
		);

		// Add context if provided
		if ( ! empty( $context['post_title'] ) ) {
			$user_message .= sprintf(
				__( '\n\nContext: This comment is on a post titled "%s".', 'moodmoderator' ),
				$context['post_title']
			);
		}

		return array(
			array(
				'role'    => 'system',
				'content' => $system_message,
			),
			array(
				'role'    => 'user',
				'content' => $user_message,
			),
		);
	}

	/**
	 * Make API call to OpenAI.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $messages Messages array for the API.
	 * @return array|WP_Error API response or error.
	 */
	private function make_api_call( $messages ) {
		$api_key = $this->get_api_key();

		if ( ! $api_key ) {
			return new WP_Error( 'no_api_key', __( 'OpenAI API key not configured', 'moodmoderator' ) );
		}

		// Check rate limiting
		if ( ! $this->check_rate_limit() ) {
			return new WP_Error( 'rate_limit', __( 'API rate limit exceeded', 'moodmoderator' ) );
		}

		$api_url = 'https://api.openai.com/v1/chat/completions';

		$body = array(
			'model'           => 'gpt-4o-mini',
			'messages'        => $messages,
			'response_format' => array( 'type' => 'json_object' ),
			'temperature'     => 0.3,
			'max_tokens'      => 200,
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'timeout' => 5,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		// Increment rate limit counter
		$this->increment_rate_limit();

		return $response;
	}

	/**
	 * Parse OpenAI API response.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array|WP_Error $response API response.
	 * @return array Parsed sentiment data.
	 */
	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'tone'       => 'Unknown',
				'confidence' => 0.0,
				'reasoning'  => $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code !== 200 ) {
			$body = wp_remote_retrieve_body( $response );
			return array(
				'tone'       => 'Unknown',
				'confidence' => 0.0,
				'reasoning'  => sprintf( __( 'API error: HTTP %d - %s', 'moodmoderator' ), $response_code, $body ),
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array(
				'tone'       => 'Unknown',
				'confidence' => 0.0,
				'reasoning'  => __( 'Invalid JSON response', 'moodmoderator' ),
			);
		}

		// Extract the AI's response content
		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return array(
				'tone'       => 'Unknown',
				'confidence' => 0.0,
				'reasoning'  => __( 'Unexpected response structure', 'moodmoderator' ),
			);
		}

		$content = json_decode( $data['choices'][0]['message']['content'], true );

		if ( ! isset( $content['tone'] ) ) {
			return array(
				'tone'       => 'Unknown',
				'confidence' => 0.0,
				'reasoning'  => __( 'Missing tone in response', 'moodmoderator' ),
			);
		}

		return array(
			'tone'       => sanitize_text_field( $content['tone'] ),
			'confidence' => isset( $content['confidence'] ) ? floatval( $content['confidence'] ) : 0.5,
			'reasoning'  => isset( $content['reasoning'] ) ? sanitize_text_field( $content['reasoning'] ) : '',
		);
	}

	/**
	 * Validate tone against predefined list (hybrid approach).
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $tone The AI-detected tone.
	 * @return bool True if this is a new AI-suggested tone, false if predefined.
	 */
	private function validate_tone( $tone ) {
		$predefined_tones = $this->get_predefined_tones();
		$approved_tones   = $this->database->get_approved_tones();
		$all_known_tones  = array_merge( $predefined_tones, $approved_tones );

		// Case-insensitive comparison
		$all_known_tones_lower = array_map( 'strtolower', $all_known_tones );
		$tone_lower = strtolower( $tone );

		if ( in_array( $tone_lower, $all_known_tones_lower, true ) ) {
			return false; // Tone is already known
		}

		// This is a new tone - save it as a suggestion
		$this->database->save_tone_suggestion( $tone );

		return true; // New tone
	}

	/**
	 * Get predefined tones.
	 *
	 * @since 1.0.0
	 * @return array Array of predefined tone names.
	 */
	public function get_predefined_tones() {
		return get_option( 'moodmoderator_predefined_tones', array( 'Friendly', 'Toxic', 'Sarcastic', 'Questioning', 'Angry', 'Neutral' ) );
	}

	/**
	 * Check if valid API key is configured.
	 *
	 * @since 1.0.0
	 * @return bool True if API key exists and is not empty.
	 */
	public function has_valid_api_key() {
		$api_key = $this->get_api_key();
		return ! empty( $api_key );
	}

	/**
	 * Get the decrypted API key.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return string The decrypted API key.
	 */
	private function get_api_key() {
		$encrypted = get_option( 'moodmoderator_api_key', '' );

		if ( empty( $encrypted ) ) {
			return '';
		}

		return $this->decrypt_api_key( $encrypted );
	}

	/**
	 * Decrypt the API key.
	 *
	 * Simple XOR encryption using WordPress salts (acceptable for MVP).
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $encrypted Encrypted API key.
	 * @return string Decrypted API key.
	 */
	private function decrypt_api_key( $encrypted ) {
		if ( empty( $encrypted ) ) {
			return '';
		}

		$salt = wp_salt( 'auth' );
		$decoded = base64_decode( $encrypted );

		if ( $decoded === false ) {
			return '';
		}

		// XOR decryption
		$decoded_length = strlen( $decoded );
		$salt_length = strlen( $salt );
		$decrypted = '';

		for ( $i = 0; $i < $decoded_length; $i++ ) {
			$decrypted .= chr( ord( $decoded[ $i ] ) ^ ord( $salt[ $i % $salt_length ] ) );
		}

		return $decrypted;
	}

	/**
	 * Check rate limiting.
	 *
	 * Limits API calls to 100 per hour to prevent excessive costs.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return bool True if under limit, false if limit exceeded.
	 */
	private function check_rate_limit() {
		$calls = get_transient( 'moodmoderator_api_calls_count' );

		if ( false === $calls ) {
			$calls = 0;
		}

		// Allow up to 100 calls per hour
		return $calls < 100;
	}

	/**
	 * Increment rate limit counter.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function increment_rate_limit() {
		$calls = get_transient( 'moodmoderator_api_calls_count' );

		if ( false === $calls ) {
			$calls = 0;
		}

		$calls++;
		set_transient( 'moodmoderator_api_calls_count', $calls, HOUR_IN_SECONDS );
	}

	/**
	 * Get current rate limit status.
	 *
	 * @since 1.0.0
	 * @return array {
	 *     Rate limit information.
	 *
	 *     @type int $calls_made Calls made in current hour.
	 *     @type int $limit      Maximum calls per hour.
	 *     @type int $remaining  Calls remaining.
	 * }
	 */
	public function get_rate_limit_status() {
		$calls = get_transient( 'moodmoderator_api_calls_count' );

		if ( false === $calls ) {
			$calls = 0;
		}

		return array(
			'calls_made' => $calls,
			'limit'      => 100,
			'remaining'  => max( 0, 100 - $calls ),
		);
	}
}
