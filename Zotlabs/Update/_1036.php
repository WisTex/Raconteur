<?php

namespace Zotlabs\Update;

class _1036 {
function run() {
	$r = q("ALTER TABLE `profile` ADD `channels` TEXT NOT NULL AFTER `contact` ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}



}