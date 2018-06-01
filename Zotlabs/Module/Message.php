<?php
namespace Zotlabs\Module;

require_once('include/acl_selectors.php');
require_once('include/message.php');
require_once("include/bbcode.php");


class Message extends \Zotlabs\Web\Controller {

	function get() {
	
		$o = '';
		nav_set_selected('messages');
	
		if(! local_channel()) {
			notice( t('Permission denied.') . EOL);
			return login();
		}
	
		$channel = \App::get_channel();
		head_set_icon($channel['xchan_photo_s']);
	
		$cipher = get_pconfig(local_channel(),'system','default_cipher');
		if(! $cipher)
			$cipher = 'aes256';
	
	
		return;
	}
	
}
