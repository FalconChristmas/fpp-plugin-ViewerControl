<?php
$a = session_id();
if(empty($a))
{
    session_start();
}

/////////////////////////////////////////////////////////////////////////////
$siteBase = 'http://www.controlmylights.com';
/////////////////////////////////////////////////////////////////////////////

$skipJSsettings = 1;
require_once('common.php');
require_once('commandsocket.php');
require_once('universeentry.php');
require_once('playlistentry.php');
require_once('pluginconfig.php');

$command_array = Array(
	"prepNextItem"    => 'prepNextItem',
	"loadNextItem"    => 'loadNextItem',
	"startedNextItem" => 'startedNextItem',
	"currentStatus"   => 'currentStatus',
	"enableControl"   => 'enableControl',
	"disableControl"  => 'disableControl',
	"clearQueue"      => 'clearQueue',
);

$command = "";
$args = Array();

if ( isset($_GET['command']) && !empty($_GET['command']) ) {
	$command = $_GET['command'];
	$args = $_GET;
} else if ( isset($_POST['command']) && !empty($_POST['command']) ) {
	$command = $_POST['command'];
	$args = $_POST;
}

if (array_key_exists($command,$command_array) )
{
	global $debug;

	if ( $debug )
		error_log("Calling " .$command);

	call_user_func($command_array[$command]);
}
return;


/////////////////////////////////////////////////////////////////////////////

function returnText($json) {
	header( "Content-Type: application/json");

	echo $json;

	exit(0);
}

function returnJSON($arr) {
	header( "Content-Type: application/json");

	echo json_encode($arr);

	exit(0);
}


function prepHelper() {
	global $settings;
	global $pluginSettings;
	global $siteBase;

	$reqFile = $settings['mediaDirectory'] . '/plugins/' . $pluginSettings['plugin'] . '/tmp/request.json';
	$nextFile = $settings['mediaDirectory'] . '/plugins/' . $pluginSettings['plugin'] . '/tmp/next.json';

	if (file_exists($reqFile))
		unlink($reqFile);
	if (file_exists($nextFile))
		unlink($nextFile);

	$url = $siteBase . '/api/site/' . $pluginSettings['SiteCode'] . '/queue/peek';

	$json = file_get_contents($url);
	$data = json_decode($json, true);

	if (isset($data['PlayerData']))
	{
		$f = fopen($reqFile, 'w');
		fwrite($f, json_encode(json_decode($json), JSON_PRETTY_PRINT));
		fclose($f);

		$playlistName = $data{'PlayerData'};

		$playlist = loadPlaylist($playlistName);

		$entries = $playlist['mainPlaylist'];

		$result['playlistEntries'] = $entries;
	}
	else if (isset($pluginSettings['UseDefaultPlaylist']) && ($pluginSettings['UseDefaultPlaylist'] == 0))
	{
		$result = Array();
		$result['status'] = "Nothing in queue and default playlist disabled";
	}
	else
	{
		// Nothing in the queue, so fall back to local CML-DefaultPlaylist playlist
		$result = GetNextEntryInDefaultPlaylist();
	}

	$nextJSON = json_encode($result, JSON_PRETTY_PRINT);

	$f = fopen($nextFile, 'w');
	fwrite($f, $nextJSON);
	fclose($f);

	return $result;
}

function GetNextEntryInDefaultPlaylist() {
	if (isset($_SESSION['defaultPlaylistPosition']))
		$_SESSION['defaultPlaylistPosition'] += 1;
	else
		$_SESSION['defaultPlaylistPosition'] = 0;

	$playlist = loadPlaylist('CML-DefaultPlaylist');

	$result = Array();
	$entries = Array();
	$entry = Array();

	if ($_SESSION['defaultPlaylistPosition'] >= sizeof($playlist['mainPlaylist']))
		$_SESSION['defaultPlaylistPosition'] = 0;

	// If no entries in the default playlist, pause for 5 minutes so we don't hammer the site
	if (sizeof($playlist['mainPlaylist']))
	{
		$entry = $playlist['mainPlaylist'][$_SESSION['defaultPlaylistPosition']];
	}
	else
	{
		$entry['type'] = 'pause';
		$entry['enabled'] = 1;
		$entry['duration'] = 300;
		$entry['note'] = 'Pause since no mainPlaylist entries in CML-DefaultPlaylist';
	}

	array_push($entries, $entry);
	$result['playlistEntries'] = $entries;
	
	return $result;
}

/////////////////////////////////////////////////////////////////////////////
// prepNextItem() called by Playlist before loading the JSON to give the
//                plugin time to prep the JSON.  Nothing to return.
function prepNextItem() {
	$result = prepHelper();

	returnJSON($result);
}

