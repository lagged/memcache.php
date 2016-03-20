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

if (!isset($VERSION)) {
    echo "Not allowed.";
    exit(1);
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

/**
 * create menu entry
 *
 * @return string
 */
function menu_entry($ob,$title) {
	$PHP_SELF = getUrl();
	if ($ob==$_GET['op']){
	    return "<li class=\"active\"><a href=\"{$PHP_SELF}&op={$ob}\">{$title}</a></li>";
	}
	return "<li><a href=\"{$PHP_SELF}&op={$ob}\">{$title}</a></li>";
}

/**
 * Print the header when we run stand-alone.
 *
 * @return void
 */
function getHeader()
{

    $jq_core  = JQ_CORE;
    $jq_ts    = JQ_TABLESORT;
    $base_url = BASE_URL;
    $PHP_SELF = getUrl();

    $header = <<<EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-us">
<head><title>MEMCACHE INFO</title>

<link rel="stylesheet" href="{$base_url}js/style.css" type="text/css" />
<link rel="stylesheet" href="{$base_url}js/vtip/css/vtip.css" type="text/css" />
<link href="bootstrap/css/bootstrap.min.css" rel="stylesheet" media="screen">

<script type="text/javascript" src="{$jq_core}"></script>
<script type="text/javascript" src="{$jq_ts}"></script>
<script type="text/javascript" src="{$base_url}js/vtip/vtip-min.js"></script>
<script src="bootstrap/js/bootstrap.min.js"></script>

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

/* console.debug(123); */
</script>

</head>
<body>
<div class="container">
    <div class="navbar navbar-fixed-top">
        <div class="navbar-inner">
            <div class="container">
                <a class="brand" href="{$PHP_SELF}">Memcache stats v0.X</a>
EOT;
    $PHP_SELF = getUrl();
    $header .= '<ul class="nav pull-right">';
    if ($_GET['op'] !=4 ){
        $header .= <<<EOB
<li><a href="$PHP_SELF&op={$_GET['op']}"><span class="text-info"><span class="icon icon-repeat"> </span> Refresh Stats</span> </a></li>
EOB;

    } else {
        $header .= <<<EOB
    <li><a href="$PHP_SELF&op=2">Back</a></li>
EOB;
    }
    $header .= menu_entry(1,'View Host Stats').
	    menu_entry(2,'Variables').
	    menu_entry(8,'Slabs');

	    $header .= <<<EOT
                </ul>
            </div>
        </div>
    </div>
EOT;

    return $header;
}
function getFooter(){
    $footer = '
                <ul class="nav nav-tabs">
                    <li><a href="http://github.com/lagged/memcache.php">Github</a></li>
                    <li><a href="http://artur.ejsmont.org">Arthur Ejsmont</a></li>
                    <li><a href="http://livebookmark.net/journal/2008/05/21/memcachephp-stats-like-apcphp/">Original memcache.php website</a></li>
                </ul>
	</div>
    </body>
</html>
';

    return $footer;

}
function getMenu(){

    echo <<<EOB

EOB;
}
