<?php
namespace Zotlabs\Module;

use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\LDSignatures;
use Zotlabs\Web\HTTPSig;
use Zotlabs\Lib\Activity;


class Outbox extends \Zotlabs\Web\Controller {

	function post() {

	}

	function get() {

		if(observer_prohibited(true)) {
			killme();
		}

		if(argc() < 2)
			killme();

		$channel = channelx_by_nick(argv(1));
		if(! $channel)
			killme();

		$observer_hash = get_observer_hash();

		$params = [];
	
		$params['begin']     = ((x($_REQUEST,'date_begin')) ? $_REQUEST['date_begin']       : NULL_DATE);
		$params['end']       = ((x($_REQUEST,'date_end'))   ? $_REQUEST['date_end']         : '');
		$params['type']      = 'json';
		$params['pages']     = ((x($_REQUEST,'pages'))      ? intval($_REQUEST['pages'])    : 0);
		$params['top']       = ((x($_REQUEST,'top'))        ? intval($_REQUEST['top'])      : 0);
		$params['start']     = ((x($_REQUEST,'start'))      ? intval($_REQUEST['start'])    : 0);
		$params['records']   = ((x($_REQUEST,'records'))    ? intval($_REQUEST['records'])  : 60);
		$params['direction'] = ((x($_REQUEST,'direction'))  ? dbesc($_REQUEST['direction']) : 'desc');
		$params['cat']       = ((x($_REQUEST,'cat'))        ? escape_tags($_REQUEST['cat']) : '');
		$params['compat']    = ((x($_REQUEST,'compat'))     ? intval($_REQUEST['compat'])   : 1);	


		$items = items_fetch(
    	    [
        	    'wall'       => '1',
            	'datequery'  => $params['end'],
	            'datequery2' => $params['begin'],
    	        'start'      => intval($params['start']),
        	    'records'    => intval($params['records']),
            	'direction'  => dbesc($params['direction']),
	            'pages'      => $params['pages'],
    	        'order'      => dbesc('post'),
        	    'top'        => $params['top'],
            	'cat'        => $params['cat'],
	            'compat'     => $params['compat']
    	    ], $channel, $observer_hash, CLIENT_MODE_NORMAL, \App::$module
	    );

		if(ActivityStreams::is_as_request()) {

	        $x = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV
            	]], Activity::encode_item_collection($items, \App::$query_string, 'OrderedCollection',true));

			$headers = [];
			$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;
			$x['signature'] = LDSignatures::sign($x,$channel);
			$ret = json_encode($x, JSON_UNESCAPED_SLASHES);
			$headers['Digest'] = HTTPSig::generate_digest_header($ret);
			$h = HTTPSig::create_sig($headers,$channel['channel_prvkey'],channel_url($channel));
			HTTPSig::set_headers($h);
			echo $ret;
			killme();

    	}

	}

}



