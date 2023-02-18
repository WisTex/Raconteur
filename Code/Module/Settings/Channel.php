<?php

namespace Code\Module\Settings;

use App;
use Code\Lib\Apps;
use Code\Lib\Libsync;
use Code\Lib\AccessList;
use Code\Lib as Zlib;
use Code\Access\Permissions;
use Code\Access\PermissionRoles;
use Code\Access\PermissionLimits;
use Code\Lib\PermissionDescription;
use Code\Access\AccessControl;
use Code\Daemon\Run;
use Code\Lib\Permcat;
use Code\Lib\PConfig;
use Code\Lib\Libacl;
use Code\Lib\Features;
use Code\Lib\Menu;
use Code\Extend\Hook;
use Code\Render\Theme;


class Channel
{

    protected $autoperms;
    protected $publish = 0;

    public function post()
    {
        $channel = App::get_channel();

        check_form_security_token_redirectOnErr('/settings', 'settings');

        Hook::call('settings_post', $_POST);

        $role = ((x($_POST, 'permissions_role')) ? notags(trim($_POST['permissions_role'])) : '');
        $oldrole = get_pconfig(local_channel(), 'system', 'permissions_role');

        $forbidden_roles = ['collection', 'collection_restricted'];
        if (in_array($role, $forbidden_roles) || in_array($oldrole, $forbidden_roles)) {
            $role = $oldrole;
        }

        if (($role !== $oldrole) || ($role === 'custom')) {
            $this->change_permissions_role($channel, $role);
        }

        // The post_comments permission is critical to privacy, so we always allow you to set it, no matter what
        // permission role is in place.

        $post_comments = array_key_exists('post_comments', $_POST) ? intval($_POST['post_comments']) : PERMS_SPECIFIC;
        PermissionLimits::Set(local_channel(), 'post_comments', $post_comments);

        $post_mail = array_key_exists('post_mail', $_POST) ? intval($_POST['post_mail']) : PERMS_SPECIFIC;
        PermissionLimits::Set(local_channel(), 'post_mail', $post_mail);

        $search_stream = array_key_exists('search_stream', $_POST) ? intval($_POST['search_stream']) : PERMS_SPECIFIC;
        PermissionLimits::Set(local_channel(), 'search_stream', $search_stream);


        $default_view_contacts = ($role === 'social_restricted') ? PERMS_SPECIFIC : PERMS_PUBLIC;  
        $view_contacts = array_key_exists('view_contacts', $_POST) ? intval($_POST['view_contacts']) : $default_view_contacts;
        PermissionLimits::Set(local_channel(), 'view_contacts', $view_contacts);

        $this->publish = (((x($_POST, 'profile_in_directory')) && (intval($_POST['profile_in_directory']) == 1)) ? 1 : 0);
        $channel_name = ((x($_POST, 'channel_name')) ? escape_tags(trim($_POST['channel_name'])) : '');
        $timezone = ((x($_POST, 'timezone_select')) ? notags(trim($_POST['timezone_select'])) : '');
        $defloc = ((x($_POST, 'defloc')) ? notags(trim($_POST['defloc'])) : '');
        $maxreq = ((x($_POST, 'maxreq')) ? intval($_POST['maxreq']) : 0);
        $expire = ((x($_POST, 'expire')) ? intval($_POST['expire']) : 0);
        $evdays = ((x($_POST, 'evdays')) ? intval($_POST['evdays']) : 3);
        $photo_path = ((x($_POST, 'photo_path')) ? escape_tags(trim($_POST['photo_path'])) : '');
        $attach_path = ((x($_POST, 'attach_path')) ? escape_tags(trim($_POST['attach_path'])) : '');
        $noindex = ((x($_POST, 'noindex')) ? intval($_POST['noindex']) : 0);
        $channel_menu = ((x($_POST['channel_menu'])) ? htmlspecialchars_decode(trim($_POST['channel_menu']), ENT_QUOTES) : '');

        $unless_mention_count = ((x($_POST, 'unless_mention_count')) ? intval($_POST['unless_mention_count']) : 0);
        $unless_tag_count = ((x($_POST, 'unless_tag_count')) ? intval($_POST['unless_tag_count']) : 0);
        $preview_outbox = ((x($_POST, 'preview_outbox')) ? intval($_POST['preview_outbox']) : 0);
        $allow_location = (((x($_POST, 'allow_location')) && (intval($_POST['allow_location']) == 1)) ? 1 : 0);
        $blocktags = (((x($_POST, 'blocktags')) && (intval($_POST['blocktags']) == 1)) ? 0 : 1); // this setting is inverted!
        $suggestme = ((x($_POST, 'suggestme')) ? intval($_POST['suggestme']) : 0);
        $hyperdrive = ((x($_POST, 'hyperdrive')) ? intval($_POST['hyperdrive']) : 0);
        $activitypub = ((x($_POST, 'activitypub')) ? intval($_POST['activitypub']) : 0);
        $tag_username = ((x($_POST, 'tag_username')) ? intval($_POST['tag_username']) : 0);
        $post_newfriend = (($_POST['post_newfriend'] == 1) ? 1 : 0);
        $post_joingroup = (($_POST['post_joingroup'] == 1) ? 1 : 0);
        $post_profilechange = (($_POST['post_profilechange'] == 1) ? 1 : 0);
        $adult = (($_POST['adult'] == 1) ? 1 : 0);
        $defpermcat = ((x($_POST, 'defpermcat')) ? notags(trim($_POST['defpermcat'])) : 'default');
        $cal_first_day = (((x($_POST, 'first_day')) && intval($_POST['first_day']) >= 0 && intval($_POST['first_day']) < 7) ? intval($_POST['first_day']) : 0);
        $mailhost = ((array_key_exists('mailhost', $_POST)) ? notags(trim($_POST['mailhost'])) : '');
        $profile_assign = ((x($_POST, 'profile_assign')) ? notags(trim($_POST['profile_assign'])) : '');
        $permit_all_mentions = (($_POST['permit_all_mentions'] == 1) ? 1 : 0);
        $close_comment_days = (($_POST['close_comments']) ? intval($_POST['close_comments']) : 0);
        set_pconfig(local_channel(), 'system', 'close_comments', $close_comment_days ? $close_comment_days . ' days' : '');

        // allow a permission change to over-ride the autoperms setting from the form
        if (!isset($this->autoperms)) {
            $this->autoperms = ((x($_POST, 'autoperms')) ? intval($_POST['autoperms']) : 0);
        }
        $set_location = (isset($_POST['set_location']) ? trim($_POST['set_location']) : '');
        if ($set_location) {
            $lat = false;
            $lon = false;
            $tmp = preg_split('/[ ,\/]/', $set_location);
            if (count($tmp) > 1) {
                $lat = floatval(trim($tmp[0]));
                $lon = floatval(trim($tmp[1]));
            }
            $valid = $lat || $lon;
            if ($valid) {
                PConfig::Set(local_channel(),'system', 'set_location', $lat . ',' . $lon);
            }
        }
        else {
            PConfig::Set(local_channel(),'system', 'set_location', '');
        }

        $pageflags = $channel['channel_pageflags'];
        $existing_adult = (($pageflags & PAGE_ADULT) ? 1 : 0);
        if ($adult != $existing_adult) {
            $pageflags = ($pageflags ^ PAGE_ADULT);
        }


        $notify = 0;
        $vnotify = 0;
        for ($x = 1; $x <= 10; $x++) {
            if(isset($_POST['notify' . $x])) {
                $notify += intval($_POST['notify' . $x]);
            }
        }
        for ($x = 1; $x <= 17; $x++) {
            if(isset($_POST['vnotify' . $x])) {
                $vnotify += intval($_POST['vnotify' . $x]);
            }
        }

        date_default_timezone_set($timezone ?: 'UTC');

        $name_change = false;

        if ($channel_name != $channel['channel_name']) {
            $name_change = true;
            $err = Zlib\Channel::validate_channelname($channel_name);
            if ($err) {
                notice($err);
                return;
            }
        }

        $ntags = strtoarr(',', $_POST['followed_tags']);

        set_pconfig(local_channel(), 'system', 'followed_tags', ($ntags) ?: EMPTY_STR);
        set_pconfig(local_channel(), 'system', 'use_browser_location', $allow_location);
        set_pconfig(local_channel(), 'system', 'suggestme', $suggestme);
        set_pconfig(local_channel(), 'system', 'post_newfriend', $post_newfriend);
        set_pconfig(local_channel(), 'system', 'post_joingroup', $post_joingroup);
        set_pconfig(local_channel(), 'system', 'post_profilechange', $post_profilechange);
        set_pconfig(local_channel(), 'system', 'blocktags', $blocktags);
        set_pconfig(local_channel(), 'system', 'channel_menu', $channel_menu);
        set_pconfig(local_channel(), 'system', 'vnotify', $vnotify);
        set_pconfig(local_channel(), 'system', 'evdays', $evdays);
        set_pconfig(local_channel(), 'system', 'photo_path', $photo_path);
        set_pconfig(local_channel(), 'system', 'attach_path', $attach_path);
        set_pconfig(local_channel(), 'system', 'cal_first_day', $cal_first_day);
        set_pconfig(local_channel(), 'system', 'default_permcat', $defpermcat);
        set_pconfig(local_channel(), 'system', 'email_notify_host', $mailhost);
        set_pconfig(local_channel(), 'system', 'profile_assign', $profile_assign);
        set_pconfig(local_channel(), 'system', 'hyperdrive', $hyperdrive);
        set_pconfig(local_channel(), 'system', 'activitypub', $activitypub);
        set_pconfig(local_channel(), 'system', 'autoperms', $this->autoperms);
        set_pconfig(local_channel(), 'system', 'tag_username', $tag_username);
        set_pconfig(local_channel(), 'system', 'permit_all_mentions', $permit_all_mentions);
        set_pconfig(local_channel(), 'system', 'unless_mention_count', $unless_mention_count);
        set_pconfig(local_channel(), 'system', 'unless_tag_count', $unless_tag_count);
        set_pconfig(local_channel(), 'system', 'noindex', $noindex);
        set_pconfig(local_channel(), 'system', 'preview_outbox', $preview_outbox);

        $r = q(
            "update channel set channel_name = '%s', channel_pageflags = %d, channel_timezone = '%s', channel_location = '%s', 
                   channel_notifyflags = %d, channel_max_friend_req = %d, channel_expire_days = %d where channel_id = %d",
            dbesc($channel_name),
            intval($pageflags),
            dbesc($timezone),
            dbesc($defloc),
            intval($notify),
            intval($maxreq),
            intval($expire),
            intval(local_channel())
        );
        if ($r) {
            info(t('Settings updated.') . EOL);
        }


        $r = q(
            "UPDATE profile SET publish = %d WHERE is_default = 1 AND uid = %d",
            intval($this->publish),
            intval(local_channel())
        );
        $r = q(
            "UPDATE xchan SET xchan_hidden = %d WHERE xchan_hash = '%s'",
            intval(1 - $this->publish),
            intval($channel['channel_hash'])
        );

        if ($name_change) {
            // catch xchans for all protocols by matching the url
            $r = q(
                "update xchan set xchan_name = '%s', xchan_name_date = '%s' where xchan_url = '%s'",
                dbesc($channel_name),
                dbesc(datetime_convert()),
                dbesc(z_root() . '/channel/' . $channel['channel_address'])
            );
            $r = q(
                "update profile set fullname = '%s' where uid = %d and is_default = 1",
                dbesc($channel_name),
                intval($channel['channel_id'])
            );
            if (Zlib\Channel::is_system($channel['channel_id'])) {
                set_config('system', 'sitename', $channel_name);
            }
        }

        Run::Summon(['Directory', local_channel()]);

        Libsync::build_sync_packet();

        goaway(z_root() . '/settings');

    }

