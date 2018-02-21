<?php

namespace Zotlabs\Update;

class _1194 {
function run() {
	$r = q("select id, resource_id from item where resource_type = 'nwiki'"); 
	if($r) {
		foreach($r as $rv) {
			$mimetype = get_iconfig($rv['id'],'wiki','mimeType');
			q("update item set mimetype = '%s' where resource_type = 'nwikipage' and resource_id = '%s'",
				dbesc($mimetype),
				dbesc($rv['resource_id'])
			);
		}
	}

	return UPDATE_SUCCESS;
}


}