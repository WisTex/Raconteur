<?php

namespace Zotlabs\Update;

use Zotlabs\Lib\Config;

class _1254 {

	function run() {
		q("UPDATE channel SET channel_notifyflags = channel_notifyflags + %d WHERE true",
			intval(NOTIFY_RESHARE)
		);
		return UPDATE_SUCCESS;
	}

	function verify() {
		return true;
	}

}
