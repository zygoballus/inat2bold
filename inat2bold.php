<?php
// This script is dual licensed under the MIT License and the CC0 License.
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
ini_set( 'max_execution_time', 600 );

$useragent = 'iNat2BOLD Script/1.0';
$inatapi = 'https://api.inaturalist.org/v1/observations/';
$errors = [];

/**
 * Make curl request using the passed URL
 *
 * @param string $url The URL to request
 * @return array|null
 */
function make_curl_request( $url = null ) {
	global $useragent;
	$curl = curl_init();
    if ( $curl && $url ) {
        curl_setopt( $curl, CURLOPT_URL, $url );
        curl_setopt( $curl, CURLOPT_USERAGENT, $useragent );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
        $out = curl_exec( $curl );
        $object = json_decode( $out );
        return json_decode( json_encode( $object ), true );
    } else {
        return null;
    }
}

function get_observation_data( $observations ) {
	global $inatapi, $errors;
	$data = [];
	if ( $observations ) {
		$observationlist = implode( ',', $observations );
		$url = $inatapi . $observationlist;
		$inatdata = make_curl_request( $url );
		if ( $inatdata && $inatdata['results'] ) {
			$data['date'] = $inatdata['results'][0]['observed_on_details']['date'];
			return $data;
		} else {
			$errors[] = 'No observations found.';
			return null;
		}
	}
}

$observations = [142865349];
$observationdata = get_observation_data( $observations );
echo $observationdata['date'] . "\n";
foreach ( $errors as $error ) {
	echo $error . "\n";
}
