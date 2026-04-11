<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CBXR_API {

	public function fetch_reviews() {
		$api_key  = get_option( 'cbxr_api_key', '' );
		$place_id = get_option( 'cbxr_place_id', '' );

		if ( empty( $api_key ) || empty( $place_id ) ) {
			return new WP_Error( 'cbxr_missing_config', 'API key or Place ID not configured.' );
		}

		$url = add_query_arg(
			array(
				'place_id'     => $place_id,
				'fields'       => 'name,rating,user_ratings_total,reviews,url',
				'key'          => $api_key,
				'reviews_sort' => 'newest',
			),
			'https://maps.googleapis.com/maps/api/place/details/json'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['result'] ) ) {
			$error_msg = isset( $body['error_message'] ) ? $body['error_message'] : 'Unknown API error';
			return new WP_Error( 'cbxr_api_error', $error_msg );
		}

		return $body['result'];
	}

	public function search_places( $query ) {
		$api_key = get_option( 'cbxr_api_key', '' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'cbxr_missing_key', 'API key not configured.' );
		}

		$url = add_query_arg(
			array(
				'input'     => $query,
				'inputtype' => 'textquery',
				'fields'    => 'place_id,name,formatted_address',
				'key'       => $api_key,
			),
			'https://maps.googleapis.com/maps/api/place/findplacefromtext/json'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['candidates'] ) ) {
			$error_msg = isset( $body['error_message'] ) ? $body['error_message'] : 'No results';
			return new WP_Error( 'cbxr_search_error', $error_msg );
		}

		return $body['candidates'];
	}

	public function refresh_reviews() {
		$result = $this->fetch_reviews();

		if ( is_wp_error( $result ) ) {
			update_option( 'cbxr_last_error', $result->get_error_message() );
			return;
		}

		if ( isset( $result['rating'] ) ) {
			update_option( 'cbxr_rating', $result['rating'] );
		}
		if ( isset( $result['user_ratings_total'] ) ) {
			update_option( 'cbxr_review_count', $result['user_ratings_total'] );
		}
		if ( isset( $result['name'] ) ) {
			update_option( 'cbxr_place_name', $result['name'] );
		}
		if ( isset( $result['url'] ) ) {
			update_option( 'cbxr_place_url', $result['url'] );
		}

		$new_reviews = isset( $result['reviews'] ) ? $result['reviews'] : array();
		$cached      = get_option( 'cbxr_cached_reviews', array() );

		if ( ! is_array( $cached ) ) {
			$cached = array();
		}

		$existing_keys = array();
		foreach ( $cached as $r ) {
			$key                   = $this->review_key( $r );
			$existing_keys[ $key ] = true;
		}

		foreach ( $new_reviews as $review ) {
			$key = $this->review_key( $review );
			if ( ! isset( $existing_keys[ $key ] ) ) {
				$cached[]              = $review;
				$existing_keys[ $key ] = true;
			}
		}

		usort( $cached, function ( $a, $b ) {
			return ( $b['time'] ?? 0 ) - ( $a['time'] ?? 0 );
		});

		update_option( 'cbxr_cached_reviews', $cached, false );
		update_option( 'cbxr_last_refresh', current_time( 'mysql' ) );
		delete_option( 'cbxr_last_error' );
	}

	public function get_display_reviews() {
		$cached = get_option( 'cbxr_cached_reviews', array() );

		if ( ! is_array( $cached ) ) {
			return array();
		}

		$min_rating = (int) get_option( 'cbxr_min_rating', 4 );

		return array_values(
			array_filter( $cached, function ( $r ) use ( $min_rating ) {
				$has_text = ! empty( $r['text'] ) && trim( $r['text'] ) !== '';
				$rating   = isset( $r['rating'] ) ? (int) $r['rating'] : 0;
				return $rating >= $min_rating && $has_text;
			})
		);
	}

	private function review_key( $review ) {
		$name = isset( $review['author_name'] ) ? $review['author_name'] : '';
		$time = isset( $review['time'] ) ? $review['time'] : '';
		return md5( $name . '|' . $time );
	}
}
