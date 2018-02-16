<?php

namespace Zotlabs\Update;

class _1181 {
function run() {
	if(\Zotlabs\Lib\System::get_server_role() == 'pro') {
		q("update account set account_level = 5 where true");
	}
	return UPDATE_SUCCESS;
}


}