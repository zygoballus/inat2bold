<?php
// This script is dual licensed under the MIT License and the CC0 License.
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
ini_set( 'max_execution_time', 600 );

$useragent = 'iNat2BOLD Script/1.0';
$inatapi = 'https://api.inaturalist.org/v1/';
$errors = [];
$observationdata = [];
$guess = true;

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

function get_country( $placeids, $observationid ) {
	global $inatapi, $errors;
	$placelist = implode( ',', $placeids );
	$url = $inatapi . 'places/' . $placelist . '?admin_level=0'; // admin_level 0 is country
	$inatdata = make_curl_request( $url );
	if ( $inatdata && $inatdata['results'] && $inatdata['results'][0] && $inatdata['results'][0]['name'] ) {
		return $inatdata['results'][0]['name'];
	} else {
		$errors[] = 'Country not found for observation ' . $observationid . '.';
		return null;
	}
}

function get_state( $placeids, $observationid ) {
	global $inatapi, $errors;
	$placelist = implode( ',', $placeids );
	$url = $inatapi . 'places/' . $placelist . '?admin_level=10'; // admin_level 10 is state/province/district
	$inatdata = make_curl_request( $url );
	if ( $inatdata && $inatdata['results'] && $inatdata['results'][0] && $inatdata['results'][0]['name'] ) {
		return $inatdata['results'][0]['name'];
	} else {
		$errors[] = 'State not found for observation ' . $observationid . '.';
		return null;
	}
}

function get_region( $placeids, $observationid ) {
	global $inatapi, $errors;
	$placelist = implode( ',', $placeids );
	$url = $inatapi . 'places/' . $placelist . '?admin_level=20'; // admin_level 20 is county/region
	$inatdata = make_curl_request( $url );
	if ( $inatdata && $inatdata['results'] && $inatdata['results'][0] && $inatdata['results'][0]['name'] ) {
		return $inatdata['results'][0]['name'];
	} else {
		return null;
	}
}

function get_taxonomy( $ancestorids ) {
	global $inatapi, $errors;
	$ancestorlist = implode( ',', $ancestorids );
	$url = $inatapi . 'taxa/' . $ancestorlist;
	$inatdata = make_curl_request( $url );
	if ( $inatdata && $inatdata['results'] ) {
		$taxonomy = [];
		foreach ( $inatdata['results'] as $taxon ) {
			switch ( $taxon['rank'] ) {
				case 'phylum':
					$taxonomy['phylum'] = $taxon['name'];
					break;
				case 'class':
					$taxonomy['class'] = $taxon['name'];
					break;
				case 'order':
					$taxonomy['order'] = $taxon['name'];
					break;
				case 'family':
					$taxonomy['family'] = $taxon['name'];
					break;
				case 'subfamily':
					$taxonomy['subfamily'] = $taxon['name'];
					break;
				case 'genus':
					$taxonomy['genus'] = $taxon['name'];
					break;
				case 'species':
					$taxonomy['species'] = $taxon['name'];
					break;
			}
		}
		return $taxonomy;
	} else {
		return null;
	}
}

