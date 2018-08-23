<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\LDSignatures;
use Zotlabs\Lib\Activity;
use Zotlabs\Web\HTTPSig;


class Followers extends \Zotlabs\Web\Controller {


	function init() {

		if(observer_prohibited(true)) {
			http_status_exit(403, 'Forbidden');
		}

		if(argc() < 2) {
			http_status_exit(404, 'Not found');
		}

		$channel = channelx_by_nick(argv(1));
		if(! $channel) {
			http_status_exit(404, 'Not found');
		}

		$observer_hash = get_observer_hash();

		if(! perm_is_allowed($channel['channel_id'],$observer_hash,'view_contacts')) {
			http_status_exit(403, 'Forbidden');
		}

		$r = q("select * from abconfig left join xchan on abconfig.xchan = xchan_hash where abconfig.chan = %d and abconfig.cat = 'system' and abconfig.k = 'their_perms' and abconfig.v like '%%send_stream%%' and xchan_hash != '%s'",
			intval($channel['channel_id']),
			dbesc($channel['channel_hash'])	
		);
			
		if(ActivityStreams::is_as_request()) {

			$x = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV
				]], Activity::encode_follow_collection($r, \App::$query_string, 'OrderedCollection'));


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
