<?php
/*
manage ezsession into memcache server
you must update the file autoload\ezp_kernel.php
replace 
'eZSession'                             => 'lib/ezutils/classes/ezsession.php',
by
'eZSession'                          => 'extension/lamemcache/classes/override/ezsession.php',


*/
class eZSession
{
    // Name of session handler, change if you override class with autoload
    const HANDLER_NAME = 'lamemcache';
	 
	
    /**
     * User id, see {@link eZSession::userID()}.
     *
     * @access protected
     */
    static protected $userID = 0;

    /**
     * Flag session started, see {@link eZSession::start()}.
     *
     * @access protected
     */
    static protected $hasStarted = false;

    /**
     * Flag request contains session cookie, set in {@link eZSession::registerFunctions()}.
     *
     * @access protected
     */
    static protected $hasSessionCookie = null;

    /**
     * Flag if user session validated when reading data from session, set in {@link eZSession::internalRead()}.
     *
     * @access protected
     */
    static protected $userSessionIsValid = null;

    /**
     * User session hash (ip + ua string), set in {@link eZSession::registerFunctions()}.
     *
     * @access protected
     */
    static protected $userSessionHash = null;

    /**
     * List of callback actions, see {@link eZSession::addCallback()}.
     *
     * @access protected
     */
    static protected $callbackFunctions = array(); 

    /**
     * Constructor
     *
     * @access protected
     */
    protected function eZSession()
    {
    }

    /**
     * Does nothing, eZDB will open connection when needed.
     * 
     * @return true
     */
    static public function open()
    {
        return true;
    }

    /**
     * Does nothing, eZDB will handle closing db connection.
     * 
     * @return true
     */
    static public function close()
    {
        return true;
    }

    /**
     * Reads the session data from the database for a specific session id
     *
     * @param string $sessionId
     * @return string|false Returns false if session doesn't exits, string in php session format if it does.
     */
    static public function read( $sessionId )
    {
        return self::internalRead( $sessionId, false );
    }

    /**
     * Internal function that reads the session data from the database, this function
     * is registered as session_read handler in {@link eZSession::registerFunctions()}
     * Note: user will be "kicked out" as in get a new session id if {@link self::getUserSessionHash()} does
     * not equals to the existing user_hash unless the user_hash is empty.
     *
     * @access private
     * @param string $sessionId
     * @param bool $isCurrentUserSession
     * @return string|false Returns false if session doesn't exits
     */
    static public function internalRead( $sessionId, $isCurrentUserSession = true )
    {
		$memCache  = new lammcache(array('use_zlib' => true));
        $sessionRes = $isCurrentUserSession && !self::$hasSessionCookie ? false : $memCache->get($sessionId);
		
		
        if ( $sessionRes !== false and count( $sessionRes ) == 1 )
        {
            if ( $isCurrentUserSession )
            {
                if ( $sessionRes[0]['user_hash'] && $sessionRes[0]['user_hash'] != self::getUserSessionHash() )
                {
                    eZDebug::writeNotice( 'User ('. $sessionRes[0]['user_id'] .') hash did not match, regenerating session id for the user to avoid potentially hijack session attempt.', 'eZSession::internalRead' );
                    self::regenerate( false );
                    self::$userID = 0;
                    self::$userSessionIsValid = false;
                    return false;
                }
                else if ( self::$userSessionIsValid === null )
                {
                    self::$userSessionIsValid = true;
                }
                self::$userID = $sessionRes[0]['user_id'];
            }
            $ini = eZINI::instance();

            $sessionUpdatesTime = $sessionRes[0]['expiration_time'] - $ini->variable( 'Session', 'SessionTimeout' );
            $sessionIdle = time() - $sessionUpdatesTime;

            $GLOBALS['eZSessionIdleTime'] = $sessionIdle;

            return $sessionRes[0]['data'];
        }
        else
        {
            return false;
        }
    }