    public function get()
    {

        require_once('include/permissions.php');

        $yes_no = [t('No'), t('Yes')];

        $p = q(
            "SELECT * FROM profile WHERE is_default = 1 AND uid = %d LIMIT 1",
            intval(local_channel())
        );
        if (count($p)) {
            $profile = $p[0];
        }

        $channel = App::get_channel();

        $global_perms = Permissions::Perms();

        $permiss = [];

        $perm_opts = [
            [t('Restricted - connections only'), PERMS_SPECIFIC],
            [t('Semi-public - anybody that can be identified'), PERMS_AUTHED],
            [t('Public - anybody on the internet'), PERMS_PUBLIC]
        ];

        $limits = PermissionLimits::Get(local_channel());
        $anon_comments = get_config('system', 'anonymous_comments');

        foreach ($global_perms as $k => $perm) {
            $options = [];
            $can_be_public = strstr($k, 'view') || ($k === 'search_stream') || ($k === 'post_comments' && $anon_comments);
            foreach ($perm_opts as $opt) {
                if ($opt[1] == PERMS_PUBLIC && (!$can_be_public)) {
                    continue;
                }
                $options[$opt[1]] = $opt[0];
            }
            if ($k === 'post_comments') {
                $comment_perms = [$k, $perm, $limits[$k], '', $options];
            } elseif ($k === 'post_mail') {
                $mail_perms = [$k, $perm, $limits[$k], '', $options];
            } elseif ($k === 'view_contacts') {
                $view_contact_perms = [$k, $perm, $limits[$k], '', $options];
            } elseif ($k === 'search_stream') {
                $search_perms = [$k, $perm, $limits[$k], '', $options];
            } else {
                $permiss[] = [$k, $perm, $limits[$k], '', $options];
            }
        }

        //      logger('permiss: ' . print_r($permiss,true));

        $channel_name = $channel['channel_name'];
        $nickname = $channel['channel_address'];
        $timezone = $channel['channel_timezone'];
        $notify = $channel['channel_notifyflags'];
        $defloc = $channel['channel_location'];

        $maxreq = $channel['channel_max_friend_req'];
        $expire = $channel['channel_expire_days'];
        $adult_flag = intval($channel['channel_pageflags'] & PAGE_ADULT);
        $sys_expire = get_config('system', 'default_expire_days');

        $hide_presence = intval(get_pconfig(local_channel(), 'system', 'hide_online_status'));

        $suggestme = get_pconfig(local_channel(), 'system', 'suggestme');
        $suggestme = (($suggestme === false) ? '0' : $suggestme); // default if not set: 0

        $post_newfriend = get_pconfig(local_channel(), 'system', 'post_newfriend');
        $post_newfriend = (($post_newfriend === false) ? '0' : $post_newfriend); // default if not set: 0

        $post_joingroup = get_pconfig(local_channel(), 'system', 'post_joingroup');
        $post_joingroup = (($post_joingroup === false) ? '0' : $post_joingroup); // default if not set: 0

        $post_profilechange = get_pconfig(local_channel(), 'system', 'post_profilechange');
        $post_profilechange = (($post_profilechange === false) ? '0' : $post_profilechange); // default if not set: 0

        $blocktags = get_pconfig(local_channel(), 'system', 'blocktags');
        $blocktags = (($blocktags === false) ? '0' : $blocktags);

        $opt_tpl = Theme::get_template("field_checkbox.tpl");
        if (get_config('system', 'publish_all')) {
            $profile_in_dir = '<input type="hidden" name="profile_in_directory" value="1" />';
        } else {
            $profile_in_dir = replace_macros($opt_tpl, [
                '$field' => ['profile_in_directory', t('Publish your profile in the network directory'), $profile['publish'], '', $yes_no],
            ]);
        }

        $suggestme = replace_macros($opt_tpl, [
            '$field' => ['suggestme', t('Allow us to suggest you as a potential friend to new members?'), $suggestme, '', $yes_no],

        ]);

        $subdir = ((strlen(App::get_path())) ? '<br>' . t('or') . ' ' . z_root() . '/channel/' . $nickname : '');

        $webbie = $nickname . '@' . App::get_hostname();
        $intl_nickname = unpunify($nickname) . '@' . unpunify(App::get_hostname());

        $prof_addr = replace_macros(Theme::get_template('channel_settings_header.tpl'), [
            '$desc' => t('Your channel address is'),
            '$nickname' => (($intl_nickname === $webbie) ? $webbie : $intl_nickname . '&nbsp;(' . $webbie . ')'),
            '$compat' => t('Friends using compatible applications can use this address to connect with you.'),
            '$subdir' => $subdir,
            '$davdesc' => t('Your files/photos are accessible as a network drive at'),
            '$davpath' => z_root() . '/dav/' . $nickname,
            '$windows' => t('(Windows)'),
            '$other' => t('(other platforms)'),
            '$or' => t('or'),
            '$davspath' => 'davs://' . App::get_hostname() . '/dav/' . $nickname,
            '$basepath' => App::get_hostname()
        ]);


        $pcat = new Permcat(local_channel());
        $pcatlist = $pcat->listing();
        $permcats = [];
        if ($pcatlist) {
            foreach ($pcatlist as $pc) {
                $permcats[$pc['name']] = $pc['localname'];
            }
        }

        $default_permcat = get_pconfig(local_channel(), 'system', 'default_permcat', 'default');


        $acl = new AccessControl($channel);
        $perm_defaults = $acl->get();

        $group_select = AccessList::select(local_channel(), $channel['channel_default_group']);


        $m1 = Menu::list(local_channel());
        $menu = false;
        if ($m1) {
            $menu = [];
            $current = get_pconfig(local_channel(), 'system', 'channel_menu');
            $menu[] = ['name' => '', 'selected' => !$current];
            foreach ($m1 as $m) {
                $menu[] = ['name' => htmlspecialchars($m['menu_name'], ENT_COMPAT, 'UTF-8'), 'selected' => (($m['menu_name'] === $current) ? ' selected="selected" ' : false)];
            }
        }

        $evdays = get_pconfig(local_channel(), 'system', 'evdays');
        if (!$evdays) {
            $evdays = 3;
        }

        $permissions_role = get_pconfig(local_channel(), 'system', 'permissions_role');
        if (!$permissions_role) {
            $permissions_role = 'custom';
        }

        $autoperms = replace_macros(Theme::get_template('field_checkbox.tpl'), [
                '$field' => ['autoperms', t('Automatic connection approval'), ((get_pconfig(local_channel(), 'system', 'autoperms', 0)) ? 1 : 0),
                t('If enabled, connection requests will be approved without your interaction'), $yes_no]
        ]);

        $hyperdrive = ['hyperdrive', t('Friend-of-friend conversations'), ((get_pconfig(local_channel(), 'system', 'hyperdrive', true)) ? 1 : 0), t('Import public third-party conversations in which your connections participate.'), $yes_no];

        if (get_config('system', 'activitypub', ACTIVITYPUB_ENABLED)) {
            $apconfig = true;
            $activitypub = replace_macros(Theme::get_template('field_checkbox.tpl'), ['$field' => ['activitypub', t('Enable ActivityPub protocol'), ((get_pconfig(local_channel(), 'system', 'activitypub', ACTIVITYPUB_ENABLED)) ? 1 : 0), t('ActivityPub is an emerging internet standard for social communications'), $yes_no]]);
        } else {
            $apconfig = false;
            $activitypub = '<input type="hidden" name="activitypub" value="' . intval(ACTIVITYPUB_ENABLED) . '" >' . EOL;
        }

        $permissions_set = $permissions_role != 'custom';

        $perm_roles = PermissionRoles::roles();
        // Don't permit changing to a collection (@TODO unless there is a mechanism to select the channel_parent)
        unset($perm_roles['Collection']);


        $vnotify = get_pconfig(local_channel(), 'system', 'vnotify');
        if ($vnotify === false) {
            $vnotify = (-1);
        }

        $plugin = ['basic' => '', 'security' => '', 'notify' => '', 'misc' => ''];
        Hook::call('channel_settings', $plugin);

        $public_stream_mode = intval(get_config('system', 'public_stream_mode', PUBLIC_STREAM_NONE));

        $ft = get_pconfig(local_channel(), 'system', 'followed_tags', '');
        if ($ft && is_array($ft)) {
            $followed = implode(',', $ft);
        } else {
            $followed = EMPTY_STR;
        }

        $mention_count = get_pconfig(local_channel(), 'system', 'unless_mention_count',
            get_config('system', 'unless_mention_count', 20));
        $tag_count = get_pconfig(local_channel(), 'system', 'unless_tag_count',
            get_config('system', 'unless_tag_count', 20));
  
        $o = replace_macros(Theme::get_template('settings.tpl'), [
            '$ptitle' => t('Channel Settings'),
            '$submit' => t('Submit'),
            '$baseurl' => z_root(),
            '$uid' => local_channel(),
            '$form_security_token' => get_form_security_token("settings"),
            '$nickname_block' => $prof_addr,
            '$h_basic' => t('Basic Settings'),
            '$channel_name' => ['channel_name', t('Full name'), $channel_name, ''],
            '$timezone' => ['timezone_select', t('Your timezone'), $timezone, t('This is important for showing the correct time on shared events'), get_timezones()],
            '$defloc' => ['defloc', t('Default post location (place name)'), $defloc, t('Optional geographical location to display on your posts')],
            '$allowloc' => ['allow_location', t('Obtain post location from your web browser or device'), ((get_pconfig(local_channel(), 'system', 'use_browser_location')) ? 1 : ''), '', $yes_no],
            '$set_location' => [ 'set_location', t('Over-ride your web browser or device and use these coordinates (latitude,longitude)'), get_pconfig(local_channel(),'system','set_location')],
            '$adult' => ['adult', t('Adult content'), $adult_flag, t('Enable to indicate if this channel frequently or regularly publishes adult content. (Please also tag any adult material and/or nudity with #NSFW)'), $yes_no],

            '$h_prv' => t('Security and Privacy'),
            '$permissions_set' => $permissions_set,
            '$perms_set_msg' => t('Your permissions are already configured. Click to view/adjust'),

            '$hide_presence' => ['hide_presence', t('Hide your online presence'), $hide_presence, t('Prevents displaying in your profile that you are online'), $yes_no],
            '$preview_outbox' => [ 'preview_outbox', t('Preview some public posts from new connections prior to connection approval'), intval(get_pconfig($channel['channel_id'], 'system','preview_outbox', false)), '', $yes_no ],
            '$permiss_arr' => $permiss,
            '$comment_perms' => $comment_perms,
            '$mail_perms' => $mail_perms,
            '$view_contact_perms' => $view_contact_perms,
            '$search_perms' => $search_perms,
            '$noindex' => ['noindex', t('Forbid indexing of your public channel content by search engines'), get_pconfig($channel['channel_id'], 'system', 'noindex'), '', $yes_no],
            '$close_comments' => ['close_comments', t('Disable acceptance of comments on your posts after this many days'), ((intval(get_pconfig(local_channel(), 'system', 'close_comments'))) ? intval(get_pconfig(local_channel(), 'system', 'close_comments')) : EMPTY_STR), t('Leave unset or enter 0 to allow comments indefinitely')],
            '$blocktags' => ['blocktags', t('Allow others to tag your posts'), 1 - $blocktags, t('Often used by the community to retro-actively flag inappropriate content'), $yes_no],

            '$lbl_p2macro' => t('Channel Permission Limits'),

            '$expire' => ['expire', t('Expire conversations you have not participated in after this many days'), $expire, t('0 or blank to use the website limit.') . ' ' . ((intval($sys_expire)) ? sprintf(t('This website expires after %d days.'), intval($sys_expire)) : t('This website does not provide an expiration policy.')) . ' ' . t('The website limit takes precedence if lower than your limit.')],
            '$maxreq' => ['maxreq', t('Maximum Friend Requests/Day:'), intval($channel['channel_max_friend_req']), t('May reduce spam activity')],
            '$permissions' => t('Default Access List'),
            '$permdesc' => t("(click to open/close)"),
            '$aclselect' => Libacl::populate($perm_defaults, false, PermissionDescription::fromDescription(t('Use your default audience setting for the type of object published'))),
            '$profseltxt' => t('Profile to assign new connections'),
            '$profselect' => ((Features::enabled(local_channel(), 'multi_profiles')) ? contact_profile_assign(get_pconfig(local_channel(), 'system', 'profile_assign', '')) : ''),

            '$allow_cid' => acl2json($perm_defaults['allow_cid']),
            '$allow_gid' => acl2json($perm_defaults['allow_gid']),
            '$deny_cid' => acl2json($perm_defaults['deny_cid']),
            '$deny_gid' => acl2json($perm_defaults['deny_gid']),
            '$suggestme' => $suggestme,
            '$group_select' => $group_select,
            '$can_change_role' => !in_array($permissions_role, ['collection', 'collection_restricted']),
            '$permissions_role' => $permissions_role,
            '$role' => ['permissions_role', t('Channel type and privacy'), $permissions_role, '', $perm_roles, ' onchange="update_role_text(); return false;"'],
            '$defpermcat' => ['defpermcat', t('Default Permissions Role'), $default_permcat, '', $permcats],
            '$permcat_enable' => Apps::system_app_installed(local_channel(), 'Roles'),
            '$profile_in_dir' => $profile_in_dir,
            '$autoperms' => $autoperms,
            '$hyperdrive' => $hyperdrive,
            '$activitypub' => $activitypub,
            '$apconfig' => $apconfig,
            '$close' => t('Close'),
            '$h_not' => t('Notifications'),
            '$activity_options' => t('By default post a status message when:'),
            '$post_newfriend' => ['post_newfriend', t('accepting a friend request'), $post_newfriend, '', $yes_no],
            '$post_joingroup' => ['post_joingroup', t('joining a group/community'), $post_joingroup, '', $yes_no],
            '$post_profilechange' => ['post_profilechange', t('making an <em>interesting</em> profile change'), $post_profilechange, '', $yes_no],
            '$lbl_not' => t('Send a notification email when:'),
            '$notify1' => ['notify1', t('You receive a connection request'), ($notify & NOTIFY_INTRO), NOTIFY_INTRO, '', $yes_no],
//          '$notify2'  => array('notify2', t('Your connections are confirmed'), ($notify & NOTIFY_CONFIRM), NOTIFY_CONFIRM, '', $yes_no),
            '$notify3' => ['notify3', t('Someone writes on your profile wall'), ($notify & NOTIFY_WALL), NOTIFY_WALL, '', $yes_no],
            '$notify4' => ['notify4', t('Someone writes a followup comment'), ($notify & NOTIFY_COMMENT), NOTIFY_COMMENT, '', $yes_no],
            '$notify10' => ['notify10', t('Someone shares a followed conversation'), ($notify & NOTIFY_RESHARE), NOTIFY_RESHARE, '', $yes_no],
            '$notify5' => ['notify5', t('You receive a direct (private) message'), ($notify & NOTIFY_MAIL), NOTIFY_MAIL, '', $yes_no],
//          '$notify6'  => array('notify6', t('You receive a friend suggestion'), ($notify & NOTIFY_SUGGEST), NOTIFY_SUGGEST, '', $yes_no),
            '$notify7' => ['notify7', t('You are tagged in a post'), ($notify & NOTIFY_TAGSELF), NOTIFY_TAGSELF, '', $yes_no],
//          '$notify8'  => array('notify8', t('You are poked/prodded/etc. in a post'), ($notify & NOTIFY_POKE), NOTIFY_POKE, '', $yes_no),

            '$notify9' => ['notify9', t('Someone likes your post/comment'), ($notify & NOTIFY_LIKE), NOTIFY_LIKE, '', $yes_no],


            '$lbl_vnot' => t('Show visual notifications including:'),

            '$vnotify1' => ['vnotify1', t('Unseen stream activity'), ($vnotify & VNOTIFY_NETWORK), VNOTIFY_NETWORK, '', $yes_no],
            '$vnotify2' => ['vnotify2', t('Unseen channel activity'), ($vnotify & VNOTIFY_CHANNEL), VNOTIFY_CHANNEL, '', $yes_no],
            '$vnotify3' => ['vnotify3', t('Unseen direct messages'), ($vnotify & VNOTIFY_MAIL), VNOTIFY_MAIL, t('Recommended'), $yes_no],
            '$vnotify4' => ['vnotify4', t('Upcoming events'), ($vnotify & VNOTIFY_EVENT), VNOTIFY_EVENT, '', $yes_no],
            '$vnotify5' => ['vnotify5', t('Events today'), ($vnotify & VNOTIFY_EVENTTODAY), VNOTIFY_EVENTTODAY, '', $yes_no],
            '$vnotify6' => ['vnotify6', t('Upcoming birthdays'), ($vnotify & VNOTIFY_BIRTHDAY), VNOTIFY_BIRTHDAY, t('Not available in all themes'), $yes_no],
            '$vnotify7' => ['vnotify7', t('System (personal) notifications'), ($vnotify & VNOTIFY_SYSTEM), VNOTIFY_SYSTEM, '', $yes_no],
            '$vnotify8' => ['vnotify8', t('System info messages'), ($vnotify & VNOTIFY_INFO), VNOTIFY_INFO, t('Recommended'), $yes_no],
            '$vnotify9' => ['vnotify9', t('System critical alerts'), ($vnotify & VNOTIFY_ALERT), VNOTIFY_ALERT, t('Recommended'), $yes_no],
            '$vnotify10' => ['vnotify10', t('New connections'), ($vnotify & VNOTIFY_INTRO), VNOTIFY_INTRO, t('Recommended'), $yes_no],
            '$vnotify11' => ((is_site_admin()) ? ['vnotify11', t('System Registrations'), ($vnotify & VNOTIFY_REGISTER), VNOTIFY_REGISTER, '', $yes_no] : []),
//          '$vnotify12'  => array('vnotify12', t('Unseen shared files'), ($vnotify & VNOTIFY_FILES), VNOTIFY_FILES, '', $yes_no),
            '$vnotify13' => (($public_stream_mode) ? ['vnotify13', t('Unseen public stream activity'), ($vnotify & VNOTIFY_PUBS), VNOTIFY_PUBS, '', $yes_no] : []),
            '$vnotify14' => ['vnotify14', t('Unseen likes and dislikes'), ($vnotify & VNOTIFY_LIKE), VNOTIFY_LIKE, '', $yes_no],
            '$vnotify15' => ['vnotify15', t('Unseen group posts'), ($vnotify & VNOTIFY_FORUMS), VNOTIFY_FORUMS, '', $yes_no],
            '$vnotify16' => ((is_site_admin()) ? ['vnotify16', t('Reported content'), ($vnotify & VNOTIFY_REPORTS), VNOTIFY_REPORTS, '', $yes_no] : []),
            '$vnotify17' => ['vnotify17', t('Moderated Activities'), ($vnotify & VNOTIFY_MODERATE), VNOTIFY_MODERATE, t('Recommended'), $yes_no],
            '$desktop_notifications_info' => t('Desktop notifications are unavailable because the required browser permission has not been granted'),
            '$desktop_notifications_request' => t('Grant permission'),
            '$mailhost' => ['mailhost', t('Email notifications sent from (hostname)'), get_pconfig(local_channel(), 'system', 'email_notify_host', App::get_hostname()), sprintf(t('If your channel is mirrored to multiple locations, set this to your preferred location. This will prevent duplicate email notifications. Example: %s'), App::get_hostname())],
            '$permit_all_mentions' => ['permit_all_mentions', t('Accept messages from strangers which mention you'), get_pconfig(local_channel(), 'system', 'permit_all_mentions'), t('This setting bypasses normal permissions'), $yes_no],
            '$followed_tags' => ['followed_tags', t('Accept messages from strangers which include any of the following hashtags'), $followed, t('comma separated, do not include the #')],
            '$unless_mention_count' => ['unless_mention_count', t('Unless more than this many channels are mentioned'), $mention_count, t('0 for unlimited')],
            '$unless_tag_count' => ['unless_tag_count', t('Unless more than this many hashtags are used'), $tag_count, t('0 for unlimited')],
            '$evdays' => ['evdays', t('Notify me of events this many days in advance'), $evdays, t('Must be greater than 0')],
            '$basic_addon' => $plugin['basic'],
            '$sec_addon' => $plugin['security'],
            '$notify_addon' => $plugin['notify'],
            '$misc_addon' => $plugin['misc'],
            '$lbl_time' => t('Date and Time'),
            '$miscdoc' => t('This section is reserved for use by optional addons and apps to provide additional settings.'),
            '$h_advn' => t('Advanced Account/Page Type Settings'),
            '$h_descadvn' => t('Change the behaviour of this account for special situations'),
            '$lbl_misc' => t('Miscellaneous'),
            '$photo_path' => ['photo_path', t('Default photo upload folder name'), get_pconfig(local_channel(), 'system', 'photo_path'), t('%Y - current year, %m -  current month')],
            '$attach_path' => ['attach_path', t('Default file upload folder name'), get_pconfig(local_channel(), 'system', 'attach_path'), t('%Y - current year, %m -  current month')],
            '$menus' => $menu,
            '$menu_desc' => t('Personal menu to display in your channel pages'),
            '$removeme' => t('Remove Channel'),
            '$removechannel' => t('Remove this channel.'),
            '$tag_username' => ['tag_username', t('Mentions should display'), intval(get_pconfig(local_channel(), 'system', 'tag_username', get_config('system', 'tag_username', false))), t('Changes to this setting are applied to new posts/comments only. It is not retroactive.'),
                [
                    0 => t('the channel display name [example: @Barbara Jenkins]'),
                    1 => t('the channel nickname [example: @barbara1976]'),
                    2 => t('combined [example: @Barbara Jenkins (barbara1976)]'),
                    127 => t('no preference, use the system default'),
                ]],

            '$cal_first_day' => ['first_day', t('Calendar week begins on'), intval(get_pconfig(local_channel(), 'system', 'cal_first_day')), t('This varies by country/culture'),
                [0 => t('Sunday'),
                    1 => t('Monday'),
                    2 => t('Tuesday'),
                    3 => t('Wednesday'),
                    4 => t('Thursday'),
                    5 => t('Friday'),
                    6 => t('Saturday')
                ]],
        ]);

        Hook::call('settings_form', $o);
        return $o;
    }

