<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Zotfinger;
use Zotlabs\Web\HTTPSig;

class Zot_probe extends \Zotlabs\Web\Controller {

	function get() {
	
		$o .= '<h3>Zot6 Probe Diagnostic</h3>';
	
		$o .= '<form action="zot_probe" method="get">';
		$o .= 'Lookup URI: <input type="text" style="width: 250px;" name="addr" value="' . $_GET['addr'] .'" /><br>';
		$o .= '<input type="submit" name="submit" value="Submit" /></form>'; 
	
		$o .= '<br /><br />';
	
		if(x($_GET,'addr')) {
			$addr = $_GET['addr'];


			$x = Zotfinger::exec($addr);
			
			$o .= '<pre>' . htmlspecialchars(print_array($x)) . '</pre>';

			$headers = 'Accept: application/x-zot+json, application/jrd+json, application/json';

			$redirects = 0;
		    $x = z_fetch_url($addr,true,$redirects, [ 'headers' => [ $headers ]]);

	    	if($x['success']) {

				$o .= '<pre>' . htmlspecialchars($x['header']) . '</pre>' . EOL;

				$o .= 'verify returns: ' . str_replace("\n",EOL,print_r(HTTPSig::verify($x),true)) . EOL;

				$o .= '<pre>' . htmlspecialchars(json_encode(json_decode($x['body']),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) . '</pre>' . EOL;

			}				

		}
		return $o;
	}
	
}
