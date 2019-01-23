<?
set_include_path("/opt/fpp/www" . PATH_SEPARATOR . get_include_path());
$skipJSsettings = 1;
require_once('config.php');

$_GET['plugin'] = 'ViewerControl';
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

$cmd = "/opt/fpp/src/fppmm -m $model | grep Layout | cut -f2 -d: | sed -e 's/[^0-9x]//g'";
$layout = exec($cmd, $output, $return_val);
if ( $return_val != 0 )
{
	echo "Unable to determine model layout!\n";
	exit(0);
}
unset($output);

$font = 'fixed';
if (isset($pluginSettings['RequestCodeFont']))
	$font = $pluginSettings['RequestCodeFont'];

$fontSize = '10';
if (isset($pluginSettings['RequestCodeFontSize']))
	$fontSize = $pluginSettings['RequestCodeFontSize'];

$fontColor = 'red';
if (isset($pluginSettings['RequestCodeFontColor']))
	$fontColor = $pluginSettings['RequestCodeFontColor'];

$cmd = sprintf("convert -size %s -depth 8 xc:black -font %s -pointsize %d -fill %s -gravity center -draw \"text 0,0 '%03d'\" /tmp/cml.rgb > /dev/null 2> /dev/null",
	$layout, $font, $fontSize, $fontColor, $requestCode);

system($cmd);

$cmd = sprintf("fppmm -m %s -f /tmp/cml.rgb > /dev/null 2> /dev/null", $model);

system($cmd);

?>