// loadNextItem() called by Playlist to load the JSON for the item(s) to play
function loadNextItem() {
	global $settings;
	global $pluginSettings;

	# Update the Request Code
	if (isset($pluginSettings['PixelOverlayRequestCode']) && ($pluginSettings['PixelOverlayRequestCode'] == 1))
	{
		$path = dirname(__FILE__);
		system("/opt/fpp/scripts/eventScript $path/displayRequestCode.php");
	}

	$nextFile = $settings['mediaDirectory'] . '/plugins/' . $pluginSettings['plugin'] . '/tmp/next.json';

	if (!file_exists($nextFile))
		prepHelper();

	if (file_exists($nextFile))
	{
		$json = file_get_contents($settings['mediaDirectory'] . '/plugins/' . $pluginSettings['plugin'] . '/tmp/next.json');

		$data = json_decode($json, true);

		if (isset($data['playlistEntries']))
			returnText($json);

		if (isset($pluginSettings['UseDefaultPlaylist']) && ($pluginSettings['UseDefaultPlaylist'] == 0))
			returnText($json);
	}

	// Nothing in the queue, so fall back to local CML-DefaultPlaylist playlist
	$result = GetNextEntryInDefaultPlaylist();

	returnJSON($result);
}

// startedNextItem() called by playlist after starting the playlist item.
//                   Useful for marking an item as played.  Nothing to return.
function startedNextItem() {
	global $settings;
	global $pluginSettings;
	global $siteBase;

	$reqFile = $settings['mediaDirectory'] . '/plugins/' . $pluginSettings['plugin'] . '/tmp/request.json';
	$nextFile = $settings['mediaDirectory'] . '/plugins/' . $pluginSettings['plugin'] . '/tmp/next.json';

	if (file_exists($reqFile))
	{
		$json = file_get_contents($reqFile);
		$data = json_decode($json, true);

		$url = $siteBase . '/api/site/' . $pluginSettings['SiteCode'] . '/queue/' . $data['RequestId'];

		// Status 2 is 'started' so go on to next item
		$vars = 'SiteKey=' . $pluginSettings['SiteKey'] . '&Status=2';

		$ch = curl_init($url);
		curl_setopt( $ch, CURLOPT_POST, 1);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $vars);
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt( $ch, CURLOPT_HEADER, 0);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);

		$resp = curl_exec($ch);

		unlink($reqFile);
	}

	if (file_exists($nextFile))
	{
		unlink($nextFile);
	}

	# Update the Request Code in case it changed
	if (isset($pluginSettings['PixelOverlayRequestCode']) && ($pluginSettings['PixelOverlayRequestCode'] == 1))
	{
		$path = dirname(__FILE__);
		system("/opt/fpp/scripts/eventScript $path/displayRequestCode.php");
	}

	returnText("OK");
}

/////////////////////////////////////////////////////////////////////////////
function loadPlaylist($playlistName) {
	global $settings;

	$json = file_get_contents($settings['playlistDirectory'] . '/' . $playlistName . '.json');
	$data = json_decode($json, true);

	return $data;
}

/////////////////////////////////////////////////////////////////////////////
function currentStatus() {
	global $settings;
	global $pluginSettings;
	global $siteBase;

	// NOTE: This doesn't work yet, the endpoint doesn't exist.

	// Publish the current status here
	$url = $siteBase . '/api/site/' . $pluginSettings['SiteCode'] . '/status';

	$vars = 'SiteKey=' . $pluginSettings['SiteKey'];

	$ch = curl_init($url);
	curl_setopt( $ch, CURLOPT_POST, 1);
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $vars);
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt( $ch, CURLOPT_HEADER, 0);
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);

	$resp = curl_exec($ch);

	// FIXME, more good stuff here
}
/////////////////////////////////////////////////////////////////////////////
function setControlEnabled($enabled) {
	global $settings;
	global $pluginSettings;
	global $siteBase;

	$url = $siteBase . '/api/site/' . $pluginSettings['SiteCode'] . '/enable';

	$vars = 'SiteKey=' . $pluginSettings['SiteKey'] . "&Enabled=$enabled";

	$ch = curl_init($url);
	curl_setopt( $ch, CURLOPT_POST, 1);
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $vars);
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt( $ch, CURLOPT_HEADER, 0);
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);

	$resp = curl_exec($ch);
}

function enableControl() {
	setControlEnabled(1);
}

function disableControl() {
	setControlEnabled(0);
}

/////////////////////////////////////////////////////////////////////////////
function clearQueue() {
	global $settings;
	global $pluginSettings;
	global $siteBase;

	$url = $siteBase . '/api/site/' . $pluginSettings['SiteCode'] . '/queue';

	$vars = 'SiteKey=' . $pluginSettings['SiteKey'];

	$ch = curl_init($url);
	curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "DELETE");
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $vars);
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt( $ch, CURLOPT_HEADER, 0);
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);

	$resp = curl_exec($ch);
}

/////////////////////////////////////////////////////////////////////////////

?>
