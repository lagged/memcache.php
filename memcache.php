<?php
/*
  +----------------------------------------------------------------------+
  | PHP Version 5                                                        |
  +----------------------------------------------------------------------+
  | Copyright (c) 1997-2012 The PHP Group                                |
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
  |                   Till Klampaeckel <till@php.net>                    |
  +----------------------------------------------------------------------+
*/

$VERSION='$Id: modified memcache.php,v 1.1.2.3 2008/08/28 18:07:54 mikl Exp $';

/**
 * This allows us to override the path to the local configuration before including
 * 'memcache.php'. Pretty cool, no?
 */
if (!isset($localConfig) || empty($localConfig)) {
	$localConfig = __DIR__ . '/etc/config.local.php';
}

if (true === file_exists($localConfig)) {
    require_once $localConfig;
} else {
    require_once __DIR__ . '/etc/config.php';
}

////////// END OF DEFAULT CONFIG AREA /////////////////////////////////////////////////////////////

///////////////// Password protect ////////////////////////////////////////////////////////////////
if (defined('ADMIN_USERNAME') && ADMIN_USERNAME != '') {
    if (!isset($_SERVER['PHP_AUTH_USER'])
        || !isset($_SERVER['PHP_AUTH_PW'])
        || $_SERVER['PHP_AUTH_USER'] != ADMIN_USERNAME
        || $_SERVER['PHP_AUTH_PW'] != ADMIN_PASSWORD
    ) {
        Header("WWW-Authenticate: Basic realm=\"Memcache Login\"");
        Header("HTTP/1.0 401 Unauthorized");

        echo <<<EOB
<html><body>
    <h1>Rejected!</h1>
    <big>Wrong Username or Password!</big>
</body></html>
EOB;
        exit(1);
    }
}

// so oldschool :-)
require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/src/display.functions.php';

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

// TODO, AUTH

$PHP_SELF = getUrl();

$_GET['op'] = !isset($_GET['op'])? '1':$_GET['op'];
$time = time();

$_GET = filter_input_array(INPUT_GET);

// singleout
// when singleout is set, it only gives details for that server.
if (isset($_GET['singleout']) && $_GET['singleout']>=0 && $_GET['singleout'] <count($GLOBALS['MEMCACHE_SERVERS'])){
    $GLOBALS['MEMCACHE_SERVERS'] = array($GLOBALS['MEMCACHE_SERVERS'][$_GET['singleout']]);
}

