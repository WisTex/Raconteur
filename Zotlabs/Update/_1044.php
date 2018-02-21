<?php

namespace Zotlabs\Update;

class _1044 {
function run() {
	$r = q("ALTER TABLE `term` ADD `imgurl` CHAR( 255 ) NOT NULL ,
ADD INDEX ( `imgurl` ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}