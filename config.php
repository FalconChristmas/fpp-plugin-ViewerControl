<?
require_once("pluginconfig.php");

function CheckForDefaultPlaylist() {
	global $settings;

	$playlistFile = $settings['playlistDirectory'] . '/CML-DefaultPlaylist.json';

	if (!file_exists($playlistFile))
	{
		$f = fopen($playlistFile, "w") or exit("Unable to open file! " . $playlistFile);
		fprintf($f, '{
	"name": "CML-DefaultPlaylist",
	"repeat": 0,
	"loopCount": 0,
	"mainPlaylist": [
	]
}');
		fclose($f);
	}
}

CheckForDefaultPlaylist();
?>

<script type="text/javascript" src="js/fpp.js"></script>
<style>
td {
	vertical-align: top;
}
</style>
<div id="global" class="settings">

<script>
var apiServer = 'ControlMyLights.com';
var apiBase = 'http://' + apiServer + '/api';
var wsIsOpen = 0;
var ws;
var blockList = {};
var blockData = [];
var blockName = "Matrix1";
var dataIsPending = 0;
var pendingData;
var RequestCodeModel = '<? if (isset($pluginSettings['RequestCodeModel'])) echo $pluginSettings['RequestCodeModel']; else echo ''; ?>';
var RequestCodeFont = '<? if (isset($pluginSettings['RequestCodeFont'])) echo $pluginSettings['RequestCodeFont']; else echo 'fixed'?>';

$(document).ready(function(){
	$tabs = $("#tabs").tabs({
		activate: function(e, ui) {
			currentTabTitle = $(ui.newTab).text();
		},
		cache: true,
		spinner: "",
		fx: {
			opacity: 'toggle',
			height: 'toggle'
		}
	});

	var total = $tabs.find('.ui-tabs-nav li').length;
	var currentLoadingTab = 1;
	$tabs.bind('tabsload',function(){
		currentLoadingTab++;
		if (currentLoadingTab < total)
			$tabs.tabs('load',currentLoadingTab);
		else
			$tabs.unbind('tabsload');
	}).tabs('load',currentLoadingTab);

	if (($('#SiteCode').val() != '') && ($('#SiteKey').val() != ''))
	{
		ReLoadSiteInfo();
		LoadQueue();
		SyncPlaylists();
	}

	GetBlockList();
	$('#txtPlaylistName').prop('disabled', true);
	PlaylistTypeChanged();
	PopulatePlaylists("playList", 'CML-', '');
});

function SetSiteInfo(data) {
	if (data.SiteName == 'ERROR')
	{
		$('#SiteName').html('No site configured or invalid SiteCode/SiteKey.');
		return;
	}

	$('#SiteName').html(data.SiteName);

	var siteURL = data.VanityName + '.' + data.DomainName;
	$('#SiteURL').html("<a href='http://" + siteURL + "' target='_blank'>http://" + siteURL + "</a>");

	for (var prop in data)
	{
		if (data.hasOwnProperty(prop))
		{
			$('#' + prop).html(data[prop]);
		}
	}
}

function ClearSiteInfo() {
	$('#SiteName').html('');
	$('#SiteURL').html('');
}

function ReLoadSiteInfo() {
	var siteCode = $('#SiteCode').val();
	var siteKey = $('#SiteKey').val();

	$('.siteInfo').html('');

	var url = apiBase + '/site/' + siteCode + '/key/' + siteKey;
	$.getJSON(url, function(data) {
		SetSiteInfo(data);
	}).fail(function() {
		ClearSiteInfo();
	});
}

function PlaylistSaveCallback() {
	SavePlaylist('^CML-', '');
}

