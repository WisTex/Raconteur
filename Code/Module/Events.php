<?php

namespace Code\Module;

use App;
use DateTime;
use Code\Lib\PermissionDescription;
use Code\Web\Controller;
use Code\Lib\Libsync;
use Code\Lib\Apps;
use Code\Access\AccessControl;
use Code\Daemon\Run;
use Code\Lib\Navbar;
use Code\Lib\Libacl;
use Code\Lib\Features;
use Code\Render\Theme;

    
require_once('include/conversation.php');
require_once('include/event.php');
require_once('include/html2plain.php');

class Events extends Controller
{

    public function post()
    {

        logger('post: ' . print_r($_REQUEST, true), LOGGER_DATA);

        if (!local_channel()) {
            return;
        }

        $channel = App::get_channel();

        if (($_FILES) && array_key_exists('userfile', $_FILES) && intval($_FILES['userfile']['size'])) {
            $src = $_FILES['userfile']['tmp_name'];
            if ($src) {
                $result = parse_ical_file($src, local_channel());
                if ($result) {
                    info(t('Calendar entries imported.') . EOL);
                } else {
                    notice(t('No calendar entries found.') . EOL);
                }
                @unlink($src);
            }
            goaway(z_root() . '/events');
        }


        $event_id = ((x($_POST, 'event_id')) ? intval($_POST['event_id']) : 0);
        $event_hash = ((x($_POST, 'event_hash')) ? $_POST['event_hash'] : '');

        $xchan = ((x($_POST, 'xchan')) ? dbesc($_POST['xchan']) : '');
        $uid = local_channel();

        $start_text = escape_tags($_REQUEST['start_text']);
        $finish_text = escape_tags($_REQUEST['finish_text']);

        $adjust = intval($_POST['adjust']);
        $nofinish = intval($_POST['nofinish']);

        $timezone = ((x($_POST, 'timezone_select')) ? notags(trim($_POST['timezone_select'])) : '');

        $tz = (($timezone) ? $timezone : date_default_timezone_get());

        $categories = escape_tags(trim($_POST['category']));

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

        // Don't allow the event to finish before it begins.
        // It won't hurt anything, but somebody will file a bug report
        // and we'll waste a bunch of time responding to it. Time that
        // could've been spent doing something else.


        $summary = escape_tags(trim($_POST['summary']));
        $desc = escape_tags(trim($_POST['desc']));
        $location = escape_tags(trim($_POST['location']));
        $type = escape_tags(trim($_POST['type']));

        $repeat = ((array_key_exists('repeat', $_REQUEST) && intval($_REQUEST['repeat'])) ? 1 : 0);
        $freq = ((array_key_exists('freq', $_REQUEST) && $_REQUEST['freq']) ? escape_tags(trim($_REQUEST['freq'])) : EMPTY_STR);
        $interval = ((array_key_exists('interval', $_REQUEST) && intval($_REQUEST['interval'])) ? 1 : 0);
        $count = ((array_key_exists('count', $_REQUEST) && intval($_REQUEST['count'])) ? intval($_REQUEST['count']) : 0);
        $until = ((array_key_exists('until', $_REQUEST) && $_REQUEST['until']) ? datetime_convert(date_default_timezone_get(), 'UTC', $_REQUEST['until']) : NULL_DATE);
        $byday = [];

        if ((!$freq) || (!in_array($freq, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY']))) {
            $repeat = 0;
        }
        if ($count < 0) {
            $count = 0;
        }
        if ($count > MAX_EVENT_REPEAT_COUNT) {
            $count = MAX_EVENT_REPEAT_COUNT;
        }

        linkify_tags($desc, local_channel());
        linkify_tags($location, local_channel());

        //$action = ($event_hash == '') ? 'new' : "event/" . $event_hash;

        //@fixme: this url gives a wsod if there is a linebreak detected in one of the variables ($desc or $location)
        //$onerror_url = z_root() . "/events/" . $action . "?summary=$summary&description=$desc&location=$location&start=$start_text&finish=$finish_text&adjust=$adjust&nofinish=$nofinish&type=$type";
        $onerror_url = z_root() . "/events";

        if (strcmp($finish, $start) < 0 && !$nofinish) {
            notice(t('Event can not end before it has started.') . EOL);
            if (intval($_REQUEST['preview'])) {
                echo(t('Unable to generate preview.'));
                killme();
            }
            goaway($onerror_url);
        }

        if ((!$summary) || (!$start)) {
            notice(t('Event title and start time are required.') . EOL);
            if (intval($_REQUEST['preview'])) {
                echo(t('Unable to generate preview.'));
                killme();
            }
            goaway($onerror_url);
        }

        //      $share = ((intval($_POST['distr'])) ? intval($_POST['distr']) : 0);

        $share = 1;


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

            if (
                $x[0]['allow_cid'] === '<' . $channel['channel_hash'] . '>'
                && $x[0]['allow_gid'] === '' && $x[0]['deny_cid'] === '' && $x[0]['deny_gid'] === ''
            ) {
                $share = false;
            } else {
                $share = true;
            }
        } else {
            $created = $edited = datetime_convert();
            if ($share) {
                $acl->set_from_array($_POST);
            } else {
                $acl->set(['allow_cid' => '<' . $channel['channel_hash'] . '>', 'allow_gid' => '', 'deny_cid' => '', 'deny_gid' => '']);
            }
        }

        $post_tags = [];

        $ac = $acl->get();

        if (strlen($categories)) {
            $cats = explode(',', $categories);
            foreach ($cats as $cat) {
                $post_tags[] = [
                    'uid' => $profile_uid,
                    'ttype' => TERM_CATEGORY,
                    'otype' => TERM_OBJ_POST,
                    'term' => trim($cat),
                    'url' => $channel['xchan_url'] . '?f=&cat=' . urlencode(trim($cat))
                ];
            }
        }

        $datarray = [];
        $datarray['dtstart'] = $start;
        $datarray['dtend'] = $finish;
        $datarray['summary'] = $summary;
        $datarray['description'] = $desc;
        $datarray['location'] = $location;
        $datarray['etype'] = $type;
        $datarray['adjust'] = $adjust;
        $datarray['nofinish'] = $nofinish;
        $datarray['uid'] = local_channel();
        $datarray['account'] = get_account_id();
        $datarray['event_xchan'] = $channel['channel_hash'];
        $datarray['allow_cid'] = $ac['allow_cid'];
        $datarray['allow_gid'] = $ac['allow_gid'];
        $datarray['deny_cid'] = $ac['deny_cid'];
        $datarray['deny_gid'] = $ac['deny_gid'];
        $datarray['private'] = (($acl->is_private()) ? 1 : 0);
        $datarray['id'] = $event_id;
        $datarray['created'] = $created;
        $datarray['edited'] = $edited;

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

        if ($share) {
            Run::Summon(['Notifier', 'event', $item_id]);
        }
    }


    public function get()
    {

        if (argc() > 2 && argv(1) == 'ical') {
            $event_id = argv(2);

            require_once('include/security.php');
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
                return '';
            }
        }

        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
            return '';
        }

