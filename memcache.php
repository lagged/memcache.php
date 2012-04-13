<?php
/*
  +----------------------------------------------------------------------+
  | PHP Version 5                                                        |
  +----------------------------------------------------------------------+
  | Copyright (c) 1997-2004 The PHP Group                                |
  +----------------------------------------------------------------------+
  | This source file is subject to version 3.0 of the PHP license,       |
  | that is bundled with this package in the file LICENSE, and is        |
  | available through the world-wide-web at the following url:           |
  | http://www.php.net/license/3_0.txt.                                  |
  | If you did not receive a copy of the PHP license and are unable to   |
  | obtain it through the world-wide-web, please send a note to          |
  | license@php.net so we can mail you a copy immediately.               |
  +----------------------------------------------------------------------+
  | Initial author:   Harun Yayli <harunyayli at gmail.com>              |
  | Modifications by: Artur Ejsmont http://artur.ejsmont.org             |
  +----------------------------------------------------------------------+
*/

$VERSION='$Id: modified memcache.php,v 1.1.2.3 2008/08/28 18:07:54 mikl Exp $';

define('ADMIN_USERNAME','admin'); 	// Admin Username
define('ADMIN_PASSWORD','pass');  	// Admin Password
define('DATE_FORMAT','Y/m/d H:i:s');
define('GRAPH_SIZE',200);
define('MAX_ITEM_DUMP',50);

$MEMCACHE_SERVERS[] = 'localhost:11211'; // add more as an array
//$MEMCACHE_SERVERS[] = 'mymemcache-server2:11211'; // add more as an array


////////// END OF DEFAULT CONFIG AREA /////////////////////////////////////////////////////////////

///////////////// Password protect ////////////////////////////////////////////////////////////////
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) ||
           $_SERVER['PHP_AUTH_USER'] != ADMIN_USERNAME ||$_SERVER['PHP_AUTH_PW'] != ADMIN_PASSWORD) {
			Header("WWW-Authenticate: Basic realm=\"Memcache Login\"");
			Header("HTTP/1.0 401 Unauthorized");

			echo <<<EOB
				<html><body>
				<h1>Rejected!</h1>
				<big>Wrong Username or Password!</big>
				</body></html>
EOB;
			exit;
}

///////////MEMCACHE FUNCTIONS /////////////////////////////////////////////////////////////////////

function sendMemcacheCommands($command){
    global $MEMCACHE_SERVERS;
	$result = array();

	foreach($MEMCACHE_SERVERS as $server){
		$strs = explode(':',$server);
		$host = $strs[0];
		$port = $strs[1];
		$result[$server] = sendMemcacheCommand($host,$port,$command);
	}
	return $result;
}
function sendMemcacheCommand($server,$port,$command){

	$s = @fsockopen($server,$port);
	if (!$s){
		die("Cant connect to:".$server.':'.$port);
	}

	fwrite($s, $command."\r\n");

	$buf='';
	while ((!feof($s))) {
		$buf .= fgets($s, 256);
		if (strpos($buf,"END\r\n")!==false){ // stat says end
		    break;
		}
		if (strpos($buf,"DELETED\r\n")!==false || strpos($buf,"NOT_FOUND\r\n")!==false){ // delete says these
		    break;
		}
		if (strpos($buf,"OK\r\n")!==false){ // flush_all says ok
		    break;
		}
		if (strpos($buf,"RESET\r\n")!==false){ // reset stats ok
		    break;
		}
		if (strpos($buf,"VERSION ")!==false){ // version answer
		    break;
		}
	}
    fclose($s);
    return parseMemcacheResults($buf);
}
function parseMemcacheResults($str){
	$res = array();
	$lines = explode("\r\n",$str);
	$cnt = count($lines);
	for($i=0; $i< $cnt; $i++){
	    $line = $lines[$i];
		$l = explode(' ',$line,3);
		if (count($l)==3){
			$res[$l[0]][$l[1]]=$l[2];
			if ($l[0]=='VALUE'){ // next line is the value
			    $res[$l[0]][$l[1]] = array();
			    list ($flag,$size)=explode(' ',$l[2]);
			    $res[$l[0]][$l[1]]['stat']=array('flag'=>$flag,'size'=>$size);
			    $res[$l[0]][$l[1]]['value']=$lines[++$i];
			}
		}elseif( $l[0] == 'VERSION' ){
		    return $l[1];
		}elseif($line=='DELETED' || $line=='NOT_FOUND' || $line=='OK' || $line=='RESET'){
		    return $line;
		}
	}
	return $res;
}

function dumpCacheSlab($server,$slabId,$limit){
    list($host,$port) = explode(':',$server);
    $resp = sendMemcacheCommand($host,$port,'stats cachedump '.$slabId.' '.$limit);
    return $resp;
}


function getAllSlabStats(){
    $entries = sendMemcacheCommands('stats slabs');
    $slabs = array();
    foreach($entries as $server => $stats){
        $slabs[$server] = array();
        if( isset($stats['STAT']) ){
            foreach($stats['STAT'] as $keyinfo => $value){
                if (preg_match('/(\d+?)\:(.+?)$/',$keyinfo,$matches)){
                    $slabs[$server][ $matches[1] ][ $matches[2] ] = $value;
                }
            }
        }
    }
    return $slabs;
}
function flushStats($server){
    list($host,$port) = explode(':',$server);
    $resp = sendMemcacheCommand($host,$port,'stats reset');
    return $resp;
}
function getMemcacheVersion(){
    $entries = sendMemcacheCommands('version');
    return $entries;
}

