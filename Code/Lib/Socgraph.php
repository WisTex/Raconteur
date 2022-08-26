<?php
namespace Code\Lib;

use App;
use Code\Lib\Libzot;
use Code\Lib\Libzotdir;
use Code\Lib\Zotfinger;
use Code\Lib\ASCollection;
use Code\Render\Theme;
use Code\Lib\Url;

class Socgraph {

    /**
     * poco_load
     *
     * xchan is your connection
     * We will load their friend list, and store in xlink_xchan your connection hash and xlink_link the hash for each connection
     * If xchan isn't provided we will load the list of people from url who have indicated they are willing to be friends with
     * new folks and add them to xlink with no xlink_xchan.
     *
     * Old behaviour: (documentation only):
     * Given a contact-id (minimum), load the PortableContacts friend list for that contact,
     * and add the entries to the gcontact (Global Contact) table, or update existing entries
     * if anything (name or photo) has changed.
     * We use normalised urls for comparison which ignore http vs https and www.domain vs domain
     *
     * Once the global contact is stored add (if necessary) the contact linkage which associates
     * the given uid, cid to the global contact entry. There can be many uid/cid combinations
     * pointing to the same global contact id.
     *
     * @param string $xchan
     * @param string $url
     */

    public static function poco_load($xchan = '', $url = null)
    {

        if (self::ap_poco_load($xchan)) {
            return;
        }

        if ($xchan && ! $url) {
            $r = q(
                "select xchan_connurl from xchan where xchan_hash = '%s' limit 1",
                dbesc($xchan)
            );
            if ($r) {
                $url = $r[0]['xchan_connurl'];
            }
        }

        if (! $url) {
            logger('poco_load: no url');
            return;
        }

        $max = intval(get_config('system', 'max_imported_follow', MAX_IMPORTED_FOLLOW));
        if (! intval($max)) {
            return;
        }


        $url = $url . '?f=&fields=displayName,hash,urls,photos' ;

        logger('poco_load: ' . $url, LOGGER_DEBUG);

        $s = Url::get($url);

        if (! $s['success']) {
            if ($s['return_code'] == 401) {
                logger('poco_load: protected');
            } elseif ($s['return_code'] == 404) {
                logger('poco_load: nothing found');
            } else {
                logger('poco_load: returns ' . print_r($s, true), LOGGER_DATA);
            }
            return;
        }

        $j = json_decode($s['body'], true);

        if (! $j) {
            logger('poco_load: unable to json_decode returned data.');
            return;
        }

        logger('poco_load: ' . print_r($j, true), LOGGER_DATA);

        if ($xchan) {
            if (array_key_exists('chatrooms', $j) && is_array($j['chatrooms'])) {
                foreach ($j['chatrooms'] as $room) {
                    if ((! $room['url']) || (! $room['desc'])) {
                        continue;
                    }

                    $r = q(
                        "select * from xchat where xchat_url = '%s' and xchat_xchan = '%s' limit 1",
                        dbesc($room['url']),
                        dbesc($xchan)
                    );
                    if ($r) {
                        q(
                            "update xchat set xchat_edited = '%s' where xchat_id = %d",
                            dbesc(datetime_convert()),
                            intval($r[0]['xchat_id'])
                        );
                    } else {
                        $x = q(
                            "insert into xchat ( xchat_url, xchat_desc, xchat_xchan, xchat_edited )
                            values ( '%s', '%s', '%s', '%s' ) ",
                            dbesc(escape_tags($room['url'])),
                            dbesc(escape_tags($room['desc'])),
                            dbesc($xchan),
                            dbesc(datetime_convert())
                        );
                    }
                }
            }
            q(
                "delete from xchat where xchat_edited < %s - INTERVAL %s and xchat_xchan = '%s' ",
                db_utcnow(),
                db_quoteinterval('7 DAY'),
                dbesc($xchan)
            );
        }

        if (! ((x($j, 'entry')) && (is_array($j['entry'])))) {
            logger('poco_load: no entries');
            return;
        }


        $total = 0;
        foreach ($j['entry'] as $entry) {
            $profile_url = '';
            $profile_photo = '';
            $address = '';
            $name = '';
            $hash = '';
            $rating = 0;
            $network = '';

            $name   = $entry['displayName'];
            $hash   = $entry['hash'];

            if (x($entry, 'urls') && is_array($entry['urls'])) {
                foreach ($entry['urls'] as $url) {
                    if ($url['type'] == 'profile') {
                        $profile_url = $url['value'];
                        continue;
                    }
                    if (in_array($url['type'], ['nomad', 'zot6', 'activitypub'])) {
                        $network = $url['type'];
                        $address = str_replace('acct:', '', $url['value']);
                        continue;
                    }
                }
            }
            if (x($entry, 'photos') && is_array($entry['photos'])) {
                foreach ($entry['photos'] as $photo) {
                    if ($photo['type'] == 'profile') {
                        $profile_photo = $photo['value'];
                        continue;
                    }
                }
            }

            if (! in_array($network, ['nomad', 'zot6', 'activitypub'])) {
                continue;
            }

            if ((! $name) || (! $profile_url) || (! $profile_photo) || (! $hash) || (! $address)) {
                logger('poco_load: missing data');
                logger('poco_load: ' . print_r($entry, true), LOGGER_DATA);
                continue;
            }

            $x = q(
                "select xchan_hash from xchan where ( xchan_hash = '%s' or xchan_url = '%s' ) order by xchan_network desc limit 1",
                dbesc($hash),
                dbesc($hash)
            );

            // We've never seen this person before. Import them.

            if (($x !== false) && (! count($x))) {
                if ($address) {
                    if (in_array($network, ['nomad', 'zot6', 'activitypub'])) {
                        $wf = discover_by_webbie($profile_url);
                        if ($wf) {
                            $x = q(
                                "select xchan_hash from xchan where ( xchan_hash = '%s' or xchan_url = '%s') order by xchan_network desc limit 1",
                                dbesc($wf),
                                dbesc($wf)
                            );
                            if ($x) {
                                $hash = $x[0]['xchan_hash'];
                            }
                        }
                        if (! $x) {
                            continue;
                        }
                    }
                } else {
                    continue;
                }
            }

            $total++;

            $r = q(
                "select * from xlink where xlink_xchan = '%s' and xlink_link = '%s' and xlink_static = 0 limit 1",
                dbesc($xchan),
                dbesc($hash)
            );

            if (! $r) {
                q(
                    "insert into xlink ( xlink_xchan, xlink_link, xlink_rating, xlink_rating_text, xlink_sig, xlink_updated, xlink_static ) values ( '%s', '%s', %d, '%s', '%s', '%s', 0 ) ",
                    dbesc($xchan),
                    dbesc($hash),
                    intval(0),
                    dbesc(''),
                    dbesc(''),
                    dbesc(datetime_convert())
                );
            } else {
                q(
                    "update xlink set xlink_updated = '%s' where xlink_id = %d",
                    dbesc(datetime_convert()),
                    intval($r[0]['xlink_id'])
                );
            }

            $total++;
            if ($total > $max) {
                break;
            }
        }
        logger("poco_load: loaded $total entries", LOGGER_DEBUG);

        q(
            "delete from xlink where xlink_xchan = '%s' and xlink_updated < %s - INTERVAL %s and xlink_static = 0",
            dbesc($xchan),
            db_utcnow(),
            db_quoteinterval('7 DAY')
        );
    }




