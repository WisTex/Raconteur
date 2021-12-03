<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\LibBlock;

require_once('include/socgraph.php');


class Connections extends Controller
{

    public function init()
    {

        if (!local_channel()) {
            return;
        }

        $channel = App::get_channel();
        if ($channel) {
            head_set_icon($channel['xchan_photo_s']);
        }
    }

    public function get()
    {

        $sort_type = 0;
        $o = '';


        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
            return login();
        }

        nav_set_selected('Connections');

        $active = false;
        $blocked = false;
        $hidden = false;
        $ignored = false;
        $archived = false;
        $unblocked = false;
        $pending = false;
        $unconnected = false;
        $all = false;

        if (!(isset($_REQUEST['aj']) && $_REQUEST['aj'])) {
            $_SESSION['return_url'] = App::$query_string;
        }

        $search_flags = "";
        $head = '';

        if (argc() == 2) {
            switch (argv(1)) {
                case 'active':
                    $search_flags = " and abook_blocked = 0 and abook_ignored = 0 and abook_hidden = 0 and abook_archived = 0 AND abook_not_here = 0 ";
                    $head = t('Active');
                    $active = true;
                    break;
                case 'blocked':
                    $search_flags = " and abook_blocked = 1 ";
                    $head = t('Blocked');
                    $blocked = true;
                    break;
                case 'ignored':
                    $search_flags = " and abook_ignored = 1 ";
                    $head = t('Ignored');
                    $ignored = true;
                    break;
                case 'hidden':
                    $search_flags = " and abook_hidden = 1 ";
                    $head = t('Hidden');
                    $hidden = true;
                    break;
                case 'archived':
                    $search_flags = " and ( abook_archived = 1 OR abook_not_here = 1) ";
                    $head = t('Archived/Unreachable');
                    $archived = true;
                    break;
                case 'all':
                    $search_flags = '';
                    $head = t('All');
                    $all = true;
                    break;
                case 'pending':
                    $search_flags = " and abook_pending = 1 ";
                    $head = t('New');
                    $pending = true;
                    break;
                case 'ifpending':
                case intval(argv(1)):
                    $r = q(
                        "SELECT COUNT(abook.abook_id) AS total FROM abook left join xchan on abook.abook_xchan = xchan.xchan_hash where abook_channel = %d and abook_pending = 1 and abook_self = 0 and abook_ignored = 0 and xchan_deleted = 0 and xchan_orphan = 0 ",
                        intval(local_channel())
                    );
                    if ($r && $r[0]['total']) {
                        $search_flags = " and abook_pending = 1 ";
                        if (intval(argv(1))) {
                            $search_flags .= " and abook_id = " . intval(argv(1)) . " ";
                        }
                        $head = t('New');
                        $pending = true;
                        App::$argv[1] = 'pending';
                    } else {
                        $head = t('All');
                        $search_flags = '';
                        $all = true;
                        App::$argc = 1;
                        unset(App::$argv[1]);
                    }
                    break;

                default:
                    $search_flags = " and abook_blocked = 0 and abook_ignored = 0 and abook_hidden = 0 and abook_archived = 0 and abook_not_here = 0 ";
                    $active = true;
                    $head = t('Active');
                    break;
            }

            $sql_extra = $search_flags;
            if (argv(1) === 'pending') {
                $sql_extra .= " and abook_ignored = 0 ";
            }
        } else {
            $sql_extra = " and abook_blocked = 0 and abook_ignored = 0 and abook_hidden = 0 and abook_archived = 0 and abook_not_here = 0 ";
            $active = true;
            $head = t('Active');
        }

        $search = ((x($_REQUEST, 'search')) ? notags(trim($_REQUEST['search'])) : '');

        $tabs = array(

            'active' => array(
                'label' => t('Active Connections'),
                'url' => z_root() . '/connections/active',
                'sel' => ($active) ? 'active' : '',
                'title' => t('Show active connections'),
            ),

            'pending' => array(
                'label' => t('New Connections'),
                'url' => z_root() . '/connections/pending',
                'sel' => ($pending) ? 'active' : '',
                'title' => t('Show pending (new) connections'),
            ),

            'blocked' => array(
                'label' => t('Blocked'),
                'url' => z_root() . '/connections/blocked',
                'sel' => ($blocked) ? 'active' : '',
                'title' => t('Only show blocked connections'),
            ),

            'ignored' => array(
                'label' => t('Ignored'),
                'url' => z_root() . '/connections/ignored',
                'sel' => ($ignored) ? 'active' : '',
                'title' => t('Only show ignored connections'),
            ),

            'archived' => array(
                'label' => t('Archived/Unreachable'),
                'url' => z_root() . '/connections/archived',
                'sel' => ($archived) ? 'active' : '',
                'title' => t('Only show archived/unreachable connections'),
            ),

            'hidden' => array(
                'label' => t('Hidden'),
                'url' => z_root() . '/connections/hidden',
                'sel' => ($hidden) ? 'active' : '',
                'title' => t('Only show hidden connections'),
            ),

            'all' => array(
                'label' => t('All Connections'),
                'url' => z_root() . '/connections/all',
                'sel' => ($all) ? 'active' : '',
                'title' => t('Show all connections'),
            ),

        );