function flushServer($server){
    list($host,$port) = explode(':',$server);
    $resp = sendMemcacheCommand($host,$port,'flush_all');
    return $resp;
}
function getCacheItems(){
 $items = sendMemcacheCommands('stats items');
 $serverItems = array();
 $totalItems = array();
 foreach ($items as $server=>$itemlist){
    $serverItems[$server] = array();
    $totalItems[$server]=0;
    if (!isset($itemlist['STAT'])){
        continue;
    }

    $iteminfo = $itemlist['STAT'];

    foreach($iteminfo as $keyinfo=>$value){
        if (preg_match('/items\:(\d+?)\:(.+?)$/',$keyinfo,$matches)){
            $serverItems[$server][$matches[1]][$matches[2]] = $value;
            if ($matches[2]=='number'){
                $totalItems[$server] +=$value;
            }
        }
    }
 }
 return array('items'=>$serverItems,'counts'=>$totalItems);
}
function getMemcacheStats($total=true){
	$resp = sendMemcacheCommands('stats');
	if ($total){
		$res = array();
		foreach($resp as $server=>$r){
			foreach($r['STAT'] as $key=>$row){
				if (!isset($res[$key])){
					$res[$key]=null;
				}
				switch ($key){
					case 'pid':
						$res['pid'][$server]=$row;
						break;
					case 'uptime':
						$res['uptime'][$server]=$row;
						break;
					case 'time':
						$res['time'][$server]=$row;
						break;
					case 'version':
						$res['version'][$server]=$row;
						break;
					case 'pointer_size':
						$res['pointer_size'][$server]=$row;
						break;
					case 'rusage_user':
						$res['rusage_user'][$server]=$row;
						break;
					case 'rusage_system':
						$res['rusage_system'][$server]=$row;
						break;
					case 'curr_items':
						$res['curr_items']+=$row;
						break;
					case 'total_items':
						$res['total_items']+=$row;
						break;
					case 'bytes':
						$res['bytes']+=$row;
						break;
					case 'curr_connections':
						$res['curr_connections']+=$row;
						break;
					case 'total_connections':
						$res['total_connections']+=$row;
						break;
					case 'connection_structures':
						$res['connection_structures']+=$row;
						break;
					case 'cmd_get':
						$res['cmd_get']+=$row;
						break;
					case 'cmd_set':
						$res['cmd_set']+=$row;
						break;
					case 'get_hits':
						$res['get_hits']+=$row;
						break;
					case 'get_misses':
						$res['get_misses']+=$row;
						break;
					case 'evictions':
						$res['evictions']+=$row;
						break;
					case 'bytes_read':
						$res['bytes_read']+=$row;
						break;
					case 'bytes_written':
						$res['bytes_written']+=$row;
						break;
					case 'limit_maxbytes':
						$res['limit_maxbytes']+=$row;
						break;
					case 'threads':
						$res['rusage_system'][$server]=$row;
						break;
				}
			}
		}
		return $res;
	}
	return $resp;
}

//////////////////////////////////////////////////////

//
// don't cache this page
//
header("Cache-Control: no-store, no-cache, must-revalidate");  // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");                                    // HTTP/1.0

function duration($ts) {
    global $time;
    $years = (int)((($time - $ts)/(7*86400))/52.177457);
    $rem = (int)(($time-$ts)-($years * 52.177457 * 7 * 86400));
    $weeks = (int)(($rem)/(7*86400));
    $days = (int)(($rem)/86400) - $weeks*7;
    $hours = (int)(($rem)/3600) - $days*24 - $weeks*7*24;
    $mins = (int)(($rem)/60) - $hours*60 - $days*24*60 - $weeks*7*24*60;
    $str = '';
    if($years==1) $str .= "$years year, ";
    if($years>1) $str .= "$years years, ";
    if($weeks==1) $str .= "$weeks week, ";
    if($weeks>1) $str .= "$weeks weeks, ";
    if($days==1) $str .= "$days day,";
    if($days>1) $str .= "$days days,";
    if($hours == 1) $str .= " $hours hour and";
    if($hours>1) $str .= " $hours hours and";
    if($mins == 1) $str .= " 1 minute";
    else $str .= " $mins minutes";
    return $str;
}

// create graphics
//
function graphics_avail() {
	return extension_loaded('gd');
}

function bsize($s) {
	foreach (array('','K','M','G') as $i => $k) {
		if ($s < 1024) break;
		$s/=1024;
	}
	return sprintf("%5.1f %sBytes",$s,$k);
}

// create menu entry
function menu_entry($ob,$title) {
	global $PHP_SELF;
	if ($ob==$_GET['op']){
	    return "<li><a class=\"child_active\" href=\"$PHP_SELF&op=$ob\">$title</a></li>";
	}
	return "<li><a class=\"active\" href=\"$PHP_SELF&op=$ob\">$title</a></li>";
}