    public static function ap_poco_load($xchan)
    {

        $max = intval(get_config('system', 'max_imported_follow', MAX_IMPORTED_FOLLOW));
        if (! intval($max)) {
            return false;
        }


        if ($xchan) {
            $cl = get_xconfig($xchan, 'activitypub', 'collections');
            if (is_array($cl) && $cl) {
                $url = ((array_key_exists('following', $cl)) ? $cl['following'] : '');
            } else {
                return false;
            }
        }

        if (! $url) {
            logger('ap_poco_load: no url');
            return false;
        }

        $obj = new ASCollection($url, '', 0, $max);

        $friends = $obj->get();

        if (! $friends) {
            return false;
        }

        foreach ($friends as $entry) {
            $hash = EMPTY_STR;

            $x = q(
                "select xchan_hash from xchan where (xchan_hash = '%s' or xchan_url = '%s') order by xchan_network desc limit 1",
                dbesc($entry),
                dbesc($entry)
            );


            if ($x) {
                $hash = $x[0]['xchan_hash'];
            } else {
                // We've never seen this person before. Import them.

                $wf = discover_by_webbie($entry);
                if ($wf) {
                    $x = q(
                        "select xchan_hash from xchan where (xchan_hash = '%s' or xchan_url = '%s') order by xchan_network desc limit 1",
                        dbesc($wf),
                        dbesc($wf)
                    );
                    if ($x) {
                        $hash = $x[0]['xchan_hash'];
                    }
                }
            }

            if (! $hash) {
                continue;
            }

            $total++;

            $r = q(
                "select * from xlink where xlink_xchan = '%s' and xlink_link = '%s' and xlink_static = 0 limit 1",
                dbesc($xchan),
                dbesc($hash)
            );

            if (! $r) {
                q(
                    "insert into xlink ( xlink_xchan, xlink_link, xlink_rating, xlink_rating_text, xlink_sig, xlink_updated, xlink_static ) values ( '%s', '%s', %d, '%s', '%s', '%s', 0 ) ",
                    dbesc($xchan),
                    dbesc($hash),
                    intval(0),
                    dbesc(''),
                    dbesc(''),
                    dbesc(datetime_convert())
                );
            } else {
                q(
                    "update xlink set xlink_updated = '%s' where xlink_id = %d",
                    dbesc(datetime_convert()),
                    intval($r[0]['xlink_id'])
                );
            }
        }

        logger("ap_poco_load: loaded $total entries", LOGGER_DEBUG);

        q(
            "delete from xlink where xlink_xchan = '%s' and xlink_updated < %s - INTERVAL %s and xlink_static = 0",
            dbesc($xchan),
            db_utcnow(),
            db_quoteinterval('7 DAY')
        );

        return true;
    }


