<?php
// This script is dual licensed under the MIT License and the CC0 License.
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
ini_set( 'max_execution_time', 900 );

include 'SimpleXLSXGen.php';

$useragent = 'iNat2BOLD Script/1.0';
$inatapi = 'https://api.inaturalist.org/v1/';
$errors = [];
$observationdata = [];
$fileoutput = false;
$sleeptime = 2;
$observations = [
'https://www.inaturalist.org/observations/123456'
];

/**
 * Make curl request using the passed URL
 *
 * @param string $url The URL to request
 * @return array|null
 */
function make_curl_request( $url = null ) {
	global $useragent, $errors;
	$curl = curl_init();
    if ( $curl && $url ) {
        curl_setopt( $curl, CURLOPT_URL, $url );
        curl_setopt( $curl, CURLOPT_USERAGENT, $useragent );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
        $out = curl_exec( $curl );
		if ( $out ) {
        	$object = json_decode( $out );
        	if ( $object ) {
        		return json_decode( json_encode( $object ), true );
        	} else {
        		$errors[] = 'API request failed. ' . curl_error( $curl );
        		return null;
        	}
        } else {
        	$errors[] = 'API request failed. ' . curl_error( $curl );
        	return null;
        }
    } else {
    	$errors[] = 'Curl initialization failed. ' . curl_error( $curl );
        return null;
    }
}

function get_photos( $photos ) {
	$allphotodata = [];
	foreach ( $photos as $photo ) {
		$url = str_replace( '/square', '/original', $photo['url'] );
		$photoarray = array(
			'id'=>$photo['id'],
			'license'=>$photo['license_code'],
			'url'=>$url
		);
		$allphotodata[] = $photoarray;
	}
	return $allphotodata;
}

function get_sample_id( $ofvs ) {
	return "1";
	foreach ( $ofvs as $observation_field ) {
		if ( $observation_field['name'] === 'BOLD ID' ) {
			return $observation_field['value'];
		}
	}
	return null;
}

function get_observation_data( $observationid ) {
	global $inatapi, $errors;
	if ( $observationid ) {
		$data = [];
		$url = $inatapi . 'observations/' . $observationid;
		$inatdata = make_curl_request( $url );
		if ( $inatdata && $inatdata['results'] && $inatdata['results'][0] ) {
			$results = $inatdata['results'][0];
			if ( isset( $results['ofvs'] ) ) {
				$data['sample_id'] = get_sample_id( $results['ofvs'] );
			}
			$data['license_holder'] = $results['user']['name'];
			if ( isset( $results['photos'] ) ) {
				$data['photos'] = get_photos( $results['photos'] );
			}
			return $data;
		} else {
			$errors[] = 'Observation not found: ' . $observationid;
			return null;
		}
	}
}

function downloadFile( $fileUrl ) {
	preg_match( '/photos\/(\d+)/', $fileUrl, $matches );
	$photoId = $matches[1];
	$savePath = "./images/" . $photoId . ".jpg";
			
    // Open the file to write the downloaded content
    $file = fopen($savePath, 'w');

    // Initialize cURL session
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $fileUrl); // Set the URL
    curl_setopt($ch, CURLOPT_FILE, $file); // Set output file
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ignore SSL certificate verification (for HTTPS)
    
    // Execute cURL session
    curl_exec($ch);
    
    // Check for errors and close cURL session
    $error = curl_error($ch);
    curl_close($ch);
    
    // Close the file
    fclose($file);
    
    // Return true if no errors occurred, otherwise return the error
    if ($error) {
        return $error;
    } else {
        return true;
    }
}

print("------------------ SCRIPT STARTED ------------------\n");
// See if form was submitted.
if ( $observations ) {
	$start_time = microtime( true );
	//$observationlist = explode( "\n", $_POST['observations'] );
	$observationlist = $observations;
	// Limit to 95 observations.
	$observationlist = array_slice( $observationlist, 0, 95 );
	$a = 0;
	foreach ( $observationlist as $observationid ) {
		if ( preg_match( '/\d+/', $observationid, $matches ) ) {
			$observationid = $matches[0];
			$observationdata[$a] = get_observation_data( $observationid );
		} else {
			$errors[] = 'Invalid observation number: ' . $observationid;
			$observationdata[$a] = null;
		}
		if ( count( $observationlist ) > 1 ) {
			sleep( $sleeptime );
		}
		$a++;
	}
	$end_time = microtime( true );
	$execution_time = ( $end_time - $start_time );
}

if ( $errors ) {
	print( "Errors:\n" );
	foreach ( $errors as $error ) {
		print( "   " . $error . "\n" );
	}
}
if ( $observationdata ) {
	print( "Sample ID\tLicense Holder\tLicense Photo\tURL\n" );

	foreach ( $observationdata as $observation ) {
		foreach ( $observation['photos'] as $photo ) {
			isset( $observation['sample_id'] ) ? print( $observation['sample_id']."\t" ) : print( "\t" );
			isset( $observation['license_holder'] ) ? print( $observation['license_holder']."\t" ) : print( "\t" );
			isset( $photo['license'] ) ? print( $photo['license']."\t" ) : print( "\t");
			isset( $photo['url'] ) ? print( $photo['url']."\n" ) : print( "\n" );
		}
	}
	print( "Execution time: " . $execution_time . " seconds.\n" );
}
if ( $observationdata ) {
	foreach ( $observationdata as $observation ) {
		foreach ( $observation['photos'] as $photo ) {
			$result = downloadFile( $photo['url'] );
			if ($result === true) {
				echo "File downloaded successfully!\n";
			} else {
				echo "Error downloading file: " . $result . "\n";
			}
		}
	}
}
print("------------------ SCRIPT TERMINATED ------------------\n");
