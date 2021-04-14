<?php

namespace Zotlabs\Lib;

use App;
use Zotlabs\Web\HTTPSig;
use Zotlabs\Access\Permissions;
use Zotlabs\Access\PermissionRoles;
use Zotlabs\Access\PermissionLimits;
use Zotlabs\Daemon\Run;
use Zotlabs\Lib\PConfig;
use Zotlabs\Lib\Config;
use Zotlabs\Lib\LibBlock;
use Zotlabs\Lib\Markdown;
use Zotlabs\Lib\Libzotdir;
use Zotlabs\Lib\Nodeinfo;
use Emoji;

require_once('include/html2bbcode.php');
require_once('include/html2plain.php');
require_once('include/event.php');

class Activity {

	static $ACTOR_CACHE_DAYS = 3;

	static function encode_object($x) {

		if (($x) && (! is_array($x)) && (substr(trim($x),0,1)) === '{' ) {
			$x = json_decode($x,true);
		}

		if ($x['type'] === ACTIVITY_OBJ_PERSON) {
			return self::fetch_person($x); 
		}
		if ($x['type'] === ACTIVITY_OBJ_PROFILE) {
			return self::fetch_profile($x); 
		}
		if (in_array($x['type'], [ ACTIVITY_OBJ_NOTE, ACTIVITY_OBJ_ARTICLE ] )) {

			// Use Mastodon-specific note and media hacks if nomadic. Else HTML.
			// Eventually this needs to be passed in much further up the stack
			// and base the decision on whether or not we are encoding for ActivityPub or Zot6

			return self::fetch_item($x,((get_config('system','activitypub', ACTIVITYPUB_ENABLED)) ? true : false)); 
		}
		if ($x['type'] === ACTIVITY_OBJ_THING) {
			return self::fetch_thing($x); 
		}

		call_hooks('encode_object',$x);

		return $x;

	}



