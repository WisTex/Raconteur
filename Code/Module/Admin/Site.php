<?php

namespace Code\Module\Admin;

use App;
use Code\Lib\System;
use Code\Lib\PConfig;
use Code\Access\PermissionRoles;
use Code\Lib\Channel;
use Code\Render\Theme;
use Code\Lib\Addon;
use Code\Module\Legal;

class Site
{

    /**
     * @brief POST handler for Admin Site Page.
     *
     */
    public function post()
    {

        if (!is_site_admin()) {
            return;
        }

        if (!x($_POST, 'page_site')) {
            return;
        }

        $sys = Channel::get_system();

        check_form_security_token_redirectOnErr('/admin/site', 'admin_site');

        $sitename = ((x($_POST, 'sitename')) ? notags(trim($_POST['sitename'])) : App::get_hostname());

        $siteinfo = ((x($_POST, 'siteinfo')) ? trim($_POST['siteinfo']) : '');
        $legal = ((x($_POST, 'legal')) ? trim($_POST['legal']) : '');
        $language = ((x($_POST, 'language')) ? notags(trim($_POST['language'])) : 'en');
        $theme = ((x($_POST, 'theme')) ? notags(trim($_POST['theme'])) : '');
        $maximagesize = ((x($_POST, 'maximagesize')) ? intval(trim($_POST['maximagesize'])) : 0);

        $register_policy = ((x($_POST, 'register_policy')) ? intval(trim($_POST['register_policy'])) : 0);
        $minimum_age = ((x($_POST, 'minimum_age')) ? intval(trim($_POST['minimum_age'])) : 13);
        $access_policy = ((x($_POST, 'access_policy')) ? intval(trim($_POST['access_policy'])) : 0);
        $invite_only = ((x($_POST, 'invite_only')) ? true : false);
        $abandon_days = ((x($_POST, 'abandon_days')) ? intval(trim($_POST['abandon_days'])) : 0);

        $register_text = ((x($_POST, 'register_text')) ? notags(trim($_POST['register_text'])) : '');
        $site_sellpage = ((x($_POST, 'site_sellpage')) ? notags(trim($_POST['site_sellpage'])) : '');
        $site_location = ((x($_POST, 'site_location')) ? notags(trim($_POST['site_location'])) : '');
        $frontpage = ((x($_POST, 'frontpage')) ? notags(trim($_POST['frontpage'])) : '');
        $firstpage = ((x($_POST, 'firstpage')) ? notags(trim($_POST['firstpage'])) : 'profiles');
        $first_page = ((x($_POST, 'first_page')) ? notags(trim($_POST['first_page'])) : 'profiles');
        // check value after trim
        if (!$first_page) {
            $first_page = 'profiles';
        }
        $tos_required = ((x($_POST, 'tos_required')) ? intval(trim($_POST['tos_required'])) : 0);
        $mirror_frontpage = ((x($_POST, 'mirror_frontpage')) ? intval(trim($_POST['mirror_frontpage'])) : 0);
        $force_publish = ((x($_POST, 'publish_all')) ? true : false);
        $open_pubstream = ((x($_POST, 'open_pubstream')) ? true : false);
        $public_stream_mode = ((x($_POST, 'public_stream_mode')) ? intval($_POST['public_stream_mode']) : PUBLIC_STREAM_NONE);
        $animations = ((x($_POST, 'animations')) ? true : false);
        $login_on_homepage = ((x($_POST, 'login_on_homepage')) ? true : false);
        $no_community_page = !((x($_POST, 'no_community_page')) ? true : false);
        $default_expire_days = ((array_key_exists('default_expire_days', $_POST)) ? intval($_POST['default_expire_days']) : 0);
        $active_expire_days = ((array_key_exists('active_expire_days', $_POST)) ? intval($_POST['active_expire_days']) : 7);
        $max_imported_follow = ((x($_POST, 'max_imported_follow')) ? intval(trim($_POST['max_imported_follow'])) : MAX_IMPORTED_FOLLOW);

        $reply_address = ((array_key_exists('reply_address', $_POST) && trim($_POST['reply_address'])) ? trim($_POST['reply_address']) : 'noreply@' . App::get_hostname());
        $from_email = ((array_key_exists('from_email', $_POST) && trim($_POST['from_email'])) ? trim($_POST['from_email']) : 'Administrator@' . App::get_hostname());
        $from_email_name = ((array_key_exists('from_email_name', $_POST) && trim($_POST['from_email_name'])) ? trim($_POST['from_email_name']) : System::get_site_name());

        $verifyssl = ((x($_POST, 'verifyssl')) ? true : false);
        $proxyuser = ((x($_POST, 'proxyuser')) ? notags(trim($_POST['proxyuser'])) : '');
        $proxy = ((x($_POST, 'proxy')) ? notags(trim($_POST['proxy'])) : '');
        $timeout = ((x($_POST, 'timeout')) ? intval(trim($_POST['timeout'])) : 60);
        $post_timeout = ((x($_POST, 'post_timeout')) ? intval(trim($_POST['post_timeout'])) : 90);
        $show_like_counts = ((x($_POST, 'show_like_counts')) ? intval(trim($_POST['show_like_counts'])) : 0);
        $cache_images = ((x($_POST, 'cache_images')) ? intval(trim($_POST['cache_images'])) : 0);
        $delivery_interval = ((x($_POST, 'delivery_interval')) ? intval(trim($_POST['delivery_interval'])) : 0);
        $delivery_batch_count = ((x($_POST, 'delivery_batch_count') && $_POST['delivery_batch_count'] > 0) ? intval(trim($_POST['delivery_batch_count'])) : 3);
        $poll_interval = ((x($_POST, 'poll_interval')) ? intval(trim($_POST['poll_interval'])) : 0);
        $maxloadavg = ((x($_POST, 'maxloadavg')) ? intval(trim($_POST['maxloadavg'])) : 50);
//      $feed_contacts     = ((x($_POST,'feed_contacts'))    ? intval($_POST['feed_contacts'])    : 0);
        $ap_contacts = ((x($_POST, 'ap_contacts')) ? intval($_POST['ap_contacts']) : 0);
        $verify_email = ((x($_POST, 'verify_email')) ? 1 : 0);
        $imagick_path = ((x($_POST, 'imagick_path')) ? trim($_POST['imagick_path']) : '');
        $force_queue = ((intval($_POST['force_queue']) > 0) ? intval($_POST['force_queue']) : 3000);
        $pub_incl = escape_tags(trim($_POST['pub_incl']));
        $pub_excl = escape_tags(trim($_POST['pub_excl']));

        $permissions_role = escape_tags(trim($_POST['permissions_role']));

//      set_config('system', 'feed_contacts', $feed_contacts);
        set_config('system', 'activitypub', $ap_contacts);
        set_config('system', 'delivery_interval', $delivery_interval);
        set_config('system', 'delivery_batch_count', $delivery_batch_count);
        set_config('system', 'poll_interval', $poll_interval);
        set_config('system', 'maxloadavg', $maxloadavg);
        set_config('system', 'frontpage', $frontpage);
        set_config('system', 'cache_images', $cache_images);
        set_config('system', 'sellpage', $site_sellpage);
        set_config('system', 'workflow_channel_next', $first_page);
        set_config('system', 'site_location', $site_location);
        set_config('system', 'mirror_frontpage', $mirror_frontpage);
        set_config('system', 'sitename', $sitename);
        set_config('system', 'login_on_homepage', $login_on_homepage);
        set_config('system', 'verify_email', $verify_email);
        set_config('system', 'default_expire_days', $default_expire_days);
        set_config('system', 'active_expire_days', $active_expire_days);
        set_config('system', 'reply_address', $reply_address);
        set_config('system', 'from_email', $from_email);
        set_config('system', 'from_email_name', $from_email_name);
        set_config('system', 'imagick_convert_path', $imagick_path);
        set_config('system', 'default_permissions_role', $permissions_role);
        set_config('system', 'show_like_counts', $show_like_counts);
        set_config('system', 'pubstream_incl', $pub_incl);
        set_config('system', 'pubstream_excl', $pub_excl);
        set_config('system', 'max_imported_follow', $max_imported_follow);
        set_config('system', 'animated_avatars', $animations);
        set_config('system', 'tos_required', $tos_required);
        PConfig::Set(App::$sys_channel['channel_id'], 'system', 'legal', $legal);

        set_config('system', 'siteinfo', $siteinfo);

        // sync sitename and siteinfo updates to the system channel

        q(
            "update profile set about = '%s' where uid = %d and is_default = 1",
            dbesc($siteinfo),
            intval($sys['channel_id'])
        );
        q(
            "update profile set fullname = '%s' where uid = %d and is_default = 1",
            dbesc($sitename),
            intval($sys['channel_id'])
        );
        q(
            "update channel set channel_name = '%s' where channel_id  = %d",
            dbesc($sitename),
            intval($sys['channel_id'])
        );
        q(
            "update xchan set xchan_name = '%s' , xchan_name_date = '%s' where xchan_hash = '%s'",
            dbesc($sitename),
            dbesc(datetime_convert()),
            dbesc($sys['channel_hash'])
        );

        set_config('system', 'language', $language);
        set_config('system', 'theme', $theme);
        set_config('system', 'maximagesize', $maximagesize);

        set_config('system', 'register_policy', $register_policy);
        set_config('system', 'minimum_age', $minimum_age);
        set_config('system', 'invitation_only', $invite_only);
        set_config('system', 'access_policy', $access_policy);
        set_config('system', 'account_abandon_days', $abandon_days);
        set_config('system', 'register_text', $register_text);
        set_config('system', 'publish_all', $force_publish);
        set_config('system', 'public_stream_mode', $public_stream_mode);
        set_config('system', 'open_pubstream', $open_pubstream);
        set_config('system', 'force_queue_threshold', $force_queue);
        set_config('system', 'no_community_page', $no_community_page);
        set_config('system', 'verifyssl', $verifyssl);
        set_config('system', 'proxyuser', $proxyuser);
        set_config('system', 'proxy', $proxy);
        set_config('system', 'curl_timeout', $timeout);
        set_config('system', 'curl_post_timeout', $post_timeout);

        info(t('Site settings updated.') . EOL);
        goaway(z_root() . '/admin/site');
    }

