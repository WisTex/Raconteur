<?php

namespace Zotlabs\Module;

use Zotlabs\Web\Controller;
use Zotlabs\Lib\Config;

class Sudo extends Controller {

	function init() {

		if ((argc() < 2) || (! intval(Config::Get('system','allow_sudo')))) { {
			http_status_exit(404,'Not found');
		}

		if (! is_site_admin()) {
			http_status_exit(403,'Permission denied');
		}
	
		$c = channelx_by_nick(argv(1));
		if ($c) {
			$tmp = $_SESSION;
			$_SESSION['delegate_push']	  = $tmp;
			$_SESSION['delegate_channel'] = $c['channel_id'];
			$_SESSION['delegate']		  = get_observer_hash();
			$_SESSION['account_id']	      = intval($c['channel_account_id']);

			change_channel($c['channel_id']);
			goaway(z_root());			
		}
	}

}