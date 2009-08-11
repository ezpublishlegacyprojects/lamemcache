<?php
include_once( 'kernel/common/template.php' );
include_once('extension/lamemcache/classes/common/lamemcachestat.php');
 
$oHTTP      = eZHTTPTool::instance();
$Module     = $Params['Module'];
$tpl        = templateInit();

global $PHP_SELF;
$_GET['op'] = !isset($_GET['op'])? '1':$_GET['op'];
$PHP_SELF   = isset($_SERVER['PHP_SELF']) ? htmlentities(strip_tags($_SERVER['PHP_SELF'],'')) : '';
$PHP_SELF   = $PHP_SELF. '?';
$time       = time();

foreach($_GET as $key => $g){
    $_GET[$key] = htmlentities($g);
}

$oLaMemCacheStats = new lammcachestat();
$MEMCACHE_SERVERS = $oLaMemCacheStats->get_memecache_servers();

// singleout
// when singleout is set, it only gives details for that server.
if (isset($_GET['singleout']) && $_GET['singleout']>=0 && $_GET['singleout'] <count($MEMCACHE_SERVERS)){
    $MEMCACHE_SERVERS = array($MEMCACHE_SERVERS[$_GET['singleout']]);
}

switch ($_GET['op']) {

    case 1: 
    	// host stats
    	$phpversion          = phpversion();
        $memcacheStats       = $oLaMemCacheStats->getMemcacheStats();
        $memcacheStatsSingle = $oLaMemCacheStats->getMemcacheStats(false);

        $mem_size    = $memcacheStats['limit_maxbytes'];
    	$mem_used    = $memcacheStats['bytes'];
	    $mem_avail   = $mem_size - $mem_used;
	    $startTime   = time() - array_sum($memcacheStats['uptime']);

        $curr_items  = $memcacheStats['curr_items'];
        $total_items = $memcacheStats['total_items'];
        $hits        = ($memcacheStats['get_hits']==0) ? 1:$memcacheStats['get_hits'];
        $misses      = ($memcacheStats['get_misses']==0) ? 1:$memcacheStats['get_misses'];
        $sets        = $memcacheStats['cmd_set'];

       	$req_rate    = sprintf("%.2f",($hits+$misses)/($time-$startTime));
	    $hit_rate    = sprintf("%.2f",($hits)/($time-$startTime));
	    $miss_rate   = sprintf("%.2f",($misses)/($time-$startTime));
	    $set_rate    = sprintf("%.2f",($sets)/($time-$startTime));

	   	$sResult = '<div class="info div1"><h2>General Cache Information</h2>
						<table cellspacing=0><tbody>
						<tr class=tr-1><td class=td-0>PHP Version</td><td>' . $phpversion . '</td></tr>
							<tr class=tr-0><td class=td-0>Memcached Host" ' . ((count($MEMCACHE_SERVERS)>1) ? 's':''). '</td><td>';
		
		$i = 0;
		if (!isset($_GET['singleout']) && count($MEMCACHE_SERVERS)>1){
    		foreach($MEMCACHE_SERVERS as $server){
    		      $sResult .= ($i+1).'. <a href="'.$PHP_SELF.'&singleout='.$i++.'">' . $server . '</a><br/>';
    		}
		} else{
		     $sResult .=  '1.'.$MEMCACHE_SERVERS[0];
		}
		if (isset($_GET['singleout'])){
		     $sResult .=  '<a href="'.$PHP_SELF.'">(all servers)</a><br/>';
		}
		$sResult .= "</td></tr>\n";
		$sResult .= "<tr class=tr-1><td class=td-0>Total Memcache Cache</td><td>" . $oLaMemCacheStats->bsize($memcacheStats['limit_maxbytes']) . "</td></tr>\n";
		$sResult .= "</tbody></table></div><div class=\"info div1\"><h2>Memcache Server Information</h2>";

        foreach($MEMCACHE_SERVERS as $server){
            
			$sResult .= '<table cellspacing=0><tbody>';
            $sResult .= '<tr class=tr-1><td class=td-1>' . $server . '</td><td><a href="' . $PHP_SELF . '&server='. array_search($server, $MEMCACHE_SERVERS) .'&op=6">[<b>Flush this server</b>]</a></td></tr>';
    		$sResult .= '<tr class=tr-0><td class=td-0>Start Time</td><td>' . date(MEMCACHE_DATE_FORMAT, $memcacheStatsSingle[$server]['STAT']['time'] - $memcacheStatsSingle[$server]['STAT']['uptime']) . '</td></tr>';
    		$sResult .= '<tr class=tr-1><td class=td-0>Uptime</td><td>' . $oLaMemCacheStats->duration($memcacheStatsSingle[$server]['STAT']['time'] - $memcacheStatsSingle[$server]['STAT']['uptime']) . '</td></tr>';
    		$sResult .= '<tr class=tr-0><td class=td-0>Memcached Server Version</td><td>' . $memcacheStatsSingle[$server]['STAT']['version'] .'</td></tr>';
    		$sResult .= '<tr class=tr-1><td class=td-0>Used Cache Size</td><td>' . $oLaMemCacheStats->bsize($memcacheStatsSingle[$server]['STAT']['bytes']) . '</td></tr>';
    		$sResult .= '<tr class=tr-0><td class=td-0>Total Cache Size</td><td>' . $oLaMemCacheStats->bsize($memcacheStatsSingle[$server]['STAT']['limit_maxbytes']) .  '</td></tr>';
    		$sResult .= '</tbody></table>';
	   	}
    
		$sResult .= '</div><div class="graph div3"><h2>Host Status Diagrams</h2><table cellspacing=0><tbody>';

		$size     = 'width=' . (MEMCACHE_GRAPH_SIZE+50) . ' height=' . (MEMCACHE_GRAPH_SIZE+10);
		$sResult .= '<tr><td class=td-0>Cache Usage</td><td class=td-1>Hits &amp; Misses</td></tr>';

	    if ($oLaMemCacheStats->graphics_avail()) { 
			$sResult .= '<tr>'.
			        "<td class=td-0><img alt=\"\" $size src=\"/extension/lamemcache/modules/lamemcache/memcache.php?s=". $oLaMemCacheStats->getStringMMCache() ."&IMG=1&".(isset($_GET['singleout'])? 'singleout='.$_GET['singleout'].'&':''). $time . "\"></td>".
			        "<td class=td-1><img alt=\"\" $size src=\"/extension/lamemcache/modules/lamemcache/memcache.php?s=". $oLaMemCacheStats->getStringMMCache() ."&IMG=2&".(isset($_GET['singleout'])? 'singleout='.$_GET['singleout'].'&':''). $time . "\"></td></tr>\n";

		}
		
		$sResult .= '<tr><td class=td-0><span class="green box">&nbsp;</span>Free: ' . $oLaMemCacheStats->bsize($mem_avail).sprintf(" (%.1f%%)", $mem_avail * 100 / $mem_size) . "</td>\n" .
					'<td class=td-1><span class="green box">&nbsp;</span>Hits: ' . $hits.sprintf(" (%.1f%%)",$hits * 100 / ($hits+$misses)) . "</td>\n" . 
					'</tr><tr>' .
					'<td class=td-0><span class="red box">&nbsp;</span>Used: ' . $oLaMemCacheStats->bsize($mem_used ).sprintf(" (%.1f%%)", $mem_used * 100 / $mem_size). "</td>\n" .
					'<td class=td-1><span class="red box">&nbsp;</span>Misses: ' . $misses.sprintf(" (%.1f%%)", $misses * 100 /($hits+$misses)) . "</td>\n";
		
		$sResult .= '</tr></tbody></table><br/>
		<div class="info"><h2>Cache Information</h2>
		<table cellspacing=0><tbody>
		<tr class=tr-0><td class=td-0>Current Items(total)</td><td>'. $curr_items . ' (' . $total_items. ') </td></tr>
		<tr class=tr-1><td class=td-0>Hits</td><td>' . $hits . '</td></tr>
		<tr class=tr-0><td class=td-0>Misses</td><td>' . $misses. '</td></tr>
		<tr class=tr-1><td class=td-0>Request Rate (hits, misses)</td><td>' . $req_rate. ' cache requests/second</td></tr>
		<tr class=tr-0><td class=td-0>Hit Rate</td><td>' . $hit_rate . ' cache requests/second</td></tr>
		<tr class=tr-1><td class=td-0>Miss Rate</td><td>' . $miss_rate . ' cache requests/second</td></tr>
		<tr class=tr-0><td class=td-0>Set Rate</td><td>' . $set_rate . ' cache requests/second</td></tr>
		</tbody></table>
		</div>';
    break;

    case 2: 
    	// variables
		$m=0;
		$cacheItems = $oLaMemCacheStats->getCacheItems();
		$items      = $cacheItems['items'];
		$totals     = $cacheItems['counts'];
		$maxDump    = MEMCACHE_MAX_ITEM_DUMP;
		$sResult    = ''; 
		
		foreach($items as $server => $entries) {

    		$sResult .= '
			<div class="info"><table cellspacing=0><tbody>
			<tr><th colspan="2">' . $server . '</th></tr>
			<tr><th>Slab Id</th><th>Info</th></tr>';


			foreach($entries as $slabId => $slab) {
			    
				$dumpUrl = $PHP_SELF . '&op=2&server=' . (array_search($server,$MEMCACHE_SERVERS)) . '&dumpslab=' . $slabId;
				$sResult .= "<tr class=tr-$m>" . 
					        "<td class=td-0><center>" .
							'<a href="' . $dumpUrl .'">' . $slabId . '</a>' . "</center></td>" .
							"<td class=td-last><b>Item count:</b> " . $slab['number'] . '<br/><b>Age:</b>' . $oLaMemCacheStats->duration($time-$slab['age']) .'<br/> <b>Evicted:</b>' . ((isset($slab['evicted']) && $slab['evicted']==1)? 'Yes':'No');
				
				if ((isset($_GET['dumpslab']) && $_GET['dumpslab'] == $slabId) &&  (isset($_GET['server']) && $_GET['server'] == array_search($server, $MEMCACHE_SERVERS))){
					    $sResult .= "<br/><b>Items: item</b><br/>";
					    $items = $oLaMemCacheStats->dumpCacheSlab($server,$slabId,$slab['number']);
                        // maybe someone likes to do a pagination here :)
					    $i=1;
                        foreach($items['ITEM'] as $itemKey => $itemInfo){
                            
							$itemInfo = trim($itemInfo,'[ ]');
                           	$sResult .=  '<a href="' . $PHP_SELF . '&op=4&server=' . (array_search($server, $MEMCACHE_SERVERS)) . '&key=' . base64_encode($itemKey). '">' . $itemKey . '</a>';
                            if ($i++ % 10 == 0) {
                                $sResult .= '<br/>';
                            } elseif ($i!=$slab['number']+1){
                                $sResult .= ',';
                            }
                        }
					}
				$sResult .= "</td></tr>";
				$m=1-$m;
			}
			$sResult .= '</tbody></table></div><hr/>';
		}
    break;

    case 4: 
    	//item dump
		$sResult = '';
		if (!isset($_GET['key']) || !isset($_GET['server'])){
            $sResult .= "No key set!";
        }
        // I'm not doing anything to check the validity of the key string.
        // probably an exploit can be written to delete all the files in key=base64_encode("\n\r delete all").
        // somebody has to do a fix to this.
        $theKey = htmlentities(base64_decode($_GET['key']));

        $theserver  = $MEMCACHE_SERVERS[(int)$_GET['server']];
        list($h,$p) = explode(':',$theserver);
        $r          = $oLaMemCacheStats->sendMemcacheCommand($h, $p , 'get '.$theKey);
       	$sResult .= '<div class="info"><table cellspacing=0><tbody>
					<tr><th>Server<th>Key</th><th>Value</th><th>Delete</th></tr>';
					
        $sResult .= "<tr><td class=td-0>" . $theserver . "</td><td class=td-0>" . $theKey . 
             		" <br/>flag:" . $r['VALUE'][$theKey]['stat']['flag'] . 
         			" <br/>Size:" . $oLaMemCacheStats->bsize($r['VALUE'][$theKey]['stat']['size']) . 
             		"</td><td>" . chunk_split($r['VALUE'][$theKey]['value'],40) . "</td>" . 
             		'<td><a href="' . $PHP_SELF . '&op=5&server=' . (int)$_GET['server'] . '&key=' . base64_encode($theKey) . "\">Delete</a></td></tr>
					</tbody></table>
					</div><hr/>
					";
    	break;
    case 5: 
    	// item delete
		$sResult = '';
    	if (!isset($_GET['key']) || !isset($_GET['server'])){
			 $sResult .= "No key set!";
			break;
        }
        $theKey     = htmlentities(base64_decode($_GET['key']));
		$theserver  = $MEMCACHE_SERVERS[(int)$_GET['server']];
		list($h,$p) = explode(':',$theserver);
        $r          = $oLaMemCacheStats->sendMemcacheCommand($h, $p, 'delete ' . $theKey);
        $sResult .=  'Deleting ' . $theKey . ':' . $r;
	break;
    
   case 6: // flush server
        $theserver = $MEMCACHE_SERVERS[(int)$_GET['server']];
        $r = $oLaMemCacheStats->flushServer($theserver);
        $sResult .=  'Flush  ' . $theserver . ":" . $r;
   break;
}

$sMenu = $oLaMemCacheStats->getMenu();
$tpl->setVariable( 'sMenu', $sMenu);
$tpl->setVariable( 'sResult', $sResult);

$Result = array();
$Result['content'] = $tpl->fetch( "design:lamemcache/stats_server.tpl" );
$Result['pagelayout']  = 'pagelayout_statserver.tpl';

$Result['path'] = array( array( 'url' => false,
                                'text' => "Stats Memcache Server" ) );
?>
