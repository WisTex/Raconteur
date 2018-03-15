<?php
namespace Zotlabs\Module;


require_once('include/follow.php');


class Follow extends \Zotlabs\Web\Controller {

	function init() {
	
		if(! local_channel()) {
			return;
		}
	
		$uid = local_channel();
		$url = notags(trim(punify($_REQUEST['url'])));
		$return_url = $_SESSION['return_url'];
		$confirm = intval($_REQUEST['confirm']);
		$interactive = (($_REQUEST['interactive']) ? intval($_REQUEST['interactive']) : 1);	
		$channel = \App::get_channel();

		$result = new_contact($uid,$url,$channel,$interactive,$confirm);
		
		if($result['success'] == false) {
			if($result['message'])
				notice($result['message']);
			if($interactive) {
				goaway($return_url);
			}
			else {
				json_return_and_die($result);
			}
		}
	
		info( t('Connection added.') . EOL);
	
		$clone = array();
		foreach($result['abook'] as $k => $v) {
			if(strpos($k,'abook_') === 0) {
				$clone[$k] = $v;
			}
		}
		unset($clone['abook_id']);
		unset($clone['abook_account']);
		unset($clone['abook_channel']);
	
		$abconfig = load_abconfig($channel['channel_id'],$clone['abook_xchan']);
		if($abconfig)
			$clone['abconfig'] = $abconfig;
	
		build_sync_packet(0 /* use the current local_channel */, array('abook' => array($clone)), true);
	
		$can_view_stream = intval(get_abconfig($channel['channel_id'],$clone['abook_xchan'],'their_perms','view_stream'));
	
		// If we can view their stream, pull in some posts
	
		if(($can_view_stream) || ($result['abook']['xchan_network'] === 'rss'))
			\Zotlabs\Daemon\Master::Summon(array('Onepoll',$result['abook']['abook_id']));
	
		if($interactive) {
			goaway(z_root() . '/connedit/' . $result['abook']['abook_id'] . '?f=&follow=1');
		}
		else {
			json_return_and_die([ 'success' => true ]);
		}
	
	}
	
	function get() {
		if(! local_channel()) {
			return login();
		}
	}
}
