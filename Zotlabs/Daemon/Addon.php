<?php

namespace Zotlabs\Daemon;


class Addon {

	static public function run($argc,$argv) {

		call_hooks('daemon_addon',$argv);

	}
}
