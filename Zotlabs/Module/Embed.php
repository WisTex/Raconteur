<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Daemon\Master;
use Zotlabs\Lib\Libsync;
use Zotlabs\Web\Controller;

require_once('include/security.php');
require_once('include/bbcode.php');


class Embed extends Controller {

	function init() {
	
		$post_id = ((argc() > 1) ? intval(argv(1)) : 0);
	
		if(! $post_id)
			killme();
	
		if(! local_channel()) {
			killme();
		}

		echo '[share=' . $post_id . '][/share]';

		killme();

	}

}
