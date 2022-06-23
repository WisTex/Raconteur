<?php

namespace Code\Module;

use Code\Lib\Libzotdir;
use Code\Lib\AccessList;
use Code\Web\Controller;
use Code\Lib\Url;

/**
 * @brief ACL selector json backend.
 *
 * This module provides JSON lists of connections and local/remote channels
 * (xchans) to populate various tools such as the ACL (AccessControlList) popup
 * and various auto-complete functions (such as email recipients, search, and
 * mention targets.
 *
 * There are two primary output structural formats. One for the ACL widget and
 * the other for auto-completion.
 *
 * Many of the behaviour variations are triggered on the use of single character
 * keys however this functionality has grown in an ad-hoc manner and has gotten
 * quite messy over time.
 */
class Acloader extends Controller
{

    public function init()
    {

        // logger('mod_acl: ' . print_r($_REQUEST,true),LOGGER_DATA);

        $start = (x($_REQUEST, 'start') ? $_REQUEST['start'] : 0);
        $count = (x($_REQUEST, 'count') ? $_REQUEST['count'] : 500);
        $search = (x($_REQUEST, 'search') ? $_REQUEST['search'] : '');
        $type = (x($_REQUEST, 'type') ? $_REQUEST['type'] : '');
        $noforums = (x($_REQUEST, 'n') ? $_REQUEST['n'] : false);


        // $type =
        //  ''   =>  standard ACL request
        //  'g'  =>  Groups only ACL request
        //  'f'  =>  forums only ACL request
        //  'c'  =>  Connections only ACL request or editor (textarea) mention request
        // $_REQUEST['search'] contains ACL search text.


        // $type =
        //  'm'  =>  autocomplete private mail recipient (checks post_mail permission)
        //  'a'  =>  autocomplete connections (mod_connections, mod_poke, mod_sources, mod_photos)
        //  'x'  =>  nav search bar autocomplete (match any xchan)
        //  'z'  =>  autocomplete any xchan, but also include abook_alias, requires non-zero local_channel()
        //           and also contains xid without urlencode, used specifically by activity_filter widget
        // $_REQUEST['query'] contains autocomplete search text.


        // The different autocomplete libraries use different names for the search text
        // parameter. Internally we'll use $search to represent the search text no matter
        // what request variable it was attached to.

        if (array_key_exists('query', $_REQUEST)) {
            $search = $_REQUEST['query'];
        }

        if ((!local_channel()) && (!in_array($type, ['x', 'c', 'f']))) {
            killme();
        }

        $permitted = [];

        if (in_array($type, ['m', 'a', 'f'])) {
            // These queries require permission checking. We'll create a simple array of xchan_hash for those with
            // the requisite permissions which we can check against.

            $x = q(
                "select xchan from abconfig where chan = %d and cat = 'system' and k = 'their_perms' and v like '%s'",
                intval(local_channel()),
                dbesc(($type === 'm') ? '%post_mail%' : '%tag_deliver%')
            );

            $permitted = ids_to_array($x, 'xchan');
        }


        if ($search) {
            $sql_extra = " AND pgrp.gname LIKE " . protect_sprintf("'%" . dbesc($search) . "%'") . " ";
            // sql_extra2 is typically used when we don't have a local_channel - so we are not search abook_alias
            $sql_extra2 = " AND ( xchan_name LIKE " . protect_sprintf("'%" . dbesc($search) . "%'") . " OR xchan_addr LIKE " . protect_sprintf("'%" . dbesc(punify($search)) . ((strpos($search, '@') === false) ? "%@%'" : "%'")) . ") ";


            // This horrible mess is needed because position also returns 0 if nothing is found.
            // Would be MUCH easier if it instead returned a very large value
            // Otherwise we could just
            // order by LEAST(POSITION($search IN xchan_name),POSITION($search IN xchan_addr)).

            $order_extra2 = "CASE WHEN xchan_name LIKE "
                . protect_sprintf("'%" . dbesc($search) . "%'")
                . " then POSITION('" . protect_sprintf(dbesc($search))
                . "' IN xchan_name) else position('" . protect_sprintf(dbesc(punify($search))) . "' IN xchan_addr) end, ";

            $sql_extra3 = "AND ( xchan_addr like " . protect_sprintf("'%" . dbesc(punify($search)) . "%'") . " OR xchan_name like " . protect_sprintf("'%" . dbesc($search) . "%'") . " OR abook_alias like " . protect_sprintf("'%" . dbesc($search) . "%'") . " ) ";

            $sql_extra4 = "AND ( xchan_name LIKE " . protect_sprintf("'%" . dbesc($search) . "%'") . " OR xchan_addr LIKE " . protect_sprintf("'%" . dbesc(punify($search)) . ((strpos($search, '@') === false) ? "%@%'" : "%'")) . " OR abook_alias LIKE " . protect_sprintf("'%" . dbesc($search) . "%'") . ") ";
        } else {
            $sql_extra = $sql_extra2 = $sql_extra3 = $sql_extra4 = "";
        }


        $groups = [];
        $contacts = [];

        if ($type == '' || $type == 'g') {
            // Normal privacy groups

            $r = q(
                "SELECT pgrp.id, pgrp.hash, pgrp.gname
					FROM pgrp, pgrp_member 
					WHERE pgrp.deleted = 0 AND pgrp.uid = %d 
					AND pgrp_member.gid = pgrp.id
					$sql_extra
					GROUP BY pgrp.id
					ORDER BY pgrp.gname 
					LIMIT %d OFFSET %d",
                intval(local_channel()),
                intval($count),
                intval($start)
            );

            if ($r) {
                foreach ($r as $g) {
                    //      logger('acl: group: ' . $g['gname'] . ' members: ' . AccessList::members_xchan(local_channel(),$g['id']));
                    $groups[] = [
                        "type" => "g",
                        "photo" => "images/twopeople.png",
                        "name" => $g['gname'],
                        "id" => $g['id'],
                        "xid" => $g['hash'],
                        "uids" => AccessList::members_xchan(local_channel(), $g['id']),
                        "link" => ''
                    ];
                }
            }
        }

        if ($type == '' || $type == 'c' || $type === 'f') {
            // Getting info from the abook is better for local users because it contains info about permissions
            if (local_channel()) {
                // add connections

                $r = q(
                    "SELECT abook_id as id, xchan_hash as hash, xchan_name as name, xchan_photo_s as micro, xchan_url as url, xchan_addr as nick, xchan_type, abook_flags, abook_self 
					FROM abook left join xchan on abook_xchan = xchan_hash 
					WHERE abook_channel = %d AND abook_blocked = 0 and abook_pending = 0 and xchan_deleted = 0 $sql_extra4 order by xchan_name asc limit $count",
                    intval(local_channel())
                );
            } else { // Visitors
                $r = q(
                    "SELECT xchan_hash as id, xchan_hash as hash, xchan_name as name, xchan_photo_s as micro, xchan_url as url, xchan_addr as nick, 0 as abook_flags, 0 as abook_self
					FROM xchan left join xlink on xlink_link = xchan_hash
					WHERE xlink_xchan  = '%s' AND xchan_deleted = 0 $sql_extra2 order by $order_extra2 xchan_name asc limit $count",
                    dbesc(get_observer_hash())
                );
            }
            if ((count($r) < 100) && $type == 'c') {
                $r2 = q("SELECT xchan_hash as id, xchan_hash as hash, xchan_name as name, xchan_photo_s as micro, xchan_url as url, xchan_addr as nick, 0 as abook_flags, 0 as abook_self 
					FROM xchan WHERE xchan_deleted = 0 and xchan_network != 'unknown' $sql_extra2 order by $order_extra2 xchan_name asc limit $count");
                if ($r2) {
                    $r = array_merge($r, $r2);
                    $r = unique_multidim_array($r, 'hash');
                }
            }
        } elseif ($type == 'm') {
            $r = [];
            $z = q(
                "SELECT xchan_hash as hash, xchan_name as name, xchan_addr as nick, xchan_photo_s as micro, xchan_url as url 
				FROM abook left join xchan on abook_xchan = xchan_hash
				WHERE abook_channel = %d 
				and xchan_deleted = 0
				$sql_extra3
				ORDER BY xchan_name ASC ",
                intval(local_channel())
            );
            if ($z) {
                foreach ($z as $zz) {
                    if (in_array($zz['hash'], $permitted)) {
                        $r[] = $zz;
                    }
                }
            }
        } elseif ($type == 'a') {
            $r = q(
                "SELECT abook_id as id, xchan_name as name, xchan_hash as hash, xchan_addr as nick, xchan_photo_s as micro, xchan_network as network, xchan_url as url, xchan_addr as attag FROM abook left join xchan on abook_xchan = xchan_hash
				WHERE abook_channel = %d
				and xchan_deleted = 0
				$sql_extra3
				ORDER BY xchan_name ASC ",
                intval(local_channel())
            );
        } elseif ($type == 'z') {
            $r = q(
                "SELECT xchan_name as name, xchan_hash as hash, xchan_addr as nick, xchan_photo_s as micro, xchan_network as network, xchan_url as url, xchan_addr as attag FROM xchan left join abook on xchan_hash = abook_xchan
				WHERE ( abook_channel = %d OR abook_channel IS NULL ) 
				and xchan_deleted = 0
				$sql_extra3
				ORDER BY xchan_name ASC ",
                intval(local_channel())
            );
        } elseif ($type == 'x') {
            $contacts = [];
            $r = $this->navbar_complete();
            if ($r) {
                foreach ($r as $g) {
                    $contacts[] = [
                        "photo" => $g['photo'],
                        "name" => $g['name'],
                        "nick" => $g['address'],
                        'link' => (($g['address']) ? $g['address'] : $g['url']),
                        'xchan' => $g['hash']
                    ];
                }
            }

            $o = [
                'start' => $start,
                'count' => $count,
                'items' => $contacts,
            ];
            json_return_and_die($o);
        } else {
            $r = [];
        }

        if ($r) {
            foreach ($r as $g) {
                if (isset($g['network']) && in_array($g['network'], ['rss', 'anon', 'unknown']) && ($type != 'a')) {
                    continue;
                }

                // 'z' (activity_filter autocomplete) requires an un-encoded hash to prevent double encoding

                if ($type !== 'z') {
                    $g['hash'] = urlencode($g['hash']);
                }

                if (!$g['nick']) {
                    $g['nick'] = $g['url'];
                }

                if (in_array($g['hash'], $permitted) && $type === 'f' && (!$noforums)) {
                    $contacts[] = [
                        "type" => "c",
                        "photo" => "images/twopeople.png",
                        "name" => $g['name'],
                        "id" => urlencode($g['id']),
                        "xid" => $g['hash'],
                        "link" => (($g['nick']) ? $g['nick'] : $g['url']),
                        "nick" => substr($g['nick'], 0, strpos($g['nick'], '@')),
                        "self" => (intval($g['abook_self']) ? 'abook-self' : ''),
                        "taggable" => 'taggable',
                        "label" => t('network')
                    ];
                }
                if ($type !== 'f') {
                    $contacts[] = [
                        "type" => "c",
                        "photo" => $g['micro'],
                        "name" => $g['name'],
                        "id" => urlencode($g['id']),
                        "xid" => $g['hash'],
                        "link" => (($g['nick']) ? $g['nick'] : $g['url']),
                        "nick" => ((strpos($g['nick'], '@')) ? substr($g['nick'], 0, strpos($g['nick'], '@')) : $g['nick']),
                        "self" => (intval($g['abook_self']) ? 'abook-self' : ''),
                        "taggable" => '',
                        "label" => '',
                    ];
                }
            }
        }

        $items = array_merge($groups, $contacts);

        $o = [
            'start' => $start,
            'count' => $count,
            'items' => $items,
        ];

        json_return_and_die($o);
    }


    public function navbar_complete()
    {

        //  logger('navbar_complete');

        $search = ((x($_REQUEST, 'search')) ? htmlentities($_REQUEST['search'], ENT_COMPAT, 'UTF-8', false) : '');
        if (!$search || mb_strlen($search) < 2) {
            return [];
        }

        $star = false;
        $address = false;

        if (substr($search, 0, 1) === '@') {
            $search = substr($search, 1);
        }

        if (substr($search, 0, 1) === '*') {
            $star = true;
            $search = substr($search, 1);
        }

        if (strpos($search, '@') !== false) {
            $address = true;
        }


        $url = z_root() . '/dirsearch';


        $results = [];

        $count = (x($_REQUEST, 'count') ? $_REQUEST['count'] : 100);

        if ($url) {
            $query = $url . '?f=';
            $query .= '&name=' . urlencode($search) . "&limit=$count" . (($address) ? '&address=' . urlencode(punify($search)) : '');

            $x = Url::get($query);
            if ($x['success']) {
                $t = 0;
                $j = json_decode($x['body'], true);
                if ($j && $j['results']) {
                    $results = $j['results'];
                }
            }
        }
        return $results;
    }
}
