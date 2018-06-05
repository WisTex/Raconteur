<?php

namespace Zotlabs\Widget;


class Dirtags {

	function widget($arr) {
		return dir_tagblock(z_root() . '/directory', null);
	}

}
