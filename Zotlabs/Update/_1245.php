<?php

namespace Zotlabs\Update;

class _1245 {

	function run() {
	
	    q("delete from app where app_url like '%/nocomment'");
		return UPDATE_SUCCESS;

	}

	function verify() {
		return true;
	}

}
