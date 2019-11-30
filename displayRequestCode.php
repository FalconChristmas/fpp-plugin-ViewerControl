<?
set_include_path("/opt/fpp/www" . PATH_SEPARATOR . get_include_path());
$skipJSsettings = 1;
require_once('config.php');

$_GET['plugin'] = 'fpp-plugin-ViewerControl';
require_once('pluginconfig.php');

if (!isset($pluginSettings['RequestCodeModel']))
{
	echo "No model configured!\n";
	exit(0);
}

$url = sprintf("http://www.controlmylights.com/api/site/%s/key/%s",
	$pluginSettings['SiteCode'], $pluginSettings['SiteKey']);
$json = file_get_contents($url);
$data = json_decode($json, true);
$requestCode = $data['CurrentRequestCode'];

$model = $pluginSettings['RequestCodeModel'];

$modelHost = 'localhost';
if (isset($pluginSettings['RequestCodeModelHost']))
{
	$modelHost = $pluginSettings['RequestCodeModelHost'];
	if ($modelHost == '')
		$modelHost = 'localhost';
}

$font = 'fixed';
if (isset($pluginSettings['RequestCodeFont']))
	$font = $pluginSettings['RequestCodeFont'];

$fontSize = 10;
if (isset($pluginSettings['RequestCodeFontSize']))
	$fontSize = intval($pluginSettings['RequestCodeFontSize']);

$fontColor = 'red';
if (isset($pluginSettings['RequestCodeFontColor']))
	$fontColor = $pluginSettings['RequestCodeFontColor'];

// PUT /api/overlays/model/Matrix-Right/text
// {
//     "Message": "Hello",
//     "Position": "L2R",
//     "Font": "Helvetica",
//     "FontSize": 12,
//     "AntiAlias": false,
//     "PixelsPerSecond": 5,
//     "Color": "#FF000",
//     "AutoEnable": false
// }
//
// curl -i -X PUT -H "Content-Type: application/json" -d '{ "Message": "888", "Position": "Center", "Font": "Courier-Bold", "FontSize": 12, "AntiAlias": 0, "PixelsPerSecond": 0, "Color": "#FF000", "AutoEnable": 0 }' http://localhost:32322/overlays/model/Matrix-Right/text

$arr = array();
$arr['Message'] = $requestCode;
$arr['Position'] = 'Center';
$arr['Font'] = $font;
$arr['FontSize'] = $fontSize;
$arr['AntiAlias'] = 0;
$arr['PixelsPerSecond'] = 0;
$arr['Color'] = $fontColor;
$arr['AutoEnable'] = 0;

$url = 'http://' . $modelHost . ':32322/overlays/model/' . $model . '/text';

//printf( "URL: %s\nData:\n%s\n", $url, json_encode($arr));

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($arr));
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

curl_close($ch);

?>
