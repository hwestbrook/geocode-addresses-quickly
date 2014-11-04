<?php

// this loads geocoder php
// geocoder php must be installed locally for this to work correctly
require 'vendor/autoload.php';
 

//////////////////////////
// this gets the settings for gecoding ready
//////////////////////////

$adapter  = new \Geocoder\HttpAdapter\CurlHttpAdapter();
$geocoder = new \Geocoder\Geocoder();

$chain    = new \Geocoder\Provider\ChainProvider(array(
    new \Geocoder\Provider\GoogleMapsProvider($adapter, 'your_key_here'),
    // new \Geocoder\Provider\GoogleMapsProvider($adapter),
    new \Geocoder\Provider\BingMapsProvider($adapter, 'your_key_here'),
    new \Geocoder\Provider\OpenStreetMapProvider($adapter),
	new \Geocoder\Provider\MapQuestProvider($adapter, 'your_key_here')
));

// this uses the chain created above to try each of the geocoding mechanisms available
$geocoder->registerProvider($chain);

// options if you want to be specific
// google_maps
// bing_maps
// map_quest
// openstreetmap


//////////////////////////
// this connects us to the mysql db
//////////////////////////

$mysqli = new mysqli("your_database_host_name", "user_name", "password", "database_name");
if ($mysqli->connect_errno) {
    echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}

// the database requires the following: 
// 1. table named address_table
// 2. the following fields
//      - `db_id`, 
//      - `address1`, 
//      - `address2`, 
//      - `city`, 
//      - `state`, 
//      - `zip_code`, 
//      - `country` 
//      - `geo_attempted`
//      - `lat`,
//      - `lon`,
//      - `geo_lat`,
//      - `geo_lon`,
//      - `geo_streetNumber`,
//      - `geo_streetName`,
//      - `geo_cityDistrict`,
//      - `geo_city`,
//      - `geo_zipcode`,
//      - `geo_county`,
//      - `geo_countyCode`,
//      - `geo_region`,
//      - `geo_regionCode`,
//      - `geo_country`,
//      - `geo_countryCode`,
//      - `geo_timezone`,
//      - `geo_attempted`,
//      - `geo_timeStamp`

//////////////////////////
// now we need to 
// 1. get a data set from mysql (10 items?) for geocoding
// 2. loop through those 10 items and geocode with geocoder
// 3. input the geocode information to the database
//////////////////////////


// query the mysql db for items
	$toGeocodeCount = "SELECT 
						`db_id`, 
						`address1`, 
						`address2`, 
						`city`, 
						`state`, 
						`zip_code`, 
						`country` 
					FROM 
						`address_table` 
					WHERE 
						`lat` = 0 AND `geo_attempted` IS NULL AND `important` = 1

					";

	$toGeocodeCountres = $mysqli->query($toGeocodeCount);

	$rows = mysqli_num_rows($toGeocodeCountres);
	echo PHP_EOL . "Rows: " . $rows;
	$loops = (int) $rows / 10;
	echo PHP_EOL . "Loops: " . $loops;


// loop through our entire database
// counter variable
$i = 0;

// while loop
while ( $i < $loops) {
	echo PHP_EOL. "On loop " . $i . " of " . $loops . " loops" . PHP_EOL;

	// query the mysql db for items
	$toGeocodeQry = "SELECT 
						`db_id`, 
						`address1`, 
						`address2`, 
						`city`, 
						`state`, 
						`zip_code`, 
						`country` 
					FROM 
						`address_table` 
					WHERE 
						`lat` = 0 AND `geo_attempted` IS NULL AND `important` = 1

					LIMIT 10";

	$toGeocoderes = $mysqli->query($toGeocodeQry);

	// echo "Result set order...\n";
	// $res->data_seek(0);

	// our loop to go through these 10 rows of data
	while ($row = $toGeocoderes->fetch_assoc()) {
	    
	    // build the address for geocoder
	    $lookfor = 
	    	$row['address1'] . ' ' .
	    	$row['address2'] . ' ' .
	    	$row['city'] . ' ' .
	    	$row['state'] . ' ' .
	    	$row['zip_code'] . ' ' .
	    	$row['country']
	    ;

	    echo $lookfor;

	    // Try to geocode.
	    try {
	        // geocode!
	    	$result = $geocoder->geocode($lookfor);

	    } catch (Exception $e) {
	        echo $e->getMessage();
	    }

	    // print the result!
		print_r($result);
		echo PHP_EOL . $row['db_id'] . PHP_EOL;
		// echo $result['latitude'];
		// echo $result['longitude'];

		// mark that we have attempted to get a geocode with php geocoder
		$geoAttempted = 1;
		// when was the last time we geocoded?
		// $timestamp = date('Y-m-d G:i:s');

		// update our record in the DB
		$stmt = $mysqli->prepare("UPDATE `address_table` 
								SET `lat` = ?,
								    `lon` = ?,
								    `geo_lat` = ?,
								    `geo_lon` = ?,
								    `geo_streetNumber` = ?,
								    `geo_streetName` = ?,
								    `geo_cityDistrict` = ?,
								    `geo_city` = ?,
								    `geo_zipcode` = ?,
								    `geo_county` = ?,
								    `geo_countyCode` = ?,
								    `geo_region` = ?,
								    `geo_regionCode` = ?,
								    `geo_country` = ?,
								    `geo_countryCode` = ?,
								    `geo_timezone` = ?,
								    `geo_attempted` = ?,
								    `geo_timeStamp` = now()
		   						WHERE `ns_id` = ?");

		$stmt->bind_param('ddddssssssssssssii',
			$result['latitude'],
			$result['longitude'],
			$result['latitude'],
			$result['longitude'],
			$result['streetNumber'],
			$result['streetName'],
			$result['cityDistrict'],
			$result['city'],
			$result['zipcode'],
			$result['county'],
			$result['countyCode'],
			$result['region'],
			$result['regionCode'],
			$result['country'],
			$result['countryCode'],
			$result['timezone'],
			$geoAttempted,
			$row['db_id']
		   );
		$stmt->execute(); 
		$stmt->close();

		// make it sleep because we cannot do more than 5 per second when using google maps free
		// usleep(200001);

		}

		// increment $i
		$i++;
}



?>