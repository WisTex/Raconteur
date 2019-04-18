<?php

namespace Zotlabs\Widget;

use App;
use Zotlabs\Lib\Libprofile;


class Fullprofile {

	function widget($arr) {

		if (! App::$profile['profile_uid']) {
			return EMPTY_STR;
		}

		return Libprofile::widget(App::$profile, observer_prohibited());
	}
}