    /**
     * @brief Admin page site.
     *
     * @return string with HTML
     */

    public function get()
    {

        /* Installed langs */
        $lang_choices = [];
        $langs = glob('view/*/strings.php');

        if (is_array($langs) && count($langs)) {
            if (!in_array('view/en/strings.php', $langs)) {
                $langs[] = 'view/en/';
            }
            asort($langs);
            foreach ($langs as $l) {
                $t = explode("/", $l);
                $lang_choices[$t[1]] = $t[1];
            }
        }

        /* Installed themes */
        $theme_choices_mobile["---"] = t("Default");
        $theme_choices = [];
        $files = glob('view/theme/*');
        if ($files) {
            foreach ($files as $file) {
                $vars = '';
                $f = basename($file);

                $info = Theme::get_info($f);
                $compatible = Addon::check_versions($info);
                if (!$compatible) {
                    $theme_choices[$f] = $theme_choices_mobile[$f] = sprintf(t('%s - (Incompatible)'), $f);
                    continue;
                }

                if (file_exists($file . '/library')) {
                    continue;
                }
                if (file_exists($file . '/mobile')) {
                    $vars = t('mobile');
                }
                if (file_exists($file . '/experimental')) {
                    $vars .= t('experimental');
                }
                if (file_exists($file . '/unsupported')) {
                    $vars .= t('unsupported');
                }
                if ($vars) {
                    $theme_choices[$f] = $f . ' (' . $vars . ')';
                    $theme_choices_mobile[$f] = $f . ' (' . $vars . ')';
                } else {
                    $theme_choices[$f] = $f;
                    $theme_choices_mobile[$f] = $f;
                }
            }
        }

        /* Admin Info */

        /* Register policy */
        $register_choices = [
            REGISTER_CLOSED => t("No"),
            REGISTER_APPROVE => t("Yes - with approval"),
            REGISTER_OPEN => t("Yes")
        ];

        /* Acess policy */
        $access_choices = [
            ACCESS_PRIVATE => t("My site is not a public server"),
            ACCESS_FREE => t("My site provides free public access"),
            ACCESS_PAID => t("My site provides paid public access"),
            ACCESS_TIERED => t("My site provides free public access and premium paid plans")
        ];

        $perm_roles = PermissionRoles::roles();
        $default_role = get_config('system', 'default_permissions_role', 'social');

        $role = ['permissions_role', t('Default permission role for new accounts'), $default_role, t('This role will be used for the first channel created after registration.'), $perm_roles];

        $legal = PConfig::Get(App::$sys_channel['channel_id'], 'system', 'legal');
        if (!$legal) {
            $legal = file_get_contents(Legal::LEGAL_SOURCE);
        }

        $homelogin = get_config('system', 'login_on_homepage');
        
        return replace_macros(Theme::get_template('admin_site.tpl'), [
            '$title' => t('Administration'),
            '$page' => t('Site'),
            '$submit' => t('Submit'),
            '$h_basic' => t('Site Configuration'),
            '$registration' => t('Registration'),
            '$upload' => t('File upload'),
            '$corporate' => t('Policies'),
            '$advanced' => t('Advanced'),
            '$baseurl' => z_root(),
            '$sitename' => ['sitename', t("Site name"), htmlspecialchars(get_config('system', 'sitename', App::get_hostname()), ENT_QUOTES, 'UTF-8'), ''],
            '$siteinfo' => ['siteinfo', t('Site Information'), get_config('system', 'siteinfo'), t("Description of this site.  Multicode may be used here.")],
            '$legal' => ['legal',t('Legal Information'), htmlspecialchars($legal), t('Multicode may be used here.')],
            '$language' => ['language', t("System language"), get_config('system', 'language', 'en'), "", $lang_choices],
            '$theme' => ['theme', t("System theme"), get_config('system', 'theme'), t("Default system theme - may be over-ridden by user profiles - <a href='#' id='cnftheme'>change theme settings</a>"), $theme_choices],
//          '$theme_mobile'         => [ 'theme_mobile', t("Mobile system theme"), get_config('system','mobile_theme'), t("Theme for mobile devices"), $theme_choices_mobile ],
//          '$site_channel'         => [ 'site_channel', t("Channel to use for this website's static pages"), get_config('system','site_channel'), t("Site Channel") ],
            '$ap_contacts' => ['ap_contacts', t('ActivityPub protocol'), get_config('system', 'activitypub', ACTIVITYPUB_ENABLED), t('Provides access to software supporting the ActivityPub protocol.')],
            '$maximagesize' => ['maximagesize', t("Maximum image size"), intval(get_config('system', 'maximagesize')), t("Maximum size in bytes of uploaded images. Default is 0, which means no limits.")],
            '$cache_images' => ['cache_images', t('Cache all public images'), intval(get_config('system', 'cache_images', 1)), t('If disabled, proxy non-SSL images, but do not store locally')],
            '$register_policy' => ['register_policy', t("Does this site allow new member registration?"), get_config('system', 'register_policy'), "", $register_choices],
            '$invite_only' => ['invite_only', t("Invitation only"), get_config('system', 'invitation_only'), t("Only allow new member registrations with an invitation code. New member registration must be allowed for this to work.")],
            '$invite_working' => defined('INVITE_WORKING'),
            '$minimum_age' => ['minimum_age', t("Minimum age"), (x(get_config('system', 'minimum_age')) ? get_config('system', 'minimum_age') : 13), t("Minimum age (in years) for who may register on this site - this is only evaluated if Terms of Service is required.")],
            '$access_policy' => ['access_policy', t("Which best describes the types of account offered by this site?"), get_config('system', 'access_policy'), t("If a public server policy is selected, this information may be displayed on the public server site list."), $access_choices],
            '$register_text' => ['register_text', t("Register text"), htmlspecialchars(get_config('system', 'register_text'), ENT_QUOTES, 'UTF-8'), t("Will be displayed prominently on the registration page.")],
            '$tos_required' => [ 'tos_required', t('Require acceptance of Terms of Service'),get_config('system','tos_required'),'', [ t('No'), t('Yes') ] ],
            '$role' => $role,
            '$frontpage' => ['frontpage', t("Site homepage to show visitors (default: login box)"), get_config('system', 'frontpage'), t("example: 'pubstream' to show public stream, 'channel/bob' to show Bob's channel or 'include:home.html' to include a file.")],
            '$mirror_frontpage' => ['mirror_frontpage', t("Preserve site homepage URL"), get_config('system', 'mirror_frontpage'), t('Present the site homepage in a frame at the original location instead of redirecting')],
            '$abandon_days' => ['abandon_days', t('Accounts abandoned after x days'), get_config('system', 'account_abandon_days'), t('Will not waste system resources polling external sites for abandonded accounts. Enter 0 for no time limit.')],
            '$verify_email' => ['verify_email', t("Verify Email Addresses"), get_config('system', 'verify_email'), t("Check to verify email addresses used in account registration (recommended).")],
            '$force_publish' => ['publish_all', t("Force publish in directory"), get_config('system', 'publish_all'), t("Check to force all profiles on this site to be listed in the site directory.")],

            '$public_stream_mode' => ['public_stream_mode', t('Public stream'), intval(get_config('system', 'public_stream_mode', 0)), t('Provide a Public stream on your site. This content is unmoderated.'), [
                0 => t('the Public stream is disabled'),
                1 => t('the Public stream contains public conversations from this site only'),
                2 => t('the Public stream contains public conversations from anywhere on the internet'),
            ]],

            '$open_pubstream' => ['open_pubstream', t('Allow anybody on the internet to access the Public stream'), get_config('system', 'open_pubstream', 0), t('Default is to only allow viewing by site members. Warning: this content is unmoderated.')],
            '$show_like_counts' => ['show_like_counts', t('Show numbers of likes and dislikes in conversations'), get_config('system', 'show_like_counts', 1), t('If disabled, the presence of likes and dislikes will be shown, but without totals.')],
            '$animations' => ['animations', t('Permit animated profile photos'), get_config('system', 'animated_avatars', true), t('Changing this may take several days to work through the system')],
            '$incl' => ['pub_incl', t('Only import Public stream posts with this text'), get_config('system', 'pubstream_incl'), t('words one per line or #tags or /patterns/ or lang=xx, leave blank to import all posts')],
            '$excl' => ['pub_excl', t('Do not import Public stream posts with this text'), get_config('system', 'pubstream_excl'), t('words one per line or #tags or /patterns/ or lang=xx, leave blank to import all posts')],
            '$max_imported_follow' => ['max_imported_follow', t('Maximum number of imported friends of friends'), get_config('system', 'max_imported_follow', MAX_IMPORTED_FOLLOW), t('Warning: higher numbers will improve the quality of friend suggestions and directory results but can exponentially increase resource usage')],
            '$login_on_homepage' => ['login_on_homepage', t("Login on Homepage"), ((intval($homelogin) || $homelogin === false) ? 1 : ''), t("Present a login box to visitors on the home page if no other content has been configured.")],
            '$reply_address' => ['reply_address', t('Reply-to email address for system generated email.'), get_config('system', 'reply_address', 'noreply@' . App::get_hostname()), ''],
            '$from_email' => ['from_email', t('Sender (From) email address for system generated email.'), get_config('system', 'from_email', 'Administrator@' . App::get_hostname()), ''],
            '$from_email_name' => ['from_email_name', t('Display name of email sender for system generated email.'), get_config('system', 'from_email_name', System::get_site_name()), ''],
            '$proxyuser' => ['proxyuser', t("Proxy user"), get_config('system', 'proxyuser'), ""],
            '$proxy' => ['proxy', t("Proxy URL"), get_config('system', 'proxy'), ""],
            '$timeout' => ['timeout', t("Network fetch timeout"), get_config('system', 'curl_timeout',60), t("Value is in seconds. Set to 0 for unlimited (not recommended).")],
            '$post_timeout' => ['post_timeout', t("Network post timeout"), get_config('system', 'curl_post_timeout', 90), t("Value is in seconds. Set to 0 for unlimited (not recommended).")],
            '$delivery_interval' => ['delivery_interval', t("Delivery interval"), get_config('system', 'delivery_interval', 2), t("Delay background delivery processes by this many seconds to reduce system load. Recommend: 4-5 for shared hosts, 2-3 for virtual private servers. 0-1 for large dedicated servers.")],
            '$delivery_batch_count' => ['delivery_batch_count', t('Deliveries per process'), get_config('system', 'delivery_batch_count', 3), t("Number of deliveries to attempt in a single operating system process. Adjust if necessary to tune system performance. Recommend: 1-5.")],
            '$force_queue' => ['force_queue', t("Queue Threshold"), get_config('system', 'force_queue_threshold', 3000), t("Always defer immediate delivery if queue contains more than this number of entries.")],
            '$poll_interval' => ['poll_interval', t("Poll interval"), get_config('system', 'poll_interval', 2), t("Delay background polling processes by this many seconds to reduce system load. If 0, use delivery interval.")],
            '$imagick_path' => ['imagick_path', t("Path to ImageMagick convert program"), get_config('system', 'imagick_convert_path'), t("If set, use this program to generate photo thumbnails for huge images ( > 4000 pixels in either dimension), otherwise memory exhaustion may occur. Example: /usr/bin/convert")],
            '$maxloadavg' => ['maxloadavg', t("Maximum Load Average"), ((intval(get_config('system', 'maxloadavg')) > 0) ? get_config('system', 'maxloadavg') : 50), t("Maximum system load before delivery and poll processes are deferred - default 50.")],
            '$default_expire_days' => ['default_expire_days', t('Expiration period in days for imported streams and cached images'), intval(get_config('system', 'default_expire_days', 60)), t('0 for no expiration of imported content')],
            '$active_expire_days' => ['active_expire_days', t('Do not expire any posts which have comments less than this many days ago'), intval(get_config('system', 'active_expire_days', 7)), ''],
            '$sellpage' => ['site_sellpage', t('Public servers: Optional landing (marketing) webpage for new registrants'), get_config('system', 'sellpage', ''), sprintf(t('Create this page first. Default is %s/register'), z_root())],
            '$first_page' => ['first_page', t('Page to display after creating a new channel'), get_config('system', 'workflow_channel_next', 'settings/profile_edit'), t('Default: profiles')],
            '$location' => ['site_location', t('Site location'), get_config('system', 'site_location', ''), t('Region or country - shared with other sites')],
            '$form_security_token' => get_form_security_token("admin_site"),
        ]);
    }
}
