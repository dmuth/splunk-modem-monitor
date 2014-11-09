#!/usr/bin/env php
<?php
/**
* This script reads the HTML status page from an Arris TG852G modem and parses the data.
*/

$config = array();

//
// The URL of the modem's status page
//
$config["url"] = "http://192.168.100.1/cgi-bin/status_cgi";
//$config["url"] = "http://10.255.0.1/"; // Debugging
$config["timeout"] = 5;
//$config["sleep"] = 10;
$config["sleep"] = 10; // Debugging
//$config["num_loops"] = 10;
$config["num_loops"] = 1; // Debugging


/**
* Read the contents of our status page from the modem.
*/
function readStatus($config) {

	$retval = "";

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $config["url"]);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $config["timeout"]);
	$retval = curl_exec($ch);
	curl_close($ch);

	return($retval);

} // End of readStatus()


/**
* Parse our HTML and retrieve information from the status page
*/
function parseStatus($html) {

	$retval = array();
	$retval["downstream"] = array();
	$retval["upstream"] = array();
	$retval["status"] = array();
	$retval["interface"] = array();

	$dom = new DOMDocument();

	//
	// Parse the HTML, suppressing any warnings because of invalid HTML.
	//
	@$dom->loadHTML($html);

	$trs = $dom->getElementsByTagName("tr");

	//
	// Loop through our <tr> tags, then our <td> tags.
	//
	foreach ($trs as $tr) {

		$tds = $tr->getElementsByTagName("td");
		$values = array();
		foreach ($tds as $td) {
			$values[] = $td->nodeValue;
		}

		//
		// For each grouping of <td> tags, determine what type of data 
		// it is (if any), and store it in the appropriate array.
		//
		if (strstr($values[0], "Downstream")) {
			$row = array();
			$row["name"] = $values[0];
			$row["dcid"] = $values[1];
			$row["freq_raw"] = $values[2];
			$row["freq"] = floatval($row["freq_raw"]);
			$row["power_raw"] = $values[3];
			$row["power"] = floatval($row["power_raw"]);
			$row["snr_raw"] = $values[4];
			$row["snr"] = floatval($row["snr_raw"]);
			$row["modulation"] = $values[5];
			$row["octets"] = $values[6];
			$row["correcteds"] = $values[7];
			//$row["correcteds"] = time() * 1000  + rand(0, 999); // Debugging
			//$row["correcteds"] = time() * 1000; // Debugging
			$row["uncorrectables"] = $values[8];
			//$row["uncorrectables"] = time() * 1000 + rand(0, 999); // Debugging
			//$row["uncorrectables"] = time() * 1000; // Debugging

			$retval["downstream"][] = $row;

		} else if (strstr($values[0], "Upstream")) {		
			$row = array();
			$row["name"] = $values[0];
			$row["ucid"] = $values[1];
			$row["freq_raw"] = $values[2];
			$row["freq"] = floatval($row["freq_raw"]);
			$row["power_raw"] = $values[3];
			$row["power"] = floatval($row["power_raw"]);
			$row["channel_type"] = $values[4];
			$row["symbol_rate_raw"] = $values[5];
			$row["symbol_rate"] = floatval($row["symbol_rate_raw"]);
			$row["modulation"] = $values[6];

			$retval["upstream"][] = $row;

		} else if (strstr($values[0], "System Uptime")) {
			$row = array();
			$row["system_uptime"] = $values[1];
			$retval["status"][] = $row;

		} else if (strstr($values[0], "CM Status")) {
			$row = array();
			$row["cm_status"] = $values[1];
			$retval["status"][] = $row;

		} else if (strstr($values[0], "Time and Date")) {
			$row = array();
			$row["time_and_date"] = $values[1];
			$retval["status"][] = $row;

		} else if (
			strstr($values[0], "LAN Port")
			|| strstr($values[0], "CABLE")
			|| strstr($values[0], "MTA")
			) {
			$row = array();
			$row["name"] = $values[0];
			$row["provisioned"] = $values[1];
			$row["state"] = $values[2];
			$row["speed_mbps"] = $values[3];
			$row["mac_address"] = $values[4];
			$retval["interface"][] = $row;

		} else {
			//
			// This is an unknown value.
			// For now, we're doing nothing, since it's probably spacing between 
			// tables or similar.
			//

		}

	}

	return($retval);

} // End of parseStatus()


/**
* This function converts our gaint array of data to key/value pairs 
* that Splunk can handle.
*
*
* @return {string} A giant multi-line string of key/value pairs.
*/
function convertArrayToKeyValue($data) {

	$retval = "";
	$pid = getmypid();

	foreach ($data as $key => $value) {

		foreach ($value as $key2 => $value2) {

			$line = gmdate("Y/m/d\ H:i:s") . "\t";

			foreach ($value2 as $key3 => $value3) {
				$line .= "${key3}=\"${value3}\"\t";
			}

			$line .= "type=\"${key}\"\t";
			$line .= "pid=${pid}\n";
			$retval .= $line;

		}
	}

	return($retval);

} // End of convertArrayToKeyValue()


/**
* This function is called repeatedly by main(), and does most of our work.
*/
function _main($config) {

	$retval = "";
	$html = readStatus($config);

	if (!$html) {
		throw new Exception("No HTML was returned from URL '". $config["url"] . "'");
	}

	$status = parseStatus($html);
	$retval = convertArrayToKeyValue($status);

	return($retval);

} // End of _main()


/**
* Our main entry point.
*/
function main($config) {

	//
	// Loop a prescribed number of times and then exit.
	// This is to keep Splunk from having to spawn a new PHP instance 
	// every few seconds.
	//
	for ($i=0; $i<$config["num_loops"]; $i++) {
		$output = _main($config);
		print $output;
		//print "Sleeping for ${config["sleep"]} seconds..\n"; // Debugging
		sleep($config["sleep"]);
	}

} // End of main()


try {
	main($config);

} catch (Exception $e) {
	$error = $e->getMessage() . ": " . $e->getTraceAsString();
	$error = str_replace("\r", " ", $error);
	$error = str_replace("\n", " ", $error);
	print "error=\"" . $error ."\"\n";

}