        $channel = App::get_channel();

        Navbar::set_selected('Events');

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

        $first_day = intval(get_pconfig(local_channel(), 'system', 'cal_first_day', 0));

        $htpl = Theme::get_template('event_head.tpl');
        App::$page['htmlhead'] .= replace_macros($htpl, [
            '$baseurl' => z_root(),
            '$module_url' => '/events',
            '$modparams' => 1,
            '$lang' => App::$language,
            '$first_day' => $first_day
        ]);

        $o = '';

        $mode = 'view';
        $y = 0;
        $m = 0;
        $ignored = ((x($_REQUEST, 'ignored')) ? " and dismissed = " . intval($_REQUEST['ignored']) . " " : '');


        // logger('args: ' . print_r(App::$argv,true));


        if (argc() > 1) {
            if (argc() > 2 && argv(1) === 'add') {
                $mode = 'add';
                $item_id = intval(argv(2));
            }
            if (argc() > 2 && argv(1) === 'drop') {
                $mode = 'drop';
                $event_id = argv(2);
            }
            if (argc() > 2 && intval(argv(1)) && intval(argv(2))) {
                $mode = 'view';
                $y = intval(argv(1));
                $m = intval(argv(2));
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
                if (count($r)) {
                    $orig_event = $r[0];
                }
            }

            $channel = App::get_channel();

            // Passed parameters overrides anything found in the DB
            if (!x($orig_event)) {
                $orig_event = [];
            }

            // In case of an error the browser is redirected back here, with these parameters filled in with the previous values
            /*
            if(x($_REQUEST,'nofinish')) $orig_event['nofinish'] = $_REQUEST['nofinish'];
            if(x($_REQUEST,'adjust')) $orig_event['adjust'] = $_REQUEST['adjust'];
            if(x($_REQUEST,'summary')) $orig_event['summary'] = $_REQUEST['summary'];
            if(x($_REQUEST,'description')) $orig_event['description'] = $_REQUEST['description'];
            if(x($_REQUEST,'location')) $orig_event['location'] = $_REQUEST['location'];
            if(x($_REQUEST,'start')) $orig_event['dtstart'] = $_REQUEST['start'];
            if(x($_REQUEST,'finish')) $orig_event['dtend'] = $_REQUEST['finish'];
            if(x($_REQUEST,'type')) $orig_event['etype'] = $_REQUEST['type'];
            */

            $n_checked = ((x($orig_event) && $orig_event['nofinish']) ? ' checked="checked" ' : '');
            $a_checked = ((x($orig_event) && $orig_event['adjust']) ? ' checked="checked" ' : '');
            $t_orig = ((x($orig_event)) ? $orig_event['summary'] : '');
            $d_orig = ((x($orig_event)) ? $orig_event['description'] : '');
            $l_orig = ((x($orig_event)) ? $orig_event['location'] : '');
            $eid = ((x($orig_event)) ? $orig_event['id'] : 0);
            $event_xchan = ((x($orig_event)) ? $orig_event['event_xchan'] : $channel['channel_hash']);
            $mid = ((x($orig_event)) ? $orig_event['mid'] : '');

            if (!x($orig_event)) {
                $sh_checked = '';
                $a_checked = ' checked="checked" ';
            } else {
                $sh_checked = ((($orig_event['allow_cid'] === '<' . $channel['channel_hash'] . '>' || (!$orig_event['allow_cid'])) && (!$orig_event['allow_gid']) && (!$orig_event['deny_cid']) && (!$orig_event['deny_gid'])) ? '' : ' checked="checked" ');
            }

            if ($orig_event['event_xchan']) {
                $sh_checked .= ' disabled="disabled" ';
            }

            $sdt = ((x($orig_event)) ? $orig_event['dtstart'] : 'now');

            $fdt = ((x($orig_event)) ? $orig_event['dtend'] : '+1 hour');

            $tz = date_default_timezone_get();
            if (x($orig_event)) {
                $tz = (($orig_event['adjust']) ? date_default_timezone_get() : 'UTC');
            }

            $syear = datetime_convert('UTC', $tz, $sdt, 'Y');
            $smonth = datetime_convert('UTC', $tz, $sdt, 'm');
            $sday = datetime_convert('UTC', $tz, $sdt, 'd');
            $shour = datetime_convert('UTC', $tz, $sdt, 'H');
            $sminute = datetime_convert('UTC', $tz, $sdt, 'i');

            $stext = datetime_convert('UTC', $tz, $sdt);
            $stext = substr($stext, 0, 14) . "00:00";

            $fyear = datetime_convert('UTC', $tz, $fdt, 'Y');
            $fmonth = datetime_convert('UTC', $tz, $fdt, 'm');
            $fday = datetime_convert('UTC', $tz, $fdt, 'd');
            $fhour = datetime_convert('UTC', $tz, $fdt, 'H');
            $fminute = datetime_convert('UTC', $tz, $fdt, 'i');

            $ftext = datetime_convert('UTC', $tz, $fdt);
            $ftext = substr($ftext, 0, 14) . "00:00";

            $type = ((x($orig_event)) ? $orig_event['etype'] : 'event');

            $f = get_config('system', 'event_input_format');
            if (!$f) {
                $f = 'ymd';
            }

            $catsenabled = Apps::system_app_installed(local_channel(), 'Categories');

            $category = '';

            if ($catsenabled && x($orig_event)) {
                $itm = q(
                    "select * from item where resource_type = 'event' and resource_id = '%s' and uid = %d limit 1",
                    dbesc($orig_event['event_hash']),
                    intval(local_channel())
                );
                $itm = fetch_post_tags($itm);
                if ($itm) {
                    $cats = get_terms_oftype($itm[0]['term'], TERM_CATEGORY);
                    foreach ($cats as $cat) {
                        if (strlen($category)) {
                            $category .= ', ';
                        }
                        $category .= $cat['term'];
                    }
                }
            }

            $acl = new AccessControl($channel);
            $perm_defaults = $acl->get();

            $permissions = ((x($orig_event)) ? $orig_event : $perm_defaults);

            $freq_options = [
                'DAILY' => t('day(s)'),
                'WEEKLY' => t('week(s)'),
                'MONTHLY' => t('month(s)'),
                'YEARLY' => t('year(s)')
            ];


            $tpl = Theme::get_template('event_form.tpl');

            $form = replace_macros($tpl, [
                '$post' => z_root() . '/events',
                '$eid' => $eid,
                '$type' => $type,
                '$xchan' => $event_xchan,
                '$mid' => $mid,
                '$event_hash' => $event_id,
                '$summary' => ['summary', (($event_id) ? t('Edit event title') : t('Event title')), $t_orig, t('Required'), '*'],
                '$catsenabled' => $catsenabled,
                '$placeholdercategory' => t('Categories (comma-separated list)'),
                '$c_text' => (($event_id) ? t('Edit Category') : t('Category')),
                '$category' => $category,
                '$required' => '<span class="required" title="' . t('Required') . '">*</span>',
                '$s_dsel' => datetimesel($f, new DateTime(), DateTime::createFromFormat('Y', (int) $syear + 5), DateTime::createFromFormat('Y-m-d H:i', "$syear-$smonth-$sday $shour:$sminute"), (($event_id) ? t('Edit start date and time') : t('Start date and time')), 'start_text', true, true, '', '', true, $first_day),
                '$n_text' => t('Finish date and time are not known or not relevant'),
                '$n_checked' => $n_checked,
                '$f_dsel' => datetimesel($f, new DateTime(), DateTime::createFromFormat('Y', (int) $fyear + 5), DateTime::createFromFormat('Y-m-d H:i', "$fyear-$fmonth-$fday $fhour:$fminute"), (($event_id) ? t('Edit finish date and time') : t('Finish date and time')), 'finish_text', true, true, 'start_text', '', false, $first_day),
                '$nofinish' => ['nofinish', t('Finish date and time are not known or not relevant'), $n_checked, '', [t('No'), t('Yes')], 'onclick="enableDisableFinishDate();"'],
                '$adjust' => ['adjust', t('Adjust for viewer timezone'), $a_checked, t('Important for events that happen in a particular place. Not practical for global holidays.'), [t('No'), t('Yes')]],
                '$a_text' => t('Adjust for viewer timezone'),
                '$d_text' => (($event_id) ? t('Edit Description') : t('Description')),
                '$d_orig' => $d_orig,
                '$l_text' => (($event_id) ? t('Edit Location') : t('Location')),
                '$l_orig' => $l_orig,
                '$t_orig' => $t_orig,
                '$preview' => t('Preview'),
                '$perms_label' => t('Permission settings'),
                // populating the acl dialog was a permission description from view_stream because Cal.php, which
                // displays events, says "since we don't currently have an event permission - use the stream permission"
                '$acl' => (($orig_event['event_xchan']) ? '' : Libacl::populate(((x($orig_event)) ? $orig_event : $perm_defaults), false, PermissionDescription::fromGlobalPermission('view_stream'))),

                '$allow_cid' => acl2json($permissions['allow_cid']),
                '$allow_gid' => acl2json($permissions['allow_gid']),
                '$deny_cid' => acl2json($permissions['deny_cid']),
                '$deny_gid' => acl2json($permissions['deny_gid']),
                '$tz_choose' => Features::enabled(local_channel(), 'event_tz_select'),
                '$timezone' => ['timezone_select', t('Timezone:'), date_default_timezone_get(), '', get_timezones()],

                '$lockstate' => (($acl->is_private()) ? 'lock' : 'unlock'),

                '$submit' => t('Submit'),
                '$advanced' => t('Advanced Options'),

                '$repeat' => ['repeat', t('Event repeat'), false, '', [t('No'), t('Yes')]],
                '$freq' => ['freq', t('Repeat frequency'), '', '', $freq_options],
                '$interval' => ['interval', t('Repeat every'), 1, ''],
                '$count' => ['count', t('Number of total repeats'), 10, ''],
                '$until' => '',
                '$byday' => '',

            ]);
            /* end edit/create form */

            $thisyear = datetime_convert('UTC', date_default_timezone_get(), 'now', 'Y');
            $thismonth = datetime_convert('UTC', date_default_timezone_get(), 'now', 'm');
            if (!$y) {
                $y = intval($thisyear);
            }
            if (!$m) {
                $m = intval($thismonth);
            }

            $export = false;
            if (argc() === 4 && argv(3) === 'export') {
                $export = true;
            }

            // Put some limits on dates. The PHP date functions don't seem to do so well before 1900.
            // An upper limit was chosen to keep search engines from exploring links millions of years in the future.

            if ($y < 1901) {
                $y = 1900;
            }
            if ($y > 2099) {
                $y = 2100;
            }

            $nextyear = $y;
            $nextmonth = $m + 1;
            if ($nextmonth > 12) {
                $nextmonth = 1;
                $nextyear++;
            }

            $prevyear = $y;
            if ($m > 1) {
                $prevmonth = $m - 1;
            } else {
                $prevmonth = 12;
                $prevyear--;
            }

            $dim = get_dim($y, $m);
            $start = sprintf('%d-%d-%d %d:%d:%d', $y, $m, 1, 0, 0, 0);
            $finish = sprintf('%d-%d-%d %d:%d:%d', $y, $m, $dim, 23, 59, 59);


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
                    "SELECT event.*, item.plink, item.item_flags, item.author_xchan, item.owner_xchan
	                                from event left join item on resource_id = event_hash where resource_type = 'event' and event.uid = %d and event.id = %d limit 1",
                    intval(local_channel()),
                    intval($_GET['id'])
                );
            } elseif ($export) {
                $r = q(
                    "SELECT * from event where uid = %d
					AND (( adjust = 0 AND ( dtend >= '%s' or nofinish = 1 ) AND dtstart <= '%s' ) 
					OR  (  adjust = 1 AND ( dtend >= '%s' or nofinish = 1 ) AND dtstart <= '%s' )) ",
                    intval(local_channel()),
                    dbesc($start),
                    dbesc($finish),
                    dbesc($adjust_start),
                    dbesc($adjust_finish)
                );
            } else {
                // fixed an issue with "nofinish" events not showing up in the calendar.
                // There's still an issue if the finish date crosses the end of month.
                // Noting this for now - it will need to be fixed here and in Friendica.
                // Ultimately the finish date shouldn't be involved in the query.

                $r = q(
                    "SELECT event.*, item.plink, item.item_flags, item.author_xchan, item.owner_xchan
	                              from event left join item on event_hash = resource_id 
					where resource_type = 'event' and event.uid = %d and event.uid = item.uid $ignored 
					AND (( adjust = 0 AND ( dtend >= '%s' or nofinish = 1 ) AND dtstart <= '%s' ) 
					OR  (  adjust = 1 AND ( dtend >= '%s' or nofinish = 1 ) AND dtstart <= '%s' )) ",
                    intval(local_channel()),
                    dbesc($start),
                    dbesc($finish),
                    dbesc($adjust_start),
                    dbesc($adjust_finish)
                );
            }

            $links = [];

            if ($r && !$export) {
                xchan_query($r);
                $r = fetch_post_tags($r);

                $r = sort_by_date($r);
            }

            if ($r) {
                foreach ($r as $rr) {
                    $j = (($rr['adjust']) ? datetime_convert('UTC', date_default_timezone_get(), $rr['dtstart'], 'j') : datetime_convert('UTC', 'UTC', $rr['dtstart'], 'j'));
                    if (!x($links, $j)) {
                        $links[$j] = z_root() . '/' . App::$cmd . '#link-' . $j;
                    }
                }
            }

            $events = [];

            $last_date = '';
            $fmt = t('l, F j');

            if ($r) {
                foreach ($r as $rr) {
                    $j = (($rr['adjust']) ? datetime_convert('UTC', date_default_timezone_get(), $rr['dtstart'], 'j') : datetime_convert('UTC', 'UTC', $rr['dtstart'], 'j'));
                    $d = (($rr['adjust']) ? datetime_convert('UTC', date_default_timezone_get(), $rr['dtstart'], $fmt) : datetime_convert('UTC', 'UTC', $rr['dtstart'], $fmt));
                    $d = day_translate($d);

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


                    $is_first = ($d !== $last_date);

                    $last_date = $d;

                    $edit = ((local_channel() && $rr['author_xchan'] == get_observer_hash()) ? [z_root() . '/events/' . $rr['event_hash'] . '?expandform=1', t('Edit event'), '', ''] : false);

                    $drop = [z_root() . '/events/drop/' . $rr['event_hash'], t('Delete event'), '', ''];

                    $title = strip_tags(html_entity_decode(zidify_links(bbcode($rr['summary'])), ENT_QUOTES, 'UTF-8'));
                    if (!$title) {
                        list($title, $_trash) = explode("<br", bbcode($rr['desc']), 2);
                        $title = strip_tags(html_entity_decode($title, ENT_QUOTES, 'UTF-8'));
                    }
                    $html = format_event_html($rr);
                    $rr['desc'] = zidify_links(smilies(bbcode($rr['desc'])));
                    $rr['description'] = htmlentities(html2plain(bbcode($rr['description'])), ENT_COMPAT, 'UTF-8', false);
                    $rr['location'] = zidify_links(smilies(bbcode($rr['location'])));
                    $events[] = [
                        'id' => $rr['id'],
                        'hash' => $rr['event_hash'],
                        'start' => $start,
                        'end' => $end,
                        'drop' => $drop,
                        'allDay' => false,
                        'title' => $title,

                        'j' => $j,
                        'd' => $d,
                        'edit' => $edit,
                        'is_first' => $is_first,
                        'item' => $rr,
                        'html' => $html,
                        'plink' => [zid($rr['plink']), t('Link to Source'), '', ''],
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
                echo json_encode($events);
                killme();
            }

            // links: array('href', 'text', 'extra css classes', 'title')
            if (x($_GET, 'id')) {
                $tpl = Theme::get_template("event.tpl");
            } else {
                $tpl = Theme::get_template("events-js.tpl");
            }

            $o = replace_macros($tpl, [
                '$baseurl' => z_root(),
                '$new_event' => [z_root() . '/events', (($event_id) ? t('Edit Event') : t('Create Event')), '', ''],
                '$previousmonth' => [z_root() . "/events/$prevyear/$prevmonth", t('Previous'), '', ''],
                '$nextmonth' => [z_root() . "/events/$nextyear/$nextmonth", t('Next'), '', ''],
                '$export' => [z_root() . "/events/$y/$m/export", t('Export'), '', ''],
                '$calendar' => cal($y, $m, $links, ' eventcal'),
                '$events' => $events,
                '$view_label' => t('View'),
                '$month' => t('Month'),
                '$week' => t('Week'),
                '$day' => t('Day'),
                '$prev' => t('Previous'),
                '$next' => t('Next'),
                '$today' => t('Today'),
                '$form' => $form,
                '$expandform' => ((x($_GET, 'expandform')) ? true : false),
            ]);

            if (x($_GET, 'id')) {
                echo $o;
                killme();
            }

            return $o;
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
                    $i = q(
                        "select * from item where resource_type = 'event' and resource_id = '%s' and uid = %d",
                        dbesc($event_id),
                        intval(local_channel())
                    );

                    if ($i) {
                        $can_delete = false;
                        $local_delete = true;

                        $ob_hash = get_observer_hash();
                        if ($ob_hash && ($ob_hash === $i[0]['author_xchan'] || $ob_hash === $i[0]['owner_xchan'] || $ob_hash === $i[0]['source_xchan'])) {
                            $can_delete = true;
                        }

                        // The site admin can delete any post/item on the site.
                        // If the item originated on this site+channel the deletion will propagate downstream.
                        // Otherwise just the local copy is removed.

                        if (is_site_admin()) {
                            $local_delete = true;
                            if (intval($i[0]['item_origin'])) {
                                $can_delete = true;
                            }
                        }

                        if ($can_delete || $local_delete) {
                            // if this is a different page type or it's just a local delete
                            // but not by the item author or owner, do a simple deletion

                            $complex = false;

                            if (intval($i[0]['item_type']) || ($local_delete && (!$can_delete))) {
                                drop_item($i[0]['id']);
                            } else {
                                // complex deletion that needs to propagate and be performed in phases
                                drop_item($i[0]['id'], DROPITEM_PHASE1);
                                $complex = true;
                            }

                            $ii = q(
                                "select * from item where id = %d",
                                intval($i[0]['id'])
                            );
                            if ($ii) {
                                xchan_query($ii);
                                $sync_item = fetch_post_tags($ii);
                                Libsync::build_sync_packet($i[0]['uid'], ['item' => [encode_item($sync_item[0], true)]]);
                            }

                            if ($complex) {
                                tag_deliver($i[0]['uid'], $i[0]['id']);
                                if (intval($i[0]['item_wall']) && $complex) {
                                    Run::Summon(['Notifier', 'drop', $i[0]['id']]);
                                }
                            }
                        }
                    }

                    $r = q(
                        "update item set resource_type = '', resource_id = '' where resource_type = 'event' and resource_id = '%s' and uid = %d",
                        dbesc($event_id),
                        intval(local_channel())
                    );
                    $sync_event['event_deleted'] = 1;
                    Libsync::build_sync_packet(0, ['event' => [$sync_event]]);

                    info(t('Event removed') . EOL);
                } else {
                    notice(t('Failed to remove event') . EOL);
                }
                goaway(z_root() . '/events');
            }
        }
    }
}
