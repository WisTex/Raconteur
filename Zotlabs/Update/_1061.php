<?php

namespace Zotlabs\Update;

class _1061 {
function run() {
	$r = q("ALTER TABLE `vote` ADD INDEX ( `vote_poll` ),  ADD INDEX ( `vote_element` ) ");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}