    protected function change_permissions_role($channel, $role)
    {
        if ($role === 'custom') {
            $this->set_custom_role($channel);
        }
        else {
            $this->set_standard_role($channel, $role);
        }
        set_pconfig(local_channel(), 'system', 'permissions_role', $role);
    }

    protected function set_custom_role($channel)
    {
        $hide_presence = (((x($_POST, 'hide_presence')) && (intval($_POST['hide_presence']) == 1)) ? 1 : 0);
        $def_group = ((x($_POST, 'group-selection')) ? notags(trim($_POST['group-selection'])) : '');
        q(
            "update channel set channel_default_group = '%s' where channel_id = %d",
            dbesc($def_group),
            intval(local_channel())
        );

        $global_perms = Permissions::Perms();

        foreach ($global_perms as $k => $v) {
            PermissionLimits::Set(local_channel(), $k, intval($_POST[$k]));
        }
        $acl = new AccessControl($channel);
        $acl->set_from_array($_POST);
        $x = $acl->get();

        q(
            "update channel set channel_allow_cid = '%s', channel_allow_gid = '%s',
                    channel_deny_cid = '%s', channel_deny_gid = '%s' where channel_id = %d",
            dbesc($x['allow_cid']),
            dbesc($x['allow_gid']),
            dbesc($x['deny_cid']),
            dbesc($x['deny_gid']),
            intval(local_channel())
        );
        set_pconfig(local_channel(), 'system', 'hide_online_status', $hide_presence);
    }
    protected function set_standard_role($channel, $role)
    {
        $role_permissions = PermissionRoles::role_perms($role);
        if (!$role_permissions) {
            notice('Permissions category could not be found.');
            return;
        }
        $hide_presence = 1 - (intval($role_permissions['online']));
        if ($role_permissions['default_collection']) {
            $r = q(
                "select hash from pgrp where uid = %d and gname = '%s' limit 1",
                intval(local_channel()),
                dbesc(t('Friends'))
            );
            if (!$r) {
                AccessList::add(local_channel(), t('Friends'));
                AccessList::member_add(local_channel(), t('Friends'), $channel['channel_hash']);
                $r = q(
                    "select hash from pgrp where uid = %d and gname = '%s' limit 1",
                    intval(local_channel()),
                    dbesc(t('Friends'))
                );
            }
            if ($r) {
                q(
                    "update channel set channel_default_group = '%s', channel_allow_gid = '%s', channel_allow_cid = '', channel_deny_gid = '', channel_deny_cid = '' where channel_id = %d",
                    dbesc($r[0]['hash']),
                    dbesc('<' . $r[0]['hash'] . '>'),
                    intval(local_channel())
                );
            } else {
                notice(sprintf('Default access list \'%s\' not found. Please create and re-submit permission change.', t('Friends')) . EOL);
                return;
            }
        } // no default permissions
        else {
            q(
                "update channel set channel_default_group = '', channel_allow_gid = '', channel_allow_cid = '', channel_deny_gid = '',
                         channel_deny_cid = '' where channel_id = %d",
                intval(local_channel())
            );
        }

        if ($role_permissions['perms_connect']) {
            $x = Permissions::FilledPerms($role_permissions['perms_connect']);
            $str = Permissions::serialise($x);
            set_abconfig(local_channel(), $channel['channel_hash'], 'system', 'my_perms', $str);

            $this->autoperms = intval($role_permissions['perms_auto']);
        }

        if ($role_permissions['limits']) {
            foreach ($role_permissions['limits'] as $k => $v) {
                PermissionLimits::Set(local_channel(), $k, $v);
            }
        }
        if (array_key_exists('directory_publish', $role_permissions)) {
            $this->publish = intval($role_permissions['directory_publish']);
        }
        set_pconfig(local_channel(), 'system', 'hide_online_status', $hide_presence);
    }

}
