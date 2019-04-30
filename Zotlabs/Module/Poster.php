<?php

namespace Zotlabs\Module;

use Zotlabs\Web\Controller;

require_once('include/security.php');

class Poster extends Controller {

	function init() {

		$nick = argv(1);
		$hash = argv(2);

		if(! ($nick && $hash)) {
			return;
		}

		$u = channelx_by_nick($nick);

		$sql_extra = permissions_sql(intval($u['channel_id']));

		$r = q("select content from attach where hash = '%s' and uid = %d and os_storage = 1 $sql_extra limit 1",
			dbesc($hash),
			intval($u['channel_id'])
		);
		if($r) {
			$path = dbunescbin($r[0]['content']);
			if($path && @file_exists($path . '.thumb')) {
				header('Content-Type: image/jpeg');
				echo file_get_contents($path . '.thumb');
				killme();
			}
		}
	}
}
