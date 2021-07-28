<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Daemon\Run;

class Dreport extends Controller {

	function get() {
	
		if (! local_channel()) {
			notice( t('Permission denied') . EOL);
			return;
		}
	
		$table = 'item';
	
		$channel = App::get_channel();

		if (argc() > 2) {
			$cmd = argv(1);
			$mid = argv(2);
		}
		elseif (argc() > 1) {
			$cmd = EMPTY_STR;
			$mid = argv(1);
		}

		if (strpos($mid,'b64.') === 0) {
			$message_id = @base64url_decode(substr($mid,4));
		}

		if (! $message_id) {
			notice( t('Invalid message') . EOL);
			return;
		}
		
		if($cmd === 'push') {
			$i = q("select id from item where mid = '%s' and uid = %d and ( author_xchan = '%s' or ( owner_xchan = '%s' and item_wall = 1 )) ",
				dbesc($message_id),
				intval($channel['channel_id']),
				dbesc($channel['channel_hash']),
				dbesc($channel['channel_hash'])
			);
			if ($i) {
					Run::Summon([ 'Notifier', 'edit_post', $i[0]['id'] ]);
			}
			sleep(3);
			goaway(z_root() . '/dreport/' . urlencode($mid));
		}

		if ($cmd === 'log') {
			$host = escape_tags($_REQUEST['host']);
			$r = q("select * from dreport where dreport_xchan = '%s' and dreport_mid = '%s' and dreport_recip = '%s'",
				dbesc($channel['channel_hash']),
				dbesc($message_id),
				dbesc($host)
			);
			if ($r) {
				$output = '<h3>' . t('Delivery Log') . '</h3>';
				$output .= EOL . $r[0]['dreport_log'];
				return $output;
			}
			else {
				notice( t('Item not found') . EOL);
				return;
			}
		}
		
		switch ($table) {
			case 'item':
				$i = q("select id from item where mid = '%s' and ( author_xchan = '%s' or ( owner_xchan = '%s' and item_wall = 1 )) ",
					dbesc($message_id),
					dbesc($channel['channel_hash']),
					dbesc($channel['channel_hash'])
				);
				break;
			default:
				break;
		}
	
		if(! $i) {
			notice( t('Permission denied') . EOL);
			return;
		}
		
		$r = q("select * from dreport where dreport_xchan = '%s' and dreport_mid = '%s'",
			dbesc($channel['channel_hash']),
			dbesc($message_id)
		);
	
		if (! $r) {
			notice( t('no results') . EOL);
		}
		
		for($x = 0; $x < count($r); $x++ ) {
	
			// This has two purposes: 1. make the delivery report strings translateable, and
			// 2. assign an ordering to item delivery results so we can group them and provide
			// a readable report with more interesting events listed toward the top and lesser
			// interesting items towards the bottom
	
			switch($r[$x]['dreport_result']) {
				case 'channel sync processed':
					$r[$x]['gravity'] = 0;
					$r[$x]['dreport_result'] = t('channel sync processed');
					break;
				case 'queued':
					$r[$x]['gravity'] = 2;
					$r[$x]['dreport_result'] = '<a href="' . z_root() . '/dreport/log/' . $mid . '?host=' . urlencode($r[$x]['dreport_recip']) . '" >' . t('queued') . '</a>';
					break;
				case 'site dead':
					$r[$x]['gravity'] = 3;
					$r[$x]['dreport_result'] = t('site dead');
					break;
				case 'site deferred':
					$r[$x]['gravity'] = 3;
					$r[$x]['dreport_result'] = t('site might be dead - deferred');
					break;
				case 'posted':
					$r[$x]['gravity'] = 3;
					$r[$x]['dreport_result'] = t('posted');
					break;
				case 'accepted for delivery':
					$r[$x]['gravity'] = 4;
					$r[$x]['dreport_result'] = t('accepted for delivery');
					break;
				case 'updated':
					$r[$x]['gravity'] = 5;
					$r[$x]['dreport_result'] = t('updated');
				case 'update ignored':
					$r[$x]['gravity'] = 6;
					$r[$x]['dreport_result'] = t('update ignored');
					break;
				case 'permission denied':
					$r[$x]['dreport_result'] = t('permission denied');
					$r[$x]['gravity'] = 6;
					break;
				case 'recipient not found':
					$r[$x]['dreport_result'] = t('recipient not found');
					break;
				case 'mail recalled':
					$r[$x]['dreport_result'] = t('mail recalled');
					break;
				case 'duplicate mail received':
					$r[$x]['dreport_result'] = t('duplicate mail received');
					break;
				case 'mail delivered':
					$r[$x]['dreport_result'] = t('mail delivered');
					break;
				default:
					if (strpos($r[$x]['dreport_result'],'delivery rejected') === 0) {
						$r[$x]['dreport_result'] = t('delivery rejected') . ' ' . substr($r[$x]['dreport_result'],17);
					}
					$r[$x]['gravity'] = 1;
					break;
			}
		}
	
		usort($r,'self::dreport_gravity_sort');

		$entries = [];
		foreach($r as $rr) {
			$entries[] = [ 
				'name' => escape_tags($rr['dreport_name'] ?: $rr['dreport_recip']),					
				'result' => $rr['dreport_result'],
				'time' => escape_tags(datetime_convert('UTC',date_default_timezone_get(),$rr['dreport_time']))
			];
		}

		$output = replace_macros(get_markup_template('dreport.tpl'), array(
			'$title' => sprintf( t('Delivery report for %1$s'),basename($message_id)) . '...',
			'$table' => $table,
			'$mid' => urlencode($mid),
			'$options' => t('Options'),
			'$push' => t('Redeliver'),
			'$entries' => $entries
		));
	
	
		return $output;
	
	
	
	}
	
	private static function dreport_gravity_sort($a,$b) {
		if($a['gravity'] == $b['gravity']) {
			if($a['dreport_name'] === $b['dreport_name'])
				return strcmp($a['dreport_time'],$b['dreport_time']);
			return strcmp($a['dreport_name'],$b['dreport_name']);
		}
		return (($a['gravity'] > $b['gravity']) ? 1 : (-1));
	}
	
}
