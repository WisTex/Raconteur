<?php
namespace Zotlabs\Module; /** @file */


class Autosavetext extends \Zotlabs\Web\Controller {

	function init() {
	
		if(! local_channel())
			return;
	
		$ret = array('success' => true);
		if(array_key_exists('body',$_REQUEST) && array_key_exists('type',$_REQUEST)) {
			$body = escape_tags($_REQUEST['body']);
			$title = (array_key_exists('title',$_REQUEST) ? escape_tags($_REQUEST['title']) : '');
			$type = $_REQUEST['type'];
	
			if($body && $type === 'post') {
				set_pconfig(local_channel(),'autosavetext_post','body',$body);
				set_pconfig(local_channel(),'autosavetext_post','title',$title);
			}
			
			logger('post saved.', LOGGER_DEBUG);
		}
	
		//		// push updates to channel clones
		//
		//		if((argc() > 1) && (argv(1) === 'sync')) {
		//			require_once('include/zot.php');
		//			build_sync_packet();
		//		}
	
		json_return_and_die($ret);
		
	}
	
}