    /**
     * Inserts|Updates the session data in the database for a specific session id
     *
     * @param string $sessionId
     * @param string $value session data (in php session data format)
     */
    static public function write( $sessionId, $value )
    {
        return self::internalWrite( $sessionId, $value, false );
    }

    /**
     * Internal function that inserts|updates the session data in the database, this function
     * is registered as session_write handler in {@link eZSession::registerFunctions()}
     *
     * @access private
     * @param string $sessionId
     * @param string $value session data
     * @param bool $isCurrentUserSession
     */
    static public function internalWrite( $sessionId, $value, $isCurrentUserSession = true )
    {
        if ( isset( $GLOBALS['eZRequestError'] ) && $GLOBALS['eZRequestError'] )
        {
            return false;
        }

        $db = eZDB::instance();
        $ini = eZINI::instance();
        $expirationTime = time() + $ini->variable( 'Session', 'SessionTimeout' );
		$memValue=$value;
        if ( $db->bindingType() != eZDBInterface::BINDING_NO )
        {
            $value = $db->bindVariable( $value, array( 'name' => 'data' ) );
        }
        else
        {
            $value = '\'' . $db->escapeString( $value ) . '\'';
        }
        $escKey = $db->escapeString( $sessionId );
        $userID = 0;
        $userHash = '';

        if ( $isCurrentUserSession )
        {
            $userID = $db->escapeString( self::$userID );
            $userHash = $db->escapeString( self::getUserSessionHash() );
        }
		$memCache  = new lammcache(array('use_zlib' => true));
        // check if session already exists
        $sessionRes = $isCurrentUserSession && !self::$hasSessionCookie ? false : $memCache->get($sessionId);
        if ( $sessionRes !== false and count( $sessionRes ) == 1 )
        {
            self::triggerCallback( 'update_pre', array( $db, $sessionId, $escKey, $expirationTime, $userID, $value ) );
            if ( $isCurrentUserSession ){
            	$sessionRes[0]['expiration_time']=$expirationTime;
            	$sessionRes[0]['data']=$memValue;
            	$sessionRes[0]['user_id']=self::$userID;
            	$sessionRes[0]['user_hash']=self::getUserSessionHash();
            }
            else{
            	$sessionRes[0]['expiration_time']=$expirationTime;
            	$sessionRes[0]['data']=$memValue;
			}
			$memCache->set($sessionRes, $sessionId, $expirationTime);
            self::triggerCallback( 'update_post', array( $db, $sessionId, $escKey, $expirationTime, $userID, $value ) );
        }
        else
        {
            self::triggerCallback( 'insert_pre', array( $db, $sessionId, $escKey, $expirationTime, $userID, $value ) );
			$aData=array();
			$aData[]=array(
				'session_key' 		=>$sessionId, 
				'expiration_time' 	=>$expirationTime, 
				'data'				=>$memValue, 
				'user_id' 			=>self::$userID, 
				'user_hash' 		=>self::getUserSessionHash()
			);        		
    		$memCache->set($aData, $sessionId, $expirationTime);
            self::triggerCallback( 'insert_post', array( $db, $sessionId, $escKey, $expirationTime, $userID, $value ) );
        }			
        return true;
    }

    /**
     * Deletes the session data from the database, this function is 
     * register in {@link eZSession::registerFunctions()}
     *
     * @param string $sessionId
     */
    static public function destroy( $sessionId )
    {
        $db = eZDB::instance();
        $escKey = $db->escapeString( $sessionId );

        self::triggerCallback( 'destroy_pre', array( $db, $sessionId, $escKey ) );
		
       	$memCache  = new lammcache(array('use_zlib' => true));
        $memCache->delete($sessionId);

        self::triggerCallback( 'destroy_post', array( $db, $sessionId, $escKey ) );
    }