	static function fetch($url,$channel = null,$hub = null) {
		$redirects = 0;
		if (! check_siteallowed($url)) {
			logger('denied: ' . $url);
			return null;
		}
		if (! $channel) {
			$channel = get_sys_channel();
		}

		logger('fetch: ' . $url, LOGGER_DEBUG);

		if (strpos($url,'x-zot:') === 0) {
			$x = ZotURL::fetch($url,$channel,$hub);
		}
		else {
			$m = parse_url($url);

			// handle bearcaps
			if ($m['scheme'] === 'bear' && $m['query']) {
				$params = explode('&',$m['query']);
				if ($params) {
					foreach ($params as $p) {
						if (substr($p,0,2) === 'u=') {
							$url = substr($p,2);
						}
						if (substr($p,0,2) === 't=') {
							$token = substr($p,2);
						}
					}
					// re-parse the URL because it changed and we need the host in the next section
					$m = parse_url($url);
				}

			}

			$headers = [
				'Accept'           => 'application/activity+json, application/x-zot-activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
				'Host'             => $m['host'],
				'Date'             => datetime_convert('UTC','UTC', 'now', 'D, d M Y H:i:s \\G\\M\\T'),
				'(request-target)' => 'get ' . get_request_string($url)
			];
			if (isset($token)) {
				$headers['Authorization'] = 'Bearer ' . $token;
			}
			$h = HTTPSig::create_sig($headers,$channel['channel_prvkey'],channel_url($channel),false);
			$x = z_fetch_url($url, true, $redirects, [ 'headers' => $h ] );
		}

		if ($x['success']) {

			$y = json_decode($x['body'],true);
			logger('returned: ' . json_encode($y,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

			$m = parse_url($url);
			if ($m) {
				$site_url = unparse_url( ['scheme' => $m['scheme'], 'host' => $m['host'], 'port' => ((array_key_exists('port',$m) && intval($m['port'])) ? $m['port'] : 0) ] );
				q("update site set site_update = '%s' where site_url = '%s' and site_update < %s - INTERVAL %s",
					dbesc(datetime_convert()),
					dbesc($site_url),
					db_utcnow(), db_quoteinterval('1 DAY')
				);
			}

			// check for a valid signature, but only if this is not an actor object. If it is signed, it must be valid.
			// Ignore actors because of the potential for infinite recursion if we perform this step while
			// fetching an actor key to validate a signature elsewhere. This should validate relayed activities
			// over litepub which arrived at our inbox that do not use LD signatures
			
			if (($y['type']) && (! ActivityStreams::is_an_actor($y['type']))) {
				$sigblock = HTTPSig::verify($x);

				if (($sigblock['header_signed']) && (! $sigblock['header_valid'])) {
					return null;
				}
			}

			return json_decode($x['body'], true);


		}
		else {
			logger('fetch failed: ' . $url);
		}
		return null;
	}



	static function fetch_person($x) {
		return self::fetch_profile($x);
	}

	static function fetch_profile($x) {
		$r = q("select * from xchan left join hubloc on xchan_hash = hubloc_hash where hubloc_id_url = '%s' limit 1",
			dbesc($x['id'])
		);
		if (! $r) {
			$r = q("select * from xchan where xchan_hash = '%s' limit 1",
				dbesc($x['id'])
			);

		}
		if (! $r) {
			return [];
		}

		return self::encode_person($r[0],false);
	}

	static function fetch_thing($x) {

		$r = q("select * from obj where obj_type = %d and obj_obj = '%s' limit 1",
			intval(TERM_OBJ_THING),
			dbesc($x['id'])
		);

		if (! $r) {
			return [];
		}
		
		$x = [
			'type' => 'Object',
			'id'   => z_root() . '/thing/' . $r[0]['obj_obj'],
			'name' => $r[0]['obj_term']
		];

		if ($r[0]['obj_image']) {
			$x['image'] = $r[0]['obj_image'];
		}
		return $x;

	}

	static function fetch_item($x,$activitypub = false) {

		if (array_key_exists('source',$x)) {
			// This item is already processed and encoded
			return $x;
		}

		$r = q("select * from item where mid = '%s' limit 1",
			dbesc($x['id'])
		);
		if ($r) {
			xchan_query($r,true);
			$r = fetch_post_tags($r,true);
			if ($r[0]['verb'] === 'Invite') {
				return self::encode_activity($r[0],$activitypub);
			}
			return self::encode_item($r[0],$activitypub);
		}
	}

	static function paged_collection_init($total,$id, $type = 'OrderedCollection') {

		$ret = [
			'id' => z_root() . '/' . $id,
			'type' => $type,
			'totalItems' => $total,
		];

		$numpages = $total / App::$pager['itemspage'];
		$lastpage = (($numpages > intval($numpages)) ? intval($numpages) + 1 : $numpages);

		$ret['first'] = z_root() . '/' . App::$query_string . '?page=1';
		$ret['last']  = z_root() . '/' . App::$query_string . '?page=' . $lastpage;

		return $ret;
		
	}


	static function encode_item_collection($items,$id,$type,$activitypub = false,$total = 0) {

		if ($total > 100) {
			$ret = [
				'id' => z_root() . '/' . $id,
				'type' => $type . 'Page',
			];

			$numpages = $total / App::$pager['itemspage'];
			$lastpage = (($numpages > intval($numpages)) ? intval($numpages) + 1 : $numpages);

			$url_parts = parse_url($id);

			$ret['partOf'] = z_root() . '/' . $url_parts['path'];

			$extra_query_args = '';
			$query_args = null;
			if (isset($url_parts['query'])) {
				parse_str($url_parts['query'], $query_args);
			}

			if (is_array($query_args)) {
				unset($query_args['page']);
				foreach ($query_args as $k => $v) {
					$extra_query_args .= '&' . urlencode($k) . '=' . urlencode($v);
				}
			}

			if (App::$pager['page'] < $lastpage) {
				$ret['next'] = z_root() . '/' . $url_parts['path'] . '?page=' . (intval(App::$pager['page']) + 1) . $extra_query_args;
			}
			if (App::$pager['page'] > 1) {
				$ret['prev'] = z_root() . '/' . $url_parts['path'] . '?page=' . (intval(App::$pager['page']) - 1) . $extra_query_args;
			}
		}
		else {
			$ret = [
				'id' => z_root() . '/' . $id,
				'type' => $type,
				'totalItems' => $total,
			];
		}


		if ($items) {
			$x = [];
			foreach ($items as $i) {
				$m = get_iconfig($i['id'],'activitypub','rawmsg');
				if ($m) {
					$t = json_decode($m,true);
				}
				else {
					$t = self::encode_activity($i,$activitypub);
				}
				if ($t) {
					$x[] = $t;
				}
			}
			if ($type === 'OrderedCollection') {
				$ret['orderedItems'] = $x;
			}
			else {
				$ret['items'] = $x;
			}
		}

		return $ret;
	}

	static function encode_follow_collection($items,$id,$type,$total = 0,$extra = null) {

		if ($total > 100) {
			$ret = [
				'id' => z_root() . '/' . $id,
				'type' => $type . 'Page',
			];

			$numpages = $total / App::$pager['itemspage'];
			$lastpage = (($numpages > intval($numpages)) ? intval($numpages) + 1 : $numpages);

			$stripped = preg_replace('/([&|\?]page=[0-9]*)/','',$id);
			$stripped = rtrim($stripped,'/');

			$ret['partOf'] = z_root() . '/' . $stripped;

			if (App::$pager['page'] < $lastpage) {
				$ret['next'] = z_root() . '/' . $stripped . '?page=' . (intval(App::$pager['page']) + 1);
			}
			if (App::$pager['page'] > 1) {
				$ret['prev'] = z_root() . '/' . $stripped . '?page=' . (intval(App::$pager['page']) - 1);
			}
		}
		else {
			$ret = [
				'id' => z_root() . '/' . $id,
				'type' => $type,
				'totalItems' => $total,
			];
		}
		
		if ($extra) {
			$ret = array_merge($ret,$extra);
		}

		if ($items) {
			$x = [];
			foreach ($items as $i) {
				if ($i['xchan_network'] === 'activitypub') {
					$x[] = $i['xchan_hash'];
				}
				else {
					$x[] = $i['xchan_url'];
				}
			}

			if ($type === 'OrderedCollection') {
				$ret['orderedItems'] = $x;
			}
			else {
				$ret['items'] = $x;
			}
		}

		return $ret;
	}



	static function encode_simple_collection($items,$id,$type,$total = 0,$extra = null) {

		$ret = [
			'id' => z_root() . '/' . $id,
			'type' => $type,
			'totalItems' => $total,
		];
		
		if ($extra) {
			$ret = array_merge($ret,$extra);
		}

		if ($items) {
			if ($type === 'OrderedCollection') {
				$ret['orderedItems'] = $items;
			}
			else {
				$ret['items'] = $items;
			}
		}

		return $ret;
	}





	static function decode_taxonomy($item) {

		$ret = [];

		if (array_key_exists('tag',$item) && is_array($item['tag'])) {
			$ptr = $item['tag'];
			if (! array_key_exists(0,$ptr)) {
				$ptr = [ $ptr ];
			}
			foreach ($ptr as $t) {
				if (! array_key_exists('type',$t)) {
					$t['type'] = 'Hashtag';
				}
				if (! (array_key_exists('name',$t))) {
					continue;
				}
				if (! (array_path_exists('icon/url',$t) || array_key_exists('href',$t))) {
					continue;
				}

				switch($t['type']) {
					case 'Hashtag':
						$ret[] = [ 'ttype' => TERM_HASHTAG, 'url' => $t['href'], 'term' => escape_tags((substr($t['name'],0,1) === '#') ? substr($t['name'],1) : $t['name']) ];
						break;

					case 'topicalCollection':
						$ret[] = [ 'ttype' => TERM_PCATEGORY, 'url' => $t['href'], 'term' => escape_tags($t['name']) ];
						break;

					case 'Category':
						$ret[] = [ 'ttype' => TERM_CATEGORY, 'url' => $t['href'], 'term' => escape_tags($t['name']) ];
						break;

					case 'Mention':
						$mention_type = substr($t['name'],0,1);
						if ($mention_type === '!') {
							$ret[] = [ 'ttype' => TERM_FORUM, 'url' => $t['href'], 'term' => escape_tags(substr($t['name'],1)) ];
						}
						else {
							$ret[] = [ 'ttype' => TERM_MENTION, 'url' => $t['href'], 'term' => escape_tags((substr($t['name'],0,1) === '@') ? substr($t['name'],1) : $t['name']) ];
						}
						break;

					case 'Emoji':
						$ret[] = [ 'ttype' => TERM_EMOJI, 'url' => $t['icon']['url'], 'term' => escape_tags($t['name']) ];
						break;

					default:
						break;
				}
			}
		}

		return $ret;
	}


	static function encode_taxonomy($item) {

		$ret = [];

		if (isset($item['term']) && is_array($item['term']) && $item['term']) {
			foreach ($item['term'] as $t) {
				switch($t['ttype']) {
					case TERM_HASHTAG:
						// An id is required so if we don't have a url in the taxonomy, ignore it and keep going.
						if ($t['url']) {
							$ret[] = [ 'id' => $t['url'], 'name' => '#' . $t['term'] ];
						}
						break;

					case TERM_PCATEGORY:
						if ($t['url'] && $t['term']) {
							$ret[] = [ 'type' => 'topicalCollection', 'href' => $t['url'], 'name' => $t['term'] ];
						}
						break;

					case TERM_CATEGORY:
						if ($t['url'] && $t['term']) {
							$ret[] = [ 'type' => 'Category', 'href' => $t['url'], 'name' => $t['term'] ];
						}
						break;

					case TERM_FORUM:
						$term = self::lookup_term_addr($t['url'],$t['term']);
						$ret[] = [ 'type' => 'Mention', 'href' => $t['url'], 'name' => '!' . (($term) ? $term : $t['term']) ];
						break;

					case TERM_MENTION:
						$term = self::lookup_term_addr($t['url'],$t['term']);
						$ret[] = [ 'type' => 'Mention', 'href' => $t['url'], 'name' => '@' . (($term) ? $term : $t['term']) ];
						break;
	
					default:
						break;
				}
			}
		}

		return $ret;
	}


	static function lookup_term_addr($url,$name) {

		// The visible mention in our activities is always the full name.
		// In the object taxonomy change this to the webfinger handle in case
		// platforms expect the Mastodon form in order to generate notifications
		// Try a couple of different things in case the url provided isn't the canonical id. 
		// If all else fails, try to match the name. 

		$r = false;

		if ($url) {
			$r = q("select xchan_addr from xchan where ( xchan_url = '%s' OR xchan_hash = '%s' ) limit 1",
				dbesc($url),
				dbesc($url)
			);

			if ($r) {
				return $r[0]['xchan_addr'];
			}
		}		
		if ($name) {
			$r = q("select xchan_addr from xchan where xchan_name = '%s' limit 1",
				dbesc($name)
			);
			if ($r) {
				return $r[0]['xchan_addr'];
			}

		}

		return EMPTY_STR;
	}



	static function lookup_term_url($url) {

		// The xchan_url for mastodon is a text/html rendering. This is called from map_mentions where we need
		// to convert the mention url to an ActivityPub id. If this fails for any reason, return the url we have

		$r = q("select * from hubloc where hubloc_id_url = '%s' or hubloc_hash = '%s' limit 1",
			dbesc($url),
			dbesc($url)
		);

		if ($r) {
			if ($r[0]['hubloc_network'] === 'activitypub') {
				return $r[0]['hubloc_hash'];
			}
			return $r[0]['hubloc_id_url'];
		}

		return $url;
	}


	static function encode_attachment($item) {

		$ret = [];

		if (array_key_exists('attach',$item)) {
			$atts = ((is_array($item['attach'])) ? $item['attach'] : json_decode($item['attach'],true));
			if ($atts) {
				foreach ($atts as $att) {
					if (strpos($att['type'],'image')) {
						$ret[] = [ 'type' => 'Image', 'url' => $att['href'] ];
					}
					else {
						$ret[] = [ 'type' => 'Link', 'mediaType' => $att['type'], 'href' => $att['href'] ];
					}
				}
			}
		}
		if (array_key_exists('iconfig',$item) && is_array($item['iconfig'])) {
			foreach ($item['iconfig'] as $att) {
				if ($att['sharing']) {
					$ret[] = [ 'type' => 'PropertyValue', 'name' => 'zot.' . $att['cat'] . '.' . $att['k'], 'value' => unserialise($att['v']) ];
				}
			}
		}

		return $ret;
	}


	static function decode_iconfig($item) {

		$ret = [];

		if (is_array($item['attachment']) && $item['attachment']) {
			$ptr = $item['attachment'];
			if (! array_key_exists(0,$ptr)) {
				$ptr = [ $ptr ];
			}
			foreach ($ptr as $att) {
				$entry = [];
				if ($att['type'] === 'PropertyValue') {
					if (array_key_exists('name',$att) && $att['name']) {
						$key = explode('.',$att['name']);
						if (count($key) === 3 && $key[0] === 'zot') {
							$entry['cat'] = $key[1];
							$entry['k'] = $key[2];
							$entry['v'] = $att['value'];
							$entry['sharing'] = '1';
							$ret[] = $entry;
						}
					}
				}
			}
		}
		return $ret;
	}




	static function decode_attachment($item) {

		$ret = [];

		if (array_key_exists('attachment',$item) && is_array($item['attachment'])) {
			$ptr = $item['attachment'];
			if (! array_key_exists(0,$ptr)) {
				$ptr = [ $ptr ];
			}
			foreach ($ptr as $att) {
				$entry = [];
				if (array_key_exists('href',$att) && $att['href'])
					$entry['href'] = $att['href'];
				elseif (array_key_exists('url',$att) && $att['url'])
					$entry['href'] = $att['url'];
				if (array_key_exists('mediaType',$att) && $att['mediaType'])
					$entry['type'] = $att['mediaType'];
				elseif (array_key_exists('type',$att) && $att['type'] === 'Image')
					$entry['type'] = 'image/jpeg';
				if (array_key_exists('name',$att) && $att['name']) {
					$entry['name'] = html2plain(purify_html($att['name']),256);
				}
				if ($entry)
					$ret[] = $entry;
			}
		}
		elseif (is_string($item['attachment'])) {
			btlogger('not an array: ' . $item['attachment']);
		}

		return $ret;
	}


	// the $recurse flag encodes the original non-deleted object of a deleted activity

	static function encode_activity($i,$activitypub = false,$recurse = false) {

		$ret   = [];
		$reply = false;

		if (intval($i['item_deleted']) && (! $recurse)) {

			$is_response = ActivityStreams::is_response_activity($i['verb']);

			if ($is_response) {
				$ret['type'] = 'Undo';
				$fragment = '#undo';
			}
			else {
				$ret['type'] = 'Delete';
				$fragment = '#delete';
			}
			
			$ret['id'] = str_replace('/item/','/activity/',$i['mid']) . $fragment;
			$actor = self::encode_person($i['author'],false);
			if ($actor)
				$ret['actor'] = $actor;
			else
				return []; 

			$obj = (($is_response) ? self::encode_activity($i,$activitypub,true) : self::encode_item($i,$activitypub));
			if ($obj) {
				if (array_path_exists('object/id',$obj)) {
					$obj['object'] = $obj['object']['id'];
				}
				if ($obj) {
					$ret['object'] = $obj;
				}
            }
            else {
                return [];
			}
			
			$ret['to'] = [ ACTIVITY_PUBLIC_INBOX ];
			return $ret;

		}

		$ret['type'] = self::activity_mapper($i['verb']);

		if (strpos($i['mid'],z_root() . '/item/') !== false) {
			$ret['id'] = str_replace('/item/','/activity/',$i['mid']);
		}
		elseif (strpos($i['mid'],z_root() . '/event/') !== false) {
			$ret['id'] = str_replace('/event/','/activity/',$i['mid']);
		}
		else {
			$ret['id'] = $i['mid'];
		}

		if ($i['title']) {
			$ret['name'] = $i['title'];
		}

		if ($i['summary']) {
			$ret['summary'] = bbcode($i['summary'], [ 'export' => true ]);
		}

		if ($ret['type'] === 'Announce') {
			$tmp = $i['body'];
			$ret['content'] = bbcode($tmp, [ 'export' => true ]);
			$ret['source'] = [
				'content' => $i['body'],
				'mediaType' => 'text/bbcode'
			];
			if ($i['summary']) {
				$ret['source']['summary'] = $i['summary'];
			}
		}

		$ret['published'] = datetime_convert('UTC','UTC',$i['created'],ATOM_TIME);
		if ($i['created'] !== $i['edited']) {
			$ret['updated'] = datetime_convert('UTC','UTC',$i['edited'],ATOM_TIME);
			if ($ret['type'] === 'Create') {
				$ret['type'] = 'Update';
			}
		}
		if ($i['app']) {
			$ret['generator'] = [ 'type' => 'Application', 'name' => $i['app'] ];
		}
		if ($i['location'] || $i['coord']) {
			$ret['location'] = [ 'type' => 'Place' ];
			if ($i['location']) {
				$ret['location']['name'] = $i['location'];
			}
			if ($i['coord']) {
				$l = explode(' ',$i['coord']);
				$ret['location']['latitude'] = $l[0];
				$ret['location']['longitude'] = $l[1];
			}
		}

		if ($i['mid'] !== $i['parent_mid']) {
			$reply = true;

			// inReplyTo needs to be set in the activity for followup actions (Like, Dislike, Announce, etc.),
			// but *not* for comments and RSVPs, where it should only be present in the object
			
			if (! in_array($ret['type'],[ 'Create','Update','Accept','Reject','TentativeAccept','TentativeReject' ])) {
				$ret['inReplyTo'] = $i['thr_parent'];
				$cnv = get_iconfig($i['parent'],'ostatus','conversation');
				if (! $cnv) {
					$cnv = $ret['parent_mid'];
				}
			}
		}

		if (! (isset($cnv) && $cnv)) {
			// This method may be called before the item is actually saved - in which case there is no id and IConfig cannot be used
			if ($i['id']) {
				$cnv = get_iconfig($i,'ostatus','conversation');
			}
			else {
				$cnv = $i['parent_mid'];
			}
		}
		if (isset($cnv) && $cnv) {
			$ret['conversation'] = $cnv;
		}

		if (intval($i['item_private']) === 2) {
			$ret['directMessage'] = true;
		}

		$actor = self::encode_person($i['author'],false);
		if ($actor)
			$ret['actor'] = $actor;
		else
			return []; 

		$replyto = self::encode_person($i['owner'],false);
//		if ($replyto) {
//			$ret['replyTo'] = $replyto;
//		}
		
		if (! isset($ret['url'])) {
			$urls = [];
			if (intval($i['item_wall'])) {
				$locs = self::nomadic_locations($i);
				if ($locs) {
					foreach ($locs as $l) {
						if (strpos($ret['id'],$l['hubloc_url']) !== false) {
							continue;
						}
						$urls[] = [
							'type' => 'Link',
							'href' => str_replace(z_root(),$l['hubloc_url'],$ret['id']),
							'rel' => 'alternate',
							'mediaType' => 'text/html'
						];
						$urls[] = [
							'type' => 'Link',
							'href' => str_replace(z_root(),$l['hubloc_url'],$ret['id']),
							'rel' => 'alternate',
							'mediaType' => 'application/activity+json'
						];
						$urls[] = [
							'type' => 'Link',
							'href' => str_replace(z_root(),$l['hubloc_url'],$ret['id']),
							'rel' => 'alternate',
							'mediaType' => 'application/x-zot+json'
						];
					}
				}
			}
			if ($urls) {
				$curr[] = [
					'type'      => 'Link',
					'href'      => $ret['id'],
					'rel'       => 'alternate',
					'mediaType' => 'text/html'
				];				
				$ret['url'] = array_merge($curr, $urls);
			}
			else {
				$ret['url'] = $ret['id'];
			}
		}


		if ($i['obj']) {
			if (! is_array($i['obj'])) {
				$i['obj'] = json_decode($i['obj'],true);
			}
			$obj = self::encode_object($i['obj']);
			if ($obj)
				$ret['object'] = $obj;
			else
				return [];
		}
		else {
			$obj = self::encode_item($i,$activitypub);
			if ($obj)
				$ret['object'] = $obj;
			else
				return [];
		}

		if ($i['target']) {
			if (! is_array($i['target'])) {
				$i['target'] = json_decode($i['target'],true);
			}
			$tgt = self::encode_object($i['target']);
			if ($tgt) {
				$ret['target'] = $tgt;
			}
		}

		$t = self::encode_taxonomy($i);
		if ($t) {
			$ret['tag'] = $t;
		}

		// addressing madness
		
		if ($activitypub) {

			$public = (($i['item_private']) ? false : true);
			$top_level = (($reply) ? false : true);
			
			if ($public) {
				$ret['to'] = [ ACTIVITY_PUBLIC_INBOX ];
				$ret['cc'] = [ z_root() . '/followers/' . substr($i['author']['xchan_addr'],0,strpos($i['author']['xchan_addr'],'@')) ];
			}
			else {
			
				// private activity
				
				if ($top_level) {
					$ret['to'] = self::map_acl($i);
				}
				else {
					$ret['cc'] = self::map_acl($i);
					if ($ret['tag']) {
						foreach ($ret['tag'] as $mention) {
							if (is_array($mention) && array_key_exists('ttype',$mention) && in_array($mention['ttype'],[ TERM_FORUM, TERM_MENTION]) && array_key_exists('href',$mention) && $mention['href']) {
								$h = q("select * from hubloc where hubloc_id_url = '%s' limit 1",
									dbesc($mention['href'])
								);
								if ($h) {
									if ($h[0]['hubloc_network'] === 'activitypub') {
										$addr = $h[0]['hubloc_hash'];
									}
									else {
										$addr = $h[0]['hubloc_id_url'];
									}
									if (! in_array($addr,$ret['to'])) {
										$ret['to'][] = $addr;
									}
								}
							}
						}
					}

					$d = q("select hubloc.*  from hubloc left join item on hubloc_hash = owner_xchan where item.parent_mid = '%s' and item.uid = %d limit 1",
						dbesc($i['parent_mid']),
						intval($i['uid'])
					);
					if ($d) {
						if ($d[0]['hubloc_network'] === 'activitypub') {
							$addr = $d[0]['hubloc_hash'];
						}
						else {
							$addr = $d[0]['hubloc_id_url'];
						}
						$ret['cc'][] = $addr;
					}
				}
			}

			$mentions = self::map_mentions($i);
			if (count($mentions) > 0) {
				if (! $ret['to']) {
					$ret['to'] = $mentions;
				}
				else {
					$ret['to'] = array_values(array_unique(array_merge($ret['to'], $mentions)));
				}
			}	
		}

		$cc = [];
		if ($ret['cc'] && is_array($ret['cc'])) {
			foreach ($ret['cc'] as $e) {
				if (! is_array($ret['to'])) {
					$cc[] = $e;
				}
				elseif (! in_array($e,$ret['to'])) {
					$cc[] = $e;
				}
			}
		}
		$ret['cc'] = $cc;

		return $ret;
	}


	static function nomadic_locations($item) {
		$synchubs = [];
		$h = q("select hubloc.*, site.site_crypto from hubloc left join site on site_url = hubloc_url 
			where hubloc_hash = '%s' and hubloc_network = 'zot6' and hubloc_deleted = 0",
			dbesc($item['author_xchan'])
		);

		if (! $h) {
			return [];
		}

		foreach ($h as $x) {
			$y = q("select site_dead from site where site_url = '%s' limit 1",
				dbesc($x['hubloc_url'])
			);

			if ((! $y) || intval($y[0]['site_dead']) === 0) {
				$synchubs[] = $x;
			}
		}
		
		return $synchubs;
	}


	static function encode_item($i, $activitypub = false) {

		$ret = [];
		$reply = false;
		$is_directmessage = false;

		$bbopts = (($activitypub) ? 'activitypub' : 'export');

		$objtype = self::activity_obj_mapper($i['obj_type']);

		if (intval($i['item_deleted'])) {
			$ret['type'] = 'Tombstone';
			$ret['formerType'] = $objtype;
			$ret['id'] = $i['mid'];
			$ret['to'] = [ ACTIVITY_PUBLIC_INBOX ];
			return $ret;
		}

		if (isset($i['obj'])) {
			if (is_array($i['obj'])) {
				$ret = $i['obj'];
			}
			else {
				$ret = json_decode($i['obj'],true);
			}
		}

		$ret['type'] = $objtype;

		if ($objtype === 'Question') {
			if ($i['obj']) {
				if (is_array($i['obj'])) {
					$ret = $i['obj'];
				}
				else {
					$ret = json_decode($i['obj'],true);
				}
			
				if(array_path_exists('actor/id',$ret)) {
					$ret['actor'] = $ret['actor']['id'];
				}
			}
		}


		$images = false;
		$has_images = preg_match_all('/\[[zi]mg(.*?)\](.*?)\[/ism',$i['body'],$images,PREG_SET_ORDER);

		$ret['id'] = $i['mid'];

		$ret['published'] = datetime_convert('UTC','UTC',$i['created'],ATOM_TIME);
		if ($i['created'] !== $i['edited']) {
			$ret['updated'] = datetime_convert('UTC','UTC',$i['edited'],ATOM_TIME);
		}
		if ($i['expires'] > NULL_DATE) {
			$ret['expires'] = datetime_convert('UTC','UTC',$i['expires'],ATOM_TIME);
		}
		if ($i['app']) {
			$ret['generator'] = [ 'type' => 'Application', 'name' => $i['app'] ];
		}
		if ($i['location'] || $i['coord']) {
			$ret['location'] = [ 'type' => 'Place' ];
			if ($i['location']) {
				$ret['location']['name'] = $i['location'];
			}
			if ($i['coord']) {
				$l = explode(' ',$i['coord']);
				$ret['location']['latitude'] = $l[0];
				$ret['location']['longitude'] = $l[1];
			}
		}

		if (intval($i['item_wall']) && $i['mid'] === $i['parent_mid']) {
			$ret['commentPolicy'] = $i['comment_policy'];
		}

		if (intval($i['item_private']) === 2) {
			$ret['directMessage'] = true;
		}

		if (intval($i['item_nocomment']))  {
			if($ret['commentPolicy']) {
				$ret['commentPolicy'] .= ' ';
			}
			$ret['commentPolicy'] .= 'until=' . datetime_convert('UTC','UTC',$i['created'],ATOM_TIME);
		}
		elseif (array_key_exists('comments_closed',$i) && $i['comments_closed'] !== EMPTY_STR && $i['comments_closed'] > NULL_DATE) {
			if($ret['commentPolicy']) {
				$ret['commentPolicy'] .= ' ';
			}
			$ret['commentPolicy'] .= 'until=' . datetime_convert('UTC','UTC',$i['comments_closed'],ATOM_TIME);
		}
		
		$ret['attributedTo'] = (($i['author']['xchan_network'] === 'zot6') ? $i['author']['xchan_url'] : $i['author']['xchan_hash']);

		if ($i['mid'] !== $i['parent_mid']) {
			$ret['inReplyTo'] = $i['thr_parent'];
			$cnv = get_iconfig($i['parent'],'ostatus','conversation');
			if (! $cnv) {
				$cnv = $ret['parent_mid'];
			}

			$reply = true;

			if  ($i['item_private']) {
				$d = q("select xchan_url, xchan_addr, xchan_name from item left join xchan on xchan_hash = author_xchan where id = %d limit 1",
					intval($i['parent'])
				);
				if ($d) {
					$recips = get_iconfig($i['parent'], 'activitypub', 'recips');

					if (is_array($recips) && in_array($i['author']['xchan_url'], $recips['to'])) {
						$reply_url = $d[0]['xchan_url'];
						$is_directmessage = true;
					}
					else {
						$reply_url = z_root() . '/followers/' . substr($i['author']['xchan_addr'],0,strpos($i['author']['xchan_addr'],'@'));
					}
					$reply_addr = (($d[0]['xchan_addr']) ? $d[0]['xchan_addr'] : $d[0]['xchan_name']);
				}
			}
		}
		if (! isset($cnv)) {
			if ($i['id']) {
				$cnv = get_iconfig($i,'ostatus','conversation');
			}
			else {
				$cnv = $i['parent_mid'];
			}
		}
		if ($cnv) {
			$ret['conversation'] = $cnv;
		}

		// provide ocap access token for private media.
		// set this for descendants even if the current item is not private
		// because it may have been relayed from a private item. 

		$token = get_iconfig($i,'ocap','relay');
		if ($token && $has_images) {
			for ($n = 0; $n < count($images); $n ++) {
				$match = $images[$n];
				if (strpos($match[1],'=http') === 0 && strpos($match[1],'/photo/' !== false)) {
					$i['body'] = str_replace($match[1],$match[1] . '?token=' . $token, $i['body']);
					$images[$n][2] = substr($match[1],1) . '?token=' . $token;
				}
				elseif (strpos($match[2],z_root() . '/photo/') !== false) {
					$i['body'] = str_replace($match[2],$match[2] . '?token=' . $token, $i['body']);
					$images[$n][2] = $match[2] . '?token=' . $token;
				}
			}
		}

		if ($i['title']) {
			$ret['name'] = $i['title'];
		}

		if ($i['mimetype'] === 'text/bbcode') {
			if ($i['summary']) {
				$ret['summary'] = bbcode($i['summary'], [ $bbopts => true ]);
			}
			$opts = [ $bbopts => true ];
			$ret['content'] = bbcode($i['body'], $opts);
			$ret['source'] = [ 'content' => $i['body'], 'mediaType' => 'text/bbcode' ];
			if (isset($ret['summary']))  {
				$ret['source']['summary'] = $i['summary'];
			}
		}
		else {
			$ret['mediaType'] = $i['mimetype'];
			$ret['content'] = $i['body'];
		}

		if (! (isset($ret['actor']) || isset($ret['attributedTo']))) {
			$actor = self::encode_person($i['author'],false);
			if ($actor) {
				$ret['actor'] = $actor;
			}
			else {
				return [];
			}
		}

		$replyto = self::encode_person($i['owner'],false);
//		if ($replyto) {
//			$ret['replyTo'] = $replyto;
//		}
		
		if (! isset($ret['url'])) {
			$urls = [];
			if (intval($i['item_wall'])) {
				$locs = self::nomadic_locations($i);
				if ($locs) {
					foreach ($locs as $l) {
						if (strpos($i['mid'],$l['hubloc_url']) !== false) {
							continue;
						}
						$urls[] = [
							'type' => 'Link',
							'href' => str_replace(z_root(),$l['hubloc_url'],$ret['id']),
							'rel' => 'alternate',
							'mediaType' => 'text/html'
						];
						$urls[] = [
							'type' => 'Link',
							'href' => str_replace(z_root(),$l['hubloc_url'],$ret['id']),
							'rel' => 'alternate',
							'mediaType' => 'application/activity+json'
						];
						$urls[] = [
							'type' => 'Link',
							'href' => str_replace(z_root(),$l['hubloc_url'],$ret['id']),
							'rel' => 'alternate',
							'mediaType' => 'application/x-zot+json'
						];
					}
				}
			}
			if ($urls) {
				$curr[] = [
					'type'      => 'Link',
					'href'      => $ret['id'],
					'rel'       => 'alternate',
					'mediaType' => 'text/html'
				];				
				$ret['url'] = array_merge($curr, $urls);
			}
			else {
				$ret['url'] = $ret['id'];
			}
		}

		$t = self::encode_taxonomy($i);
		if ($t) {
			$ret['tag'] = $t;
		}

		$a = self::encode_attachment($i);
		if ($a) {
			$ret['attachment'] = $a;
		}

		if ($activitypub && $has_images && $ret['type'] === 'Note') {
        	foreach ($images as $match) {
				$img = [];
				// handle Friendica/Hubzilla style img links with [img=$url]$alttext[/img]
				if (strpos($match[1],'=http') === 0) {
					$img[] =  [ 'type' => 'Image', 'url' => substr($match[1],1), 'name' => $match[2] ];
				}
				// preferred mechanism for adding alt text
				elseif (strpos($match[1],'alt=') !== false) {
					$txt = str_replace('&quot;','"',$match[1]);
					$txt = substr($match[1],strpos($match[1],'alt="')+5,-1);
					$img[] =  [ 'type' => 'Image', 'url' => $match[2], 'name' => $txt ];
				}
				else {
					$img[] =  [ 'type' => 'Image', 'url' => $match[2] ];
				}

	        	if (! $ret['attachment']) {
    	        	$ret['attachment'] = [];
				}
				$already_added = false;
				if ($img) {
					foreach ($ret['attachment'] as $a) {
						if (isset($a['url']) && $a['url'] === $img[0]['url']) {
							$already_added = true;
						}
					}
					if (! $already_added) {
						$ret['attachment'] = array_merge($img,$ret['attachment']);
					}
				}				
    		}
		}

		// addressing madness
		
		if ($activitypub) {

			$public = (($i['item_private']) ? false : true);
			$top_level = (($i['mid'] === $i['parent_mid']) ? true : false);
			
			if ($public) {
				$ret['to'] = [ ACTIVITY_PUBLIC_INBOX ];
				$ret['cc'] = [ z_root() . '/followers/' . substr($i['author']['xchan_addr'],0,strpos($i['author']['xchan_addr'],'@')) ];
			}
			else {
			
				// private activity
				
				if ($top_level) {
					$ret['to'] = self::map_acl($i);
				}
				else {
					$ret['cc'] = self::map_acl($i);
					if ($ret['tag']) {
						foreach ($ret['tag'] as $mention) {
							if (is_array($mention) && array_key_exists('ttype',$mention) && in_array($mention['ttype'],[ TERM_FORUM, TERM_MENTION]) && array_key_exists('href',$mention) && $mention['href']) {
								$h = q("select * from hubloc where hubloc_id_url = '%s' or hubloc_hash = '%s' limit 1",
									dbesc($mention['href']),
									dbesc($mention['href'])
								);
								if ($h) {
									if ($h[0]['hubloc_network'] === 'activitypub') {
										$addr = $h[0]['hubloc_hash'];
									}
									else {
										$addr = $h[0]['hubloc_id_url'];
									}
									if (! in_array($addr,$ret['to'])) {
										$ret['to'][] = $addr;
									}
								}
							}
						}
					}


					$d = q("select hubloc.*  from hubloc left join item on hubloc_hash = owner_xchan where item.parent_mid = '%s' and item.uid = %d limit 1",
						dbesc($i['parent_mid']),
						intval($i['uid'])
					);

					if ($d) {
						if ($d[0]['hubloc_network'] === 'activitypub') {
							$addr = $d[0]['hubloc_hash'];
						}
						else {
							$addr = $d[0]['hubloc_id_url'];
						}
						$ret['cc'][] = $addr;
					}
				}
			}

			$mentions = self::map_mentions($i);
			if (count($mentions) > 0) {
				if (! $ret['to']) {
					$ret['to'] = $mentions;
				}
				else {
					$ret['to'] = array_values(array_unique(array_merge($ret['to'], $mentions)));
				}
			}	
		}

		// remove any duplicates from 'cc' that are present in 'to'
		// as this may indicate that mentions changed the audience from secondary to primary
		
		$cc = [];
		if ($ret['cc'] && is_array($ret['cc'])) {
			foreach ($ret['cc'] as $e) {
				if (! is_array($ret['to'])) {
					$cc[] = $e;
				}
				elseif (! in_array($e,$ret['to'])) {
					$cc[] = $e;
				}
			}
		}
		$ret['cc'] = $cc;

		return $ret;
	}



	
	// Returns an array of URLS for any mention tags found in the item array $i.
	
	static function map_mentions($i) {
		if (! (array_key_exists('term',$i) && is_array($i['term']))) {
			return [];
		}

		$list = [];

		foreach ($i['term'] as $t) {
			if (! (array_key_exists('url',$t) && $t['url'])) {
				continue;
			}
			if (array_key_exists('ttype',$t) && $t['ttype'] == TERM_MENTION) {
				$url = self::lookup_term_url($t['url']);
				$list[] = (($url) ? $url : $t['url']);
			}
		}

		return $list;
	}

	// Returns an array of all recipients targeted by private item array $i.
	
	static function map_acl($i) {
		$ret = [];

		if (! $i['item_private']) {
			return $ret;
		}

		if ($i['mid'] !== $i['parent_mid']) {
			$i = q("select * from item where parent_mid = '%s' and uid = %d",
				dbesc($i['parent_mid']),
				intval($i['uid'])
			);
			if ($i) {
				$i = array_shift($i);
			}
		}
		if ($i['allow_gid']) {
			$tmp = expand_acl($i['allow_gid']);
			if ($tmp) {
				foreach ($tmp as $t) {
					$ret[] = z_root() . '/lists/' . $t;
				}
			}
		}

		if ($i['allow_cid']) {
			$tmp = expand_acl($i['allow_cid']);
			$list = stringify_array($tmp,true);
			if ($list) {
				$details = q("select hubloc_id_url, hubloc_hash, hubloc_network from hubloc where hubloc_hash in (" . $list . ") ");
				if ($details) {
					foreach ($details as $d) {
						if ($d['hubloc_network'] === 'activitypub') {
							$ret[] = $d['hubloc_hash'];
						}
						else {
							$ret[] = $d['hubloc_id_url'];
						}
					}
				}
			}
		}

		$x = get_iconfig($i['id'],'activitypub','recips');
		if ($x) {
			foreach ([ 'to','cc' ] as $k) {
				if (isset($x[$k])) {
					if (is_string($x[$k])) {
						$ret[] = $x[$k];
					}
					else {
						$ret = array_merge($ret,$x[$k]);
					}
				}
			}
		}

		return array_values(array_unique($ret));				

	}


	static function encode_person($p, $extended = true, $activitypub = false) {

		$ret = [];

		if (! $p['xchan_url'])
			return $ret;

		if (! $extended) {
			return $p['xchan_url'];
		}

		$c = ((array_key_exists('channel_id',$p)) ? $p : channelx_by_hash($p['xchan_hash']));

		$ret['type']  = 'Person';

		if ($c) {
			$role = PConfig::Get($c['channel_id'],'system','permissions_role');
			if (strpos($role,'forum') !== false) {
				$ret['type'] = 'Group';
			}
		}

		if ($c) {
			$ret['id'] = channel_url($c);
		}
		else {
			$ret['id'] = ((strpos($p['xchan_hash'],'http') === 0) ? $p['xchan_hash'] : $p['xchan_url']);
		}
		if ($p['xchan_addr'] && strpos($p['xchan_addr'],'@'))
			$ret['preferredUsername'] = substr($p['xchan_addr'],0,strpos($p['xchan_addr'],'@'));
		$ret['name']  = $p['xchan_name'];
		$ret['updated'] = datetime_convert('UTC','UTC',$p['xchan_name_date'],ATOM_TIME);
		$ret['icon']  = [
			'type'      => 'Image',
			'mediaType' => (($p['xchan_photo_mimetype']) ? $p['xchan_photo_mimetype'] : 'image/png' ),
			'updated'   => datetime_convert('UTC','UTC',$p['xchan_photo_date'],ATOM_TIME),
			'url'       => $p['xchan_photo_l'],
			'height'    => 300,
			'width'     => 300,
		];
		$ret['url'] = $p['xchan_url'];
		if ($p['channel_location']) {
			$ret['location'] = [ 'type' => 'Place', 'name' => $p['channel_location'] ];
		}

		if ($activitypub && get_config('system','activitypub', ACTIVITYPUB_ENABLED)) {	

			if ($c) {
				if (get_pconfig($c['channel_id'],'system','activitypub', ACTIVITYPUB_ENABLED)) {
					$ret['inbox']       = z_root() . '/inbox/'     . $c['channel_address'];
				}
				else {
					$ret['inbox'] = null;
				}
				
				$ret['outbox']      = z_root() . '/outbox/'    . $c['channel_address'];
				$ret['followers']   = z_root() . '/followers/' . $c['channel_address'];
				$ret['following']   = z_root() . '/following/' . $c['channel_address'];

				$ret['endpoints']   = [
					'sharedInbox' => z_root() . '/inbox',
					'oauthAuthorizationEndpoint' => z_root() . '/authorize',
					'oauthTokenEndpoint' => z_root() . '/token'
				];
				
				$ret['discoverable'] = ((1 - intval($p['xchan_hidden'])) ? true : false);				
				$ret['publicKey'] = [
					'id'           => $p['xchan_url'],
					'owner'        => $p['xchan_url'],
					'publicKeyPem' => $p['xchan_pubkey']
				];

				// map other nomadic identities linked with this channel
				
				$locations = [];
				$locs = Libzot::encode_locations($c);
				if ($locs) {
					foreach ($locs as $loc) {
						if ($loc['url'] !== z_root()) {
							$locations[] = $loc['id_url'];
						}
					}
				}
				
				if ($locations) {
					if (count($locations) === 1) {
						$locations = array_shift($locations);
					}
					$ret['copiedTo'] = $locations;
					$ret['alsoKnownAs'] = $locations;
				}
				
				$cp = get_cover_photo($c['channel_id'],'array');
				if ($cp) {
					$ret['image'] = [
						'type' => 'Image',
						'mediaType' => $cp['type'],
						'url' => $cp['url']
					];
				}
				$dp = q("select about from profile where uid = %d and is_default = 1",
					intval($c['channel_id'])
				);
				if ($dp && $dp[0]['about']) {
					$ret['summary'] = bbcode($dp[0]['about'],['export' => true ]);
				}	
			}
			else {
				$collections = get_xconfig($p['xchan_hash'],'activitypub','collections',[]);
				if ($collections) {
					$ret = array_merge($ret,$collections);
				}
				else {
					$ret['inbox'] = null;
					$ret['outbox'] = null;
				}
    		}
		}
		else {
			$ret['publicKey'] = [
				'id'           => $p['xchan_url'],
				'owner'        => $p['xchan_url'],
				'publicKeyPem' => $p['xchan_pubkey']
			];
		}

		$arr = [ 'xchan' => $p, 'encoded' => $ret, 'activitypub' => $activitypub ];
		call_hooks('encode_person', $arr);
		$ret = $arr['encoded'];


		return $ret;
	}


	static function activity_mapper($verb) {

		if (strpos($verb,'/') === false) {
			return $verb;
		}

		$acts = [
			'http://activitystrea.ms/schema/1.0/post'      => 'Create',
			'http://activitystrea.ms/schema/1.0/share'     => 'Announce',
			'http://activitystrea.ms/schema/1.0/update'    => 'Update',
			'http://activitystrea.ms/schema/1.0/like'      => 'Like',
			'http://activitystrea.ms/schema/1.0/favorite'  => 'Like',
			'http://purl.org/zot/activity/dislike'         => 'Dislike',
			'http://activitystrea.ms/schema/1.0/tag'       => 'Add',
			'http://activitystrea.ms/schema/1.0/follow'    => 'Follow',
			'http://activitystrea.ms/schema/1.0/unfollow'  => 'Unfollow',
		];

		call_hooks('activity_mapper',$acts);

		if (array_key_exists($verb,$acts) && $acts[$verb]) {
			return $acts[$verb];
		}

		// Reactions will just map to normal activities

		if (strpos($verb,ACTIVITY_REACT) !== false)
			return 'Create';
		if (strpos($verb,ACTIVITY_MOOD) !== false)
			return 'Create';

		if (strpos($verb,ACTIVITY_POKE) !== false)
			return 'Activity';

		// We should return false, however this will trigger an uncaught exception  and crash 
		// the delivery system if encountered by the JSON-LDSignature library
 
		logger('Unmapped activity: ' . $verb);
		return 'Create';
		//	return false;
	}


	static function activity_obj_mapper($obj) {


		$objs = [
			'http://activitystrea.ms/schema/1.0/note'           => 'Note',
			'http://activitystrea.ms/schema/1.0/comment'        => 'Note',
			'http://activitystrea.ms/schema/1.0/person'         => 'Person',
			'http://purl.org/zot/activity/profile'              => 'Profile',
			'http://activitystrea.ms/schema/1.0/photo'          => 'Image',
			'http://activitystrea.ms/schema/1.0/profile-photo'  => 'Icon',
			'http://activitystrea.ms/schema/1.0/event'          => 'Event',
			'http://activitystrea.ms/schema/1.0/wiki'           => 'Document',
			'http://purl.org/zot/activity/location'             => 'Place',
			'http://purl.org/zot/activity/chessgame'            => 'Game',
			'http://purl.org/zot/activity/tagterm'              => 'zot:Tag',
			'http://purl.org/zot/activity/thing'                => 'Object',
			'http://purl.org/zot/activity/file'                 => 'zot:File',
			'http://purl.org/zot/activity/mood'                 => 'zot:Mood',
		
		];

		call_hooks('activity_obj_mapper',$objs);

		if ($obj === 'Answer') {
			return 'Note';
		}

		if (strpos($obj,'/') === false) {
			return $obj;
		}

		if (array_key_exists($obj,$objs)) {
			return $objs[$obj];
		}

		logger('Unmapped activity object: ' . $obj);
		return 'Note';

		//	return false;

	}


	static function follow($channel,$act) {

		$contact = null;
		$their_follow_id = null;

		if (intval($channel['channel_system'])) {
			// The system channel ignores all follow requests 
			return;
		}

		/*
		 * 
		 * if $act->type === 'Follow', actor is now following $channel 
		 * if $act->type === 'Accept', actor has approved a follow request from $channel 
		 *	 
		 */

		$person_obj = $act->actor;

		if (in_array($act->type, [ 'Follow', 'Invite', 'Join'])) {
			$their_follow_id  = $act->id;
		}
		elseif ($act->type === 'Accept') {
			$my_follow_id = z_root() . '/follow/' . $contact['id'];
		}
	
		if (is_array($person_obj)) {

			// store their xchan and hubloc

			self::actor_store($person_obj['id'],$person_obj);

			// Find any existing abook record 

			$r = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_xchan = '%s' and abook_channel = %d limit 1",
				dbesc($person_obj['id']),
				intval($channel['channel_id'])
			);
			if ($r) {
				$contact = $r[0];
			}
		}

		$x = PermissionRoles::role_perms('social');
		$p = Permissions::FilledPerms($x['perms_connect']);
		
		// add tag_deliver permissions to remote groups

		if (is_array($person_obj) && $person_obj['type'] === 'Group') {
			$p['tag_deliver'] = 1;
		}

		$their_perms = Permissions::serialise($p);


		if ($contact && $contact['abook_id']) {

			// A relationship of some form already exists on this site. 

			switch($act->type) {

				case 'Follow':
				case 'Invite':
				case 'Join':

					// A second Follow request, but we haven't approved the first one

					if ($contact['abook_pending']) {
						return;
					}

					// We've already approved them or followed them first
					// Send an Accept back to them

					set_abconfig($channel['channel_id'],$person_obj['id'],'activitypub','their_follow_id', $their_follow_id);
					Run::Summon([ 'Notifier', 'permissions_accept', $contact['abook_id'] ]);
					return;

				case 'Accept':

					// They accepted our Follow request - set default permissions
	
					set_abconfig($channel['channel_id'],$contact['abook_xchan'],'system','their_perms',$their_perms);

					$abook_instance = $contact['abook_instance'];
	
					if (strpos($abook_instance,z_root()) === false) {
						if ($abook_instance) 
							$abook_instance .= ',';
						$abook_instance .= z_root();

						$r = q("update abook set abook_instance = '%s', abook_not_here = 0 
							where abook_id = %d and abook_channel = %d",
							dbesc($abook_instance),
							intval($contact['abook_id']),
							intval($channel['channel_id'])
						);
					}
		
					return;
				default:
					return;
	
			}
		}

		// No previous relationship exists.

		if ($act->type === 'Accept') {
			// This should not happen unless we deleted the connection before it was accepted.
			return;
		}

		// From here on out we assume a Follow activity to somebody we have no existing relationship with

		set_abconfig($channel['channel_id'],$person_obj['id'],'activitypub','their_follow_id', $their_follow_id);

		// The xchan should have been created by actor_store() above

		$r = q("select * from xchan where xchan_hash = '%s' and xchan_network = 'activitypub' limit 1",
			dbesc($person_obj['id'])
		);

		if (! $r) {
			logger('xchan not found for ' . $person_obj['id']);
			return;
		}
		$ret = $r[0];
		
		$blocked = LibBlock::fetch($channel['channel_id'],BLOCKTYPE_SERVER);
		if ($blocked) {
			foreach($blocked as $b) {
				if (strpos($ret['xchan_url'],$b['block_entity']) !== false) {
					logger('siteblock - follower denied');
					return;
				}
			}
		}
		if (LibBlock::fetch_by_entity($channel['channel_id'],$ret['xchan_hash'])) {
			logger('actorblock - follower denied');
			return;
		}
	
		$p = Permissions::connect_perms($channel['channel_id']);
		$my_perms  = Permissions::serialise($p['perms']);
		$automatic = $p['automatic'];

		$closeness = PConfig::Get($channel['channel_id'],'system','new_abook_closeness',80);

		$r = abook_store_lowlevel(
			[
				'abook_account'   => intval($channel['channel_account_id']),
				'abook_channel'   => intval($channel['channel_id']),
				'abook_xchan'     => $ret['xchan_hash'],
				'abook_closeness' => intval($closeness),
				'abook_created'   => datetime_convert(),
				'abook_updated'   => datetime_convert(),
				'abook_connected' => datetime_convert(),
				'abook_dob'       => NULL_DATE,
				'abook_pending'   => intval(($automatic) ? 0 : 1),
				'abook_instance'  => z_root()
			]
		);
		
		if ($my_perms)
			AbConfig::Set($channel['channel_id'],$ret['xchan_hash'],'system','my_perms',$my_perms);

		if ($their_perms)
			AbConfig::Set($channel['channel_id'],$ret['xchan_hash'],'system','their_perms',$their_perms);


		if ($r) {
			logger("New ActivityPub follower for {$channel['channel_name']}");

			$new_connection = q("select * from abook left join xchan on abook_xchan = xchan_hash left join hubloc on hubloc_hash = xchan_hash where abook_channel = %d and abook_xchan = '%s' order by abook_created desc limit 1",
				intval($channel['channel_id']),
				dbesc($ret['xchan_hash'])
			);
			if ($new_connection) {
				Enotify::submit(
					[
						'type'	       => NOTIFY_INTRO,
						'from_xchan'   => $ret['xchan_hash'],
						'to_xchan'     => $channel['channel_hash'],
						'link'         => z_root() . '/connedit/' . $new_connection[0]['abook_id'],
					]
				);

				if ($my_perms && $automatic) {
					// send an Accept for this Follow activity
					Run::Summon([ 'Notifier', 'permissions_accept', $new_connection[0]['abook_id'] ]);
					// Send back a Follow notification to them
					Run::Summon([ 'Notifier', 'permissions_create', $new_connection[0]['abook_id'] ]);
				}

				$clone = [];
				foreach ($new_connection[0] as $k => $v) {
					if (strpos($k,'abook_') === 0) {
						$clone[$k] = $v;
					}
				}
				unset($clone['abook_id']);
				unset($clone['abook_account']);
				unset($clone['abook_channel']);
		
				$abconfig = load_abconfig($channel['channel_id'],$clone['abook_xchan']);

				if ($abconfig) {
					$clone['abconfig'] = $abconfig;
				}
				Libsync::build_sync_packet($channel['channel_id'], [ 'abook' => [ $clone ] ] );
			}
		}


		/* If there is a default group for this channel and permissions are automatic, add this member to it */

		if ($channel['channel_default_group'] && $automatic) {
			$g = AccessList::rec_byhash($channel['channel_id'],$channel['channel_default_group']);
			if ($g) {
				AccessList::member_add($channel['channel_id'],'',$ret['xchan_hash'],$g['id']);
			}
		}

		return;

	}


	static function unfollow($channel,$act) {

		$contact = null;

		/* actor is unfollowing $channel */

		$person_obj = $act->actor;

		if (is_array($person_obj)) {

			$r = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_xchan = '%s' and abook_channel = %d limit 1",
				dbesc($person_obj['id']),
				intval($channel['channel_id'])
			);
			if ($r) {
				// remove all permissions they provided
				del_abconfig($channel['channel_id'],$r[0]['xchan_hash'],'system','their_perms',EMPTY_STR);
			}
		}

		return;
	}