function AddNewCMLPlaylist() {
	var name=document.getElementById("txtNewPlaylistName");
	var plName = "CML-" + name.value.replace(/ /,'_');

	var xmlhttp=new XMLHttpRequest();
	var url = "fppxml.php?command=addPlayList&pl=" + plName;
	xmlhttp.open("GET",url,false);
	xmlhttp.setRequestHeader('Content-Type', 'text/xml');

	xmlhttp.onreadystatechange = function () {
		if (xmlhttp.readyState == 4 && xmlhttp.status==200) 
		{
			var xmlDoc=xmlhttp.responseXML; 
			var productList = xmlDoc.getElementsByTagName('Music')[0];
			PopulatePlaylists('playList', '^CML-', '');
			PopulatePlayListEntries(plName,true);
			$('#txtName').val(plName);
			$('#txtName').focus();
			$('#txtName').select();
		}
	};
	
	xmlhttp.send();

}

function ClearQueue()
{
	var siteCode = $('#SiteCode').val();
	var siteKey = $('#SiteKey').val();

	var url = apiBase + '/site/' + siteCode + '/queue';
	var data = '_method=DELETE' + '&SiteKey=' + siteKey;

	$.ajax({
		type: "POST",
		url: url,
		data: data,
		success: function(data) {
			$.jGrowl('Queue cleared.');
			LoadQueue();
		},
		fail: function() {
			alert('Error, failed to clear queue.');
		}
	});
}

function NothingQueued()
{
	$('#queue').html('<tr><td colspan=4>Nothing Currently Queued</td></tr>');
}

function humanDuration(length) {
	var date = new Date(length * 1000);
	var hh = date.getUTCHours();
	var mm = date.getUTCMinutes();
	var ss = date.getSeconds();

	if (hh < 10) {hh = "0"+hh;}
	if (mm < 10) {mm = "0"+mm;}
	if (ss < 10) {ss = "0"+ss;}

	var result = "";

	if (hh > 0)
		result = hh + ":" + mm + ":" + ss;
	else
		result = mm + ":" + ss;

	return result;
}

function PopulateQueue(data)
{
	if (data.length == 0)
	{
		NothingQueued();
		return;
	}

	$('#queue').html('');

	for (var i = 0; i < data.length; i++)
	{
		var row = "<tr><td><input type='hidden' class='requestId' value='" + data[i].RequestId + "'>" +
			data[i].Artist + " - " + data[i].Title + "</td><td>" + humanDuration(data[i].Length) + "</td>" +
			"<td><input type='button' value='Move to Head' onClick='MoveItem(this, \"head\");'> " +
				"<input type='button' value='Move to End' onClick='MoveItem(this, \"tail\");'> " +
				"<input type='button' value='Remove' onClick='DeQueueItem(this);'>" +
				"</td>" +
			"</tr>";
		$('#queue').append(row);
	}
}

function MoveItem(item, position)
{
	var siteCode = $('#SiteCode').val();
	var siteKey = $('#SiteKey').val();
	var requestId = $(item).parent().parent().find('.requestId').val();

	var url = apiBase + '/site/' + siteCode + '/queue/' + requestId;
	var data = 'SiteKey=' + siteKey + '&Action=';

	if (position == 'head')
		data += 'MoveToHead';
	else if (position == 'tail')
		data += 'MoveToTail';
	else
		return;

	$.ajax({
		type: "POST",
		url: url,
		data: data,
		success: function(data) {
			LoadQueue();
		},
		fail: function() {
			alert('Error, failed to RequestId ' + requestId + ' from queue.');
		}
	});
}		

function DeQueueItem(item)
{
	var siteCode = $('#SiteCode').val();
	var siteKey = $('#SiteKey').val();
	var requestId = $(item).parent().parent().find('.requestId').val();

	var data = 'SiteKey=' + siteKey + '&Status=9';
	var url = apiBase + '/site/' + siteCode + '/queue/' + requestId;

	$.ajax({
		type: "POST",
		url: url,
		data: data,
		success: function(data) {
			LoadQueue();
		},
		fail: function() {
			alert('Error, failed to RequestId ' + requestId + ' from queue.');
		}
	});
}		

function LoadQueue()
{
	var siteCode = $('#SiteCode').val();
	var siteKey = $('#SiteKey').val();

	var url = apiBase + '/site/' + siteCode + '/queue';
	$.getJSON(url, function(data) {
		PopulateQueue(data);
	}).fail(function() {
		NothingQueued();
	});
}

