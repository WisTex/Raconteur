<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Enotify;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\PConfig;

require_once('include/bbcode.php');

/**
 * @brief Ping Controller.
 * Called from the client at regular intervals to check for updates from the server
 *
 */

class Ping extends Controller {

	/**
	 * @brief do several updates when pinged.
	 *
	 * This function does several tasks. Whenever called it checks for new messages,
	 * introductions, notifications, etc. and returns a json with the results.
	 *
	 * @result JSON
	 */

	function init() {

		$result = [];
		$notifs = [];

		$result['notify'] = 0;
		$result['home'] = 0;
		$result['stream'] = 0;
		$result['intros'] = 0;
		$result['register'] = 0;
		$result['moderate'] = 0;
		$result['events'] = 0;
		$result['events_today'] = 0;
		$result['birthdays'] = 0;
		$result['birthdays_today'] = 0;
		$result['all_events'] = 0;
		$result['all_events_today'] = 0;
		$result['pubs'] = 0;
		$result['files'] = 0;
		$result['forums'] = 0;
		$result['forums_sub'] = [];
		$result['reports'] = 0;
		
		$vnotify = false;
		$evdays = 3;

		$my_activity = 0;

		$item_normal = item_normal();

		if (local_channel()) {
			$vnotify = get_pconfig(local_channel(),'system','vnotify');
			$evdays = intval(get_pconfig(local_channel(),'system','evdays'));
			$ob_hash = get_observer_hash();
		}

		// if unset show all visual notification types
		if ($vnotify === false) {
			$vnotify = (-1);
		}
		if ($evdays < 1) {
			$evdays = 3;
		}

		/**
		 * If you have several windows open to this site and switch to a different channel
		 * in one of them, the others may get into a confused state showing you a page or options
		 * on that page which were only valid under the old identity. You session has changed.
		 * Therefore we send a notification of this fact back to the browser where it is picked up
		 * in javascript and which reloads the page it is on so that it is valid under the context
		 * of the now current channel.
		 */

		$result['invalid'] = ((isset($_GET['uid']) && intval($_GET['uid'])) && (intval($_GET['uid']) != local_channel()) ? 1 : 0);

		// If we're currently installing, there won't be a populated database.
		// So just send back what we have and stop here.
		
		if (App::$install) {
			json_return_and_die($result);
		}

		/**
		 * Update chat presence indication (if applicable)
		 */

		if (get_observer_hash() && (! $result['invalid'])) {
			$r = q("select cp_id, cp_room from chatpresence where cp_xchan = '%s' and cp_client = '%s' and cp_room = 0 limit 1",
				dbesc(get_observer_hash()),
				dbesc($_SERVER['REMOTE_ADDR'])
			);
			$basic_presence = false;
			if ($r) {
				$basic_presence = true;
				q("update chatpresence set cp_last = '%s' where cp_id = %d",
					dbesc(datetime_convert()),
					intval($r[0]['cp_id'])
				);
			}
			if (! $basic_presence) {
				q("insert into chatpresence ( cp_xchan, cp_last, cp_status, cp_client)
					values( '%s', '%s', '%s', '%s' ) ",
					dbesc(get_observer_hash()),
					dbesc(datetime_convert()),
					dbesc('online'),
					dbesc($_SERVER['REMOTE_ADDR'])
				);
			}
		}

		/**
		 * Chatpresence continued... if somebody hasn't pinged recently, they've most likely left the page
		 * and shouldn't count as online anymore. We allow an expection for bots.
		 */

		q("delete from chatpresence where cp_last < %s - INTERVAL %s and cp_client != 'auto' ",
			db_utcnow(), db_quoteinterval('3 MINUTE')
		);


		$sql_extra = '';

		if (! ($vnotify & VNOTIFY_LIKE)) {
			$sql_extra = " AND verb NOT IN ('" . dbesc(ACTIVITY_LIKE) . "', '" . dbesc(ACTIVITY_DISLIKE) . "') ";
		}

		$discover_tab_on = can_view_public_stream();

		$notify_pubs = ((local_channel()) ? ($vnotify & VNOTIFY_PUBS) && $discover_tab_on : $discover_tab_on);

		if ($notify_pubs && local_channel() && ! Apps::system_app_installed(local_channel(),'Public Stream')) {
			$notify_pubs = false;
		}

		$sys = get_sys_channel();

		$seenstr = EMPTY_STR;


		if (local_channel()) {
			$seen = PConfig::Get(local_channel(),'system','seen_items',[]);
			if ($seen) {
				$seenstr = " and not item.id in (" . implode(',',$seen) . ") ";
			}
		}

		$loadtime = get_loadtime('pubstream');

		if ($notify_pubs) {
			$pubs = q("SELECT id, author_xchan from item
				WHERE uid = %d
				AND created > '%s'
				$seenstr
				$item_normal
				$sql_extra",
				intval($sys['channel_id']),
				dbesc($loadtime)
			);

			if ($pubs) {
				foreach($pubs as $p) {
					if ($p['author_xchan'] === get_observer_hash()) {
						$my_activity ++;
					}
					else {
						$result['pubs'] ++;
					}
				}
			}
		}
		
		if ((argc() > 1) && (argv(1) === 'pubs') && ($notify_pubs)) {

			$local_result = [];

			$r = q("SELECT * FROM item
				WHERE uid = %d
				AND author_xchan != '%s'
				AND created > '%s'
				$seenstr
				$item_normal
				$sql_extra
				ORDER BY created DESC
				LIMIT 300",
				intval($sys['channel_id']),
				dbesc(get_observer_hash()),
				dbesc($loadtime)
			);

			if ($r) {
				xchan_query($r);
				foreach ($r as $rr) {
					$rr['llink'] = str_replace('display/', 'pubstream/?f=&mid=', $rr['llink']);
					$z = Enotify::format($rr);
					if ($z) {
						$local_result[] = $z;
					}
				}
			}

			json_return_and_die( [ 'notify' => $local_result ] );
		}

		if ((! local_channel()) || ($result['invalid'])) {
			json_return_and_die($result);
		}

		/**
		 * Everything following is only permitted under the context of a locally authenticated site member.
		 */

		/**
		 * Handle "mark all xyz notifications read" requests.
		 */

		// mark all items read
		if (x($_REQUEST, 'markRead') && local_channel() && (! $_SESSION['sudo'])) {
			switch ($_REQUEST['markRead']) {
				case 'stream':
					$r = q("UPDATE item SET item_unseen = 0 WHERE uid = %d AND item_unseen = 1",
						intval(local_channel())
					);
					$_SESSION['loadtime_stream'] = datetime_convert();
					PConfig::Set(local_channel(),'system','loadtime_stream',$_SESSION['loadtime_stream']);
					$_SESSION['loadtime_channel'] = datetime_convert();
					PConfig::Set(local_channel(),'system','loadtime_channel',$_SESSION['loadtime_channel']);
					break;
				case 'home':
					$r = q("UPDATE item SET item_unseen = 0 WHERE uid = %d AND item_unseen = 1 AND item_wall = 1",
						intval(local_channel())
					);
					$_SESSION['loadtime_channel'] = datetime_convert();
					PConfig::Set(local_channel(),'system','loadtime_channel',$_SESSION['loadtime_channel']);
					break;
				case 'all_events':
					$r = q("UPDATE event SET dismissed = 1 WHERE uid = %d AND dismissed = 0 AND dtstart < '%s' AND dtstart > '%s' ",
						intval(local_channel()),
						dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now + ' . intval($evdays) . ' days')),
						dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now - 1 days'))
					);
					break;
				case 'notify':
					$r = q("update notify set seen = 1 where uid = %d",
						intval(local_channel())
					);
					break;
				case 'pubs':

					$_SESSION['loadtime_pubstream'] = datetime_convert();
					PConfig::Set(local_channel(),'system','loadtime_pubstream',$_SESSION['loadtime_pubstream']);
					break;
				default:
					break;
			}
		}

		if (x($_REQUEST, 'markItemRead') && local_channel() && (! $_SESSION['sudo'])) {
			$r = q("UPDATE item SET item_unseen = 0 WHERE  uid = %d AND parent = %d",
				intval(local_channel()),
				intval($_REQUEST['markItemRead'])
			);
			$id = intval($_REQUEST['markItemRead']);
			$seen = PConfig::Get(local_channel(),'system','seen_items',[]);
			if (! in_array($id,$seen)) {
				$seen[] = $id;
			}
			PConfig::Set(local_channel(),'system','seen_items',$seen);
		}

		/**
		 * URL ping/something will return detail for "something", e.g. a json list with which to populate a notification
		 * dropdown menu.
		 */
		 
		if (argc() > 1 && argv(1) === 'notify') {

			$t = q("SELECT * FROM notify WHERE uid = %d AND seen = 0 ORDER BY CREATED DESC",
				intval(local_channel())
			);

			if ($t) {
				foreach ($t as $tt) {
					$message = trim(strip_tags(bbcode($tt['msg'])));

					if (strpos($message, $tt['xname']) === 0)
						$message = substr($message, strlen($tt['xname']) + 1);


					$mid = basename($tt['link']);
					$mid = unpack_link_id($mid);

					if (in_array($tt['verb'], [ACTIVITY_LIKE, ACTIVITY_DISLIKE])) {
						// we need the thread parent
						$r = q("select thr_parent from item where mid = '%s' and uid = %d limit 1",
							dbesc($mid),
							intval(local_channel())
						);

						$b64mid = ((strpos($r[0]['thr_parent'], 'b64.') === 0) ? $r[0]['thr_parent'] : gen_link_id($r[0]['thr_parent']));
					}
					else {
						$b64mid = ((strpos($mid, 'b64.') === 0) ? $mid : gen_link_id($mid));
					}

					$notifs[] = array(
						'notify_link' => z_root() . '/notify/view/' . $tt['id'],
						'name' => $tt['xname'],
						'url' => $tt['url'],
						'photo' => $tt['photo'],
						'when' => relative_date($tt['created']),
						'hclass' => (($tt['seen']) ? 'notify-seen' : 'notify-unseen'),
						'b64mid' => (($tt['otype'] == 'item') ? $b64mid : 'undefined'),
						'notify_id' => (($tt['otype'] == 'item') ? $tt['id'] : 'undefined'),
						'message' => $message
					);
				}
			}

			json_return_and_die( [ 'notify' => $notifs ] );
		}

		if (argc() > 1 && (argv(1) === 'stream')) {
			$local_result = [];

			$item_normal_moderate = $item_normal;
			$loadtime = get_loadtime('stream');
			
			$r = q("SELECT * FROM item 
				WHERE uid = %d
				AND author_xchan != '%s'
				AND changed > '%s'
				$seenstr
				$item_normal_moderate
				$sql_extra
				ORDER BY created DESC
				LIMIT 300",
				intval(local_channel()),
				dbesc($ob_hash),
				dbesc($loadtime)
			);
			if ($r) {
				xchan_query($r);
				foreach ($r as $item) {
					$z = Enotify::format($item);

					if($z) {
						$local_result[] = $z;
					}
				}
			}

			json_return_and_die( [ 'notify' => $local_result ] );
		}

		if (argc() > 1 && (argv(1) === 'home')) {
			$local_result = [];
			$item_normal_moderate = $item_normal;

			$sql_extra .= " and item_wall = 1 ";
			$item_normal_moderate = item_normal_moderate();

			$loadtime = get_loadtime('channel');

			$r = q("SELECT * FROM item 
				WHERE uid = %d
				AND author_xchan != '%s'
				AND changed > '%s'
				$seenstr
				$item_normal_moderate
				$sql_extra
				ORDER BY created DESC
				LIMIT 300",
				intval(local_channel()),
				dbesc($ob_hash),
				dbesc($loadtime)
			);
			if ($r) {
				xchan_query($r);
				foreach ($r as $item) {
					$z = Enotify::format($item);

					if($z) {
						$local_result[] = $z;
					}
				}
			}

			json_return_and_die( [ 'notify' => $local_result ] );
		}



		if (argc() > 1 && (argv(1) === 'intros')) {
			$local_result = [];

			$r = q("SELECT * FROM abook left join xchan on abook.abook_xchan = xchan.xchan_hash where abook_channel = %d and abook_pending = 1 and abook_self = 0 and abook_ignored = 0 and xchan_deleted = 0 and xchan_orphan = 0 ORDER BY abook_created DESC LIMIT 50",
				intval(local_channel())
			);

			if ($r) {
				foreach ($r as $rr) {
					$local_result[] = [
						'notify_link' => z_root() . '/connections/' . $rr['abook_id'],
						'name'        => $rr['xchan_name'],
						'addr'        => $rr['xchan_addr'],
						'url'         => $rr['xchan_url'],
						'photo'       => $rr['xchan_photo_s'],
						'when'        => relative_date($rr['abook_created']),
						'hclass'      => ('notify-unseen'),
						'message'     => t('added your channel')
					];
				}
			}

			json_return_and_die( [ 'notify' => $local_result ] );
		}

		if( (argc() > 1 && (argv(1) === 'register')) && is_site_admin()) {
			$result = [];

			$r = q("SELECT account_email, account_created from account where (account_flags & %d) > 0",
				intval(ACCOUNT_PENDING)
			);
			if ($r) {
				foreach ($r as $rr) {
					$result[] = array(
						'notify_link' => z_root() . '/admin/accounts',
						'name' => $rr['account_email'],
						'addr' => $rr['account_email'],
						'url' => '',
						'photo' => z_root() . '/' . get_default_profile_photo(48),
						'when' => relative_date($rr['account_created']),
						'hclass' => ('notify-unseen'),
						'message' => t('requires approval')
					);
				}
			}

			json_return_and_die( [ 'notify' => $result ] );
		}

		if (argc() > 1 && (argv(1) === 'all_events')) {
			$bd_format = t('g A l F d') ; // 8 AM Friday January 18

			$result = [];

			$r = q("SELECT * FROM event left join xchan on event_xchan = xchan_hash
				WHERE event.uid = %d AND dtstart < '%s' AND dtstart > '%s' and dismissed = 0
				and etype in ( 'event', 'birthday' )
				ORDER BY dtstart DESC LIMIT 1000",
				intval(local_channel()),
				dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now + ' . intval($evdays) . ' days')),
				dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now - 1 days'))
			);

			if ($r) {
				foreach ($r as $rr) {

					$strt = datetime_convert('UTC', (($rr['adjust']) ? date_default_timezone_get() : 'UTC'), $rr['dtstart']);
					$today = ((substr($strt, 0, 10) === datetime_convert('UTC', date_default_timezone_get(), 'now', 'Y-m-d')) ? true : false);
					$when = day_translate(datetime_convert('UTC', (($rr['adjust']) ? date_default_timezone_get() : 'UTC'), $rr['dtstart'], $bd_format)) . (($today) ?  ' ' . t('[today]') : '');

					$result[] = array(
						'notify_link' => z_root() . '/events', /// @FIXME this takes you to an edit page and it may not be yours, we really want to just view the single event  --> '/events/event/' . $rr['event_hash'],
						'name'        => $rr['xchan_name'],
						'addr'        => $rr['xchan_addr'],
						'url'         => $rr['xchan_url'],
						'photo'       => $rr['xchan_photo_s'],
						'when'        => $when,
						'hclass'       => ('notify-unseen'),
						'message'     => t('posted an event')
					);
				}
			}

			json_return_and_die( [ 'notify' => $result ] );
		}

		if (argc() > 1 && (argv(1) === 'files')) {
			$result = [];

			$r = q("SELECT item.created, xchan.xchan_name, xchan.xchan_addr, xchan.xchan_url, xchan.xchan_photo_s FROM item 
				LEFT JOIN xchan on author_xchan = xchan_hash
				WHERE item.verb = '%s'
				AND item.obj_type = '%s'
				AND item.uid = %d
				AND item.owner_xchan != '%s'
				AND item.item_unseen = 1",
				dbesc(ACTIVITY_POST),
				dbesc(ACTIVITY_OBJ_FILE),
				intval(local_channel()),
				dbesc($ob_hash)
			);
			if ($r) {
				foreach ($r as $rr) {
					$result[] = array(
						'notify_link' => z_root() . '/sharedwithme',
						'name' => $rr['xchan_name'],
						'addr' => $rr['xchan_addr'],
						'url' => $rr['xchan_url'],
						'photo' => $rr['xchan_photo_s'],
						'when' => relative_date($rr['created']),
						'hclass' => ('notify-unseen'),
						'message' => t('shared a file with you')
					);
				}
			}

			json_return_and_die( [ 'notify' => $result ] );
		}

		if (argc() > 1 && (argv(1) === 'reports') && is_site_admin()) {

			$local_result = [];

			$r = q("SELECT item.created, xchan.xchan_name, xchan.xchan_addr, xchan.xchan_url, xchan.xchan_photo_s FROM item 
				LEFT JOIN xchan on author_xchan = xchan_hash
				WHERE item.type = '%s' AND item.item_unseen = 1",
				dbesc(ITEM_TYPE_REPORT)
			);

			if ($r) {
				foreach ($r as $rv) {
					$result[] = [
						'notify_link' => z_root() . '/reports',
						'name'        => $rv['xchan_name'],
						'addr'        => $rv['xchan_addr'],
						'url'         => $rv['xchan_url'],
						'photo'       => $rv['xchan_photo_s'],
						'when'        => relative_date($rv['created']),
						'hclass'      => ('notify-unseen'),
						'message'     => t('reported content')
					];
				}
			}

			json_return_and_die( [ 'notify' => $result ] );
		}




		/**
		 * Normal ping - just the counts, no detail
		 */

		
		if ($vnotify & VNOTIFY_SYSTEM) {
			$t = q("select count(*) as total from notify where uid = %d and seen = 0",
				intval(local_channel())
			);
			if ($t)
				$result['notify'] = intval($t[0]['total']);
		}

		if ($vnotify & VNOTIFY_FILES) {
			$files = q("SELECT count(id) as total FROM item
				WHERE verb = '%s'
				AND obj_type = '%s'
				AND uid = %d
				AND owner_xchan != '%s'
				AND item_unseen = 1",
				dbesc(ACTIVITY_POST),
				dbesc(ACTIVITY_OBJ_FILE),
				intval(local_channel()),
				dbesc($ob_hash)
			);
			if ($files)
				$result['files'] = intval($files[0]['total']);
		}


		if ($vnotify & VNOTIFY_NETWORK) {
			$loadtime = get_loadtime('stream');
			$r = q("SELECT id, author_xchan FROM item 
				WHERE uid = %d and changed > '%s' 
				$seenstr
				$item_normal
				$sql_extra ",
				intval(local_channel()),
				dbesc($loadtime)
			);

			if($r) {
				$arr = array('items' => $r);
				call_hooks('network_ping', $arr);

				foreach ($r as $it) {
					if ($it['author_xchan'] === $ob_hash) {
						$my_activity ++;
					}
					else {
						$result['stream'] ++;
					}
				}
			}
		}
		if (! ($vnotify & VNOTIFY_NETWORK)) {
			$result['stream'] = 0;
		}

		if ($vnotify & VNOTIFY_CHANNEL) {
			$loadtime = get_loadtime('channel');
			$r = q("SELECT id, author_xchan FROM item 
				WHERE item_wall = 1 and uid = %d and changed > '%s'
				$seenstr 
				$item_normal
				$sql_extra ",
				intval(local_channel()),
				dbesc($loadtime)
			);

			if ($r) {
				foreach ($r as $it) {
					if ($it['author_xchan'] === $ob_hash) {
						$my_activity ++;
					}
					else {
						$result['home'] ++;
					}
				}
			}
		}
		if (! ($vnotify & VNOTIFY_CHANNEL)) {
			$result['home'] = 0;
		}


		if ($vnotify & VNOTIFY_INTRO) {
			$intr = q("SELECT COUNT(abook.abook_id) AS total FROM abook left join xchan on abook.abook_xchan = xchan.xchan_hash where abook_channel = %d and abook_pending = 1 and abook_self = 0 and abook_ignored = 0 and xchan_deleted = 0 and xchan_orphan = 0 ",
				intval(local_channel())
			);

			if ($intr)
				$result['intros'] = intval($intr[0]['total']);
		}


		$channel = App::get_channel();

		if ($vnotify & VNOTIFY_REGISTER) {
			if (App::$config['system']['register_policy'] == REGISTER_APPROVE && is_site_admin()) {
				$regs = q("SELECT count(account_id) as total from account where (account_flags & %d) > 0",
					intval(ACCOUNT_PENDING)
				);
				if ($regs)
					$result['register'] = intval($regs[0]['total']);
			}
		}

		if ($vnotify & VNOTIFY_REPORTS) {
			if (is_site_admin()) {
				$reps = q("SELECT count(id) as total from item where item_type = %d",
					intval(ITEM_TYPE_REPORT)
				);
				if ($reps)
					$result['reports'] = intval($reps[0]['total']);
			}
		}

		if ($vnotify & (VNOTIFY_EVENT|VNOTIFY_EVENTTODAY|VNOTIFY_BIRTHDAY)) {
			$events = q("SELECT etype, dtstart, adjust FROM event
				WHERE event.uid = %d AND dtstart < '%s' AND dtstart > '%s' and dismissed = 0
				and etype in ( 'event', 'birthday' )
				ORDER BY dtstart ASC ",
					intval(local_channel()),
					dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now + ' . intval($evdays) . ' days')),
					dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now - 1 days'))
			);

			if ($events) {
				$result['all_events'] = count($events);

				if ($result['all_events']) {
					$str_now = datetime_convert('UTC', date_default_timezone_get(), 'now', 'Y-m-d');
					foreach ($events as $x) {
						$bd = false;
						if ($x['etype'] === 'birthday') {
							$result['birthdays'] ++;
							$bd = true;
						}
						else {
							$result['events'] ++;
						}
						if (datetime_convert('UTC', ((intval($x['adjust'])) ? date_default_timezone_get() : 'UTC'), $x['dtstart'], 'Y-m-d') === $str_now) {
							$result['all_events_today'] ++;
							if($bd)
								$result['birthdays_today'] ++;
							else
								$result['events_today'] ++;
						}
					}
				}
			}
		}

		if (! ($vnotify & VNOTIFY_EVENT))
			$result['all_events'] = $result['events'] = 0;
		if (! ($vnotify & VNOTIFY_EVENTTODAY))
			$result['all_events_today'] = $result['events_today'] = 0;
		if (! ($vnotify & VNOTIFY_BIRTHDAY))
			$result['birthdays'] = 0;



		if ($vnotify & VNOTIFY_FORUMS) {
			$forums = get_forum_channels(local_channel());

			if ($forums) {

				$perms_sql = item_permissions_sql(local_channel()) . item_normal();
				$fcount = count($forums);
				$forums['total'] = 0;

				for ($x = 0; $x < $fcount; $x ++) {
					$ttype = TERM_FORUM;
					$p = q("SELECT oid AS parent FROM term WHERE uid = " . intval(local_channel()) . " AND ttype = $ttype AND term = '" . protect_sprintf(dbesc($forums[$x]['xchan_name'])) . "'");
	
					$p = ids_to_querystr($p, 'parent');	
					$pquery = (($p) ? "OR parent IN ( $p )" : '');

					$r = q("select sum(item_unseen) as unseen from item 
						where uid = %d and ( owner_xchan = '%s' $pquery ) and item_unseen = 1 $perms_sql ",
						intval(local_channel()),
						dbesc($forums[$x]['xchan_hash'])
					);
					if ($r[0]['unseen']) {
						$forums[$x]['notify_link'] = (($forums[$x]['private_forum']) ? $forums[$x]['xchan_url'] : z_root() . '/stream/?f=&pf=1&cid=' . $forums[$x]['abook_id']);
						$forums[$x]['name'] = $forums[$x]['xchan_name'];
						$forums[$x]['addr'] = $forums[$x]['xchan_addr'];
						$forums[$x]['url'] = $forums[$x]['xchan_url'];
						$forums[$x]['photo'] = $forums[$x]['xchan_photo_s'];
						$forums[$x]['unseen'] = $r[0]['unseen'];
						$forums[$x]['private_forum'] = (($forums[$x]['private_forum']) ? 'lock' : '');
						$forums[$x]['message'] = (($forums[$x]['private_forum']) ? t('Private group') : t('Public group'));

						$forums['total'] = $forums['total'] + $r[0]['unseen'];

						unset($forums[$x]['abook_id']);
						unset($forums[$x]['xchan_hash']);
						unset($forums[$x]['xchan_name']);
						unset($forums[$x]['xchan_url']);
						unset($forums[$x]['xchan_photo_s']);

					}
					else {
						unset($forums[$x]);
					}
				}
				$result['forums'] = $forums['total'];
				unset($forums['total']);

				$result['forums_sub'] = $forums;
			}
		}

		// Mark all of the stream notifications seen if all three of them are caught up.
		// This also resets the pconfig storage for items_seen
		
		if ((! $my_activity) && (! (intval($result['home']) + intval($result['stream']) + intval($result['pubs'])))) {
			PConfig::Delete(local_channel(),'system','seen_items');

			$_SESSION['loadtime_channel']   = datetime_convert();
			$_SESSION['loadtime_stream']    = datetime_convert();
			$_SESSION['loadtime_pubstream'] = datetime_convert();
			
			PConfig::Set(local_channel(),'system','loadtime_channel',   $_SESSION['loadtime_channel']);
			PConfig::Set(local_channel(),'system','loadtime_stream',    $_SESSION['loadtime_stream']);
			PConfig::Set(local_channel(),'system','loadtime_pubstream', $_SESSION['loadtime_pubstream']);
		}

		json_return_and_die($result);
	}

}
