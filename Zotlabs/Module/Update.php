<?php
namespace Zotlabs\Module;

/*
 * Update
 * Called by main.js to load or fetch updates of content for various conversation streams/timelines.
 * It invokes the appropriate module's get() function to return an array of HTML rendered conversations/posts.
 * Some state is passed to the module controller prior to calling module->get() to indicate that
 * the existing content should either be replaced or appended to.
 * The current modules that support this manner of update are
 *  channel, hq, stream, display, search, and pubstream.
 * The state we are passing is the profile_uid (passed to us as $_GET['p']), and argv(2) === 'load'
 * to indicate that we're replacing original content.
 *
 * module->profile_uid - tell the module who owns this data
 * module->loading - tell the module to replace existing  content
 * module->updating - always true to tell the module that this is a js initiated request
 *
 * Inside main.js we also append all of the relevant content query params which were initially used on that
 * page via buildCmd so those are passed to this instance of update and are therefore available from the module.
 */

use App;
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

		// Set the state flags of the relevant module (only conversational
		// modules support state flags
		
		if (isset($mod->profile_uid)) {
			$mod->profile_uid = $profile_uid;
		}
		if (isset($mod->updating)) {
			$mod->updating = 1;
		}
		if (isset($mod->loading) && $load) {
			$mod->loading = 1;
		}

		header("Content-type: text/html");

		// Modify the argument parameters to match what the new controller
		// expects. They are currently set to what this controller expects. 

		App::$argv = [ argv(1) ];
		App::$argc = 1;

		echo "<!DOCTYPE html><html><body><section>\r\n";
		echo $mod->get();
		echo "</section></body></html>\r\n";

		killme();
	}
}
