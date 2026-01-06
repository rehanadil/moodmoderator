<?php
/**
 * Settings page handler.
 *
 * @package    MoodModerator
 * @subpackage MoodModerator/admin
 */

/**
 * Handles the plugin settings page.
 *
 * This class manages the Settings > MoodModerator page where
 * users configure the plugin.
 */
class MoodModerator_Settings {

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
	 * Add settings page to WordPress admin menu.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'MoodModerator Settings', 'moodmoderator' ),
			__( 'MoodModerator', 'moodmoderator' ),
			'manage_options',
			'moodmoderator',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		// Register settings group
		register_setting(
			'moodmoderator_settings',
			'moodmoderator_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
			)
		);

		register_setting(
			'moodmoderator_settings',
			'moodmoderator_strictness',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_strictness' ),
				'default'           => 'medium',
			)
		);

		register_setting(
			'moodmoderator_settings',
			'moodmoderator_custom_tones',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_custom_tones' ),
				'default'           => array(),
			)
		);

		register_setting(
			'moodmoderator_settings',
			'moodmoderator_enable_logging',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		register_setting(
			'moodmoderator_settings',
			'moodmoderator_cache_duration',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 24,
			)
		);

		// Add settings sections
		add_settings_section(
			'moodmoderator_api_section',
			__( 'OpenAI API Configuration', 'moodmoderator' ),
			array( $this, 'render_api_section' ),
			'moodmoderator'
		);

		add_settings_section(
			'moodmoderator_moderation_section',
			__( 'Moderation Settings', 'moodmoderator' ),
			array( $this, 'render_moderation_section' ),
			'moodmoderator'
		);

		add_settings_section(
			'moodmoderator_advanced_section',
			__( 'Advanced Settings', 'moodmoderator' ),
			array( $this, 'render_advanced_section' ),
			'moodmoderator'
		);

		add_settings_section(
			'moodmoderator_tone_management_section',
			__( 'Tone Management', 'moodmoderator' ),
			array( $this, 'render_tone_management_section' ),
			'moodmoderator'
		);

		// Add settings fields
		add_settings_field(
			'moodmoderator_api_key',
			__( 'OpenAI API Key', 'moodmoderator' ),
			array( $this, 'render_api_key_field' ),
			'moodmoderator',
			'moodmoderator_api_section'
		);

		add_settings_field(
			'moodmoderator_strictness',
			__( 'Strictness Level', 'moodmoderator' ),
			array( $this, 'render_strictness_field' ),
			'moodmoderator',
			'moodmoderator_moderation_section'
		);

		add_settings_field(
			'moodmoderator_custom_tones',
			__( 'Custom Tones to Hold', 'moodmoderator' ),
			array( $this, 'render_custom_tones_field' ),
			'moodmoderator',
			'moodmoderator_moderation_section'
		);

		add_settings_field(
			'moodmoderator_enable_logging',
			__( 'Enable Logging', 'moodmoderator' ),
			array( $this, 'render_logging_field' ),
			'moodmoderator',
			'moodmoderator_advanced_section'
		);

		add_settings_field(
			'moodmoderator_cache_duration',
			__( 'Cache Duration (hours)', 'moodmoderator' ),
			array( $this, 'render_cache_duration_field' ),
			'moodmoderator',
			'moodmoderator_advanced_section'
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'moodmoderator' ) );
		}

		require_once MOODMODERATOR_PLUGIN_DIR . 'admin/partials/admin-settings-display.php';
	}

	/**
	 * Render API section description.
	 *
	 * @since 1.0.0
	 */
	public function render_api_section() {
		echo '<p>' . esc_html__( 'Configure your OpenAI API credentials for sentiment analysis.', 'moodmoderator' ) . '</p>';
	}

	/**
	 * Render moderation section description.
	 *
	 * @since 1.0.0
	 */
	public function render_moderation_section() {
		echo '<p>' . esc_html__( 'Control how aggressively comments are moderated based on sentiment.', 'moodmoderator' ) . '</p>';
	}

	/**
	 * Render advanced section description.
	 *
	 * @since 1.0.0
	 */
	public function render_advanced_section() {
		echo '<p>' . esc_html__( 'Advanced performance and debugging options.', 'moodmoderator' ) . '</p>';
	}

	/**
	 * Render tone management section.
	 *
	 * @since 1.0.0
	 */
	public function render_tone_management_section() {
		$pending_suggestions = $this->database->get_tone_suggestions( 'pending' );

		if ( empty( $pending_suggestions ) ) {
			echo '<p>' . esc_html__( 'No pending tone suggestions at this time.', 'moodmoderator' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'The AI has suggested the following new tones. Approve them to make them available for custom strictness settings.', 'moodmoderator' ) . '</p>';
			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Tone Name', 'moodmoderator' ) . '</th>';
			echo '<th>' . esc_html__( 'Frequency', 'moodmoderator' ) . '</th>';
			echo '<th>' . esc_html__( 'Last Seen', 'moodmoderator' ) . '</th>';
			echo '<th>' . esc_html__( 'Actions', 'moodmoderator' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $pending_suggestions as $suggestion ) {
				echo '<tr data-suggestion-id="' . esc_attr( $suggestion->id ) . '">';
				echo '<td><strong>' . esc_html( $suggestion->tone_name ) . '</strong></td>';
				echo '<td>' . esc_html( $suggestion->frequency ) . '</td>';
				echo '<td>' . esc_html( $suggestion->last_seen ) . '</td>';
				echo '<td>';
				echo '<button type="button" class="button button-primary moodmoderator-approve-tone" data-id="' . esc_attr( $suggestion->id ) . '">' . esc_html__( 'Approve', 'moodmoderator' ) . '</button> ';
				echo '<button type="button" class="button moodmoderator-reject-tone" data-id="' . esc_attr( $suggestion->id ) . '">' . esc_html__( 'Reject', 'moodmoderator' ) . '</button>';
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}
	}

	/**
	 * Render API key field.
	 *
	 * @since 1.0.0
	 */
	public function render_api_key_field() {
		$api_key = get_option( 'moodmoderator_api_key', '' );
		$has_key = ! empty( $api_key );
		?>
		<input type="password"
		       name="moodmoderator_api_key"
		       id="moodmoderator_api_key"
		       value="<?php echo $has_key ? esc_attr( '********' ) : ''; ?>"
		       placeholder="sk-..."
		       class="regular-text"
		       autocomplete="off">
		<p class="description">
			<?php esc_html_e( 'Your OpenAI API key. Get one at', 'moodmoderator' ); ?>
			<a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>.
			<?php if ( $has_key ) : ?>
				<br><strong><?php esc_html_e( 'API key is configured.', 'moodmoderator' ); ?></strong>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Render strictness field.
	 *
	 * @since 1.0.0
	 */
	public function render_strictness_field() {
		$strictness = get_option( 'moodmoderator_strictness', 'medium' );
		?>
		<select name="moodmoderator_strictness" id="moodmoderator_strictness">
			<option value="low" <?php selected( $strictness, 'low' ); ?>>
				<?php esc_html_e( 'Low - Hold only Toxic and Angry comments', 'moodmoderator' ); ?>
			</option>
			<option value="medium" <?php selected( $strictness, 'medium' ); ?>>
				<?php esc_html_e( 'Medium - Hold Toxic, Angry, and Sarcastic comments (Recommended)', 'moodmoderator' ); ?>
			</option>
			<option value="high" <?php selected( $strictness, 'high' ); ?>>
				<?php esc_html_e( 'High - Hold all except Friendly, Questioning, and Neutral', 'moodmoderator' ); ?>
			</option>
			<option value="custom" <?php selected( $strictness, 'custom' ); ?>>
				<?php esc_html_e( 'Custom - Choose specific tones below', 'moodmoderator' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Controls which tone categories trigger automatic comment moderation.', 'moodmoderator' ); ?>
		</p>
		<?php
	}

	/**
	 * Render custom tones field.
	 *
	 * @since 1.0.0
	 */
	public function render_custom_tones_field() {
		$custom_tones = get_option( 'moodmoderator_custom_tones', array() );
		$predefined_tones = get_option( 'moodmoderator_predefined_tones', array() );
		$approved_tones = $this->database->get_approved_tones();
		$all_tones = array_merge( $predefined_tones, $approved_tones );
		?>
		<fieldset id="moodmoderator_custom_tones_field">
			<?php foreach ( $all_tones as $tone ) : ?>
				<label>
					<input type="checkbox"
					       name="moodmoderator_custom_tones[]"
					       value="<?php echo esc_attr( $tone ); ?>"
					       <?php checked( in_array( $tone, $custom_tones, true ) ); ?>>
					<?php echo esc_html( $tone ); ?>
				</label><br>
			<?php endforeach; ?>
		</fieldset>
		<p class="description">
			<?php esc_html_e( 'Select specific tones to hold for moderation when using Custom strictness level.', 'moodmoderator' ); ?>
		</p>
		<?php
	}

	/**
	 * Render logging field.
	 *
	 * @since 1.0.0
	 */
	public function render_logging_field() {
		$enabled = get_option( 'moodmoderator_enable_logging', true );
		?>
		<label>
			<input type="checkbox"
			       name="moodmoderator_enable_logging"
			       value="1"
			       <?php checked( $enabled ); ?>>
			<?php esc_html_e( 'Enable logging of API calls and moderation decisions', 'moodmoderator' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Logs are useful for debugging but may use additional database space.', 'moodmoderator' ); ?>
		</p>
		<?php
	}

	/**
	 * Render cache duration field.
	 *
	 * @since 1.0.0
	 */
	public function render_cache_duration_field() {
		$duration = get_option( 'moodmoderator_cache_duration', 24 );
		?>
		<input type="number"
		       name="moodmoderator_cache_duration"
		       id="moodmoderator_cache_duration"
		       value="<?php echo esc_attr( $duration ); ?>"
		       min="1"
		       max="720"
		       class="small-text">
		<p class="description">
			<?php esc_html_e( 'How long to cache sentiment analysis results (1-720 hours). Longer durations reduce API costs.', 'moodmoderator' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize API key.
	 *
	 * @since  1.0.0
	 * @param  string $value Raw API key value.
	 * @return string Encrypted API key.
	 */
	public function sanitize_api_key( $value ) {
		$value = sanitize_text_field( $value );

		// If placeholder value, keep existing key
		if ( $value === '********' ) {
			return get_option( 'moodmoderator_api_key', '' );
		}

		// If empty, return empty
		if ( empty( $value ) ) {
			return '';
		}

		// Encrypt the key
		return $this->encrypt_api_key( $value );
	}

	/**
	 * Sanitize strictness level.
	 *
	 * @since  1.0.0
	 * @param  string $value Raw strictness value.
	 * @return string Sanitized strictness value.
	 */
	public function sanitize_strictness( $value ) {
		$allowed = array( 'low', 'medium', 'high', 'custom' );
		return in_array( $value, $allowed, true ) ? $value : 'medium';
	}

	/**
	 * Sanitize custom tones array.
	 *
	 * @since  1.0.0
	 * @param  array $value Raw custom tones array.
	 * @return array Sanitized custom tones array.
	 */
	public function sanitize_custom_tones( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_map( 'sanitize_text_field', $value );
	}

	/**
	 * Encrypt API key.
	 *
	 * Simple XOR encryption using WordPress salts.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $key Raw API key.
	 * @return string Encrypted API key.
	 */
	private function encrypt_api_key( $key ) {
		if ( empty( $key ) ) {
			return '';
		}

		$salt = wp_salt( 'auth' );
		$encrypted = '';
		$salt_length = strlen( $salt );

		for ( $i = 0; $i < strlen( $key ); $i++ ) {
			$encrypted .= $key[ $i ] ^ $salt[ $i % $salt_length ];
		}

		return base64_encode( $encrypted );
	}
}