	static function actor_store($url, $person_obj, $force = false) {

		if (! is_array($person_obj)) {
			return;
		}

//		logger('person_obj: ' . print_r($person_obj,true));

		// We may have been passed a cached entry. If it is, and the cache duration has expired
		// fetch a fresh copy before continuing.

		if (array_key_exists('cached',$person_obj)) {
			if (array_key_exists('updated',$person_obj) && (datetime_convert('UTC','UTC',$person_obj['updated']) < datetime_convert('UTC','UTC','now - ' . self::$ACTOR_CACHE_DAYS . ' days') || $force)) {
				$person_obj = self::fetch($url);
			}
			else {
				return;
			}
		}

		if (is_array($person_obj) && array_key_exists('movedTo',$person_obj) && $person_obj['movedTo'] && ! is_array($person_obj['movedTo'])) {
			$tgt = self::fetch($person_obj['movedTo']);
			self::actor_store($person_obj['movedTo'],$tgt);
			ActivityPub::move($person_obj['id'],$tgt);
			return;
		}
		
		$url = $person_obj['id'];

		if (! $url) {
			return;
		}

		$name = escape_tags($person_obj['name']);
		if (! $name)
			$name = escape_tags($person_obj['preferredUsername']);
		if (! $name)
			$name = escape_tags( t('Unknown'));

		$username = escape_tags($person_obj['preferredUsername']);
		$h = parse_url($url);
		if ($h && $h['host']) {
			$username .= '@' . $h['host'];
		}

		if ($person_obj['icon']) {
			if (is_array($person_obj['icon'])) {
				if (array_key_exists('url',$person_obj['icon']))
					$icon = $person_obj['icon']['url'];
				else {
					if (is_string($person_obj['icon'][0])) {
						$icon = $person_obj['icon'][0];
					}
					elseif (array_key_exists('url',$person_obj['icon'][0])) {
						$icon = $person_obj['icon'][0]['url'];
					}
				}
			}
			else {
				$icon = $person_obj['icon'];
			}
		}
		if (! (isset($icon) && $icon)) {
			$icon = z_root() . '/' . get_default_profile_photo();
		}

		$hidden = false;
		if (array_key_exists('discoverable',$person_obj) && (! intval($person_obj['discoverable']))) {
			$hidden = true;
		}

		$links = false;
		$profile = false;

		if (is_array($person_obj['url'])) {
			if (! array_key_exists(0,$person_obj['url'])) {
				$links = [ $person_obj['url'] ];
			}
			else {
				$links = $person_obj['url'];
			}
		}

		if ($links) {
			foreach ($links as $link) {
				if (array_key_exists('mediaType',$link) && $link['mediaType'] === 'text/html') {
					$profile = $link['href'];
				}
			}
			if (! $profile) {
				$profile = $links[0]['href'];
			}
		}
		elseif (isset($person_obj['url']) && is_string($person_obj['url'])) {
			$profile = $person_obj['url'];
		}

		if (! $profile) {
			$profile = $url;
		}

		$inbox = ((array_key_exists('inbox',$person_obj)) ? $person_obj['inbox'] : null);

		// either an invalid identity or a cached entry of some kind which didn't get caught above

		if ((! $inbox) || strpos($inbox,z_root()) !== false) {
			return;
		} 


		$collections = [];

		if ($inbox) {
			$collections['inbox'] = $inbox;
			if (array_key_exists('outbox',$person_obj) && is_string($person_obj['outbox'])) {
				$collections['outbox'] = $person_obj['outbox'];
			}
			if (array_key_exists('followers',$person_obj) && is_string($person_obj['followers'])) {
				$collections['followers'] = $person_obj['followers'];
			}
			if (array_key_exists('following',$person_obj) && is_string($person_obj['following'])) {
				$collections['following'] = $person_obj['following'];
			}
			if (array_path_exists('endpoints/sharedInbox',$person_obj) && is_string($person_obj['endpoints']['sharedInbox'])) {
				$collections['sharedInbox'] = $person_obj['endpoints']['sharedInbox'];
			}
		}

		if (isset($person_obj['publicKey']['publicKeyPem'])) {
			if ($person_obj['id'] === $person_obj['publicKey']['owner']) {
				$pubkey = $person_obj['publicKey']['publicKeyPem'];
				if (strstr($pubkey,'RSA ')) {
					$pubkey = Keyutils::rsatopem($pubkey);
				}
			}
		}

		$keywords = [];
		
		if (is_array($person_obj['tag'])) {
			foreach ($person_obj['tag'] as $t) {
				if (is_array($t) && isset($t['type']) && $t['type'] === 'Hashtag') {
					if (isset($t['name'])) {
						$tag = escape_tags((substr($t['name'],0,1) === '#') ? substr($t['name'],1) : $t['name']);
						if ($tag) {
							$keywords[] = $tag;
						}
					}
				}
			}
		}

		$xchan_type = (($person_obj['type'] === 'Group') ? 1 : 0);
		$about = ((isset($person_obj['summary'])) ? html2bbcode(purify_html($person_obj['summary'])) : EMPTY_STR);

		$p = q("select * from xchan where xchan_url = '%s' and xchan_network = 'zot6' limit 1",
			dbesc($url)
		);
		if ($p) {
			set_xconfig($url,'system','protocols','zot6,activitypub');
		}


		$r = q("select * from xchan where xchan_hash = '%s' limit 1",
			dbesc($url)
		);
		if (! $r) {
			// create a new record
			$r = xchan_store_lowlevel(
				[
					'xchan_hash'           => $url,
					'xchan_guid'           => $url,
					'xchan_pubkey'         => $pubkey,
					'xchan_addr'           => ((strpos($username,'@')) ? $username : ''),
					'xchan_url'            => $profile,
					'xchan_name'           => $name,
					'xchan_hidden'         => intval($hidden),
					'xchan_updated'        => datetime_convert(),
					'xchan_name_date'      => datetime_convert(),
					'xchan_network'        => 'activitypub',
					'xchan_type'           => $xchan_type,
					'xchan_photo_date'     => datetime_convert('UTC','UTC','1968-01-01'),
					'xchan_photo_l'        => z_root() . '/' . get_default_profile_photo(),
					'xchan_photo_m'        => z_root() . '/' . get_default_profile_photo(80),
					'xchan_photo_s'        => z_root() . '/' . get_default_profile_photo(48),
					'xchan_photo_mimetype' => 'image/png',					

				]
			);
		}
		else {

			// Record exists. Cache existing records for a set number of days
			// then refetch to catch updated profile photos, names, etc. 

			if ($r[0]['xchan_name_date'] >= datetime_convert('UTC','UTC','now - ' . self::$ACTOR_CACHE_DAYS . ' days') && (! $force)) {
				return;
			}

			// update existing record
			$u = q("update xchan set xchan_updated = '%s', xchan_name = '%s', xchan_pubkey = '%s', xchan_network = '%s', xchan_name_date = '%s', xchan_hidden = %d, xchan_type = %d where xchan_hash = '%s'",
				dbesc(datetime_convert()),
				dbesc($name),
				dbesc($pubkey),
				dbesc('activitypub'),
				dbesc(datetime_convert()),
				intval($hidden),
				intval($xchan_type),
				dbesc($url)
			);

			if (strpos($username,'@') && ($r[0]['xchan_addr'] !== $username)) {
				$r = q("update xchan set xchan_addr = '%s' where xchan_hash = '%s'",
					dbesc($username),
					dbesc($url)
				);
			}
		}

		$m = parse_url($url);
		if ($m['scheme'] && $m['host']) {
			$site_url = $m['scheme'] . '://' . $m['host'];
			$ni = Nodeinfo::fetch($site_url);
			if ($ni && is_array($ni)) {
				$software = ((array_path_exists('software/name',$ni)) ? $ni['software']['name'] : '');
				$version = ((array_path_exists('software/version',$ni)) ? $ni['software']['version'] : '');
				$register = $ni['openRegistrations'];
				
				$site = q("select * from site where site_url = '%s'",
					dbesc($site_url)
				);
				if ($site) {
					q("update site set site_project = '%s', site_update = '%s', site_version = '%s' where site_url = '%s'",
						dbesc($software),
						dbesc(datetime_convert()),
						dbesc($version),
						dbesc($site_url)
					);
					// it may have been saved originally as an unknown type, but we now know what it is
					if (intval($site[0]['site_type']) === SITE_TYPE_UNKNOWN) {
						q("update site set site_type = %d where site_url = '%s'",
							intval(SITE_TYPE_NOTZOT),
							dbesc($site_url)
						);
					}			
				}
				else {
					site_store_lowlevel( 
						[
						'site_url'    => $site_url,
						'site_update' => datetime_convert(),
						'site_dead'   => 0,
						'site_type'   => SITE_TYPE_NOTZOT,
						'site_project' => $software,
						'site_version' => $version,
						'site_access' => (($register) ? ACCESS_FREE : ACCESS_PRIVATE),
						'site_register' => (($register) ? REGISTER_OPEN : REGISTER_CLOSED)
						]
					);
				}
			}
		}

		Libzotdir::import_directory_profile($url,[ 'about' => $about, 'keywords' => $keywords, 'dob' => '0000-00-00' ], null,0,true);

		if ($collections) {
			set_xconfig($url,'activitypub','collections',$collections);
		}

		$h = q("select * from hubloc where hubloc_hash = '%s' limit 1",
			dbesc($url)
		);


		$m = parse_url($url);
		if ($m) {
			$hostname = $m['host'];
			$baseurl = $m['scheme'] . '://' . $m['host'] . (($m['port']) ? ':' . $m['port'] : '');
		}

		if (! $h) {
			$r = hubloc_store_lowlevel(
				[
					'hubloc_guid'     => $url,
					'hubloc_hash'     => $url,
					'hubloc_id_url'   => $profile,
					'hubloc_addr'     => ((strpos($username,'@')) ? $username : ''),
					'hubloc_network'  => 'activitypub',
					'hubloc_url'      => $baseurl,
					'hubloc_host'     => $hostname,
					'hubloc_callback' => $inbox,
					'hubloc_updated'  => datetime_convert(),
					'hubloc_primary'  => 1
				]
			);
		}
		else {
			if (strpos($username,'@') && ($h[0]['hubloc_addr'] !== $username)) {
				$r = q("update hubloc set hubloc_addr = '%s' where hubloc_hash = '%s'",
					dbesc($username),
					dbesc($url)
				);
			}
			if ($inbox !== $h[0]['hubloc_callback']) {
				$r = q("update hubloc set hubloc_callback = '%s' where hubloc_hash = '%s'",
					dbesc($inbox),
					dbesc($url)
				);
			}
			if ($profile !== $h[0]['hubloc_id_url']) {
				$r = q("update hubloc set hubloc_id_url = '%s' where hubloc_hash = '%s'",
					dbesc($profile),
					dbesc($url)
				);
			}
			$r = q("update hubloc set hubloc_updated = '%s' where hubloc_hash = '%s'",
				dbesc(datetime_convert()),
				dbesc($url)
			);
		}

		if (! $icon) {
			$icon = z_root() . '/' . get_default_profile_photo(300);
		}

		// We store all ActivityPub actors we can resolve. Some of them may be able to communicate over Zot6. Find them.
		// Only probe if it looks like it looks something like a zot6 URL as there isn't anything in the actor record which we can reliably use for this purpose
		// and adding zot discovery urls to the actor record will cause federation to fail with the 20-30 projects which don't accept arrays in the url field. 
		
		if (strpos($url,'/channel/') !== false) {
			$zx = q("select * from hubloc where hubloc_id_url = '%s' and hubloc_network = 'zot6'",
				dbesc($url)
			);	
			if (($username) && strpos($username,'@') && (! $zx)) {
				Run::Summon( [ 'Gprobe', bin2hex($username) ] );
			}
		}

		Run::Summon( [ 'Xchan_photo', bin2hex($icon), bin2hex($url) ] );

	}

