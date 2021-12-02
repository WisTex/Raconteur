<?php
namespace Zotlabs\Module;


use Zotlabs\Web\Controller;

class Login extends Controller {

	function get() {
		if(local_channel())
			goaway(z_root());
		if(remote_channel() && $_SESSION['atoken'])
			goaway(z_root());

		return login(true);
	}
	
}
