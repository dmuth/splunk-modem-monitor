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
$config["timeout"] = 5;


/**
* Read the contents of our status page from the modem.
*/
function readStatusFromModem($config) {

	$retval = "";

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $config["url"]);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $config["timeout"]);
	$retval = curl_exec($ch);
	curl_close($ch);

	return($retval);

} // End of readFromModem()


/**
* Our main entry point.
*/
function main($config) {

	$html = readStatusFromModem($config);

	if (!$html) {
		throw new Exception("No HTML was returned from URL '". $config["url"] . "'");
	}
print $html;

// parseDownstream()
// parseUpstream()
// parseStatus()
// parseInterfaces()

/*
TODO:
- test timeouts
- test parsing
- check delta command?
	- debugging switches for counters
*/

} // End of main()


try {
	main($config);

} catch (Exception $e) {
	$error = $e->getMessage() . ": " . $e->getTraceAsString();
	$error = str_replace("\r", " ", $error);
	$error = str_replace("\n", " ", $error);
	print "error=\"" . $error ."\"\n";

}