	static function drop($channel,$observer,$act) {
		$r = q("select * from item where mid = '%s' and uid = %d limit 1",
			dbesc((is_array($act->obj)) ? $act->obj['id'] : $act->obj),
			intval($channel['channel_id'])
		);

		if (! $r) {
			return;
		}

		if (in_array($observer,[ $r[0]['author_xchan'], $r[0]['owner_xchan'] ])) {
			drop_item($r[0]['id'],false);
		}
		elseif (in_array($act->actor['id'],[ $r[0]['author_xchan'], $r[0]['owner_xchan'] ])) {
			drop_item($r[0]['id'],false);
		}

	}


	// sort function width decreasing

	static function vid_sort($a,$b) {
		if ($a['width'] === $b['width'])
			return 0;
		return (($a['width'] > $b['width']) ? -1 : 1);
	}

	static function share_bb($obj) {
		// @fixme - error check and set defaults

		$name = urlencode($obj['actor']['name']);
		$profile = $obj['actor']['id'];
		$photo = $obj['icon']['url'];

		$s = "\r\n[share author='" . $name .
			"' profile='" . $profile .
			"' avatar='" . $photo . 
			"' link='" . $act->obj['id'] .
			"' auth='" . ((is_matrix_url($act->obj['id'])) ? 'true' : 'false' ) . 
			"' posted='" . $act->obj['published'] . 
			"' message_id='" . $act->obj['id'] . 
		"']";

		return $s;
	}

