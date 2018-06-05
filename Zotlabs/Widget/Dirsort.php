<?php

namespace Zotlabs\Widget;

use Zotlabs\Lib\Libzotdir;

class Dirsort {
	function widget($arr) {
		return Libzotdir::dir_sort_links();
	}
}