function get_observation_data( $observationid, $guessplace ) {
	global $inatapi, $errors;
	$data = [];
	if ( $observationid ) {
		$url = $inatapi . 'observations/' . $observationid;
		$inatdata = make_curl_request( $url );
		if ( $inatdata && $inatdata['results'] && $inatdata['results'][0] ) {
			$results = $inatdata['results'][0];
			$montharray = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
			$data['collection_date'] = $results['observed_on_details']['day'] . '-' . $montharray[$results['observed_on_details']['month']] . '-' . $results['observed_on_details']['year'];
			$data['country'] = get_country( $results['place_ids'], $observationid );
			$data['state'] = get_state( $results['place_ids'], $observationid );
			$data['region'] = get_region( $results['place_ids'], $observationid );
			$taxonomy = get_taxonomy( $results['taxon']['ancestor_ids'] );
			if ( $taxonomy ) {
				$data = array_merge( $data, $taxonomy );
			}
			if ( $guessplace ) {
				$data['exact_site'] = $results['place_guess'];
			}
			$location = explode( ',', $results['location'] );
			$data['latitude'] = $location[0];
			$data['longitude'] = $location[1];
			$data['coordinate_accuracy'] = $results['positional_accuracy'] . ' m';
			$data['external_urls'] = 'https://www.inaturalist.org/observations/' . $observationid;
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
		if ( isset( $_POST['guess'] ) ) {
			$guessplace = true;
		} else {
			$guessplace = false;
		}
		$observationlist = explode( "\n", $_POST['observations'] );
		// Limit to 96 observations.
		$observationlist = array_slice( $observationlist, 0, 96 );
		$a = 0;
		foreach ( $observationlist as $observationid ) {
			if ( preg_match( '/\d+/', $observationid, $matches ) ) {
				$observationid = $matches[0];
				$observationdata[$a] = get_observation_data( $observationid, $guessplace );
				if ( isset( $_POST['identifier'] ) ) $observationdata[$a]['identifier'] = $_POST['identifier'];
				if ( isset( $_POST['identifier_email'] ) ) $observationdata[$a]['identifier_email'] = $_POST['identifier_email'];
				if ( isset( $_POST['identifier_institution'] ) ) $observationdata[$a]['identifier_institution'] = $_POST['identifier_institution'];
				if ( isset( $_POST['identification_method'] ) ) $observationdata[$a]['identification_method'] = $_POST['identification_method'];
				if ( isset( $_POST['voucher_status'] ) ) $observationdata[$a]['voucher_status'] = $_POST['voucher_status'];
				if ( isset( $_POST['tissue_descriptor'] ) ) $observationdata[$a]['tissue_descriptor'] = $_POST['tissue_descriptor'];
				if ( isset( $_POST['collectors'] ) ) $observationdata[$a]['collectors'] = $_POST['collectors'];
				if ( isset( $_POST['gps_source'] ) ) $observationdata[$a]['gps_source'] = $_POST['gps_source'];
				if ( isset( $_POST['habitat'] ) ) $observationdata[$a]['habitat'] = $_POST['habitat'];
				if ( isset( $_POST['sampling_protocol'] ) ) $observationdata[$a]['sampling_protocol'] = $_POST['sampling_protocol'];
				if ( isset( $_POST['site_code'] ) ) $observationdata[$a]['site_code'] = $_POST['site_code'];
				if ( isset( $_POST['collection_event_id'] ) ) $observationdata[$a]['collection_event_id'] = $_POST['collection_event_id'];
			} else {
				$observationdata[$a] = null;
			}
			if ( count( $observationlist ) > 1 ) {
				sleep(2);
			}
			$a++;
		}
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
<form id="lookupform" action="inat2bold.php" method="post">
<p>
	Observation List (1 per line, max 96):<br/><textarea rows="5" cols="50" name="observations"></textarea>
</p>
<p class="optionaldata">
	<input type="checkbox" id="guess" name="guess" <?php if ($guess) echo "checked";?> value="yes">
	<label for="guess">Map iNaturalist place guess to BOLD exact site.</label><br/>
	Optional data (not supplied by iNaturalist):<br/>
	&nbsp;&nbsp;&nbsp;&nbsp;<label for="identifier">Identifier:</label>
	<input type="text" id="identifier" name="identifier" /><br/>
	&nbsp;&nbsp;&nbsp;&nbsp;<label for="identifier_email">Identifier email:</label>
	<input type="text" id="identifier_email" name="identifier_email" /><br/>
	&nbsp;&nbsp;&nbsp;&nbsp;<label for="identifier_institution">Identifier institution:</label>
	<input type="text" id="identifier_institution" name="identifier_institution" /><br/>
	&nbsp;&nbsp;&nbsp;&nbsp;<label for="identification_method">Identification method:</label>
	<input type="text" id="identification_method" name="identification_method" /><br/>
	&nbsp;&nbsp;&nbsp;&nbsp;<label for="voucher_status">Voucher status:</label>
	<input type="text" id="voucher_status" name="voucher_status" /><br/>
	&nbsp;&nbsp;&nbsp;&nbsp;<label for="tissue_descriptor">Tissue descriptor:</label>
	<input type="text" id="tissue_descriptor" name="tissue_descriptor" /><br/>
	&nbsp;&nbsp;&nbsp;&nbsp;<label for="collectors">Collectors:</label>
	<input type="text" id="collectors" name="collectors" /><br/>
	&nbsp;&nbsp;&nbsp;&nbsp;<label for="gps_source">GPS source:</label>
	<input type="text" id="gps_source" name="gps_source" /><br/>
	&nbsp;&nbsp;&nbsp;&nbsp;<label for="habitat">Habitat:</label>
	<input type="text" id="habitat" name="habitat" /><br/>
	&nbsp;&nbsp;&nbsp;&nbsp;<label for="sampling_protocol">Sampling protocol:</label>
	<input type="text" id="sampling_protocol" name="sampling_protocol" /><br/>
	&nbsp;&nbsp;&nbsp;&nbsp;<label for="site_code">Site code:</label>
	<input type="text" id="site_code" name="site_code" /><br/>
	&nbsp;&nbsp;&nbsp;&nbsp;<label for="collection_event_id">Collection event ID:</label>
	<input type="text" id="collection_event_id" name="collection_event_id" /><br/>
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

// Taxonomy Table
	print( '<h2>Taxonomy</h2>' );
	print( '<table class="resulttable" border="0" cellpadding="5" cellspacing="10">' );
	print( '<tr><th>Phylum</th><th>Class</th><th>Order</th><th>Family</th><th>Subfamily</th><th>Genus</th><th>Species</th><th>Identifier</th><th>Identifier Email</th><th>Identifier Institution</th><th>Identification Method</th><th>Taxonomy Notes</th></tr>' );

	$x = 0;
	foreach ( $observationdata as $observation ) {
		print( '<tr>' );
			isset( $observation['phylum'] ) ? print( '<td>'.$observation['phylum'].'</td>' ) : print( '<td></td>' );
			isset( $observation['class'] ) ? print( '<td>'.$observation['class'].'</td>' ) : print( '<td></td>' );
			isset( $observation['order'] ) ? print( '<td>'.$observation['order'].'</td>' ) : print( '<td></td>' );
			isset( $observation['family'] ) ? print( '<td>'.$observation['family'].'</td>' ) : print( '<td></td>' );
			isset( $observation['subfamily'] ) ? print( '<td>'.$observation['subfamily'].'</td>' ) : print( '<td></td>' );
			isset( $observation['genus'] ) ? print( '<td>'.$observation['genus'].'</td>' ) : print( '<td></td>' );
			isset( $observation['species'] ) ? print( '<td>'.$observation['species'].'</td>' ) : print( '<td></td>' );
			isset( $observation['identifier'] ) ? print( '<td>'.$observation['identifier'].'</td>' ) : print( '<td></td>' );
			isset( $observation['identifier_email'] ) ? print( '<td>'.$observation['identifier_email'].'</td>' ) : print( '<td></td>' );
			isset( $observation['identifier_institution'] ) ? print( '<td>'.$observation['identifier_institution'].'</td>' ) : print( '<td></td>' );
			isset( $observation['identification_method'] ) ? print( '<td>'.$observation['identification_method'].'</td>' ) : print( '<td></td>' );
			isset( $observation['taxonomy_notes'] ) ? print( '<td>'.$observation['taxonomy_notes'].'</td>' ) : print( '<td></td>' );
		print( '</tr>' );
		$x++;
	}
	print( '</table>' );

// Specimen Details Table
	print( '<h2>Specimen Details</h2>' );
	print( '<table class="resulttable" border="0" cellpadding="5" cellspacing="10">' );
	print( '<tr><th>Sex</th><th>Reproduction</th><th>Life Stage</th><th>Extra Info</th><th>Notes</th><th>Voucher Status</th><th>Tissue Descriptor</th><th>Associated Taxa</th><th>Associated Specimens</th><th>External URLs</th></tr>' );

	$x = 0;
	foreach ( $observationdata as $observation ) {
		print( '<tr>' );
			isset( $observation['sex'] ) ? print( '<td>'.$observation['sex'].'</td>' ) : print( '<td></td>' );
			isset( $observation['reproduction'] ) ? print( '<td>'.$observation['reproduction'].'</td>' ) : print( '<td></td>' );
			isset( $observation['life_stage'] ) ? print( '<td>'.$observation['life_stage'].'</td>' ) : print( '<td></td>' );
			isset( $observation['extra_info'] ) ? print( '<td>'.$observation['extra_info'].'</td>' ) : print( '<td></td>' );
			isset( $observation['notes'] ) ? print( '<td>'.$observation['notes'].'</td>' ) : print( '<td></td>' );
			isset( $observation['voucher_status'] ) ? print( '<td>'.$observation['voucher_status'].'</td>' ) : print( '<td></td>' );
			isset( $observation['tissue_descriptor'] ) ? print( '<td>'.$observation['tissue_descriptor'].'</td>' ) : print( '<td></td>' );
			isset( $observation['associated_taxa'] ) ? print( '<td>'.$observation['associated_taxa'].'</td>' ) : print( '<td></td>' );
			isset( $observation['associated specimens'] ) ? print( '<td>'.$observation['associated specimens'].'</td>' ) : print( '<td></td>' );
			isset( $observation['external_urls'] ) ? print( '<td>'.$observation['external_urls'].'</td>' ) : print( '<td></td>' );
		print( '</tr>' );
		$x++;
	}
	print( '</table>' );
	
// Collection Data Table
	print( '<h2>Collection Data</h2>' );
	print( '<table class="resulttable" border="0" cellpadding="5" cellspacing="10">' );
	print( '<tr><th>Collectors</th><th>Collection Date</th><th>Country/Ocean</th><th>State/Province</th><th>Region</th><th>Sector</th><th>Exact Site</th><th>Latitude</th><th>Longitude</th><th>Elevation</th><th>Depth</th><th>Elevation Precision</th><th>Depth Precision</th><th>GPS Source</th><th>Coordinate Accuracy</th><th>Event Time</th><th>Collection Date Accuracy</th><th>Habitat</th><th>Sampling Protocol</th><th>Collection Notes</th><th>Site Code</th><th>Collection Event ID</th></tr>' );

	$x = 0;
	foreach ( $observationdata as $observation ) {
		print( '<tr>' );
			isset( $observation['collectors'] ) ? print( '<td>'.$observation['collectors'].'</td>' ) : print( '<td></td>' );
			isset( $observation['collection_date'] ) ? print( '<td class="nowrap">'.$observation['collection_date'].'</td>' ) : print( '<td></td>' );
			isset( $observation['country'] ) ? print( '<td>'.$observation['country'].'</td>' ) : print( '<td></td>' );
			isset( $observation['state'] ) ? print( '<td>'.$observation['state'].'</td>' ) : print( '<td></td>' );
			isset( $observation['region'] ) ? print( '<td>'.$observation['region'].'</td>' ) : print( '<td></td>' );
			isset( $observation['sector'] ) ? print( '<td>'.$observation['sector'].'</td>' ) : print( '<td></td>' );
			isset( $observation['exact_site'] ) ? print( '<td>'.$observation['exact_site'].'</td>' ) : print( '<td></td>' );
			isset( $observation['latitude'] ) ? print( '<td>'.$observation['latitude'].'</td>' ) : print( '<td></td>' );
			isset( $observation['longitude'] ) ? print( '<td>'.$observation['longitude'].'</td>' ) : print( '<td></td>' );
			isset( $observation['elevation'] ) ? print( '<td class="nowrap">'.$observation['elevation'].'</td>' ) : print( '<td></td>' );
			isset( $observation['depth'] ) ? print( '<td class="nowrap">'.$observation['depth'].'</td>' ) : print( '<td></td>' );
			isset( $observation['elevation_precision'] ) ? print( '<td>'.$observation['elevation_precision'].'</td>' ) : print( '<td></td>' );
			isset( $observation['depth_precision'] ) ? print( '<td>'.$observation['depth_precision'].'</td>' ) : print( '<td></td>' );
			isset( $observation['gps_source'] ) ? print( '<td>'.$observation['gps_source'].'</td>' ) : print( '<td></td>' );
			isset( $observation['coordinate_accuracy'] ) ? print( '<td>'.$observation['coordinate_accuracy'].'</td>' ) : print( '<td></td>' );
			isset( $observation['event_time'] ) ? print( '<td>'.$observation['event_time'].'</td>' ) : print( '<td></td>' );
			isset( $observation['collection_date_accuracy'] ) ? print( '<td>'.$observation['collection_date_accuracy'].'</td>' ) : print( '<td></td>' );
			isset( $observation['habitat'] ) ? print( '<td>'.$observation['habitat'].'</td>' ) : print( '<td></td>' );
			isset( $observation['sampling_protocol'] ) ? print( '<td>'.$observation['sampling_protocol'].'</td>' ) : print( '<td></td>' );
			isset( $observation['collection_notes'] ) ? print( '<td>'.$observation['collection_notes'].'</td>' ) : print( '<td></td>' );
			isset( $observation['site_code'] ) ? print( '<td>'.$observation['site_code'].'</td>' ) : print( '<td></td>' );
			isset( $observation['collection_event_id'] ) ? print( '<td>'.$observation['collection_event_id'].'</td>' ) : print( '<td></td>' );
		print( '</tr>' );
		$x++;
	}
	print( '</table>' );
}
?>

</div>
</body>
</html>