	static function get_actor_bbmention($id) {

		$x = q("select * from hubloc left join xchan on hubloc_hash = xchan_hash where hubloc_hash = '%s' or hubloc_id_url = '%s' limit 1",
			dbesc($id),
			dbesc($id)
		);

		if ($x) {
			// a name starting with a left paren can trick the markdown parser into creating a link so insert a zero-width space
			if (substr($x[0]['xchan_name'],0,1) === '(') {                             
				$x[0]['xchan_name'] = htmlspecialchars_decode('&#8203;',ENT_QUOTES) . $x[0]['xchan_name'];
			}

			return sprintf('@[zrl=%s]%s[/zrl]',$x[0]['xchan_url'],$x[0]['xchan_name']);		
		}
		return '@{' . $id . '}';

	}

	static function update_poll($item,$post) {
		$multi = false;
		$mid = $post['mid'];
		$content = $post['title'];
		
		if (! $item) {
			return false;
		}

		$o = json_decode($item['obj'],true);
		if ($o && array_key_exists('anyOf',$o)) {
			$multi = true;
		}

		$r = q("select mid, title from item where parent_mid = '%s' and author_xchan = '%s' and mid != parent_mid ",
			dbesc($item['mid']),
			dbesc($post['author_xchan'])
		);

		// prevent any duplicate votes by same author for oneOf and duplicate votes with same author and same answer for anyOf
		
		if ($r) {
			if ($multi) {
				foreach ($r as $rv) {
					if ($rv['title'] === $content && $rv['mid'] !== $mid) {
						return false;
					}
				}
			}
			else {
				foreach ($r as $rv) {
					if ($rv['mid'] !== $mid) {
						return false;
					}
				}
			}
		}
			
		$answer_found = false;
		$found = false;
		if ($multi) {
			for ($c = 0; $c < count($o['anyOf']); $c ++) {
				if ($o['anyOf'][$c]['name'] === $content) {
					$answer_found = true;
					if (is_array($o['anyOf'][$c]['replies'])) {
						foreach($o['anyOf'][$c]['replies'] as $reply) {
							if(is_array($reply) && array_key_exists('id',$reply) && $reply['id'] === $mid) {
								$found = true;
							}
						}
					}

					if (! $found) {
						$o['anyOf'][$c]['replies']['totalItems'] ++;
						$o['anyOf'][$c]['replies']['items'][] = [ 'id' => $mid, 'type' => 'Note' ];
					}
				}
			}
		}
		else {
			for ($c = 0; $c < count($o['oneOf']); $c ++) {
				if ($o['oneOf'][$c]['name'] === $content) {
					$answer_found = true;
					if (is_array($o['oneOf'][$c]['replies'])) {
						foreach($o['oneOf'][$c]['replies'] as $reply) {
							if(is_array($reply) && array_key_exists('id',$reply) && $reply['id'] === $mid) {
								$found = true;
							}
						}
					}

					if (! $found) {
						$o['oneOf'][$c]['replies']['totalItems'] ++;
						$o['oneOf'][$c]['replies']['items'][] = [ 'id' => $mid, 'type' => 'Note' ];
					}
				}
			}
		}

		if ($item['comments_closed'] > NULL_DATE) {
			if ($item['comments_closed'] > datetime_convert()) {
				$o['closed'] = datetime_convert('UTC','UTC',$item['comments_closed'], ATOM_TIME);
				// set this to force an update
				$answer_found = true;
			}
		}

		logger('updated_poll: ' . print_r($o,true),LOGGER_DATA);		
		if ($answer_found && ! $found) {			
			$x = q("update item set obj = '%s', edited = '%s' where id = %d",
				dbesc(json_encode($o)),
				dbesc(datetime_convert()),
				intval($item['id'])
			);
			Run::Summon( [ 'Notifier', 'wall-new', $item['id'] ] );
			return true;
		}

		return false;
	}


