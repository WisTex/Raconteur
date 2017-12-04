<?php
namespace Zotlabs\Module;


class Update extends \Zotlabs\Web\Controller {

	function get() {
	
		$profile_uid = intval($_GET['p']);

		if(argc() < 2) {
			killme();
		}

		// These modules don't have a completely working liveUpdate implementation currently

		if(in_array(strtolower(argv(1)),['articles','cards']))
			killme();

		$module = "\\Zotlabs\\Module\\" . ucfirst(argv(1));		
		$load = (((argc() > 2) && (argv(2) == 'load')) ? 1 : 0);

		$mod = new $module;

		header("Content-type: text/html");

		echo "<!DOCTYPE html><html><body><section>\r\n";
		echo $mod->get($profile_uid, $load);
		echo "</section></body></html>\r\n";

		killme();
	
	}
}
