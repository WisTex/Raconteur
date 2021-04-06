<?php
namespace Zotlabs\Module;

/*
 * Update
 * Performs AJAX liveUpdate of conversational content by calling the appropriate
 * controller (passed as argv(1)) and passing the profile_uid (passed as $_GET['p'])
 * and load flag (argv(2) === 'load') to the get() function of the controller.
 * Conversational controllers have been written to expect this input. Other controllers
 * have not.
 */
 
use Zotlabs\Web\Controller;

class Update extends Controller {

	function get() {

		$profile_uid = intval($_GET['p']);

		// Change a profile_uid of 0 (not logged in) to (-1) for selected controllers
		// as they only respond to liveUpdate with non-zero values
		
		if ((! $profile_uid) && in_array(argv(1),[ 'display', 'search', 'pubstream', 'home' ])) {
			$profile_uid = (-1);
		}

		if (argc() < 2) {
			killme();
		}

		// These modules don't have a completely working liveUpdate implementation currently

		if (in_array(strtolower(argv(1)),[ 'articles', 'cards' ])) {
			killme();
		}

		$module = "\\Zotlabs\\Module\\" . ucfirst(argv(1));		
		$load = (((argc() > 2) && (argv(2) == 'load')) ? 1 : 0);

		$mod = new $module;

		header("Content-type: text/html");

		// Modify the argument parameters to match what the new controller
		// expects. They are currently set to what this controller expects. 

		\App::$argv = [ argv(1) ];
		\App::$argc = 1;

		echo "<!DOCTYPE html><html><body><section>\r\n";
		echo $mod->get($profile_uid, $load);
		echo "</section></body></html>\r\n";

		killme();
	}
}
