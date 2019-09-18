<?php

namespace Zotlabs\Update;

class _1235 {

	function run() {

		$r = q("ALTER TABLE item add replyto text NOT NULL DEFAULT ''");

		if($r)
			return UPDATE_SUCCESS;
		return UPDATE_FAILED;

	}

	function verify() {

		$columns = db_columns('item');

		if(in_array('replyto',$columns)) {
			return true;
		}

		return false;

	}

}