function getHeader(){
    $header = <<<EOB
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-us">
<head><title>MEMCACHE INFO</title>
<style type="text/css"><!--
body { background:white; font-size:100.01%; margin:0; padding:0; }
body,p,td,th,input,submit { font-size:0.8em;font-family:arial,helvetica,sans-serif; }
* html body   {font-size:0.8em}
* html p      {font-size:0.8em}
* html td     {font-size:0.8em}
* html th     {font-size:0.8em}
* html input  {font-size:0.8em}
* html submit {font-size:0.8em}
td { vertical-align:top }
a { color:black; font-weight:none; text-decoration:none; }
a:hover { text-decoration:underline; }
div.content { padding:1em 1em 1em 1em; position:absolute; width:97%; z-index:100; }

h1.memcache { background:rgb(153,153,204); margin:0; padding:0.5em 1em 0.5em 1em; }
div.memcache { background:rgb(153,153,204); margin:0; padding:0.5em 1em 0.5em 1em; }
div.memcache a { text-decoration:underline; }

* html h1.memcache { margin-bottom:-7px; }
h1.memcache a:hover { text-decoration:none; color:rgb(90,90,90); }
h1.memcache span.logo {
	background:rgb(119,123,180);
	color:black;
	border-right: solid black 1px;
	border-bottom: solid black 1px;
	font-style:italic;
	font-size:1em;
	padding-left:1.2em;
	padding-right:1.2em;
	text-align:right;
	display:block;
	width:130px;
	}
h1.memcache span.logo span.name { color:white; font-size:0.7em; padding:0 0.8em 0 2em; }
h1.memcache span.nameinfo { color:white; display:inline; font-size:0.4em; margin-left: 3em; }
h1.memcache div.copy { color:black; font-size:0.4em; position:absolute; right:1em; }
hr.memcache {
	background:white;
	border-bottom:solid rgb(102,102,153) 1px;
	border-style:none;
	border-top:solid rgb(102,102,153) 10px;
	height:12px;
	margin:0;
	margin-top:1px;
	padding:0;
}

ol,menu { margin:1em 0 0 0; padding:0.2em; margin-left:1em;}
ol.menu li { display:inline; margin-right:0.7em; list-style:none; font-size:85%}
ol.menu a {
	background:rgb(153,153,204);
	border:solid rgb(102,102,153) 2px;
	color:white;
	font-weight:bold;
	margin-right:0em;
	padding:0.1em 0.5em 0.1em 0.5em;
	text-decoration:none;
	margin-left: 5px;
	}
ol.menu a.child_active {
	background:rgb(153,153,204);
	border:solid rgb(102,102,153) 2px;
	color:white;
	font-weight:bold;
	margin-right:0em;
	padding:0.1em 0.5em 0.1em 0.5em;
	text-decoration:none;
	border-left: solid black 5px;
	margin-left: 0px;
	}
ol.menu span.active {
	background:rgb(153,153,204);
	border:solid rgb(102,102,153) 2px;
	color:black;
	font-weight:bold;
	margin-right:0em;
	padding:0.1em 0.5em 0.1em 0.5em;
	text-decoration:none;
	border-left: solid black 5px;
	}
ol.menu span.inactive {
	background:rgb(193,193,244);
	border:solid rgb(182,182,233) 2px;
	color:white;
	font-weight:bold;
	margin-right:0em;
	padding:0.1em 0.5em 0.1em 0.5em;
	text-decoration:none;
	margin-left: 5px;
	}
ol.menu a:hover {
	background:rgb(193,193,244);
	text-decoration:none;
	}


div.info {
	background:rgb(204,204,204);
	border:solid rgb(204,204,204) 1px;
	margin-bottom:1em;
	}
div.info h2 {
	background:rgb(204,204,204);
	color:black;
	font-size:1em;
	margin:0;
	padding:0.1em 1em 0.1em 1em;
	}
div.info table {
	border:solid rgb(204,204,204) 1px;
	border-spacing:0;
	width:100%;
	}
div.info table th {
	background:rgb(204,204,204);
	color:white;
	margin:0;
	padding:0.1em 1em 0.1em 1em;
	}
div.info table th a.sortable { color:black; }
div.info table tr.tr-0 { background:rgb(238,238,238); }
div.info table tr.tr-1 { background:rgb(221,221,221); }
div.info table td { padding:0.3em 1em 0.3em 1em; }
div.info table td.td-0 { border-right:solid rgb(102,102,153) 1px; white-space:nowrap; }
div.info table td.td-n { border-right:solid rgb(102,102,153) 1px; }
div.info table td h3 {
	color:black;
	font-size:1.1em;
	margin-left:-0.3em;
	}
.td-0 a , .td-n a, .tr-0 a , tr-1 a {
    text-decoration:underline;
}
div.graph { margin-bottom:1em }
div.graph h2 { background:rgb(204,204,204);; color:black; font-size:1em; margin:0; padding:0.1em 1em 0.1em 1em; }
div.graph table { border:solid rgb(204,204,204) 1px; color:black; font-weight:normal; width:100%; }
div.graph table td.td-0 { background:rgb(238,238,238); }
div.graph table td.td-1 { background:rgb(221,221,221); }
div.graph table td { padding:0.2em 1em 0.4em 1em; }

div.div1,div.div2 { margin-bottom:1em; width:35em; }
div.div3 { position:absolute; left:40em; top:1em; width:580px; }
//div.div3 { position:absolute; left:37em; top:1em; right:1em; }

div.sorting { margin:1.5em 0em 1.5em 2em }
.center { text-align:center }
.aright { position:absolute;right:1em }
.right { text-align:right }
.ok { color:rgb(0,200,0); font-weight:bold}
.failed { color:rgb(200,0,0); font-weight:bold}

span.box {
	border: black solid 1px;
	border-right:solid black 2px;
	border-bottom:solid black 2px;
	padding:0 0.5em 0 0.5em;
	margin-right:1em;
}
span.green { background:#60F060; padding:0 0.5em 0 0.5em}
span.red { background:#D06030; padding:0 0.5em 0 0.5em }

div.authneeded {
	background:rgb(238,238,238);
	border:solid rgb(204,204,204) 1px;
	color:rgb(200,0,0);
	font-size:1.2em;
	font-weight:bold;
	padding:2em;
	text-align:center;
	}

input {
	background:rgb(153,153,204);
	border:solid rgb(102,102,153) 2px;
	color:white;
	font-weight:bold;
	margin-right:1em;
	padding:0.1em 0.5em 0.1em 0.5em;
	}
//-->
</style>

<link rel="stylesheet" href="/js/style.css" type="text/css" />
<link rel="stylesheet" href="/js/vtip/css/vtip.css" type="text/css" />

<script type="text/javascript" src="/js/jquery.min.js"></script> 
<script type="text/javascript" src="/js/jquery.tablesorter.min.js"></script> 
<script type="text/javascript" src="/js/vtip/vtip-min.js"></script> 

<script type="text/javascript">
    function must_confirm(txt, link){
        if( confirm(txt) == true ){
            document.location.href = link;
            return true;
        }else{
            return false;
        }
    }
    $(document).ready(function(){
        $.tablesorter.defaults.widgets = ['zebra'];
        $('#slabStats').tablesorter( {sortList: [[0,0], [1,0]]} );
    });

console.debug(123);
</script>

</head>
<body>
<div class="head">
	<h1 class="memcache">Memcache stats v0.1</h1>
    <div class="memcache">
    <a href="http://artur.ejsmont.org">Visit website</a> &nbsp;
    <a href="http://livebookmark.net/journal/2008/05/21/memcachephp-stats-like-apcphp/">Visit oryginal memcache.php website.</a>
    </div>
	<hr class="memcache">
</div>
<div class=content>
EOB;

    return $header;
}
function getFooter(){
    global $VERSION;
    $footer = '</div><!-- Based on apc.php '.$VERSION.'--></body>
</html>
';

    return $footer;

}
function getMenu(){
    global $PHP_SELF;
echo "<ol class=menu>";
if ($_GET['op']!=4){
echo <<<EOB
    <li><a href="$PHP_SELF&op={$_GET['op']}">Refresh Data</a></li>
EOB;
}
else {
echo <<<EOB
    <li><a href="$PHP_SELF&op=2}">Back</a></li>
EOB;
}
echo
	menu_entry(1,'View Host Stats'),
	menu_entry(2,'Variables'),
	menu_entry(8,'Slabs');

echo <<<EOB
	</ol>
	<br/>
EOB;
}

// TODO, AUTH

$_GET['op'] = !isset($_GET['op'])? '1':$_GET['op'];
$PHP_SELF= isset($_SERVER['PHP_SELF']) ? htmlentities(strip_tags($_SERVER['PHP_SELF'],'')) : '';

$PHP_SELF=$PHP_SELF.'?';
$time = time();
// sanitize _GET

foreach($_GET as $key=>$g){
    $_GET[$key]=htmlentities($g);
}


// singleout
// when singleout is set, it only gives details for that server.
if (isset($_GET['singleout']) && $_GET['singleout']>=0 && $_GET['singleout'] <count($MEMCACHE_SERVERS)){
    $MEMCACHE_SERVERS = array($MEMCACHE_SERVERS[$_GET['singleout']]);
}

// display images
if (isset($_GET['IMG'])){
    $memcacheStats = getMemcacheStats();
    $memcacheStatsSingle = getMemcacheStats(false);

    if (!graphics_avail()) {
		exit(0);
	}

	function fill_box($im, $x, $y, $w, $h, $color1, $color2,$text='',$placeindex='') {
		global $col_black;
		$x1=$x+$w-1;
		$y1=$y+$h-1;

		imagerectangle($im, $x, $y1, $x1+1, $y+1, $col_black);
		if($y1>$y) imagefilledrectangle($im, $x, $y, $x1, $y1, $color2);
		else imagefilledrectangle($im, $x, $y1, $x1, $y, $color2);
		imagerectangle($im, $x, $y1, $x1, $y, $color1);
		if ($text) {
			if ($placeindex>0) {

				if ($placeindex<16)
				{
					$px=5;
					$py=$placeindex*12+6;
					imagefilledrectangle($im, $px+90, $py+3, $px+90-4, $py-3, $color2);
					imageline($im,$x,$y+$h/2,$px+90,$py,$color2);
					imagestring($im,2,$px,$py-6,$text,$color1);

				} else {
					if ($placeindex<31) {
						$px=$x+40*2;
						$py=($placeindex-15)*12+6;
					} else {
						$px=$x+40*2+100*intval(($placeindex-15)/15);
						$py=($placeindex%15)*12+6;
					}
					imagefilledrectangle($im, $px, $py+3, $px-4, $py-3, $color2);
					imageline($im,$x+$w,$y+$h/2,$px,$py,$color2);
					imagestring($im,2,$px+2,$py-6,$text,$color1);
				}
			} else {
				imagestring($im,4,$x+5,$y1-16,$text,$color1);
			}
		}
	}


    function fill_arc($im, $centerX, $centerY, $diameter, $start, $end, $color1,$color2,$text='',$placeindex=0) {
		$r=$diameter/2;
		$w=deg2rad((360+$start+($end-$start)/2)%360);


		if (function_exists("imagefilledarc")) {
			// exists only if GD 2.0.1 is avaliable
			imagefilledarc($im, $centerX+1, $centerY+1, $diameter, $diameter, $start, $end, $color1, IMG_ARC_PIE);
			imagefilledarc($im, $centerX, $centerY, $diameter, $diameter, $start, $end, $color2, IMG_ARC_PIE);
			imagefilledarc($im, $centerX, $centerY, $diameter, $diameter, $start, $end, $color1, IMG_ARC_NOFILL|IMG_ARC_EDGED);
		} else {
			imagearc($im, $centerX, $centerY, $diameter, $diameter, $start, $end, $color2);
			imageline($im, $centerX, $centerY, $centerX + cos(deg2rad($start)) * $r, $centerY + sin(deg2rad($start)) * $r, $color2);
			imageline($im, $centerX, $centerY, $centerX + cos(deg2rad($start+1)) * $r, $centerY + sin(deg2rad($start)) * $r, $color2);
			imageline($im, $centerX, $centerY, $centerX + cos(deg2rad($end-1))   * $r, $centerY + sin(deg2rad($end))   * $r, $color2);
			imageline($im, $centerX, $centerY, $centerX + cos(deg2rad($end))   * $r, $centerY + sin(deg2rad($end))   * $r, $color2);
			imagefill($im,$centerX + $r*cos($w)/2, $centerY + $r*sin($w)/2, $color2);
		}
		if ($text) {
			if ($placeindex>0) {
				imageline($im,$centerX + $r*cos($w)/2, $centerY + $r*sin($w)/2,$diameter, $placeindex*12,$color1);
				imagestring($im,4,$diameter, $placeindex*12,$text,$color1);

			} else {
				imagestring($im,4,$centerX + $r*cos($w)/2, $centerY + $r*sin($w)/2,$text,$color1);
			}
		}
	}
	$size = GRAPH_SIZE; // image size
	$image = imagecreate($size+50, $size+10);

	$col_white = imagecolorallocate($image, 0xFF, 0xFF, 0xFF);
	$col_red   = imagecolorallocate($image, 0xD0, 0x60,  0x30);
	$col_green = imagecolorallocate($image, 0x60, 0xF0, 0x60);
	$col_black = imagecolorallocate($image,   0,   0,   0);

	imagecolortransparent($image,$col_white);

    switch ($_GET['IMG']){
        case 1: // pie chart
            $tsize=$memcacheStats['limit_maxbytes'];
    		$avail=$tsize-$memcacheStats['bytes'];
    		$x=$y=$size/2;
    		$angle_from = 0;
    		$fuzz = 0.000001;

            foreach($memcacheStatsSingle as $serv=>$mcs) {
    			$free = $mcs['STAT']['limit_maxbytes']-$mcs['STAT']['bytes'];
    			$used = $mcs['STAT']['bytes'];


                if ($free>0){
    			// draw free
    			    $angle_to = ($free*360)/$tsize;
                    $perc =sprintf("%.2f%%", ($free *100) / $tsize) ;

        			fill_arc($image,$x,$y,$size,$angle_from,$angle_from + $angle_to ,$col_black,$col_green,$perc);
        			$angle_from = $angle_from + $angle_to ;
                }
    			if ($used>0){
    			// draw used
        			$angle_to = ($used*360)/$tsize;
        			$perc =sprintf("%.2f%%", ($used *100) / $tsize) ;
        			fill_arc($image,$x,$y,$size,$angle_from,$angle_from + $angle_to ,$col_black,$col_red, '('.$perc.')' );
                    $angle_from = $angle_from+ $angle_to ;
    			}
    			}

        break;

        case 2: // hit miss

            $hits = ($memcacheStats['get_hits']==0) ? 1:$memcacheStats['get_hits'];
            $misses = ($memcacheStats['get_misses']==0) ? 1:$memcacheStats['get_misses'];
            $total = $hits + $misses ;

	       	fill_box($image, 30,$size,50,-$hits*($size-21)/$total,$col_black,$col_green,sprintf("%.1f%%",$hits*100/$total));
		    fill_box($image,130,$size,50,-max(4,($total-$hits)*($size-21)/$total),$col_black,$col_red,sprintf("%.1f%%",$misses*100/$total));
		break;
		
    }
    header("Content-type: image/png");
	imagepng($image);
	exit;
}

echo getHeader();
echo getMenu();

switch ($_GET['op']) {

    case 1: // host stats
        $phpversion = phpversion();
        $memcacheStats = getMemcacheStats();
        $memcacheStatsSingle = getMemcacheStats(false);
        $memcacheVersion = getMemcacheVersion();

        $mem_size = $memcacheStats['limit_maxbytes'];
    	$mem_used = $memcacheStats['bytes'];
	    $mem_avail= $mem_size-$mem_used;
	    $startTime = time()-array_sum($memcacheStats['uptime']);

        $curr_items = $memcacheStats['curr_items'];
        $total_items = $memcacheStats['total_items'];
        $hits = ($memcacheStats['get_hits']==0) ? 1:$memcacheStats['get_hits'];
        $misses = ($memcacheStats['get_misses']==0) ? 1:$memcacheStats['get_misses'];
        $sets = $memcacheStats['cmd_set'];

       	$req_rate = sprintf("%.2f",($hits+$misses)/($time-$startTime));
	    $hit_rate = sprintf("%.2f",($hits)/($time-$startTime));
	    $miss_rate = sprintf("%.2f",($misses)/($time-$startTime));
	    $set_rate = sprintf("%.2f",($sets)/($time-$startTime));

	    echo <<< EOB
		<div class="info div1"><h2>General Cache Information</h2>
		<table cellspacing=0><tbody>
		<tr class=tr-1><td class=td-0>PHP Version</td><td>$phpversion</td></tr>
EOB;
		echo "<tr class=tr-0><td class=td-0>Memcached Host". ((count($MEMCACHE_SERVERS)>1) ? 's':'')."</td><td>";

		$i=0;
		if (!isset($_GET['singleout']) && count($MEMCACHE_SERVERS)>1){
    		foreach($MEMCACHE_SERVERS as $server){
    		      echo ($i+1).'. <a href="'.$PHP_SELF.'&singleout='.$i++.'">'.$server.'</a><br/>';
    		}
		}
		else{
		    echo '1.'.$MEMCACHE_SERVERS[0];
		}
		if (isset($_GET['singleout'])){
		      echo '<a href="'.$PHP_SELF.'">(all servers)</a><br/>';
		}
		echo "</td></tr>\n";
		echo "<tr class=tr-1><td class=td-0>Max Memcache Size</td><td>".bsize($memcacheStats['limit_maxbytes'])."</td></tr>\n";

	echo <<<EOB
		</tbody></table>
		</div>

		<div class="info div1"><h2>Memcache Server Information</h2>
EOB;
        foreach($MEMCACHE_SERVERS as $server){
            echo '<table cellspacing=0><tbody>';
            echo '<tr class=tr-0><td class=td-0>'.$server.'</td><td>
                  <button onclick="javascript:must_confirm(\'Delete all content on the server?!\',\''.$PHP_SELF.'&server='.array_search($server,$MEMCACHE_SERVERS).'&op=6\');">[<b>Flush server</b>]</button>
                  </td></tr>';
            echo "<tr class=tr-1><td class=td-0>Memcache Version</td><td>".($memcacheVersion[$server])."</td></tr>";
            
    		echo '<tr class=tr-0><td class=td-0>Start Time</td><td>',date(DATE_FORMAT,$memcacheStatsSingle[$server]['STAT']['time']-$memcacheStatsSingle[$server]['STAT']['uptime']),'</td></tr>';
    		echo '<tr class=tr-1><td class=td-0>Uptime</td><td>',duration($memcacheStatsSingle[$server]['STAT']['time']-$memcacheStatsSingle[$server]['STAT']['uptime']),'</td></tr>';
    		echo '<tr class=tr-0><td class=td-0>Memcached Server Version</td><td>'.$memcacheStatsSingle[$server]['STAT']['version'].'</td></tr>';
    		echo '<tr class=tr-1><td class=td-0>Used Cache Size</td><td>',bsize($memcacheStatsSingle[$server]['STAT']['bytes']),'</td></tr>';
    		echo '<tr class=tr-0><td class=td-0>Max Cache Size</td><td>',bsize($memcacheStatsSingle[$server]['STAT']['limit_maxbytes']),'</td></tr>';
    		
    		echo '<tr class=tr-1><td class=td-0>Current Connections Count</td><td>',(int)($memcacheStatsSingle[$server]['STAT']['curr_connections']),'</td></tr>';
    		echo '<tr class=tr-0><td class=td-0>Total Connections So Far</td><td>',(int)($memcacheStatsSingle[$server]['STAT']['total_connections']),'</td></tr>';
    		echo '<tr class=tr-1><td class=td-0>Flush CMD count</td><td>',(int)($memcacheStatsSingle[$server]['STAT']['cmd_flush']),'</td></tr>';
    		echo '<tr class=tr-0><td class=td-0>Get CMD count</td><td>',(int)($memcacheStatsSingle[$server]['STAT']['cmd_get']),'</td></tr>';
    		echo '<tr class=tr-1><td class=td-0>Set CMD cunt</td><td>',(int)($memcacheStatsSingle[$server]['STAT']['cmd_set']),'</td></tr>';
    		echo '<tr class=tr-0><td class=td-0>Items Evicted So Far</td><td>',(int)($memcacheStatsSingle[$server]['STAT']['evictions']),'</td></tr>';
    		echo '<tr class=tr-1><td class=td-0>Bytes Read So Far</td><td>',bsize($memcacheStatsSingle[$server]['STAT']['bytes_read']),'</td></tr>';
    		echo '<tr class=tr-0><td class=td-0>Bytes Written So Far</td><td>',bsize($memcacheStatsSingle[$server]['STAT']['bytes_written']),'</td></tr>';
    		echo '<tr class=tr-1><td class=td-0>Threads</td><td>',(int)($memcacheStatsSingle[$server]['STAT']['threads']),'</td></tr>';
    		
            echo '<tr class=tr-0><td class=td-0>'.$server.'</td><td>
                  <button onclick="javascript:must_confirm(\'Clear stats on the server?!\',\''.$PHP_SELF.'&server='.array_search($server,$MEMCACHE_SERVERS).'&op=7\');">[<b>Reset stats</b>]</button>
                  </td></tr>';
            
    		echo '</tbody></table>';
	   }
    echo <<<EOB

		</div>
		<div class="graph div3"><h2>Host Status Diagrams</h2>
		<table cellspacing=0><tbody>
EOB;

	$size='width='.(GRAPH_SIZE+50).' height='.(GRAPH_SIZE+10);
	echo <<<EOB
		<tr>
		<td class=td-0>Cache Usage</td>
		<td class=td-1>Hits &amp; Misses</td>
		</tr>
EOB;

	echo
		graphics_avail() ?
			  '<tr>'.
			  "<td class=td-0><img alt=\"\" $size src=\"$PHP_SELF&IMG=1&".(isset($_GET['singleout'])? 'singleout='.$_GET['singleout'].'&':'')."$time\"></td>".
			  "<td class=td-1><img alt=\"\" $size src=\"$PHP_SELF&IMG=2&".(isset($_GET['singleout'])? 'singleout='.$_GET['singleout'].'&':'')."$time\"></td></tr>\n"
			: "",
		'<tr>',
		'<td class=td-0><span class="green box">&nbsp;</span>Free: ',bsize($mem_avail).sprintf(" (%.1f%%)",$mem_avail*100/$mem_size),"</td>\n",
		'<td class=td-1><span class="green box">&nbsp;</span>Hits: ',$hits.sprintf(" (%.1f%%)",$hits*100/($hits+$misses)),"</td>\n",
		'</tr>',
		'<tr>',
		'<td class=td-0><span class="red box">&nbsp;</span>Used: ',bsize($mem_used ).sprintf(" (%.1f%%)",$mem_used *100/$mem_size),"</td>\n",
		'<td class=td-1><span class="red box">&nbsp;</span>Misses: ',$misses.sprintf(" (%.1f%%)",$misses*100/($hits+$misses)),"</td>\n";
		echo <<< EOB
	</tr>
	</tbody></table>
<br/>
	<div class="info"><h2>Cache Information</h2>
		<table cellspacing=0><tbody>
		<tr class=tr-0><td class=td-0>Current Items(total)</td><td>$curr_items ($total_items)</td></tr>
		<tr class=tr-1><td class=td-0>Hits</td><td class=td-1>{$hits}</td></tr>
		<tr class=tr-0><td class=td-0>Misses</td><td>{$misses}</td></tr>
		<tr class=tr-1><td class=td-0>Request Rate (hits, misses)</td><td>$req_rate cache requests/second</td></tr>
		<tr class=tr-0><td class=td-0>Hit Rate</td><td>$hit_rate cache requests/second</td></tr>
		<tr class=tr-1><td class=td-0>Miss Rate</td><td>$miss_rate cache requests/second</td></tr>
		<tr class=tr-0><td class=td-0>Set Rate</td><td>$set_rate cache requests/second</td></tr>
		</tbody></table>
		</div>

EOB;

    break;

    case 2: // variables

		$m=0;
		$cacheItems= getCacheItems();
		$slabInfo= getAllSlabStats();
		
		$items = $cacheItems['items'];
		$totals = $cacheItems['counts'];
		$maxDump = MAX_ITEM_DUMP;
		foreach($items as $server => $entries) {

    	echo <<< EOB

			<div class="info"><table cellspacing=0><tbody>
			<tr><th colspan="2">$server</th></tr>
			<tr><th>Slab Id</th><th>Info</th></tr>
EOB;

			foreach($entries as $slabId => $slab) {
			    $dumpUrl = $PHP_SELF.'&op=2&server='.(array_search($server,$MEMCACHE_SERVERS)).'&dumpslab='.$slabId;
				echo
					"<tr class=tr-$m>",
					"<td class=td-0><center>",'<a href="',$dumpUrl,'">',$slabId,'</a>',"</center></td>",
					"<td class=td-last>
					   <b>Item count: </b> ",$slab['number'],'<br/>
					   <b>Chunk Size (max item size): </b> ',(bsize($slabInfo[$server][$slabId]['chunk_size'])).'<br/>
					   <b>Chunks Per Page (items per 1MB): </b> ',(($slabInfo[$server][$slabId]['chunks_per_page'])).'<br/>
					   <b>Pages Allocated: </b> ',(($slabInfo[$server][$slabId]['total_pages'])).'<br/>
					   <b>Total Chunks (capacity): </b> ',(($slabInfo[$server][$slabId]['total_chunks'])).'<br/>
					   <b>Used Chunks (capacity): </b> ',(($slabInfo[$server][$slabId]['used_chunks'])).'<br/>
					   <b>Free Chunks (free capacity): </b> ',(($slabInfo[$server][$slabId]['total_chunks'] - $slabInfo[$server][$slabId]['used_chunks'])).'<br/>
					   <b>Evicted: </b> '.((int)$slab['evicted']).'<br/>
					   <b>Age: </b> ',duration($time-$slab['age']),'<br/>';
					if ((isset($_GET['dumpslab']) && $_GET['dumpslab']==$slabId) &&  (isset($_GET['server']) && $_GET['server']==array_search($server,$MEMCACHE_SERVERS))){
					    echo "<br/><b>Items: item</b><br/>";
					    $items = dumpCacheSlab($server,$slabId,$slab['number']);
                        ksort($items['ITEM']);
                        foreach($items['ITEM'] as $itemKey=>$itemInfo){
                            echo '<a href="',$PHP_SELF,'&op=4&server=',(array_search($server,$MEMCACHE_SERVERS)),'&key=',base64_encode($itemKey).'">'.$itemKey.'</a><br> ';
                        }
					}

					echo "</td></tr>";
				$m=1-$m;
			}
		echo <<<EOB
			</tbody></table>
			</div><hr/>
EOB;
}
		break;

    break;

    case 4: //item dump
        if (!isset($_GET['key']) || !isset($_GET['server'])){
            echo "No key set!";
            break;
        }
        // I'm not doing anything to check the validity of the key string.
        // probably an exploit can be written to delete all the files in key=base64_encode("\n\r delete all").
        // somebody has to do a fix to this.
        $theKey = htmlentities(base64_decode($_GET['key']));

        $theserver = $MEMCACHE_SERVERS[(int)$_GET['server']];
        list($h,$p) = explode(':',$theserver);
        $r = sendMemcacheCommand($h,$p,'get '.$theKey);
        echo <<<EOB
        <div class="info"><table cellspacing=0><tbody>
			<tr><th>Server<th>Key</th><th>Value</th><th>Delete</th></tr>
EOB;
        echo "<tr><td class=td-0>",$theserver,"</td><td class=td-0>",$theKey,
             " <br/>flag:",$r['VALUE'][$theKey]['stat']['flag'],
             " <br/>Size:",bsize($r['VALUE'][$theKey]['stat']['size']),
             "</td><td>",chunk_split($r['VALUE'][$theKey]['value'],40),"</td>",
             '<td><a href="',$PHP_SELF,'&op=5&server=',(int)$_GET['server'],'&key=',base64_encode($theKey),"\">Delete</a></td>","</tr>";
        echo <<<EOB
			</tbody></table>
			</div><hr/>
EOB;
    break;
    case 5: // item delete
    	if (!isset($_GET['key']) || !isset($_GET['server'])){
			echo "No key set!";
			break;
        }
        $theKey = htmlentities(base64_decode($_GET['key']));
		$theserver = $MEMCACHE_SERVERS[(int)$_GET['server']];
		list($h,$p) = explode(':',$theserver);
        $r = sendMemcacheCommand($h,$p,'delete '.$theKey);
        echo 'Deleting '.$theKey.' : '.$r;
	break;
    
   case 6: // flush server
        $theserver = $MEMCACHE_SERVERS[(int)$_GET['server']];
        $r = flushServer($theserver);
        echo 'Flush  '.$theserver." : ".$r;
   break;
   case 7: // flush stats
        $theserver = $MEMCACHE_SERVERS[(int)$_GET['server']];
        $r = flushStats($theserver);
        echo 'Stats reset '.$theserver." : ".print_r( $r,true);
   break;
   case 8: // variables
		$m=0;
		$cacheItems= getCacheItems();
		$slabInfo  = getAllSlabStats();
        $items = $cacheItems['items'];
		$totals = $cacheItems['counts'];
		$maxDump = MAX_ITEM_DUMP;
		foreach($items as $server => $entries) {

        $memcacheStats = getMemcacheStats();
        

    	echo <<< EOB

			<h3>$server</h3>
            <table cellspacing=1 id="slabStats" class="tablesorter"><thead>
			<tr>
            <th class="vtip" title="Id of the slab">Id</th>
            <th class="vtip" title="Current items count">Items</th>
            <th class="vtip" title="The amount of space each chunk uses.<br>One item will use one chunk of the appropriate size">Chunk Size</th>
            <th class="vtip" title="How many chunks exist within one page. A page by default is one megabyte in size.<br>Slabs are allocated per page, then broken into chunks">Chunks per page</th>
            <th class="vtip" title="Total number of pages allocated to the slab class">Pages</th>
            <th class="vtip" title="Total number of chunks allocated to the slab class">Total Chunks</th>
            <th class="vtip" title="How many chunks have been allocated to items">Used Chunks</th>
            <th class="vtip" title="Chunks not yet allocated to items, or freed via delete">Free Chunks</th>
            <th class="vtip" title="How much memory is wasted because its not used by any item">Free Bytes</th>
            <th class="vtip" title="% of total cache space (all memeory allocated by memcache) used by slabs of this class">% of space</th>
            <th class="vtip" title="Number of times an item had to be evicted from the LRU before it expired">Evicted</th>
            <th class="vtip" title="Seconds since the last access for the most recent item evicted from this class.<br>Use this to judge how recently active your evicted data is">Evicted time</th>
            <th class="vtip" title="Age of the oldest item in the LRU.">Age</th>
            <th class="vtip" title="Details and keys">Link</th>
            </tr>
            </thead>
            <tbody>

EOB;

			foreach($entries as $slabId => $slab) {
                $itemsCount = $slab['number'];
                $chunkSize  = $slabInfo[$server][$slabId]['chunk_size'];
                $itemsSpace = round(($itemsCount * $chunkSize * 100) / $memcacheStats['limit_maxbytes'], 3);

                $chunks_total = $slabInfo[$server][$slabId]['total_chunks'];
                $chunks_used  = $slabInfo[$server][$slabId]['used_chunks'];

                $percentFree = round((($chunks_total - $chunks_used) * 100) / $chunks_total, 2);

                $totalBytes  = $chunks_total * $chunkSize;
                $usedBytes   = $slabInfo[$server][$slabId]['used_chunks'] * $chunkSize;
                $unusedBytes = $totalBytes - $usedBytes;

			    $dumpUrl = $PHP_SELF.'&op=2&server='.(array_search($server,$MEMCACHE_SERVERS)).'&dumpslab='.$slabId;
				echo "
					<tr class=tr-$m>
					<td class=td-0>".$slabId."</td>
					<td>".$itemsCount."</td>
					<td>".$chunkSize."</td>
					<td>".(($slabInfo[$server][$slabId]['chunks_per_page']))."</td>
					<td>".(($slabInfo[$server][$slabId]['total_pages']))."</td>
					<td class='vtip' title='".bsize($totalBytes)."'>".$chunks_total."</td>
					<td class='vtip' title='".bsize($usedBytes)."'>".$chunks_used."</td>
					<td class='vtip' title='".$percentFree." % allocated chunks in this class'>".($chunks_total - $chunks_used)."</td>
					<td class='vtip' title='".bsize($unusedBytes)."'>".$unusedBytes."</td>
					<td>".$itemsSpace."</td>
					<td>".(int)$slab['evicted']."</td>
					<td>".(int)$slab['evicted_time']."</td>
                    <td>".duration($time-$slab['age'])."</td>
					<td class=td-0><center><a href=\"".$dumpUrl."\">details</a></center></td>

                    </tr>";
				$m=1-$m;
			}
		echo <<<EOB
			</tbody></table>
			<hr/>
EOB;
}
		break;

    break;




}
echo getFooter();

?>