<?php
namespace Zotlabs\Module;



class Regmod extends \Zotlabs\Web\Controller {

	function get() {
	
		global $lang;
	
		$_SESSION['return_url'] = \App::$cmd;
	
		if(! local_channel()) {
			info( t('Please login.') . EOL);
			return login();
		}
	
		if(! is_site_admin()) {
			notice( t('Permission denied.') . EOL);
			return '';
		}
	
		if(argc() != 3)
			killme();
	
		$cmd  = argv(1);
		$hash = argv(2);
	
		if($cmd === 'deny') {
			if (! account_deny($hash)) killme();
		}
	
		if($cmd === 'allow') {
			if (! account_allow($hash)) killme();
		}

		goaway('/admin/accounts');
	}
	
}