    /**
     * Deletes all expired session data in the database, this function is 
     * register in {@link eZSession::registerFunctions()}
     */
    static public function garbageCollector()
    {
        $db = eZDB::instance();
        $time = time();

        self::triggerCallback( 'gc_pre', array( $db, $time ) );

        $db->query( "DELETE FROM ezsession WHERE expiration_time < $time" );

        self::triggerCallback( 'gc_post', array( $db, $time ) );
    }

    /**
     * Truncates all session data in the database.
     * Named eZSessionEmpty() in eZ Publish 4.0 and earlier!
     */
    static public function cleanup()
    {
        $db = eZDB::instance();

        self::triggerCallback( 'cleanup_pre', array( $db ) );

        $db->query( 'TRUNCATE TABLE ezsession' );

        self::triggerCallback( 'cleanup_post', array( $db ) );
    }

    /**
     * Counts the number of active session and returns it.
     * 
     * @return string Returns number of sessions.
     */
    static public function countActive()
    {
        $db = eZDB::instance();

        $rows = $db->arrayQuery( 'SELECT count( * ) AS count FROM ezsession' );
        return $rows[0]['count'];
    }

    /**
     * Register the needed session functions, this is called automatically by 
     * {@link eZSession::start()}, so only call this if you don't start the session.
     * Named eZRegisterSessionFunctions() in eZ Publish 4.0 and earlier!
     * 
     * @return bool Returns true|false depending on if eZSession is registrated as session handler.
    */
    static protected function registerFunctions()
    {
        if ( self::$hasStarted )
            return false;
        session_module_name( 'user' );
        $ini = eZINI::instance();
        if ( $ini->variable( 'Session', 'SessionNameHandler' ) == 'custom' )
        {
            $sessionName = $ini->variable( 'Session', 'SessionNamePrefix' );
            if ( $ini->variable( 'Session', 'SessionNamePerSiteAccess' ) == 'enabled' )
            {
                $access = $GLOBALS['eZCurrentAccess'];
                // use md5 to make sure name is only alphanumeric characters
                $sessionName .=  md5( $access['name'] );
            }
            session_name( $sessionName );
        }
        else
        {
            $sessionName = session_name();
        }

        // See if user has session, used to avoid reading from db if no session.
        // Allow session bye post params for use by flash, but use $_POST directly
        // to avoid session double start issues ( #014686 ) caused by eZHTTPTool
        if ( isset( $_POST[ $sessionName ] ) && isset( $_POST[ 'UserSessionHash' ] ) )
        {
            // First use session id from post params (for use in flash upload)  
            session_id( $_POST[ $sessionName ] );
            self::$hasSessionCookie = true;
            // allow verification of user hash if client is different ua then actual session client
            self::$userSessionHash = $_POST[ 'UserSessionHash' ];
        }
        else
        {
            // else check cookie as used by default
            self::$hasSessionCookie = isset( $_COOKIE[ $sessionName ] );
        }

        session_set_save_handler(
            array('eZSession', 'open'),
            array('eZSession', 'close'),
            array('eZSession', 'internalRead'),
            array('eZSession', 'internalWrite'),
            array('eZSession', 'destroy'),
            array('eZSession', 'garbageCollector')
            );
        return true;
    }

    /**
     * Starts the session and sets the timeout of the session cookie.
     * Multiple calls will be ignored unless you call {@link eZSession::stop()} first.
     * 
     * @param bool|int $cookieTimeout use this to set custom cookie timeout.
     * @return bool Returns true|false depending on if session was started.
     */
    static public function start( $cookieTimeout = false )
    {
        // Check if we are allowed to use sessions
        if ( isset( $GLOBALS['eZSiteBasics'] ) &&
             isset( $GLOBALS['eZSiteBasics']['session-required'] ) &&
             !$GLOBALS['eZSiteBasics']['session-required'] )
        {
            return false;
        }
        if ( self::$hasStarted )
        {
             return false;
        }
        $db = eZDB::instance();
        if ( !$db->isConnected() )
        {
            return false;
        }
      	self::registerFunctions();
        if ( $cookieTimeout == false )
        {
            $ini = eZINI::instance();
            $cookieTimeout = $ini->variable( 'Session', 'CookieTimeout' );
        }

        /* HACK SESSION PREMIERE SOUS DOMAINE 
        if ( is_numeric( $cookieTimeout ) )
        {
	        
	        session_set_cookie_params( (int)$cookieTimeout , "/", ".premiere.fr");
        	//session_set_cookie_params( (int)$cookieTimeout );
        }
		*/
 		session_set_cookie_params( (int)$cookieTimeout , "/", ".premiere.fr");
        
        session_start();
        return self::$hasStarted = true;
    }

