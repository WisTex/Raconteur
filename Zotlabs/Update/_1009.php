<?php

namespace Zotlabs\Update;

class _1009 {
function run() {
	$r = q("ALTER TABLE `xprof` ADD `xprof_keywords` TEXT NOT NULL");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}