<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\Libsync;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Navbar;

class Poke extends Controller
{

    public function init()
    {

        $uid = local_channel();
        if (!$uid) {
            return;
        }

        if (!Apps::system_app_installed($uid, 'Poke')) {
            return;
        }
        $channel = App::get_channel();

        $verbs = get_poke_verbs();

        if (isset($_REQUEST['pokeverb']) && array_key_exists(trim($_REQUEST['pokeverb']), $verbs)) {
            set_pconfig($uid, 'system', 'pokeverb', trim($_REQUEST['pokeverb']));
            return;
        }

        $verb = get_pconfig($uid, 'system', 'pokeverb', 'poke');

        if (!array_key_exists($verb, $verbs)) {
            return;
        }

        $activity = $verbs[$verb][0];

        $xchan = trim($_REQUEST['xchan']);

        if (!$xchan) {
            return;
        }

        $r = q(
            "SELECT * FROM xchan where xchan_hash = '%s' LIMIT 1",
            dbesc($xchan)
        );

        if (!$r) {
            logger('poke: no target.');
            return;
        }

        $target = $r[0];
        $parent_item = null;

        $item_private = 1;

        if ($target) {
            $allow_cid = '<' . $target['abook_xchan'] . '>';
            $allow_gid = EMPTY_STR;
            $deny_cid = EMPTY_STR;
            $deny_gid = EMPTY_STR;
        }

        $arr = [];

        $arr['item_wall'] = 1;
        $arr['owner_xchan'] = $channel['channel_hash'];
        $arr['author_xchan'] = $channel['channel_hash'];
        $arr['allow_cid'] = $allow_cid;
        $arr['allow_gid'] = $allow_gid;
        $arr['deny_cid'] = $deny_cid;
        $arr['deny_gid'] = $deny_gid;
        $arr['verb'] = 'Create';
        $arr['item_private'] = 1;
        $arr['obj_type'] = 'Note';
        $arr['body'] = '[zrl=' . $channel['xchan_url'] . ']' . $channel['xchan_name'] . '[/zrl]' . ' ' . $verbs[$verb][2] . ' ' . '[zrl=' . $target['xchan_url'] . ']' . $target['xchan_name'] . '[/zrl]';


        $arr['item_origin'] = 1;
        $arr['item_wall'] = 1;

        $obj = Activity::encode_item($arr, ((get_config('system', 'activitypub', ACTIVITYPUB_ENABLED)) ? true : false));

        $i = post_activity_item($arr);

        if ($i['success']) {
            $item_id = $i['item_id'];
            $r = q(
                "select * from item where id = %d",
                intval($item_id)
            );
            if ($r) {
                xchan_query($r);
                $sync_item = fetch_post_tags($r);
                Libsync::build_sync_packet($uid, ['item' => [encode_item($sync_item[0], true)]]);
            }

            info(sprintf(t('You %1$s %2$s'), $verbs[$verb][2], $target['xchan_name']));
        }

        json_return_and_die(['success' => true]);
    }


    public function get()
    {

        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        if (!Apps::system_app_installed(local_channel(), 'Poke')) {
            $o = '<b>' . t('Poke App (Not Installed)') . '</b><br>';
            $o .= t('Poke or do something else to somebody');
            return $o;
        }

        Navbar::set_selected('Poke');

        $name = '';
        $id = '';

        $verbs = get_poke_verbs();

        $shortlist = [];
        $current = get_pconfig(local_channel(), 'system', 'pokeverb', 'poke');
        foreach ($verbs as $k => $v) {
            $shortlist[] = [$k, $v[1], (($k === $current) ? true : false)];
        }


        $title = t('Poke');
        $desc = t('Poke, prod or do other things to somebody');

        $o = replace_macros(get_markup_template('poke_content.tpl'), array(
            '$title' => $title,
            '$desc' => $desc,
            '$clabel' => t('Recipient'),
            '$choice' => t('Choose your default action'),
            '$verbs' => $shortlist,
            '$submit' => t('Submit'),
            '$id' => $id
        ));

        return $o;
    }
}
