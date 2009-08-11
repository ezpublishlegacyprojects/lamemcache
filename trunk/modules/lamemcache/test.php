<?php
/*
*monitoring of memcache for system team.
*/
$cache = new lammcache(array('use_zlib' => true));
$data="OK ".time();
$cache->set($data, $data, 5); 
echo $cache->get($data); 

eZExecution::cleanExit();
?>