	static function decode_note($act, $cacheable = false) {

		$response_activity = false;
		$poll_handled = false;
		
		$s = [];

		if (is_array($act->obj)) {
			$binary = false;
			$markdown = false;
			
			if (array_key_exists('mediaType',$act->obj) && $act->obj['mediaType'] !== 'text/html') {
				if ($act->obj['mediaType'] === 'text/markdown') {
					$markdown = true;
				}
				else {
					$s['mimetype'] = escape_tags($act->obj['mediaType']);
					$binary = true;
				}
			}

			$content = self::get_content($act->obj,$binary);

			if ($cacheable) {
				// Zot6 activities will all be rendered from bbcode source in order to generate dynamic content.
				// If the activity came from ActivityPub (hence $cacheable is set), use the HTML rendering
				// and discard the bbcode source since it is unlikely that it is compatible with our implementation.
				// Friendica for example.
				
				unset($content['bbcode']);
			}

			// handle markdown conversion inline (peertube)
			
			if ($markdown) {
				foreach ( [ 'summary', 'content' ] as $t) {
					$content[$t] = Markdown::to_bbcode($content[$t],true, [ 'preserve_lf' => true ]); 
				}
			}
		}

		// These activities should have been handled separately in the Inbox module and should not be turned into posts
		
		if (in_array($act->type, ['Follow', 'Accept', 'Reject', 'Create', 'Update']) && is_array($act->obj) && array_key_exists('type',$act->obj)
			&& ($act->obj['type'] === 'Follow' || ActivityStreams::is_an_actor($act->obj['type']))) {
			return false;
		}

		// Within our family of projects, Follow/Unfollow of a thread is an internal activity which should not be transmitted,
		// hence if we receive it - ignore or reject it.
		// Unfollow is not defined by ActivityStreams, which prefers Undo->Follow.
		// This may have to be revisited if AP projects start using Follow for objects other than actors.
		
		if (in_array($act->type, [ ACTIVITY_FOLLOW, ACTIVITY_UNFOLLOW ])) {
			return false;
		}


		$s['owner_xchan']  = $act->actor['id'];
		$s['author_xchan'] = $act->actor['id'];

		// ensure we store the original actor
		self::actor_store($act->actor['id'],$act->actor);

		$s['mid']        = $act->obj['id'];
		$s['parent_mid'] = $act->parent_id;

		if (array_key_exists('published',$act->data) && $act->data['published']) {
			$s['created'] = datetime_convert('UTC','UTC',$act->data['published']);
		}
		elseif (array_key_exists('published',$act->obj) && $act->obj['published']) {
			$s['created'] = datetime_convert('UTC','UTC',$act->obj['published']);
		}
		if (array_key_exists('updated',$act->data) && $act->data['updated']) {
			$s['edited'] = datetime_convert('UTC','UTC',$act->data['updated']);
		}
		elseif (array_key_exists('updated',$act->obj) && $act->obj['updated']) {
			$s['edited'] = datetime_convert('UTC','UTC',$act->obj['updated']);
		}
		if (array_key_exists('expires',$act->data) && $act->data['expires']) {
			$s['expires'] = datetime_convert('UTC','UTC',$act->data['expires']);
		}
		elseif (array_key_exists('expires',$act->obj) && $act->obj['expires']) {
			$s['expires'] = datetime_convert('UTC','UTC',$act->obj['expires']);
		}

		if ($act->type === 'Invite' && array_key_exists('type',$act->obj) && $act->obj['type'] === 'Event') {
			$s['mid'] = $s['parent_mid'] = $act->id;
		}

		if (ActivityStreams::is_response_activity($act->type)) {

			$response_activity = true;

			$s['mid'] = $act->id;
			$s['parent_mid'] = $act->obj['id'];

//			if (isset($act->replyto) && ! empty($act->replyto)) {
//				$s['replyto'] = $act->replyto;
//			}
			
			// over-ride the object timestamp with the activity

			if ($act->data['published']) {
				$s['created'] = datetime_convert('UTC','UTC',$act->data['published']);
			}

			if ($act->data['updated']) {
				$s['edited'] = datetime_convert('UTC','UTC',$act->data['updated']);
			}

			$obj_actor = ((isset($act->obj['actor'])) ? $act->obj['actor'] : $act->get_actor('attributedTo', $act->obj));

			// We already check for admin blocks of third-party objects when fetching them explicitly.
			// Repeat here just in case the entire object was supplied inline and did not require fetching
			
			if ($obj_actor && array_key_exists('id',$obj_actor)) {
				$m = parse_url($obj_actor['id']);
				if ($m && $m['scheme'] && $m['host']) {
					if (! check_siteallowed($m['scheme'] . '://' . $m['host'])) {
						return;
					}
				}
				if (! check_channelallowed($obj_actor['id'])) {
					return;
				}
			}

			// if the object is an actor, it is not really a response activity, so reset a couple of things
			
			if (ActivityStreams::is_an_actor($act->obj['type'])) {
				$obj_actor = $act->actor;
				$s['parent_mid'] = $s['mid'];
			}


			// ensure we store the original actor
			self::actor_store($obj_actor['id'],$obj_actor);

			$mention = self::get_actor_bbmention($obj_actor['id']);

			$quoted_content = '[quote]' . $content['content'] . '[/quote]';

			if ($act->type === 'Like') {
				$content['content'] = sprintf( t('Likes %1$s\'s %2$s'),$mention, ((ActivityStreams::is_an_actor($act->obj['type'])) ? t('Profile') : $act->obj['type'])) . EOL . EOL . $quoted_content;
			}
			if ($act->type === 'Dislike') {
				$content['content'] = sprintf( t('Doesn\'t like %1$s\'s %2$s'),$mention, ((ActivityStreams::is_an_actor($act->obj['type'])) ? t('Profile') : $act->obj['type'])) . EOL . EOL . $quoted_content;
			}
			
			// handle event RSVPs
			if (($act->obj['type'] === 'Event') || ($act->obj['type'] === 'Invite' && array_path_exists('object/type',$act->obj) && $act->obj['object']['type'] === 'Event')) {
				if ($act->type === 'Accept') {
					$content['content'] = sprintf( t('Will attend %s\'s event'),$mention) . EOL . EOL . $quoted_content;
				}
				if ($act->type === 'Reject') {
					$content['content'] = sprintf( t('Will not attend %s\'s event'),$mention) . EOL . EOL . $quoted_content;
				}
				if ($act->type === 'TentativeAccept') {
					$content['content'] = sprintf( t('May attend %s\'s event'),$mention) . EOL . EOL . $quoted_content;
				}
				if ($act->type === 'TentativeReject') {
					$content['content'] = sprintf( t('May not attend %s\'s event'),$mention) . EOL . EOL . $quoted_content;
				}
			}
			
			if ($act->type === 'Announce') {
				$content['content'] = sprintf( t('&#x1f501; Repeated %1$s\'s %2$s'), $mention, $act->obj['type']);
			}

			if ($act->type === 'emojiReaction') {
				// Hubzilla reactions
				$content['content'] = (($act->tgt && $act->tgt['type'] === 'Image') ? '[img=32x32]' . $act->tgt['url'] . '[/img]' : '&#x' . $act->tgt['name'] . ';');
			}
			
			if (in_array($act->type,[ 'EmojiReaction', 'EmojiReact' ])) {
				// Pleroma reactions
				$t = trim(self::get_textfield($act->data,'content'));
				$e = Emoji\is_single_emoji($t) || mb_strlen($t) === 1;
				if ($e) {
					$content['content'] = $t;
				}	
			}
		}

		if ($s['mid'] === $s['parent_mid']) {
			// it is a parent node - decode the comment policy info if present
			if (isset($act->obj['commentPolicy'])) {
				$until = strpos($act->obj['commentPolicy'],'until=');
				if ($until !== false) {
					$item['comments_closed'] = datetime_convert('UTC','UTC',substr($act->obj['commentPolicy'],'until=') + 6);
					if ($item['comments_closed'] < datetime_convert()) {
						$item['nocomment'] = true;
					}
				}
				$remainder = substr($act->obj['commentPolicy'],0,(($until) ? $until : strlen($act->obj['commentPolicy'])));
				if ($remainder) {
					$item['comment_policy'] = $remainder;
				}
			}
			else {
				$item['comment_policy'] = 'authenticated';
			}
		}

		if (! (array_key_exists('created',$s) && $s['created'])) {
			$s['created'] = datetime_convert();
		}
		if (! (array_key_exists('edited',$s) && $s['edited'])) {
			$s['edited'] = $s['created'];
		}
		$s['title']    = (($response_activity) ? EMPTY_STR : self::bb_content($content,'name'));
		$s['summary']  = self::bb_content($content,'summary');

		if (array_key_exists('mimetype',$s) && (! in_array($s['mimetype'], [ 'text/bbcode', 'text/x-multicode' ]))) {
			$s['body'] = $content['content'];
		}
		else {
			$s['body'] = ((self::bb_content($content,'bbcode') && (! $response_activity)) ? self::bb_content($content,'bbcode') : self::bb_content($content,'content'));
		}


		// handle some of the more widely used of the numerous and varied ways of deleting something
		
		if (in_array($act->type, [ 'Delete', 'Undo', 'Tombstone' ])) {
			$s['item_deleted'] = 1;
		}
		
		if ($act->type === 'Create' && $act->obj['type'] === 'Tombstone') {
			$s['item_deleted'] = 1;
		}

		if ($act->obj && array_key_exists('sensitive',$act->obj) && boolval($act->obj['sensitive'])) {
			$s['item_nsfw'] = 1;
		}

		$s['verb']     = self::activity_mapper($act->type);

		// Mastodon does not provide update timestamps when updating poll tallies which means race conditions may occur here.
		if ($act->type === 'Update' && $act->obj['type'] === 'Question' && $s['edited'] === $s['created']) {
			$s['edited'] = datetime_convert();
		}


		$s['obj_type'] = self::activity_obj_mapper($act->obj['type']);
		$s['obj']      = $act->obj;
		if (is_array($s['obj']) && array_path_exists('actor/id',$s['obj'])) {
			$s['obj']['actor'] = $s['obj']['actor']['id'];
		}

		// @todo add target if present

		$generator = $act->get_property_obj('generator');
		if ((! $generator) && (! $response_activity)) {
			$generator = $act->get_property_obj('generator',$act->obj);
		}

		if ($generator && array_key_exists('type',$generator) 
			&& in_array($generator['type'], [ 'Application','Service' ] ) && array_key_exists('name',$generator)) {
			$s['app'] = escape_tags($generator['name']);
		}

		$location = $act->get_property_obj('location');
		if (is_array($location) && array_key_exists('type',$location) && $location['type'] === 'Place') {
			if (array_key_exists('name',$location)) {
				$s['location'] = escape_tags($location['name']);
			}
			if (array_key_exists('content',$location)) {
				$s['location'] = html2plain(purify_html($location['content']),256);
			}
			
			if (array_key_exists('latitude',$location) && array_key_exists('longitude',$location)) {
				$s['coord'] = escape_tags($location['latitude']) . ' ' . escape_tags($location['longitude']);
			}
		}

		if (! $response_activity) {
			$a = self::decode_taxonomy($act->obj);
			if ($a) {
				$s['term'] = $a;
				foreach ($a as $b) {
					if ($b['ttype'] === TERM_EMOJI) {
						// $s['title'] = str_replace($b['term'],'[img=16x16]' . $b['url'] . '[/img]',$s['title']);
						$s['summary'] = str_replace($b['term'],'[img=16x16]' . $b['url'] . '[/img]',$s['summary']);
						$s['body'] = str_replace($b['term'],'[img=16x16]' . $b['url'] . '[/img]',$s['body']);
					}
				}
			}

			$a = self::decode_attachment($act->obj);
			if ($a) {
				$s['attach'] = $a;
			}

			$a = self::decode_iconfig($act->obj);
			if ($a) {
				$s['iconfig'] = $a;
			}
		}



		if ($act->obj['type'] === 'Note' && $s['attach']) {
			$s['body'] .= self::bb_attach($s['attach'],$s['body']);
		}

		if ($act->obj['type'] === 'Question' && in_array($act->type,['Create','Update'])) {
			if ($act->obj['endTime']) {
				$s['comments_closed'] = datetime_convert('UTC','UTC', $act->obj['endTime']);
			}
		}

		if (array_key_exists('closed',$act->obj) && $act->obj['closed']) {
			$s['comments_closed'] = datetime_convert('UTC','UTC', $act->obj['closed']);
		}			


		// we will need a hook here to extract magnet links e.g. peertube
		// right now just link to the largest mp4 we find that will fit in our
		// standard content region

		if (! $response_activity) {
			if ($act->obj['type'] === 'Video') {

				$vtypes = [
					'video/mp4',
					'video/ogg',
					'video/webm'
				];

				$mps    = [];
				$poster = null;
				$ptr    = null;

				// try to find a poster to display on the video element
				
				if (array_key_exists('icon',$act->obj)) {
					if (is_array($act->obj['icon'])) {
						if (array_key_exists(0,$act->obj['icon'])) {
							$ptr = $act->obj['icon'];
						}
						else {
							$ptr = [ $act->obj['icon'] ];
						}
					}
					if ($ptr) {
						foreach ($ptr as $foo) {
							if (is_array($foo) && array_key_exists('type',$foo) && $foo['type'] === 'Image' && is_string($foo['url'])) {
								$poster = $foo['url'];
							}
						}
					}
				}

				$tag = (($poster) ? '[video poster=&quot;' . $poster . '&quot;]' : '[video]' );
				$ptr = null;
				
				if (array_key_exists('url',$act->obj)) {
					if (is_array($act->obj['url'])) {
						if (array_key_exists(0,$act->obj['url'])) {				
							$ptr = $act->obj['url'];
						}
						else {
							$ptr = [ $act->obj['url'] ];
						}
						// handle peertube's weird url link tree if we find it here
						// 0 => html link, 1 => application/x-mpegURL with 'tag' set to an array of actual media links
						foreach ($ptr as $idex) {
							if (is_array($idex) && array_key_exists('mediaType',$idex)) {
								if ($idex['mediaType'] === 'application/x-mpegURL' && isset($idex['tag']) && is_array($idex['tag'])) {
									$ptr = $idex['tag'];
									break;
								}
							}
						}
						foreach ($ptr as $vurl) {
							if (array_key_exists('mediaType',$vurl)) {
								if (in_array($vurl['mediaType'], $vtypes)) {
									if (! array_key_exists('width',$vurl)) {
										$vurl['width'] = 0;
									}
									$mps[] = $vurl;
								}
							}
						}
					}
					if ($mps) {
						usort($mps,[ __CLASS__, 'vid_sort' ]);
						foreach ($mps as $m) {
							if (intval($m['width']) < 500 && self::media_not_in_body($m['href'],$s['body'])) {
								$s['body'] .= "\n\n" . $tag . $m['href'] . '[/video]';
								break;
							}
						}
					}
					elseif (is_string($act->obj['url']) && self::media_not_in_body($act->obj['url'],$s['body'])) {
						$s['body'] .= "\n\n" . $tag . $act->obj['url'] . '[/video]';
					}
				}
			}

			if ($act->obj['type'] === 'Audio') {

				$atypes = [
					'audio/mpeg',
					'audio/ogg',
					'audio/wav'
				];

				$ptr = null;

				if (array_key_exists('url',$act->obj)) {
					if (is_array($act->obj['url'])) {
						if (array_key_exists(0,$act->obj['url'])) {				
							$ptr = $act->obj['url'];
						}
						else {
							$ptr = [ $act->obj['url'] ];
						}
						foreach ($ptr as $vurl) {
							if (in_array($vurl['mediaType'], $atypes) && self::media_not_in_body($vurl['href'],$s['body'])) {
								$s['body'] .= "\n\n" . '[audio]' . $vurl['href'] . '[/audio]';
								break;
							}
						}
					}
					elseif (is_string($act->obj['url']) && self::media_not_in_body($act->obj['url'],$s['body'])) {
						$s['body'] .= "\n\n" . '[audio]' . $act->obj['url'] . '[/audio]';
					}
				}
				// Pleroma audio scrobbler
				elseif ($act->type === 'Listen' && array_key_exists('artist', $act->obj) && array_key_exists('title',$act->obj) && $s['body'] === EMPTY_STR) {
					$s['body'] .= "\n\n" . sprintf('Listening to \"%1$s\" by %2$s', escape_tags($act->obj['title']), escape_tags($act->obj['artist']));
					if(isset($act->obj['album'])) {
						$s['body'] .= "\n" . sprintf('(%s)', escape_tags($act->obj['album']));
					}
				}
			}

			if ($act->obj['type'] === 'Image' && strpos($s['body'],'zrl=') === false) {

				$ptr = null;

				if (array_key_exists('url',$act->obj)) {
					if (is_array($act->obj['url'])) {
						if (array_key_exists(0,$act->obj['url'])) {				
							$ptr = $act->obj['url'];
						}
						else {
							$ptr = [ $act->obj['url'] ];
						}
						foreach ($ptr as $vurl) {
							if (strpos($s['body'],$vurl['href']) === false) {
								$s['body'] .= "\n\n" . '[zmg]' . $vurl['href'] . '[/zmg]';
								break;
							}
						}
					}
					elseif (is_string($act->obj['url'])) {
						if (strpos($s['body'],$act->obj['url']) === false) {
							$s['body'] .= "\n\n" . '[zmg]' . $act->obj['url'] . '[/zmg]';
						}
					}
				}
			}


			if ($act->obj['type'] === 'Page' && ! $s['body'])  {

				$ptr  = null;
				$purl = EMPTY_STR;

				if (array_key_exists('url',$act->obj)) {
					if (is_array($act->obj['url'])) {
						if (array_key_exists(0,$act->obj['url'])) {				
							$ptr = $act->obj['url'];
						}
						else {
							$ptr = [ $act->obj['url'] ];
						}
						foreach ($ptr as $vurl) {
							if (array_key_exists('mediaType',$vurl) && $vurl['mediaType'] === 'text/html') {
								$purl = $vurl['href'];
								break;
							}
							elseif (array_key_exists('mimeType',$vurl) && $vurl['mimeType'] === 'text/html') {
								$purl = $vurl['href'];
								break;
							}
						}
					}
					elseif (is_string($act->obj['url'])) {
						$purl = $act->obj['url'];
					}
					if ($purl) {
						$li = z_fetch_url(z_root() . '/linkinfo?binurl=' . bin2hex($purl));
						if ($li['success'] && $li['body']) {
							$s['body'] .= "\n" . $li['body'];
						}
						else {
							$s['body'] .= "\n\n" . $purl;
						}
					}
				}
			}
		}



		if (in_array($act->obj['type'],[ 'Note','Article','Page' ])) {
			$ptr = null;

			if (array_key_exists('url',$act->obj)) {
				if (is_array($act->obj['url'])) {
					if (array_key_exists(0,$act->obj['url'])) {				
						$ptr = $act->obj['url'];
					}
					else {
						$ptr = [ $act->obj['url'] ];
					}
					foreach ($ptr as $vurl) {
						if (array_key_exists('mediaType',$vurl) && $vurl['mediaType'] === 'text/html') {
							$s['plink'] = $vurl['href'];
							break;
						}
					}
				}
				elseif (is_string($act->obj['url'])) {
					$s['plink'] = $act->obj['url'];
				}
			}
		}

		if (! $s['plink']) {
			$s['plink'] = $s['mid'];
		}

		// assume this is private unless specifically told otherwise.

		$s['item_private'] = 1;
		
		if ($act->recips && (in_array(ACTIVITY_PUBLIC_INBOX,$act->recips) || in_array('Public',$act->recips) || in_array('as:Public',$act->recips))) {
			$s['item_private'] = 0;
		}

		if (is_array($act->obj)) {
			if (array_key_exists('directMessage',$act->obj) && intval($act->obj['directMessage'])) {
				$s['item_private'] = 2;
			}
		}
		
		set_iconfig($s,'activitypub','recips',$act->raw_recips);

		if (array_key_exists('directMessage',$act->data) && intval($act->data['directMessage'])) {
				$s['item_private'] = 2;
		}

		if ($parent) {
			set_iconfig($s,'activitypub','rawmsg',$act->raw,1);
		}


		// Restrict html caching to ActivityPub senders.
		// Zot has dynamic content and this library is used by both. 
		
		if ($cacheable) {
			if ((! array_key_exists('mimetype',$s)) || (in_array($s['mimetype'], [ 'text/bbcode', 'text/x-multicode' ]))) {
			
				// preserve the original purified HTML content *unless* we've modified $s['body']
				// within this function (to add attachments or reaction descriptions or mention rewrites).
				// This avoids/bypasses some markdown rendering issues which can occur when
				// converting to our markdown-enhanced bbcode and then back to HTML again.
				// Also if we do need bbcode, use the 'bbonly' flag to ignore markdown and only
				// interpret bbcode; which is much less susceptible to false positives in the
				// conversion regexes. 
				
				if ($s['body'] === self::bb_content($content,'content')) {
					$s['html'] = $content['content'];
				}
				else {
					$s['html'] = bbcode($s['body'], [ 'bbonly' => true ]);
				}
			}
		}
		
		$hookinfo = [
			'act' => $act,
			's' => $s
		];

		call_hooks('decode_note',$hookinfo);

		$s = $hookinfo['s'];

		return $s;

	}

