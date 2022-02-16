<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\Libsync;
use Code\Access\AccessControl;
use Code\Lib\Apps;
use Code\Daemon\Run;

require_once('include/conversation.php');
require_once('include/bbcode.php');
require_once('include/datetime.php');
require_once('include/event.php');
require_once('include/items.php');
require_once('include/text.php');
require_once('include/html2plain.php');
require_once('include/security.php');

class Calendar extends Controller
{

    public function post()
    {

        logger('post: ' . print_r($_REQUEST, true), LOGGER_DATA);

        if (!local_channel()) {
            return;
        }

        $event_id = ((x($_POST, 'event_id')) ? intval($_POST['event_id']) : 0);
        $event_hash = ((x($_POST, 'event_hash')) ? $_POST['event_hash'] : '');
        $xchan = ((x($_POST, 'xchan')) ? dbesc($_POST['xchan']) : '');
        $summary = escape_tags(trim($_POST['summary']));
        $desc = escape_tags(trim($_POST['desc']));
        $location = escape_tags(trim($_POST['location']));
        $type = escape_tags(trim($_POST['type']));
        $start_text = escape_tags($_REQUEST['dtstart']);
        $finish_text = escape_tags($_REQUEST['dtend']);
        $adjust = intval($_POST['adjust']);
        $nofinish = intval($_POST['nofinish']);
        $uid = local_channel();
        $timezone = ((x($_POST, 'timezone_select')) ? notags(trim($_POST['timezone_select'])) : '');
        $tz = (($timezone) ? $timezone : date_default_timezone_get());
        $categories = escape_tags(trim($_POST['categories']));


        // only allow editing your own events.

        if (($xchan) && ($xchan !== get_observer_hash())) {
            return;
        }


        if ($start_text) {
            $start = $start_text;
        } else {
            $start = sprintf('%d-%d-%d %d:%d:0', $startyear, $startmonth, $startday, $starthour, $startminute);
        }

        if ($finish_text) {
            $finish = $finish_text;
        } else {
            $finish = sprintf('%d-%d-%d %d:%d:0', $finishyear, $finishmonth, $finishday, $finishhour, $finishminute);
        }

        if ($nofinish) {
            $finish = NULL_DATE;
        }

        if ($adjust) {
            $start = datetime_convert($tz, 'UTC', $start);
            if (!$nofinish) {
                $finish = datetime_convert($tz, 'UTC', $finish);
            }
        } else {
            $start = datetime_convert('UTC', 'UTC', $start);
            if (!$nofinish) {
                $finish = datetime_convert('UTC', 'UTC', $finish);
            }
        }

        linkify_tags($location, local_channel());

        // Don't allow the event to finish before it begins.
        // It won't hurt anything, but somebody will file a bug report
        // and we'll waste a bunch of time responding to it. Time that
        // could've been spent doing something else.

        if (strcmp($finish, $start) < 0 && (!$nofinish)) {
            notice(t('Event can not end before it has started.') . EOL);
            if (intval($_REQUEST['preview'])) {
                echo(t('Unable to generate preview.'));
            }
            killme();
        }

        if ((!$summary) || (!$start)) {
            notice(t('Event title and start time are required.') . EOL);
            if (intval($_REQUEST['preview'])) {
                echo(t('Unable to generate preview.'));
            }
            killme();
        }

        $channel = App::get_channel();
        $acl = new AccessControl(false);

        if ($event_id) {
            $x = q(
                "select * from event where id = %d and uid = %d limit 1",
                intval($event_id),
                intval(local_channel())
            );
            if (!$x) {
                notice(t('Event not found.') . EOL);
                if (intval($_REQUEST['preview'])) {
                    echo(t('Unable to generate preview.'));
                    killme();
                }
                return;
            }

            $acl->set($x[0]);

            $created = $x[0]['created'];
            $edited = datetime_convert();
        } else {
            $created = $edited = datetime_convert();
            $acl->set_from_array($_POST);
        }

        $post_tags = [];
        $ac = $acl->get();

        $str_contact_allow = $ac['allow_cid'];
        $str_group_allow = $ac['allow_gid'];
        $str_contact_deny = $ac['deny_cid'];
        $str_group_deny = $ac['deny_gid'];

        $private = $acl->is_private();

        $results = linkify_tags($desc, local_channel());

        if ($results) {
            // Set permissions based on tag replacements

            set_linkified_perms($results, $str_contact_allow, $str_group_allow, local_channel(), false, $private);

            foreach ($results as $result) {
                $success = $result['success'];
                if ($success['replaced']) {
                    $post_tags[] = [
                        'uid' => local_channel(),
                        'ttype' => $success['termtype'],
                        'otype' => TERM_OBJ_POST,
                        'term' => $success['term'],
                        'url' => $success['url']
                    ];
                }
            }
        }


        if (strlen($categories)) {
            $cats = explode(',', $categories);
            foreach ($cats as $cat) {
                $post_tags[] = array(
                    'uid' => local_channel(),
                    'ttype' => TERM_CATEGORY,
                    'otype' => TERM_OBJ_POST,
                    'term' => trim($cat),
                    'url' => $channel['xchan_url'] . '?f=&cat=' . urlencode(trim($cat))
                );
            }
        }

        $datarray = [
            'dtstart' => $start,
            'dtend' => $finish,
            'summary' => $summary,
            'description' => $desc,
            'location' => $location,
            'etype' => $type,
            'adjust' => $adjust,
            'nofinish' => $nofinish,
            'uid' => local_channel(),
            'account' => get_account_id(),
            'event_xchan' => $channel['channel_hash'],
            'allow_cid' => $str_contact_allow,
            'allow_gid' => $str_group_allow,
            'deny_cid' => $str_contact_deny,
            'deny_gid' => $str_group_deny,
            'private' => intval($private),
            'id' => $event_id,
            'created' => $created,
            'edited' => $edited
        ];

        if (intval($_REQUEST['preview'])) {
            $html = format_event_html($datarray);
            echo $html;
            killme();
        }

        $event = event_store_event($datarray);

        if ($post_tags) {
            $datarray['term'] = $post_tags;
        }

        $item_id = event_store_item($datarray, $event);

        if ($item_id) {
            $r = q(
                "select * from item where id = %d",
                intval($item_id)
            );
            if ($r) {
                xchan_query($r);
                $sync_item = fetch_post_tags($r);
                $z = q(
                    "select * from event where event_hash = '%s' and uid = %d limit 1",
                    dbesc($r[0]['resource_id']),
                    intval($channel['channel_id'])
                );
                if ($z) {
                    Libsync::build_sync_packet($channel['channel_id'], ['event_item' => [encode_item($sync_item[0], true)], 'event' => $z]);
                }
            }
        }

        Run::Summon(['Notifier', 'event', $item_id]);
        killme();
    }


