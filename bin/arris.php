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
$config["url_event_log"] = "http://192.168.100.1/cgi-bin/event_cgi";
//$config["url_event_log"] = "http://10.255.0.1"; // Debugging
$config["timeout"] = 5;

$config["sleep"] = 10;
//$config["sleep"] = 1; // Debugging
$config["num_loops"] = 10;
//$config["num_loops"] = 1; // Debugging

//
// Where will we write the Event Log?
//
$config["event_log"] = dirname(__FILE__) . "/../arris-event.log";


/**
* Read the contents of our status page from the modem.
*/
function readFromUrl($url, $timeout) {

	$retval = "";

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	$retval = curl_exec($ch);
	curl_close($ch);

	return($retval);

} // End of readFromUrl()


/**
* Return a timestamp in GMT that Splunk can understand.
*/
function getTimeStamp() {
	$retval = gmdate("Y/m/d\ H:i:s");
	return($retval);
} // End of getTimeStamp()


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
*
* Convert an "inner" array, where each element corresponds to a specific 
* event line which contains an array of key/value combinations, into a 
* string.  The reason for this funciton is because this logic is used 
* by both the parts of this progrma which read the modem data as well 
* as the parts that read the Event Log. (DRY Principle)
*
* @return {string} A multi-line string of key/value pairs
*/
function convertInnerArrayToKeyValue($data, $type) {

	$retval = "";
	$pid = getmypid();

	foreach ($data as $key => $value) {

		$line = getTimestamp() . "\t";

		foreach ($value as $key2 => $value2) {
			$line .= "${key2}=\"${value2}\"\t";
		}

		if ($type) {
			$line .= "type=\"${type}\"\t";
		}

		$line .= "pid=${pid}\n";

		$retval .= $line;

	}

	return($retval);

} // End of convertInnerArrayToKeyValue()


/**
* This function converts our gaint array of data to key/value pairs 
* that Splunk can handle.
*
*
* @return {string} A giant multi-line string of key/value pairs.
*/
function convertArrayToKeyValue($data) {

	$retval = "";

	foreach ($data as $key => $value) {

		$retval .= convertInnerArrayToKeyValue($value, $key);

	}

	return($retval);

} // End of convertArrayToKeyValue()


/**
* Main function to read stats from the modem.
*/
function _mainStats($config) {

	$retval = "";
	$html = readFromUrl($config["url"], $config["timeout"]);

	if (!$html) {
		throw new Exception("No HTML was returned from URL '". $config["url"] . "'");
	}

	$status = parseStatus($html);
	$retval = convertArrayToKeyValue($status);

	return($retval);

} // End of _mainSats()


/**
* Sanity check permissions on the Event Log file (if it exists) or the 
* directory (if it does not).
*/
function sanityCheckLogFileAndDirectory($file) {

	//
	// If our file exists, make sure it is readable and writeable
	//
	if (is_file($file)) {

		if (!is_readable($file)) {
			throw new Exception("Unable to read from Event Log '${file}'!");
		}

		//
		// Seems like the best place for this sanity check is next to the 
		// readable sanity check for the same file, for now.
		//
		if (!is_writable($file)) {
			throw new Exception("Unable to write to Event Log '${file}'!");
		}

	} else {
		//
		// If the file doesn't exist, create it.
		//
		$dir = dirname($file);
		if (!is_writable($dir)) {
			throw new Exception("Unable to write to directory '${dir}'!");
		}

	}

} // End of sanityCheckLogFileAndDirectory()


/**
* Read the last log line from our Event Log saved on disk.
*
* @param {string} $file The filename to read from. If it does not exist, 
*	it is created and an empty string is returned.
*
* @return {string} The last line of the logfile, which could be an 
*	empty string.
*/
function readLastLogLineFromFile($file) {

	$retval = "";

	sanityCheckLogFileAndDirectory($file);


	//
	// Open the file for reading and writing, place the file pointer 
	// at the end of the file, create the file if it does not exist.
	//
	$fp = fopen($file, "a+");
	if (!$fp) {
		throw new Exception("Unable to open Event Log '${file}' for writing");
	}

	//
	// Start reading 10,000 bytes from the end of the file.  Based on what 
	// I've seen from Arris modems, that should be plenty far back.
	//
	$index = filesize($file);
	$index -= 10000;
	if ($index < 0) {
		$index = 0;
	}

	if (fseek($fp, $index) == -1) {
		throw new Exception("Unable to seek to index ${index} in file ${file}!");
	}

	$last_line = "";
	while ($line = fgets($fp)) {
		$last_line = $line;
	}

	//
	// Remove the leading datestamp and trailing PID and newline just like 
	// we're going to do in truncateEventLogLines().
	//
	$last_line = preg_replace("/[^\t]+\t/", "", $last_line, 1);
	$last_line = preg_replace("/\tpid=.*/", "", $last_line);
	$retval = rtrim($last_line);

	if (!fclose($fp)) {
		throw new Exception("Unable to close Event Log '${file}'!");
	}

	return($retval);

} // End of readLastLogLineFromFile()