	static function rewrite_mentions_sub(&$s, $pref, &$obj = null) {

		if (isset($s['term']) && is_array($s['term'])) {
			foreach ($s['term'] as $tag) {
				$txt = EMPTY_STR;
				if (intval($tag['ttype']) === TERM_MENTION) {
					// some platforms put the identity url into href rather than the profile url. Accept either form.
					$x = q("select * from xchan where xchan_url = '%s' or xchan_hash = '%s' limit 1",
						dbesc($tag['url']),
						dbesc($tag['url'])
					);
					if ($x) {
						switch ($pref) {
							case 0:
								$txt = $x[0]['xchan_name'];
								break;
							case 1:
								$txt = (($x[0]['xchan_addr']) ? $x[0]['xchan_addr'] : $x[0]['xchan_name']);
								break;
							case 2:
							default;
								if ($x[0]['xchan_addr']) {
									$txt = sprintf( t('%1$s (%2$s)'), $x[0]['xchan_name'], $x[0]['xchan_addr']);
								}
								else {
									$txt = $x[0]['xchan_name'];
								}
								break;
						}
					}
				}
				
				if ($txt) {

					// the Markdown filter will get tripped up and think this is a markdown link
					// if $txt begins with parens so put it behind a zero-width space
					if (substr($txt,0,1) === '(') {
						$txt = htmlspecialchars_decode('&#8203;',ENT_QUOTES) . $txt;
					}					
					$s['body'] = preg_replace('/\@\[zrl\=' . preg_quote($x[0]['xchan_url'],'/') . '\](.*?)\[\/zrl\]/ism',
						'@[zrl=' . $x[0]['xchan_url'] . ']' . $txt . '[/zrl]',$s['body']);
					$s['body'] = preg_replace('/\@\[url\=' . preg_quote($x[0]['xchan_url'],'/') . '\](.*?)\[\/url\]/ism',
						'@[url=' . $x[0]['xchan_url'] . ']' . $txt . '[/url]',$s['body']);
					$s['body'] = preg_replace('/\[zrl\=' . preg_quote($x[0]['xchan_url'],'/') . '\]@(.*?)\[\/zrl\]/ism',
						'@[zrl=' . $x[0]['xchan_url'] . ']' . $txt . '[/zrl]',$s['body']);
					$s['body'] = preg_replace('/\[url\=' . preg_quote($x[0]['xchan_url'],'/') . '\]@(.*?)\[\/url\]/ism',
						'@[url=' . $x[0]['xchan_url'] . ']' . $txt . '[/url]',$s['body']);

					// replace these just in case the sender (in this case Friendica) got it wrong
					$s['body'] = preg_replace('/\@\[zrl\=' . preg_quote($x[0]['xchan_hash'],'/') . '\](.*?)\[\/zrl\]/ism',
						'@[zrl=' . $x[0]['xchan_url'] . ']' . $txt . '[/zrl]',$s['body']);
					$s['body'] = preg_replace('/\@\[url\=' . preg_quote($x[0]['xchan_hash'],'/') . '\](.*?)\[\/url\]/ism',
						'@[url=' . $x[0]['xchan_url'] . ']' . $txt . '[/url]',$s['body']);
					$s['body'] = preg_replace('/\[zrl\=' . preg_quote($x[0]['xchan_hash'],'/') . '\]@(.*?)\[\/zrl\]/ism',
						'@[zrl=' . $x[0]['xchan_url'] . ']' . $txt . '[/zrl]',$s['body']);
					$s['body'] = preg_replace('/\[url\=' . preg_quote($x[0]['xchan_hash'],'/') . '\]@(.*?)\[\/url\]/ism',
						'@[url=' . $x[0]['xchan_url'] . ']' . $txt . '[/url]',$s['body']);

					if ($obj && $txt) {
						if (! is_array($obj)) {
							$obj = json_decode($obj,true);
						}
						if (array_path_exists('source/content',$obj)) {
							$obj['source']['content'] = preg_replace('/\@\[zrl\=' . preg_quote($x[0]['xchan_url'],'/') . '\](.*?)\[\/zrl\]/ism',
								'@[zrl=' . $x[0]['xchan_url'] . ']' . $txt . '[/zrl]',$obj['source']['content']);
							$obj['source']['content'] = preg_replace('/\@\[url\=' . preg_quote($x[0]['xchan_url'],'/') . '\](.*?)\[\/url\]/ism',
								'@[url=' . $x[0]['xchan_url'] . ']' . $txt . '[/url]',$obj['source']['content']);
						}
						$obj['content'] = preg_replace('/\@(.*?)\<a (.*?)href\=\"' . preg_quote($x[0]['xchan_url'],'/') . '\"(.*?)\>(.*?)\<\/a\>/ism',
							'@$1<a $2 href="' . $x[0]['xchan_url'] . '"$3>' . $txt . '</a>', $obj['content']);
					}
				}
			}
		}

		// $s['html'] will be populated if caching was enabled.
		// This is usually the case for ActivityPub sourced content, while Zot6 content is not cached.

		if ($s['html']) {
			$s['html'] = bbcode($s['body'], [ 'bbonly' => true ] );
		}

		return;
	}

	static function rewrite_mentions(&$s) {
		// rewrite incoming mentions in accordance with system.tag_username setting
		// 0 - displayname
		// 1 - username
		// 2 - displayname (username)
		// 127 - default
		
		$pref = intval(PConfig::Get($s['uid'],'system','tag_username',Config::Get('system','tag_username',false)));

		if ($pref === 127) {
			return;
		}

		self::rewrite_mentions_sub($s,$pref);


		return;
	}

	// $force is used when manually fetching a remote item - it assumes you are granting one-time
	// permission for the selected item/conversation regardless of your relationship with the author and
	// assumes that you are in fact the sender. Please do not use it for anything else. The only permission
	// checking that is performed is that the author isn't blocked by the site admin.

	static function store($channel,$observer_hash,$act,$item,$fetch_parents = true, $force = false) {

		if ($act && $act->implied_create && ! $force) {
			// This is originally a S2S object with no associated activity
			logger('Not storing implied create activity!');
			return;
		}

		$is_sys_channel = is_sys_channel($channel['channel_id']);
		$is_child_node = false;

		// Pleroma scrobbles can be really noisy and contain lots of duplicate activities. Disable them by default.
		
		if (($act->type === 'Listen') && ($is_sys_channel || get_pconfig($channel['channel_id'],'system','allow_scrobbles',false))) {
			return;
		}

		// Mastodon only allows visibility in public timelines if the public inbox is listed in the 'to' field.
		// They are hidden in the public timeline if the public inbox is listed in the 'cc' field.
		// This is not part of the activitypub protocol - we might change this to show all public posts in pubstream at some point.

		$pubstream = ((is_array($act->obj) && array_key_exists('to', $act->obj) && is_array($act->obj['to']) && (in_array(ACTIVITY_PUBLIC_INBOX, $act->obj['to']) || in_array('Public',$act->obj['to']) || in_array('as:Public',$act->obj['to']))) ? true : false);

		// very unpleasant and imperfect way of determining a Mastodon DM
		
		if ($act->raw_recips && array_key_exists('to',$act->raw_recips) && is_array($act->raw_recips['to']) && count($act->raw_recips['to']) === 1 && $act->raw_recips['to'][0] === channel_url($channel) && ! $act->raw_recips['cc']) {
			$item['item_private'] = 2;
		}


		if ($item['parent_mid'] && $item['parent_mid'] !== $item['mid']) {
			$is_child_node = true;
		}
		
		$allowed = false;
		$reason = [ 'init' ];
		$permit_mentions = intval(PConfig::Get($channel['channel_id'], 'system','permit_all_mentions') && i_am_mentioned($channel,$item));

		if ($is_child_node) {		
			$p = q("select * from item where mid = '%s' and uid = %d and item_wall = 1",
				dbesc($item['parent_mid']),
				intval($channel['channel_id'])
			);
			if ($p) {
				// set the owner to the owner of the parent
				$item['owner_xchan'] = $p[0]['owner_xchan'];
				// check permissions against the author, not the sender
				$allowed = perm_is_allowed($channel['channel_id'],$item['author_xchan'],'post_comments');
				if (! $allowed) {
					$reason[] = 'post_comments perm';
				}
				if ((! $allowed) && $permit_mentions)  {
					if ($p[0]['owner_xchan'] === $channel['channel_hash']) {
						$allowed = false;
						$reason[] = 'ownership';
					}
					else {
						$allowed = true;
					}
				}
				if (absolutely_no_comments($p[0])) {
					$allowed = false;
					$reason[] = 'absolutely';
				}
				
				if (! $allowed) {
					logger('rejected comment from ' . $item['author_xchan'] . ' for ' . $channel['channel_address']);
					logger('rejected reason ' . print_r($reason,true));
					logger('rejected: ' . print_r($item,true), LOGGER_DATA);
					// let the sender know we received their comment but we don't permit spam here.
					self::send_rejection_activity($channel,$item['author_xchan'],$item);
					return;
				}

				if (perm_is_allowed($channel['channel_id'],$item['author_xchan'],'moderated')) {
					$item['item_blocked'] = ITEM_MODERATED;
				}
			}
			else {
				$allowed = true;

				// reject public stream comments that weren't sent by the conversation owner
				// but only on remote message deliveries to our site ($fetch_parents === true)

				if ($is_sys_channel && $pubstream && $item['owner_xchan'] !== $observer_hash && ! $fetch_parents) {
					$allowed = false;
					$reason[] = 'sender ' . $observer_hash . ' not owner ' . $item['owner_xchan'];
				}
			}
			
			if ($p && $p[0]['obj_type'] === 'Question') {
				if ($item['obj_type'] === 'Note' && $item['title'] && (! $item['content'])) {
					$item['obj_type'] = 'Answer';
				}
			}
		}
		else {
			if (perm_is_allowed($channel['channel_id'],$observer_hash,'send_stream') || ($is_sys_channel && $pubstream)) {
				$allowed = true;
			}
			if ($permit_mentions) {
				$allowed = true;
			}
		}

		if (tgroup_check($channel['channel_id'],$item) && (! $is_child_node)) {
			// for forum deliveries, make sure we keep a copy of the signed original
			set_iconfig($item,'activitypub','rawmsg',$act->raw,1);
			$allowed = true;
		}

		if (get_abconfig($channel['channel_id'],$observer_hash,'system','block_announce', false)) {
			if ($item['verb'] === 'Announce' || strpos($item['body'],'[/share]')) {
				$allowed = false;
			}
		}


		if ($is_sys_channel) {

			if (! $pubstream) {
				$allowed = false;
				$reason[] = 'unlisted post delivered to sys channel';
			}

			if (! check_pubstream_channelallowed($observer_hash)) {
				$allowed = false;
				$reason[] = 'pubstream channel blocked';
			}

			// don't allow pubstream posts if the sender even has a clone on a pubstream denied site

			$h = q("select hubloc_url from hubloc where hubloc_hash = '%s'",
				dbesc($observer_hash)
			);
			if ($h) {
				foreach ($h as $hub) {
					if (! check_pubstream_siteallowed($hub['hubloc_url'])) {
						$allowed = false;
						$reason = 'pubstream site blocked';
						break;
					}
				}
			}
			if (intval($item['item_private'])) {
				$allowed = false;
				$reason[] = 'private item';
			}
		}	

		$blocked = LibBlock::fetch($channel['channel_id'],BLOCKTYPE_SERVER);
		if ($blocked) {
			foreach($blocked as $b) {
				if (strpos($observer_hash,$b['block_entity']) !== false) {
					$allowed = false;
					$reason[] = 'blocked';
				}
			}
		}

		if (! $allowed && ! $force) {
			logger('no permission: channel ' . $channel['channel_address'] . ', id = ' . $item['mid']);
			logger('no permission: reason ' . print_r($reason,true));
			return;
		}

		$item['aid'] = $channel['channel_account_id'];
		$item['uid'] = $channel['channel_id'];


		// Some authors may be zot6 authors in which case we want to store their nomadic identity
		// instead of their ActivityPub identity

		$item['author_xchan'] = self::find_best_identity($item['author_xchan']);
		$item['owner_xchan']  = self::find_best_identity($item['owner_xchan']);

		if (! ( $item['author_xchan'] && $item['owner_xchan'])) {
			logger('owner or author missing.');
			return;
		}

		if ($channel['channel_system']) {
			if (! MessageFilter::evaluate($item,get_config('system','pubstream_incl'),get_config('system','pubstream_excl'))) {
				logger('post is filtered');
				return;
			}
		}

		$abook = q("select * from abook where abook_xchan = '%s' and abook_channel = %d limit 1",
			dbesc($observer_hash),
			intval($channel['channel_id'])
		);


		if (! post_is_importable($channel['channel_id'],$item,$abook[0])) {
			logger('post is filtered');
			return;
		}

		if ($act->obj['conversation']) {
			set_iconfig($item,'ostatus','conversation',$act->obj['conversation'],1);
		}


		set_iconfig($item,'activitypub','recips',$act->raw_recips);

		if (! (isset($act->data['inheritPrivacy']) && $act->data['inheritPrivacy'])) {				
			if ($item['item_private']) {
				$item['item_restrict'] = $item['item_restrict'] & 1;
				if ($is_child_node) {
					$item['allow_cid'] = '<' . $channel['channel_hash'] . '>';
					$item['allow_gid'] = $item['deny_cid'] = $item['deny_gid'] = '';
				}
				logger('restricted');
			}				
		}

		if (intval($act->sigok)) {
			$item['item_verified'] = 1;
		}

		$parent = null;
		
		if ($is_child_node) {

			$parent = q("select * from item where mid = '%s' and uid = %d limit 1",
				dbesc($item['parent_mid']),
				intval($item['uid'])
			);
			if (! $parent) {
				if (! get_config('system','activitypub', ACTIVITYPUB_ENABLED)) {
					return;
				}
				else {
					$fetch = false;
					if (intval($channel['channel_system']) || (perm_is_allowed($channel['channel_id'],$observer_hash,'send_stream') && (PConfig::Get($channel['channel_id'],'system','hyperdrive',true) || $act->type === 'Announce'))) {
						$fetch = (($fetch_parents) ? self::fetch_and_store_parents($channel,$observer_hash,$act,$item) : false);
					}
					if ($fetch) {
						$parent = q("select * from item where mid = '%s' and uid = %d limit 1",
							dbesc($item['parent_mid']),
							intval($item['uid'])
						);
					}
					else {
						logger('no parent');
						return;
					}
				}
			}

			$item['comment_policy']  = $parent[0]['comment_policy'];
			$item['item_nocomment']  = $parent[0]['item_nocomment'];
			$item['comments_closed'] = $parent[0]['comments_closed'];
			
			if ($parent[0]['parent_mid'] !== $item['parent_mid']) {
				$item['thr_parent'] = $item['parent_mid'];
			}
			else {
				$item['thr_parent'] = $parent[0]['parent_mid'];
			}
			$item['parent_mid'] = $parent[0]['parent_mid'];
		}

		self::rewrite_mentions($item);

		$r = q("select id, created, edited from item where mid = '%s' and uid = %d limit 1",
			dbesc($item['mid']),
			intval($item['uid'])
		);
		if ($r) {
			if ($item['edited'] > $r[0]['edited']) {
				$item['id'] = $r[0]['id'];
				$x = item_store_update($item);
			}
			else {
				return;
			}
		}
		else {
			$x = item_store($item);
		}

//		if ($fetch_parents && $parent && ! intval($parent[0]['item_private'])) {
//			logger('topfetch', LOGGER_DEBUG);
//			// if the thread owner is a connnection, we will already receive any additional comments to their posts
//			// but if they are not we can try to fetch others in the background
//			$x = q("SELECT abook.*, xchan.* FROM abook left join xchan on abook_xchan = xchan_hash
//				WHERE abook_channel = %d and abook_xchan = '%s' LIMIT 1",
//				intval($channel['channel_id']),
//				dbesc($parent[0]['owner_xchan'])
//			);
//			if (! $x) {
//				// determine if the top-level post provides a replies collection
//				if ($parent[0]['obj']) {
//					$parent[0]['obj'] = json_decode($parent[0]['obj'],true);
//				}
//				logger('topfetch: ' . print_r($parent[0],true), LOGGER_ALL);
//				$id = ((array_path_exists('obj/replies/id',$parent[0])) ? $parent[0]['obj']['replies']['id'] : false);
//				if (! $id) {
//					$id = ((array_path_exists('obj/replies',$parent[0]) && is_string($parent[0]['obj']['replies'])) ? $parent[0]['obj']['replies'] : false);
//				}
//				if ($id) {
//					Run::Summon( [ 'Convo', $id, $channel['channel_id'], $observer_hash ] );
//				}
//			}
//		}

		if (is_array($x) && $x['item_id']) {
			if ($is_child_node) {
				if ($item['owner_xchan'] === $channel['channel_hash']) {
					// We are the owner of this conversation, so send all received comments back downstream
					Run::Summon( [ 'Notifier','comment-import',$x['item_id'] ] );
				}
				$r = q("select * from item where id = %d limit 1",
					intval($x['item_id'])
				);
				if ($r) {
					send_status_notifications($x['item_id'],$r[0]);
				}
			}
			sync_an_item($channel['channel_id'],$x['item_id']);
		}

	}


