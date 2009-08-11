<?php
//include_once( 'lib/ezutils/classes/ezini.php' );
	
class lammcache
{
	// (object) memcache instance
	var $_memcache      = NULL;
	const USE_MEMCACHE  = 0;
	// (bool) cache enable		
	var $_status        = NULL;                 
	
	// (bool) use persistent connexion
	// (bool) use zlib to store conpressed data
	// (int)  timeout (secondes)
	// (int)  default item ttl (secondes)
	
	var $_params        = array(
		'use_pconnect'  => false,                    
		'use_zlib'      => false,                    
		'timeout'       => 2,                        
		'default_ttl'   => 86400                     
	);
	
	// definition of the server
	// (srting) hostname or ip
	// (int)    tcp port
	// (int)    server's weight
	var $_servers       = array();
	
	var $_ini = null;
	
	var $_site = null;
 
   /**
    * New instance of memcache
    *
    * @param array $params         parameters
    * @param array $servers        servers pool
    */
	function lammcache($params = array(), $servers = NULL) {
		eZDebug::accumulatorStart(5, 'MemCache', 'init');
		
		$this->_ini = eZINI::instance('lamemcache.ini');
		$this->_status = (boolean) $this->_ini->variable( 'lamemcacheSettings', 'cache_enable' );
		$this->_site   = $this->_ini->variable( 'lamemcacheSettings', 'namesite' );
		$this->_params = array_merge($this->_params, $params);

		$this->_params['use_zlib'] = (true === $this->_params['use_zlib'] ? MEMCACHE_COMPRESSED : 0);
		
		$aServers = $this->_ini->variable( 'lamemcacheSettings', 'servers' );
		
		$aIndex = array('host', 'port', 'weight');
		if (is_array($aServers) && count($aServers)) {
			foreach ($aServers as $sServer) {
				$aServer = array();
				$aInfoServer = array();
				$aServer = explode(';', $sServer);
				if (is_array($aServer) && count($aServer)) {
					foreach ($aServer as $iIndex => $sInfo) {
						$aInfoServer[$aIndex[$iIndex]] = $sInfo;
					}
				}
				if (is_array($aInfoServer) && count($aInfoServer)) {
					$this->_servers[] = $aInfoServer;
				}
			}
		}

		if(NULL !== $servers) $this->_servers = $servers;

		if(true === $this->_status) {
			
			if (!isset($GLOBALS['lamemcache'])) {
				$this->_memcache = new Memcache;
				assert('true === is_object($this->_memcache)');
				$this->_connect();
				$GLOBALS['lamemcache'] = $this->_memcache;
			} else {
				$this->_memcache = $GLOBALS['lamemcache'];
			}
		}
		eZDebug::accumulatorStop(5);
	}
 
   /**
    * (void) Connect to the memcache pool servers
    */
	function _connect() {
		eZDebug::accumulatorStart(0, 'MemCache', 'connect');
		foreach($this->_servers as $server) {
			if(false === array_key_exists('host', $server) or false === array_key_exists('port', $server)) {
				return false;
			}

			if(false === $this->_memcache->addServer(
				$server['host'],
				$server['port'],
				$this->_params['use_pconnect'],
				(true === array_key_exists('weight', $server) ? $server['weight'] : 50),
				$this->_params['timeout']
			)) {
				//return false;
			}
		}
		eZDebug::accumulatorStop(0);
		return true;
	}
 
   /**
    * Return the value cached item
    *
    * @param mixed $data   item value
    * @param string $name  item key
    * @param int $ttl              item cache ttl
    * @return mixed                return false if an erro rhas occured
    */
    function set(&$data, $name, $ttl = NULL) {
   	 eZDebug::accumulatorStart(1, 'MemCache', 'Set');
		if (is_null($this->_ini) || is_null($this->_site)) {
			
			$this->_ini  = eZINI::instance('lamemcache.ini');
			$this->_site = $this->_ini->variable( 'lamemcacheSettings', 'namesite' );
		}
		$name = $this->_site . $name;
		$this->_status = (boolean) $this->_ini->variable( 'lamemcacheSettings', 'cache_enable' );
		
		assert('true === is_string($name)');
		if(false === $this->_status) return false;

		if(NULL === $ttl) $ttl = $this->_params['default_ttl'];
		$ret=$this->_memcache->set($name, $data, $this->_params['use_zlib'], $ttl);
		eZDebug::accumulatorStop(1);
		eZDebug::writeNotice( "Write in mmcache the key $name for $ttl s" );
		
    	if(false === $ret) {
			return false;
		}
		return true;
    }
 
	function add(&$data, $name, $ttl = NULL) {
   	 	eZDebug::accumulatorStart(10, 'MemCache', 'add');
		if (is_null($this->_ini) || is_null($this->_site)) {
			
			$this->_ini  = eZINI::instance('lamemcache.ini');
			$this->_site = $this->_ini->variable( 'lamemcacheSettings', 'namesite' );
		}
		$name = $this->_site . $name;
		$this->_status = (boolean) $this->_ini->variable( 'lamemcacheSettings', 'cache_enable' );
		
		assert('true === is_string($name)');
		if(false === $this->_status) return false;

		if(NULL === $ttl) $ttl = $this->_params['default_ttl'];
		$ret=$this->_memcache->add($name, $data, $this->_params['use_zlib'], $ttl);
		eZDebug::accumulatorStop(10);
		eZDebug::writeNotice( "add in mmcache the key $name for $ttl s" );
		
    	if(false === $ret) {
			return false;
		}
		return true;
    }    
    
    
   /**
    * Retrieve item from the server
    *
    * @param string $name  item's name
    * @return mixed  return false if an error has occured
    */
	function get($name) {
		eZDebug::accumulatorStart(3, 'MemCache', 'Get');
		if (is_null($this->_ini) || is_null($this->_site)) {
			
			$this->_ini  = eZINI::instance('lamemcache.ini');
			$this->_site = $this->_ini->variable( 'lamemcacheSettings', 'namesite' );
		}
		$name = $this->_site . $name;
		assert('true === is_string($name)');
		eZDebug::accumulatorStop(3);
		
		eZDebug::writeNotice( "Read in mmcache the key $name" );
		
		return (false === $this->_status ? false : $this->_memcache->get($name));
	}
	
	function delete($name, $time = 0) {
		eZDebug::accumulatorStart(2, 'MemCache', 'Delete');
	
		if (is_null($this->_ini) || is_null($this->_site)) {
			
			$this->_ini  = eZINI::instance('lamemcache.ini');
			$this->_site = $this->_ini->variable( 'lamemcacheSettings', 'namesite' );
		}
		$name = $this->_site . $name;
		assert('true === is_string($name)');
		eZDebug::accumulatorStop(2);
		eZDebug::writeNotice( "Delete in mmcache the key $name" );
		if(false === $this->_memcache->delete($name, $time)) {
			return false;
		}
		return true;
	}
 
    /**
     * (void) Flush all existing items
     */
	function flush() {
		$this->_memcache->flush();
	}
 
    /**
     * Destructor: close the connexions to memcached
     */
	function close() {
		if($this->_memcache) $this->_memcache->close();
	}
}
?>