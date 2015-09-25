<?php

require_once( __DIR__ . '/libs/tlc-transients/tlc-transients.php' );

function wctampa2015_enqueue_styles() {
	wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
}
add_action( 'wp_enqueue_scripts', 'wctampa2015_enqueue_styles' );

/**
 * Find posts that are related to the given post that have
 * at least 100 likes
 *
 * @param int $post_id
 * @param int $count
 *
 * @return array The IDs of the related posts
 */
function wctampa2015_get_related_posts( $post_id = 0, $count = 3 ) {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	$query_args = [
		'post_type' => 'post',
		'posts_per_page' => $count,
		'meta_query' => [
			[
				'key' => 'related_post',
				'value' => $post_id,
				'type' => 'NUMERIC'
			],
			[
				'key' => 'like_count',
				'value' => 100,
				'type' => 'NUMERIC',
				'compare' => '>=',
			],
		],
		'fields' => 'ids'
	];

	$query = new WP_Query();
	$related_posts = $query->query( $query_args );

	return $related_posts;
}

/**
 * Find posts that are related to the given post that have
 * at least 100 likes.
 *
 * Data is pulled from the cache if available.
 *
 * @param int $post_id
 * @param int $count
 *
 * @return array The IDs of the related posts
 */
function wctampa2015_get_related_posts_cached( $post_id = 0, $count = 3 ) {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	return tlc_transient( 'related_posts_' . $post_id . '_' . $count )
		->updates_with( 'wctampa2015_get_related_posts', [ $post_id, $count ] )
		->expires_in( 12 * HOUR_IN_SECONDS )
		->get();
}

/**
 * Find the posts with geolocation meta nearest to the given post
 *
 * @param int $post_id The post to search around
 * @param int $count Maximum number of results to return
 *
 * @return array Post IDs of the nearest posts
 */
function wctampa2015_get_nearby_posts( $post_id = 0, $count = 3 ) {
	/** @var \wpdb $wpdb */
	global $wpdb;

	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	$location = get_post_meta( $post_id, 'geolocation', true );
	if ( empty( $location ) ) {
		return [];
	}
	list( $latitude, $longitude ) = explode( ',', $location );

	$other_locations = $wpdb->get_results( $wpdb->prepare(
		"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'geolocation' AND post_id != %d",
		$post_id
	) );

	$nearby_posts = [];

	foreach ( $other_locations as $meta_record ) {
		$other_post_id = $meta_record->post_id;
		$other_location = $meta_record->meta_value;
		if ( empty( $other_location ) ) {
			continue;
		}
		list( $other_latitude, $other_longitude ) = explode( ',', $other_location );

		$distance = wctampa2015_get_distance( $latitude, $longitude, $other_latitude, $other_longitude );

		$nearby_posts[$other_post_id] = $distance;
	}

	asort( $nearby_posts );
	return array_slice( array_keys( $nearby_posts ), 0, $count );
}

/**
 * Find the distance between to pairs of coordinates on Earth
 *
 * @param float $lat1
 * @param float $lon1
 * @param float $lat2
 * @param float $lon2
 * @param string $unit 'miles' or 'km'
 *
 * @return float
 */
function wctampa2015_get_distance( $lat1, $lon1, $lat2, $lon2, $unit = 'miles' ) {
	$theta = $lon1 - $lon2;
	$dist = sin( deg2rad( $lat1 ) ) * sin( deg2rad( $lat2 ) ) + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * cos( deg2rad( $theta ) );
	$dist = acos( $dist );
	$dist = rad2deg( $dist );
	$miles = $dist * 60 * 1.1515;

	$unit = strtolower( $unit );
	if ( $unit == "km" ) {
		return ( $miles * 1.609344 );
	} else {
		return $miles;
	}
}

/**
 * Find the posts with geolocation meta nearest to the given post.
 *
 * This version offloads the distance calculation and sorting to the database
 *
 * @param int $post_id The post to search around
 * @param int $count Maximum number of results to return
 *
 * @return array Post IDs of the nearest posts
 */
function wctampa2015_get_nearby_posts_v2( $post_id = 0, $count = 3 ) {
	/** @var \wpdb $wpdb */
	global $wpdb;

	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	$location = get_post_meta( $post_id, 'geolocation', true );
	if ( empty( $location ) ) {
		return [];
	}
	list( $latitude, $longitude ) = explode( ',', $location );

	$sql = "SELECT post_id,
	( ( (
		acos(
			sin( ( %s * pi() / 180 ) ) *
			sin( ( SUBSTRING_INDEX( meta_value, ',', 1  ) * pi() / 180 ) ) +
			cos( ( %s * pi() / 180 ) ) *
			cos( ( SUBSTRING_INDEX( meta_value, ',', 1  ) * pi() / 180 ) ) *
			cos( ( ( %s - SUBSTRING_INDEX( meta_value, ',', -1  ) ) * pi() /180 ) )
		)
	) * 180 / pi() ) * 60 * 1.1515 ) AS distance
	FROM {$wpdb->postmeta}
	LEFT JOIN {$wpdb->posts} p ON p.ID = {$wpdb->postmeta}.post_id
	WHERE meta_key = 'geolocation' AND post_id != %d AND p.post_status = 'publish' AND p.post_type = 'post'
	ORDER BY distance ASC LIMIT %d";
	$sql = $wpdb->prepare( $sql, $latitude, $latitude, $longitude, $post_id, $count );
	return $wpdb->get_col( $sql );
}

/**
 * Find the posts with geolocation meta nearest to the given post.
 * Results are pulled from the cache if possible.
 *
 * @param int $post_id The post to search around
 * @param int $count Maximum number of results to return
 *
 * @return array Post IDs of the nearest posts
 */
function wctampa2015_get_nearby_posts_cached( $post_id = 0, $count = 3 ) {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	return tlc_transient( 'nearby_posts_' . $post_id . '_' . $count )
		->updates_with( 'wctampa2015_get_nearby_posts_v2', [ $post_id, $count ] )
		->expires_in( 12 * HOUR_IN_SECONDS )
		->get();
}

/**
 * Register widgets defined in the theme
 *
 * @return void
 */
function wctamp2015_widgets_init() {
	include_once( __DIR__ . '/widgets/Most_Popular_Widget.cached.php' );
	register_widget( 'Most_Popular_Widget' );

	include_once( __DIR__ . '/widgets/Popular_Authors_Widget.cached.php' );
	register_widget( 'Popular_Authors_Widget' );

	include_once( __DIR__ . '/widgets/News_From_WCTampa_Widget.cached.php' );
	register_widget( 'News_From_WCTampa_Widget' );
}
add_action( 'widgets_init', 'wctamp2015_widgets_init' );