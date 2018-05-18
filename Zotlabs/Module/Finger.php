<?php
namespace Zotlabs\Module;




class Finger extends \Zotlabs\Web\Controller {

	function get() {
	
	
		$o .= '<h3>Webfinger Diagnostic</h3>';
	
		$o .= '<form action="finger" method="get">';
		$o .= 'Lookup address: <input type="text" style="width: 250px;" name="addr" value="' . $_GET['addr'] .'" />';
		$o .= '<input type="submit" name="submit" value="Submit" /></form>'; 
	
		$o .= '<br /><br />';
		
		if(x($_GET,'addr')) {
			$addr = trim($_GET['addr']);

			$res = \Zotlabs\Lib\Webfinger::exec($addr);
	
		
			$o .= '<pre>';
			$o .= str_replace("\n",'<br />',print_r($res,true));
			$o .= '</pre>';
		}
		return $o;
	}
	
}
