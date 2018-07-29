<?php

namespace Zotlabs\Update;

class _1215 {

	function run() {

		$r = q("UPDATE xchan set xchan_name = 'unknown' where xchan_name like '%<%' ");

		if($r) {
			return UPDATE_SUCCESS;
		}
		else {        
			return UPDATE_FAILED;
		}
	}

}