    public function get()
    {

        if (argc() > 2 && argv(1) == 'ical') {
            $event_id = argv(2);

            $sql_extra = permissions_sql(local_channel());

            $r = q(
                "select * from event where event_hash = '%s' $sql_extra limit 1",
                dbesc($event_id)
            );
            if ($r) {
                header('Content-type: text/calendar');
                header('Content-Disposition: attachment; filename="' . t('event') . '-' . $event_id . '.ics"');
                echo ical_wrapper($r);
                killme();
            } else {
                notice(t('Event not found.') . EOL);
                return;
            }
        }

        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        if ((argc() > 2) && (argv(1) === 'ignore') && intval(argv(2))) {
            $r = q(
                "update event set dismissed = 1 where id = %d and uid = %d",
                intval(argv(2)),
                intval(local_channel())
            );
        }

        if ((argc() > 2) && (argv(1) === 'unignore') && intval(argv(2))) {
            $r = q(
                "update event set dismissed = 0 where id = %d and uid = %d",
                intval(argv(2)),
                intval(local_channel())
            );
        }

        $channel = App::get_channel();

        $mode = 'view';
        $export = false;

        $ignored = ((x($_REQUEST, 'ignored')) ? " and dismissed = " . intval($_REQUEST['ignored']) . " " : '');

        if (argc() > 1) {
            if (argc() > 2 && argv(1) === 'add') {
                $mode = 'add';
                $item_id = intval(argv(2));
            }
            if (argc() > 2 && argv(1) === 'drop') {
                $mode = 'drop';
                $event_id = argv(2);
            }
            if (argc() <= 2 && argv(1) === 'export') {
                $export = true;
            }
            if (argc() > 2 && intval(argv(1)) && intval(argv(2))) {
                $mode = 'view';
            }
            if (argc() <= 2) {
                $mode = 'view';
                $event_id = argv(1);
            }
        }

        if ($mode === 'add') {
            event_addtocal($item_id, local_channel());
            killme();
        }

        if ($mode == 'view') {
            /* edit/create form */
            if ($event_id) {
                $r = q(
                    "SELECT * FROM event WHERE event_hash = '%s' AND uid = %d LIMIT 1",
                    dbesc($event_id),
                    intval(local_channel())
                );
                if ($r) {
                    $orig_event = $r[0];
                }
            }

            $channel = App::get_channel();

            if (argv(1) === 'json') {
                if (x($_GET, 'start')) {
                    $start = $_GET['start'];
                }
                if (x($_GET, 'end')) {
                    $finish = $_GET['end'];
                }
            }

            $start = datetime_convert('UTC', 'UTC', $start);
            $finish = datetime_convert('UTC', 'UTC', $finish);

            $adjust_start = datetime_convert('UTC', date_default_timezone_get(), $start);
            $adjust_finish = datetime_convert('UTC', date_default_timezone_get(), $finish);

            if (x($_GET, 'id')) {
                $r = q(
                    "SELECT event.*, item.plink, item.item_flags, item.author_xchan, item.owner_xchan, item.id as item_id
	                                from event left join item on item.resource_id = event.event_hash
					where item.resource_type = 'event' and event.uid = %d and event.id = %d limit 1",
                    intval(local_channel()),
                    intval($_GET['id'])
                );
            } elseif ($export) {
                $r = q(
                    "SELECT * from event where uid = %d",
                    intval(local_channel())
                );
            } else {
                // fixed an issue with "nofinish" events not showing up in the calendar.
                // There's still an issue if the finish date crosses the end of month.
                // Noting this for now - it will need to be fixed here and in Friendica.
                // Ultimately the finish date shouldn't be involved in the query.

                $r = q(
                    "SELECT event.*, item.plink, item.item_flags, item.author_xchan, item.owner_xchan, item.id as item_id
					from event left join item on event.event_hash = item.resource_id 
					where item.resource_type = 'event' and event.uid = %d and event.uid = item.uid $ignored 
					AND (( event.adjust = 0 AND ( event.dtend >= '%s' or event.nofinish = 1 ) AND event.dtstart <= '%s' ) 
					OR  (  event.adjust = 1 AND ( event.dtend >= '%s' or event.nofinish = 1 ) AND event.dtstart <= '%s' )) ",
                    intval(local_channel()),
                    dbesc($start),
                    dbesc($finish),
                    dbesc($adjust_start),
                    dbesc($adjust_finish)
                );
            }

            if ($r && !$export) {
                xchan_query($r);
                $r = fetch_post_tags($r, true);

                $r = sort_by_date($r);
            }

            $events = [];

            if ($r) {
                foreach ($r as $rr) {
                    $start = (($rr['adjust']) ? datetime_convert('UTC', date_default_timezone_get(), $rr['dtstart'], 'c') : datetime_convert('UTC', 'UTC', $rr['dtstart'], 'c'));
                    if ($rr['nofinish']) {
                        $end = null;
                    } else {
                        $end = (($rr['adjust']) ? datetime_convert('UTC', date_default_timezone_get(), $rr['dtend'], 'c') : datetime_convert('UTC', 'UTC', $rr['dtend'], 'c'));

                        // give a fake end to birthdays so they get crammed into a
                        // single day on the calendar

                        if ($rr['etype'] === 'birthday') {
                            $end = null;
                        }
                    }

                    $catsenabled = Apps::system_app_installed($x['profile_uid'], 'Categories');
                    $categories = '';
                    if ($catsenabled) {
                        if ($rr['term']) {
                            $categories = array_elm_to_str(get_terms_oftype($rr['term'], TERM_CATEGORY), 'term');
                        }
                    }

                    $allDay = false;

                    // allDay event rules
                    if (!strpos($start, 'T') && !strpos($end, 'T')) {
                        $allDay = true;
                    }
                    if (strpos($start, 'T00:00:00') && strpos($end, 'T00:00:00')) {
                        $allDay = true;
                    }

                    $edit = ((local_channel() && $rr['author_xchan'] == get_observer_hash()) ? array(z_root() . '/events/' . $rr['event_hash'] . '?expandform=1', t('Edit event'), '', '') : false);

                    $drop = [z_root() . '/events/drop/' . $rr['event_hash'], t('Delete event'), '', ''];

                    $events[] = [
                        'calendar_id' => 'calendar',
                        'rw' => true,
                        'id' => $rr['id'],
                        'uri' => $rr['event_hash'],
                        'start' => $start,
                        'end' => $end,
                        'drop' => $drop,
                        'allDay' => $allDay,
                        'title' => html_entity_decode($rr['summary'], ENT_COMPAT, 'UTF-8'),
                        'editable' => $edit ? true : false,
                        'item' => $rr,
                        'plink' => [$rr['plink'], t('Link to source')],
                        'description' => htmlentities($rr['description'], ENT_COMPAT, 'UTF-8', false),
                        'location' => htmlentities($rr['location'], ENT_COMPAT, 'UTF-8', false),
                        'allow_cid' => expand_acl($rr['allow_cid']),
                        'allow_gid' => expand_acl($rr['allow_gid']),
                        'deny_cid' => expand_acl($rr['deny_cid']),
                        'deny_gid' => expand_acl($rr['deny_gid']),
                        'categories' => $categories
                    ];
                }
            }

            if ($export) {
                header('Content-type: text/calendar');
                header('Content-Disposition: attachment; filename="' . t('calendar') . '-' . $channel['channel_address'] . '.ics"');
                echo ical_wrapper($r);
                killme();
            }

            if (App::$argv[1] === 'json') {
                json_return_and_die($events);
            }
        }


        if ($mode === 'drop' && $event_id) {
            $r = q(
                "SELECT * FROM event WHERE event_hash = '%s' AND uid = %d LIMIT 1",
                dbesc($event_id),
                intval(local_channel())
            );

            $sync_event = $r[0];

            if ($r) {
                $r = q(
                    "delete from event where event_hash = '%s' and uid = %d",
                    dbesc($event_id),
                    intval(local_channel())
                );
                if ($r) {
                    $r = q(
                        "update item set resource_type = '', resource_id = '' where resource_type = 'event' and resource_id = '%s' and uid = %d",
                        dbesc($event_id),
                        intval(local_channel())
                    );
                    $sync_event['event_deleted'] = 1;
                    Libsync::build_sync_packet(0, ['event' => [$sync_event]]);
                    killme();
                }
                notice(t('Failed to remove event') . EOL);
                killme();
            }
        }
    }
}
