<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CBXR_Widget {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_widget' ) );
	}

	public function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		$place_id = get_option( 'cbxr_place_id', '' );
		if ( empty( $place_id ) ) {
			return;
		}

		wp_enqueue_style( 'cbxr-widget', CBXR_PLUGIN_URL . 'assets/css/cbxr-widget.css', array(), CBXR_VERSION );
		wp_enqueue_script( 'cbxr-widget', CBXR_PLUGIN_URL . 'assets/js/cbxr-widget.js', array(), CBXR_VERSION, true );
	}

	public function render_widget() {
		if ( is_admin() ) {
			return;
		}

		$place_id = get_option( 'cbxr_place_id', '' );
		if ( empty( $place_id ) ) {
			return;
		}

		$api     = new CBXR_API();
		$reviews = $api->get_display_reviews();
		$rating  = get_option( 'cbxr_rating', '5.0' );
		$count   = get_option( 'cbxr_review_count', '0' );
		$name    = get_option( 'cbxr_place_name', '' );
		$url     = get_option( 'cbxr_place_url', '' );

		$position     = get_option( 'cbxr_widget_position', 'bottom-left' );
		$header_text  = get_option( 'cbxr_header_text', 'What our patients say...' );
		$cta_text     = get_option( 'cbxr_cta_text', 'Review us on Google' );
		$accent_color = get_option( 'cbxr_accent_color', '#ffffff' );

		$review_url = $url ? $url : 'https://search.google.com/local/writereview?placeid=' . urlencode( $place_id );

		if ( empty( $reviews ) && empty( $rating ) ) {
			return;
		}

		$is_right = ( 'bottom-right' === $position );
		$pos_class = $is_right ? 'cbxr-pos-right' : 'cbxr-pos-left';
		?>
		<style id="cbxr-critical-css">
		/* Critical positioning, inlined so it applies at first paint even when the main stylesheet is
		   deferred (e.g. WP Rocket Remove Unused CSS / async). Without this the panel flashes un-hidden
		   in normal flow below the footer until the deferred CSS loads. */
		#cbxr-widget .cbxr-badge{position:fixed!important;bottom:20px!important;z-index:999998!important}
		#cbxr-widget.cbxr-pos-left .cbxr-badge{left:20px!important}
		#cbxr-widget.cbxr-pos-right .cbxr-badge{right:20px!important}
		#cbxr-widget .cbxr-panel{position:fixed!important;top:0!important;bottom:0!important;width:340px!important;z-index:999999!important;transform:translateX(-100%)!important}
		#cbxr-widget.cbxr-pos-left .cbxr-panel{left:0!important}
		#cbxr-widget.cbxr-pos-right .cbxr-panel{right:0!important;left:auto!important;transform:translateX(100%)!important}
		#cbxr-widget.cbxr-open .cbxr-panel{transform:translateX(0)!important}
		#cbxr-widget .cbxr-overlay{position:fixed!important;inset:0!important;z-index:999997!important;opacity:0!important;pointer-events:none!important}
		#cbxr-widget.cbxr-open .cbxr-overlay{opacity:1!important;pointer-events:auto!important}
		</style>
		<div id="cbxr-widget" class="<?php echo esc_attr( $pos_class ); ?>" style="--cbxr-accent: <?php echo esc_attr( $accent_color ); ?>;">

			<!-- Floating Badge -->
			<button id="cbxr-badge" class="cbxr-badge" aria-label="Open Google Reviews">
				<span class="cbxr-badge-rating"><?php echo esc_html( number_format( (float) $rating, 1 ) ); ?></span>
				<span class="cbxr-badge-stars"><?php echo $this->render_stars( (float) $rating, 22 ); ?></span>
				<span class="cbxr-badge-count"><?php echo esc_html( $count ); ?> reviews</span>
			</button>

			<!-- Slide-out Panel -->
			<div id="cbxr-panel" class="cbxr-panel" aria-hidden="true">
				<div class="cbxr-panel-inner">

					<button id="cbxr-close" class="cbxr-close" aria-label="Close reviews panel">&times;</button>

					<h2 class="cbxr-panel-title"><?php echo esc_html( $header_text ); ?></h2>

					<div class="cbxr-summary">
						<div class="cbxr-google-logo">
							<svg viewBox="0 0 272 92" width="88" height="30" xmlns="http://www.w3.org/2000/svg">
								<path d="M115.75 47.18c0 12.77-9.99 22.18-22.25 22.18s-22.25-9.41-22.25-22.18C71.25 34.32 81.24 25 93.5 25s22.25 9.32 22.25 22.18zm-9.74 0c0-7.98-5.79-13.44-12.51-13.44S80.99 39.2 80.99 47.18c0 7.9 5.79 13.44 12.51 13.44s12.51-5.55 12.51-13.44z" fill="#EA4335"/>
								<path d="M163.75 47.18c0 12.77-9.99 22.18-22.25 22.18s-22.25-9.41-22.25-22.18c0-12.85 9.99-22.18 22.25-22.18s22.25 9.32 22.25 22.18zm-9.74 0c0-7.98-5.79-13.44-12.51-13.44s-12.51 5.46-12.51 13.44c0 7.9 5.79 13.44 12.51 13.44s12.51-5.55 12.51-13.44z" fill="#FBBC05"/>
								<path d="M209.75 26.34v39.82c0 16.38-9.66 23.07-21.08 23.07-10.75 0-17.22-7.19-19.66-13.07l8.48-3.53c1.51 3.61 5.21 7.87 11.17 7.87 7.31 0 11.84-4.51 11.84-13v-3.19h-.34c-2.18 2.69-6.38 5.04-11.68 5.04-11.09 0-21.25-9.66-21.25-22.09 0-12.52 10.16-22.26 21.25-22.26 5.29 0 9.49 2.35 11.68 4.96h.34v-3.61h9.25zm-8.56 20.92c0-7.81-5.21-13.52-11.84-13.52-6.72 0-12.35 5.71-12.35 13.52 0 7.73 5.63 13.36 12.35 13.36 6.63 0 11.84-5.63 11.84-13.36z" fill="#4285F4"/>
								<path d="M225 3v65h-9.5V3h9.5z" fill="#34A853"/>
								<path d="M262.02 54.48l7.56 5.04c-2.44 3.61-8.32 9.83-18.48 9.83-12.6 0-22.01-9.74-22.01-22.18 0-13.19 9.49-22.18 20.92-22.18 11.51 0 17.14 9.16 18.98 14.11l1.01 2.52-29.65 12.28c2.27 4.45 5.8 6.72 10.75 6.72 4.96 0 8.4-2.44 10.92-6.14zm-23.27-7.98l19.82-8.23c-1.09-2.77-4.37-4.7-8.23-4.7-4.95 0-11.84 4.37-11.59 12.93z" fill="#EA4335"/>
								<path d="M35.29 41.19V32H67c.31 1.64.47 3.58.47 5.68 0 7.06-1.93 15.79-8.15 22.01-6.05 6.3-13.78 9.66-24.02 9.66C16.32 69.35.36 53.89.36 34.91.36 15.93 16.32.47 35.3.47c10.5 0 17.98 4.12 23.6 9.49l-6.64 6.64c-4.03-3.78-9.49-6.72-16.97-6.72-13.86 0-24.7 11.17-24.7 25.03 0 13.86 10.84 25.03 24.7 25.03 8.99 0 14.11-3.61 17.39-6.89 2.66-2.66 4.41-6.46 5.1-11.65l-22.49-.21z" fill="#4285F4"/>
							</svg>
							<span class="cbxr-reviews-text">Reviews</span>
						</div>
						<div class="cbxr-summary-rating">
							<span class="cbxr-summary-number"><?php echo esc_html( number_format( (float) $rating, 1 ) ); ?></span>
							<span class="cbxr-summary-stars"><?php echo $this->render_stars( (float) $rating ); ?></span>
							<span class="cbxr-summary-count">(<?php echo esc_html( $count ); ?>)</span>
						</div>
						<a href="<?php echo esc_url( 'https://search.google.com/local/writereview?placeid=' . urlencode( $place_id ) ); ?>"
						   class="cbxr-cta-btn" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( $cta_text ); ?>
						</a>
					</div>

					<div class="cbxr-reviews-list">
						<?php foreach ( $reviews as $review ) : ?>
							<?php $this->render_review_card( $review ); ?>
						<?php endforeach; ?>
					</div>

				</div>
			</div>

			<div id="cbxr-overlay" class="cbxr-overlay"></div>
		</div>
		<?php
		$this->render_schema( $name, $rating, $count, $url, $place_id, $reviews );
	}

	private function render_schema( $name, $rating, $count, $url, $place_id, $reviews ) {
		if ( empty( $name ) || empty( $rating ) ) {
			return;
		}

		$schema = array(
			'@context'       => 'https://schema.org',
			'@type'          => 'LocalBusiness',
			'name'           => $name,
			'aggregateRating' => array(
				'@type'       => 'AggregateRating',
				'ratingValue' => number_format( (float) $rating, 1 ),
				'bestRating'  => '5',
				'worstRating' => '1',
				'reviewCount' => (int) $count,
			),
		);

		// Business identity fields — Google requires `address` on any LocalBusiness that carries
		// ratings/reviews; without it the item is flagged invalid. NAP persisted from Place Details.
		$address = get_option( 'cbxr_place_address', '' );
		if ( ! empty( $address ) ) {
			$schema['address'] = $this->parse_postal_address( $address );
		}
		$telephone = get_option( 'cbxr_place_phone', '' );
		if ( ! empty( $telephone ) ) {
			$schema['telephone'] = $telephone;
		}
		$geo = get_option( 'cbxr_place_geo', '' );
		if ( ! empty( $geo ) && false !== strpos( $geo, ',' ) ) {
			list( $lat, $lng ) = array_map( 'trim', explode( ',', $geo, 2 ) );
			if ( '' !== $lat && '' !== $lng ) {
				$schema['geo'] = array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => $lat,
					'longitude' => $lng,
				);
			}
		}
		$image = get_option( 'cbxr_place_image', '' );
		if ( empty( $image ) && function_exists( 'get_site_icon_url' ) ) {
			$image = get_site_icon_url( 512 );
		}
		if ( ! empty( $image ) ) {
			$schema['image'] = $image;
		}
		$price_range = get_option( 'cbxr_price_range', '$$' );
		if ( ! empty( $price_range ) ) {
			$schema['priceRange'] = $price_range;
		}

		// Prefer the business's own website for `url`; fall back to the Google Maps URL.
		$site_url = home_url( '/' );
		if ( ! empty( $site_url ) ) {
			$schema['url'] = $site_url;
		} elseif ( ! empty( $url ) ) {
			$schema['url'] = $url;
		}

		if ( ! empty( $reviews ) ) {
			$schema['review'] = array();
			foreach ( $reviews as $review ) {
				$r = array(
					'@type'        => 'Review',
					'author'       => array(
						'@type' => 'Person',
						'name'  => isset( $review['author_name'] ) ? $review['author_name'] : 'Anonymous',
					),
					'reviewRating' => array(
						'@type'      => 'Rating',
						'ratingValue' => isset( $review['rating'] ) ? (int) $review['rating'] : 5,
						'bestRating'  => '5',
						'worstRating' => '1',
					),
				);

				if ( ! empty( $review['text'] ) ) {
					$r['reviewBody'] = $review['text'];
				}

				if ( ! empty( $review['time'] ) ) {
					$r['datePublished'] = gmdate( 'Y-m-d', (int) $review['time'] );
				}

				$schema['review'][] = $r;
			}
		}

		/**
		 * Filter the reviews-widget LocalBusiness schema before output.
		 *
		 * @param array  $schema   Assembled schema array.
		 * @param string $place_id Google place id.
		 */
		$schema = apply_filters( 'cbxr_schema', $schema, $place_id );

		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	/**
	 * Parse a Google formatted_address string into a schema.org PostalAddress.
	 * e.g. "36 14th Ave NE Ste 101, Hickory, NC 28601, USA".
	 *
	 * @param string $formatted Formatted address.
	 * @return array|string PostalAddress array, or the raw string if it can't be parsed.
	 */
	private function parse_postal_address( $formatted ) {
		$parts = array_values( array_filter( array_map( 'trim', explode( ',', $formatted ) ), 'strlen' ) );
		$addr  = array( '@type' => 'PostalAddress' );

		if ( $parts && preg_match( '/^(USA|United States|US)$/i', end( $parts ) ) ) {
			$addr['addressCountry'] = 'US';
			array_pop( $parts );
		}
		if ( $parts && preg_match( '/^([A-Za-z]{2})\s+(\d{5}(?:-\d{4})?)$/', end( $parts ), $m ) ) {
			$addr['addressRegion'] = strtoupper( $m[1] );
			$addr['postalCode']    = $m[2];
			array_pop( $parts );
		}
		if ( $parts ) {
			$addr['addressLocality'] = array_pop( $parts );
		}
		if ( $parts ) {
			$addr['streetAddress'] = implode( ', ', $parts );
		}

		return ( count( $addr ) > 1 ) ? $addr : $formatted;
	}

	private function render_review_card( $review ) {
		$author  = isset( $review['author_name'] ) ? $review['author_name'] : 'Anonymous';
		$avatar  = isset( $review['profile_photo_url'] ) ? $review['profile_photo_url'] : '';
		$rating  = isset( $review['rating'] ) ? (int) $review['rating'] : 5;
		$text    = isset( $review['text'] ) ? $review['text'] : '';
		$time    = isset( $review['relative_time_description'] ) ? $review['relative_time_description'] : '';
		$url     = isset( $review['author_url'] ) ? $review['author_url'] : '#';
		$initial = strtoupper( substr( $author, 0, 1 ) );

		$colors = array( '#4285F4', '#EA4335', '#FBBC05', '#34A853', '#FF6D01', '#46BDC6', '#7B1FA2', '#C2185B' );
		$color_index = abs( crc32( $author ) ) % count( $colors );
		$bg_color = $colors[ $color_index ];
		?>
		<div class="cbxr-review-card">
			<div class="cbxr-review-header">
				<?php if ( $avatar ) : ?>
					<img class="cbxr-review-avatar" src="<?php echo esc_url( $avatar ); ?>"
						 alt="<?php echo esc_attr( $author ); ?>" loading="lazy" referrerpolicy="no-referrer" />
				<?php else : ?>
					<div class="cbxr-review-avatar cbxr-avatar-initial" style="background-color: <?php echo esc_attr( $bg_color ); ?>;">
						<?php echo esc_html( $initial ); ?>
					</div>
				<?php endif; ?>
				<div class="cbxr-review-meta">
					<strong class="cbxr-review-author"><?php echo esc_html( $author ); ?></strong>
					<span class="cbxr-review-time"><?php echo esc_html( $time ); ?></span>
				</div>
			</div>
			<div class="cbxr-review-stars"><?php echo $this->render_stars( $rating ); ?></div>
			<p class="cbxr-review-text"><?php echo esc_html( $text ); ?></p>
			<?php if ( strlen( $text ) > 180 ) : ?>
				<button class="cbxr-read-more">Read more</button>
			<?php endif; ?>
			<div class="cbxr-review-source">
				<svg class="cbxr-google-icon" width="16" height="16" viewBox="0 0 48 48">
					<path fill="#4285F4" d="M44.5 20H24v8.5h11.8C34.7 33.9 30.1 37 24 37c-7.2 0-13-5.8-13-13s5.8-13 13-13c3.1 0 5.9 1.1 8.1 2.9l6.4-6.4C34.6 4.1 29.6 2 24 2 11.8 2 2 11.8 2 24s9.8 22 22 22c11 0 21-8 21-22 0-1.3-.2-2.7-.5-4z"/>
					<path fill="#34A853" d="M6.3 14.7l7 5.1C15.2 15.6 19.2 13 24 13c3.1 0 5.9 1.1 8.1 2.9l6.4-6.4C34.6 4.1 29.6 2 24 2 16.3 2 9.7 6.6 6.3 14.7z"/>
					<path fill="#FBBC05" d="M24 46c5.4 0 10.3-1.8 14.1-4.9l-6.5-5.5C29.5 37.5 26.9 38.5 24 38.5c-6 0-11.1-4-12.9-9.5l-7 5.4C7.6 41.4 15 46 24 46z"/>
					<path fill="#EA4335" d="M44.5 20H24v8.5h11.8c-1 3.2-3.1 5.8-5.8 7.6l6.5 5.5C40.2 38.3 46 31.8 46 24c0-1.3-.2-2.7-.5-4z"/>
				</svg>
				<span class="cbxr-source-label">Posted on</span>
				<a href="<?php echo esc_url( $url ); ?>" class="cbxr-source-link" target="_blank" rel="noopener noreferrer">Google</a>
			</div>
		</div>
		<?php
	}

	private function render_stars( $rating, $size = 18 ) {
		$output = '';
		for ( $i = 1; $i <= 5; $i++ ) {
			if ( $i <= floor( $rating ) ) {
				$output .= '<svg class="cbxr-star cbxr-star-full" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="#F4B400"/></svg>';
			} elseif ( $i - $rating < 1 ) {
				$output .= '<svg class="cbxr-star cbxr-star-half" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24"><defs><linearGradient id="cbxr-half-' . $i . '"><stop offset="50%" stop-color="#F4B400"/><stop offset="50%" stop-color="#DAD9D6"/></linearGradient></defs><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="url(#cbxr-half-' . $i . ')"/></svg>';
			} else {
				$output .= '<svg class="cbxr-star cbxr-star-empty" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="#DAD9D6"/></svg>';
			}
		}
		return $output;
	}
}
