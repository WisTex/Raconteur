<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\AccessList;
use Code\Lib\Apps;
use Code\Lib\PConfig;
use Code\Lib\PermissionDescription;
use Code\Lib\Channel;
use Code\Lib\Navbar;
use Code\Lib\Libacl;
use Code\Extend\Hook;
use Code\Render\Theme;


require_once('include/conversation.php');

class Stream extends Controller
{
    // State passed in from the Update module.

    public int $profile_uid = 0;
    public int $loading = 0;
    public int $updating = 0;


    public function init()
    {
        if (!local_channel()) {
            return;
        }

        // setup identity information for page
        $channel = App::get_channel();
        App::$profile_uid = local_channel();
        App::$data['channel'] = $channel;
        head_set_icon($channel['xchan_photo_s']);
    }

    public function get()
    {
        if (!local_channel()) {
            $_SESSION['return_url'] = App::$query_string;
            return login();
        }

        $o = '';

        if ($this->loading && intval(App::$pager['page']) <= 1) {
            $_SESSION['loadtime_stream'] = datetime_convert();
            PConfig::Set(local_channel(), 'system', 'loadtime_stream', $_SESSION['loadtime_stream']);
            // stream is a superset of channel when it comes to notifications
            $_SESSION['loadtime_channel'] = datetime_convert();
            PConfig::Set(local_channel(), 'system', 'loadtime_channel', $_SESSION['loadtime_channel']);
        }

        $arr = ['query' => App::$query_string];
        Hook::call('stream_content_init', $arr);

        $channel = ((isset(App::$data['channel'])) ? App::$data['channel'] : null);

        // if called from liveUpdate() we will not have called Stream->init() on this request and $channel will not be set

        if (!$channel) {
            $channel = App::get_channel();
        }

        $item_normal = item_normal();
        $item_normal_update = item_normal_update();

        $group = 0;

        // $nouveau indicates the output is an unthreaded timeline.
        $nouveau = false;

        $datequery = ((x($_GET, 'dend') && is_a_date_arg($_GET['dend'])) ? notags($_GET['dend']) : '');
        $datequery2 = ((x($_GET, 'dbegin') && is_a_date_arg($_GET['dbegin'])) ? notags($_GET['dbegin']) : '');
        $static = ((x($_GET, 'static')) ? intval($_GET['static']) : 0);
        $gid = ((x($_GET, 'gid')) ? $_REQUEST['gid'] : 0);
        $category = ((x($_REQUEST, 'cat')) ? $_REQUEST['cat'] : '');
        $hashtags = ((x($_REQUEST, 'tag')) ? $_REQUEST['tag'] : '');
        $verb = ((x($_REQUEST, 'verb')) ? $_REQUEST['verb'] : '');
        $dm = ((x($_REQUEST, 'dm')) ? $_REQUEST['dm'] : 0);
        $distance = ((x($_REQUEST, 'distance')) ? $_REQUEST['distance'] : 0);
        $distance_from = ((x($_REQUEST,'distance_from')) ? notags(trim($_REQUEST['distance_from'])) : '');

        if ($distance_from) {
            $tmp = preg_split('/[ ,\/]/', $distance_from);
            $distance_lat = floatval($tmp[0]);
            $distance_lon = floatval($tmp[1]);
            if (! ($distance_lat || $distance_lon)) {
                $distance = 0;
            }
        }

        $c_order = get_pconfig(local_channel(), 'mod_stream', 'order', 0);
        switch ($c_order) {
            case 0:
                $order = 'comment';
                break;
            case 1:
                $order = 'post';
                break;
            case 2:
                $nouveau = true;
                break;
            case 3:
                $order = 'received';
                break;
            case 4:
                $order = 'distance';
                break;
        }

        $search = ($_GET['search'] ?? '');
        if ($search) {
            $_GET['netsearch'] = escape_tags($search);
            if (str_starts_with($search, '@')) {
                $r = q(
                    "select abook_id from abook left join xchan on abook_xchan = xchan_hash where xchan_name = '%s' and abook_channel = %d limit 1",
                    dbesc(substr($search, 1)),
                    intval(local_channel())
                );
                if ($r) {
                    $_GET['cid'] = $r[0]['abook_id'];
                    $search = $_GET['search'] = '';
                }
            } elseif (str_starts_with($search, '#')) {
                $hashtags = substr($search, 1);
                $search = $_GET['search'] = '';
            }
        }

        if ($datequery) {
            $order = 'post';
        }

        // filter by collection (e.g. group)

        $vg = false;

        if ($gid) {
            if (str_starts_with($gid, ':')) {
                $g = substr($gid, 1);
                switch ($g) {
                    case '1':
                        $r = [['hash' => 'connections:' . $channel['channel_hash']]];
                        $vg = t('Connections');
                        break;
                    case '2':
                        $r = [['hash' => 'zot:' . $channel['channel_hash']]];
                        $vg = t('Nomad');
                        break;
                    case '3':
                        $r = [['hash' => 'activitypub:' . $channel['channel_hash']]];
                        $vg = t('ActivityPub');
                        break;
                    default:
                        break;
                }
            } else {
                $r = q(
                    "SELECT * FROM pgrp WHERE id = %d AND uid = %d LIMIT 1",
                    intval($gid),
                    intval(local_channel())
                );
                if (!$r) {
                    if ($this->updating) {
                        killme();
                    }
                    notice(t('Access list not found') . EOL);
                    goaway(z_root() . '/stream');
                }
            }


            $group = $gid;
            $group_hash = $r[0]['hash'];
        }

        $default_cmin = ((Apps::system_app_installed(local_channel(), 'Friend Zoom')) ? get_pconfig(local_channel(), 'affinity', 'cmin', 0) : (-1));
        $default_cmax = ((Apps::system_app_installed(local_channel(), 'Friend Zoom')) ? get_pconfig(local_channel(), 'affinity', 'cmax', 99) : (-1));

        $cid = ((x($_GET, 'cid')) ? intval($_GET['cid']) : 0);
        $draft = ((x($_GET, 'draft')) ? intval($_GET['draft']) : 0);
        $star = ((x($_GET, 'star')) ? intval($_GET['star']) : 0);
        $liked = ((x($_GET, 'liked')) ? intval($_GET['liked']) : 0);
        $conv = ((x($_GET, 'conv')) ? intval($_GET['conv']) : 0);
        $spam = ((x($_GET, 'spam')) ? intval($_GET['spam']) : 0);
        $cmin = ((array_key_exists('cmin', $_GET)) ? intval($_GET['cmin']) : $default_cmin);
        $cmax = ((array_key_exists('cmax', $_GET)) ? intval($_GET['cmax']) : $default_cmax);
        $file = ((x($_GET, 'file')) ? $_GET['file'] : '');
        $xchan = ((x($_GET, 'xchan')) ? $_GET['xchan'] : '');
        $net = ((x($_GET, 'net')) ? $_GET['net'] : '');
        $pf = ((x($_GET, 'pf')) ? $_GET['pf'] : '');

        $deftag = '';


        if (x($_GET, 'search') || $file || (!$pf && $cid)) {
            $nouveau = true;
        }

        if ($cid) {
            $cid_r = q(
                "SELECT abook.abook_xchan, xchan.xchan_addr, xchan.xchan_name, xchan.xchan_url, xchan.xchan_photo_s, xchan.xchan_type from abook left join xchan on abook_xchan = xchan_hash where abook_id = %d and abook_channel = %d and abook_blocked = 0 limit 1",
                intval($cid),
                intval(local_channel())
            );

            if (!$cid_r) {
                if ($this->updating) {
                    killme();
                }
                notice(t('No such channel') . EOL);
                goaway(z_root() . '/stream');
            }
        }
        $status_editor = '';

        if (!$this->updating) {
            // search terms header

            // hide the terms we use to search for videos from
            // the activity_filter widget because it doesn't look very good
            if ($search && $search !== 'video]') {
                $o .= replace_macros(
                    Theme::get_template("section_title.tpl"),
                    ['$title' => t('Search Results For:') . ' ' . htmlspecialchars($search, ENT_COMPAT, 'UTF-8')]
                );
            }

            $body = EMPTY_STR;

            Navbar::set_selected('Stream');

            $channel_acl = [
                'allow_cid' => $channel['channel_allow_cid'],
                'allow_gid' => $channel['channel_allow_gid'],
                'deny_cid' => $channel['channel_deny_cid'],
                'deny_gid' => $channel['channel_deny_gid']
            ];

            $x = [
                'is_owner' => true,
                'allow_location' => ((intval(get_pconfig($channel['channel_id'], 'system', 'use_browser_location'))) ? '1' : ''),
                'default_location' => $channel['channel_location'],
                'nickname' => $channel['channel_address'],
                'lockstate' => (($channel['channel_allow_cid'] || $channel['channel_allow_gid'] || $channel['channel_deny_cid'] || $channel['channel_deny_gid']) ? 'lock' : 'unlock'),
                'acl' => Libacl::populate($channel_acl, true, PermissionDescription::fromGlobalPermission('view_stream'), Libacl::get_post_aclDialogDescription(), 'acl_dialog_post'),
                'permissions' => $channel_acl,
                'bang' => EMPTY_STR,
                'body' => $body,
                'visitor' => true,
                'profile_uid' => local_channel(),
                'editor_autocomplete' => true,
                'bbco_autocomplete' => 'bbcode',
                'bbcode' => true,
                'jotnets' => true,
                'reset' => t('Reset form')
            ];

            if ($deftag) {
                $x['pretext'] = $deftag;
            }

            $status_editor = status_editor($x);
            $o .= $status_editor;

            $static = Channel::manual_conv_update(local_channel());
        }

        // We don't have to deal with ACL's on this page. You're looking at everything
        // that belongs to you, hence you can see all of it. We will filter by group if
        // desired.

        $sql_options = (($star)
            ? " and item_starred = 1 "
            : '');

        $sql_nets = '';

        $item_thread_top = ' AND item_thread_top = 1 ';

        $sql_extra = '';

        if ($draft) {
            $item_normal = item_normal_draft();
            $sql_extra = " AND item.parent IN ( SELECT DISTINCT parent FROM item WHERE item_unpublished = 1 and item_deleted = 0 ) ";
        }

        $title = '';

        if ($group) {
            $contacts = AccessList::members(local_channel(), $group);
            if ($contacts) {
                $contact_str = ids_to_querystr($contacts, 'xchan', true);
            } else {
                $contact_str = " '0' ";
                if (!$this->updating) {
                    info(t('Access list is empty'));
                }
            }
            $item_thread_top = '';

            $sql_extra = " AND item.parent IN ( SELECT DISTINCT parent FROM item WHERE true $sql_options AND (( author_xchan IN ( $contact_str ) OR owner_xchan in ( $contact_str )) or allow_gid like '" . protect_sprintf('%<' . dbesc($group_hash) . '>%') . "' ) and id = parent $item_normal ) ";


            if (!$vg) {
                $x = AccessList::rec_byhash(local_channel(), $group_hash);
            }

            if ($x || $vg) {
                $title = replace_macros(Theme::get_template("section_title.tpl"), [
                    '$title' => sprintf(t('Access list: %s'), (($vg) ?: $x['gname']))
                ]);
            }

            $o = $title . $status_editor;
        } elseif (isset($cid_r) && $cid_r) {
            $item_thread_top = EMPTY_STR;

            if ($this->loading || $this->updating) {
                if (!$pf && $nouveau) {
                    $sql_extra = " AND author_xchan = '" . dbesc($cid_r[0]['abook_xchan']) . "' ";
                } else {
                    $ttype = (($pf) ? TERM_FORUM : TERM_MENTION);

                    $p1 = q("SELECT DISTINCT parent FROM item WHERE uid = " . intval(local_channel()) . " AND ( author_xchan = '" . dbesc($cid_r[0]['abook_xchan']) . "' OR owner_xchan = '" . dbesc($cid_r[0]['abook_xchan']) . "' ) $item_normal ");
                    $p2 = q("SELECT oid AS parent FROM term WHERE uid = " . intval(local_channel()) . " AND ttype = $ttype AND term = '" . dbesc($cid_r[0]['xchan_name']) . "'");

                    $p_str = ids_to_querystr(array_merge($p1, $p2), 'parent');
                    $sql_extra = " AND item.parent IN ( $p_str ) ";
                }
            }

            $title = replace_macros(Theme::get_template('section_title.tpl'), [
                '$title' => '<a href="' . zid($cid_r[0]['xchan_url']) . '" ><img src="' . zid($cid_r[0]['xchan_photo_s']) . '" alt="' . urlencode($cid_r[0]['xchan_name']) . '" /></a> <a href="' . zid($cid_r[0]['xchan_url']) . '" >' . $cid_r[0]['xchan_name'] . '</a>'
            ]);

            $o = $title;
            $o .= $status_editor;
        } elseif ($xchan) {
            $r = q(
                "select * from xchan where xchan_hash = '%s'",
                dbesc($xchan)
            );
            if ($r) {
                $item_thread_top = '';
                $sql_extra = " AND item.parent IN ( SELECT DISTINCT parent FROM item WHERE true $sql_options AND uid = " . intval(local_channel()) . " AND ( author_xchan = '" . dbesc($xchan) . "' or owner_xchan = '" . dbesc($xchan) . "' ) $item_normal ) ";
                $title = replace_macros(Theme::get_template('section_title.tpl'), [
                    '$title' => '<a href="' . zid($r[0]['xchan_url']) . '" ><img src="' . zid($r[0]['xchan_photo_s']) . '" alt="' . urlencode($r[0]['xchan_name']) . '" /></a> <a href="' . zid($r[0]['xchan_url']) . '" >' . $r[0]['xchan_name'] . '</a>'
                ]);

                $o = $title;
                $o .= $status_editor;
            } else {
                notice(t('Invalid channel.') . EOL);
                goaway(z_root() . '/stream');
            }
        }

        if (x($category)) {
            $sql_extra .= protect_sprintf(term_query('item', $category, TERM_CATEGORY));
        }
        if (x($hashtags)) {
            $sql_extra .= protect_sprintf(term_query('item', $hashtags, TERM_HASHTAG, TERM_COMMUNITYTAG));
        }

        if (!$this->updating) {
            // The special div is needed for liveUpdate to kick in for this page.
            // We only launch liveUpdate if you aren't filtering in some incompatible
            // way, and also you aren't writing a comment (discovered in javascript).

            $maxheight = get_pconfig(local_channel(), 'system', 'stream_divmore_height');
            if (!$maxheight) {
                $maxheight = 400;
            }


            $o .= '<div id="live-stream"></div>' . "\r\n";
            $o .= "<script> let profile_uid = " . local_channel()
                . "; let profile_page = " . App::$pager['page']
                . "; divmore_height = " . intval($maxheight) . "; </script>\r\n";

            App::$page['htmlhead'] .= replace_macros(Theme::get_template('build_query.tpl'), [
                '$baseurl' => z_root(),
                '$pgtype' => 'stream',
                '$uid' => ((local_channel()) ? local_channel() : '0'),
                '$gid' => (($gid) ?: '0'),
                '$cid' => (($cid) ?: '0'),
                '$cmin' => (($cmin) ?: '(-1)'),
                '$cmax' => (($cmax) ?: '(-1)'),
                '$star' => (($star) ?: '0'),
                '$liked' => (($liked) ?: '0'),
                '$conv' => (($conv) ?: '0'),
                '$spam' => (($spam) ?: '0'),
                '$fh' => '0',
                '$dm' => (($dm) ?: '0'),
                '$nouveau' => (($nouveau) ?: '0'),
                '$wall' => '0',
                '$draft' => (($draft) ?: '0'),
                '$static' => $static,
                '$list' => ((x($_REQUEST, 'list')) ? intval($_REQUEST['list']) : 0),
                '$page' => ((App::$pager['page'] != 1) ? App::$pager['page'] : 1),
                '$search' => (($search) ? urlencode($search) : ''),
                '$xchan' => (($xchan) ? urlencode($xchan) : ''),
                '$order' => (($order) ? urlencode($order) : ''),
                '$file' => (($file) ? urlencode($file) : ''),
                '$cats' => (($category) ? urlencode($category) : ''),
                '$tags' => (($hashtags) ? urlencode($hashtags) : ''),
                '$dend' => $datequery,
                '$mid' => '',
                '$verb' => (($verb) ? urlencode($verb) : ''),
                '$net' => (($net) ? urlencode($net) : ''),
                '$dbegin' => $datequery2,
                '$pf' => (($pf) ? intval($pf) : '0'),
                '$distance' => (($distance) ? intval($distance) : '0'),
                '$distance_from' => (($distance_from) ? urlencode($distance_from) : ''),
            ]);
        }

        $sql_extra3 = '';

        if ($datequery) {
            $sql_extra3 .= protect_sprintf(sprintf(" AND item.created <= '%s' ", dbesc(datetime_convert(date_default_timezone_get(), '', $datequery))));
        }
        if ($datequery2) {
            $sql_extra3 .= protect_sprintf(sprintf(" AND item.created >= '%s' ", dbesc(datetime_convert(date_default_timezone_get(), '', $datequery2))));
        }

        $sql_extra3 = (($nouveau) ? '' : $sql_extra3);

        if (x($_GET, 'search')) {
            $search = escape_tags($_GET['search']);
            if (str_starts_with($search, '#')) {
                $sql_extra .= term_query('item', substr($search, 1), TERM_HASHTAG, TERM_COMMUNITYTAG);
            } else {
                $sql_extra .= sprintf(
                    " AND (item.body like '%s' OR item.title like '%s') ",
                    dbesc(protect_sprintf('%' . $search . '%')),
                    dbesc(protect_sprintf('%' . $search . '%'))
                );
            }
        }

        if ($verb) {
            // the presence of a leading dot in the verb determines
            // whether to match the type of activity or the child object.
            // The name 'verb' is a holdover from the earlier XML
            // ActivityStreams specification.

            if (str_starts_with($verb, '.')) {
                $verb = substr($verb, 1);
                $sql_extra .= sprintf(
                    " AND item.obj_type like '%s' ",
                    dbesc(protect_sprintf('%' . $verb . '%'))
                );
            } else {
                $sql_extra .= sprintf(
                    " AND item.verb like '%s' ",
                    dbesc(protect_sprintf('%' . $verb . '%'))
                );
            }
        }

        if (strlen($file)) {
            $sql_extra .= term_query('item', $file, TERM_FILE);
        }

        if ($dm) {
            $sql_extra .= " and item_private = 2 ";
        }

        if ($conv) {
            $item_thread_top = '';

            if ($nouveau) {
                $sql_extra .= " AND author_xchan = '" . dbesc($channel['channel_hash']) . "' ";
            } else {
                $sql_extra .= sprintf(
                    " AND parent IN (SELECT distinct(parent) from item where ( author_xchan = '%s' or item_mentionsme = 1 ) and item_deleted = 0 ) ",
                    dbesc(protect_sprintf($channel['channel_hash']))
                );
            }
        }

        if ($this->updating && !$this->loading) {
            // only setup pagination on initial page view
            $pager_sql = '';
        } else {
            $itemspage = get_pconfig(local_channel(), 'system', 'itemspage');
            App::set_pager_itemspage(((intval($itemspage)) ? $itemspage : 20));
            $pager_sql = sprintf(" LIMIT %d OFFSET %d ", intval(App::$pager['itemspage']), intval(App::$pager['start']));
        }

        // cmin and cmax are both -1 when the affinity tool is disabled

        if (($cmin != (-1)) || ($cmax != (-1))) {
            // Not everybody who shows up in the network stream will be in your address book.
            // By default, those that aren't are assumed to have closeness = 99; but this isn't
            // recorded anywhere. So if cmax is 99, we'll open the search up to anybody in
            // the stream with a NULL address book entry.

            $sql_nets .= " AND ";

            if ($cmax == 99) {
                $sql_nets .= " ( ";
            }

            $sql_nets .= "( abook.abook_closeness >= " . intval($cmin) . " ";
            $sql_nets .= " AND abook.abook_closeness <= " . intval($cmax) . " ) ";

            if ($cmax == 99) {
                $sql_nets .= " OR abook.abook_closeness IS NULL ) ";
            }
        }

        $net_query = (($net) ? " left join xchan on xchan_hash = author_xchan " : '');
        $net_query2 = (($net) ? " and xchan_network = '" . protect_sprintf(dbesc($net)) . "' " : '');

        $abook_uids = " and abook.abook_channel = " . local_channel() . " ";
        $uids = " and item.uid = " . local_channel() . " ";

        if (get_pconfig(local_channel(), 'system', 'stream_list_mode')) {
            $page_mode = 'list';
        } else {
            $page_mode = 'client';
        }

        $simple_update = (($this->updating) ? " and item.changed > '" . $_SESSION['loadtime_stream'] . "' " : '');

        $parents_str = '';
        $update_unseen = '';
        $items = [];

        if ($this->loading) {
            $simple_update = '';
        }

        if ($static && $simple_update) {
            $simple_update .= " and item_thread_top = 0 and author_xchan = '" . protect_sprintf(get_observer_hash()) . "' ";
        }


        // we are not yet using this in updates because the content may have just been marked seen
        // and this might prevent us from loading the update. Will need to test further.

        $seenstr = EMPTY_STR;
        if (local_channel()) {
            $seen = PConfig::Get(local_channel(), 'system', 'seen_items', []);
            if ($seen) {
                $seenstr = " and not item.id in (" . implode(',', $seen) . ") ";
            }
        }

        if ($nouveau && $this->loading) {
            // "New Item View" - show all items unthreaded in reverse date order

            $items = q("SELECT item.*, item.id AS item_id, created FROM item 
				left join abook on ( item.owner_xchan = abook.abook_xchan $abook_uids )
				$net_query
				WHERE true $uids $item_normal
				and (abook.abook_blocked = 0 or abook.abook_flags is null)
				$simple_update
				$sql_extra $sql_options $sql_nets
				$net_query2
				ORDER BY item.edited DESC $pager_sql ");

            xchan_query($items);
            $items = fetch_post_tags($items);
        } elseif ($this->updating) {
            // Normal conversation view
            if ($order === 'post') {
                $ordering = 'created';
            } elseif ($order === 'received') {
                $ordering = 'changed';
            } elseif ($distance) {
                $ordering = 'distance asc, commented';
                $sql_extra .= " and (lat != 0 or lon != 0) ";
            } else {
                $ordering = 'commented';
            }

            $null_distance = ($distance) ? " AND lat != 0.0 AND lon != 0.0 " : "";

            $base_query = ($distance)
                ?  "SELECT item.parent AS item_id, 
                    (6371 * acos(cos(radians(lat) )
                    * cos( radians( $distance_lat ) )
                    * cos( radians( $distance_lon ) - radians(lon) )
                    + sin( radians(lat) )
                    * sin( radians( $distance_lat ) )
                    )) as distance from item"
                : "SELECT item.parent AS item_id FROM item";

            if ($this->loading) {
                // Fetch a page full of parent items for this page


                $r = q("$base_query 
					left join abook on ( item.owner_xchan = abook.abook_xchan $abook_uids )
					$net_query
					WHERE true $uids $item_thread_top $item_normal $null_distance
					AND item.mid = item.parent_mid
					and (abook.abook_blocked = 0 or abook.abook_flags is null)
					$sql_extra3 $sql_extra $sql_options $sql_nets
					$net_query2
					ORDER BY $ordering DESC $pager_sql ");

            } else {
                // this is an update
                $r = q("$base_query
					left join abook on ( item.owner_xchan = abook.abook_xchan $abook_uids )
					$net_query
					WHERE true $uids $item_normal_update $simple_update $null_distance
					and (abook.abook_blocked = 0 or abook.abook_flags is null)
					$sql_extra3 $sql_extra $sql_options $sql_nets $net_query2");
            }

            if ($r) {
                $parents_str = ids_to_querystr($r, 'item_id');

                $items = q(
                    "SELECT item.*, item.id AS item_id FROM item
					WHERE true $uids $item_normal
					AND item.parent IN ( %s )
					$sql_extra ",
                    dbesc($parents_str)
                );

                if ($distance) {
                    foreach ($r as $parent) {
                        for($cnt = 0; $cnt < count($items); $cnt ++) {
                            if ($parent['item_id'] === $items[$cnt]['id']) {
                                $items[$cnt]['distance'] = $parent['distance'];
                            }
                        }
                    }
                }

    
                xchan_query($items, true);
                $items = fetch_post_tags($items);
                $items = conv_sort($items, $ordering);
            }

            if ($page_mode === 'list') {

                /**
                 * in "list mode", only mark the parent item and any like activities as "seen".
                 * We won't distinguish between comment likes and post likes. The important thing
                 * is that the number of unseen comments will be accurate. The SQL to separate the
                 * comment likes could also get somewhat hairy.
                 */

                if ($parents_str) {
                    $update_unseen = " AND ( id IN ( " . dbesc($parents_str) . " )";
                    $update_unseen .= " OR ( parent IN ( " . dbesc($parents_str) . " ) AND verb in ( '" . dbesc(ACTIVITY_LIKE) . "','" . dbesc(ACTIVITY_DISLIKE) . "' ))) ";
                }
            } else {
                if ($parents_str) {
                    $update_unseen = " AND parent IN ( " . dbesc($parents_str) . " )";
                }
            }
        }

        if ($update_unseen && empty($_SESSION['sudo'])) {
            $x = ['channel_id' => local_channel(), 'update' => 'unset'];
            Hook::call('update_unseen', $x);
            if ($x['update'] === 'unset' || intval($x['update'])) {
                q("UPDATE item SET item_unseen = 0 WHERE item_unseen = 1 AND uid = %d $update_unseen ",
                    intval(local_channel())
                );
            }

            $ids = ids_to_array($items, 'item_id');
            $seen = PConfig::Get(local_channel(), 'system', 'seen_items');
            if (!$seen) {
                $seen = [];
            }
            $seen = array_merge($ids, $seen);
            PConfig::Set(local_channel(), 'system', 'seen_items', $seen);
        }

        $mode = (($nouveau) ? 'stream-new' : 'stream');

        if ($search) {
            $mode = 'search';
        }

        $o .= conversation($items, $mode, $this->updating, $page_mode);

        if (($items) && (!$this->updating)) {
            $o .= alt_pager(count($items));
        }

        return $o;
    }
}
