<?php
namespace Zotlabs\Module;


class Update extends \Zotlabs\Web\Controller {

	function get() {
	
		$profile_uid = intval($_GET['p']);

		// it's probably safe to do this for all modules and not just a limited subset,
		// but it needs to be verified.

		if((! $profile_uid) && in_array(argv(1),['display','search','pubstream','home']))
			$profile_uid = (-1);

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

		\App::$argv = [ argv(1) ];
		\App::$argc = 1;

		echo "<!DOCTYPE html><html><body><section>\r\n";
		echo $mod->get($profile_uid, $load);
		echo "</section></body></html>\r\n";

		killme();
	
	}
}
