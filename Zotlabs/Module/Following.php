<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\LDSignatures;
use Zotlabs\Lib\Activity;
use Zotlabs\Web\HTTPSig;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libprofile;
use Zotlabs\Lib\Channel;
use Zotlabs\Render\Theme;


class Following extends Controller
{

	private $results = [];

    public function init()
    {

        if (observer_prohibited(true)) {
            http_status_exit(403, 'Forbidden');
        }

        if (argc() < 2) {
            http_status_exit(404, 'Not found');
        }

        $channel = Channel::from_username(argv(1));
        if (!$channel) {
            http_status_exit(404, 'Not found');
        }

        Libprofile::load(argv(1));

        $observer_hash = get_observer_hash();

        if (((!(is_array(App::$profile) && count(App::$profile))) || (App::$profile['hide_friends']))) {
            http_status_exit(403, 'Forbidden');
        }

        if (!perm_is_allowed($channel['channel_id'], $observer_hash, 'view_contacts')) {
            http_status_exit(403, 'Forbidden');
        }

        $t = q(
            "select count(xchan_hash) as total from xchan left join abconfig on abconfig.xchan = xchan_hash left join abook on abook_xchan = xchan_hash where abook_channel = %d and abconfig.chan = %d and abconfig.cat = 'system' and abconfig.k = 'my_perms' and abconfig.v like '%%send_stream%%' and xchan_hash != '%s' and xchan_orphan = 0 and xchan_deleted = 0 and abook_hidden = 0 and abook_pending = 0 and abook_self = 0",
            intval($channel['channel_id']),
            intval($channel['channel_id']),
            dbesc($channel['channel_hash'])
        );

        if ($t) {
            App::set_pager_total($t[0]['total']);
            App::set_pager_itemspage(100);
        }

        if (App::$pager['unset'] && $t[0]['total'] > 100) {
            $ret = Activity::paged_collection_init($t[0]['total'], App::$query_string);
        } else {
            $pager_sql = sprintf(" LIMIT %d OFFSET %d ", intval(App::$pager['itemspage']), intval(App::$pager['start']));

            $r = q(
                "select * from xchan left join abconfig on abconfig.xchan = xchan_hash left join abook on abook_xchan = xchan_hash where abook_channel = %d and abconfig.chan = %d and abconfig.cat = 'system' and abconfig.k = 'my_perms' and abconfig.v like '%%send_stream%%' and xchan_hash != '%s' and xchan_orphan = 0 and xchan_deleted = 0 and abook_hidden = 0 and abook_pending = 0 and abook_self = 0 $pager_sql",
                intval($channel['channel_id']),
                intval($channel['channel_id']),
                dbesc($channel['channel_hash'])
            );

			$this->results = $r;
			
            $ret = Activity::encode_follow_collection($r, App::$query_string, 'OrderedCollection', $t[0]['total']);
        }

        if (ActivityStreams::is_as_request()) {
            as_return_and_die($ret, $channel);
        }
    }

	function get() {

		if ($this->results) {
            foreach ($this->results as $member) {
                $members[] = micropro($member, true, 'mpgroup', 'card');
            }
        }
        $o = replace_macros(Theme::get_template('listmembers.tpl'), [
            '$title' => t('List members'),
            '$members' => $members
        ]);
        return $o;
	}

}