	static public function find_best_identity($xchan) {
		
		$r = q("select hubloc_hash from hubloc where hubloc_id_url = '%s' limit 1",
			dbesc($xchan)
		);
		if ($r) {
			return $r[0]['hubloc_hash'];
		}
		return $xchan;
	}


	static public function fetch_and_store_parents($channel,$observer_hash,$act,$item) {

		logger('fetching parents');

		$p = [];

		$current_act = $act;
		$current_item = $item;

		while ($current_item['parent_mid'] !== $current_item['mid']) {
			$n = self::fetch($current_item['parent_mid']);
			if (! $n) { 
				break;
			}
			// set client flag to convert objects to implied activities
			$a = new ActivityStreams($n,null,true);
			if ($a->type === 'Announce' && is_array($a->obj)
				&& array_key_exists('object',$a->obj) && array_key_exists('actor',$a->obj)) {
				// This is a relayed/forwarded Activity (as opposed to a shared/boosted object)
				// Reparse the encapsulated Activity and use that instead
				logger('relayed activity',LOGGER_DEBUG);
				$a = new ActivityStreams($a->obj,null,true);
			}

			logger($a->debug(),LOGGER_DATA);

			if (! $a->is_valid()) {
				logger('not a valid activity');
				break;
			}
			if (is_array($a->actor) && array_key_exists('id',$a->actor)) {
				Activity::actor_store($a->actor['id'],$a->actor);
			}

			// ActivityPub sourced items are cacheable
			$item = Activity::decode_note($a,true);

			if (! $item) {
				break;
			}

			$hookinfo = [
		        'a' => $a,
		        'item' => $item
			];

			call_hooks('fetch_and_store',$hookinfo);

			$item = $hookinfo['item'];

			if ($item) {
			
				// don't leak any private conversations to the public stream
				// even if they contain publicly addressed comments/reactions
				
				if (intval($channel['channel_system']) && intval($item['item_private'])) {
					logger('private conversation ignored');
					$p = [];
					break;
				}

				if (count($p) > 100) {
					logger('Conversation overflow');
					$p = [];
					break;
				}
				
				array_unshift($p,[ $a, $item ]);

				if ($item['parent_mid'] === $item['mid']) {
					break;
				}
			}

			$current_act = $a;
			$current_item = $item;
		}

		

		if ($p) {
			foreach ($p as $pv) {
				if ($pv[0]->is_valid()) {
					Activity::store($channel,$observer_hash,$pv[0],$pv[1],false);
				}
			}			
			return true;
		}

		return false;
	}


	// This function is designed to work with both ActivityPub and Zot attachments.
	// The 'type' of each is different ('Image' vs 'image/jpeg' for example).
	// If editing this function please be aware of the need to support both formats
	// which we accomplish here through the use of stripos(). 

	static function bb_attach($attach,$body) {

		$ret = false;

		if (! $attach) {
			return EMPTY_STR;
		}

		foreach ($attach as $a) {
			if (array_key_exists('type',$a) && stripos($a['type'],'image') !== false) {
				if (self::media_not_in_body($a['href'],$body)) {
					if (isset($a['name']) && $a['name']) {
						$alt = htmlspecialchars($a['name'],ENT_QUOTES);
						$ret .= "\n\n" . '[img alt="' . $alt . '"]' . $a['href'] . '[/img]';
					}
					else {
						$ret .= "\n\n" . '[img]' . $a['href'] . '[/img]';
					}
				}
			}
			if (array_key_exists('type',$a) && stripos($a['type'], 'video') !== false) {
				if (self::media_not_in_body($a['href'],$body)) {
					$ret .= "\n\n" . '[video]' . $a['href'] . '[/video]';
				}
			}
			if (array_key_exists('type',$a) && stripos($a['type'], 'audio') !== false) {
				if (self::media_not_in_body($a['href'],$body)) {
					$ret .= "\n\n" . '[audio]' . $a['href'] . '[/audio]';
				}
			}
		}

		return $ret;
	}


	// check for the existence of existing media link in body

	static function media_not_in_body($s,$body) {
		
		if ((strpos($body,']' . $s . '[/img]') === false) && 
			(strpos($body,']' . $s . '[/zmg]') === false) && 
			(strpos($body,']' . $s . '[/video]') === false) && 
			(strpos($body,']' . $s . '[/zvideo]') === false) && 
			(strpos($body,']' . $s . '[/audio]') === false) && 
			(strpos($body,']' . $s . '[/zaudio]') === false)) {
			return true;
		}
		return false;
	}


	static function bb_content($content,$field) {

		$ret = false;

		if (! is_array($content)) {
			btlogger('content not initialised');
			return $ret;
		}
		
		if (array_key_exists($field,$content) && is_array($content[$field])) {
			foreach ($content[$field] as $k => $v) {
				$ret .= html2bbcode($v);
				// save this for auto-translate or dynamic filtering
				// $ret .= '[language=' . $k . ']' . html2bbcode($v) . '[/language]';
			}
		}
		else {
			if ($field === 'bbcode' && array_key_exists('bbcode',$content)) {
				$ret = $content[$field];
			}
			else {
				$ret = html2bbcode($content[$field]);
			}
		}
		if ($field === 'content' && $content['event'] && (! strpos($ret,'[event'))) {
			$ret .= format_event_bbcode($content['event']);
		}

		return $ret;
	}


	static function get_content($act,$binary = false) {

		$content = [];
		$event = null;

		if ((! $act) || (! is_array($act))) {
			return $content;
		}


		if ($act['type'] === 'Event') {
			$adjust = false;
			$event = [];
			$event['event_hash'] = $act['id'];
			if (array_key_exists('startTime',$act) && strpos($act['startTime'],-1,1) === 'Z') {
				$adjust = true;
				$event['adjust'] = 1;
				$event['dtstart'] = datetime_convert('UTC','UTC',$event['startTime'] . (($adjust) ? '' : 'Z'));
			}
			if (array_key_exists('endTime',$act)) {
				$event['dtend'] = datetime_convert('UTC','UTC',$event['endTime'] . (($adjust) ? '' : 'Z'));
			}
			else {
				$event['nofinish'] = true;
			}

			if (array_key_exists('eventRepeat',$act)) {
				$event['event_repeat'] = $act['eventRepeat'];
			}
		}                         

		foreach ([ 'name', 'summary', 'content' ] as $a) {
			if (($x = self::get_textfield($act,$a,$binary)) !== false) {
				$content[$a] = $x;
			}
			if (isset($content['name'])) {
				$content['name'] = html2plain(purify_html($content['name']),256);
			}
		}

		if ($event && ! $binary) {
			$event['summary'] = html2plain(purify_html($content['summary']),256);
			if (! $event['summary']) {
				if ($content['name']) {
					$event['summary'] = html2plain(purify_html($content['name']),256);
				}
			}
			if (! $event['summary']) {
				if ($content['content']) {
					$event['summary'] = html2plain(purify_html($content['content']),256);
				}
			}
			if ($event['summary']) {
				$event['summary'] = substr($event['summary'],0,256);
			}
			$event['description'] = html2bbcode($content['content']);
			if ($event['summary'] && $event['dtstart']) {
				$content['event'] = $event;
			}
		}

		if (array_path_exists('source/mediaType',$act) && array_path_exists('source/content',$act)) {
			if (in_array($act['source']['mediaType'], [ 'text/bbcode', 'text/x-multicode' ])) {
				if (is_string($act['source']['content']) && strpos($act['source']['content'],'<') !== false) {
					$content['bbcode'] = multicode_purify($act['source']['content']);
				}
				else {
					$content['bbcode'] = purify_html($act['source']['content'], [ 'escape' ] );
				}
			}
		}

		return $content;
	}


	static function get_textfield($act,$field,$binary = false) {
	
		$content = false;

		if (array_key_exists($field,$act) && $act[$field])
			$content = (($binary) ? $act[$field] : purify_html($act[$field]));
		elseif (array_key_exists($field . 'Map',$act) && $act[$field . 'Map']) {
			foreach ($act[$field . 'Map'] as $k => $v) {
				$content[escape_tags($k)] = (($binary) ? $v : purify_html($v));
			}
		}
		return $content;
	}

	static function send_rejection_activity($channel,$observer_hash,$item) {

		$recip = q("select * from hubloc where hubloc_hash = '%s' limit 1",
			dbesc($observer_hash)
		);
		if (! $recip) {
			return;
		}

		$arr = [
			'id'     => z_root() . '/bounces/' . new_uuid(),
			'to'     => [ $observer_hash ],
			'type'   => 'Reject',
			'actor'  => channel_url($channel),
			'name'   => 'Permission denied',
			'object' => $item['message_id']
		];
		
		$msg = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			self::ap_schema()
		]], $arr);

		$queue_id = ActivityPub::queue_message(json_encode($msg, JSON_UNESCAPED_SLASHES),$channel,$recip[0]);
		do_delivery( [ $queue_id ] );
		
	}

	// Find either an Authorization: Bearer token or 'token' request variable
	// in the current web request and return it

	static function token_from_request() {

		foreach ( [ 'REDIRECT_REMOTE_USER', 'HTTP_AUTHORIZATION' ] as $s ) {		
			$auth = ((array_key_exists($s,$_SERVER) && strpos($_SERVER[$s],'Bearer ') === 0)
				? str_replace('Bearer ', EMPTY_STR, $_SERVER[$s])
				: EMPTY_STR
			);
			if ($auth) {
				break;
			}
		}

		if (! $auth) {
			if (array_key_exists('token',$_REQUEST) && $_REQUEST['token']) {
				$auth = $_REQUEST['token'];
			}
		}

		return $auth;
	}

	static function ap_schema() {

		return [
			'zot'                       => z_root() . '/apschema#',
//			'as'                        => 'https://www.w3.org/ns/activitystreams#',
			'toot'                      => 'http://joinmastodon.org/ns#',
			'ostatus'                   => 'http://ostatus.org#',
			'schema'                    => 'http://schema.org#',
			'conversation'              => 'ostatus:conversation',
			'manuallyApprovesFollowers' => 'as:manuallyApprovesFollowers',
			'sensitive'                 => 'as:sensitive',
			'movedTo'                   => 'as:movedTo',
			'copiedTo'                  => 'as:copiedTo',
			'alsoKnownAs'               => 'as:alsoKnownAs',
			'inheritPrivacy'            => 'as:inheritPrivacy',
			'EmojiReact'                => 'as:EmojiReact',
			'commentPolicy'             => 'zot:commentPolicy',
			'topicalCollection'         => 'zot:topicalCollection',
			'eventRepeat'               => 'zot:eventRepeat',
			'emojiReaction'             => 'zot:emojiReaction',
			'expires'                   => 'zot:expires',
			'directMessage'             => 'zot:directMessage',
			'Category'                  => 'zot:Category',
			'replyTo'                   => 'zot:replyTo',
			'PropertyValue'             => 'schema:PropertyValue',
			'value'                     => 'schema:value',
			'discoverable'              => 'toot:discoverable',
		];

	}

}
