<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Config;
use Zotlabs\Lib\LDSignatures;
use Zotlabs\Web\HTTPSig;

require_once('include/security.php');
require_once('include/attach.php');
require_once('include/photo_factory.php');
require_once('include/photos.php');


class Album extends Controller {

	function init() {
	

		if (ActivityStreams::is_as_request()) {

			$sigdata = HTTPSig::verify(EMPTY_STR);
			if ($sigdata['portable_id'] && $sigdata['header_valid']) {
				$portable_id = $sigdata['portable_id'];
				if (! check_channelallowed($portable_id)) {
					http_status_exit(403, 'Permission denied');
				}
				if (! check_siteallowed($sigdata['signer'])) {
					http_status_exit(403, 'Permission denied');
				}
				observer_auth($portable_id);
			}
			elseif (Config::get('system','require_authenticated_fetch',false)) {
				http_status_exit(403,'Permission denied');
			}

			$observer_xchan = get_observer_hash();
			$allowed = false;

			$bear = Activity::token_from_request();
			if ($bear) {
				logger('bear: ' . $bear, LOGGER_DEBUG);
			}

			$r = q("select * from attach where is_dir = 1 and hash = '%s' limit 1",
				dbesc(argv(1))
			);
			if ($r) {
				$allowed = attach_can_view($r[0]['uid'],$observer_xchan,argv(1),$bear);
			}
			if (! $allowed) {
				http_status_exit(404,'Permission denied.');
			}

			$channel = channelx_by_n($r[0]['uid']);

			$sql_extra = permissions_sql($r[0]['uid'],$observer_xchan);

			$x = q("select * from attach where folder = '%s' and uid = %d $sql_extra",
				dbesc($r[0]['hash']),
				intval($r[0]['uid'])
			);

			$contents = [];

			if ($x) {
				foreach ($x as $xv) {
					if (intval($xv['is_dir'])) {
						continue;
					}
					if (intval($xv['is_photo'])) {
						$contents[] = z_root() . '/photo/' . $xv['hash'];
					}
				}
			}
			
			$obj = Activity::encode_simple_collection($contents, App::$query_string, 'OrderedCollection', count($contents));
			as_return_and_die($obj,$channel);

		}

	}
}