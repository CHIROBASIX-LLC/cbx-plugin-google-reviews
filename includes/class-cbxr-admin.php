<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CBXR_Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_cbxr_search_places', array( $this, 'ajax_search_places' ) );
		add_action( 'wp_ajax_cbxr_refresh_reviews', array( $this, 'ajax_refresh_reviews' ) );
		add_action( 'wp_ajax_cbxr_bulk_fetch', array( $this, 'ajax_bulk_fetch' ) );

		// Clear stale errors when settings are saved.
		add_action( 'update_option_cbxr_api_key', array( $this, 'on_settings_saved' ) );
		add_action( 'update_option_cbxr_place_id', array( $this, 'on_settings_saved' ) );
	}

	/**
	 * When settings are saved, clear errors and auto-fetch reviews if configured.
	 */
	public function on_settings_saved() {
		delete_option( 'cbxr_last_error' );

		// Auto-fetch reviews when Place ID is saved for the first time (or changed).
		$api_key  = get_option( 'cbxr_api_key', '' );
		$place_id = get_option( 'cbxr_place_id', '' );
		if ( ! empty( $api_key ) && ! empty( $place_id ) ) {
			$api = new CBXR_API();
			$api->refresh_reviews();
		}
	}

	public function add_menu() {
		add_options_page(
			'CHIROBASIX Google Reviews Widget',
			'Reviews Widget',
			'manage_options',
			'cbx-google-reviews',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'cbxr_settings', 'cbxr_api_key', array( 'sanitize_callback' => 'cbxr_sanitize_google_key' ) );
		register_setting( 'cbxr_settings', 'cbxr_outscraper_key', array( 'sanitize_callback' => 'cbxr_sanitize_outscraper_key' ) );
		register_setting( 'cbxr_settings', 'cbxr_place_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'cbxr_settings', 'cbxr_min_rating', array(
			'sanitize_callback' => 'absint',
			'default'           => 4,
		));
		register_setting( 'cbxr_settings', 'cbxr_widget_position', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'bottom-left',
		));
		register_setting( 'cbxr_settings', 'cbxr_header_text', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'What our patients say...',
		));
		register_setting( 'cbxr_settings', 'cbxr_cta_text', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'Review us on Google',
		));
		register_setting( 'cbxr_settings', 'cbxr_accent_color', array(
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#ffffff',
		));
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_cbx-google-reviews' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'cbxr-admin', CBXR_PLUGIN_URL . 'assets/css/cbxr-admin.css', array(), CBXR_VERSION );
		wp_enqueue_script( 'cbxr-admin', CBXR_PLUGIN_URL . 'assets/js/cbxr-admin.js', array( 'jquery' ), CBXR_VERSION, true );
		wp_localize_script( 'cbxr-admin', 'cbxrAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'cbxr_admin_nonce' ),
		));
	}

	public function ajax_search_places() {
		check_ajax_referer( 'cbxr_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$query   = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

		if ( empty( $query ) ) {
			wp_send_json_error( 'Enter a business name to search.' );
		}

		$api    = new CBXR_API();
		$result = $api->search_places( $query, $api_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	public function ajax_refresh_reviews() {
		check_ajax_referer( 'cbxr_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$api = new CBXR_API();
		$api->refresh_reviews();

		$error = get_option( 'cbxr_last_error', '' );
		if ( ! empty( $error ) ) {
			wp_send_json_error( $error );
		}

		$reviews = $api->get_display_reviews();
		wp_send_json_success( array(
			'review_count'   => count( $reviews ),
			'total_cached'   => count( get_option( 'cbxr_cached_reviews', array() ) ),
			'rating'         => get_option( 'cbxr_rating', '' ),
			'total_reviews'  => get_option( 'cbxr_review_count', '' ),
			'place_name'     => get_option( 'cbxr_place_name', '' ),
			'last_refresh'   => get_option( 'cbxr_last_refresh', '' ),
		));
	}

	public function ajax_bulk_fetch() {
		check_ajax_referer( 'cbxr_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$outscraper_key = isset( $_POST['outscraper_key'] ) ? sanitize_text_field( wp_unslash( $_POST['outscraper_key'] ) ) : '';

		$api    = new CBXR_API();
		$result = $api->bulk_fetch_reviews( $outscraper_key, 200 );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		$reviews = $api->get_display_reviews();
		wp_send_json_success( array(
			'total_fetched'  => $result,
			'review_count'   => count( $reviews ),
			'total_cached'   => count( get_option( 'cbxr_cached_reviews', array() ) ),
			'rating'         => get_option( 'cbxr_rating', '' ),
			'total_reviews'  => get_option( 'cbxr_review_count', '' ),
			'place_name'     => get_option( 'cbxr_place_name', '' ),
			'last_refresh'   => get_option( 'cbxr_last_refresh', '' ),
		));
	}

	public function render_settings_page() {
		$api_key          = get_option( 'cbxr_api_key', '' );
		$outscraper_key   = get_option( 'cbxr_outscraper_key', '' );
		$google_const     = cbxr_key_from_constant( 'google' );
		$outscraper_const = cbxr_key_from_constant( 'outscraper' );
		$has_google_key   = $google_const || '' !== $api_key;
		// Placeholders prove a key is saved WITHOUT printing it into the page source.
		$google_ph     = $google_const ? 'Set in wp-config.php (CBXR_GOOGLE_API_KEY)' : ( '' !== $api_key ? 'Saved key ending ••••' . substr( $api_key, -4 ) . ' — leave blank to keep' : 'AIza...' );
		$outscraper_ph = $outscraper_const ? 'Set in wp-config.php (CBXR_OUTSCRAPER_KEY)' : ( '' !== $outscraper_key ? 'Saved key ending ••••' . substr( $outscraper_key, -4 ) . ' — leave blank to keep' : 'Outscraper API key' );
		$place_id       = get_option( 'cbxr_place_id', '' );
		$place_name     = get_option( 'cbxr_place_name', '' );
		$rating       = get_option( 'cbxr_rating', '' );
		$review_count = get_option( 'cbxr_review_count', '' );
		$last_refresh = get_option( 'cbxr_last_refresh', '' );
		$last_error   = get_option( 'cbxr_last_error', '' );
		$cached       = get_option( 'cbxr_cached_reviews', array() );
		$cached_count = is_array( $cached ) ? count( $cached ) : 0;
		$min_rating   = get_option( 'cbxr_min_rating', 4 );
		$position     = get_option( 'cbxr_widget_position', 'bottom-left' );
		$header_text  = get_option( 'cbxr_header_text', 'What our patients say...' );
		$cta_text     = get_option( 'cbxr_cta_text', 'Review us on Google' );
		$accent_color = get_option( 'cbxr_accent_color', '#ffffff' );
		?>
		<div class="wrap cbxr-admin-wrap">
			<h1>CHIROBASIX Google Reviews Widget</h1>

			<?php if ( ! empty( $last_error ) ) : ?>
				<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> <?php echo esc_html( $last_error ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! empty( $place_id ) ) : ?>
				<div class="cbxr-status-card">
					<?php if ( ! empty( $place_name ) ) : ?>
						<h3><?php echo esc_html( $place_name ); ?></h3>
						<p>
							<strong><?php echo esc_html( $rating ); ?></strong> stars &bull;
							<?php echo esc_html( $review_count ); ?> total reviews on Google &bull;
							<?php echo esc_html( $cached_count ); ?> reviews cached
						</p>
					<?php else : ?>
						<h3>Place ID configured</h3>
						<p>Click "Refresh Reviews Now" to pull in reviews from Google.</p>
					<?php endif; ?>
					<?php if ( $last_refresh ) : ?>
						<p class="cbxr-meta">Last refreshed: <?php echo esc_html( $last_refresh ); ?></p>
					<?php endif; ?>
					<button type="button" class="button" id="cbxr-refresh-btn">Refresh (5 newest)</button>
					<button type="button" class="button button-primary" id="cbxr-bulk-fetch-btn">Fetch All Reviews (up to 200)</button>
					<span id="cbxr-refresh-status"></span>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'cbxr_settings' ); ?>

				<h2 class="cbxr-section-title">1. Connect APIs</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="cbxr_api_key">Google API Key</label></th>
						<td>
							<input type="password" id="cbxr_api_key" name="cbxr_api_key"
								value="" class="regular-text" autocomplete="off"
								<?php disabled( $google_const ); ?>
								placeholder="<?php echo esc_attr( $google_ph ); ?>" />
							<p class="description">
								Requires Places API enabled.
								<a href="https://console.cloud.google.com/apis/library/places-backend.googleapis.com" target="_blank" rel="noopener">Enable it here</a>.
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="cbxr_outscraper_key">Outscraper API Key</label></th>
						<td>
							<input type="password" id="cbxr_outscraper_key" name="cbxr_outscraper_key"
								value="" class="regular-text" autocomplete="off"
								<?php disabled( $outscraper_const ); ?>
								placeholder="<?php echo esc_attr( $outscraper_ph ); ?>" />
							<p class="description">
								Fetches up to 200 reviews per location. First 500 reviews free.
								<a href="https://outscraper.com/" target="_blank" rel="noopener">Get a key here</a>.
							</p>
						</td>
					</tr>
				</table>

				<h2 class="cbxr-section-title">2. Find Your Business</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label>Search</label></th>
						<td>
							<div class="cbxr-search-wrap">
								<input type="text" id="cbxr-place-search" placeholder="e.g. Tri-States Chiropractic Dubuque" class="regular-text" />
								<button type="button" class="button button-primary" id="cbxr-search-btn">Search</button>
							</div>
							<p class="description">Enter the business name and city. Uses your API key from above (no need to save first).</p>
							<div id="cbxr-search-results"></div>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="cbxr_place_id">Place ID</label></th>
						<td>
							<input type="text" id="cbxr_place_id" name="cbxr_place_id"
								value="<?php echo esc_attr( $place_id ); ?>" class="regular-text" />
							<p class="description">Auto-filled when you select a business above, or paste manually.</p>
						</td>
					</tr>

					<tr>
						<th scope="row">Map Preview</th>
						<td>
							<div id="cbxr-map-preview">
								<?php if ( ! empty( $place_id ) && $has_google_key ) : ?>
									<iframe
										width="100%" height="250" style="border:0; border-radius:8px;"
										loading="lazy" referrerpolicy="no-referrer-when-downgrade"
										src="https://www.google.com/maps/embed/v1/place?key=<?php echo esc_attr( $api_key ); ?>&q=place_id:<?php echo esc_attr( $place_id ); ?>">
									</iframe>
								<?php else : ?>
									<div class="cbxr-map-placeholder">Select a business above to see the map preview.</div>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				</table>

				<h2 class="cbxr-section-title">3. Customize Widget</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="cbxr_header_text">Header Text</label></th>
						<td>
							<input type="text" id="cbxr_header_text" name="cbxr_header_text"
								value="<?php echo esc_attr( $header_text ); ?>" class="regular-text" />
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="cbxr_cta_text">CTA Button Text</label></th>
						<td>
							<input type="text" id="cbxr_cta_text" name="cbxr_cta_text"
								value="<?php echo esc_attr( $cta_text ); ?>" class="regular-text" />
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="cbxr_min_rating">Minimum Star Rating</label></th>
						<td>
							<select id="cbxr_min_rating" name="cbxr_min_rating">
								<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
									<option value="<?php echo $i; ?>" <?php selected( $min_rating, $i ); ?>><?php echo $i; ?>+ stars</option>
								<?php endfor; ?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="cbxr_widget_position">Widget Position</label></th>
						<td>
							<select id="cbxr_widget_position" name="cbxr_widget_position">
								<option value="bottom-left" <?php selected( $position, 'bottom-left' ); ?>>Bottom Left</option>
								<option value="bottom-right" <?php selected( $position, 'bottom-right' ); ?>>Bottom Right</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="cbxr_accent_color">Accent Color</label></th>
						<td>
							<input type="text" id="cbxr_accent_color" name="cbxr_accent_color"
								value="<?php echo esc_attr( $accent_color ); ?>" class="regular-text" placeholder="#ffffff" />
							<span class="description">HEX color code for the left border accent on the slide-out panel.</span>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Save Settings' ); ?>
			</form>
		</div>
		<?php
	}
}