function SendWSCommand(data)
{
	if (!wsIsOpen)
	{
		dataIsPending = 1;
		pendingData = data;

		ws = new WebSocket("ws://<? echo $_SERVER['HTTP_HOST']; ?>:32321/echo");
		ws.onopen = function()
		{
			wsIsOpen = 1;
			if (dataIsPending)
			{
				dataIsPending = 0;
				ws.send(JSON.stringify(pendingData));
			}
		}
		ws.onmessage = function(evt)
		{
			var data = JSON.parse(evt.data);
			if (data.Command == "GetBlockList") {
				blockList = JSON.parse(evt.data).Result;
				ProcessBlockListResponse();
			} else if (data.Command == "GetFontList") {
				ProcessFontListResponse(JSON.parse(evt.data).Result);
			}
		},
     	ws.onclose = function()
		{ 
		 	wsIsOpen = 0;
		};
	} else {
		ws.send(JSON.stringify(data));
	}
}


function GetFontList() {
	SendWSCommand( { Command: "GetFontList" } );
}

function ProcessFontListResponse(list) {
	$('#RequestCodeFont option').remove();
	$('#RequestCodeFont').append("<option value=''> -- Pick A Font -- </option>");

	for (var i = 0; i < list.length; i++) {
		var key = list[i];
		var text = key.replace(/[^-a-zA-Z0-9]/g, '');
		if (key == text)
		{
			var option = "<option value='" + key + "'";

			if (key == RequestCodeFont)
				option += " selected";

			option += ">" + text + "</option>";

			$('#RequestCodeFont').append(option);
		}
	}
}

function GetBlockList() {
	SendWSCommand( { Command: "GetBlockList" } );
}

function ProcessBlockListResponse() {
	GetFontList();

	$('#RequestCodeModel option').remove();
	$('#RequestCodeModel').append("<option value=''> -- Pick A Model -- </option>");
	blockName = "";
	var sortedNames = Object.keys(blockList);
	sortedNames.sort();
	for (var i = 0; i < sortedNames.length; i++) {
		var key = sortedNames[i];
		if (blockName == "")
			blockName = key;
		if (blockList[key].orientation == 'V')
		{
			blockList[key].height = blockList[key].channelCount / blockList[key].strandsPerString / blockList[key].stringCount / 3;
			blockList[key].width = blockList[key].channelCount / 3 / blockList[key].height;
		}
		else
		{
			blockList[key].width = blockList[key].channelCount / blockList[key].strandsPerString / blockList[key].stringCount / 3;
			blockList[key].height = blockList[key].channelCount / 3 / blockList[key].width;
		}

		var option = "<option value='" + key + "'";
		if (key == RequestCodeModel)
			option += " selected";
		option += ">" + key + " (" + blockList[key].width + "x" + blockList[key].height + ")</option>";

		$('#RequestCodeModel').append(option);
	}

	selectBlock(blockName);
}

function PlaylistExists(name)
{
	for (var i = 0; i < currentPlaylists.length; i++) {
		if (currentPlaylists[i] == name)
		{
			return 1;
		}
	}

	return 0;
}

function AddItemToDefault(entry)
{
	var postData = JSON.stringify(entry);

	var url = '/api/playlist/CML-DefaultPlaylist/mainPlaylist/item';

	$.ajax({
		type: "POST",
		url: url,
		data: postData,
		contentType: 'application/json',
		success: function(data) {
			if (data.Status == 'OK')
			{
				$.jGrowl('Entry added.');
				PopulatePlayListEntries('CML-DefaultPlaylist', true);
			}
			else
				alert('Add failed.');
		},
		fail: function() {
			alert('Error, failed to update playlist.');
		}
	});
}

function EditPlaylist(item)
{
	var $row = $(item).parent().parent();
	var name = $row.find('.playlistName').html();

	PopulatePlayListEntries(name, true);
}

