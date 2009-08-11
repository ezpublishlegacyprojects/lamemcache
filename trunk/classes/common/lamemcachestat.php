<?php
define('MEMCACHE_DATE_FORMAT','Y/m/d H:i:s');
define('MEMCACHE_GRAPH_SIZE',200);
define('MEMCACHE_MAX_ITEM_DUMP',50);


class lammcachestat
{
	var $_VERSION          = '$Id: memcache.php,v 1.1.2.3 2008/08/28 18:07:54 mikl Exp $';
	var $_MEMCACHE_SERVERS = array();
	var $_ini              = null;
	
	function lammcachestat() {
		
		include_once( 'lib/ezutils/classes/ezini.php' );
		$this->_ini = eZINI::instance('lamemcache.ini');
		$aServers = $this->_ini->variable( 'lamemcacheSettings', 'servers' );
		
		$aIndex = array('host', 'port');
		if (is_array($aServers) && count($aServers)) {
			foreach ($aServers as $sServer) {
				$aServer = array();
				$aInfoServer = array();
				$aServer = explode(';', $sServer);
				if (is_array($aServer) && count($aServer)) {
					$this->_MEMCACHE_SERVERS[] = $aServer[0] . ':' . $aServer[1];
				}
			}
		}
		
	}
	
	function get_memecache_servers() {
		
		return $this->_MEMCACHE_SERVERS;
	}
	
	function getStringMMCache() {
		
		$sReturn = '';
		if (is_array($this->_MEMCACHE_SERVERS) && count($this->_MEMCACHE_SERVERS)) {
			foreach($this->_MEMCACHE_SERVERS as $sServer) {
				$sReturn .= $sServer . ';';
			}
		}
		return trim($sReturn, ';');
	}
	
	function sendMemcacheCommands($command){
		$result = array();
	
		foreach($this->_MEMCACHE_SERVERS as $server){
			$strs = explode(':',$server);
			$host = $strs[0];
			$port = $strs[1];
			$result[$server] = $this->sendMemcacheCommand($host,$port,$command);
		}
		return $result;
	}
	
	function sendMemcacheCommand($server, $port, $command){
		$error = 0;
		$errstr = '';
		$s = @fsockopen($server,$port, $error, $errstr, 1);
		if (!$s){
			//die("Cant connect to:".$server.':'.$port);
			return false;
		}
	
		fwrite($s, $command."\r\n");
	
		$buf='';
		while ((!feof($s))) {
			$buf .= fgets($s, 256);
			if (strpos($buf,"END\r\n")!==false){ // stat says end
			    break;
			}
			if (strpos($buf,"DELETED\r\n")!==false || strpos($buf,"NOT_FOUND\r\n")!==false){ // delete says these
			    break;
			}
			if (strpos($buf,"OK\r\n")!==false){ // flush_all says ok
			    break;
			}
		}
	    fclose($s);
	    return $this->parseMemcacheResults($buf);
	}
	
	function parseMemcacheResults($str){
    
		$res = array();
		$lines = explode("\r\n",$str);
		$cnt = count($lines);
		for($i=0; $i< $cnt; $i++){
		    $line = $lines[$i];
			$l = explode(' ',$line,3);
			if (count($l)==3){
				$res[$l[0]][$l[1]]=$l[2];
				if ($l[0]=='VALUE'){ // next line is the value
				    $res[$l[0]][$l[1]] = array();
				    list ($flag,$size)=explode(' ',$l[2]);
				    $res[$l[0]][$l[1]]['stat']=array('flag'=>$flag,'size'=>$size);
				    $res[$l[0]][$l[1]]['value']=$lines[++$i];
				}
			}elseif($line=='DELETED' || $line=='NOT_FOUND' || $line=='OK'){
			    return $line;
			}
		}
		return $res;
	
	}
	
	function dumpCacheSlab($server,$slabId,$limit){
	    list($host,$port) = explode(':',$server);
	    $resp = $this->sendMemcacheCommand($host,$port,'stats cachedump '.$slabId.' '.$limit);
	
	   	return $resp;
	}
	
	function flushServer($server){
	    list($host,$port) = explode(':',$server);
	    $resp = $this->sendMemcacheCommand($host,$port,'flush_all');
	    return $resp;
	}
	