    /**
     * Gets/generates the user hash for use in validating the session based on [Session]
     * SessionValidation* site.ini settings.
     * 
     * @return string Returns md5 hash based on parts of the user ip and agent string.
     */
    static public function getUserSessionHash()
    {
        if ( self::$userSessionHash === null )
        {
            $ini = eZINI::instance();
            $sessionValidationString = '';
            $sessionValidationIpParts = (int) $ini->variable( 'Session', 'SessionValidationIpParts' );
            if ( $sessionValidationIpParts && isset( $_SERVER['REMOTE_ADDR'] ) )
            {
                $sessionValidationString .= '-' . self::getIpPart( $_SERVER['REMOTE_ADDR'], $sessionValidationIpParts );
            }
            $sessionValidationForwardedIpParts = (int) $ini->variable( 'Session', 'SessionValidationForwardedIpParts' );
            if ( $sessionValidationForwardedIpParts && isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )
            {
                $sessionValidationString .= '-' . self::getIpPart( $_SERVER['HTTP_X_FORWARDED_FOR'], $sessionValidationForwardedIpParts );
            }
            $sessionValidationUAString = $ini->variable( 'Session', 'SessionValidationUseUA' ) === 'enabled';
            if ( $sessionValidationUAString && isset( $_SERVER['HTTP_USER_AGENT'] ) )
            {
                $sessionValidationString .= '-' . $_SERVER['HTTP_USER_AGENT'];
            }
            self::$userSessionHash = $sessionValidationString ? md5( $sessionValidationString ) : '';
        }
        return self::$userSessionHash;
    }

    /**
     * Gets part of a ipv4/ipv6 address, used internally by {@link eZSession::getUserSessionHash()} 
     * 
     * @access protected
     * @param string $ip IPv4 or IPv6 format
     * @param int $parts number from 0-4
     * @return string returns part of a ip imploded with '-' for use as a hash.
     */
    static protected function getIpPart( $ip, $parts = 2 )
    {
        $parts = $parts > 4 ? 4 : $parts;
        $ip = strpos( $ip, ':' ) === false ? explode( '.', $ip ) : explode( ':', $ip );
        return implode('-', array_slice( $ip, 0, $parts ) );
    }

    /**
     * Writes session data and stops the session, if not already stopped.
     * 
     * @return bool Returns true|false depending on if session was stopped.
     */
    static public function stop()
    {
        if ( !self::$hasStarted )
        {
             return false;
        }
        $db = eZDB::instance();
        if ( !$db->isConnected() )
        {
            return false;
        }
        session_write_close();
        self::$hasStarted = false;
        return true;
    }