var createButton = "<input type='button' class='buttons actionCreate' value='Create' onClick='CreatePlaylist(this)';> ";
var editButton = "<input type='button' class='buttons actionEdit' value='Edit' onClick='EditPlaylist(this)';> ";

function PlaylistCreated(item, addToDefault, playlistItem)
{
	var $row = $(item).parent().parent().parent().parent().parent().parent();
	var name = $row.find('.playlistName').html();

	$row.find('.playlistActions').html('');
	$row.find('.playlistActions').append(editButton);

	if (addToDefault)
	{
		AddItemToDefault(playlistItem);
	}
	else
	{
		PopulatePlayListEntries(name, true);
	}
}

function CreatePlaylist(item)
{
	var $row = $(item).parent().parent().parent().parent().parent().parent();

	var name = $row.find('.playlistName').html();

	if (name == 'CML-DefaultPlaylist')
	{
		CreateDefaultPlaylist(item);
		return;
	}

	var seqFile = $row.find('.sequenceSelect').val();
	var mediaFile = $row.find('.mediaSelect').val();
	var addToDefault = $row.find('.addCheck').is(':checked');

	var playlist = {};
	var mainPlaylist = [];
	var playlistItem = {};

	if ((seqFile == '') && (mediaFile == ''))
	{
		playlistItem.type = 'pause';
		playlistItem.enabled = 1;
		playlistItem.duration = 1;
	}
	else if (seqFile == '')
	{
		playlistItem.type = 'media';
		playlistItem.enabled = 1;
		playlistItem.mediaName = mediaFile;
	}
	else if (mediaFile == '')
	{
		playlistItem.type = 'sequence';
		playlistItem.enabled = 1;
		playlistItem.sequenceName = seqFile;
	}
	else
	{
		playlistItem.type = 'both';
		playlistItem.enabled = 1;
		playlistItem.mediaName = mediaFile;
		playlistItem.sequenceName = seqFile;
	}
		
	mainPlaylist[0] = playlistItem;
	playlist.name = name;
	playlist.mainPlaylist = mainPlaylist;

	var postData = JSON.stringify(playlist);

	var url = '/api/playlist/' + name;

	$.ajax({
		type: "POST",
		url: url,
		data: postData,
		contentType: 'application/json',
		success: function(data) {
			PlaylistCreated(item, addToDefault, playlistItem);
		},
		fail: function() {
			alert('Error, failed to create playlist.');
		}
	});
}

var sequenceSelect = "<select class='sequenceSelect'><option value=''>-- Select a Sequence to play --</option><?
$sequenceEntries = scandir($sequenceDirectory);
sort($sequenceEntries);
foreach($sequenceEntries as $sequenceFile)
{
	if($sequenceFile != '.' && $sequenceFile != '..' && !preg_match('/^\./', $sequenceFile))
	{
		echo "<option value='" . $sequenceFile . "'>" . $sequenceFile . "</option>";
	}
}

?></select>";

var mediaSelect = "<select class='mediaSelect'><option value=''>-- Select a Media file to play --</option><?
$mediaEntries = array_merge(scandir($musicDirectory),scandir($videoDirectory));
sort($mediaEntries);
foreach($mediaEntries as $mediaFile)
{
	if($mediaFile != '.' && $mediaFile != '..' && !preg_match('/^\./', $mediaFile))
	{
		echo "<option value='" . $mediaFile . "'>" . $mediaFile . "</option>";
	}
}

?></select>";

