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