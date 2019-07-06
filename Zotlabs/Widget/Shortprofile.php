<?php

namespace Zotlabs\Widget;

use Zotlabs\Lib\Libprofile;

class Shortprofile {

	function widget($arr) {

		if(! \App::$profile['profile_uid'])
			return;

		$block = observer_prohibited();

		return Libprofile::widget(\App::$profile, $block, true, true);
	}

}