function SyncPlaylistsFromData(songs)
{
	var html = '';
	var defaultPlaylist = {};
	defaultPlaylist.PlayerData = 'CML-DefaultPlaylist';
	songs.unshift(defaultPlaylist);

	$('#playList').hide();
	$('#sitePlaylists').show();

	for (var i = 0; i < songs.length; i++) {
		var exists = PlaylistExists(songs[i].PlayerData);

		html += '<tr>';

		html += '<td class="playlistName">' + songs[i].PlayerData + '</td>';

		if (exists)
		{
			html += "<td class='playlistActions'>" + editButton + '</td>';
		}
		else
		{
			html += '<td class="playlistActions"><table border=0 cellpadding=0 cellspacing=0>'
			html += '<tr><td>' + createButton + '</td><td><table border=0 cellpadding=2><tr><td>Sequence: </td><td>' + sequenceSelect + '</td></tr>';
			html += '<tr><td>Media:</td><td>' + mediaSelect + '</td></tr>';
			html += '<tr><td colspan=2>Add to Default Playlist: <input type="checkbox" class="addCheck"></td></tr>';
			html += '</table></td></tr></table></td>';
		}
		html += '</tr>';
	}

	$('#sitePlaylists tbody').html(html);
}

function SyncPlaylists()
{
	var siteCode = $('#SiteCode').val();
	var url = apiBase + '/site/' + siteCode + '/song';
	$.getJSON(url, function(data) {
		SyncPlaylistsFromData(data);
	});
}

</script>

<div>
	<div class='title'>Viewer Control via <a href='http://ControlMyLights.com' target='_blank'>http://ControlMyLights.com</a>, a.k.a. ElfControl.com</div>
	<div id='tabs'>
		<ul>
			<li><a href='#tab-site-config'>Site Config</a></li>
			<li><a href='#tab-queue'>Queue</a></li>
			<li><a href='#tab-playlists'>Playlists</a></li>
			<li><a href='#tab-request'>Request Code</a></li>
		</ul>

		<div id='tab-site-config'>
			<table>
				<tr><td>Admin Site:</td><td><a href='http://ControlMyLights.com/admin/' target='_blank'>http://ControlMyLights.com/admin/</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;You must <a href='http://ControlMyLights.com/admin/signup.php' target='_blank'>sign up</a> first to create a beta testing account.</td></tr>
				<tr><td>Site Code:</td><td><? PrintSettingTextSaved("SiteCode", 0, 0, 16, 16, 'fpp-plugin-ViewerControl', '', 'ReLoadSiteInfo'); ?></td></tr>
				<tr><td>Site Key:</td><td><? PrintSettingTextSaved("SiteKey", 0, 0, 16, 16, 'fpp-plugin-ViewerControl', '', 'ReLoadSiteInfo'); ?></td></tr>
				<tr><td>Site Name:</td><td><span id='SiteName' class='siteInfo'></span></td></tr>
				<tr><td>Vanity Name:</td><td><span id='VanityName' class='siteInfo'></span></td></tr>
				<tr><td>Public URL:</td><td><span id='SiteURL' class='siteInfo'></span></td></tr>
				<tr><td>Address:</td><td><span id='DisplayAddress' class='siteInfo'></span></td></tr>
				<tr><td>Subscription Plan:</td><td><span id='SubscriptionPlan' class='siteInfo'></span></td></tr>
				<tr><td>Expiration Date:</td><td><span id='ExpirationDate' class='siteInfo'></span></td></tr>
				<tr><td>Playlist Position:</td><td><span id='PlaylistPosition' class='siteInfo'></span></td></tr>
				<tr><td>Next Local Item:</td><td><span id='NextLocalItem' class='siteInfo'></span></td></tr>
				<tr><td>Last Local Item:</td><td><span id='LastLocalItem' class='siteInfo'></span></td></tr>
				<tr><td>Time Zone:</td><td><span id='TimeZone' class='siteInfo'></span></td></tr>
			</table>
		</div>
		<div id='tab-queue'>
			<table border=1 cellpadding=2 cellspacing=2>
				<thead>
					<tr><th colspan=3>Current Request Queue</th></tr>
					<tr><th>Artist - Title</th><th>Length</th><th>Options</th></tr>
				</thead>
				<tbody id='queue'>
					<tr><td colspan=3>Nothing Currently Queued</td></tr>
				</tbody>
			</table>
			<input type='button' value='Refresh List' onClick='LoadQueue();'>
			&nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
			<input type='button' value='Clear Queue' onClick='ClearQueue();'>
		</div>

		<div id='tab-playlists'>
			<fieldset style="padding: 10px; border: 2px solid #000;">
				<legend>Playlists</legend>
				<span id = "playList"> </span>
				<span>
					<input type='button' class='buttons' value='Sync Playlists' onClick='SyncPlaylists();'>
					<br>
					<br>
					<table id='sitePlaylists' border=1 cellspacing=1 style='display: none;'>
						<thead><th>Playlist Name</th><th>Actions</th></thead>
						<tbody>
						</tbody>
					</table>
				</span>
			</fieldset>
			<br>