    /**
     * Will make sure the user gets a new session ID while keepin the session data.
     * This is useful to call on logins, to avoid sessions theft from users.
     * NOTE: make sure you set new user id first using {@link eZSession::setUserID()} 
     * 
     * @param bool $updateUserSession set to false to not update session in db with new session id and user id.
     * @return bool Returns true|false depending on if session was regenerated.
     */
    static public function regenerate( $updateUserDBSession = true )
    {
        if ( !self::$hasStarted )
        {
             return false;
        }
        if ( !function_exists( 'session_regenerate_id' ) )
        {
            return false;
        }
        if ( headers_sent() )
        {
            if ( PHP_SAPI !== 'cli' )
                eZDebug::writeWarning( 'Could not regenerate session id, HTTP headers already sent.', 'eZSession::regenerate' );
            return false;
        }

        $oldSessionId = session_id();
        session_regenerate_id();

        // If user has session and $updateUserSession is true, then update user session data
        if ( $updateUserDBSession && self::$hasSessionCookie )
        {
            $db = eZDB::instance();
            if ( !$db->isConnected() )
            {
                return false;
            }
            $escOldKey = $db->escapeString( $oldSessionId );
            $escKey = $db->escapeString( session_id() );
            $escUserID = $db->escapeString( self::$userID );

            self::triggerCallback( 'regenerate_pre', array( $db, $escKey, $escOldKey, $escUserID ) );

			$memCache  = new lammcache(array('use_zlib' => true));
        	$odata=$memCache->get($oldSessionId);
        	$odata['session_key']=session_id();
        	$odata['user_id']=self::$userID;
        	$memCache->set($oData,session_id());
			self::triggerCallback( 'regenerate_post', array( $db, $escKey, $escOldKey, $escUserID ) );
        }
        return true;
    }

    /**
     * Removes the current session and resets session variables.
     * 
     * @return bool Returns true|false depending on if session was removed.
     */
    static public function remove()
    {
        if ( !self::$hasStarted )
        {
             return false;
        }
        $db = eZDB::instance();
        if ( !$db->isConnected() )
        {
            return false;
        }
        $_SESSION = array();
        session_destroy();
        self::$hasStarted = false;
        return true;
    }

    /**
     * Sets the current userID used by self::write on shutdown.
     * 
     * @param int $userID to use in {@link eZSession::write()}
     */
    static public function setUserID( $userID )
    {
        self::$userID = $userID;
    }

    /**
     * Gets the current user id.
     * 
     * @return int Returns user id stored by {@link eZSession::setUserID()}
     */
    static public function userID()
    {
        return self::$userID;
    }

    /**
     * Returns if user had session cookie at start of request or not.
     * 
     * @return bool|null returns null if session is not started yet.
     */
    static public function userHasSessionCookie()
    {
        return self::$hasSessionCookie;
    }

    /**
     * Returns if user session validated against stored data in db
     * or if it was invalidated during the current request.
     * 
     * @return bool|null returns null if user is not validated yet (for instance a new session).
     */
    static public function userSessionIsValid()
    {
        // force a session read if session has started but not yet used
        if ( self::$userSessionIsValid === null &&
             self::$hasSessionCookie === true )
        {
            $tempSession = $_SESSION;
        }
        return self::$userSessionIsValid;
    }

    /**
     * Adds a callback function, to be triggered by {@link eZSession::triggerCallback()}
     * when a certan session event occurs.
     * Use: eZSession::addCallback('gc_pre', myCustomGarabageFunction );
     * 
     * @param string $type cleanup, gc, destroy, insert and update, pre and post types.
     * @param handler $callback a function to call.
     */
    static public function addCallback( $type, $callback )
    {
        if ( !isset( self::$callbackFunctions[$type] ) )
        {
            self::$callbackFunctions[$type] = array();
        }
        self::$callbackFunctions[$type][] = $callback;
    }

    /**
     * Triggers callback functions by type, registrated by {@link eZSession::addCallback()}
     * Use: eZSession::triggerCallback('gc_pre', array( $db, $time ) );
     * 
     * @param string $type cleanup, gc, destroy, insert and update, pre and post types.
     * @param array $params list of parameters to pass to the callback function.
     */
    static public function triggerCallback( $type, $params )
    {
        if ( isset( self::$callbackFunctions[$type] ) )
        {
            foreach( self::$callbackFunctions[$type] as $callback )
            {
                call_user_func_array( $callback, $params );
            }
            return true;
        }
        return false;
    }
}

// DEPRECATED (For BC use only)
function eZSessionStart()
{
    eZSession::start();
}

?>
