<?php

namespace Zotlabs\Module;

/**
 *
 * This is the POST destination for most all locally posted
 * text stuff. This function handles status, wall-to-wall status, 
 * local comments, and remote coments that are posted on this site 
 * (as opposed to being delivered in a feed).
 * Also processed here are posts and comments coming through the 
 * statusnet/twitter API. 
 * All of these become an "item" which is our basic unit of 
 * information.
 * Posts that originate externally or do not fall into the above 
 * posting categories go through item_store() instead of this function. 
 *
 */  

use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\LDSignatures;
use Zotlabs\Web\HTTPSig;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\ThreadListener;
use Zotlabs\Lib\IConfig;
use Zotlabs\Lib\Enotify;
use App;

require_once('include/attach.php');
require_once('include/bbcode.php');
require_once('include/security.php');


class Id extends Controller {

	function init() {

		if(Libzot::is_zot_request()) {

			$conversation = false;

			$item_id = argv(1);

			if(! $item_id)
				http_status_exit(404, 'Not found');

			$portable_id = EMPTY_STR;

			$sigdata = HTTPSig::verify(EMPTY_STR);
			if($sigdata['portable_id'] && $sigdata['header_valid']) {
				$portable_id = $sigdata['portable_id'];
			}

			$item_normal = " and item.item_hidden = 0 and item.item_type = 0 and item.item_unpublished = 0 and item.item_delayed = 0 and item.item_blocked = 0 ";

			$sql_extra = item_permissions_sql(0);

			$r = q("select * from item where mid like '%s' $item_normal $sql_extra limit 1",
				dbesc('%/' . $item_id)
			);
			if(! $r) {


				$r = q("select * from item where mid like '%s' $item_normal limit 1",
					dbesc('%/' . $item_id)
				);
				if($r) {
					http_status_exit(403, 'Forbidden');
				}
				http_status_exit(404, 'Not found');
			}


			$items = q("select parent as item_id from item where mid = '%s' and uid = %d $item_normal $sql_extra ",
				dbesc($r[0]['parent_mid']),
				intval($r[0]['uid'])
			);
			if(! $items) {
				http_status_exit(404, 'Not found');
			}

			$r = $items;

			$parents_str = ids_to_querystr($r,'item_id');
	
			$items = q("SELECT item.*, item.id AS item_id FROM item WHERE item.parent IN ( %s ) $item_normal $sql_extra ",
				dbesc($parents_str)
			);

			if(! $items) {
				http_status_exit(404, 'Not found');
			}

			$r = $items;
			xchan_query($r,true);
			$items = fetch_post_tags($r,true);

			$observer = App::get_observer();
			$parent = $items[0];
			$recips = (($parent['owner']['xchan_network'] === 'activitypub') ? get_iconfig($parent['id'],'activitypub','recips', []) : []);
			$to = (($recips && array_key_exists('to',$recips) && is_array($recips['to'])) ? $recips['to'] : null);
			$nitems = [];
			foreach($items as $i) {

				$mids = [];

				if(intval($i['item_private'])) {
					if(! $observer) {
						continue;
					}
					// ignore private reshare, possibly from hubzilla
					if($i['verb'] === 'Announce') {
						if(! in_array($i['thr_parent'],$mids)) {
							$mids[] = $i['thr_parent'];
						}
						continue;
					}
					// also ignore any children of the private reshares
					if(in_array($i['thr_parent'],$mids)) {
						continue;
					}

					if((! $to) || (! in_array($observer['xchan_url'],$to))) {
						continue;
					}

				}
				$nitems[] = $i;
			}

			if(! $nitems)
				http_status_exit(404, 'Not found');

			$chan = channelx_by_n($nitems[0]['uid']);

			if(! $chan)
				http_status_exit(404, 'Not found');

			if(! perm_is_allowed($chan['channel_id'],get_observer_hash(),'view_stream'))
				http_status_exit(403, 'Forbidden');

			$i = Activity::encode_item_collection($nitems,'conversation/' . $item_id,'OrderedCollection',( defined('NOMADIC') ? false : true));
			if($portable_id) {
				ThreadListener::store(z_root() . '/item/' . $item_id,$portable_id);
			}

			if(! $i)
				http_status_exit(404, 'Not found');

			$x = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV
				]], $i);

			$headers = [];
			$headers['Content-Type'] = 'application/x-zot+json' ;
			$x['signature'] = LDSignatures::sign($x,$chan);
			$ret = json_encode($x, JSON_UNESCAPED_SLASHES);
			$headers['Digest'] = HTTPSig::generate_digest_header($ret);
			$headers['(request-target)'] = strtolower($_SERVER['REQUEST_METHOD']) . ' ' . $_SERVER['REQUEST_URI'];
			$h = HTTPSig::create_sig($headers,$chan['channel_prvkey'],channel_url($chan));
			HTTPSig::set_headers($h);
			echo $ret;
			killme();

		}

		if(argc() > 1 && argv(1) !== 'drop') {
			$x = q("select plink from item where mid = '%s' limit 1",
				dbesc(z_root() . '/item/' . argv(1))
			);
			if($x) {
				goaway($x[0]['plink']);
			}
		}
	}

}