    public static function count_common_friends($uid, $xchan)
    {

        $r = q(
            "SELECT count(xlink_id) as total from xlink where xlink_xchan = '%s' and xlink_static = 0 and xlink_link in
            (select abook_xchan from abook where abook_xchan != '%s' and abook_channel = %d and abook_self = 0 )",
            dbesc($xchan),
            dbesc($xchan),
            intval($uid)
        );

        if ($r) {
            return $r[0]['total'];
        }
        return 0;
    }


    public static function common_friends($uid, $xchan, $start = 0, $limit = 100000000, $shuffle = false)
    {

        $rand = db_getfunc('rand');
        if ($shuffle) {
            $sql_extra = " order by $rand ";
        } else {
            $sql_extra = " order by xchan_name asc ";
        }

        $r = q(
            "SELECT * from xchan left join xlink on xlink_link = xchan_hash where xlink_xchan = '%s' and xlink_static = 0 and xlink_link in
            (select abook_xchan from abook where abook_xchan != '%s' and abook_channel = %d and abook_self = 0 ) $sql_extra limit %d offset %d",
            dbesc($xchan),
            dbesc($xchan),
            intval($uid),
            intval($limit),
            intval($start)
        );

        return $r;
    }


    public static function suggestion_query($uid, $myxchan, $start = 0, $limit = 120)
    {

        if ((! $uid) || (! $myxchan)) {
            return [];
        }

        $r1 = q(
            "SELECT count(xlink_xchan) as total, xchan_hash from xchan
            left join xlink on xlink_link = xchan_hash
            where xlink_xchan in ( select abook_xchan from abook where abook_channel = %d )
            and not xlink_link in ( select abook_xchan from abook where abook_channel = %d )
            and not xlink_link in ( select xchan from xign where uid = %d )
            and xlink_xchan != ''
            and xchan_hidden = 0
            and xchan_deleted = 0
            and xlink_static = 0
            group by xchan_hash order by total desc limit %d offset %d ",
            intval($uid),
            intval($uid),
            intval($uid),
            intval($limit),
            intval($start)
        );

        if (! $r1) {
            $r1 = [];
        }

        $r2 = q(
            "SELECT count(xtag_hash) as total, xchan_hash from xchan
            left join xtag on xtag_hash = xchan_hash
            where xtag_hash != '%s' 
            and not xtag_hash in ( select abook_xchan from abook where abook_channel = %d )
            and xtag_term in ( select xtag_term from xtag where xtag_hash = '%s' )
            and not xtag_hash in ( select xchan from xign where uid = %d )
            and xchan_hidden = 0
            and xchan_deleted = 0
            group by xchan_hash order by total desc limit %d offset %d ",
            dbesc($myxchan),
            intval($uid),
            dbesc($myxchan),
            intval($uid),
            intval($limit),
            intval($start)
        );

        if (! $r2) {
            $r2 = [];
        }

        foreach ($r2 as $r) {
            $found = false;
            for ($x = 0; $x < count($r1); $x++) {
                if ($r['xchan_hash'] === $r1[$x]['xchan_hash']) {
                    $r1[$x]['total'] = intval($r1[$x]['total']) + intval($r['total']);
                    $found = true;
                    continue;
                }
            }
            if (! $found) {
                $r1[] = $r;
            }
        }

        $xchan_arr = [];

        foreach ($r1 as $xchans) {
            $xchan_arr[] = "'" . dbesc($xchans['xchan_hash']) . "'";
        }
        if ($xchan_arr) {
            $xchan_complete = q("select * from xchan where xchan_hash in (" . protect_sprintf(implode(',',$xchan_arr)) . ")");
        }
        $results = [];
        if ($xchan_complete) {
            for ($x = 0; $x < count($xchan_complete); $x ++) {
                for ($y = 0; $y < count($r1); $y ++) {
                    if ($r1[$y]['xchan_hash'] === $xchan_complete[$x]['xchan_hash']) {
                        $tmp = $xchan_complete[$x];
                        $tmp['total'] = $r1[$y]['total'];
                        $results[] = $tmp;
                        break;
                    }
                }
            }
        }
        usort($results, 'self::socgraph_total_sort');
        return ($results);
    }

