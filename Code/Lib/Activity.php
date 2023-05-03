<?php

namespace Code\Lib;

use App;
use Code\Access\PermissionLimits;
use Code\Web\HTTPSig;
use Code\Access\Permissions;
use Code\Access\PermissionRoles;
use Code\Daemon\Run;
use Code\Lib as Zlib;
use Code\Extend\Hook;
use Emoji;

require_once('include/html2bbcode.php');
require_once('include/html2plain.php');
require_once('include/event.php');

class Activity
{

    public static $ACTOR_CACHE_DAYS = 3;

    // Turn $element into an array if it isn't already.
    public static function force_array($element) {
        if (empty($element)) {
            return [];
        }
        return (is_array($element)) ? $element : [$element];
    }

    // $x (string|array)
    // if json string, decode it
    // returns activitystreams object as an array except if it is a URL
    // which returns the URL as string

    public static function encode_object($x)
    {

        if ($x) {
            if (is_string($x)) {
                $tmp = json_decode($x, true);
                if ($tmp !== null) {
                    $x = $tmp;
                }
            }
        }

        if (is_string($x)) {
            return ($x);
        }

        if ($x['type'] === ACTIVITY_OBJ_PERSON) {
            return self::fetch_person($x);
        }
        if ($x['type'] === ACTIVITY_OBJ_PROFILE) {
            return self::fetch_profile($x);
        }
        if (in_array($x['type'], [ACTIVITY_OBJ_NOTE, ACTIVITY_OBJ_ARTICLE])) {
            // Use Mastodon-specific note and media hacks if nomadic. Else HTML.
            // Eventually this needs to be passed in much further up the stack
            // and base the decision on whether we are encoding for
            // ActivityPub or Zot6 or Nomad

            return self::fetch_item($x, (bool)get_config('system', 'activitypub', ACTIVITYPUB_ENABLED));
        }
        if ($x['type'] === ACTIVITY_OBJ_THING) {
            return self::fetch_thing($x);
        }

        Hook::call('encode_object', $x);

        return $x;
    }


