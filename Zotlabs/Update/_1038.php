<?php

namespace Zotlabs\Update;

class _1038 {
function run() {
	$r = q("ALTER TABLE `manage` CHANGE `mid` `xchan` CHAR( 255 ) NOT NULL DEFAULT '', drop index `mid`,  ADD INDEX ( `xchan` )");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}
 


}