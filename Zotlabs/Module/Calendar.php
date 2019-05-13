<?php
namespace Zotlabs\Module;

use Zotlabs\Web\Controller;

require_once('include/conversation.php');
require_once('include/bbcode.php');
require_once('include/datetime.php');
require_once('include/event.php');
require_once('include/items.php');
require_once('include/html2plain.php');

class Calendar extends Controller {

	function post() {
	
		logger('post: ' . print_r($_REQUEST,true), LOGGER_DATA);
	
		if(! local_channel())
			return;
	
		$event_id = ((x($_POST,'event_id')) ? intval($_POST['event_id']) : 0);
		$event_hash = ((x($_POST,'event_hash')) ? $_POST['event_hash'] : '');
	
		$xchan = ((x($_POST,'xchan')) ? dbesc($_POST['xchan']) : '');
		$uid      = local_channel();
	
		$start_text = escape_tags($_REQUEST['dtstart']);
		$finish_text = escape_tags($_REQUEST['dtend']);

		$adjust   = intval($_POST['adjust']);
		$nofinish = intval($_POST['nofinish']);
	
		$timezone = ((x($_POST,'timezone_select')) ? notags(trim($_POST['timezone_select']))     : '');

		$tz = (($timezone) ? $timezone : date_default_timezone_get());

		$categories = escape_tags(trim($_POST['categories']));
	
		// only allow editing your own events. 
	
		if(($xchan) && ($xchan !== get_observer_hash()))
			return;
	
		if($start_text) {
			$start = $start_text;
		}
		else {
			$start = sprintf('%d-%d-%d %d:%d:0',$startyear,$startmonth,$startday,$starthour,$startminute);
		}

		if($finish_text) {
			$finish = $finish_text;
		}
		else {
			$finish = sprintf('%d-%d-%d %d:%d:0',$finishyear,$finishmonth,$finishday,$finishhour,$finishminute);
		}

		if($nofinish) {
			$finish = NULL_DATE;
		}

		if($adjust) {
			$start = datetime_convert($tz,'UTC',$start);
			if(! $nofinish)
				$finish = datetime_convert($tz,'UTC',$finish);
		}
		else {
			$start = datetime_convert('UTC','UTC',$start);
			if(! $nofinish)
				$finish = datetime_convert('UTC','UTC',$finish);
		}


		// Don't allow the event to finish before it begins.
		// It won't hurt anything, but somebody will file a bug report
		// and we'll waste a bunch of time responding to it. Time that 
		// could've been spent doing something else. 
	
		$summary  = escape_tags(trim($_POST['summary']));
		$desc     = escape_tags(trim($_POST['desc']));
		$location = escape_tags(trim($_POST['location']));
		$type     = escape_tags(trim($_POST['type']));

		require_once('include/text.php');
		linkify_tags($desc, local_channel());
		linkify_tags($location, local_channel());
	
		if(strcmp($finish,$start) < 0 && !$nofinish) {
			notice( t('Event can not end before it has started.') . EOL);
			if(intval($_REQUEST['preview'])) {
				echo( t('Unable to generate preview.'));
			}
			killme();
		}
	
		if((! $summary) || (! $start)) {
			notice( t('Event title and start time are required.') . EOL);
			if(intval($_REQUEST['preview'])) {
				echo( t('Unable to generate preview.'));
			}
			killme();
		}

		$channel = \App::get_channel();
	
		$acl = new \Zotlabs\Access\AccessList(false);
	
		if($event_id) {
			$x = q("select * from event where id = %d and uid = %d limit 1",
				intval($event_id),
				intval(local_channel())
			);
			if(! $x) {
				notice( t('Event not found.') . EOL);
				if(intval($_REQUEST['preview'])) {
					echo( t('Unable to generate preview.'));
					killme();
				}
				return;
			}
	
			$acl->set($x[0]);
	
			$created = $x[0]['created'];
			$edited = datetime_convert();
		}
		else {
			$created = $edited = datetime_convert();
			$acl->set_from_array($_POST);
		}
	
		$post_tags = array();
		$channel = \App::get_channel();
		$ac = $acl->get();
	
		if(strlen($categories)) {
			$cats = explode(',',$categories);
			foreach($cats as $cat) {
				$post_tags[] = array(
					'uid'   => $profile_uid, 
					'ttype' => TERM_CATEGORY,
					'otype' => TERM_OBJ_POST,
					'term'  => trim($cat),
					'url'   => $channel['xchan_url'] . '?f=&cat=' . urlencode(trim($cat))
				);
			}
		}
	
		$datarray = array();
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
	
		if(intval($_REQUEST['preview'])) {
			$html = format_event_html($datarray);
			echo $html;
			killme();
		}
	
		$event = event_store_event($datarray);
	
		if($post_tags)	
			$datarray['term'] = $post_tags;
	
		$item_id = event_store_item($datarray,$event);
	
		if($item_id) {
			$r = q("select * from item where id = %d",
				intval($item_id)
			);
			if($r) {
				xchan_query($r);
				$sync_item = fetch_post_tags($r);
				$z = q("select * from event where event_hash = '%s' and uid = %d limit 1",
					dbesc($r[0]['resource_id']),
					intval($channel['channel_id'])
				);
				if($z) {
					build_sync_packet($channel['channel_id'],array('event_item' => array(encode_item($sync_item[0],true)),'event' => $z));
				}
			}
		}
	
		\Zotlabs\Daemon\Master::Summon(array('Notifier','event',$item_id));

		killme();
	
	}
	
	
	
