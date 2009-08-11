<?php
$Module = array(
	'name'            => 'lamemcache',
    'variable_params' => true
);

$ViewList         = array();

$ViewList['test'] = array(
    'script'                  => 'test.php',
	'functions'               => array( 'droit_anonyme' )
);

$ViewList['stats_server'] = array(
    'script'                  => 'stats_server.php',
	'functions'               => array( 'droit_compte' )
);



$FunctionList = array();
$FunctionList['droit_compte'] = array( );
$FunctionList['droit_anonyme'] = array( );
?>