	function getCacheItems(){
		 $items = $this->sendMemcacheCommands('stats items');
		 $serverItems = array();
		 $totalItems = array();
		 foreach ($items as $server=>$itemlist){
		    $serverItems[$server] = array();
		    $totalItems[$server]=0;
		    if (!isset($itemlist['STAT'])){
		        continue;
		    }
		
		    $iteminfo = $itemlist['STAT'];
		
		    foreach($iteminfo as $keyinfo=>$value){
		        if (preg_match('/items\:(\d+?)\:(.+?)$/',$keyinfo,$matches)){
		            $serverItems[$server][$matches[1]][$matches[2]] = $value;
		            if ($matches[2]=='number'){
		                $totalItems[$server] +=$value;
		            }
		        }
		    }
		 }
		 return array('items'=>$serverItems,'counts'=>$totalItems);
	}
	
	function getMemcacheStats($total=true){
		$resp = $this->sendMemcacheCommands('stats');
		if ($total){
			$res = array();
			foreach($resp as $server=>$r){
				foreach($r['STAT'] as $key=>$row){
					if (!isset($res[$key])){
						$res[$key]=null;
					}
					switch ($key){
						case 'pid':
							$res['pid'][$server]=$row;
							break;
						case 'uptime':
							$res['uptime'][$server]=$row;
							break;
						case 'time':
							$res['time'][$server]=$row;
							break;
						case 'version':
							$res['version'][$server]=$row;
							break;
						case 'pointer_size':
							$res['pointer_size'][$server]=$row;
							break;
						case 'rusage_user':
							$res['rusage_user'][$server]=$row;
							break;
						case 'rusage_system':
							$res['rusage_system'][$server]=$row;
							break;
						case 'curr_items':
							$res['curr_items']+=$row;
							break;
						case 'total_items':
							$res['total_items']+=$row;
							break;
						case 'bytes':
							$res['bytes']+=$row;
							break;
						case 'curr_connections':
							$res['curr_connections']+=$row;
							break;
						case 'total_connections':
							$res['total_connections']+=$row;
							break;
						case 'connection_structures':
							$res['connection_structures']+=$row;
							break;
						case 'cmd_get':
							$res['cmd_get']+=$row;
							break;
						case 'cmd_set':
							$res['cmd_set']+=$row;
							break;
						case 'get_hits':
							$res['get_hits']+=$row;
							break;
						case 'get_misses':
							$res['get_misses']+=$row;
							break;
						case 'evictions':
							$res['evictions']+=$row;
							break;
						case 'bytes_read':
							$res['bytes_read']+=$row;
							break;
						case 'bytes_written':
							$res['bytes_written']+=$row;
							break;
						case 'limit_maxbytes':
							$res['limit_maxbytes']+=$row;
							break;
						case 'threads':
							$res['rusage_system'][$server]=$row;
							break;
					}
				}
			}
			return $res;
		}
		return $resp;
	}
	
	function duration($ts) {
	    global $time;
	    $years = (int)((($time - $ts)/(7*86400))/52.177457);
	    $rem = (int)(($time-$ts)-($years * 52.177457 * 7 * 86400));
	    $weeks = (int)(($rem)/(7*86400));
	    $days = (int)(($rem)/86400) - $weeks*7;
	    $hours = (int)(($rem)/3600) - $days*24 - $weeks*7*24;
	    $mins = (int)(($rem)/60) - $hours*60 - $days*24*60 - $weeks*7*24*60;
	    $str = '';
	    if($years==1) $str .= "$years year, ";
	    if($years>1) $str .= "$years years, ";
	    if($weeks==1) $str .= "$weeks week, ";
	    if($weeks>1) $str .= "$weeks weeks, ";
	    if($days==1) $str .= "$days day,";
	    if($days>1) $str .= "$days days,";
	    if($hours == 1) $str .= " $hours hour and";
	    if($hours>1) $str .= " $hours hours and";
	    if($mins == 1) $str .= " 1 minute";
	    else $str .= " $mins minutes";
	    return $str;
	}
	
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
	
	// create menu entry
	function menu_entry($ob,$title) {
		global $PHP_SELF;
		if ($ob==$_GET['op']){
		    return "<li><a class=\"child_active\" href=\"$PHP_SELF&op=$ob\">$title</a></li>";
		}
		return "<li><a class=\"active\" href=\"$PHP_SELF&op=$ob\">$title</a></li>";
	}
	
	function getMenu(){
	    
		global $PHP_SELF;
		
		$sHtml = '';
		$sHtml .= "<ol class=menu>";
		if ($_GET['op']!=4){
			$sHtml .= '<li><a href="' . $PHP_SELF . '&op=' . $_GET['op'] . '">Refresh Data</a></li>';
		} else {
			$sHtml .= '<li><a href="' . $PHP_SELF . '&op=2}">Back</a></li>';
		}
		
		$sHtml .= $this->menu_entry(1,'View Host Stats');
		$sHtml .= $this->menu_entry(2,'Variables');
		
		$sHtml .= '</ol>';
		return $sHtml;
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
}
?>
