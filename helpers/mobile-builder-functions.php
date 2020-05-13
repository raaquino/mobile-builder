<?php

/**
 *
 * Handle network request
 *
 * @param $method
 * @param $url
 * @param bool $data
 *
 * @return bool|string
 * @since    1.0.0
 */
function mobile_builder_request( $method, $url, $data = false ) {
	$curl = curl_init();

	switch ( $method ) {
		case "POST":
			curl_setopt( $curl, CURLOPT_POST, 1 );

			if ( $data ) {
				curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
			}
			break;
		case "PUT":
			curl_setopt( $curl, CURLOPT_PUT, 1 );
			break;
		default:
			if ( $data ) {
				$url = sprintf( "%s?%s", $url, http_build_query( $data ) );
			}
	}

	// Optional Authentication:
	curl_setopt( $curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
	curl_setopt( $curl, CURLOPT_USERPWD, "username:password" );

	curl_setopt( $curl, CURLOPT_URL, $url );
	curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );

	$result = curl_exec( $curl );

	curl_close( $curl );

	return $result;
}

/**
 *
 * Distance matrix
 *
 * @param $origin_string
 * @param $destinations_string
 * @param $key
 * @param string $units
 *
 * @return mixed
 */
function mobile_builder_distance_matrix($origin_string, $destinations_string, $key, $units = 'metric') {
	$google_map_api = 'https://maps.googleapis.com/maps/api';
	$url = "$google_map_api/distancematrix/json?units=$units&origins=$origin_string&destinations=$destinations_string&key=$key";
	return json_decode( mobile_builder_request( 'GET', $url ) )->rows;
}