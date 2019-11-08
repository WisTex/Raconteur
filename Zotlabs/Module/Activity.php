<?php
namespace Zotlabs\Module;

use Zotlabs\Web\Controller;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Activity as ZlibActivity;
use Zotlabs\Web\HTTPSig;
use Zotlabs\Lib\LDSignatures;

class Activity extends Controller {

	function init() {
	
		if (ActivityStreams::is_as_request()) {
			$item_id = argv(1);

			if (! $item_id) {
				return;
			}

			$ob_authorise = false;
			$item_uid = 0;
			
			$bear = ZlibActivity::token_from_request();
			if ($bear) {
				logger('bear: ' . $bear, LOGGER_DEBUG);
				$t = q("select item.uid, iconfig.v from iconfig left join item on iid = item.id where cat = 'ocap' and item.uuid = '%s'",
					dbesc($item_id)
				);
				if ($t) {
					foreach ($t as $token) {
						if ($token['v'] === $bear) {
							$ob_authorize = true;
							$item_uid = $token['uid'];
							break;
						}
					}
				}
			}

			$item_normal = " and item.item_hidden = 0 and item.item_type = 0 and item.item_unpublished = 0 
				and item.item_delayed = 0 and item.item_blocked = 0 ";

			$sigdata = HTTPSig::verify(EMPTY_STR);
			if ($sigdata['portable_id'] && $sigdata['header_valid']) {
				$portable_id = $sigdata['portable_id'];
				if (! check_channelallowed($portable_id)) {
					http_status_exit(403, 'Permission denied');
				}
				if (! check_siteallowed($sigdata['signer'])) {
					http_status_exit(403, 'Permission denied');
				}
				observer_auth($portable_id);
			}

			// if passed an owner_id of 0 to item_permissions_sql(), we force "guest access" or observer checking
			// Give ocap tokens priority
			
			if ($ob_authorize) {
				$sql_extra = " and item.uid = " . intval($token['uid']) . " ";
			}
			else {
				$sql_extra = item_permissions_sql(0);
			}

			$r = q("select * from item where uuid = '%s' $item_normal $sql_extra and item_deleted = 0 limit 1",
				dbesc($item_id)
			);

			if (! $r) {
				$r = q("select * from item where uuid = '%s' $item_normal limit 1",
					dbesc($item_id)
				);

				if($r) {
					http_status_exit(403, 'Forbidden');
				}
				http_status_exit(404, 'Not found');
			}

			xchan_query($r,true);
			$items = fetch_post_tags($r,false);

			$channel = channelx_by_n($items[0]['uid']);

			$x = array_merge( ['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV
				]], ZlibActivity::encode_activity($items[0],get_config('system','activitypub',true)));


			$headers = [];
			$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;
			$x['signature'] = LDSignatures::sign($x,$channel);
			$ret = json_encode($x, JSON_UNESCAPED_SLASHES);
			$headers['Date'] = datetime_convert('UTC','UTC', 'now', 'D, d M Y H:i:s \\G\\M\\T');
			$headers['Digest'] = HTTPSig::generate_digest_header($ret);
			$headers['(request-target)'] = strtolower($_SERVER['REQUEST_METHOD']) . ' ' . $_SERVER['REQUEST_URI'];

			$h = HTTPSig::create_sig($headers,$channel['channel_prvkey'],channel_url($channel));
			HTTPSig::set_headers($h);
			echo $ret;
			killme();

		}
		goaway(z_root() . '/item/' . argv(1));
	}

}