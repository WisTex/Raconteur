<?php

namespace Zotlabs\Update;

class _1020 {
function run() {
	$r = q("alter table photo drop `contact-id`, drop guid, drop index `resource-id`, add index ( `resource_id` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}