<?php
namespace Zotlabs\Module;

use Zotlabs\Lib\Libzot;

class Probe extends \Zotlabs\Web\Controller {

	function get() {

		nav_set_selected('Remote Diagnostics');

		$o .= '<h3>Probe Diagnostic</h3>';
	
		$o .= '<form action="probe" method="get">';
		$o .= 'Lookup address: <input type="text" style="width: 250px;" name="addr" value="' . $_GET['addr'] .'" />';
		$o .= '<input type="submit" name="submit" value="Submit" /></form>'; 
	
		$o .= '<br /><br />';
	
		if(x($_GET,'addr')) {
			$channel = \App::get_channel();
			$addr = trim($_GET['addr']);
			$do_import = ((intval($_GET['import']) && is_site_admin()) ? true : false);
			
			$j = \Zotlabs\Lib\Zotfinger::exec($addr,$channel);

			$o .= '<pre>';

			if($do_import && $j)
				$x = Libzot::import_xchan($j);
			$o .= str_replace("\n",'<br />',print_r($j,true));
			$o .= '</pre>';
		}
		return $o;
	}
	
}
