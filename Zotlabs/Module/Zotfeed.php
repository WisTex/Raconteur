<?php
namespace Zotlabs\Module;


use App;
use Zotlabs\Web\Controller;

class Zotfeed extends Controller {

	function init() {
	
		$result = [ 'success' => false ];
	
		$mindate = (($_REQUEST['mindate']) ? datetime_convert('UTC','UTC',$_REQUEST['mindate']) : '');
		if (! $mindate) {
			$mindate = datetime_convert('UTC','UTC', 'now - 14 days');
		}
		
		if (observer_prohibited()) {
			$result['message'] = 'Public access denied';
			json_return_and_die($result);
		}
			
		$channel_address = ((argc() > 1) ? argv(1) : '');
		if ($channel_address) {
			$channel = channelx_by_nick($channel_address);
		}
		else {
			$channel = get_sys_channel();
			$mindate = datetime_convert('UTC','UTC', 'now - 14 days');
		}
		if (! $channel) {
			$result['message'] = 'Channel not found.';
			json_return_and_die($result);
		}
	
		logger('zotfeed request: ' . $channel['channel_name'], LOGGER_DEBUG);
	
		$result['project'] = 'Zap';	
		$result['messages'] = zot_feed($channel['channel_id'],get_observer_hash(), [ 'mindate' => $mindate ]);
		$result['success'] = true;
		json_return_and_die($result);
	}
	
}
