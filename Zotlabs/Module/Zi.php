<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libzot;


class Zi extends Controller {

	function get() {

		if (argc() < 2) {
			killme();
		}

		$channel = channelx_by_nick(argv(1));
		if (! $channel) {
			http_status_exit(404, 'Not found');
		}

		return str_replace("\n","<br>", print_r( Libzot::zotinfo([ 'guid_hash' => $channel['channel_hash'] ]), true));
	}
}
