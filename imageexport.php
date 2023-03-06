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

// See if form was submitted.
if ( $_POST ) {
	// If an observation was posted, look up the data.
	if ( isset( $_POST['observations'] ) ) {
		$start_time = microtime( true );
		$observationlist = explode( "\n", $_POST['observations'] );
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
}
?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Language" content="en-us">
	<title>iNat2BOLD</title>

<style type="text/css">
body {
	font-family: "Trebuchet MS", Verdana, sans-serif;
	color:#777777;
	background: #FFFFFF;
	}
#content {
	margin: 2em;
	}
#errors {
	margin: 1em;
	color: #FF6666;
	font-weight: bold;
	}
.resulttable {
    background-color: #f8f9fa;
    color: #202122;
    margin: 1em 0;
    border: 1px solid #a2a9b1;
    border-collapse: collapse;
}
.resulttable > tr > th, .resulttable > * > tr > th {
    background-color: #eaecf0;
    text-align: center;
    font-weight: bold;
}
.resulttable > tr > th, .resulttable > tr > td, .resulttable > * > tr > th, .resulttable > * > tr > td {
    border: 1px solid #a2a9b1;
    padding: 0.2em 0.4em;
}
td.nowrap {
	white-space: nowrap;
}
p.optionaldata {
	line-height: 1.5;
}
</style>
<script src="./jquery.min.js"></script>
<script type="text/javascript">
$(document).ready(function () {
    $("#lookupform").submit(function () {
        $(".submitbtn").attr("disabled", true);
        return true;
    });
});
</script>
</head>
<body>
<div id="content">
<form id="lookupform" action="imageexport.php" method="post">
<p>
	Observation List (1 per line, max 95):<br/><textarea rows="5" cols="50" name="observations"></textarea>
</p>
<input class="submitbtn" type="submit" />
</form>

<?php
if ( $errors ) {
	print( '<p id="errors">' );
	print( 'Errors:<br/>' );
	foreach ( $errors as $error ) {
		print( $error . '<br/>' );
	}
	print( '</p>' );
}
if ( $observationdata ) {
	if ( !$fileoutput ) {
		print( '<h2>Image Data</h2>' );
		print( '<table class="resulttable" border="0" cellpadding="5" cellspacing="10">' );
		print( '<tr><th>Sample ID</th><th>License Holder</th><th>License</th><th>Photo URL</th></tr>' );

		foreach ( $observationdata as $observation ) {
			foreach ( $observation['photos'] as $photo ) {
				print( '<tr>' );
					isset( $observation['sample_id'] ) ? print( '<td class="nowrap">'.$observation['sample_id'].'</td>' ) : print( '<td></td>' );
					isset( $observation['license_holder'] ) ? print( '<td class="nowrap">'.$observation['license_holder'].'</td>' ) : print( '<td></td>' );
					isset( $photo['license'] ) ? print( '<td class="nowrap">'.$photo['license'].'</td>' ) : print( '<td></td>' );
					isset( $photo['url'] ) ? print( '<td class="nowrap">'.$photo['url'].'</td>' ) : print( '<td></td>' );
				print( '</tr>' );
			}
		}
		print( '</table>' );
		print( '<p>Execution time: ' . $execution_time . ' seconds.</p>' );
	}
}
?>
</div>
</body>
</html>