    public static function socgraph_total_sort($a, $b)
    {
        if ($a['total'] === $b['total']) {
            return 0;
        }

        return((intval($a['total']) <  intval($b['total'])) ? 1 : -1 );
    }


    public static function poco()
    {

        $system_mode = false;

        $observer = App::get_observer();

        if (argc() > 1) {
            $user = notags(trim(argv(1)));
        }
        if (! (isset($user) && $user)) {
            $c = q("select * from pconfig where cat = 'system' and k = 'suggestme' and v = '1'");
            if (! $c) {
                logger('mod_poco: system mode. No candidates.', LOGGER_DEBUG);
                http_status_exit(404);
            }
            $system_mode = true;
        }

        $format = ((isset($_REQUEST['format']) && $_REQUEST['format']) ? $_REQUEST['format'] : 'json');

        $justme = false;

        if (argc() > 2 && argv(2) === '@me') {
            $justme = true;
        }
        if (argc() > 3) {
            if (argv(3) === '@all') {
                $justme = false;
            } elseif (argv(3) === '@self') {
                $justme = true;
            }
        }
        if (argc() > 4 && intval(argv(4)) && $justme == false) {
            $cid = intval(argv(4));
        }

        if (! $system_mode) {
            $r = q(
                "SELECT channel_id from channel where channel_address = '%s' limit 1",
                dbesc($user)
            );
            if (! $r) {
                logger('mod_poco: user mode. Account not found. ' . $user);
                http_status_exit(404);
            }

            $channel_id = $r[0]['channel_id'];
            $ohash = (($observer) ? $observer['xchan_hash'] : '');

            if (! perm_is_allowed($channel_id, $ohash, 'view_contacts')) {
                logger('mod_poco: user mode. Permission denied for ' . $ohash . ' user: ' . $user);
                http_status_exit(401);
            }
        }

        if (isset($justme) && $justme) {
            $sql_extra = " and abook_self = 1 ";
        } else {
            $sql_extra = " and abook_self = 0 ";
        }

        if (isset($cid) && $cid) {
            $sql_extra = sprintf(" and abook_id = %d and abook_archived = 0 and abook_hidden = 0 and abook_pending = 0 ", intval($cid));
        }

        if (isset($system_mode) && $system_mode) {
            $r = q("SELECT count(*) as total from abook where abook_self = 1 
                and abook_channel in (select uid from pconfig where cat = 'system' and k = 'suggestme' and v = '1') ");
        } else {
            $r = q(
                "SELECT count(*) as total from abook where abook_channel = %d 
                $sql_extra ",
                intval($channel_id)
            );
            $rooms = q(
                "select * from menu_item where ( mitem_flags & " . intval(MENU_ITEM_CHATROOM) . " ) > 0 and allow_cid = '' and allow_gid = '' and deny_cid = '' and deny_gid = '' and mitem_channel_id = %d",
                intval($channel_id)
            );
        }
        if ($r) {
            $totalResults = intval($r[0]['total']);
        } else {
            $totalResults = 0;
        }

        $startIndex = ((isset($_GET['startIndex'])) ? intval($_GET['startIndex']) : 0);
        if ($startIndex < 0) {
            $startIndex = 0;
        }

        $itemsPerPage = ((isset($_GET['count']) && intval($_GET['count'])) ? intval($_GET['count']) : $totalResults);

        if ($system_mode) {
            $r = q(
                "SELECT abook.*, xchan.* from abook left join xchan on abook_xchan = xchan_hash where abook_self = 1 
                and abook_channel in (select uid from pconfig where cat = 'system' and k = 'suggestme' and v = '1') 
                limit %d offset %d ",
                intval($itemsPerPage),
                intval($startIndex)
            );
        } else {
            $r = q(
                "SELECT abook.*, xchan.* from abook left join xchan on abook_xchan = xchan_hash where abook_channel = %d 
                $sql_extra LIMIT %d OFFSET %d",
                intval($channel_id),
                intval($itemsPerPage),
                intval($startIndex)
            );
        }

        $ret = [];
        if (x($_GET, 'sorted')) {
            $ret['sorted'] = 'false';
        }
        if (x($_GET, 'filtered')) {
            $ret['filtered'] = 'false';
        }
        if (x($_GET, 'updatedSince')) {
            $ret['updateSince'] = 'false';
        }

        $ret['startIndex']   = (string) $startIndex;
        $ret['itemsPerPage'] = (string) $itemsPerPage;
        $ret['totalResults'] = (string) $totalResults;

        if ($rooms) {
            $ret['chatrooms'] = [];
            foreach ($rooms as $room) {
                $ret['chatrooms'][] = array('url' => $room['mitem_link'], 'desc' => $room['mitem_desc']);
            }
        }

        $ret['entry'] = [];

        $fields_ret = array(
            'id' => false,
            'guid' => false,
            'guid_sig' => false,
            'hash' => false,
            'displayName' => false,
            'urls' => false,
            'preferredUsername' => false,
            'photos' => false,
            'rating' => false
        );

        if ((! x($_GET, 'fields')) || ($_GET['fields'] === '@all')) {
            foreach ($fields_ret as $k => $v) {
                $fields_ret[$k] = true;
            }
        } else {
            $fields_req = explode(',', $_GET['fields']);
            foreach ($fields_req as $f) {
                $fields_ret[trim($f)] = true;
            }
        }

        if (is_array($r)) {
            if (count($r)) {
                foreach ($r as $rr) {
                    $entry = [];
                    if ($fields_ret['id']) {
                        $entry['id'] = $rr['abook_id'];
                    }
                    if ($fields_ret['guid']) {
                        $entry['guid'] = $rr['xchan_guid'];
                    }
                    if ($fields_ret['guid_sig']) {
                        $entry['guid_sig'] = $rr['xchan_guid_sig'];
                    }
                    if ($fields_ret['hash']) {
                        $entry['hash'] = $rr['xchan_hash'];
                    }

                    if ($fields_ret['displayName']) {
                        $entry['displayName'] = $rr['xchan_name'];
                    }
                    if ($fields_ret['urls']) {
                        $entry['urls'] = array(array('value' => $rr['xchan_url'], 'type' => 'profile'));
                        $network = $rr['xchan_network'];
                        if ($rr['xchan_addr']) {
                            $entry['urls'][] = array('value' => 'acct:' . $rr['xchan_addr'], 'type' => $network);
                        }
                    }
                    if ($fields_ret['preferredUsername']) {
                        $entry['preferredUsername'] = substr($rr['xchan_addr'], 0, strpos($rr['xchan_addr'], '@'));
                    }
                    if ($fields_ret['photos']) {
                        $entry['photos'] = array(array('value' => $rr['xchan_photo_l'], 'mimetype' => $rr['xchan_photo_mimetype'], 'type' => 'profile'));
                    }
                    $ret['entry'][] = $entry;
                }
            } else {
                $ret['entry'][] = [];
            }
        } else {
            http_status_exit(500);
        }

        if ($format === 'xml') {
            header('Content-type: text/xml');
            echo replace_macros(Theme::get_template('poco_xml.tpl'), array_xmlify(array('$response' => $ret)));
            http_status_exit(500);
        }
        if ($format === 'json') {
            header('Content-type: application/json');
            echo json_encode($ret);
            killme();
        } else {
            http_status_exit(500);
        }
    }

}
