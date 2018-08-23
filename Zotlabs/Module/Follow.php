<?php
namespace Zotlabs\Module;

use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Activity;
use Zotlabs\Web\HTTPSignatures;

require_once('include/follow.php');


class Follow extends \Zotlabs\Web\Controller {

	function init() {
	
		if(! local_channel()) {
			return;
		}
	
		if(ActivityStreams::is_as_request() && argc() == 2) {                                                                                        			$abook_id = intval(argv(1));
			if(! $abook_id)
				return;

			$r = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_id = %d",
				intval($abook_id)
			);
			if (! $r)
				return;

			$chan = channelx_by_n($r[0]['abook_channel']);

			if(! $chan)
				http_status_exit(404, 'Not found');

			$actor = Activity::encode_person($chan);
			if(! $actor)
				http_status_exit(404, 'Not found');

			$x = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV
			]],
			[
				'id'     => z_root() . '/follow/' . $r[0]['abook_id'],                                 
                'type'   => 'Follow',
                'actor'  => $actor,
				'object' => $r[0]['xchan_url']
			]);

	        $headers = [];
    	    $headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;
        	$x['signature'] = \Zotlabs\Lib\LDSignatures::sign($x,$chan);
	        $ret = json_encode($x, JSON_UNESCAPED_SLASHES);
    	    $headers['Digest'] = HTTPSig::generate_digest_header($ret);
        	$h = HTTPSig::create_sig($headers,$chan['channel_prvkey'],channel_url($chan));
	        HTTPSig::set_headers($h);
    	    echo $ret;
        	killme();

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
	
		Libsync::build_sync_packet(0 /* use the current local_channel */, array('abook' => array($clone)), true);
	
		$can_view_stream = their_perms_contains($channel['channel_id'],$clone['abook_xchan'],'view_stream');
	
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
