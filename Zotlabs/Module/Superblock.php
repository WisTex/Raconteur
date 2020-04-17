<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\LibBlock;

class Superblock extends Controller {

	function init() {
	
		if (! local_channel()) {
			return;
		}

		$type = BLOCKTYPE_CHANNEL;
		$blocked = trim($_GET['block']);
		if (! $blocked) {
			$blocked = trim($_GET['blocksite']);
			if ($blocked) {
				$type = BLOCKTYPE_SERVER;				
			}
		}
		
		$handled = false;
		$ignored = [];

		if ($blocked) {
			$handled = true;
			$r = q("select xchan_url from xchan where xchan_hash = '%s' limit 1",
				dbesc($blocked)
			);
			if ($r) {
				if ($type === BLOCKTYPE_SERVER) {
					$m = parse_url($r[0]['xchan_url']);
					if ($m) {
						$blocked = $m['host'];
					}
				}

				$bl = [
					'block_channel_id' => local_channel(),
					'block_entity' => $blocked,
					'block_type' => $type,
					'block_comment' => t('Added by Superblock')
				];
				
				LibBlock::store($bl);

				$sync = LibBlock::fetch_by_entity(local_channel(),trim($_GET['block']));
				
				$z = q("insert into xign ( uid, xchan ) values ( %d , '%s' ) ",
					intval(local_channel()),
					dbesc(trim($_GET['block']))
				);
				
				$ignored = [ 'uid' => local_channel(), 'xchan' => $_GET['block'] ];
				Libsync::build_sync_packet(0, [ 'xign' => [ $ignored ], 'block' => $sync ] );
			}
		}
		if (array_key_exists('unblock',$_GET) && trim($_GET['unblock'])) {
			$handled = true;
			if (check_form_security_token('superblock','sectok')) {
				$r = LibBlock::fetch_by_entity(local_channel(), trim($_GET['unblock']));
				if ($r) {
					LibBlock::remove(local_channel(), trim($_GET['unblock']));
					$z = q("delete from xign where uid = %d  and xchan = '%s' ",
						intval(local_channel()),
						dbesc($_GET['block'])
					);
					$ignored = [ 'uid' => local_channel(), 'xchan' => $_GET['block'], 'deleted' => true ];
					$r['deleted'] = true;
					Libsync::build_sync_packet(0, [ 'xign' => [ $ignored ], 'block' => $r ] );
				}
			}
		}

		if ($handled) {

			info( t('superblock settings updated') . EOL );

			if ($_GET['unblock']) {
				return;
			}
		
			killme();
		}

	}

	function get() {

		$l = LibBlock::fetch(local_channel(),BLOCKTYPE_CHANNEL);
		$list = ids_to_array($l,'block_entity');

		stringify_array_elms($list,true);
		$query_str = implode(',',$list);
		if ($query_str) {
			$r = q("select * from xchan where xchan_hash in ( " . $query_str . " ) ");
		}
		else {
			$r = [];
		}
		if ($r) {
			for ($x = 0; $x < count($r); $x ++) {
				$r[$x]['encoded_hash'] = urlencode($r[$x]['xchan_hash']);
			}
		}

		$sc .= replace_macros(get_markup_template('superblock_list.tpl'), [
			'$blocked' => t('Currently blocked'),
			'$entries' => $r,
			'$nothing' => (($r) ? '' : t('No channels currently blocked')),
			'$token'   => get_form_security_token('superblock'),
			'$remove'  => t('Remove')
		]);

		$s .= replace_macros(get_markup_template('generic_app_settings.tpl'), [
			'$addon' 	=> array('superblock', t('Superblock Settings'), '', t('Submit')),
			'$content'	=> $sc
		]);

		return $s;

	}
}