	function get() {
	
		if(argc() > 2 && argv(1) == 'ical') {
			$event_id = argv(2);
	
			require_once('include/security.php');
			$sql_extra = permissions_sql(local_channel());
	
			$r = q("select * from event where event_hash = '%s' $sql_extra limit 1",
				dbesc($event_id)
			);
			if($r) { 
				header('Content-type: text/calendar');
				header('content-disposition: attachment; filename="' . t('event') . '-' . $event_id . '.ics"' );
				echo ical_wrapper($r);
				killme();
			}
			else {
				notice( t('Event not found.') . EOL );
				return;
			}
		}
	
		if(! local_channel()) {
			notice( t('Permission denied.') . EOL);
			return;
		}

		if((argc() > 2) && (argv(1) === 'ignore') && intval(argv(2))) {
			$r = q("update event set dismissed = 1 where id = %d and uid = %d",
				intval(argv(2)),
				intval(local_channel())
			);
		}
	
		if((argc() > 2) && (argv(1) === 'unignore') && intval(argv(2))) {
			$r = q("update event set dismissed = 0 where id = %d and uid = %d",
				intval(argv(2)),
				intval(local_channel())
			);
		}

		$channel = \App::get_channel();
	
		$mode = 'view';
		$export = false;
		//$y = 0;
		//$m = 0;
		$ignored = ((x($_REQUEST,'ignored')) ? " and dismissed = " . intval($_REQUEST['ignored']) . " "  : '');

		if(argc() > 1) {
			if(argc() > 2 && argv(1) === 'add') {
				$mode = 'add';
				$item_id = intval(argv(2));
			}
			if(argc() > 2 && argv(1) === 'drop') {
				$mode = 'drop';
				$event_id = argv(2);
			}
			if(argc() <= 2 && argv(1) === 'export') {
				$export = true;
			}
			if(argc() > 2 && intval(argv(1)) && intval(argv(2))) {
				$mode = 'view';
				//$y = intval(argv(1));
				//$m = intval(argv(2));
			}
			if(argc() <= 2) {
				$mode = 'view';
				$event_id = argv(1);
			}
		}
	
		if($mode === 'add') {
			event_addtocal($item_id,local_channel());
			killme();
		}
	
		if($mode == 'view') {
	
			/* edit/create form */
			if($event_id) {
				$r = q("SELECT * FROM event WHERE event_hash = '%s' AND uid = %d LIMIT 1",
					dbesc($event_id),
					intval(local_channel())
				);
				if(count($r))
					$orig_event = $r[0];
			}
	
			$channel = \App::get_channel();

/*	
			// Passed parameters overrides anything found in the DB
			if(!x($orig_event))
				$orig_event = array();
	
			$n_checked = ((x($orig_event) && $orig_event['nofinish']) ? ' checked="checked" ' : '');
			$a_checked = ((x($orig_event) && $orig_event['adjust']) ? ' checked="checked" ' : '');
			$t_orig = ((x($orig_event)) ? $orig_event['summary'] : '');
			$d_orig = ((x($orig_event)) ? $orig_event['description'] : '');
			$l_orig = ((x($orig_event)) ? $orig_event['location'] : '');
			$eid = ((x($orig_event)) ? $orig_event['id'] : 0);
			$event_xchan = ((x($orig_event)) ? $orig_event['event_xchan'] : $channel['channel_hash']);
			$mid = ((x($orig_event)) ? $orig_event['mid'] : '');
	
			$sdt = ((x($orig_event)) ? $orig_event['dtstart'] : 'now');
	
			$fdt = ((x($orig_event)) ? $orig_event['dtend'] : '+1 hour');
	
			$tz = date_default_timezone_get();
			if(x($orig_event))
				$tz = (($orig_event['adjust']) ? date_default_timezone_get() : 'UTC');
	
			$syear = datetime_convert('UTC', $tz, $sdt, 'Y');
			$smonth = datetime_convert('UTC', $tz, $sdt, 'm');
			$sday = datetime_convert('UTC', $tz, $sdt, 'd');
			$shour = datetime_convert('UTC', $tz, $sdt, 'H');
			$sminute = datetime_convert('UTC', $tz, $sdt, 'i');
	
			$stext = datetime_convert('UTC',$tz,$sdt);
			$stext = substr($stext,0,14) . "00:00";
	
			$fyear = datetime_convert('UTC', $tz, $fdt, 'Y');
			$fmonth = datetime_convert('UTC', $tz, $fdt, 'm');
			$fday = datetime_convert('UTC', $tz, $fdt, 'd');
			$fhour = datetime_convert('UTC', $tz, $fdt, 'H');
			$fminute = datetime_convert('UTC', $tz, $fdt, 'i');
	
			$ftext = datetime_convert('UTC',$tz,$fdt);
			$ftext = substr($ftext,0,14) . "00:00";
	
			$type = ((x($orig_event)) ? $orig_event['etype'] : 'event');
	
			$f = get_config('system','event_input_format');
			if(! $f)
				$f = 'ymd';

			$thisyear = datetime_convert('UTC',date_default_timezone_get(),'now','Y');
			$thismonth = datetime_convert('UTC',date_default_timezone_get(),'now','m');
			if(! $y)
				$y = intval($thisyear);
			if(! $m)
				$m = intval($thismonth);
	

			// Put some limits on dates. The PHP date functions don't seem to do so well before 1900.
			// An upper limit was chosen to keep search engines from exploring links millions of years in the future. 
	
			if($y < 1901)
				$y = 1900;
			if($y > 2099)
				$y = 2100;
	
			$nextyear = $y;
			$nextmonth = $m + 1;
			if($nextmonth > 12) {
					$nextmonth = 1;
				$nextyear ++;
			}
	
			$prevyear = $y;
			if($m > 1)
				$prevmonth = $m - 1;
			else {
				$prevmonth = 12;
				$prevyear --;
			}
				
			$dim    = get_dim($y,$m);
			$start  = sprintf('%d-%d-%d %d:%d:%d',$y,$m,1,0,0,0);
			$finish = sprintf('%d-%d-%d %d:%d:%d',$y,$m,$dim,23,59,59);
*/	
	
			if (argv(1) === 'json'){
				if (x($_GET,'start'))	$start = $_GET['start'];
				if (x($_GET,'end'))	$finish = $_GET['end'];
			}
	
			$start  = datetime_convert('UTC','UTC',$start);
			$finish = datetime_convert('UTC','UTC',$finish);
	
			$adjust_start = datetime_convert('UTC', date_default_timezone_get(), $start);
			$adjust_finish = datetime_convert('UTC', date_default_timezone_get(), $finish);
	
			if (x($_GET,'id')){
			  	$r = q("SELECT event.*, item.plink, item.item_flags, item.author_xchan, item.owner_xchan, item.id as item_id
	                                from event left join item on item.resource_id = event.event_hash
					where item.resource_type = 'event' and event.uid = %d and event.id = %d limit 1",
					intval(local_channel()),
					intval($_GET['id'])
				);
			}
			elseif($export) {
				$r = q("SELECT * from event where uid = %d",
					intval(local_channel())
				);
			}
			else {
				// fixed an issue with "nofinish" events not showing up in the calendar.
				// There's still an issue if the finish date crosses the end of month.
				// Noting this for now - it will need to be fixed here and in Friendica.
				// Ultimately the finish date shouldn't be involved in the query. 

				$r = q("SELECT event.*, item.plink, item.item_flags, item.author_xchan, item.owner_xchan, item.id as item_id
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
	
			//$links = [];
	
			if($r && ! $export) {
				xchan_query($r);
				$r = fetch_post_tags($r,true);

				$r = sort_by_date($r);
			}

/*	
			if($r) {
				foreach($r as $rr) {
					$j = (($rr['adjust']) ? datetime_convert('UTC',date_default_timezone_get(),$rr['dtstart'], 'j') : datetime_convert('UTC','UTC',$rr['dtstart'],'j'));
					if(! x($links,$j)) 
						$links[$j] = z_root() . '/' . \App::$cmd . '#link-' . $j;
				}
			}
*/
	
			$events = [];
	
			//$last_date = '';
			//$fmt = t('l, F j');
	
			if($r) {
	
				foreach($r as $rr) {
					//$j = (($rr['adjust']) ? datetime_convert('UTC',date_default_timezone_get(),$rr['dtstart'], 'j') : datetime_convert('UTC','UTC',$rr['dtstart'],'j'));
					//$d = (($rr['adjust']) ? datetime_convert('UTC',date_default_timezone_get(),$rr['dtstart'], $fmt) : datetime_convert('UTC','UTC',$rr['dtstart'],$fmt));
					//$d = day_translate($d);
					
					$start = (($rr['adjust']) ? datetime_convert('UTC',date_default_timezone_get(),$rr['dtstart'], 'c') : datetime_convert('UTC','UTC',$rr['dtstart'],'c'));
					if ($rr['nofinish']){
						$end = null;
					} else {
						$end = (($rr['adjust']) ? datetime_convert('UTC',date_default_timezone_get(),$rr['dtend'], 'c') : datetime_convert('UTC','UTC',$rr['dtend'],'c'));

						// give a fake end to birthdays so they get crammed into a 
						// single day on the calendar

						if($rr['etype'] === 'birthday')
							$end = null;
					}

					$catsenabled = feature_enabled(local_channel(),'categories');
					$categories = '';
					if($catsenabled){
						if($rr['term']) {
							$cats = get_terms_oftype($rr['term'], TERM_CATEGORY);
							foreach ($cats as $cat) {
								if(strlen($categories))
									$categories .= ', ';
								$categories .= $cat['term'];
							}
						}
					}

					$allDay = false;

					// allDay event rules
					if(!strpos($start, 'T') && !strpos($end, 'T'))
						$allDay = true;
					if(strpos($start, 'T00:00:00') && strpos($end, 'T00:00:00'))
						$allDay = true;

					//$is_first = ($d !== $last_date);
						
					//$last_date = $d;
	
					$edit = ((local_channel() && $rr['author_xchan'] == get_observer_hash()) ? array(z_root().'/events/'.$rr['event_hash'].'?expandform=1',t('Edit event'),'','') : false);
	
					$drop = array(z_root().'/events/drop/'.$rr['event_hash'],t('Delete event'),'','');
	

					$events[] = [
						'calendar_id' => 'calendar',
						'rw'          => true,
						'id'          => $rr['id'],
						'uri'         => $rr['event_hash'],
						'start'       => $start,
						'end'         => $end,
						'drop'        => $drop,
						'allDay'      => $allDay,
						'title'       => htmlentities($rr['summary'], ENT_COMPAT, 'UTF-8'),
						'editable'    => $edit ? true : false,
						'item'        => $rr,
						'plink'       => [ $rr['plink'], t('Link to source') ],
						'description' => htmlentities($rr['description'], ENT_COMPAT, 'UTF-8'),
						'location'    => htmlentities($rr['location'], ENT_COMPAT, 'UTF-8'),
						'allow_cid'   => expand_acl($rr['allow_cid']),
						'allow_gid'   => expand_acl($rr['allow_gid']),
						'deny_cid'    => expand_acl($rr['deny_cid']),
						'deny_gid'    => expand_acl($rr['deny_gid']),
						'categories'  => $categories
					];
				}
			}
			
			if($export) {
				header('Content-type: text/calendar');
				header('content-disposition: attachment; filename="' . t('calendar') . '-' . $channel['channel_address'] . '.ics"' );
				echo ical_wrapper($r);
				killme();
			}
	
			if (\App::$argv[1] === 'json'){
				json_return_and_die($events);
			}
		}

	
		if($mode === 'drop' && $event_id) {
			$r = q("SELECT * FROM event WHERE event_hash = '%s' AND uid = %d LIMIT 1",
				dbesc($event_id),
				intval(local_channel())
			);
	
			$sync_event = $r[0];
	
			if($r) {
				$r = q("delete from event where event_hash = '%s' and uid = %d",
					dbesc($event_id),
					intval(local_channel())
				);
				if($r) {
					$r = q("update item set resource_type = '', resource_id = '' where resource_type = 'event' and resource_id = '%s' and uid = %d",
						dbesc($event_id),
						intval(local_channel())
					);
					$sync_event['event_deleted'] = 1;
					build_sync_packet(0,array('event' => array($sync_event)));
					killme();
				}
				notice( t('Failed to remove event' ) . EOL);
				killme();
			}
		}
	
	}
	
}
