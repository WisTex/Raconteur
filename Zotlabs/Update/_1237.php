<?php

namespace Zotlabs\Update;

class _1237 {

	function run() {
		q("update app set app_url = '%s' where app_url = '%s' ",
			dbesc(z_root() . '/stream'),
			dbesc(z_root() . '/network')
		);
		q("update app set app_url = '%s' where app_url = '%s' ",
			dbesc('$baseurl/stream'),
			dbesc('$baseurl/network')
		);

		return UPDATE_SUCCESS;
	}

	function verify() {
		return true;
	}
}
