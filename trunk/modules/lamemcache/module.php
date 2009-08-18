<?php
$Module = array(
	'name'            => 'lamemcache',
    'variable_params' => true
);

$ViewList         = array();

$ViewList['test'] = array(
    'script'                  => 'test.php',
	'functions'               => array( 'public' )
);

$ViewList['stats_server'] = array(
    'script'                  => 'stats_server.php',
	'functions'               => array( 'private' )
);



$FunctionList = array();
$FunctionList['private'] = array( );
$FunctionList['public'] = array( );
?>