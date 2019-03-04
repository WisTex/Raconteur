<?php

namespace Zotlabs\Daemon;

/*
 * Daemon to remove 'item' resources in the background from a removed connection
 */

class Delxitems {

	static public function run($argc,$argv){

		cli_startup();

		if($argc != 3) {
			killme();

		remove_abook_items($argv[1],$argv[2]);

		killme();
	}
}