        $searching = false;
        if ($search) {
            $search_hdr = $search;
            $search_txt = dbesc(protect_sprintf($search));
            $searching = true;
        }
        $sql_extra .= (($searching) ? " AND ( xchan_name like '%%$search_txt%%' OR abook_alias like '%%$search_txt%%' ) " : "");

        if (isset($_REQUEST['gid']) && intval($_REQUEST['gid'])) {
            $sql_extra .= " and xchan_hash in ( select xchan from pgrp_member where gid = " . intval($_REQUEST['gid']) . " and uid = " . intval(local_channel()) . " ) ";
        }

        $r = q(
            "SELECT COUNT(abook.abook_id) AS total FROM abook left join xchan on abook.abook_xchan = xchan.xchan_hash 
			where abook_channel = %d and abook_self = 0 and xchan_deleted = 0 and xchan_orphan = 0 $sql_extra ",
            intval(local_channel())
        );
        if ($r) {
            App::set_pager_total($r[0]['total']);
            $total = $r[0]['total'];
        }

        $order_q = 'xchan_name';
        if (isset($_REQUEST['order'])) {
            switch ($_REQUEST['order']) {
                case 'date':
                    $order_q = 'abook_created desc';
                    break;
                case 'created':
                    $order_q = 'abook_created';
                    break;
                case 'cmax':
                    $order_q = 'abook_closeness';
                    break;
                case 'name':
                default:
                    $order_q = 'xchan_name';
                    break;
            }
        }


        $order = array(

            'name' => array(
                'label' => t('Name'),
                'url' => z_root() . '/connections' . ((argv(1)) ? '/' . argv(1) : '') . '?order=name',
                'sel' => ((isset($_REQUEST['order']) && $_REQUEST['order'] !== 'name') ? 'active' : ''),
                'title' => t('Order by name'),
            ),

            'date' => array(
                'label' => t('Recent'),
                'url' => z_root() . '/connections' . ((argv(1)) ? '/' . argv(1) : '') . '?order=date',
                'sel' => ((isset($_REQUEST['order']) && $_REQUEST['order'] === 'date') ? 'active' : ''),
                'title' => t('Order by recent'),
            ),

            'created' => array(
                'label' => t('Created'),
                'url' => z_root() . '/connections' . ((argv(1)) ? '/' . argv(1) : '') . '?order=created',
                'sel' => ((isset($_REQUEST['order']) && $_REQUEST['order'] === 'created') ? 'active' : ''),
                'title' => t('Order by date'),
            ),
// reserved for cmax
//          'date' => array(
//              'label' => t(''),
//              'url'   => z_root() . '/connections' . ((argv(1)) ? '/' . argv(1) : '') . '?order=date',
//              'sel'   => ($_REQUEST['order'] === 'date') ? 'active' : '',
//              'title' => t('Order by recent'),
//          ),

        );


        $r = q(
            "SELECT abook.*, xchan.* FROM abook left join xchan on abook.abook_xchan = xchan.xchan_hash
			WHERE abook_channel = %d and abook_self = 0 and xchan_deleted = 0 and xchan_orphan = 0 $sql_extra ORDER BY $order_q LIMIT %d OFFSET %d ",
            intval(local_channel()),
            intval(App::$pager['itemspage']),
            intval(App::$pager['start'])
        );

        $contacts = [];

