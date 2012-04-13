<?php

$memcache_obj = new Memcache;
$memcache_obj->connect('localhost', 11211);

$file = 'big.txt';
$data = file_get_contents($file);

for($i=0; $i<5000; $i++){
    $start  = rand(0,10000);
    $length = rand(50, 5000);
    $part   = substr($data, $start, $length);
    $memcache_obj->set('book_'.md5($part), $part, false, 3600);
}

echo 'Saved';

?>