/**
* Read our event log from the modem.
*/
function readEventLogFromModem($config) {

	$retval = "";

	$html = readFromUrl($config["url_event_log"], $config["timeout"]);

	$dom = new DOMDocument();

	//
	// Parse the HTML, suppressing any warnings because of invalid HTML.
	//
	@$dom->loadHTML($html);

	//
	// The Event Log is in our second table
	//
	$tables = $dom->getElementsByTagName("table");
	$table = $tables->item(1);

	$trs = $table->getElementsByTagName("tr");

	$rows = array();
	$skip_first_line = false;

	//
	// Loop through our rows, skipping the first row (the header)
	// and pull out the value sfrom each row
	//
	foreach ($trs as $tr) {

		if (!$skip_first_line) {
			$skip_first_line = true;
			continue;
		}

		$row = array();

		$tds = $tr->getElementsByTagName("td");

		$values = array();
		foreach ($tds as $td) {
			$values[] = $td->nodeValue;
		}

		$row["date_time"] = $values[0];
		$row["event_id"] = $values[1];
		$row["event_level"] = $values[2];
		$row["description"] = $values[3];

		$rows[] = $row;

	}

	$retval = convertInnerArrayToKeyValue($rows, "event_log");

	return($retval);

} // End of readEventLogFromModem()


/**
* Unlike _mainEventLog(), I *ASSURE YOU* that this function is complicated. ;-)
*
* What we're going to do is take the lines we just read from the modem and 
* work our way through them. The moment we find a line which matches the 
* last line in the stored Event Log file on our disk, we discard everything 
* read up to that point, because we already have it in our log.  Then we 
* take what's left, and return that, as it needs to be written to our 
* logfile on disk.
*
* @param {string} $lines The lines we received from the modem, 
*	in key/value format.
*
* @param {string} $last_line The last line inthe Event Log file. This may be an empty string!
*
* @return {string} A multi-line string of line that we want to
*	write to the Event Log file. This may be an empty string!
*
*/
function truncateEventLogLines($lines, $last_line) {

	$retval = "";

	//
	// No last line? Full stop.
	//
	if (!$last_line) {
		$retval = $lines;
		return($retval);
	}

	$match_found = false;
	$stop_match_checking = false;

	//
	// No last line found due to an empty Event Log file? Don't even 
	// bother checking.
	//
	if (!$last_line) {
		$stop_match_checking = true;
	}

	//
	// Turn our lines into an array and remove the last (empty) element.
	//
	$lines_array = split("\n", $lines);
	array_pop($lines_array);

	foreach ($lines_array as $key => $value) {

		if (!$stop_match_checking) {
			//
			// If we're checking, remove the leading date and the trailing PID
			//
			$stripped = preg_replace("/[^\t]+\t/", "", $value, 1);
			$stripped = preg_replace("/\tpid=.*/", "", $stripped);

			//
			// If this matches the last line in the logfile, make a note and stop checking.
			//
			if ($stripped == $last_line) {
				$match_found = true;
				$stop_match_checking = true;
			}

			//
			// If we didn't find a match, this line goes into the return value.
			// If we DID find a match, however, wipe the return value.  
			// Everything after this line is eligible for the logfile, though.
			//
			if (!$match_found) {
				$retval .= $value . "\n";
			} else {
				$retval = "";
			}

		} else {
			$retval .= $value . "\n";

		}

	}

	return($retval);

} // End of truncatEventLogLines()


/**
* Write our lines from the Event Log to the file.
*
*/
function writeEventLogToFile($lines, $file) {

	$fp = fopen($file, "a");
	if (!$fp) {
		throw new Exception("Unable to open Event Log '${file}' for writing");
	}

	$bytes_written = fwrite($fp, $lines);
	if (!$bytes_written) {
		throw new Exception("Only ${bytes_written} bytes were written to file '${file}'!");
	}

	if (!fclose($fp)) {
		throw new Exception("Unable to close Event Log '${file}'!");
	}

} // End of writeEventLogToFile()


/**
*
* Fetch the Event Log and write it out to a file that Splunk can then read in.
* Why do it this way? Because the Event Log rarely changes and we don't 
* to keep sending the same stuff to Splunk.  Instead, we will look at the last
* line stored in the logfile and write everything after that from the
* mode.  It's not as complex as it sounds, I promise. :-)
*
*/
function _mainEventLog($config) {

	//
	// First step, get the last log line from our file
	//
	$last_line = readLastLogLineFromFile($config["event_log"]);

	$lines = readEventLogFromModem($config);

	$lines = truncateEventLogLines($lines, $last_line);

	if ($lines) {
		writeEventLogToFile($lines, $config["event_log"]);
	}

} // End of _mainEventLog()


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

		try {
			//
			// Fetch the stats and print them on stdout
			//
			$output = _mainStats($config);
			print $output;
			//print "Sleeping for ${config["sleep"]} seconds..\n"; // Debugging

			//
			// Fetch the Event Log and write it out to a file that Splunk
			// can then read in
			//
			_mainEventLog($config);

		} catch (Exception $e) {
			$error = $e->getMessage() . ": " . $e->getTraceAsString();
			$error = str_replace("\r", " ", $error);
			$error = str_replace("\n", " ", $error);
			print "error=\"" . $error ."\" pid=\"" . getmypid() . "\"\n";

		}

		sleep($config["sleep"]);

	}

} // End of main()


main($config);