        if ($r) {
            vcard_query($r);


            foreach ($r as $rr) {
                if ((!$blocked) && LibBlock::fetch_by_entity(local_channel(), $rr['xchan_hash'])) {
                    continue;
                }
                if ($rr['xchan_url']) {
                    if ((isset($rr['vcard']) && $rr['vcard']) && is_array($rr['vcard']['tels']) && $rr['vcard']['tels'][0]['nr']) {
                        $phone = $rr['vcard']['tels'][0]['nr'];
                    } else {
                        $phone = '';
                    }

                    $status_str = '';
                    $status = array(
                        ((isset($rr['abook_active']) && intval($rr['abook_active'])) ? t('Active') : ''),
                        ((intval($rr['abook_pending'])) ? t('Pending approval') : ''),
                        ((intval($rr['abook_archived'])) ? t('Archived') : ''),
                        ((intval($rr['abook_hidden'])) ? t('Hidden') : ''),
                        ((intval($rr['abook_ignored'])) ? t('Ignored') : ''),
                        ((intval($rr['abook_blocked'])) ? t('Blocked') : ''),
                        ((intval($rr['abook_not_here'])) ? t('Not connected at this location') : '')
                    );

                    $oneway = false;
                    if (!their_perms_contains(local_channel(), $rr['xchan_hash'], 'post_comments')) {
                        $oneway = true;
                    }

                    foreach ($status as $str) {
                        if (!$str) {
                            continue;
                        }
                        $status_str .= $str;
                        $status_str .= ', ';
                    }
                    $status_str = rtrim($status_str, ', ');

                    $contacts[] = array(
                        'img_hover' => sprintf(t('%1$s [%2$s]'), $rr['xchan_name'], $rr['xchan_url']),
                        'edit_hover' => t('Edit connection'),
                        'edit' => t('Edit'),
                        'delete_hover' => t('Delete connection'),
                        'id' => $rr['abook_id'],
                        'thumb' => $rr['xchan_photo_m'],
                        'name' => $rr['xchan_name'] . (($rr['abook_alias']) ? ' &lt;' . $rr['abook_alias'] . '&gt;' : ''),
                        'classes' => ((intval($rr['abook_archived']) || intval($rr['abook_not_here'])) ? 'archived' : ''),
                        'link' => z_root() . '/connedit/' . $rr['abook_id'],
                        'deletelink' => z_root() . '/connedit/' . intval($rr['abook_id']) . '/drop',
                        'delete' => t('Delete'),
                        'url' => chanlink_hash($rr['xchan_hash']),
                        'webbie_label' => t('Channel address'),
                        'webbie' => $rr['xchan_addr'],
                        'network_label' => t('Network'),
                        'network' => network_to_name($rr['xchan_network']),
                        'channel_type' => intval($rr['xchan_type']),
                        'call' => t('Call'),
                        'phone' => $phone,
                        'status_label' => t('Status'),
                        'status' => $status_str,
                        'connected_label' => t('Connected'),
                        'connected' => datetime_convert('UTC', date_default_timezone_get(), $rr['abook_created'], 'c'),
                        'approve_hover' => t('Approve connection'),
                        'approve' => (($rr['abook_pending']) ? t('Approve') : false),
                        'ignore_hover' => t('Ignore connection'),
                        'ignore' => ((!$rr['abook_ignored']) ? t('Ignore') : false),
                        'recent_label' => t('Recent activity'),
                        'recentlink' => z_root() . '/stream/?f=&cid=' . intval($rr['abook_id']),
                        'oneway' => $oneway,
                        'allow_delete' => ($rr['abook_pending'] || get_pconfig(local_channel(), 'system', 'connections_quick_delete')),
                    );
                }
            }
        }


        if ($_REQUEST['aj']) {
            if ($contacts) {
                $o = replace_macros(get_markup_template('contactsajax.tpl'), array(
                    '$contacts' => $contacts,
                    '$edit' => t('Edit'),
                ));
            } else {
                $o = '<div id="content-complete"></div>';
            }
            echo $o;
            killme();
        } else {
            $o .= "<script> var page_query = '" . escape_tags(urlencode($_GET['req'])) . "'; var extra_args = '" . extra_query_args() . "' ; </script>";
            $o .= replace_macros(get_markup_template('connections.tpl'), array(
                '$header' => t('Connections') . (($head) ? ': ' . $head : ''),
                '$tabs' => $tabs,
                '$order' => $order,
                '$sort' => t('Filter by'),
                '$sortorder' => t('Sort by'),
                '$total' => $total,
                '$search' => ((isset($search_hdr)) ? $search_hdr : EMPTY_STR),
                '$label' => t('Search'),
                '$desc' => t('Search your connections'),
                '$finding' => (($searching) ? t('Connections search') . ": '" . $search . "'" : ""),
                '$submit' => t('Find'),
                '$edit' => t('Edit'),
                '$cmd' => App::$cmd,
                '$contacts' => $contacts,
                '$paginate' => paginate($a),

            ));
        }

        if (!$contacts) {
            $o .= '<div id="content-complete"></div>';
        }

        return $o;
    }
}