// display images
if (isset($_GET['IMG'])){
    $memcacheStats = getMemcacheStats();
    $memcacheStatsSingle = getMemcacheStats(false);

    if (!graphics_avail()) {
        exit(0);
    }

    require_once __DIR__ . '/src/graph.functions.php';

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

if (RUN_WRAPPED !== true) {
    echo getHeader();
}
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
<div class="row">
    <div class="span8">
		<h2>General Cache Information</h2>
		<table class="table table-striped"><tbody>
		<tr><th scope="row">PHP Version</td><td>{$phpversion}</td></tr>
EOB;
        echo '<tr><th scope="row">Memcached Host'. ((count($GLOBALS['MEMCACHE_SERVERS'])>1) ? 's':'')."</td><td>";

        $i=0;
        if (!isset($_GET['singleout']) && count($GLOBALS['MEMCACHE_SERVERS'])>1){
            foreach($GLOBALS['MEMCACHE_SERVERS'] as $server){
                  echo ($i+1).'. <a href="'.$PHP_SELF.'&singleout='.$i++.'">'.$server.'</a><br/>';
            }
        }
        else{
            echo '1.'.$GLOBALS['MEMCACHE_SERVERS'][0];
        }
        if (isset($_GET['singleout'])){
              echo '<a href="'.$PHP_SELF.'">(all servers)</a><br/>';
        }
        echo "</td></tr>\n";
        echo '<tr><th scope="row">Max Memcache Size</td><td>' . bsize($memcacheStats['limit_maxbytes'])."</td></tr>\n";

    echo <<<EOB
        </tbody></table>
    </div>
</div>
<div class="row">
    <div class="span8">
		<h2>Memcache Server Information</h2>
EOB;
        foreach($GLOBALS['MEMCACHE_SERVERS'] as $server){
            echo '<table class="table table-striped"><tbody>';
            echo '<tr><th scope="row">'.$server.'</td><td>
                    <a onclick="javascript:must_confirm(\'Delete all content on the server?!\',\''.$PHP_SELF.'&server='.array_search($server,$GLOBALS['MEMCACHE_SERVERS']).'&op=6\');">
                        <span class="label label-warning">Flush server</span>
                    </a>
                  </td></tr>';
            echo '<tr><th scope="row">Memcache Version</td><td>' . ($memcacheVersion[$server]) . "</td></tr>";
            echo '<tr><th scope="row">Start Time</td><td>',date(DATE_FORMAT,$memcacheStatsSingle[$server]['STAT']['time']-$memcacheStatsSingle[$server]['STAT']['uptime']),'</td></tr>';
            echo '<tr><th scope="row">Uptime</td><td>',duration($memcacheStatsSingle[$server]['STAT']['time']-$memcacheStatsSingle[$server]['STAT']['uptime']),'</td></tr>';
            echo '<tr><th scope="row">Memcached Server Version</td><td>'.$memcacheStatsSingle[$server]['STAT']['version'].'</td></tr>';
            echo '<tr><th scope="row">Used Cache Size</td><td>',bsize($memcacheStatsSingle[$server]['STAT']['bytes']),'</td></tr>';
            echo '<tr><th scope="row">Max Cache Size</td><td>',bsize($memcacheStatsSingle[$server]['STAT']['limit_maxbytes']),'</td></tr>';
            echo '<tr><th scope="row">Current Connections Count</td><td>',(int)($memcacheStatsSingle[$server]['STAT']['curr_connections']),'</td></tr>';
            echo '<tr><th scope="row">Total Connections So Far</td><td>',(int)($memcacheStatsSingle[$server]['STAT']['total_connections']),'</td></tr>';
            echo '<tr><th scope="row">Flush CMD count</td><td>',(int)($memcacheStatsSingle[$server]['STAT']['cmd_flush']),'</td></tr>';
            echo '<tr><th scope="row">Get CMD count</td><td>',(int)($memcacheStatsSingle[$server]['STAT']['cmd_get']),'</td></tr>';
            echo '<tr><th scope="row">Set CMD cunt</td><td>',(int)($memcacheStatsSingle[$server]['STAT']['cmd_set']),'</td></tr>';
            echo '<tr><th scope="row">Items Evicted So Far</td><td>',(int)($memcacheStatsSingle[$server]['STAT']['evictions']),'</td></tr>';
            echo '<tr><th scope="row">Bytes Read So Far</td><td>',bsize($memcacheStatsSingle[$server]['STAT']['bytes_read']),'</td></tr>';
            echo '<tr><th scope="row">Bytes Written So Far</td><td>',bsize($memcacheStatsSingle[$server]['STAT']['bytes_written']),'</td></tr>';
            echo '<tr><th scope="row">Threads</td><td>',(int)($memcacheStatsSingle[$server]['STAT']['threads']),'</td></tr>';
            echo '<tr><th scope="row">'.$server.'</td><td>
                    <a onclick="javascript:must_confirm(\'Clear stats on the server?!\',\''.$PHP_SELF.'&server='.array_search($server,$GLOBALS['MEMCACHE_SERVERS']).'&op=7\');">
                        <span class="label label-warning">Reset stats</span>
                    </a>
                  </td></tr>';
            echo '</tbody></table>';
       }
    echo <<<EOB
    </div>
</div>

<div class="row">
    <div class="span8">

        <h2>Host Status Diagrams</h2>
        <table class="table table-striped"><thead>
EOB;

    $size='width='.(GRAPH_SIZE+50).' height='.(GRAPH_SIZE+10);
    echo <<<EOB
        <tr>
        <th>Cache Usage</th>
        <th>Hits &amp; Misses</th>
        </tr>
        </thead>
        <tbody>
EOB;

    echo
        graphics_avail() ?
              '<tr>'.
              "<td><img alt=\"\" $size src=\"$PHP_SELF&IMG=1&".(isset($_GET['singleout'])? 'singleout='.$_GET['singleout'].'&':'')."$time\"></td>".
              "<td><img alt=\"\" $size src=\"$PHP_SELF&IMG=2&".(isset($_GET['singleout'])? 'singleout='.$_GET['singleout'].'&':'')."$time\"></td></tr>\n"
            : "",
        '<tr>',
        '<td><span class="label label-success">Free</span> ',bsize($mem_avail).sprintf(" (%.1f%%)",$mem_avail*100/$mem_size),"</td>\n",
        '<td><span class="label label-success">Hits</span> ',$hits.sprintf(" (%.1f%%)",$hits*100/($hits+$misses)),"</td>\n",
        '</tr>',
        '<tr>',
        '<td><span class="label label-important">Used</span> ',bsize($mem_used ).sprintf(" (%.1f%%)",$mem_used *100/$mem_size),"</td>\n",
        '<td><span class="label label-important">Misses</span> ',$misses.sprintf(" (%.1f%%)",$misses*100/($hits+$misses)),"</td>\n";
        echo <<< EOB
    </tr>
    </tbody></table>
    </div>
</div>
<div class="row">
    <div class="span8">
	<h2>Cache Information</h2>
		<table class="table table-striped"><tbody>
		<tr><th scope="row">Current Items(total)</th><td>$curr_items ($total_items)</td></tr>
		<tr><th scope="row">Hits</td><td>{$hits}</td></tr>
		<tr><th scope="row">Misses</td><td>{$misses}</td></tr>
		<tr><th scope="row">Request Rate (hits, misses)</td><td>$req_rate cache requests/second</td></tr>
		<tr><th scope="row"><span class="label label-success">Hit Rate</span></td><td>$hit_rate cache requests/second</td></tr>
		<tr><th scope="row"><span class="label label-important">Miss Rate</span></td><td>$miss_rate cache requests/second</td></tr>
		<tr><th scope="row"><span class="label label-info">Set Rate</span></td><td>$set_rate cache requests/second</td></tr>
		</tbody></table>
    </div>
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

            <table class="table table-striped"><thead>
            <tr><th colspan="2">$server</th></tr>
            <tr><th>Slab Id</th><th>Info</th></tr>
            </thead>
            <tbody>
EOB;

            foreach($entries as $slabId => $slab) {
                $dumpUrl = $PHP_SELF.'&op=2&server='.(array_search($server,$GLOBALS['MEMCACHE_SERVERS'])).'&dumpslab='.$slabId;
                echo
                    "<tr>",
                    "<td><center>",'<a href="',$dumpUrl,'">',$slabId,'</a>',"</center></td>",
                    "<td>
                       <b>Item count: </b> ",$slab['number'],'<br/>
                       <b>Chunk Size (max item size): </b> ',(bsize($slabInfo[$server][$slabId]['chunk_size'])).'<br/>
                       <b>Chunks Per Page (items per 1MB): </b> ',(($slabInfo[$server][$slabId]['chunks_per_page'])).'<br/>
                       <b>Pages Allocated: </b> ',(($slabInfo[$server][$slabId]['total_pages'])).'<br/>
                       <b>Total Chunks (capacity): </b> ',(($slabInfo[$server][$slabId]['total_chunks'])).'<br/>
                       <b>Used Chunks (capacity): </b> ',(($slabInfo[$server][$slabId]['used_chunks'])).'<br/>
                       <b>Free Chunks (free capacity): </b> ',(($slabInfo[$server][$slabId]['total_chunks'] - $slabInfo[$server][$slabId]['used_chunks'])).'<br/>
                       <b>Evicted: </b> '.((int)$slab['evicted']).'<br/>
                       <b>Age: </b> ',duration($time-$slab['age']),'<br/>';
                    if ((isset($_GET['dumpslab']) && $_GET['dumpslab']==$slabId) &&  (isset($_GET['server']) && $_GET['server']==array_search($server,$GLOBALS['MEMCACHE_SERVERS']))){
                        echo "<br/><b>Items: item</b><br/>";
                        $items = dumpCacheSlab($server,$slabId,$slab['number']);
                        ksort($items['ITEM']);
                        foreach($items['ITEM'] as $itemKey=>$itemInfo){
                            echo '<a href="',$PHP_SELF,'&op=4&server=',(array_search($server,$GLOBALS['MEMCACHE_SERVERS'])),'&key=',base64_encode($itemKey).'">'.$itemKey.'</a><br> ';
                        }
                    }

                    echo "</td></tr>";
                $m=1-$m;
            }
        echo <<<EOB
            </tbody></table>
            <hr/>
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

        $theserver = $GLOBALS['MEMCACHE_SERVERS'][(int)$_GET['server']];
        list($h,$p) = explode(':',$theserver);
        $r = sendMemcacheCommand($h,$p,'get '.$theKey);
        echo <<<EOB
        <table class="table table-striped"><thead>
            <tr><th>Server<th>Key</th><th>Value</th><th>Delete</th></tr>
            </thead><tbody>
EOB;
        echo "<tr><td>",$theserver,"</td><td>",$theKey,
             " <br/>flag:",$r['VALUE'][$theKey]['stat']['flag'],
             " <br/>Size:",bsize($r['VALUE'][$theKey]['stat']['size']),
             "</td><td>",chunk_split($r['VALUE'][$theKey]['value'],40),"</td>",
             '<td><a href="',$PHP_SELF,'&op=5&server=',(int)$_GET['server'],'&key=',base64_encode($theKey),"\">Delete</a></td>","</tr>";
        echo <<<EOB
            </tbody></table>
            <hr/>
EOB;
    break;
    case 5: // item delete
        if (!isset($_GET['key']) || !isset($_GET['server'])){
            echo "No key set!";
            break;
        }
        $theKey = htmlentities(base64_decode($_GET['key']));
        $theserver = $GLOBALS['MEMCACHE_SERVERS'][(int)$_GET['server']];
        list($h,$p) = explode(':',$theserver);
        $r = sendMemcacheCommand($h,$p,'delete '.$theKey);
        echo 'Deleting '.$theKey.' : '.$r;
    break;
    
   case 6: // flush server
        $theserver = $GLOBALS['MEMCACHE_SERVERS'][(int)$_GET['server']];
        $r = flushServer($theserver);
        echo 'Flush  '.$theserver." : ".$r;
   break;
   case 7: // flush stats
        $theserver = $GLOBALS['MEMCACHE_SERVERS'][(int)$_GET['server']];
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

                $dumpUrl = $PHP_SELF.'&op=2&server='.(array_search($server,$GLOBALS['MEMCACHE_SERVERS'])).'&dumpslab='.$slabId;
                echo "
                    <tr>
                    <td>".$slabId."</td>
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
                    <td><center><a href=\"".$dumpUrl."\">details</a></center></td>

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
if (RUN_WRAPPED !== true) {
    echo getFooter();
}
