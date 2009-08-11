
INSTALL:
rename the file lamemcache.ini.append.txt to lamemcache.ini.append.php
Add the memcached servers informations.

update the file autoload\ezp_kernel.php
replace 
'eZSession'                             => 'lib/ezutils/classes/ezsession.php',
by
'eZSession'                          => 'extension/lamemcache/classes/override/ezsession.php',

