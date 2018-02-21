<?php

namespace Zotlabs\Update;

class _1088 {
function run() {
	$r = q("ALTER TABLE `obj` ADD `allow_cid` MEDIUMTEXT NOT NULL DEFAULT '',
ADD `allow_gid` MEDIUMTEXT NOT NULL DEFAULT '',
ADD `deny_cid` MEDIUMTEXT NOT NULL DEFAULT '',
ADD `deny_gid` MEDIUMTEXT NOT NULL DEFAULT ''");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}