    public static function fetch($url, $channel = null, $must_verify = false, $debug = false)
    {
        if (!$url) {
            return null;
        }
        if (!check_siteallowed($url)) {
            logger('denied: ' . $url);
            return null;
        }
        if (!$channel) {
            $channel = Channel::get_system();
        }

        $parsed = parse_url($url);

        // perform IDN substitution

        if (isset($parsed['host']) && $parsed['host'] !== punify($parsed['host'])) {
            $url = str_replace($parsed['host'], punify($parsed['host']), $url);
        }

        logger('fetch: ' . $url, LOGGER_DEBUG);

        // handle bearcaps
        if (isset($parsed['scheme']) && isset($parsed['query']) && $parsed['scheme'] === 'bear' && $parsed['query'] !== EMPTY_STR) {
            $params = explode('&', $parsed['query']);
            if ($params) {
                foreach ($params as $p) {
                    if (str_starts_with($p, 'u=')) {
                        $url = substr($p, 2);
                    }
                    if (str_starts_with($p, 't=')) {
                        $token = substr($p, 2);
                    }
                }
                // the entire URL just changed so parse it again
                $parsed = parse_url($url);
            }
        }

        // Ignore fragments; as we are not in a browser.
        unset($parsed['fragment']);

        // rebuild the url
        $url = unparse_url($parsed);

        logger('fetch_actual: ' . $url, LOGGER_DEBUG);

        $default_accept_header = 'application/activity+json, application/x-zot-activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
        
        if ($channel) {
            $accept_header = PConfig::Get($channel['channel_id'],'system','accept_header');
        }
        if (!$accept_header) {
            $accept_header = Config::Get('system', 'accept_header', $default_accept_header);
        }
        $headers = [
            'Accept' => $accept_header,
            'Host' => $parsed['host'],
            'Date' => datetime_convert('UTC', 'UTC', 'now', 'D, d M Y H:i:s \\G\\M\\T'),
            '(request-target)' => 'get ' . get_request_string($url)
        ];

        if (isset($token)) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        $h = HTTPSig::create_sig($headers, $channel['channel_prvkey'], Channel::keyId($channel));
        $x = Url::get($url, ['headers' => $h]);

        if ($x['success']) {
            $y = json_decode($x['body'], true);
            logger('returned: ' . json_encode($y, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOGGER_DEBUG);

            $site_url = unparse_url(['scheme' => $parsed['scheme'], 'host' => $parsed['host'], 'port' => ((array_key_exists('port', $parsed) && intval($parsed['port'])) ? $parsed['port'] : 0)]);
            q(
                "update site set site_update = '%s' where site_url = '%s' and site_update < %s - INTERVAL %s",
                dbesc(datetime_convert()),
                dbesc($site_url),
                db_utcnow(),
                db_quoteinterval('1 DAY')
            );

            // check for a valid signature, but only if this is not an actor object. If it is signed, it must be valid.
            // Ignore actors because of the potential for infinite recursion if we perform this step while
            // fetching an actor key to validate a signature elsewhere. This should validate relayed activities
            // over litepub which arrived at our inbox that do not use LD signatures

            if (($y['type']) && (!ActivityStreams::is_an_actor($y['type']))) {
                $sigblock = HTTPSig::verify($x);
                if ($must_verify && !$sigblock['header_signed']) {
                    return null;
                }
                if (($sigblock['header_signed']) && (!$sigblock['header_valid'])) {
                    if ($debug) {
                        return array_merge($x, $sigblock);
                    }
                    return null;
                }
            }

            return json_decode($x['body'], true);
        }
        else {
            logger('fetch failed: ' . $url);
            if ($debug) {
                return $x;
            }
        }
        return null;
    }


    public static function fetch_person($x)
    {
        return self::fetch_profile($x);
    }

    public static function fetch_profile($x)
    {
        $r = q(
            "select * from xchan left join hubloc on xchan_hash = hubloc_hash where hubloc_id_url = '%s' limit 1",
            dbesc($x['id'])
        );
        if (!$r) {
            $r = q(
                "select * from xchan where xchan_hash = '%s' limit 1",
                dbesc($x['id'])
            );
        }
        if (!$r) {
            return [];
        }

        return self::encode_person($r[0], false);
    }

    public static function fetch_thing($x): array
    {

        $r = q(
            "select * from obj where obj_type = %d and obj_obj = '%s' limit 1",
            intval(TERM_OBJ_THING),
            dbesc($x['id'])
        );

        if (!$r) {
            return [];
        }

        $x = [
            'type' => 'Object',
            'id' => z_root() . '/thing/' . $r[0]['obj_obj'],
            'name' => $r[0]['obj_term']
        ];

        if ($r[0]['obj_image']) {
            $x['image'] = $r[0]['obj_image'];
        }
        return $x;
    }

    public static function fetch_item($x, $activitypub = false)
    {

        if (array_key_exists('source', $x)) {
            // This item is already processed and encoded
            return $x;
        }

        $r = q(
            "select * from item where mid = '%s' limit 1",
            dbesc($x['id'])
        );
        if ($r) {
            xchan_query($r);
            $r = fetch_post_tags($r);
            if ($r[0]['verb'] === 'Invite') {
                return self::encode_activity($r[0], $activitypub);
            }
            return self::encode_item($r[0], $activitypub);
        }
        return false;
    }

    public static function paged_collection_init($total, $id, $type = 'OrderedCollection'): array
    {

        $ret = [
            'id' => z_root() . '/' . $id,
            'type' => $type,
            'totalItems' => $total,
        ];

        $numpages = $total / App::$pager['itemspage'];
        $lastpage = (($numpages > intval($numpages)) ? intval($numpages) + 1 : $numpages);

        $ret['first'] = z_root() . '/' . App::$query_string . '?page=1';
        $ret['last'] = z_root() . '/' . App::$query_string . '?page=' . $lastpage;

        return $ret;
    }


    public static function encode_item_collection($items, $id, $type, $activitypub = false, $total = 0): array
    {
        if ($total > App::$pager['itemspage']) {
            $ret = [
                'id' => z_root() . '/' . $id,
                'type' => $type . 'Page',
            ];

            $numpages = $total / App::$pager['itemspage'];
            $lastpage = (($numpages > intval($numpages)) ? intval($numpages) + 1 : $numpages);

            $url_parts = parse_url($id);

            $ret['partOf'] = z_root() . '/' . $url_parts['path'];

            $extra_query_args = '';
            $query_args = [];
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
        } else {
            $ret = [
                'id' => z_root() . '/' . $id,
                'type' => $type,
                'totalItems' => $total,
            ];
        }


        if ($items) {
            $x = [];
            foreach ($items as $i) {
                //$m = get_iconfig($i['id'], 'activitypub', 'rawmsg');
                $m = ObjCache::Get($i['mid']);
                if ($m) {
                    $t = json_decode($m, true);
                } else {
                    $t = self::encode_activity($i, $activitypub);
                }
                if ($t) {
                    $x[] = $t;
                }
            }
            if ($type === 'OrderedCollection') {
                $ret['orderedItems'] = $x;
            } else {
                $ret['items'] = $x;
            }
        }

        return $ret;
    }

    public static function encode_follow_collection($items, $id, $type, $total = 0, $extra = null): array
    {

        if ($total > 100) {
            $ret = [
                'id' => z_root() . '/' . $id,
                'type' => $type . 'Page',
            ];

            $numpages = $total / App::$pager['itemspage'];
            $lastpage = (($numpages > intval($numpages)) ? intval($numpages) + 1 : $numpages);

            $stripped = preg_replace('/([&|?]page=[0-9]*)/', '', $id);
            $stripped = rtrim($stripped, '/');

            $ret['partOf'] = z_root() . '/' . $stripped;

            if (App::$pager['page'] < $lastpage) {
                $ret['next'] = z_root() . '/' . $stripped . '?page=' . (intval(App::$pager['page']) + 1);
            }
            if (App::$pager['page'] > 1) {
                $ret['prev'] = z_root() . '/' . $stripped . '?page=' . (intval(App::$pager['page']) - 1);
            }
        } else {
            $ret = [
                'id' => z_root() . '/' . $id,
                'type' => $type,
                'totalItems' => $total,
            ];
        }

        if ($extra) {
            $ret = array_merge($ret, $extra);
        }

        if ($items) {
            $x = [];
            foreach ($items as $i) {
                if ($i['xchan_network'] === 'activitypub') {
                    $x[] = $i['xchan_hash'];
                } else {
                    $x[] = $i['xchan_url'];
                }
            }

            if ($type === 'OrderedCollection') {
                $ret['orderedItems'] = $x;
            } else {
                $ret['items'] = $x;
            }
        }

        return $ret;
    }


    public static function encode_simple_collection($items, $id, $type, $total = 0, $extra = null): array
    {

        $ret = [
            'id' => z_root() . '/' . $id,
            'type' => $type,
            'totalItems' => $total,
        ];

        if ($extra) {
            $ret = array_merge($ret, $extra);
        }

        if ($items) {
            if ($type === 'OrderedCollection') {
                $ret['orderedItems'] = $items;
            } else {
                $ret['items'] = $items;
            }
        }

        return $ret;
    }


    public static function decode_taxonomy($item)
    {

        $ret = [];

        if (array_key_exists('tag', $item) && is_array($item['tag'])) {
            $ptr = $item['tag'];
            if (!array_key_exists(0, $ptr)) {
                $ptr = [$ptr];
            }
            foreach ($ptr as $t) {
                if (!is_array($t)) {
                    continue;
                }
                if (!array_key_exists('type', $t)) {
                    $t['type'] = 'Hashtag';
                }

                if ($t['type'] === 'Link' && isset($t['href']) && isset($t['mediaType']) && str_contains($t['mediaType'], 'activity')) {
                    $entry = ['ttype' => TERM_QUOTED, 'url' => $t['href']];
                    $entry['term'] = ((!empty($t['name'])) ? escape_tags($t['name']) : t('Quoted post'));
                    $ret[] = $entry;
                    continue;
                }

                if (!(array_key_exists('name', $t))) {
                    continue;
                }
                if (!(array_path_exists('icon/url', $t) || array_key_exists('href', $t))) {
                    continue;
                }

                switch ($t['type']) {
                    case 'Hashtag':
                        $ret[] = ['ttype' => TERM_HASHTAG, 'url' => $t['href'], 'term' => escape_tags((str_starts_with($t['name'], '#')) ? substr($t['name'], 1) : $t['name'])];
                        break;

                    case 'Category':
                        $ret[] = ['ttype' => TERM_CATEGORY, 'url' => $t['href'], 'term' => escape_tags($t['name'])];
                        break;

                    case 'Mention':
                        $mention_type = substr($t['name'], 0, 1);
                        if ($mention_type === '!') {
                            $ret[] = ['ttype' => TERM_FORUM, 'url' => $t['href'], 'term' => escape_tags(substr($t['name'], 1))];
                        } else {
                            $ret[] = ['ttype' => TERM_MENTION, 'url' => $t['href'], 'term' => escape_tags((str_starts_with($t['name'], '@')) ? substr($t['name'], 1) : $t['name'])];
                        }
                        break;

                    case 'Emoji':
                        $ret[] = ['ttype' => TERM_EMOJI, 'url' => $t['icon']['url'], 'term' => escape_tags($t['name'])];
                        break;

                    default:
                        break;
                }
            }
        }

        return $ret;
    }


    public static function encode_taxonomy($item)
    {

        $ret = [];

        if (isset($item['term']) && is_array($item['term']) && $item['term']) {
            foreach ($item['term'] as $t) {
                switch ($t['ttype']) {
                    case TERM_QUOTED:
                        if ($t['url'] && $t['term']) {
                            $ret[] = ['type' => 'Link', 'href' => $t['url'], 'name' => $t['term'],
                                'mediaType' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ];
                        }
                        break;
                    case TERM_HASHTAG:
                        // An id is required so if there is no url in the taxonomy, ignore it and keep going.
                        if ($t['url']) {
                            $ret[] = [ 'type' => 'Hashtag', 'id' => $t['url'], 'name' => '#' . $t['term']];
                        }
                        break;

                    case TERM_CATEGORY:
                        if ($t['url'] && $t['term']) {
                            $ret[] = ['type' => 'Category', 'href' => $t['url'], 'name' => $t['term']];
                        }
                        break;

                    case TERM_FORUM:
                        $term = self::lookup_term_addr($t['url'], $t['term']);
                        $ret[] = ['type' => 'Mention', 'href' => $t['url'], 'name' => '!' . (($term) ?: $t['term'])];
                        break;

                    case TERM_MENTION:
                        $term = self::lookup_term_addr($t['url'], $t['term']);
                        $ret[] = ['type' => 'Mention', 'href' => $t['url'], 'name' => '@' . (($term) ?: $t['term'])];
                        break;

                    default:
                        break;
                }
            }
        }

        return $ret;
    }


    public static function lookup_term_addr($url, $name)
    {

        // The visible mention in our activities is always the full name.
        // In the object taxonomy change this to the webfinger handle in case
        // platforms expect the Mastodon form in order to generate notifications
        // Try a couple of different things in case the url provided isn't the canonical id.
        // If all else fails, try to match the name.

        if ($url) {
            $r = q(
                "select xchan_addr from xchan where ( xchan_url = '%s' OR xchan_hash = '%s' ) limit 1",
                dbesc($url),
                dbesc($url)
            );

            if ($r) {
                return $r[0]['xchan_addr'];
            }
        }
        if ($name) {
            $r = q(
                "select xchan_addr from xchan where xchan_name = '%s' limit 1",
                dbesc($name)
            );
            if ($r) {
                return $r[0]['xchan_addr'];
            }
        }
        return EMPTY_STR;
    }


    public static function lookup_term_url($url)
    {

        // The xchan_url for mastodon is a text/html rendering. This is called from map_mentions where we need
        // to convert the mention url to an ActivityPub id. If this fails for any reason, return the url we have

        $r = hubloc_id_query($url, 1);

        if ($r) {
            if ($r[0]['hubloc_network'] === 'activitypub') {
                return $r[0]['hubloc_hash'];
            }
            return $r[0]['hubloc_id_url'];
        }

        return $url;
    }

    /**
     * Convert an item attach list to an ActivityStreams attachment array
     */

    public static function encode_attachment($item)
    {

        $ret = [];

        if (array_key_exists('attach', $item)) {
            $atts = ((is_array($item['attach'])) ? $item['attach'] : json_decode($item['attach'], true));
            if ($atts) {
                foreach ($atts as $att) {
                    $name = '';
                    if (isset($att['type']) && str_contains($att['type'], 'image')) {
                        if (!empty($att['href'])) {
                            if ($att['name']) {
                                $name = $att['name'];
                            }
                            if (! $name) {
                                $name = $att['title'];
                            }
                            $ret[] = [
                                'type' => 'Image',
                                'url' => $att['href'],
                                'name' => $name,
                            ];
                        }
                    } else {
                        $ret[] = [
                            'type' => 'Link',
                            'mediaType' => $att['type'] ?? 'application/octet-stream',
                            'href' => $att['href'] ?? ''
                        ];
                    }
                }
            }
        }
        if (array_key_exists('iconfig', $item) && is_array($item['iconfig'])) {
            foreach ($item['iconfig'] as $att) {
                if ($att['sharing']) {
                    $ret[] = ['type' => 'PropertyValue', 'name' => 'nomad.' . $att['cat'] . '.' . $att['k'], 'value' => unserialise($att['v'])];
                }
            }
        }

        return $ret;
    }


    public static function decode_iconfig($item)
    {

        $ret = [];

        if (isset($item['attachment']) && is_array($item['attachment']) && $item['attachment']) {
            $ptr = $item['attachment'];
            if (!array_key_exists(0, $ptr)) {
                $ptr = [$ptr];
            }
            foreach ($ptr as $att) {
                $entry = [];
                if ($att['type'] === 'PropertyValue') {
                    if (array_key_exists('name', $att) && $att['name']) {
                        $key = explode('.', $att['name']);
                        if (count($key) === 3 && in_array($key[0], ['nomad', 'zot'])) {
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

    /**
     * Decodes and Activity object attachment structure to store in an item.
     */

    public static function decode_attachment($obj)
    {

        $ret = [];

        if (array_key_exists('attachment', $obj) && is_array($obj['attachment'])) {
            $ptr = $obj['attachment'];
            if (!array_key_exists(0, $ptr)) {
                $ptr = [$ptr];
            }
            foreach ($ptr as $att) {
                $entry = [];
                if (array_key_exists('href', $att) && $att['href']) {
                    $entry['href'] = $att['href'];
                } elseif (array_key_exists('url', $att) && $att['url']) {
                    $entry['href'] = $att['url'];
                }
                if (array_key_exists('mediaType', $att) && $att['mediaType']) {
                    $entry['type'] = $att['mediaType'];
                } elseif (array_key_exists('type', $att) && $att['type'] === 'Image') {
                    $entry['type'] = 'image/jpeg';
                }
                if (array_key_exists('name', $att) && $att['name']) {
                    $entry['name'] = html2plain(purify_html($att['name']), 256);
                }
                // Friendica attachments don't match the URL in the body.
                // This makes it more difficult to detect image duplication in bb_attach()
                // which adds images to plaintext microblog software. For these we need to examine both the
                // url and image properties.
                if (isset($att['image']) && is_string($att['image']) && isset($att['url']) && $att['image'] !== $att['url']) {
                    $entry['image'] = $att['image'];
                }
                if ($entry) {
                    $ret[] = $entry;
                }
            }
        } elseif (isset($obj['attachment']) && is_string($obj['attachment'])) {
            btlogger('not an array: ' . $obj['attachment']);
        }

        return $ret;
    }


    // the $recurse flag encodes the original non-deleted object of a deleted activity

    public static function encode_activity($item, $activitypub = false, $recurse = false)
    {

        $activity = [];

        if (intval($item['item_deleted']) && (!$recurse)) {
            $is_response = ActivityStreams::is_response_activity($item['verb']);

            if ($is_response) {
                $activity['type'] = 'Undo';
                $fragment = '#undo';
            } else {
                $activity['type'] = 'Delete';
                $fragment = '#delete';
            }

            $activity['id'] = str_replace('/item/', '/activity/', $item['mid']) . $fragment;
            $actor = self::encode_person($item['author'], false);
            if ($actor) {
                $activity['actor'] = $actor;
            } else {
                return [];
            }

            $obj = (($is_response) ? self::encode_activity($item, $activitypub, true) : self::encode_item($item, $activitypub));
            if ($obj) {
                if (array_path_exists('object/id', $obj)) {
                    $obj['object'] = $obj['object']['id'];
                }
                if ($obj) {
                    $activity['object'] = $obj;
                }
            } else {
                return [];
            }

            $activity['to'] = [ACTIVITY_PUBLIC_INBOX];
            return $activity;
        }

        $activity['type'] = self::activity_mapper($item['verb']);

        if (str_contains($item['mid'], z_root() . '/item/')) {
            $activity['id'] = str_replace('/item/', '/activity/', $item['mid']);
        } elseif (str_contains($item['mid'], z_root() . '/event/')) {
            $activity['id'] = str_replace('/event/', '/activity/', $item['mid']);
        } else {
            $activity['id'] = $item['mid'];
        }

        if ($item['title']) {
            $activity['name'] = $item['title'];
        }

        if ($item['summary']) {
            $activity['summary'] = bbcode($item['summary'], ['export' => true]);
        }

        if ($activity['type'] === 'Announce') {
            $tmp = $item['body'];
            $activity['content'] = bbcode($tmp, ['export' => true]);
            $activity['source'] = [
                'content' => $item['body'],
                'mediaType' => 'text/x-multicode'
            ];
            if ($item['summary']) {
                $activity['source']['summary'] = $item['summary'];
            }
        }

        $activity['published'] = datetime_convert('UTC', 'UTC', $item['created'], ATOM_TIME);
        if ($item['created'] !== $item['edited']) {
            $activity['updated'] = datetime_convert('UTC', 'UTC', $item['edited'], ATOM_TIME);
            if ($activity['type'] === 'Create') {
                $activity['type'] = 'Update';
            }
        }
        if ($item['app']) {
            $activity['generator'] = ['type' => 'Application', 'name' => $item['app']];
        }
        if ($item['location'] || $item['lat'] || $item['lon']) {
            $activity['location'] = ['type' => 'Place'];
            if ($item['location']) {
                $activity['location']['name'] = $item['location'];
            }

            if ($item['lat'] || $item['lon']) {
                $activity['location']['latitude'] = $item['lat'] ?? 0;
                $activity['location']['longitude'] = $item['lon'] ?? 0;
            }
        }

        if ($item['mid'] === $item['parent_mid']) {
            $activity['isContainedConversation'] = true;
        }
        else {
            // inReplyTo needs to be set in the activity for followup actions (Like, Dislike, Announce, etc.),
            // but *not* for comments and RSVPs, where it should only be present in the object

            if (!in_array($activity['type'], ['Create', 'Update', 'Accept', 'Reject', 'TentativeAccept', 'TentativeReject'])) {
                $activity['inReplyTo'] = $item['thr_parent'];
            }

            // For comment approvals and rejections
            if (in_array($activity['type'], ['Accept','Reject']) && is_string($item['obj']) && strlen($item['obj'])) {
                $activity['inReplyTo'] = $item['thr_parent'];
            }

            $cnv = get_iconfig($item['parent'], 'activitypub', 'context');
            if (!$cnv) {
                $cnv = $activity['parent_mid'];
            }
        }

        if (!(isset($cnv) && $cnv)) {
            $cnv = get_iconfig($item, 'activitypub', 'context');
            if (!$cnv) {
                $cnv = $item['parent_mid'];
            }
        }
        if (!empty($cnv)) {
            if (is_string($cnv) && str_starts_with($cnv, z_root())) {
                $cnv = str_replace(['/item/', '/activity/'], ['/conversation/', '/conversation/'], $cnv);
            }
            $activity['context'] = $cnv;
            $activity['conversation'] = $cnv;
        }

        if (intval($item['item_private']) === 2) {
            $activity['directMessage'] = true;
        }

        $actor = self::encode_person($item['author'], false);
        if ($actor) {
            $activity['actor'] = $actor;
        } else {
            return [];
        }


        $replyto = unserialise($item['replyto']);
        if ($replyto) {
            $activity['replyTo'] = $replyto;
        }

        if (!isset($activity['url'])) {
            $urls = [];
            if (intval($item['item_wall'])) {
                $locs = self::nomadic_locations($item);
                if ($locs) {
                    foreach ($locs as $l) {
                        if (str_contains($activity['id'], $l['hubloc_url'])) {
                            continue;
                        }
                        $urls[] = [
                            'type' => 'Link',
                            'href' => str_replace(z_root(), $l['hubloc_url'], $activity['id']),
                            'rel' => 'alternate',
                            'mediaType' => 'text/html'
                        ];
                        $urls[] = [
                            'type' => 'Link',
                            'href' => str_replace(z_root(), $l['hubloc_url'], $activity['id']),
                            'rel' => 'alternate',
                            'mediaType' => 'application/activity+json'
                        ];
                        $urls[] = [
                            'type' => 'Link',
                            'href' => str_replace(z_root(), $l['hubloc_url'], $activity['id']),
                            'rel' => 'alternate',
                            'mediaType' => 'application/x-zot+json'
                        ];
                        $urls[] = [
                            'type' => 'Link',
                            'href' => str_replace(z_root(), $l['hubloc_url'], $activity['id']),
                            'rel' => 'alternate',
                            'mediaType' => 'application/x-nomad+json'
                        ];
                    }
                }
            }
            if ($urls) {
                $curr[] = [
                    'type' => 'Link',
                    'href' => $activity['id'],
                    'rel' => 'alternate',
                    'mediaType' => 'text/html'
                ];
                $activity['url'] = array_merge($curr, $urls);
            } else {
                $activity['url'] = $activity['id'];
            }
        }

        if ($item['obj']) {
            if (is_string($item['obj'])) {
                $tmp = json_decode($item['obj'], true);
                if ($tmp !== null) {
                    $item['obj'] = $tmp;
                }
            }
            $obj = self::encode_object($item['obj']);
        }
        else {
            $obj = self::encode_item($item, $activitypub);
        }
        if ($obj) {
            $activity['object'] = $obj;
        } else {
            return [];
        }

        if ($item['target']) {
            if (is_string($item['target'])) {
                $tmp = json_decode($item['target'], true);
                if ($tmp !== null) {
                    $item['target'] = $tmp;
                }
            }
            $tgt = self::encode_object($item['target']);
            if ($tgt) {
                $activity['target'] = $tgt;
            }
        }

        $t = self::encode_taxonomy($item);
        if ($t) {
            foreach($t as $tag) {
                if (strcasecmp($tag['name'], '#nsfw') === 0) {
                    $activity['sensitive'] = true;
                }
            }
            $activity['tag'] = $t;
        }

        if ($obj && $obj['attachment']) {
            $activity['attachment'] = $obj['attachment'];
        }
        else {
            $a = self::encode_attachment($item);
            if ($a) {
                $activity['attachment'] = $a;
            }
        }

        // addressing madness

        if ($activitypub) {
            $parent_i = [];
            $public = !$item['item_private'];
            $top_level = ($item['mid'] === $item['parent_mid']);
            $activity['to'] = [];
            $activity['cc'] = [];

            $recips = get_iconfig($item['parent'], 'activitypub', 'recips');
            if ($recips) {
                $parent_i['to'] = $recips['to'];
                $parent_i['cc'] = $recips['cc'];
            }

            if ($public) {
                $activity['to'] = [ACTIVITY_PUBLIC_INBOX];
                if (isset($parent_i['to']) && is_array($parent_i['to'])) {
                    $activity['to'] = array_values(array_unique(array_merge($activity['to'], $parent_i['to'])));
                }
                if ($item['item_origin']) {
                    $activity['cc'] = [z_root() . '/followers/' . substr($item['author']['xchan_addr'], 0, strpos($item['author']['xchan_addr'], '@'))];
                }
                if (isset($parent_i['cc']) && is_array($parent_i['cc'])) {
                    $activity['cc'] = array_values(array_unique(array_merge($activity['cc'], $parent_i['cc'])));
                }
            } else {
                // private activity
                if ($top_level) {
                    $activity['to'] = self::map_acl($item);
                    if (isset($parent_i['to']) && is_array($parent_i['to'])) {
                        $activity['to'] = array_values(array_unique(array_merge($activity['to'], $parent_i['to'])));
                    }
                } else {
                    $activity['cc'] = self::map_acl($item);
                    if (isset($parent_i['cc']) && is_array($parent_i['cc'])) {
                        $activity['cc'] = array_values(array_unique(array_merge($activity['cc'], $parent_i['cc'])));
                    }

                    if ($activity['tag']) {
                        foreach ($activity['tag'] as $mention) {
                            if (is_array($mention) && array_key_exists('ttype', $mention) && in_array($mention['ttype'], [TERM_FORUM, TERM_MENTION]) && array_key_exists('href', $mention) && $mention['href']) {
                                $h = q(
                                    "select * from hubloc where hubloc_id_url = '%s' and hubloc_deleted = 0 limit 1",
                                    dbesc($mention['href'])
                                );
                                if ($h) {
                                    if ($h[0]['hubloc_network'] === 'activitypub') {
                                        $addr = $h[0]['hubloc_hash'];
                                    } else {
                                        $addr = $h[0]['hubloc_id_url'];
                                    }
                                    if (!in_array($addr, $activity['to'])) {
                                        $activity['to'][] = $addr;
                                    }
                                }
                            }
                        }
                    }

                    $d = q(
                        "select hubloc.*  from hubloc left join item on hubloc_hash = owner_xchan where item.parent_mid = '%s' and item.uid = %d and hubloc_deleted = 0 order by hubloc_id desc limit 1",
                        dbesc($item['parent_mid']),
                        intval($item['uid'])
                    );
                    if ($d) {
                        if ($d[0]['hubloc_network'] === 'activitypub') {
                            $addr = $d[0]['hubloc_hash'];
                        } else {
                            $addr = $d[0]['hubloc_id_url'];
                        }
                        $activity['cc'][] = $addr;
                    }
                }
            }

            $mentions = self::map_mentions($item);
            if (count($mentions) > 0) {
                if (!$activity['to']) {
                    $activity['to'] = $mentions;
                } else {
                    $activity['to'] = array_values(array_unique(array_merge($activity['to'], $mentions)));
                }
            }
        }

        $cc = [];
        if ($activity['cc'] && is_array($activity['cc'])) {
            foreach ($activity['cc'] as $e) {
                if (!is_array($activity['to'])) {
                    $cc[] = $e;
                } elseif (!in_array($e, $activity['to'])) {
                    $cc[] = $e;
                }
            }
        }
        $activity['cc'] = $cc;

        return $activity;
    }


    public static function nomadic_locations($item)
    {
        $synchubs = [];
        $h = q(
            "select hubloc.*, site.site_crypto from hubloc left join site on site_url = hubloc_url
            where hubloc_hash = '%s' and hubloc_network in ('zot6','nomad') and hubloc_deleted = 0",
            dbesc($item['author_xchan'])
        );

        if (!$h) {
            return [];
        }

        foreach ($h as $x) {
            $y = q(
                "select site_dead from site where site_url = '%s' limit 1",
                dbesc($x['hubloc_url'])
            );

            if ((!$y) || intval($y[0]['site_dead']) === 0) {
                $synchubs[] = $x;
            }
        }

        return $synchubs;
    }


    public static function encode_item($item, $activitypub = false)
    {

        $activity = [];
        $bbopts = (($activitypub) ? 'activitypub' : 'export');

        $objtype = self::activity_obj_mapper($item['obj_type']);

        if (intval($item['item_deleted'])) {
            $activity['type'] = 'Tombstone';
            $activity['formerType'] = $objtype;
            $activity['id'] = $item['mid'];
            $activity['to'] = [ACTIVITY_PUBLIC_INBOX];
            return $activity;
        }

        if (isset($item['obj']) && $item['obj']) {
            if (is_string($item['obj'])) {
                $tmp = json_decode($item['obj'], true);
                if ($tmp !== null) {
                    $item['obj'] = $tmp;
                }
            }
            $activity = $item['obj'];
            if (is_string($activity)) {
                return $activity;
            }
        }

        $activity['type'] = $objtype;

        if ($objtype === 'Question') {
            if ($item['obj']) {
                if (is_array($item['obj'])) {
                    $activity = $item['obj'];
                } else {
                    $activity = json_decode($item['obj'], true);
                }

                if (array_path_exists('actor', $activity)) {
                    $activity['actor'] = self::encode_person($activity['actor'],false);
                }
            }
        }


        $images = false;
        $has_images = preg_match_all('/\[[zi]mg(.*?)](.*?)\[/ism', $item['body'], $images, PREG_SET_ORDER);

        $activity['id'] = $item['mid'];

        $activity['published'] = datetime_convert('UTC', 'UTC', $item['created'], ATOM_TIME);
        if ($item['created'] !== $item['edited']) {
            $activity['updated'] = datetime_convert('UTC', 'UTC', $item['edited'], ATOM_TIME);
        }
        if ($item['expires'] > NULL_DATE) {
            $activity['expires'] = datetime_convert('UTC', 'UTC', $item['expires'], ATOM_TIME);
        }
        if ($item['app']) {
            $activity['generator'] = ['type' => 'Application', 'name' => $item['app']];
        }
        if ($item['location'] || $item['lat'] || $item['lon']) {
            $activity['location'] = ['type' => 'Place'];
            if ($item['location']) {
                $activity['location']['name'] = $item['location'];
            }
            if ($item['lat'] || $item['lon']) {
                $activity['location']['latitude'] = $item['lat'] ?? 0;
                $activity['location']['longitude'] = $item['lon'] ?? 0;
            }
        }
        if (intval($item['item_wall']) && $item['mid'] === $item['parent_mid']) {
            $activity['commentPolicy'] = $item['comment_policy'];
        }

        if (intval($item['item_private']) === 2) {
            $activity['directMessage'] = true;
        }

        // avoid double addition of the until= clause
        if (!str_contains($activity['commentPolicy'], 'until=')) {
            if (intval($item['item_nocomment'])) {
                if ($activity['commentPolicy']) {
                    $activity['commentPolicy'] .= ' ';
                }
                $activity['commentPolicy'] .= 'until=' . datetime_convert('UTC', 'UTC', $item['created'], ATOM_TIME);
            } elseif (array_key_exists('comments_closed', $item) && $item['comments_closed'] !== EMPTY_STR && $item['comments_closed'] > NULL_DATE) {
                if ($activity['commentPolicy']) {
                    $activity['commentPolicy'] .= ' ';
                }
                $activity['commentPolicy'] .= 'until=' . datetime_convert('UTC', 'UTC', $item['comments_closed'], ATOM_TIME);
            }
        }

        $activity['attributedTo'] = self::encode_person($item['author'],false);

        if ($item['mid'] === $item['parent_mid']) {
            $activity['isContainedConversation'] = true;
            if (in_array($activity['commentPolicy'], ['public', 'authenticated'])) {
                $activity['canReply'] = ACTIVITY_PUBLIC_INBOX;
            } elseif (in_array($activity['commentPolicy'], ['contacts', 'specific'])) {
                $activity['canReply'] = z_root() . '/followers/' . substr($item['author']['xchan_addr'], 0, strpos($item['author']['xchan_addr'], '@'));
            } elseif (in_array($activity['commentPolicy'], ['self', 'none']) || $item['item_nocomment'] || datetime_convert('UTC', 'UTC', $item['comments_closed']) <= datetime_convert()) {
                $activity['canReply'] = [];
            }
        }

        if ($item['mid'] !== $item['parent_mid']) {
            if ($item['approved']) {
                $activity['approval'] = $item['approved'];
            }
            $activity['inReplyTo'] = $item['thr_parent'];
            $cnv = get_iconfig($item['parent'], 'activitypub', 'context');
            if (!$cnv) {
                $cnv = $activity['parent_mid'];
            }
        }
        if (!isset($cnv)) {
            $cnv = get_iconfig($item, 'activitypub', 'context');
            if (!$cnv) {
                $cnv = $item['parent_mid'];
            }
        }
        if (!empty($cnv)) {
            if (is_string($cnv) && str_starts_with($cnv, z_root())) {
                $cnv = str_replace(['/item/', '/activity/'], ['/conversation/', '/conversation/'], $cnv);
            }
            $activity['context'] = $cnv;
            $activity['conversation'] = $cnv;
        }

        // provide ocap access token for private media.
        // set this for descendants even if the current item is not private
        // because it may have been relayed from a private item.

        $token = get_iconfig($item, 'ocap', 'relay');
        if ($token && $has_images) {
            for ($n = 0; $n < count($images); $n++) {
                $match = $images[$n];
                if (str_starts_with($match[1], '=http') && str_contains($match[1], '/photo/')) {
                    $item['body'] = str_replace($match[1], $match[1] . '?token=' . $token, $item['body']);
                    $images[$n][2] = substr($match[1], 1) . '?token=' . $token;
                } elseif (str_contains($match[2], z_root() . '/photo/')) {
                    $item['body'] = str_replace($match[2], $match[2] . '?token=' . $token, $item['body']);
                    $images[$n][2] = $match[2] . '?token=' . $token;
                }
            }
        }

        if ($item['title']) {
            $activity['name'] = $item['title'];
        }

        if (in_array($item['mimetype'], [ 'text/bbcode', 'text/x-multicode' ])) {
            if ($item['summary']) {
                $activity['summary'] = bbcode($item['summary'], [$bbopts => true]);
            }
            $opts = [$bbopts => true];
            $activity['content'] = bbcode($item['body'], $opts);
            $activity['source'] = ['content' => $item['body'], 'mediaType' => 'text/x-multicode'];
            if (isset($activity['summary'])) {
                $activity['source']['summary'] = $item['summary'];
            }
        } else {
            $activity['mediaType'] = $item['mimetype'];
            $activity['content'] = $item['body'];
        }

        if (!(isset($activity['actor']) || isset($activity['attributedTo']))) {
            $actor = self::encode_person($item['author'], false);
            if ($actor) {
                $activity['actor'] = $actor;
            } else {
                return [];
            }
        }

        $replyto = unserialise($item['replyto']);
        if ($replyto) {
            $activity['replyTo'] = $replyto;
        }

        if (!isset($activity['url'])) {
            $urls = [];
            if (intval($item['item_wall'])) {
                $locs = self::nomadic_locations($item);
                if ($locs) {
                    foreach ($locs as $l) {
                        if (str_contains($item['mid'], $l['hubloc_url'])) {
                            continue;
                        }
                        $urls[] = [
                            'type' => 'Link',
                            'href' => str_replace(z_root(), $l['hubloc_url'], $activity['id']),
                            'rel' => 'alternate',
                            'mediaType' => 'text/html'
                        ];
                        $urls[] = [
                            'type' => 'Link',
                            'href' => str_replace(z_root(), $l['hubloc_url'], $activity['id']),
                            'rel' => 'alternate',
                            'mediaType' => 'application/activity+json'
                        ];
                        $urls[] = [
                            'type' => 'Link',
                            'href' => str_replace(z_root(), $l['hubloc_url'], $activity['id']),
                            'rel' => 'alternate',
                            'mediaType' => 'application/x-nomad+json'
                        ];
                    }
                }
            }
            if ($urls) {
                $curr[] = [
                    'type' => 'Link',
                    'href' => $activity['id'],
                    'rel' => 'alternate',
                    'mediaType' => 'text/html'
                ];
                $activity['url'] = array_merge($curr, $urls);
            } else {
                $activity['url'] = $activity['id'];
            }
        }

        $t = self::encode_taxonomy($item);
        if ($t) {
            foreach($t as $tag) {
                if (strcasecmp($tag['name'], '#nsfw') === 0) {
                    $activity['sensitive'] = true;
                }
            }
            $activity['tag'] = $t;
        }

        $a = self::encode_attachment($item);
        if ($a) {
            $activity['attachment'] = $a;
        }


        if ($activitypub && $has_images && $activity['type'] === 'Note') {
            foreach ($images as $match) {
                $img = [];
                // handle Friendica/Hubzilla style img links with [img=$url]$alttext[/img]
                if (str_starts_with($match[1], '=http')) {
                    $img[] = ['type' => 'Image', 'url' => substr($match[1], 1), 'name' => $match[2]];
                } // preferred mechanism for adding alt text
                elseif (str_contains($match[1], 'alt=')) {
                    $txt = str_replace('&quot;', '"', $match[1]);
                    $txt = substr($txt, strpos($txt, 'alt="') + 5, -1);
                    $img[] = ['type' => 'Image', 'url' => $match[2], 'name' => $txt];
                } else {
                    $img[] = ['type' => 'Image', 'url' => $match[2]];
                }

                if (!$activity['attachment']) {
                    $activity['attachment'] = [];
                }
                $already_added = false;
                if ($img) {
                    for ($pc = 0; $pc < count($activity['attachment']); $pc++) {
                        // caution: image attachments use url and links use href, and our own links will be 'attach' links based on the image href
                        // We could alternatively supply the correct attachment info when item is saved, but by replacing here we will pick up
                        // any "per-post" or manual changes to the image alt-text before sending.

                        if ((isset($activity['attachment'][$pc]['href']) && str_contains($img[0]['url'], str_replace('/attach/', '/photo/', $activity['attachment'][$pc]['href']))) || (isset($activity['attachment'][$pc]['url']) && $activity['attachment'][$pc]['url'] === $img[0]['url'])) {
                            // if it's already there, replace it with our alt-text aware version
                            $activity['attachment'][$pc] = $img[0];
                            $already_added = true;
                        }
                    }
                    if (!$already_added) {
                        // add it
                        $activity['attachment'] = array_merge($img, $activity['attachment']);
                    }
                }
            }
        }

        // addressing madness

        if ($activitypub) {
            $parent_i = [];
            $activity['to'] = [];
            $activity['cc'] = [];

            $public = !$item['item_private'];
            $top_level = $item['mid'] === $item['parent_mid'];

            if (!$top_level) {
                if (intval($item['parent'])) {
                    $recips = get_iconfig($item['parent'], 'activitypub', 'recips');
                } else {
                    // if we are encoding this item prior to storage there won't be a parent.
                    $p = q(
                        "select parent from item where parent_mid = '%s' and uid = %d",
                        dbesc($item['parent_mid']),
                        intval($item['uid'])
                    );
                    if ($p) {
                        $recips = get_iconfig($p[0]['parent'], 'activitypub', 'recips');
                    }
                }
                if ($recips) {
                    $parent_i['to'] = $recips['to'];
                    $parent_i['cc'] = $recips['cc'];
                }
            }


            if ($public) {
                $activity['to'] = [ACTIVITY_PUBLIC_INBOX];
                if (isset($parent_i['to']) && is_array($parent_i['to'])) {
                    $activity['to'] = array_values(array_unique(array_merge($activity['to'], $parent_i['to'])));
                }
                if ($item['item_origin']) {
                    $activity['cc'] = [z_root() . '/followers/' . substr($item['author']['xchan_addr'], 0, strpos($item['author']['xchan_addr'], '@'))];
                }
                if (isset($parent_i['cc']) && is_array($parent_i['cc'])) {
                    $activity['cc'] = array_values(array_unique(array_merge($activity['cc'], $parent_i['cc'])));
                }
            } else {
                // private activity

                if ($top_level) {
                    $activity['to'] = self::map_acl($item);
                    if (isset($parent_i['to']) && is_array($parent_i['to'])) {
                        $activity['to'] = array_values(array_unique(array_merge($activity['to'], $parent_i['to'])));
                    }
                } else {
                    $activity['cc'] = self::map_acl($item);
                    if (isset($parent_i['cc']) && is_array($parent_i['cc'])) {
                        $activity['cc'] = array_values(array_unique(array_merge($activity['cc'], $parent_i['cc'])));
                    }
                    if ($activity['tag']) {
                        foreach ($activity['tag'] as $mention) {
                            if (is_array($mention) && array_key_exists('ttype', $mention) && in_array($mention['ttype'], [TERM_FORUM, TERM_MENTION]) && array_key_exists('href', $mention) && $mention['href']) {
                                $h = hubloc_id_query($mention['href'], 1);
                                if ($h) {
                                    if ($h[0]['hubloc_network'] === 'activitypub') {
                                        $addr = $h[0]['hubloc_hash'];
                                    } else {
                                        $addr = $h[0]['hubloc_id_url'];
                                    }
                                    if (!in_array($addr, $activity['to'])) {
                                        $activity['to'][] = $addr;
                                    }
                                }
                            }
                        }
                    }


                    $d = q(
                        "select hubloc.*  from hubloc left join item on hubloc_hash = owner_xchan where item.parent_mid = '%s' and item.uid = %d and hubloc_deleted = 0 order by hubloc_id desc limit 1",
                        dbesc($item['parent_mid']),
                        intval($item['uid'])
                    );

                    if ($d) {
                        if ($d[0]['hubloc_network'] === 'activitypub') {
                            $addr = $d[0]['hubloc_hash'];
                        } else {
                            $addr = $d[0]['hubloc_id_url'];
                        }
                        $activity['cc'][] = $addr;
                    }
                }
            }

            $mentions = self::map_mentions($item);
            if (count($mentions) > 0) {
                if (!$activity['to']) {
                    $activity['to'] = $mentions;
                } else {
                    $activity['to'] = array_values(array_unique(array_merge($activity['to'], $mentions)));
                }
            }
        }

        // remove any duplicates from 'cc' that are present in 'to'
        // as this may indicate that mentions changed the audience from secondary to primary

        $cc = [];
        if ($activity['cc'] && is_array($activity['cc'])) {
            foreach ($activity['cc'] as $e) {
                if (!is_array($activity['to'])) {
                    $cc[] = $e;
                } elseif (!in_array($e, $activity['to'])) {
                    $cc[] = $e;
                }
            }
        }
        $activity['cc'] = $cc;

        return $activity;
    }


    // Returns an array of URLS for any mention tags found in the item array $i.

    public static function map_mentions($i)
    {
        if (!(array_key_exists('term', $i) && is_array($i['term']))) {
            return [];
        }

        $list = [];

        foreach ($i['term'] as $t) {
            if (!(array_key_exists('url', $t) && $t['url'])) {
                continue;
            }
            if (array_key_exists('ttype', $t) && $t['ttype'] == TERM_MENTION) {
                $url = self::lookup_term_url($t['url']);
                $list[] = (($url) ?: $t['url']);
            }
        }

        return $list;
    }

    // Returns an array of all recipients targeted by private item array $i.

    public static function map_acl($i)
    {
        $ret = [];

        if (!$i['item_private']) {
            return $ret;
        }

        if ($i['mid'] !== $i['parent_mid']) {
            $i = q(
                "select * from item where parent_mid = '%s' and uid = %d",
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
            $list = stringify_array($tmp, true);
            if ($list) {
                $details = q("select hubloc_id_url, hubloc_hash, hubloc_network from hubloc where hubloc_hash in (" . $list . ")  and hubloc_deleted = 0");
                if ($details) {
                    foreach ($details as $d) {
                        if ($d['hubloc_network'] === 'activitypub') {
                            $ret[] = $d['hubloc_hash'];
                        } else {
                            $ret[] = $d['hubloc_id_url'];
                        }
                    }
                }
            }
        }

        $x = get_iconfig($i['id'], 'activitypub', 'recips');
        if ($x) {
            foreach (['to', 'cc'] as $k) {
                if (isset($x[$k])) {
                    if (is_string($x[$k])) {
                        $ret[] = $x[$k];
                    } else {
                        $ret = array_merge($ret, $x[$k]);
                    }
                }
            }
        }

        return array_values(array_unique($ret));
    }


    public static function encode_person($p, $extended = true, $activitypub = false)
    {

        $ret = [];
        $currhub = false;

        if (!$p['xchan_url']) {
            return $ret;
        }

        $h = q("select * from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0",
            dbesc($p['xchan_hash'])
        );
        if ($h) {
            $currhub = $h[0];
            foreach ($h as $hub) {
                if ($hub['hubloc_url'] === z_root()) {
                    $currhub = $hub;
                }
            }
        }

        $current_url = $currhub ? $currhub['hubloc_id_url'] : $p['xchan_url'];

        if (!$extended) {
            return $current_url;
        }

        $c = ((array_key_exists('channel_id', $p)) ? $p : Channel::from_hash($p['xchan_hash']));

        $ret['type'] = 'Person';
        $auto_follow = false;

        if ($c) {
            $role = PConfig::Get($c['channel_id'], 'system', 'permissions_role');
            if (str_contains($role, 'forum')) {
                $ret['type'] = 'Group';
            }
            $auto_follow = intval(PConfig::Get($c['channel_id'],'system','autoperms'));
        }

        if ($c) {
            $ret['id'] = Channel::url($c);
        } else {
            $ret['id'] = ((str_starts_with($p['xchan_hash'], 'http')) ? $p['xchan_hash'] : $current_url);
        }
        if ($p['xchan_addr'] && strpos($p['xchan_addr'], '@')) {
            $ret['preferredUsername'] = substr($p['xchan_addr'], 0, strpos($p['xchan_addr'], '@'));
        }
        $ret['name'] = $p['xchan_name'];
        $ret['updated'] = datetime_convert('UTC', 'UTC', $p['xchan_name_date'], ATOM_TIME);
        $ret['icon'] = [
            'type' => 'Image',
            'mediaType' => (($p['xchan_photo_mimetype']) ?: 'image/png'),
            'updated' => datetime_convert('UTC', 'UTC', $p['xchan_photo_date'], ATOM_TIME),
            'url' => $p['xchan_photo_l'],
            'height' => 300,
            'width' => 300,
        ];
        $ret['url'] = $current_url;
        if (isset($p['channel_location']) && $p['channel_location']) {
            $ret['location'] = ['type' => 'Place', 'name' => $p['channel_location']];
        }

        $ret['tag'] = [['type' => 'PropertyValue', 'name' => 'Protocol', 'value' => 'zot6']];
        $ret['tag'][] = ['type' => 'PropertyValue', 'name' => 'Protocol', 'value' => 'nomad'];

        if ($activitypub && get_config('system', 'activitypub', ACTIVITYPUB_ENABLED)) {
            if ($c) {
                if (get_pconfig($c['channel_id'], 'system', 'activitypub', ACTIVITYPUB_ENABLED)) {
                    $ret['inbox'] = z_root() . '/inbox/' . $c['channel_address'];
                    $ret['tag'][] = ['type' => 'PropertyValue', 'name' => 'Protocol', 'value' => 'activitypub'];
                } else {
                    $ret['inbox'] = null;
                }

                $ret['outbox'] = z_root() . '/outbox/' . $c['channel_address'];
                $ret['followers'] = z_root() . '/followers/' . $c['channel_address'];
                $ret['following'] = z_root() . '/following/' . $c['channel_address'];

                $ret['endpoints'] = [
                    'sharedInbox' => z_root() . '/inbox',
                    'oauthRegistrationEndpoint' => z_root() . '/api/client/register',
                    'oauthAuthorizationEndpoint' => z_root() . '/authorize',
                    'oauthTokenEndpoint' => z_root() . '/token',
                    'searchContent' => z_root() . '/search/' . $c['channel_address'] . '/?search={}',
                    'searchTags' => z_root() . '/search/' . $c['channel_address'] . '/?tag={}',
                ];
                $ret['discoverable'] = (bool)((1 - intval($p['xchan_hidden'])));

                $searchPerm = PermissionLimits::Get($c['channel_id'], 'search_stream');
                if ($searchPerm === PERMS_PUBLIC) {
                        $ret['canSearch'] = ACTIVITY_PUBLIC_INBOX;
                }
                elseif (in_array($searchPerm, [ PERMS_SPECIFIC, PERMS_CONTACTS])) {
                    $ret['canSearch'] = z_root() . '/followers/' . $c['channel_address'];
                }
                else {
                    $ret['canSearch'] = [];
                }


                $ret['publicKey'] = [
                    'id' => $current_url . '?operation=getkey',
                    'owner' => $current_url,
                    'signatureAlgorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
                    'publicKeyPem' => $p['xchan_pubkey']
                ];

                $ret['manuallyApprovesFollowers'] = !$auto_follow;
                if ($ret['type'] === 'Group') {
                    $ret['capabilities'] = ['acceptsJoins' => true];
                }
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

                // To move your followers from a Mastodon account,
                // visit https://$yoursite/pconfig/system/movefrom
                // And set the value to the URL of your Mastodon profile.
                // Then go back to Mastodon and move your account.

                $move_id = PConfig::Get($c['channel_id'],'system','movefrom');
                if ($move_id) {
                    $ret['movedTo'] = z_root() . '/channel/' . $c['channel_address'];
                    $ret['alsoKnownAs'] = $move_id;
                }

                $cp = Channel::get_cover_photo($c['channel_id'], 'array');
                if ($cp) {
                    $ret['image'] = [
                        'type' => 'Image',
                        'mediaType' => $cp['type'],
                        'url' => $cp['url']
                    ];
                }
                // only fill in profile information if the profile is publicly visible
                if (perm_is_allowed($c['channel_id'], EMPTY_STR, 'view_profile')) {
                    $dp = q(
                        "select * from profile where uid = %d and is_default = 1",
                        intval($c['channel_id'])
                    );
                    if ($dp) {
                        if ($dp[0]['about']) {
                            $ret['summary'] = bbcode($dp[0]['about'], ['export' => true]);
                        }
                        foreach (
                            ['pdesc', 'address', 'locality', 'region', 'postal_code', 'country_name',
                                     'hometown', 'gender', 'marital', 'sexual', 'politic', 'religion', 'pronouns',
                                     'homepage', 'contact', 'dob'] as $k
                        ) {
                            if ($dp[0][$k]) {
                                $key = $k;
                                if ($key === 'pdesc') {
                                    $key = 'description';
                                }
                                if ($key == 'politic') {
                                    $key = 'political';
                                }
                                if ($key === 'dob') {
                                    $key = 'birthday';
                                }
                                $ret['attachment'][] = ['type' => 'PropertyValue', 'name' => $key, 'value' => $dp[0][$k]];
                            }
                        }
                        if ($dp[0]['keywords']) {
                            $kw = explode(' ', $dp[0]['keywords']);
                            if ($kw) {
                                foreach ($kw as $k) {
                                    $k = trim($k);
                                    $k = trim($k, '#,');
                                    $ret['tag'][] = ['type' => 'Hashtag', 'id' => z_root() . '/search?tag=' . urlencode($k), 'name' => '#' . urlencode($k)];
                                }
                            }
                        }
                    }
                }
            } else {
                $collections = get_xconfig($p['xchan_hash'], 'activitypub', 'collections', []);
                if ($collections) {
                    $ret = array_merge($ret, $collections);
                } else {
                    $ret['inbox'] = null;
                    $ret['outbox'] = null;
                }
            }
        } else {
            $ret['publicKey'] = [
                'id' => $current_url,
                'owner' => $current_url,
                'publicKeyPem' => $p['xchan_pubkey']
            ];
        }

        $arr = ['xchan' => $p, 'encoded' => $ret, 'activitypub' => $activitypub];
        Hook::call('encode_person', $arr);
        return $arr['encoded'];
    }


    public static function encode_site()
    {


        $sys = Channel::get_system();

        // encode  the sys channel information and over-ride with site
        // information
        $ret = self::encode_person($sys, true, true);

        $ret['type'] = self::xchan_type_to_type(intval($sys['xchan_type']));
        $ret['id'] = z_root();
        $ret['alsoKnownAs'] = z_root() . '/channel/sys';
        $ret['preferredUsername'] = 'sys';
        $ret['name'] = System::get_site_name();

        $ret['icon'] = [
            'type' => 'Image',
            'url' => System::get_site_icon(),
        ];

        if (Config::Get('system','block_public_search', 1)) {
                $ret['canSearch'] = ACTIVITY_PUBLIC_INBOX;
        }
        else {
            $ret['canSearch'] = [];
        }

        $ret['generator'] = ['type' => 'Application', 'name' => System::get_project_name()];

        $ret['url'] = z_root();

        $ret['manuallyApprovesFollowers'] = (bool)get_config('system', 'allowed_sites');

        $cp = Channel::get_cover_photo($sys['channel_id'], 'array');
        if ($cp) {
            $ret['image'] = [
                'type' => 'Image',
                'mediaType' => $cp['type'],
                'url' => $cp['url']
            ];
        }

        $ret['source'] = [
            'mediaType' => 'text/x-multicode',
            'summary' => get_config('system', 'siteinfo', '')
        ];

        $ret['publicKey'] = [
            'id' => z_root() . '?operation=getkey',
            'owner' => z_root(),
            'publicKeyPem' => get_config('system', 'pubkey')
        ];

        return $ret;
    }


    public static function activity_mapper($verb)
    {

        if (!str_contains($verb, '/')) {
            return $verb;
        }

        $acts = [
            'http://activitystrea.ms/schema/1.0/post' => 'Create',
            'http://activitystrea.ms/schema/1.0/share' => 'Announce',
            'http://activitystrea.ms/schema/1.0/update' => 'Update',
            'http://activitystrea.ms/schema/1.0/like' => 'Like',
            'http://activitystrea.ms/schema/1.0/favorite' => 'Like',
            'http://purl.org/zot/activity/dislike' => 'Dislike',
            'http://activitystrea.ms/schema/1.0/tag' => 'Add',
            'http://activitystrea.ms/schema/1.0/follow' => 'Follow',
            'http://activitystrea.ms/schema/1.0/unfollow' => 'Ignore',
        ];

        Hook::call('activity_mapper', $acts);

        if (array_key_exists($verb, $acts) && $acts[$verb]) {
            return $acts[$verb];
        }

        // Reactions will just map to normal activities

        if (str_contains($verb, ACTIVITY_REACT)) {
            return 'Create';
        }
        if (str_contains($verb, ACTIVITY_MOOD)) {
            return 'Create';
        }

        if (str_contains($verb, ACTIVITY_POKE)) {
            return 'Activity';
        }

        // We should return false, however this will trigger an uncaught exception  and crash
        // the delivery system if encountered by the JSON-LDSignature library

        logger('Unmapped activity: ' . $verb);
        return 'Create';
        //  return false;
    }


    public static function activity_obj_mapper($obj, $sync = false)
    {


        $objs = [
            'http://activitystrea.ms/schema/1.0/note' => 'Note',
            'http://activitystrea.ms/schema/1.0/comment' => 'Note',
            'http://activitystrea.ms/schema/1.0/person' => 'Person',
            'http://purl.org/zot/activity/profile' => 'Profile',
            'http://activitystrea.ms/schema/1.0/photo' => 'Image',
            'http://activitystrea.ms/schema/1.0/profile-photo' => 'Icon',
            'http://activitystrea.ms/schema/1.0/event' => 'Event',
            'http://activitystrea.ms/schema/1.0/wiki' => 'Document',
            'http://purl.org/zot/activity/location' => 'Place',
            'http://purl.org/zot/activity/chessgame' => 'Game',
            'http://purl.org/zot/activity/tagterm' => 'zot:Tag',
            'http://purl.org/zot/activity/thing' => 'Object',
            'http://purl.org/zot/activity/file' => 'zot:File',
            'http://purl.org/zot/activity/mood' => 'zot:Mood',

        ];

        Hook::call('activity_obj_mapper', $objs);

        if ($obj === 'Answer') {
            if ($sync) {
                return $obj;
            }
            return 'Note';
        }

        if (!str_contains($obj, '/')) {
            return $obj;
        }

        if (array_key_exists($obj, $objs)) {
            return $objs[$obj];
        }

        logger('Unmapped activity object: ' . $obj);
        return 'Note';

        //  return false;
    }


    public static function follow($channel, $act)
    {

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

        if (in_array($act->type, ['Follow', 'Invite', 'Join'])) {
            $their_follow_id = $act->id;
        }

        if (is_array($person_obj)) {
            // store their xchan and hubloc

            self::actor_store($person_obj['id'], $person_obj);

            // Find any existing abook record

            $r = q(
                "select * from abook left join xchan on abook_xchan = xchan_hash where abook_xchan = '%s' and abook_channel = %d limit 1",
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

            switch ($act->type) {
                case 'Follow':
                case 'Invite':
                case 'Join':
                    // A second Follow request, but we haven't approved the first one

                    if ($contact['abook_pending']) {
                        return;
                    }

                    // We've already approved them or followed them first
                    // Send an Accept back to them

                    set_abconfig($channel['channel_id'], $person_obj['id'], 'activitypub', 'their_follow_id', $their_follow_id);
                    set_abconfig($channel['channel_id'], $person_obj['id'], 'activitypub', 'their_follow_type', $act->type);
                    // In case they unfollowed us and followed again, reset their permissions to show that we're connected again. 
                    if ($their_perms) {
                        AbConfig::Set($channel['channel_id'], $person_obj['id'], 'system', 'their_perms', $their_perms);
                    }
                    Run::Summon(['Notifier', 'permissions_accept', $contact['abook_id']]);
                    return;

                case 'Accept':
                    // They accepted our Follow request - set default permissions

                    set_abconfig($channel['channel_id'], $contact['abook_xchan'], 'system', 'their_perms', $their_perms);

                    $abook_instance = $contact['abook_instance'];

                    if (!str_contains($abook_instance, z_root())) {
                        if ($abook_instance) {
                            $abook_instance .= ',';
                        }
                        $abook_instance .= z_root();

                        q(
                            "update abook set abook_instance = '%s', abook_not_here = 0
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

        set_abconfig($channel['channel_id'], $person_obj['id'], 'activitypub', 'their_follow_id', $their_follow_id);
        set_abconfig($channel['channel_id'], $person_obj['id'], 'activitypub', 'their_follow_type', $act->type);

        // The xchan should have been created by actor_store() above

        $r = q(
            "select * from xchan where xchan_hash = '%s' and xchan_network = 'activitypub' limit 1",
            dbesc($person_obj['id'])
        );

        if (!$r) {
            logger('xchan not found for ' . $person_obj['id']);
            return;
        }
        $ret = $r[0];

        $blocked = LibBlock::fetch($channel['channel_id'], BLOCKTYPE_SERVER);
        if ($blocked) {
            foreach ($blocked as $b) {
                if (str_contains($ret['xchan_url'], $b['block_entity'])) {
                    logger('siteblock - follower denied');
                    return;
                }
            }
        }
        if (LibBlock::fetch_by_entity($channel['channel_id'], $ret['xchan_hash'])) {
            logger('actorblock - follower denied');
            return;
        }

        $p = Permissions::connect_perms($channel['channel_id']);
        $my_perms = Permissions::serialise($p['perms']);
        $automatic = $p['automatic'];

        $closeness = PConfig::Get($channel['channel_id'], 'system', 'new_abook_closeness', 80);

        $r = abook_store_lowlevel(
            [
                'abook_account' => intval($channel['channel_account_id']),
                'abook_channel' => intval($channel['channel_id']),
                'abook_xchan' => $ret['xchan_hash'],
                'abook_closeness' => intval($closeness),
                'abook_created' => datetime_convert(),
                'abook_updated' => datetime_convert(),
                'abook_connected' => datetime_convert(),
                'abook_dob' => NULL_DATE,
                'abook_pending' => intval(($automatic) ? 0 : 1),
                'abook_instance' => z_root()
            ]
        );

        if ($my_perms) {
            AbConfig::Set($channel['channel_id'], $ret['xchan_hash'], 'system', 'my_perms', $my_perms);
        }

        if ($their_perms) {
            AbConfig::Set($channel['channel_id'], $ret['xchan_hash'], 'system', 'their_perms', $their_perms);
        }


        if ($r) {
            logger("New ActivityPub follower for {$channel['channel_name']}");

            $new_connection = q(
                "select * from abook left join xchan on abook_xchan = xchan_hash left join hubloc on hubloc_hash = xchan_hash where abook_channel = %d and abook_xchan = '%s' and hubloc_deleted = 0 order by abook_created desc limit 1",
                intval($channel['channel_id']),
                dbesc($ret['xchan_hash'])
            );
            if ($new_connection) {
                Enotify::submit(
                    [
                        'type' => NOTIFY_INTRO,
                        'from_xchan' => $ret['xchan_hash'],
                        'to_xchan' => $channel['channel_hash'],
                        'link' => z_root() . '/connedit/' . $new_connection[0]['abook_id'],
                    ]
                );

                if ($my_perms && $automatic) {
                    // send an Accept for this Follow activity
                    Run::Summon(['Notifier', 'permissions_accept', $new_connection[0]['abook_id']]);
                    // Send back a Follow notification to them
                    Run::Summon(['Notifier', 'permissions_create', $new_connection[0]['abook_id']]);
                }

                if ($automatic || PConfig::Get($channel['channel_id'],'system','preview_outbox')) {
                    Run::Summon(['Onepoll', $new_connection[0]['abook_id']]);
                }
                $clone = [];
                foreach ($new_connection[0] as $k => $v) {
                    if (str_starts_with($k, 'abook_')) {
                        $clone[$k] = $v;
                    }
                }
                unset($clone['abook_id']);
                unset($clone['abook_account']);
                unset($clone['abook_channel']);

                $abconfig = load_abconfig($channel['channel_id'], $clone['abook_xchan']);

                if ($abconfig) {
                    $clone['abconfig'] = $abconfig;
                }
                Libsync::build_sync_packet($channel['channel_id'], ['abook' => [$clone]]);
            }
        }


        /* If there is a default group for this channel and permissions are automatic, add this member to it */

        if ($channel['channel_default_group'] && $automatic) {
            $g = AccessList::rec_byhash($channel['channel_id'], $channel['channel_default_group']);
            if ($g) {
                AccessList::member_add($channel['channel_id'], '', $ret['xchan_hash'], $g['id']);
            }
        }
    }


    public static function unfollow($channel, $act)
    {
        /* actor is unfollowing $channel */

        $person_obj = $act->actor;

        if (is_array($person_obj)) {
            $r = q(
                "select * from abook left join xchan on abook_xchan = xchan_hash where abook_xchan = '%s' and abook_channel = %d limit 1",
                dbesc($person_obj['id']),
                intval($channel['channel_id'])
            );
            if ($r) {
                // remove all permissions they provided
                del_abconfig($channel['channel_id'], $r[0]['xchan_hash'], 'system', 'their_perms');
            }
        }
    }


    public static function actor_store($url, $person_obj, $force = false)
    {

        if (!is_array($person_obj)) {
            return;
        }

//      logger('person_obj: ' . print_r($person_obj,true));

        if (array_key_exists('movedTo', $person_obj) && $person_obj['movedTo'] && !is_array($person_obj['movedTo'])) {
            $tgt = self::fetch($person_obj['movedTo']);
            if (is_array($tgt)) {
                self::actor_store($person_obj['movedTo'], $tgt);
                ActivityPub::move($person_obj['id'], $tgt);
            }
            return;
        }


        $ap_hubloc = null;

        $hublocs = self::get_actor_hublocs($url);
        if ($hublocs) {
            foreach ($hublocs as $hub) {
                if ($hub['hubloc_network'] === 'activitypub') {
                    $ap_hubloc = $hub;
                }
                if (in_array($hub['hubloc_network'],['zot6','nomad'])) {
                    Libzot::update_cached_hubloc($hub);
                }
            }
        }

        if ($ap_hubloc) {
            // we already have a stored record. Determine if it needs updating.
            if ($ap_hubloc['hubloc_updated'] < datetime_convert('UTC', 'UTC', ' now - ' . self::$ACTOR_CACHE_DAYS . ' days') || $force) {
                $person_obj = self::fetch($url);
                // ensure we received something
                if (!is_array($person_obj)) {
                    return;
                }
            } else {
                return;
            }
        }


        if (isset($person_obj['id'])) {
            $url = $person_obj['id'];
        }

        if (!$url) {
            return;
        }

        // store the actor record in XConfig
        XConfig::Set($url, 'system', 'actor_record', $person_obj);

        $name = unicode_trim(escape_tags($person_obj['name']));
        if (!$name) {
            $name = escape_tags($person_obj['preferredUsername']);
        }
        if (!$name) {
            $name = escape_tags(t('Unknown'));
        }

        $webfinger = EMPTY_STR;
        $username = escape_tags($person_obj['preferredUsername']);
        $h = parse_url($url);
        if ($h && $h['host']) {
            $webfinger = $username . '@' . $h['host'];
        }

        if ($person_obj['icon']) {
            if (is_array($person_obj['icon'])) {
                if (array_key_exists('url', $person_obj['icon'])) {
                    $icon = $person_obj['icon']['url'];
                } else {
                    if (is_string($person_obj['icon'][0])) {
                        $icon = $person_obj['icon'][0];
                    } elseif (array_key_exists('url', $person_obj['icon'][0])) {
                        $icon = $person_obj['icon'][0]['url'];
                    }
                }
            } else {
                $icon = $person_obj['icon'];
            }
        }
        if (!(isset($icon) && $icon)) {
            $icon = z_root() . '/' . Channel::get_default_profile_photo();
        }

        $cover_photo = false;

        if (isset($person_obj['image'])) {
            if (is_string($person_obj['image'])) {
                $cover_photo = $person_obj['image'];
            }
            if (isset($person_obj['image']['url'])) {
                $cover_photo = $person_obj['image']['url'];
            }
        }

        $hidden = false;
        // Mastodon style hidden flag
        if (array_key_exists('discoverable', $person_obj) && (!intval($person_obj['discoverable']))) {
            $hidden = true;
        }
        // Pleroma style hidden flag
        if (array_key_exists('invisible', $person_obj) && (!intval($person_obj['invisible']))) {
            $hidden = true;
        }

        $links = false;
        $profile = false;

        if (is_array($person_obj['url'])) {
            if (!array_key_exists(0, $person_obj['url'])) {
                $links = [$person_obj['url']];
            } else {
                $links = $person_obj['url'];
            }
        }

        if (is_array($links) && $links) {
            foreach ($links as $link) {
                if (is_array($link) && array_key_exists('mediaType', $link) && $link['mediaType'] === 'text/html') {
                    $profile = $link['href'];
                } elseif (is_string($link)) {
                    $profile = $link;
                    break;
                }
            }
            if (!$profile) {
                $profile = $links[0]['href'];
            }
        } elseif (isset($person_obj['url']) && is_string($person_obj['url'])) {
            $profile = $person_obj['url'];
        }

        if (!$profile) {
            $profile = $url;
        }

        $inbox = ((array_key_exists('inbox', $person_obj)) ? $person_obj['inbox'] : null);

        // either an invalid identity or a cached entry of some kind which didn't get caught above

        if ((!$inbox) || str_contains($inbox, z_root())) {
            return;
        }


        $collections = [];

        $collections['inbox'] = $inbox;
        if (array_key_exists('outbox', $person_obj) && is_string($person_obj['outbox'])) {
            $collections['outbox'] = $person_obj['outbox'];
        }
        if (array_key_exists('followers', $person_obj) && is_string($person_obj['followers'])) {
            $collections['followers'] = $person_obj['followers'];
        }
        if (array_key_exists('following', $person_obj) && is_string($person_obj['following'])) {
            $collections['following'] = $person_obj['following'];
        }
        if (array_key_exists('wall', $person_obj) && is_string($person_obj['wall'])) {
            $collections['wall'] = $person_obj['wall'];
        }
        if (array_path_exists('endpoints/sharedInbox', $person_obj) && is_string($person_obj['endpoints']['sharedInbox'])) {
            $collections['sharedInbox'] = $person_obj['endpoints']['sharedInbox'];
        }
        if (array_path_exists('endpoints/searchContent', $person_obj) && is_string($person_obj['endpoints']['searchContent'])) {
            $collections['searchContent'] = $person_obj['endpoints']['searchContent'];
        }
        if (array_path_exists('endpoints/searchTags', $person_obj) && is_string($person_obj['endpoints']['searchTags'])) {
            $collections['searchTags'] = $person_obj['endpoints']['searchTags'];
        }
        if (isset($person_obj['publicKey']['publicKeyPem'])) {
            if ($person_obj['id'] === $person_obj['publicKey']['owner']) {
                $pubkey = $person_obj['publicKey']['publicKeyPem'];
                if (str_contains($pubkey, 'RSA ')) {
                    $pubkey = Keyutils::rsaToPem($pubkey);
                }
            }
        }

        $keywords = [];

        if (isset($person_obj['tag']) && is_array($person_obj['tag'])) {
            foreach ($person_obj['tag'] as $t) {
                if (is_array($t) && isset($t['type']) && $t['type'] === 'Hashtag') {
                    if (isset($t['name'])) {
                        $tag = escape_tags((str_starts_with($t['name'], '#')) ? substr($t['name'], 1) : $t['name']);
                        if ($tag) {
                            $keywords[] = $tag;
                        }
                    }
                }
                if (is_array($t) && isset($t['type']) && $t['type'] === 'PropertyValue') {
                    if (isset($t['name']) && isset($t['value']) && $t['name'] === 'Protocol') {
                        self::update_protocols($url, trim($t['value']));
                    }
                }
            }
        }

        $xchan_type = self::get_xchan_type($person_obj['type']);
        $about = ((isset($person_obj['summary'])) ? html2bbcode(purify_html($person_obj['summary'])) : EMPTY_STR);

        $p = q(
            "select * from xchan where xchan_url = '%s' and xchan_network in ('zot6','nomad') limit 1",
            dbesc($url)
        );
        if ($p) {
            set_xconfig($url, 'system', 'protocols', 'nomad,zot6,activitypub');
        }

        // there is no standard way to represent an 'instance actor' but this will at least subdue the multiple
        // pages of Mastodon and Pleroma instance actors in the directory.
        // @TODO - (2021-08-27) remove this if they provide a non-person xchan_type
        // once extended xchan_type directory filtering is implemented.
        $censored = ((strpos($profile, 'instance_actor') || strpos($profile, '/internal/fetch')) ? 1 : 0);

        $r = q(
            "select * from xchan where xchan_hash = '%s' limit 1",
            dbesc($url)
        );
        if (!$r) {
            // create a new record
            xchan_store_lowlevel( [
                'xchan_hash' => $url,
                'xchan_guid' => $url,
                'xchan_pubkey' => $pubkey,
                'xchan_addr' => $webfinger,
                'xchan_url' => $profile,
                'xchan_name' => $name,
                'xchan_hidden' => intval($hidden),
                'xchan_updated' => datetime_convert(),
                'xchan_name_date' => datetime_convert(),
                'xchan_network' => 'activitypub',
                'xchan_type' => $xchan_type,
                'xchan_photo_date' => datetime_convert('UTC', 'UTC', '1968-01-01'),
                'xchan_photo_l' => z_root() . '/' . Channel::get_default_profile_photo(),
                'xchan_photo_m' => z_root() . '/' . Channel::get_default_profile_photo(80),
                'xchan_photo_s' => z_root() . '/' . Channel::get_default_profile_photo(48),
                'xchan_photo_mimetype' => 'image/png',
                'xchan_censored' => $censored

            ]);
        }
        else {
            // Record exists. Cache existing records for a set number of days
            // then refetch to catch updated profile photos, names, etc.

            if ($r[0]['xchan_name_date'] >= datetime_convert('UTC', 'UTC', 'now - ' . self::$ACTOR_CACHE_DAYS . ' days') && (!$force)) {
                return;
            }

            // update existing record
            q(
                "update xchan set xchan_updated = '%s', xchan_name = '%s', xchan_pubkey = '%s', xchan_network = '%s', xchan_name_date = '%s', xchan_hidden = %d, xchan_type = %d, xchan_censored = %d where xchan_hash = '%s'",
                dbesc(datetime_convert()),
                dbesc($name),
                dbesc($pubkey),
                dbesc('activitypub'),
                dbesc(datetime_convert()),
                intval($hidden),
                intval($xchan_type),
                intval($censored),
                dbesc($url)
            );

            if ($webfinger !== $r[0]['xchan_addr']) {
                q(
                    "update xchan set xchan_addr = '%s' where xchan_hash = '%s'",
                    dbesc($webfinger),
                    dbesc($url)
                );
            }
        }

        if ($cover_photo) {
            set_xconfig($url, 'system', 'cover_photo', $cover_photo);
            if (is_string($cover_photo)) {
                import_remote_cover_photo($cover_photo, $url);
            }
        }


        $m = parse_url($url);
        if ($m['scheme'] && $m['host']) {
            $site_url = $m['scheme'] . '://' . $m['host'];
            $ni = Nodeinfo::fetch($site_url);
            if ($ni && is_array($ni)) {
                $software = ((array_path_exists('software/name', $ni)) ? $ni['software']['name'] : '');
                $version = ((array_path_exists('software/version', $ni)) ? $ni['software']['version'] : '');
                $register = $ni['openRegistrations'];

                $site = q(
                    "select * from site where site_url = '%s'",
                    dbesc($site_url)
                );
                if ($site) {
                    q(
                        "update site set site_project = '%s', site_update = '%s', site_version = '%s' where site_url = '%s'",
                        dbesc($software),
                        dbesc(datetime_convert()),
                        dbesc($version),
                        dbesc($site_url)
                    );
                    // it may have been saved originally as an unknown type, but we now know what it is
                    if (intval($site[0]['site_type']) === SITE_TYPE_UNKNOWN) {
                        q(
                            "update site set site_type = %d where site_url = '%s'",
                            intval(SITE_TYPE_NOTZOT),
                            dbesc($site_url)
                        );
                    }
                } else {
                    site_store_lowlevel(
                        [
                            'site_url' => $site_url,
                            'site_update' => datetime_convert(),
                            'site_dead' => 0,
                            'site_type' => SITE_TYPE_NOTZOT,
                            'site_project' => $software,
                            'site_version' => $version,
                            'site_access' => (($register) ? ACCESS_FREE : ACCESS_PRIVATE),
                            'site_register' => (($register) ? REGISTER_OPEN : REGISTER_CLOSED)
                        ]
                    );
                }
            }
        }

        Libzotdir::import_directory_profile($url, ['about' => $about, 'keywords' => $keywords, 'dob' => '0000-00-00'], null, 0, true);

        if ($collections) {
            set_xconfig($url, 'activitypub', 'collections', $collections);
        }

        $h = q(
            "select * from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0 limit 1",
            dbesc($url)
        );


        $m = parse_url($url);
        if ($m) {
            $hostname = $m['host'];
            $baseurl = $m['scheme'] . '://' . $m['host'] . ((isset($m['port']) && intval($m['port'])) ? ':' . $m['port'] : '');
        }

        if (!$h) {
            hubloc_store_lowlevel([
                'hubloc_guid' => $url,
                'hubloc_hash' => $url,
                'hubloc_id_url' => $profile,
                'hubloc_addr' => $webfinger,
                'hubloc_network' => 'activitypub',
                'hubloc_url' => $baseurl,
                'hubloc_host' => $hostname,
                'hubloc_callback' => $inbox,
                'hubloc_updated' => datetime_convert(),
                'hubloc_primary' => 1
            ]);
        }
        else {
            if ($webfinger !== $h[0]['hubloc_addr']) {
                q(
                    "update hubloc set hubloc_addr = '%s' where hubloc_hash = '%s'",
                    dbesc($webfinger),
                    dbesc($url)
                );
            }
            if ($inbox !== $h[0]['hubloc_callback']) {
                q(
                    "update hubloc set hubloc_callback = '%s' where hubloc_hash = '%s'",
                    dbesc($inbox),
                    dbesc($url)
                );
            }
            if ($profile !== $h[0]['hubloc_id_url']) {
                q(
                    "update hubloc set hubloc_id_url = '%s' where hubloc_hash = '%s'",
                    dbesc($profile),
                    dbesc($url)
                );
            }
            q(
                "update hubloc set hubloc_updated = '%s' where hubloc_hash = '%s'",
                dbesc(datetime_convert()),
                dbesc($url)
            );
        }

        if (!$icon) {
            $icon = z_root() . '/' . Channel::get_default_profile_photo();
        }

        // We store all ActivityPub actors we can resolve. Some of them may be able to communicate over Zot6. Find them.
        // Only probe if it looks like it looks something like a zot6 URL as there isn't anything in the actor record which we can reliably use for this purpose
        // and adding zot discovery urls to the actor record will cause federation to fail with the 20-30 projects which don't accept arrays in the url field.

        if (str_contains($url, '/channel/')) {
            $zx = q(
                "select * from hubloc where hubloc_id_url = '%s' and hubloc_network in ('zot6','nomad') and hubloc_deleted = 0",
                dbesc($url)
            );
            if ($webfinger && (!$zx)) {
                Run::Summon(['Gprobe', $webfinger]);
            }
        }

        Run::Summon(['Xchan_photo', bin2hex($icon), bin2hex($url)]);
    }

    public static function update_protocols($xchan, $str)
    {
        $existing = explode(',', get_xconfig($xchan, 'system', 'protocols', EMPTY_STR));
        if (!in_array($str, $existing)) {
            $existing[] = $str;
            set_xconfig($xchan, 'system', 'protocols', implode(',', $existing));
        }
    }


    public static function drop($channel, $observer, $act)
    {
        $r = q(
            "select * from item where mid = '%s' and uid = %d limit 1",
            dbesc((is_array($act->obj)) ? $act->obj['id'] : $act->obj),
            intval($channel['channel_id'])
        );
    
        if (!$r) {
            return;
        }

        if (in_array($observer, [$r[0]['author_xchan'], $r[0]['owner_xchan']])) {
            drop_item($r[0]['id'], observer_hash: $observer);
        }
        elseif (in_array($act->actor['id'], [$r[0]['author_xchan'], $r[0]['owner_xchan']])) {
            drop_item($r[0]['id']);
        }
    }


    // sort function width decreasing

    public static function vid_sort($a, $b)
    {
        if ($a['width'] === $b['width']) {
            return 0;
        }
        return (($a['width'] > $b['width']) ? -1 : 1);
    }

    public static function get_actor_bbmention($id)
    {

        $x = hublocx_id_query($id, 1);

        if ($x) {
            // a name starting with a left paren can trick the Markdown parser into creating a link so insert a zero-width space
            if (str_starts_with($x[0]['xchan_name'], '(')) {
                $x[0]['xchan_name'] = htmlspecialchars_decode('&#8203;', ENT_QUOTES) . $x[0]['xchan_name'];
            }

            return sprintf('@[zrl=%s]%s[/zrl]', $x[0]['xchan_url'], $x[0]['xchan_name']);
        }
        return '@{' . $id . '}';
    }

    public static function update_poll($item, $post, $deliver = true)
    {

        logger('updating poll');

        $multi = false;
        $mid = $post['mid'];
        $content = trim($post['title']);

        if (!$item) {
            return false;
        }

        $o = json_decode($item['obj'], true);
        if ($o && array_key_exists('anyOf', $o)) {
            $multi = true;
        }

        $r = q(
            "select mid, title from item where parent_mid = '%s' and author_xchan = '%s' and mid != parent_mid ",
            dbesc($item['mid']),
            dbesc($post['author_xchan'])
        );

        // prevent any duplicate votes by same author for oneOf and duplicate votes with same author and same answer for anyOf

        if ($r) {
            if ($multi) {
                foreach ($r as $rv) {
                    if (trim($rv['title']) === $content && $rv['mid'] !== $mid) {
                        return false;
                    }
                }
            } else {
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
            for ($c = 0; $c < count($o['anyOf']); $c++) {
                if (trim($o['anyOf'][$c]['name']) === $content) {
                    $answer_found = true;
                    if (is_array($o['anyOf'][$c]['replies'])) {
                        foreach ($o['anyOf'][$c]['replies'] as $reply) {
                            if (is_array($reply) && array_key_exists('id', $reply) && $reply['id'] === $mid) {
                                $found = true;
                            }
                        }
                    }

                    if (!$found) {
                        $o['anyOf'][$c]['replies']['totalItems']++;
                        $o['anyOf'][$c]['replies']['items'][] = ['id' => $mid, 'type' => 'Note'];
                    }
                }
            }
        } else {
            for ($c = 0; $c < count($o['oneOf']); $c++) {
                if (trim($o['oneOf'][$c]['name']) === $content) {
                    $answer_found = true;
                    if (is_array($o['oneOf'][$c]['replies'])) {
                        foreach ($o['oneOf'][$c]['replies'] as $reply) {
                            if (is_array($reply) && array_key_exists('id', $reply) && $reply['id'] === $mid) {
                                $found = true;
                            }
                        }
                    }

                    if (!$found) {
                        $o['oneOf'][$c]['replies']['totalItems']++;
                        $o['oneOf'][$c]['replies']['items'][] = ['id' => $mid, 'type' => 'Note'];
                    }
                }
            }
        }

        if ($item['comments_closed'] > NULL_DATE) {
            if ($item['comments_closed'] > datetime_convert()) {
                $o['closed'] = datetime_convert('UTC', 'UTC', $item['comments_closed'], ATOM_TIME);
                // set this to force an update
                $answer_found = true;
            }
        }

        logger('updated_poll: ' . print_r($o, true), LOGGER_DATA);
        if ($answer_found && !$found && $deliver) {
            q(
                "update item set obj = '%s', edited = '%s' where id = %d",
                dbesc(json_encode($o)),
                dbesc(datetime_convert()),
                intval($item['id'])
            );
            Run::Summon(['Notifier', 'wall-new', $item['id']]);
            return true;
        }

        return false;
    }


    public static function decode_note($act, $cacheable = false)
    {

        $response_activity = false;

        $s = [];

        if (is_array($act->obj)) {
            $binary = false;
            $markdown = false;
            $mediatype = $act->objprop('mediaType','');
            if ($mediatype && $mediatype !== 'text/html') {
                if ($mediatype === 'text/markdown') {
                    $markdown = true;
                } else {
                    $s['mimetype'] = escape_tags($mediatype);
                    $binary = true;
                }
            }

            $content = self::get_content($act->obj, $binary);

            if ($cacheable) {
                // Zot6 activities will all be rendered from bbcode source in order to generate dynamic content.
                // If the activity came from ActivityPub (hence $cacheable is set), use the HTML rendering
                // and discard the bbcode source since it is unlikely that it is compatible with our implementation.
                //
                // Friendica for example.

                unset($content['bbcode']);
            }

            // handle markdown conversion inline (peertube)

            if ($markdown) {
                foreach (['summary', 'content'] as $t) {
                    $content[$t] = Markdown::to_bbcode($content[$t], true, ['preserve_lf' => true]);
                }
            }
        }

        // These activities should have been handled separately in the Inbox module and should not be turned into posts

        if (
            in_array($act->type, ['Follow', 'Accept', 'Reject', 'Create', 'Update'])
                && ($act->objprop('type') === 'Follow' || ActivityStreams::is_an_actor($act->objprop('type')))
        ) {
            return false;
        }

        // Within our family of projects, Follow/Unfollow of a thread is an internal activity which should not be transmitted,
        // hence if we receive it - ignore or reject it.
        // This may have to be revisited if AP projects start using Follow for objects other than actors.

        if (in_array($act->type, [ACTIVITY_FOLLOW, ACTIVITY_IGNORE])) {
            return false;
        }

        // Do not proceed further if there is no actor.

        if (!isset($act->actor['id'])) {
            logger('No actor!');
            return false;
        }

        $s['owner_xchan'] = $act->actor['id'];
        $s['author_xchan'] = $act->actor['id'];

        // ensure we store the original actor
        self::actor_store($act->actor['id'], $act->actor);

        $s['mid'] = ($act->objprop('id')) ? $act->objprop('id') : $act->obj;

        if (!$s['mid']) {
            return false;
        }

        $s['parent_mid'] = $act->parent_id;

        if (array_key_exists('published', $act->data) && $act->data['published']) {
            $s['created'] = datetime_convert('UTC', 'UTC', $act->data['published']);
        } elseif ($act->objprop('published')) {
            $s['created'] = datetime_convert('UTC', 'UTC', $act->obj['published']);
        }
        if (array_key_exists('updated', $act->data) && $act->data['updated']) {
            $s['edited'] = datetime_convert('UTC', 'UTC', $act->data['updated']);
        } elseif ($act->objprop('updated')) {
            $s['edited'] = datetime_convert('UTC', 'UTC', $act->obj['updated']);
        }
        if (array_key_exists('expires', $act->data) && $act->data['expires']) {
            $s['expires'] = datetime_convert('UTC', 'UTC', $act->data['expires']);
        } elseif ($act->objprop('expires')) {
            $s['expires'] = datetime_convert('UTC', 'UTC', $act->obj['expires']);
        }

        if ($act->type === 'Invite' && $act->objprop('type') === 'Event') {
            $s['mid'] = $s['parent_mid'] = $act->id;
        }

        if (isset($act->replyto) && !empty($act->replyto)) {
            if (is_array($act->replyto) && isset($act->replyto['id'])) {
                $s['replyto'] = $act->replyto['id'];
            } else {
                $s['replyto'] = $act->replyto;
            }
        }

        if (ActivityStreams::is_response_activity($act->type)) {
            $response_activity = true;

            $s['mid'] = $act->id;

            $s['parent_mid'] = ($act->objprop('id')) ? $act->objprop('id') : $act->obj;

            // Something went horribly wrong. The activity object isn't a string but doesn't have an id.
            // Seen in the wild with a post from jasonrobinson.me being liked by a Friendica account.

            if (! is_string($s['parent_mid'])) {
                return false;
            }

            // over-ride the object timestamp with the activity

            if (isset($act->data['published']) && $act->data['published']) {
                $s['created'] = datetime_convert('UTC', 'UTC', $act->data['published']);
            }

            if (isset($act->data['updated']) && $act->data['updated']) {
                $s['edited'] = datetime_convert('UTC', 'UTC', $act->data['updated']);
            }

            $obj_actor = ($act->objprop('actor')) ? $act->obj['actor'] : $act->get_actor('attributedTo', $act->obj);

            // Actor records themselves do not have an actor or attributedTo
            if ((!$obj_actor) && $act->objprop('type') && ActivityStreams::is_an_actor($act->obj['type'])) {
                $obj_actor = $act->obj;
            }

            // ensure that the object actor record has been fetched and is an array.
            if (is_string($obj_actor)) {
                $obj_actor = Activity::fetch($obj_actor);
            }
            if (! is_array($obj_actor)) {
                return false;
            }

            // We already check for admin blocks of third-party objects when fetching them explicitly.
            // Repeat here just in case the entire object was supplied inline and did not require fetching

            if (array_key_exists('id', $obj_actor)) {
                $m = parse_url($obj_actor['id']);
                if ($m && $m['scheme'] && $m['host']) {
                    if (!check_siteallowed($m['scheme'] . '://' . $m['host'])) {
                        return false;
                    }
                }
                if (!check_channelallowed($obj_actor['id'])) {
                    return false;
                }
            }

            // if the object is an actor, it is not really a response activity, so reset it to a top level post

            if ($act->objprop('type') && ActivityStreams::is_an_actor($act->obj['type'])) {
                $s['parent_mid'] = $s['mid'];
            }

            // ensure we store the original actor of the associated (parent) object
            self::actor_store($obj_actor['id'], $obj_actor);

            $mention = self::get_actor_bbmention($obj_actor['id']);

            $quoted_content = '[quote]' . $content['content'] . '[/quote]';

            $object_type = $act->objprop('type', t('Activity'));
            if (ActivityStreams::is_an_actor($object_type)) {
                $object_type = t('Profile');
            }

            if ($act->type === 'Like') {
                $content['content'] = sprintf(t('Likes %1$s\'s %2$s'), $mention, $object_type) . EOL . EOL . $quoted_content;
            }
            if ($act->type === 'Dislike') {
                $content['content'] = sprintf(t('Doesn\'t like %1$s\'s %2$s'), $mention, $object_type) . EOL . EOL . $quoted_content;
            }

            // handle event RSVPs
            if (($object_type === 'Event') || ($object_type === 'Invite' && array_path_exists('object/type', $act->obj) && $act->obj['object']['type'] === 'Event')) {
                if ($act->type === 'Accept') {
                    $content['content'] = sprintf(t('Will attend %s\'s event'), $mention) . EOL . EOL . $quoted_content;
                }
                if ($act->type === 'Reject') {
                    $content['content'] = sprintf(t('Will not attend %s\'s event'), $mention) . EOL . EOL . $quoted_content;
                }
                if ($act->type === 'TentativeAccept') {
                    $content['content'] = sprintf(t('May attend %s\'s event'), $mention) . EOL . EOL . $quoted_content;
                }
                if ($act->type === 'TentativeReject') {
                    $content['content'] = sprintf(t('May not attend %s\'s event'), $mention) . EOL . EOL . $quoted_content;
                }
            }

            if ($act->type === 'Announce') {
                $content['content'] = sprintf(t('&#x1f501; Repeated %1$s\'s %2$s'), $mention, $object_type);
            }

            if ($act->type === 'emojiReaction') {
                // Hubzilla reactions
                $content['content'] = (($act->tgt && $act->tgt['type'] === 'Image') ? '[img=32x32]' . $act->tgt['url'] . '[/img]' : '&#x' . $act->tgt['name'] . ';');
            }

            if (in_array($act->type, ['EmojiReaction', 'EmojiReact'])) {
                // Pleroma reactions
                $t = trim(self::get_textfield($act->data, 'content'));
                $e = Emoji\is_single_emoji($t) || mb_strlen($t) === 1;
                if ($e) {
                    $content['content'] = $t;
                }
            }

            $a = self::decode_taxonomy($act->data);
            if ($a) {
                $s['term'] = $a;
                foreach ($a as $b) {
                    if ($b['ttype'] === TERM_EMOJI) {
                        $s['summary'] = str_replace($b['term'], '[img=16x16]' . $b['url'] . '[/img]', $s['summary']);

                        // @todo - @bug
                        // The emoji reference in the body might be inside a code block. In that case we shouldn't replace it.
                        // Currently we do.

                        $s['body'] = str_replace($b['term'], '[img=16x16]' . $b['url'] . '[/img]', $s['body']);
                    }
                }
            }

            $a = self::decode_attachment($act->data);
            if ($a) {
                $s['attach'] = $a;
            }

            $a = self::decode_iconfig($act->data);
            if ($a) {
                $s['iconfig'] = $a;
            }
        }

        $s['comment_policy'] = 'authenticated';

        if ($s['mid'] === $s['parent_mid']) {
            // it is a parent node - decode the comment policy info if present
            if ($act->objprop('commentPolicy')) {
                $until = strpos($act->obj['commentPolicy'], 'until=');
                if ($until !== false) {
                    $s['comments_closed'] = datetime_convert('UTC', 'UTC', substr($act->obj['commentPolicy'], $until + 6));
                    if ($s['comments_closed'] < datetime_convert()) {
                        $s['item_nocomment'] = true;
                    }
                }
                $remainder = substr($act->obj['commentPolicy'], 0, (($until) ?: strlen($act->obj['commentPolicy'])));
                if (!empty($remainder)) {
                    $s['comment_policy'] = $remainder;
                }
            }
            if (!$s['comment_policy'] && isset($act->objprop['canReply'])) {
                if (empty($act->objprop['canReply'])) {
                    $s['item_nocomment'] = true;
                }
                elseif (! is_array($act->objprop['canReply'])) {
                    $act->objprop['canReply'] = [$act->objprop['canReply']];
                }
                if (is_array($act->objprop['canReply'])) {
                    foreach ($act->objprop['canReply'] as $canReply) {
                        if (in_array($canReply, [ACTIVITY_PUBLIC_INBOX, 'Public', 'as:Public'])) {
                            $s['comment_policy'] = 'authenticated';
                            break;
                        }
                    }
                    if (!$s['comment_policy']) {
                        foreach ($act->objprop['canReply'] as $canReply) {
                            if (strpos($canReply, 'follow')) {
                                $s['comment_policy'] = 'contacts';
                            }
                        }
                    }
                }
            }
        }


        if (!(array_key_exists('created', $s) && $s['created'])) {
            $s['created'] = datetime_convert();
        }
        if (!(array_key_exists('edited', $s) && $s['edited'])) {
            $s['edited'] = $s['created'];
        }
        $s['title'] = (($response_activity) ? EMPTY_STR : self::bb_content($content, 'name'));
        $s['summary'] = self::bb_content($content, 'summary');

        if (array_key_exists('mimetype', $s) && (!in_array($s['mimetype'], ['text/bbcode', 'text/x-multicode']))) {
            $s['body'] = $content['content'];
        } else {
            $s['body'] = ((self::bb_content($content, 'bbcode') && (!$response_activity)) ? self::bb_content($content, 'bbcode') : self::bb_content($content, 'content'));
        }

        // For the special snowflakes who can't figure out how to use attachments.

        foreach ( ['quoteUrl', 'quoteUri', '_misskey_quote'] as $quote) {
            $quote_url = $act->get_property_obj($quote);
            if ($quote_url) {
                $s = self::get_quote($quote_url,$s);
            }
            elseif ($act->objprop($quote)) {
                $s = self::get_quote($act->obj[$quote],$s);
            }
        }

        // handle some of the more widely used of the numerous and varied ways of deleting something

        if (in_array($act->type, ['Delete', 'Undo', 'Tombstone'])) {
            $s['item_deleted'] = 1;
        }

        if ($act->type === 'Create' && $act->obj['type'] === 'Tombstone') {
            $s['item_deleted'] = 1;
        }

        if ($act->objprop('sensitive')) {
            $s['item_nsfw'] = 1;
        }

        $s['verb'] = self::activity_mapper($act->type);

        // Mastodon does not provide update timestamps when updating poll tallies which means race conditions may occur here.
        if (in_array($act->type,['Create','Update']) && $act->objprop('type') === 'Question' && $s['edited'] === $s['created']) {
            if (intval($act->objprop('votersCount'))) {
                $s['edited'] = datetime_convert();
            }
        }

        if ($act->objprop('type')) {
            $s['obj_type'] = self::activity_obj_mapper($act->obj['type']);
        }
        $s['obj'] = $act->obj;

        if (array_path_exists('actor/id', $s['obj'])) {
            $s['obj']['actor'] = $s['obj']['actor']['id'];
        }

        if (is_array($act->tgt) && $act->tgt) {
            if (array_key_exists('type', $act->tgt)) {
                $s['tgt_type'] = self::activity_obj_mapper($act->tgt['type']);
            }
            // We shouldn't need to store collection contents which could be large. We will often only require the meta-data
            if (isset($s['tgt_type']) && str_contains($s['tgt_type'], 'Collection')) {
                $s['target'] = ['id' => $act->tgt['id'], 'type' => $s['tgt_type'], 'attributedTo' => ((isset($act->tgt['attributedTo'])) ? $act->get_actor('attributedTo', $act->tgt) : $act->get_actor('actor', $act->tgt))];
            }
        }

        $generator = $act->get_property_obj('generator');
        if ((!$generator) && (!$response_activity)) {
            $generator = $act->get_property_obj('generator', $act->obj);
        }

        if (
            $generator && array_key_exists('type', $generator)
            && in_array($generator['type'], ['Application', 'Service', 'Organization']) && array_key_exists('name', $generator)
        ) {
            $s['app'] = escape_tags($generator['name']);
        }

        $location = $act->get_property_obj('location');
        if (is_array($location) && array_key_exists('type', $location) && $location['type'] === 'Place') {
            if (array_key_exists('name', $location)) {
                $s['location'] = escape_tags($location['name']);
                // Look for something resembling latitude/longitude coordinates in the place name and set the
                // coordinates appropriately. This technically isn't supported but is provided as a convenience
                // to reduce support requests.
                $latlon = '/(?<!\d)([-+]?(?:[1-8]?\d(?:\.\d+)?|90(?:\.0+)?)),\s*([-+]?(?:180(?:\.0+)?|(?:(?:1[0-7]\d)|(?:[1-9]?\d))(?:\.\d+)?))(?!\d)/';
                if (preg_match($latlon,$s['location'], $matches)) {
                    $s['lat'] = floatval($matches[1]);
                    $s['lon'] = floatval($matches[2]);
                }
            }
            if (array_key_exists('content', $location)) {
                $s['location'] = html2plain(purify_html($location['content']), 256);
            }
            if (array_key_exists('latitude', $location) && array_key_exists('longitude', $location)) {
                $s['lat'] = floatval($location['latitude']);
                $s['lon'] = floatval($location['longitude']);
            }
        }

        if (is_array($act->obj) && !$response_activity) {
            $a = self::decode_taxonomy($act->obj);
            if ($a) {
                $s['term'] = $a;
                foreach ($a as $b) {
                    if ($b['ttype'] === TERM_EMOJI) {
                        $s['summary'] = str_replace($b['term'], '[img=16x16]' . $b['url'] . '[/img]', $s['summary']);

                        // @todo - @bug
                        // The emoji reference in the body might be inside a code block. In that case we shouldn't replace it.
                        // Currently we do.

                        $s['body'] = str_replace($b['term'], '[img=16x16]' . $b['url'] . '[/img]', $s['body']);
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

        // Objects that might have media attachments which aren't already provided in the content element.
        // We'll check specific media objects separately.

        if (in_array($act->objprop('type',''), ['Article', 'Document', 'Event', 'Note', 'Page', 'Place', 'Question'])
                && isset($s['attach']) && $s['attach']) {
            $s = self::bb_attach($s);
        }


        if ($act->objprop('type') === 'Question' && in_array($act->type, ['Create', 'Update'])) {
            if ($act->objprop['endTime']) {
                $s['comments_closed'] = datetime_convert('UTC', 'UTC', $act->obj['endTime']);
            }
        }

        if ($act->objprop('closed')) {
            $s['comments_closed'] = datetime_convert('UTC', 'UTC', $act->obj['closed']);
        }

        // we will need a hook here to extract magnet links e.g. peertube
        // right now just link to the largest mp4 we find that will fit in our
        // standard content region

        if (!$response_activity) {
            if ($act->objprop('type') === 'Video') {
                $vtypes = [
                    'video/mp4',
                    'video/ogg',
                    'video/webm'
                ];

                $mps = [];
                $poster = null;
                $ptr = null;

                // try to find a poster to display on the video element

                if ($act->objprop('icon')) {
                    if (is_array($act->obj['icon'])) {
                        if (array_key_exists(0, $act->obj['icon'])) {
                            $ptr = $act->obj['icon'];
                        } else {
                            $ptr = [$act->obj['icon']];
                        }
                    }
                    if ($ptr) {
                        foreach ($ptr as $foo) {
                            if (is_array($foo) && array_key_exists('type', $foo) && $foo['type'] === 'Image' && is_string($foo['url'])) {
                                $poster = $foo['url'];
                            }
                        }
                    }
                }

                $tag = (($poster) ? '[video poster=&quot;' . $poster . '&quot;]' : '[video]');
                $ptr = null;

                if ($act->objprop('url')) {
                    if (is_array($act->obj['url'])) {
                        if (array_key_exists(0, $act->obj['url'])) {
                            $ptr = $act->obj['url'];
                        } else {
                            $ptr = [$act->obj['url']];
                        }
                        // handle peertube's weird url link tree if we find it here
                        // 0 => html link, 1 => application/x-mpegURL with 'tag' set to an array of actual media links
                        foreach ($ptr as $idex) {
                            if (is_array($idex) && array_key_exists('mediaType', $idex)) {
                                if ($idex['mediaType'] === 'application/x-mpegURL' && isset($idex['tag']) && is_array($idex['tag'])) {
                                    $ptr = $idex['tag'];
                                    break;
                                }
                            }
                        }
                        foreach ($ptr as $vurl) {
                            if (array_key_exists('mediaType', $vurl)) {
                                if (in_array($vurl['mediaType'], $vtypes)) {
                                    if (!array_key_exists('width', $vurl)) {
                                        $vurl['width'] = 0;
                                    }
                                    $mps[] = $vurl;
                                }
                            }
                        }
                    }
                    if ($mps) {
                        usort($mps, [__CLASS__, 'vid_sort']);
                        foreach ($mps as $m) {
                            if (intval($m['width']) < 500 && self::media_not_in_body($m['href'], $s['body'])) {
                                $s['body'] .= "\n\n" . $tag . $m['href'] . '[/video]';
                                break;
                            }
                        }
                    } elseif (is_string($act->obj['url']) && self::media_not_in_body($act->obj['url'], $s['body'])) {
                        $s['body'] .= "\n\n" . $tag . $act->obj['url'] . '[/video]';
                    }
                }
            }

            if ($act->objprop('type') === 'Audio') {
                $atypes = [
                    'audio/mpeg',
                    'audio/ogg',
                    'audio/wav'
                ];

                $ptr = null;

                if (array_key_exists('url', $act->obj)) {
                    if (is_array($act->obj['url'])) {
                        if (array_key_exists(0, $act->obj['url'])) {
                            $ptr = $act->obj['url'];
                        } else {
                            $ptr = [$act->obj['url']];
                        }
                        foreach ($ptr as $vurl) {
                            if (isset($vurl['mediaType']) && in_array($vurl['mediaType'], $atypes) && self::media_not_in_body($vurl['href'], $s['body'])) {
                                $s['body'] .= "\n\n" . '[audio]' . $vurl['href'] . '[/audio]';
                                break;
                            }
                        }
                    } elseif (is_string($act->obj['url']) && self::media_not_in_body($act->obj['url'], $s['body'])) {
                        $s['body'] .= "\n\n" . '[audio]' . $act->obj['url'] . '[/audio]';
                    }
                } // Pleroma audio scrobbler
                elseif ($act->type === 'Listen' && array_key_exists('artist', $act->obj) && array_key_exists('title', $act->obj) && $s['body'] === EMPTY_STR) {
                    $s['body'] .= "\n\n" . sprintf('Listening to \"%1$s\" by %2$s', escape_tags($act->obj['title']), escape_tags($act->obj['artist']));
                    if (isset($act->obj['album'])) {
                        $s['body'] .= "\n" . sprintf('(%s)', escape_tags($act->obj['album']));
                    }
                }
            }

            if ($act->objprop('type') === 'Image' && !str_contains($s['body'], 'zrl=')) {
                $ptr = null;

                if (array_key_exists('url', $act->obj)) {
                    if (is_array($act->obj['url'])) {
                        if (array_key_exists(0, $act->obj['url'])) {
                            $ptr = $act->obj['url'];
                        } else {
                            $ptr = [$act->obj['url']];
                        }
                        foreach ($ptr as $vurl) {
                            if (is_array($vurl) && isset($vurl['href']) && !str_contains($s['body'], $vurl['href'])) {
                                $s['body'] .= "\n\n" . '[zmg]' . $vurl['href'] . '[/zmg]';
                                break;
                            }
                        }
                    } elseif (is_string($act->obj['url'])) {
                        if (!str_contains($s['body'], $act->obj['url'])) {
                            $s['body'] .= "\n\n" . '[zmg]' . $act->obj['url'] . '[/zmg]';
                        }
                    }
                }
            }


            if ($act->objprop('type') === 'Page' && !$s['body']) {
                $ptr = null;
                $purl = EMPTY_STR;

                if (array_key_exists('url', $act->obj)) {
                    if (is_array($act->obj['url'])) {
                        if (array_key_exists(0, $act->obj['url'])) {
                            $ptr = $act->obj['url'];
                        } else {
                            $ptr = [$act->obj['url']];
                        }
                        foreach ($ptr as $vurl) {
                            if (is_array($vurl) && array_key_exists('mediaType', $vurl) && $vurl['mediaType'] === 'text/html') {
                                $purl = $vurl['href'];
                                break;
                            } elseif (array_key_exists('mimeType', $vurl) && $vurl['mimeType'] === 'text/html') {
                                $purl = $vurl['href'];
                                break;
                            }
                        }
                    } elseif (is_string($act->obj['url'])) {
                        $purl = $act->obj['url'];
                    }
                    if ($purl) {
                        $li = Url::get(z_root() . '/linkinfo?binurl=' . bin2hex($purl));
                        if ($li['success'] && $li['body']) {
                            $s['body'] .= "\n" . $li['body'];
                        } else {
                            $s['body'] .= "\n\n" . $purl;
                        }
                    }
                }
            }
        }


        if (in_array($act->objprop('type'), ['Note', 'Article', 'Page'])) {
            $ptr = null;

            if (array_key_exists('url', $act->obj)) {
                if (is_array($act->obj['url'])) {
                    if (array_key_exists(0, $act->obj['url'])) {
                        $ptr = $act->obj['url'];
                    } else {
                        $ptr = [$act->obj['url']];
                    }
                    foreach ($ptr as $vurl) {
                        if (is_array($vurl) && array_key_exists('mediaType', $vurl) && $vurl['mediaType'] === 'text/html') {
                            $s['plink'] = $vurl['href'];
                            break;
                        }
                    }
                } elseif (is_string($act->obj['url'])) {
                    $s['plink'] = $act->obj['url'];
                }
            }
        }

        if (!(isset($s['plink']) && $s['plink'])) {
            $s['plink'] = $s['mid'];
        }

        // assume this is private unless specifically told otherwise.

        $s['item_private'] = 1;

        if ($act->recips && (in_array(ACTIVITY_PUBLIC_INBOX, $act->recips) || in_array('Public', $act->recips) || in_array('as:Public', $act->recips))) {
            $s['item_private'] = 0;
        }

        if ($act->objprop('directMessage')) {
            $s['item_private'] = 2;
        }

        set_iconfig($s, 'activitypub', 'recips', $act->raw_recips);

        if (array_key_exists('directMessage', $act->data) && intval($act->data['directMessage'])) {
            $s['item_private'] = 2;
        }

        if (in_array($s['verb'], ['Arrive', 'Leave'])) {
            if ($s['lat'] || $s['lon']) {
                if (!str_contains($s['body'],'[map=')) {
                    $s['body'] .= "\n\n" . '[map=' . $s['lat'] . ',' . $s['lon'] . ']' . "\n";
                }
            }
            elseif ($s['location']) {
                if (!str_contains($s['body'],'[map]'))  {
                    $s['body'] .= "\n\n" . '[map]' . $s['location'] . '[/map]' . "\n";
                }
            }
        }

        // Restrict html caching to ActivityPub senders.
        // Zot has dynamic content and this library is used by both.

        if ($cacheable) {
            if ((!array_key_exists('mimetype', $s)) || (in_array($s['mimetype'], ['text/bbcode', 'text/x-multicode']))) {
                // preserve the original purified HTML content *unless* we've modified $s['body']
                // within this function (to add attachments or reaction descriptions or mention rewrites).
                // This avoids/bypasses some markdown rendering issues which can occur when
                // converting to our markdown-enhanced bbcode and then back to HTML again.
                // Also, if we do need bbcode, use the 'bbonly' flag to ignore Markdown and only
                // interpret bbcode; which is much less susceptible to false positives in the
                // conversion regexes.

                if ($s['body'] === self::bb_content($content, 'content')) {
                    $s['html'] = $content['content'];
                } else {
                    $s['html'] = bbcode($s['body'], ['bbonly' => true]);
                }
            }
        }

        if ($s['term']) {
            foreach ($s['term'] as $t) {
                if ($t['ttype'] === TERM_QUOTED && self::share_not_in_body($s['body'])) {
                    $s = self::get_quote($t['url'], $s);
                }
            }
        }

        $hookinfo = [
            'act' => $act,
            's' => $s
        ];

        Hook::call('decode_note', $hookinfo);

        return $hookinfo['s'];

    }

    public static function rewrite_mentions_sub(&$s, $pref, &$obj = null)
    {

        if (isset($s['term']) && is_array($s['term'])) {
            foreach ($s['term'] as $tag) {
                $txt = EMPTY_STR;
                if (intval($tag['ttype']) === TERM_MENTION) {
                    // some platforms put the identity url into href rather than the profile url. Accept either form.
                    $x = q(
                        "select * from xchan where xchan_url = '%s' or xchan_hash = '%s' limit 1",
                        dbesc($tag['url']),
                        dbesc($tag['url'])
                    );
                    if (! $x) {
                        // This tagged identity has never before been seen on this site. Perform discovery and retry.
                        /** @noinspection PhpUnusedLocalVariableInspection */
                        $hash = discover_resource($tag['url']);
                        $x = q(
                            "select * from xchan where xchan_url = '%s' or xchan_hash = '%s' limit 1",
                            dbesc($tag['url']),
                            dbesc($tag['url'])
                        );
                    }
                    if ($x) {
                        switch ($pref) {
                            case 0:
                                $txt = $x[0]['xchan_name'];
                                break;
                            case 1:
                                $txt = (($x[0]['xchan_addr']) ?: $x[0]['xchan_name']);
                                break;
                            case 2:
                            default;
                                if ($x[0]['xchan_addr']) {
                                    $txt = sprintf(t('%1$s (%2$s)'), $x[0]['xchan_name'], $x[0]['xchan_addr']);
                                } else {
                                    $txt = $x[0]['xchan_name'];
                                }
                                break;
                        }
                    }
                }

                if ($txt) {
                    // the Markdown filter will get tripped up and think this is a Markdown link
                    // if $txt begins with parens, so put it behind a zero-width space
                    if (str_starts_with($txt, '(')) {
                        $txt = htmlspecialchars_decode('&#8203;', ENT_QUOTES) . $txt;
                    }
                    $s['body'] = preg_replace(
                        '/@\[zrl=' . preg_quote($x[0]['xchan_url'], '/') . '](.*?)\[\/zrl]/ism',
                        '@[zrl=' . $x[0]['xchan_url'] . ']' . $txt . '[/zrl]',
                        $s['body']
                    );
                    $s['body'] = preg_replace(
                        '/@\[url=' . preg_quote($x[0]['xchan_url'], '/') . '](.*?)\[\/url]/ism',
                        '@[url=' . $x[0]['xchan_url'] . ']' . $txt . '[/url]',
                        $s['body']
                    );
                    $s['body'] = preg_replace(
                        '/\[zrl=' . preg_quote($x[0]['xchan_url'], '/') . ']@(.*?)\[\/zrl]/ism',
                        '@[zrl=' . $x[0]['xchan_url'] . ']' . $txt . '[/zrl]',
                        $s['body']
                    );
                    $s['body'] = preg_replace(
                        '/\[url=' . preg_quote($x[0]['xchan_url'], '/') . ']@(.*?)\[\/url]/ism',
                        '@[url=' . $x[0]['xchan_url'] . ']' . $txt . '[/url]',
                        $s['body']
                    );

                    // replace these just in case the sender (in this case Friendica) got it wrong
                    $s['body'] = preg_replace(
                        '/@\[zrl=' . preg_quote($x[0]['xchan_hash'], '/') . '](.*?)\[\/zrl]/ism',
                        '@[zrl=' . $x[0]['xchan_url'] . ']' . $txt . '[/zrl]',
                        $s['body']
                    );
                    $s['body'] = preg_replace(
                        '/@\[url=' . preg_quote($x[0]['xchan_hash'], '/') . '](.*?)\[\/url]/ism',
                        '@[url=' . $x[0]['xchan_url'] . ']' . $txt . '[/url]',
                        $s['body']
                    );
                    $s['body'] = preg_replace(
                        '/\[zrl=' . preg_quote($x[0]['xchan_hash'], '/') . ']@(.*?)\[\/zrl]/ism',
                        '@[zrl=' . $x[0]['xchan_url'] . ']' . $txt . '[/zrl]',
                        $s['body']
                    );
                    $s['body'] = preg_replace(
                        '/\[url=' . preg_quote($x[0]['xchan_hash'], '/') . ']@(.*?)\[\/url]/ism',
                        '@[url=' . $x[0]['xchan_url'] . ']' . $txt . '[/url]',
                        $s['body']
                    );

                    if ($obj && $txt) {
                        if (!is_array($obj)) {
                            $obj = json_decode($obj, true);
                        }
                        if (array_path_exists('source/content', $obj)) {
                            $obj['source']['content'] = preg_replace(
                                '/@\[zrl=' . preg_quote($x[0]['xchan_url'], '/') . '](.*?)\[\/zrl]/ism',
                                '@[zrl=' . $x[0]['xchan_url'] . ']' . $txt . '[/zrl]',
                                $obj['source']['content']
                            );
                            $obj['source']['content'] = preg_replace(
                                '/@\[url=' . preg_quote($x[0]['xchan_url'], '/') . '](.*?)\[\/url]/ism',
                                '@[url=' . $x[0]['xchan_url'] . ']' . $txt . '[/url]',
                                $obj['source']['content']
                            );
                        }
                        /** @noinspection HtmlUnknownAttribute */
                        $obj['content'] = preg_replace('/@(.*?)<a (.*?)href=\"' . preg_quote($x[0]['xchan_url'], '/') . '\"(.*?)>(.*?)<\/a>/ism',
                            '@$1<a $2 href="' . $x[0]['xchan_url'] . '"$3>' . $txt . '</a>',
                            $obj['content']
                        );
                    }
                }
            }
        }

        // $s['html'] will be populated if caching was enabled.
        // This is usually the case for ActivityPub sourced content, while Zot6 content is not cached.

        if (isset($s['html']) && $s['html']) {
            $s['html'] = bbcode($s['body'], ['bbonly' => true]);
        }
    }

    public static function rewrite_mentions(&$s)
    {
        // rewrite incoming mentions in accordance with system.tag_username setting
        // 0 - displayname
        // 1 - username
        // 2 - displayname (username)
        // 127 - default

        $pref = intval(PConfig::Get($s['uid'], 'system', 'tag_username', Config::Get('system', 'tag_username')));

        if ($pref === 127) {
            return;
        }
        self::rewrite_mentions_sub($s, $pref);
    }

    // $force is used when manually fetching a remote item - it assumes you are granting one-time
    // permission for the selected item/conversation regardless of your relationship with the author and
    // assumes that you are in fact the sender. Please do not use it for anything else. The only permission
    // checking that is performed is that the author isn't blocked by the site admin.

    public static function store($channel, $observer_hash, $act, $item, $fetch_parents = true, $force = false)
    {
        if ($act && $act->implied_create && !$force) {
            // This is originally a S2S object with no associated activity
            logger('Not storing implied create activity!');
            return;
        }

        $is_system = Channel::is_system($channel['channel_id']);
        $is_child_node = false;
        $commentApproval = null;

        // Pleroma scrobbles can be really noisy and contain lots of duplicate activities. Disable them by default.

        if (($act->type === 'Listen') && ($is_system || get_pconfig($channel['channel_id'], 'system', 'allow_scrobbles'))) {
            return;
        }

        // Mastodon only allows visibility in public timelines if the public inbox is listed in the 'to' field.
        // They are hidden in the public timeline if the public inbox is listed in the 'cc' field.
        // This is not part of the activitypub protocol - we might change this to show all public posts in pubstream at some point.

        $pubstream = is_array($act->obj)
            && array_key_exists('to', $act->obj)
            && is_array($act->obj['to'])
            && (
                in_array(ACTIVITY_PUBLIC_INBOX, $act->obj['to'])
                || in_array('Public', $act->obj['to'])
                || in_array('as:Public', $act->obj['to'])
            );

        // very unpleasant and imperfect way of determining a Mastodon DM

        if ($act->raw_recips
            && array_key_exists('to', $act->raw_recips)
            && is_array($act->raw_recips['to'])
            && count($act->raw_recips['to']) === 1
            && $act->raw_recips['to'][0] === Channel::url($channel)
            && !$act->raw_recips['cc']) {
            $item['item_private'] = 2;
        }

        if (Tombstone::check($item['mid'], $channel['channel_id'])
                || Tombstone::check($item['parent_mid'], $channel['channel_id'])) {
            logger('tombstone: post was deleted. Ignoring update.');
            return;
        }
    
        if ($item['parent_mid'] && $item['parent_mid'] !== $item['mid']) {
            $is_child_node = true;
        }

        $allowed = false;
        $reason = ['init'];
        $isMail = (bool) (intval($item['item_private']) === 2);
        if ($is_child_node) {
            $parent_item = q(
                "select * from item where mid = '%s' and uid = %d",
                dbesc($item['parent_mid']),
                intval($channel['channel_id'])
            );
            if ($parent_item) {
                $parent_item = array_shift($parent_item);
            }
            if ($parent_item && $parent_item['item_wall']) {
                // set the owner to the owner of the parent
                $item['owner_xchan'] = $parent_item['owner_xchan'];

                if ($parent_item['obj_type'] === 'Question') {
                    if ($item['obj_type'] === 'Note' && $item['title'] && (!$item['content'])) {
                        $item['obj_type'] = 'Answer';
                    }
                }
                if ($item['approved']) {
                    $valid = CommentApproval::verify($item, $channel);
                    if (!$valid) {
                        logger('commentApproval failed');
                        return;
                    }
                }

                if (in_array($item['verb'], ['Accept', 'Reject'])) {
                    if (CommentApproval::doVerify($item, $channel, $act)) {
                        return;
                    }
                }

                if (!$item['approved'] && $parent_item['owner_xchan'] === $channel['channel_hash'] && $item['author_xchan'] !== $channel['channel_hash']) {
                    $commentApproval = new CommentApproval($channel, $item);
                }
                // quietly reject group comment boosts by group owner
                // (usually only sent via ActivityPub so groups will work on microblog platforms)
                // This catches those activities if they slipped in via a conversation fetch

                if ($parent_item['parent_mid'] !== $item['parent_mid']) {
                    if ($item['verb'] === 'Announce' && $item['author_xchan'] === $item['owner_xchan']) {
                        logger('group boost activity by group owner suppressed');
                        return;
                    }
                }

                $allowed = self::comment_allowed($channel, $item, $parent_item);

                if ($allowed) {
                    // At this point we know it is allowed, but check if it requires moderation.
                    if (perm_is_allowed($channel['channel_id'], $item['author_xchan'], 'moderated')
                        || $allowed === 'moderated') {
                        $item['item_blocked'] = ITEM_MODERATED;
                    }
                    if ($item['item_blocked'] !== ITEM_MODERATED && $commentApproval) {
                        $commentApproval->Accept();
                    }

                }
                else {
                    logger('rejected comment from ' . $item['author_xchan'] . ' for ' . $channel['channel_address']);
                    logger('rejected: ' . print_r($item, true), LOGGER_DATA);
                    // let the sender know we received their comment, but we don't permit spam here.
                    $commentApproval?->Reject();
                    return;
                }


            } else {
                // By default, if we allow you to send_stream and comments and this is a comment, it is allowed.
                // A side effect of this action is that if you take away send_stream permission, comments to those
                // posts you previously allowed will still be accepted. It is possible but might be difficult to fix this.

                if ($item['approved']) {
                    $allowed = CommentApproval::verify($item, $channel);
                    if (!$allowed) {
                        logger('commentApproval failed');
                        return;
                    }
                }
                else {
                    $allowed = !(bool) Config::Get('system', 'use_fep5624');
                }

                // reject public stream comments that weren't sent by the conversation owner
                // but only on remote message deliveries to our site ($fetch_parents === true)

                if ($is_system && $pubstream && $item['owner_xchan'] !== $observer_hash && !$fetch_parents) {
                    $allowed = false;
                    $reason[] = 'sender ' . $observer_hash . ' not owner ' . $item['owner_xchan'];
                }
            }
        }
        else {
            if ((!$isMail) && (perm_is_allowed($channel['channel_id'], $observer_hash, 'send_stream') || ($is_system && $pubstream))) {
                logger('allowed: permission allowed', LOGGER_DATA);
                $allowed = true;
            }
            if (intval(PConfig::Get($channel['channel_id'], 'system', 'permit_all_mentions')
                && i_am_mentioned($channel, $item))) {
                logger('allowed: permitted mention', LOGGER_DATA);
                $allowed = true;
            }

            if (tgroup_check($channel['channel_id'], $item)) {
                logger('allowed: tgroup');
                $allowed = true;
            }
        }

        if (get_abconfig($channel['channel_id'], $observer_hash, 'system', 'block_announce')) {
            if ($item['verb'] === 'Announce' || strpos($item['body'], '[/share]')) {
                $allowed = false;
            }
        }

        if ($isMail) {
            $allowed = perm_is_allowed($channel['channel_id'], $observer_hash, 'post_mail');
            if (!$allowed) {
                logger('mail permission denied for "' . $observer_hash . '" ');
                $reason[] = 'mail permission';
            }
        }

        if ($is_system) {
            if (!check_pubstream_channelallowed($observer_hash)) {
                $allowed = false;
                $reason[] = 'pubstream channel blocked';
            }

            // don't allow pubstream posts if the sender even has a clone on a pubstream denied site

            $h = q(
                "select hubloc_url from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0",
                dbesc($observer_hash)
            );
            if ($h) {
                foreach ($h as $hub) {
                    if (!check_pubstream_siteallowed($hub['hubloc_url'])) {
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

        $blocked = LibBlock::fetch($channel['channel_id'], BLOCKTYPE_SERVER);
        if ($blocked) {
            foreach ($blocked as $b) {
                if (str_contains($observer_hash, $b['block_entity'])) {
                    $allowed = false;
                    $reason[] = 'blocked';
                }
            }
        }

        if (!$allowed && !$force) {
            logger('no permission: channel ' . $channel['channel_address'] . ', id = ' . $item['mid']);
            logger('no permission: reason ' . print_r($reason, true));
            return;
        }

        $item['aid'] = $channel['channel_account_id'];
        $item['uid'] = $channel['channel_id'];


        // Some authors may be zot6 authors in which case we want to store their nomadic identity
        // instead of their ActivityPub identity

        $item['author_xchan'] = self::find_best_identity($item['author_xchan']);
        $item['owner_xchan'] = self::find_best_identity($item['owner_xchan']);

        if (!($item['author_xchan'] && $item['owner_xchan'])) {
            logger('owner or author missing.');
            return;
        }

        $plaintext = prepare_text($item['body'],((isset($item['mimetype'])) ? $item['mimetype'] : 'text/x-multicode'));
        $plaintext = html2plain((isset($item['title']) && $item['title']) ? $item['title'] . ' ' . $plaintext : $plaintext);

        if ($channel['channel_system']) {
            if (!MessageFilter::evaluate($item, get_config('system', 'pubstream_incl'), get_config('system', 'pubstream_excl'), ['plaintext' => $plaintext])) {
                logger('post is filtered');
                return;
            }
        }

        // fetch allow/deny lists for the sender, author, or both
        // if you have them. post_is_importable() assumes true
        // and only fails if there was intentional rejection
        // due to this channel's filtering rules for content
        // provided by either of these entities.

        $abook = q(
            "select * from abook where ( abook_xchan = '%s' OR abook_xchan  = '%s' OR abook_xchan = '%s') and abook_channel = %d ",
            dbesc($item['author_xchan']),
            dbesc($item['owner_xchan']),
            dbesc($observer_hash),
            intval($channel['channel_id'])
        );


        if (!post_is_importable($channel['channel_id'], $item, $abook)) {
            logger('post is filtered');
            return;
        }

        $maxlen = get_max_import_size();

        if ($maxlen && mb_strlen($item['body']) > $maxlen) {
            $item['body'] = mb_substr($item['body'], 0, $maxlen, 'UTF-8');
            logger('message length exceeds max_import_size: truncated');
        }

        if ($maxlen && mb_strlen($item['summary']) > $maxlen) {
            $item['summary'] = mb_substr($item['summary'], 0, $maxlen, 'UTF-8');
            logger('message summary length exceeds max_import_size: truncated');
        }

        if ($act->obj['context']) {
            set_iconfig($item, 'activitypub', 'context', $act->obj['context'], 1);
        }

        set_iconfig($item, 'activitypub', 'recips', $act->raw_recips);

        if (intval($act->sigok)) {
            $item['item_verified'] = 1;
        }

        if ($is_child_node) {
            if (!$parent_item) {
                if (!get_config('system', 'activitypub', ACTIVITYPUB_ENABLED)) {
                    return;
                } else {
                    $fetch = false;
                    if (intval($channel['channel_system']) || (perm_is_allowed($channel['channel_id'], $observer_hash, 'send_stream') && (PConfig::Get($channel['channel_id'], 'system', 'hyperdrive', true) || $act->type === 'Announce'))) {
                        $fetch = ($fetch_parents && self::fetch_and_store_parents($channel, $observer_hash, $item));
                    }
                    if ($fetch) {
                        $parent_item = q(
                            "select * from item where mid = '%s' and uid = %d limit 1",
                            dbesc($item['parent_mid']),
                            intval($item['uid'])
                        );
                        if ($parent_item) {
                            $parent_item = array_shift($parent_item);
                        }
                    } else {
                        logger('no parent: ' . $item['mid']);
                        return;
                    }
                }
            }

            $item['comment_policy'] = $parent_item['comment_policy'];
            $item['item_nocomment'] = $parent_item['item_nocomment'];
            $item['comments_closed'] = $parent_item['comments_closed'];

            // If this is a nested conversation with more than one level of comments,
            // set thr_parent to the immediate parent and set parent_mid to the conversation root.

            if ($parent_item['parent_mid'] !== $item['parent_mid']) {
                $item['thr_parent'] = $item['parent_mid'];
            } else {
                $item['thr_parent'] = $parent_item['parent_mid'];
            }
            $item['parent_mid'] = $parent_item['parent_mid'];

            /*
             *
             * Check for conversation privacy mismatches
             * We can only do this if we have a channel, and we have fetched the parent
             *
             */

            // public conversation, but this comment went rogue and was published privately
            // hide it from everybody except the channel owner

            if (intval($parent_item['item_private']) === 0) {
                if (intval($item['item_private'])) {
                    $item['item_restrict'] = $item['item_restrict'] | 1;
                    $item['allow_cid'] = '<' . $channel['channel_hash'] . '>';
                    $item['allow_gid'] = $item['deny_cid'] = $item['deny_gid'] = '';
                }
            }
        }

        self::rewrite_mentions($item);

        if (! isset($item['replyto'])) {
            if (str_starts_with($item['owner_xchan'], 'http')) {
                $item['replyto'] = $item['owner_xchan'];
            }
            else {
                $r = q("select hubloc_id_url from hubloc where hubloc_hash = '%s' and hubloc_primary = 1 and hubloc_deleted = 0",
                    dbesc($item['owner_xchan'])
                );
                if ($r) {
                    $item['replyto'] = $r[0]['hubloc_id_url'];
                }
            }
        }

        $r = q(
            "select id, created, edited, approved from item where mid = '%s' and uid = %d limit 1",
            dbesc($item['mid']),
            intval($item['uid'])
        );
        if ($r) {
            if ($item['edited'] > $r[0]['edited'] || $item['approved'] !== $r[0]['approved']) {
                $item['id'] = $r[0]['id'];
                ObjCache::Set($item['mid'], $act->raw);
                $x = item_store_update($item, deliver: false);
            } else {
                return;
            }
        } else {
            ObjCache::Set($item['mid'], $act->raw);
            $x = item_store($item, deliver: false);
        }


// experimental code that needs more work. What this did was once we fetched a conversation to find the root node,
// start at that root node and fetch children, so you get all the branches and not just the branch related to the current node.
// Unfortunately there is no standard method for achieving this. Mastodon provides a 'replies' collection and Nomad projects
// can fetch the 'context'. For other platforms it's a wild guess. Additionally, when we tested this, it started an infinite
// recursion and has been disabled until the recursive behaviour is tracked down and fixed.

//      if ($fetch_parents && $parent && ! intval($parent_item['item_private'])) {
//          logger('topfetch', LOGGER_DEBUG);
//          // if the thread owner is a connnection, we will already receive any additional comments to their posts
//          // but if they are not we can try to fetch others in the background
//          $x = q("SELECT abook.*, xchan.* FROM abook left join xchan on abook_xchan = xchan_hash
//              WHERE abook_channel = %d and abook_xchan = '%s' LIMIT 1",
//              intval($channel['channel_id']),
//              dbesc($parent_item['owner_xchan'])
//          );
//          if (! $x) {
//              // determine if the top-level post provides a replies collection
//              if ($parent_item['obj']) {
//                  $parent_item['obj'] = json_decode($parent_item['obj'],true);
//              }
//              logger('topfetch: ' . print_r($parent_item,true), LOGGER_ALL);
//              $id = ((array_path_exists('obj/replies/id',$parent_item)) ? $parent_item['obj']['replies']['id'] : false);
//              if (! $id) {
//                  $id = ((array_path_exists('obj/replies',$parent_item) && is_string($parent_item['obj']['replies'])) ? $parent_item['obj']['replies'] : false);
//              }
//              if ($id) {
//                  Run::Summon( [ 'Convo', $id, $channel['channel_id'], $observer_hash ] );
//              }
//          }
//      }

        if (is_array($x) && $x['item_id']) {
            if ($is_child_node) {
                if ($item['owner_xchan'] === $channel['channel_hash']) {
                    // We are the owner of this conversation, so send all received comments back downstream
                    Run::Summon(['Notifier', 'comment-import', $x['item_id']]);
                }
            }
            elseif ($act->client && $channel['channel_hash'] === $observer_hash && !$force) {
                Run::Summon(['Notifier', 'wall-new', $x['item_id']]);
            }
            $r = q(
                "select * from item where id = %d limit 1",
                intval($x['item_id'])
            );
            if ($r) {
                send_status_notifications($r[0]);
            }
            sync_an_item($channel['channel_id'], $x['item_id']);
        }
    }

    public static function comment_allowed($channel, $item, $parent_item): bool|string
    {
        // First check if comment permissions have been granted to this author.
        $allowed = perm_is_allowed($channel['channel_id'], $item['author_xchan'], 'post_comments');

        // Allow likes from strangers if permitted to do so. These are difficult (but not impossible) to spam.
        if ($item['verb'] === 'Like' && PConfig::Get($channel['channel_id'], 'system', 'permit_all_likes') && $item['obj_type'] === 'Note') {
            $allowed = true;
        }

        // Allow mentions from strangers if permitted to do so.
        if ((!$allowed) && intval(PConfig::Get($channel['channel_id'], 'system', 'permit_all_mentions')
                && i_am_mentioned($channel, $item))) {
            // This deserves explanation. Most comments in my own conversation will mention me because
            // this is normal Mastodon behaviour - as that platform requires mentions to trigger comment
            // notifications. We have separate comment notifications for that and do not require mentions
            // in every comment. We may not want to allow anybody on the planet to comment on our posts.
            // There is a separate permission setting for that. That's like not having comment permissions
            // at all and basically creates a spam gateway. But if we were mentioned in somebody else's
            // conversation, that *might* be interesting. We have an "unless" setting which is evaluated
            // inside i_am_mentioned() that puts a limit on your tolerance to mention/tag spam. So if the
            // post mentions 87000 people, it will still be ignored.
            $allowed = ($parent_item['owner_xchan'] !== $channel['channel_hash']);
        }

        if ((!$allowed) && intval(PConfig::Get($channel['channel_id'],'system','permit_moderated_comments'))) {
            $allowed = 'moderated';
        }

        // If the item comment control forbids any comments, this over-rides everything.
        if (absolutely_no_comments($parent_item)) {
            $allowed = false;
        }

        return $allowed;
    }

    public static function find_best_identity($xchan)
    {

        $r = q(
            "select hubloc_hash, hubloc_network from hubloc where hubloc_id_url = '%s' and hubloc_deleted = 0 order by hubloc_id desc",
            dbesc($xchan)
        );
        if ($r) {
            $r = Libzot::zot_record_preferred($r);
            return $r['hubloc_hash'];
        }
        return $xchan;
    }


    public static function fetch_and_store_parents($channel, $observer_hash, $item)
    {
        logger('fetching parents');

        $conversation = [];
        $seen_mids = [];

        $current_item = $item;

        while ($current_item['parent_mid'] !== $current_item['mid']) {
            // recursion breaker
            if (in_array($current_item['parent_mid'], $seen_mids)) {
                break;
            }
            $json = self::fetch($current_item['parent_mid']);
            $seen_mids[] = $current_item['parent_mid'];
            if (!$json) {
                break;
            }
            // set client flag to convert objects to implied activities
            $activity = new ActivityStreams($json, null, true);
            if (
                $activity->type === 'Announce' && is_array($activity->obj)
                && array_key_exists('object', $activity->obj) && array_key_exists('actor', $activity->obj)
            ) {
                // This is a relayed/forwarded Activity (as opposed to a shared/boosted object)
                // Reparse the encapsulated Activity and use that instead
                logger('relayed activity', LOGGER_DEBUG);
                $activity = new ActivityStreams($activity->obj, null, true);
            }

            logger($activity->debug(), LOGGER_DATA);

            if (!$activity->is_valid()) {
                logger('not a valid activity');
                break;
            }
            if (is_array($activity->actor) && array_key_exists('id', $activity->actor)) {
                self::actor_store($activity->actor['id'], $activity->actor);
            }

            // ActivityPub sourced items are cacheable
            $item = self::decode_note($activity, true);

            if (!$item) {
                break;
            }

            logger('decoded_note: ' . print_r($item,true), LOGGER_DATA);

            $hookinfo = [
                'activity' => $activity,
                'item' => $item
            ];

            Hook::call('fetch_and_store', $hookinfo);

            $item = $hookinfo['item'];

            if ($item) {
                // don't leak any private conversations to the public stream
                // even if they contain publicly addressed comments/reactions

                if (intval($channel['channel_system']) && intval($item['item_private'])) {
                    logger('private conversation ignored');
                    $conversation = [];
                    break;
                }
                // We're fetching upstream starting with the initial post,
                // so push each fetched activity to the head of the conversation.
                array_unshift($conversation, ['activity' => $activity, 'item' => $item]);

                if ($item['parent_mid'] === $item['mid']) {
                    break;
                }
            }

            $current_item = $item;
        }

        if ($conversation && $conversation[0]['item']['mid'] === $conversation[0]['item']['parent_mid']) {
            foreach ($conversation as $post) {
                if ($post['activity']->is_valid()) {
                    self::store($channel, $observer_hash, $post['activity'], $post['item'], false);
                }
            }
            return true;
        }

        return false;
    }


    // This function is designed to work with Nomad attachments and item body

    public static function bb_attach($item)
    {
        if (!is_array($item['attach'])) {
            return $item;
        }

        foreach ($item['attach'] as $a) {
            if (array_key_exists('type', $a) && stripos($a['type'], 'image') !== false) {
                // don't add inline image if it's type svg, and we already have an inline svg
                if ($a['type'] === 'image/svg+xml' && strpos($item['body'], '[/svg]')) {
                    continue;
                }
                // Friendica attachment weirdness
                // Check both the attachment image and href since they can be different and the one in the href is a different link with different resolution.
                // Otheriwse you'll get duplicated images
                if (isset($a['image'])) {
                    if (self::media_not_in_body($a['image'], $item['body']) && self::media_not_in_body($a['href'], $item['body'])) {
                        if (isset($a['name']) && $a['name']) {
                            $alt = htmlspecialchars($a['name'], ENT_QUOTES);
                            // Escape brackets by converting to unicode full-width bracket since regular brackets will confuse multicode/bbcode parsing.
                            // The full width bracket isn't quite as alien looking as most other unicode bracket replacements.
                            $alt = str_replace(['[', ']'], ['&#xFF3B;', '&#xFF3D;'], $alt);
                            $item['body'] .= "\n\n" . '[img alt="' . $alt . '"]' . $a['href'] . '[/img]';
                        } else {
                            $item['body'] .= "\n\n" . '[img]' . $a['href'] . '[/img]';
                        }
                    }
                    continue;
                }
                elseif (self::media_not_in_body($a['href'], $item['body'])) {
                    if (isset($a['name']) && $a['name']) {
                        $alt = htmlspecialchars($a['name'], ENT_QUOTES);
                        // Escape brackets by converting to unicode full-width bracket since regular brackets will confuse multicode/bbcode parsing.
                        // The full width bracket isn't quite as alien looking as most other unicode bracket replacements.
                        $alt = str_replace(['[', ']'], ['&#xFF3B;', '&#xFF3D;'], $alt);
                        $item['body'] .= "\n\n" . '[img alt="' . $alt . '"]' . $a['href'] . '[/img]';
                    } else {
                        $item['body'] .= "\n\n" . '[img]' . $a['href'] . '[/img]';
                    }
                }
            }
            if (array_key_exists('type', $a) && stripos($a['type'], 'video') !== false) {
                if (self::media_not_in_body($a['href'], $item['body'])) {
                    $item['body'] .= "\n\n" . '[video]' . $a['href'] . '[/video]';
                }
            }
            if (array_key_exists('type', $a) && stripos($a['type'], 'audio') !== false) {
                if (self::media_not_in_body($a['href'], $item['body'])) {
                    $item['body'] .= "\n\n" . '[audio]' . $a['href'] . '[/audio]';
                }
            }
            if (array_key_exists('type', $a) && stripos($a['type'], 'activity') !== false) {
                if (self::share_not_in_body($item['body'])) {
                    $item = self::get_quote($a['href'], $item);
                }
            }

        }
        return $item;
    }


    // check for the existence of existing media link in body

    public static function media_not_in_body($s, $body)
    {

        $s_alt = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        if (
            (!str_contains($body, ']' . $s . '[/img]')) &&
            (!str_contains($body, ']' . $s . '[/zmg]')) &&
            (!str_contains($body, '[img=' . $s . ']')) &&
            (!str_contains($body, '[zmg=' . $s . ']')) &&
            (!str_contains($body, ']' . $s . '[/video]')) &&
            (!str_contains($body, ']' . $s . '[/zvideo]')) &&
            (!str_contains($body, ']' . $s . '[/audio]')) &&
            (!str_contains($body, ']' . $s . '[/zaudio]')) &&
            (!str_contains($body, ']' . $s_alt . '[/img]')) &&
            (!str_contains($body, ']' . $s_alt . '[/zmg]')) &&
            (!str_contains($body, '[img=' . $s_alt . ']')) &&
            (!str_contains($body, '[zmg=' . $s_alt . ']')) &&
            (!str_contains($body, ']' . $s_alt . '[/video]')) &&
            (!str_contains($body, ']' . $s_alt . '[/zvideo]')) &&
            (!str_contains($body, ']' . $s_alt . '[/audio]')) &&
            (!str_contains($body, ']' . $s_alt . '[/zaudio]'))
        ) {
            return true;
        }
        return false;
    }

    // check for the existence of existing share in body

    public static function share_not_in_body($body)
    {
        if (!str_contains($body, '[/share]')) {
            return true;
        }
        return false;
    }


    public static function bb_content($content, $field)
    {

        $ret = false;

        if (!is_array($content)) {
            btlogger('content not initialised');
            return false;
        }

        if (array_key_exists($field, $content) && is_array($content[$field])) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            foreach ($content[$field] as $k => $v) {
                $ret .= html2bbcode($v);
                // save this for auto-translate or dynamic filtering
                // $ret .= '[language=' . $k . ']' . html2bbcode($v) . '[/language]';
            }
        } elseif (isset($content[$field])) {
            if ($field === 'bbcode' && array_key_exists('bbcode', $content)) {
                $ret = $content[$field];
            } else {
                $ret = html2bbcode($content[$field]);
            }
        } else {
            $ret = EMPTY_STR;
        }
        if ($field === 'content' && isset($content['event']) && (!strpos($ret, '[event'))) {
            $ret .= format_event_bbcode($content['event']);
        }

        return $ret;
    }


    public static function get_content($act, $binary = false)
    {

        $content = [];
        $event = null;

        if ((!$act) || (!is_array($act))) {
            return $content;
        }


        if ($act['type'] === 'Event') {
            $adjust = false;
            $event = [];
            $event['event_hash'] = $act['id'];
            if (array_key_exists('startTime', $act) && str_ends_with($act['startTime'], 'Z')) {
                $adjust = true;
                $event['adjust'] = 1;
                $event['dtstart'] = datetime_convert('UTC', 'UTC', $event['startTime']);
            }
            if (array_key_exists('endTime', $act)) {
                $event['dtend'] = datetime_convert('UTC', 'UTC', $event['endTime'] . (($adjust) ? '' : 'Z'));
            } else {
                $event['nofinish'] = true;
            }

            if (array_key_exists('eventRepeat', $act)) {
                $event['event_repeat'] = $act['eventRepeat'];
            }
        }

        foreach (['name', 'summary', 'content'] as $a) {
            if (($x = self::get_textfield($act, $a, $binary)) !== false) {
                $content[$a] = $x;
            }
            if (isset($content['name'])) {
                $content['name'] = html2plain(purify_html($content['name']), 256);
            }
        }

        if ($event && !$binary) {
            $event['summary'] = html2plain(purify_html($content['summary']), 256);
            if (!$event['summary']) {
                if ($content['name']) {
                    $event['summary'] = html2plain(purify_html($content['name']), 256);
                }
            }
            if (!$event['summary']) {
                if ($content['content']) {
                    $event['summary'] = html2plain(purify_html($content['content']), 256);
                }
            }
            if ($event['summary']) {
                $event['summary'] = substr($event['summary'], 0, 256);
            }
            $event['description'] = html2bbcode($content['content']);
            if ($event['summary'] && $event['dtstart']) {
                $content['event'] = $event;
            }
        }

        if (array_path_exists('source/mediaType', $act) && array_path_exists('source/content', $act)) {
            if (in_array($act['source']['mediaType'], ['text/bbcode', 'text/x-multicode'])) {
                if (is_string($act['source']['content']) && str_contains($act['source']['content'], '<')) {
                    $content['bbcode'] = multicode_purify($act['source']['content']);
                } else {
                    $content['bbcode'] = purify_html($act['source']['content'], ['escape']);
                }
            }
        }

        return $content;
    }


    public static function get_textfield($act, $field, $binary = false)
    {

        $content = false;

        if (array_key_exists($field, $act) && $act[$field]) {
            $content = (($binary) ? $act[$field] : purify_html($act[$field]));
        } elseif (array_key_exists($field . 'Map', $act) && $act[$field . 'Map']) {
            foreach ($act[$field . 'Map'] as $k => $v) {
                $content[escape_tags($k)] = (($binary) ? $v : purify_html($v));
            }
        }
        return $content;
    }

    public static function send_accept_activity($channel, $observer_hash, $item, $inReplyTo = '')
    {

        $recip = q(
            "select * from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0 limit 1",
            dbesc($observer_hash)
        );
        if (!$recip) {
            return;
        }

        $arr = [
            'id' => z_root() . '/approvals/' . new_uuid(),
            'to' => [$recip[0]['hubloc_id_url']],
            'type' => 'Accept',
            'actor' => Channel::url($channel),
            'name' => 'comment accepted',
            'object' => $item['mid']
        ];
        if ($inReplyTo) {
            $arr['inReplyTo'] = $inReplyTo;
        }

        $msg = array_merge(self::ap_context(), $arr);

        logger('sending accept: ' . json_encode($msg, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOGGER_DEBUG);
        $queue_id = ActivityPub::queue_message(json_encode($msg, JSON_UNESCAPED_SLASHES), $channel, $recip[0]);
        do_delivery([$queue_id]);
    }

    public static function send_reject_activity($channel, $observer_hash, $item, $inReplyTo)
    {

        $recip = q(
            "select * from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0 limit 1",
            dbesc($observer_hash)
        );
        if (!$recip) {
            return;
        }

        $arr = [
            'id' => z_root() . '/approvals/' . new_uuid(),
            'to' => [$recip[0]['hubloc_id_url']],
            'type' => 'Reject',
            'actor' => Channel::url($channel),
            'name' => 'Permission denied',
            'object' => $item['mid']
        ];

        if ($inReplyTo) {
            $arr['inReplyTo'] = $inReplyTo;
        }

        $msg = array_merge(self::ap_context(), $arr);

        logger('sending reject: ' . json_encode($msg, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOGGER_DEBUG);
        $queue_id = ActivityPub::queue_message(json_encode($msg, JSON_UNESCAPED_SLASHES), $channel, $recip[0]);
        do_delivery([$queue_id]);
    }

    // Find either an Authorization: Bearer token or 'token' request variable
    // in the current web request and return it

    public static function token_from_request()
    {

        foreach (['REDIRECT_REMOTE_USER', 'HTTP_AUTHORIZATION'] as $s) {
            $auth = ((array_key_exists($s, $_SERVER) && str_starts_with($_SERVER[$s], 'Bearer '))
                ? str_replace('Bearer ', EMPTY_STR, $_SERVER[$s])
                : EMPTY_STR
            );
            if ($auth) {
                break;
            }
        }

        if (!$auth) {
            if (array_key_exists('token', $_REQUEST) && $_REQUEST['token']) {
                $auth = $_REQUEST['token'];
            }
        }

        return $auth;
    }

    public static function get_xchan_type($type)
    {
        return match ($type) {
            'Person' => XCHAN_TYPE_PERSON,
            'Group' => XCHAN_TYPE_GROUP,
            'Service' => XCHAN_TYPE_SERVICE,
            'Organization' => XCHAN_TYPE_ORGANIZATION,
            'Application' => XCHAN_TYPE_APPLICATION,
            default => XCHAN_TYPE_UNKNOWN,
        };
    }

    public static function xchan_type_to_type($type)
    {
        return match ($type) {
            XCHAN_TYPE_GROUP => 'Group',
            XCHAN_TYPE_SERVICE => 'Service',
            XCHAN_TYPE_ORGANIZATION => 'Organization',
            XCHAN_TYPE_APPLICATION => 'Application',
            default => 'Person',
        };
    }

    public static function get_cached_actor($id)
    {
        return (XConfig::Get($id, 'system', 'actor_record'));
    }


    public static function get_actor_hublocs($url, $options = 'all,not_deleted')
    {
        $sql_options = EMPTY_STR;

        $options_arr = explode(',', $options);
        if (count($options_arr) > 1) {
            for ($x = 1; $x < count($options_arr); $x++) {
                switch (trim($options_arr[$x])) {
                    case 'not_deleted':
                        $sql_options .= ' and hubloc_deleted = 0 ';
                        break;
                    default:
                        break;
                }
            }
        }

        return match (trim($options_arr[0])) {
            'activitypub' => q(
                "select * from hubloc left join xchan on hubloc_hash = xchan_hash where hubloc_hash = '%s' $sql_options ",
                dbesc($url)
            ),
            'zot6', 'nomad' => q(
                "select * from hubloc left join xchan on hubloc_hash = xchan_hash where hubloc_id_url = '%s' $sql_options ",
                dbesc($url)
            ),
            default => q(
                "select * from hubloc left join xchan on hubloc_hash = xchan_hash where ( hubloc_id_url = '%s' OR hubloc_hash = '%s' ) $sql_options ",
                dbesc($url),
                dbesc($url)
            ),
        };
    }

    public static function get_actor_collections($url)
    {
        $ret = [];
        $actor_record = XConfig::Get($url, 'system', 'actor_record');
        if (!$actor_record) {
            return $ret;
        }

        foreach (['inbox', 'outbox', 'followers', 'following'] as $collection) {
            if (isset($actor_record[$collection]) && $actor_record[$collection]) {
                $ret[$collection] = $actor_record[$collection];
            }
        }
        if (array_path_exists('endpoints/sharedInbox', $actor_record) && $actor_record['endpoints']['sharedInbox']) {
            $ret['sharedInbox'] = $actor_record['endpoints']['sharedInbox'];
        }

        return $ret;
    }

    public static function ap_context(): array
    {
        return ['@context' => [
            ACTIVITYSTREAMS_JSONLD_REV,
           'https://w3id.org/security/v1',
        //    'https://www.w3.org/ns/did/v1',
        //    'https://w3id.org/security/data-integrity/v1',
            self::ap_schema()
        ]];
    }

    public static function ap_schema()
    {
        return [
            'nomad' => z_root() . '/apschema#',
            'toot' => 'http://joinmastodon.org/ns#',
            'schema' => 'http://schema.org#',
            'litepub' => 'http://litepub.social/ns#',
            'sm' => 'http://smithereen.software/ns#',
         //   'fep' => 'https://codeberg.org/fediverse/fep#',
            'manuallyApprovesFollowers' => 'as:manuallyApprovesFollowers',
            'oauthRegistrationEndpoint' => 'litepub:oauthRegistrationEndpoint',
            'sensitive' => 'as:sensitive',
            'movedTo' => 'as:movedTo',
            'alsoKnownAs' => 'as:alsoKnownAs',
            'EmojiReact' => 'as:EmojiReact',
            'PropertyValue' => 'schema:PropertyValue',
            'value' => 'schema:value',
            'discoverable' => 'toot:discoverable',
            'wall' => 'sm:wall',
            'capabilities' => 'litepub:capabilities',
            'acceptsJoins' => 'litepub:acceptsJoins',
            'Hashtag' => 'as:Hashtag',
            'canReply' => 'toot:canReply',
            'approval' => 'toot:approval',
         //   'Identity' => 'fep:Identity',
            'isContainedConversation' => 'nomad:isContainedConversation',
            'conversation' => 'nomad:conversation',
            'commentPolicy' => 'nomad:commentPolicy',
            'eventRepeat' => 'nomad:eventRepeat',
            'emojiReaction' => 'nomad:emojiReaction',
            'expires' => 'nomad:expires',
            'directMessage' => 'nomad:directMessage',
            'Category' => 'nomad:Category',
            'replyTo' => 'nomad:replyTo',
            'copiedTo' => 'nomad:copiedTo',
            'canSearch' => 'nomad:canSearch',
            'searchContent' => 'nomad:searchContent',
            'searchTags' => 'nomad:searchTags',
        ];
    }

    public static function get_quote($url, $item) {

        $a = self::fetch($url);
        if ($a) {
            $act = new ActivityStreams($a);

            if ($act->is_valid()) {
                $z = Activity::decode_note($act);
                $r = hubloc_id_query((is_array($act->actor)) ? $act->actor['id'] : $act->actor);

                if ($r) {
                    $r = Libzot::zot_record_preferred($r);
                    if ($z) {
                        $z['author_xchan'] = $r['hubloc_hash'];
                    }
                }

                if ($z) {
                    // do not allow somebody to embed a post that was blocked by the site admin
                    // We *will* let them over-rule any blocks they created themselves
                    if (check_siteallowed($r['hubloc_id_url']) && check_channelallowed($z['author_xchan'])) {
                        $s = new Zlib\Share($z);
                        $item['body'] .= "\n\n" . $s->bbcode();
                        $item['attach'] = $s->get_attach();
                    }
                }
            }

        }
        return $item;
    }
}
