<?php

namespace Code\Lib;

use App;
use Code\Lib\PConfig;
use Code\Lib\Channel;

class MastAPI
{

    public static function format_channel($channel)
    {
        $p = q(
            "select * from profile where uid = %d and is_default = 1",
            intval($channel['channel_id'])
        );

        $a = q(
            "select * from account where account_id = %d",
            intval($channel['channel_account_id'])
        );

        $followers = q(
            "select count(xchan_hash) as total from xchan left join abconfig on abconfig.xchan = xchan_hash left join abook on abook_xchan = xchan_hash where abook_channel = %d and abconfig.chan = %d and abconfig.cat = 'system' and abconfig.k = 'their_perms' and abconfig.v like '%%send_stream%%' and xchan_hash != '%s' and xchan_orphan = 0 and xchan_deleted = 0 and abook_hidden = 0 and abook_pending = 0 and abook_self = 0 ",
            intval($channel['channel_id']),
            intval($channel['channel_id']),
            dbesc($channel['channel_hash'])
        );

        $following = q(
            "select count(xchan_hash) as total from xchan left join abconfig on abconfig.xchan = xchan_hash left join abook on abook_xchan = xchan_hash where abook_channel = %d and abconfig.chan = %d and abconfig.cat = 'system' and abconfig.k = 'my_perms' and abconfig.v like '%%send_stream%%' and xchan_hash != '%s' and xchan_orphan = 0 and xchan_deleted = 0 and abook_hidden = 0 and abook_pending = 0 and abook_self = 0",
            intval($channel['channel_id']),
            intval($channel['channel_id']),
            dbesc($channel['channel_hash'])
        );

        $cover_photo = Channel::get_cover_photo($channel['channel_id'], 'array');

        $item_normal = item_normal();

        // count posts/comments
        $statuses = q(
            "SELECT COUNT(id) as total FROM item
            WHERE uid = %d
            AND author_xchan = '%s' $item_normal ",
            intval($channel['channel_id']),
            dbesc($channel['channel_hash'])
        );

        $ret = [];
        $ret['id'] = (string)$channel['channel_id'];
        $ret['username'] = $channel['channel_address'];
        $ret['acct'] = $channel['channel_address'];
        $ret['display_name'] = $channel['channel_name'];
        $ret['locked'] = ((intval(PConfig::Get($channel['channel_id'], 'system', 'autoperms'))) ? false : true);
        $ret['discoverable'] = ((1 - intval($channel['xchan_hidden'])) ? true : false);
        $ret['created_at'] = datetime_convert('UTC', 'UTC', $a[0]['account_created'], ATOM_TIME);
        $ret['note'] = bbcode($p[0]['about'], ['export' => true]);
        $ret['url'] = Channel::url($channel);
        $ret['avatar'] = $channel['xchan_photo_l'];
        $ret['avatar_static'] = $channel['xchan_photo_l'];
        if ($cover_photo) {
            $ret['header'] = $cover_photo['url'];
            $ret['header_static'] = $cover_photo['url'];
        }
        $ret['followers_count'] = intval($followers[0]['total']);
        $ret['following_count'] = intval($following[0]['total']);
        $ret['statuses_count'] = intval($statuses[0]['total']);
        $ret['last_status_at'] = datetime_convert('UTC', 'UTC', $channel['lastpost'], ATOM_TIME);


        return $ret;
    }

    public static function format_site()
    {

        $register = intval(get_config('system', 'register_policy'));

        $u = q("select count(channel_id) as total from channel where channel_removed = 0");
        $i = q("select count(id) as total from item where item_origin = 1");
        $s = q("select count(site_url) as total from site");

        $admins = q("select * from channel left join account on account_id = channel_account_id where ( account_roles & 4096 ) > 0 and account_default_channel = channel_id");
        $adminsx = Channel::from_id($admins[0]['channel_id']);


        $ret = [];
        $ret['uri'] = z_root();
        $ret['title'] = System::get_site_name();
        $ret['description'] = bbcode(get_config('system', 'siteinfo', ''), ['export' => true]);
        $ret['email'] = get_config('system', 'admin_email');
        $ret['version'] = System::get_project_version();
        $ret['registrations'] = (($register) ? true : false);
        $ret['approval_required'] = (($register === REGISTER_APPROVE) ? true : false);
        $ret['invites_enabled'] = false;
        $ret['urls'] = [];
        $ret['stats'] = [
            'user_count' => intval($u[0]['total']),
            'status_count' => intval($i[0]['total']),
            'domain_count' => intval($s[0]['total']),
        ];

        $ret['contact_account'] = self::format_channel($adminsx);

        return $ret;
    }
}
