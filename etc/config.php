<?php
if (!isset($VERSION)) {
    echo "Not allowed.";
    exit(1);
}

define('ADMIN_USERNAME','admin');   // Admin Username
define('ADMIN_PASSWORD','pass');    // Admin Password
define('DATE_FORMAT','Y/m/d H:i:s');
define('GRAPH_SIZE',200);
define('MAX_ITEM_DUMP',50);
define('MEMCACHE_SCRIPT', $_SERVER['PHP_SELF']);
define('BASE_URL', '/');

$MEMCACHE_SERVERS[] = 'localhost:11211'; // add more as an array
//$MEMCACHE_SERVERS[] = 'mymemcache-server2:11211'; // add more as an array