<?
$allowDelete = 0;
$simplifiedPlaylist = 1;
$saveCallback = 'PlaylistSaveCallback();';
include_once('playlistEditor.php');
?>
		</div>

		<div id='tab-request'>
			<table width='100%'>
				<tr><td>
					<table>
						<tr><td>Current Request Code:</td><td><span id='CurrentRequestCode' class='siteInfo'></span></td></tr>
						<tr><td>Request Code Duration:</td><td><span id='RequestCodeDuration' class='siteInfo'></span></td></tr>
						<tr><td>Guest Code:</td><td><span id='GuestRequestCode' class='siteInfo'></span></td></tr>
						<tr><td>Admin Code:</td><td><span id='AdminRequestCode' class='siteInfo'></span></td></tr>
					</table>
				</td><td width='50px'>&nbsp;</td><td>
					<table>
						<tr><td colspan=2>Use Pixel Overlay Model to display Current Request Code: <? PrintSettingCheckbox('', 'PixelOverlayRequestCode', 1, 0, 1, 0, 'fpp-plugin-ViewerControl', ''); ?></td></tr>
						<tr><td>Remote Model Host IP:</td>
							<td>
<?
PrintSettingTextSaved('RequestCodeModelHost', 0, 0, 32, 32, 'fpp-plugin-ViewerControl');
?>
							</td></tr>
						<tr><td>Model:</td><td>
<? PrintSettingSelect('Request Code Model', 'RequestCodeModel', 1, 0, '', Array(), 'fpp-plugin-ViewerControl', '', ''); ?>
							</td></tr>
						<tr><td>Font:</td><td>
<? PrintSettingSelect('Request Code Font', 'RequestCodeFont', 1, 0, '', Array(), 'fpp-plugin-ViewerControl', '', ''); ?>
							</td></tr>
						<tr><td>Font Size:</td><td>
<?
$fontSizes = Array(
	"-- Pick a Font Size --" => 0,
	"10" => 10,
	"12" => 12,
	"14" => 14,
	"16" => 16,
	"18" => 18,
	"20" => 20,
	"22" => 22,
	"24" => 24,
	"26" => 26,
	"28" => 28,
	"30" => 30,
	"32" => 32,
	"34" => 34,
	"36" => 36,
	"38" => 38,
	"40" => 40,
	"42" => 42,
	"44" => 44,
	"46" => 46,
	"48" => 48,
	"50" => 50,
	"52" => 52,
	);

PrintSettingSelect('Request Code Font Size', 'RequestCodeFontSize', 1, 0, 10, $fontSizes, 'fpp-plugin-ViewerControl', '', '');
?>
							</td></tr>
						<tr><td>Color:</td><td>
<?
$fontColors = Array(
	'-- Pick a Text Color --' => '',
	'Red' => '#FF0000',
	'Green' => '#00FF00',
	'Blue' => '#0000FF',
	'Yellow' => '#FFFF00',
	'Purple' => '#FF00FF',
	'Cyan' => '#00FFFF'
	);
PrintSettingSelect('Request Code Color', 'RequestCodeFontColor', 1, 0, '#FF0000', $fontColors, 'fpp-plugin-ViewerControl', '', '');
?>
							</td></tr>
					</table>
				</td></tr>
			</table>
		</div>
	</div>

<div id='sitePreview'>
</div>
</div>
<br>
