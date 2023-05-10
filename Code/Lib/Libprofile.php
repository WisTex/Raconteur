<?php

namespace Code\Lib;

use App;
use Code\Extend\Hook;
use Code\Render\Theme;
use Code\Render\Comanche;
        
class Libprofile
{


    /**
     * @brief Loads a profile into the App structure.
     *
     * The function requires the nickname of a valid channel.
     *
     * Permissions of the current observer are checked. If a restricted profile is available
     * to the current observer, that will be loaded instead of the channel default profile.
     *
     * The channel owner can set $profile to a valid profile_guid to preview that profile.
     *
     * The channel default theme is also selected for use, unless over-riden elsewhere.
     *
     * @param string $nickname
     * @param string $profile (guid)
     */

    public static function load($nickname, $profile = '')
    {

        //  logger('Libprofile::load: ' . $nickname . (($profile) ? ' profile: ' . $profile : ''));

        $channel = Channel::from_username($nickname);

        if (!$channel) {
            logger('profile error: ' . App::$query_string, LOGGER_DEBUG);
            notice(t('Requested channel is not available.') . EOL);
            App::$error = 404;
            return;
        }

        // get the current observer
        $observer = App::get_observer();

        $can_view_profile = true;

        // Can the observer see our profile?
        require_once('include/permissions.php');
        if (!perm_is_allowed($channel['channel_id'], (($observer) ? $observer['xchan_hash'] : ''), 'view_profile')) {
            $can_view_profile = false;
        }

        if (!$profile) {
            $r = q(
                "SELECT abook_profile FROM abook WHERE abook_xchan = '%s' and abook_channel = %d limit 1",
                dbesc(($observer) ? $observer['xchan_hash'] : ''),
                intval($channel['channel_id'])
            );
            if ($r) {
                $profile = $r[0]['abook_profile'];
            }
        }

        $p = null;

        if ($profile) {
            $p = q(
                "SELECT profile.uid AS profile_uid, profile.*, channel.* FROM profile
				LEFT JOIN channel ON profile.uid = channel.channel_id
				WHERE channel.channel_address = '%s' AND profile.profile_guid = '%s' LIMIT 1",
                dbesc($nickname),
                dbesc($profile)
            );
            if (!$p) {
                $p = q(
                    "SELECT profile.uid AS profile_uid, profile.*, channel.* FROM profile
					LEFT JOIN channel ON profile.uid = channel.channel_id
					WHERE channel.channel_address = '%s' AND profile.id = %d LIMIT 1",
                    dbesc($nickname),
                    intval($profile)
                );
            }
        }

        if (!$p) {
            $p = q(
                "SELECT profile.uid AS profile_uid, profile.*, channel.* FROM profile
				LEFT JOIN channel ON profile.uid = channel.channel_id
				WHERE channel.channel_address = '%s' and channel_removed = 0
				AND profile.is_default = 1 LIMIT 1",
                dbesc($nickname)
            );
        }

        if (!$p) {
            logger('profile error: ' . App::$query_string, LOGGER_DEBUG);
            notice(t('Requested profile is not available.') . EOL);
            App::$error = 404;
            return;
        }

        $q = q(
            "select * from profext where hash = '%s' and channel_id = %d",
            dbesc($p[0]['profile_guid']),
            intval($p[0]['profile_uid'])
        );
        if ($q) {
            $extra_fields = [];

            $profile_fields_basic = Channel::get_profile_fields_basic();
            $profile_fields_advanced = Channel::get_profile_fields_advanced();

            $advanced = (bool)Features::enabled(local_channel(), 'advanced_profiles');
            if ($advanced) {
                $fields = $profile_fields_advanced;
            } else {
                $fields = $profile_fields_basic;
            }

            foreach ($q as $qq) {
                foreach ($fields as $k => $f) {
                    if ($k == $qq['k']) {
                        $p[0][$k] = $qq['v'];
                        $extra_fields[] = $k;
                        break;
                    }
                }
            }
        }

        $p[0]['extra_fields'] = ((isset($extra_fields)) ? $extra_fields : []);

        $z = q(
            "select xchan_photo_date, xchan_addr from xchan where xchan_hash = '%s' limit 1",
            dbesc($p[0]['channel_hash'])
        );
        if ($z) {
            $p[0]['picdate'] = $z[0]['xchan_photo_date'];
            $p[0]['reddress'] = str_replace('@', '&#x40;', unpunify($z[0]['xchan_addr']));
        }

        // fetch user tags if this isn't the default profile

        if (!$p[0]['is_default']) {
            $x = q(
                "select keywords from profile where uid = %d and is_default = 1 limit 1",
                intval($p[0]['profile_uid'])
            );
            if ($x && $can_view_profile) {
                $p[0]['keywords'] = $x[0]['keywords'];
            }
        }

        if ($p[0]['keywords']) {
            $keywords = str_replace(['#', ',', ' ', ',,'], ['', ' ', ',', ','], $p[0]['keywords']);
            if (strlen($keywords) && $can_view_profile) {
                if (!isset(App::$page['htmlhead'])) {
                    App::$page['htmlhead'] = EMPTY_STR;
                }
                App::$page['htmlhead'] .= '<meta name="keywords" content="' . htmlentities($keywords, ENT_COMPAT, 'UTF-8') . '" />' . "\r\n";
            }
        }

        App::$profile = $p[0];
        App::$profile_uid = $p[0]['profile_uid'];
        App::$page['title'] = App::$profile['channel_name'] . " - " . unpunify(Channel::get_webfinger(App::$profile));

        App::$profile['permission_to_view'] = $can_view_profile;

        if ($can_view_profile) {
            $online = Channel::get_online_status($nickname);
            App::$profile['online_status'] = $online['result'];
        }

        if (local_channel()) {
            App::$profile['channel_mobile_theme'] = get_pconfig(local_channel(), 'system', 'mobile_theme');
            $_SESSION['mobile_theme'] = App::$profile['channel_mobile_theme'];
        }

        /*
         * load/reload current theme info
         */

        //  $_SESSION['theme'] = $p[0]['channel_theme'];
    }

    public static function edit_menu($uid)
    {

        $ret = [];

        $is_owner = $uid == local_channel();

        // show edit profile to profile owner
        if ($is_owner) {
            $ret['menu'] = [
                'chg_photo' => t('Change profile photo'),
                'entries' => [],
            ];

            $ret['edit'] = [ z_root() . '/settings/profile_edit', t('Edit Profile'), '', t('Edit')];

            $r = q(
                "SELECT * FROM profile WHERE uid = %d",
                local_channel()
            );

            if ($r) {
                foreach ($r as $rr) {
                    if (!$rr['is_default']) {
                        continue;
                    }

                    $ret['menu']['entries'][] = [
                        'photo' => $rr['thumb'],
                        'id' => $rr['id'],
                        'alt' => t('Profile Image'),
                        'profile_name' => $rr['profile_name'],
                        'isdefault' => $rr['is_default'],
                        'visible_to_everybody' => t('Visible to everybody'),
                        'edit_visibility' => t('Edit visibility'),
                    ];
                }
            }
        }

        return $ret;
    }

    /**
     * @brief Formats a profile for display in the sidebar.
     *
     * It is very difficult to templatise the HTML completely
     * because of all the conditional logic.
     *
     * @param array $profile
     * @param int $block
     * @param bool $show_connect (optional) default true
     * @param mixed $zcard (optional) default false
     *
     * @return string suitable for sidebar inclusion
     * Exceptions: Returns empty string if passed $profile is wrong type or not populated
     */

    public static function widget($profile, $block = 0, $show_connect = true, $zcard = false)
    {

        $observer = App::get_observer();

        $o = '';
        $location = false;
        $pdesc = true;
        $reddress = true;

        if (!perm_is_allowed($profile['uid'], ((is_array($observer)) ? $observer['xchan_hash'] : ''), 'view_profile')) {
            $block = true;
        }

        if ((!is_array($profile)) && (!count($profile))) {
            return $o;
        }

        head_set_icon($profile['thumb']);

        if (Channel::is_system($profile['uid'])) {
            $show_connect = false;
        }

        $profile['picdate'] = urlencode($profile['picdate']);

        /**
         * @hooks profile_sidebar_enter
         *   Called before generating the 'channel sidebar' or mini-profile.
         */
        Hook::call('profile_sidebar_enter', $profile);

		$profdm = EMPTY_STR;
		$profdm_url = EMPTY_STR;

        $xchan = q("SELECT * from xchan where xchan_hash = '%s'",
            dbesc($profile['channel_hash'])
        );
        $is_group = ($xchan && intval($xchan[0]['xchan_type']) === XCHAN_TYPE_GROUP);
		
        $can_dm = perm_is_allowed($profile['uid'], (is_array($observer)) ? $observer['xchan_hash'] : EMPTY_STR, 'post_mail') && $is_group ;

		if (intval($profile['uid']) === local_channel()) {
			$can_dm = false;
		}
		
	    if ($can_dm) {			
			$dm_path = Libzot::get_rpost_path($observer);
			if ($dm_path) {
				$profdm = t('Direct Message');
				$profdm_url = $dm_path
				. '&to='
				. urlencode($profile['channel_hash'])
				. '&body='
				. urlencode('@!{' . $profile['channel_address'] . '@' . App::get_hostname() . '}');
			}
	  	}

        if ($show_connect) {
            // This will return an empty string if we're already connected.

            $connect_url = rconnect_url($profile['uid'], get_observer_hash());
            $connect = (($connect_url) ? t('Connect') : '');
            if ($connect && $is_group) {
                $connect = t('Join');
            }
            if ($connect_url) {
                $connect_url = sprintf($connect_url, urlencode(Channel::get_webfinger($profile)));
            }

            // premium channel - over-ride

            if ($profile['channel_pageflags'] & PAGE_PREMIUM) {
                $connect_url = z_root() . '/connect/' . $profile['channel_address'];
            }
        }

        if (
            (x($profile, 'address') == 1)
            || (x($profile, 'locality') == 1)
            || (x($profile, 'region') == 1)
            || (x($profile, 'postal_code') == 1)
            || (x($profile, 'country_name') == 1)
        ) {
            $location = t('Location:');
        }

        $profile['homepage'] = linkify($profile['homepage'], true);

        $gender = ((x($profile, 'gender') == 1) ? t('Gender:') : false);
        $marital = ((x($profile, 'marital') == 1) ? t('Status:') : false);
        $homepage = ((x($profile, 'homepage') == 1) ? t('Homepage:') : false);
        $pronouns = ((x($profile, 'pronouns') == 1) ? t('Pronouns:') : false);

        // zap/osada do not have a realtime chat system at this time so don't show online state
        //  $profile['online']   = (($profile['online_status'] === 'online') ? t('Online Now') : False);
        //  logger('online: ' . $profile['online']);

        $profile['online'] = false;

        if (($profile['hidewall'] && (!local_channel()) && (!remote_channel())) || $block) {
            $location = $reddress = $pdesc = $gender = $marital = $homepage = false;
        }

        if ($profile['gender']) {
            $profile['gender_icon'] = self::gender_icon($profile['gender']);
        }

        if ($profile['pronouns']) {
            $profile['pronouns_icon'] = self::pronouns_icon($profile['pronouns']);
        }

        $firstname = ((strpos($profile['channel_name'], ' '))
            ? trim(substr($profile['channel_name'], 0, strpos($profile['channel_name'], ' '))) : $profile['channel_name']);
        $lastname = (($firstname === $profile['channel_name']) ? '' : trim(substr($profile['channel_name'], strlen($firstname))));


        $contact_block = contact_block();

        $channel_menu = false;
        $menu = get_pconfig($profile['uid'], 'system', 'channel_menu');
        if ($menu && !$block) {
            $m = Menu::fetch($menu, $profile['uid'], $observer['xchan_hash']);
            if ($m) {
                $channel_menu = Menu::render($m);
            }
        }
        $menublock = get_pconfig($profile['uid'], 'system', 'channel_menublock');
        if ($menublock && (!$block)) {
            $comanche = new Comanche();
            $channel_menu .= $comanche->block($menublock);
        }

        $clones = '';
        $identities = '';
        $pconfigs = PConfig::Get($profile['uid'], 'system','identities', []);
        $ids = q("select * from linkid where ident = '%s' and sigtype = %d",
            dbesc($profile['channel_hash']),
            intval(IDLINK_RELME)
        );
        if (PConfig::Get($profile['uid'],'system','nomadic_ids_in_profile', true)) {
            $clones = Libzot::encode_locations($profile);
        }
        if (($ids && $pconfigs) || $clones) {
            $identities .= '<table class="identity-table">';
            // style="">';
        }
        if ($clones) {
            foreach ($clones as $clone) {
                if (str_starts_with($clone['id_url'], z_root())) {
                    continue;
                }
                $identities .= replace_macros(Theme::get_template('identity.tpl'), [
                    '$identity' => [
                        escape_tags($clone['host']),
                        $clone['id_url'],
                        'check',
                        t('Verified'),
                        '_green'
                    ]
                ]);
            }
        }
        if ($ids && $pconfigs) {
            foreach ($pconfigs as $pconfig) {
                $matched = false;
                foreach ($ids as $id) {
                    if ($pconfig[1] === $id['link']) {
                        $matched = true;
                        $identities .= replace_macros(Theme::get_template('identity.tpl'), [
                            '$identity' => [
                                escape_tags($pconfig[0]),
                                $pconfig[1],
                                'check',
                                t('Verified'),
                                '_green'
                            ]
                        ]);
                    }
                }
                if (!$matched) {
                    $identities .= replace_macros(Theme::get_template('identity.tpl'), [
                        '$identity' => [
                            escape_tags($pconfig[0]),
                            $pconfig[1],
                            'close',
                            t('Not verified'),
                            '_red'
                        ]
                    ]);
                }
            }

        }
        if (($ids && $pconfigs) || $clones) {
            $identities .= '</table>' . EOL;
        }
        if ($zcard) {
            $tpl = Theme::get_template('profile_vcard_short.tpl');
        } else {
            $tpl = Theme::get_template('profile_vcard.tpl');
        }

        $o .= replace_macros($tpl, [
            '$zcard' => $zcard,
            '$profile' => $profile,
            '$connect' => $connect,
            '$connect_url' => $connect_url,
			'$profdm' => $profdm,
			'$profdm_url' => $profdm_url,
			'$location' => $location,
            '$gender' => $gender,
            '$pronouns' => $pronouns,
            '$pdesc' => $pdesc,
            '$marital' => $marital,
            '$homepage' => $homepage,
            '$chanmenu' => $channel_menu,
            '$reddress' => $reddress,
            '$active' => t('Active'),
            '$activewhen' => relative_date($profile['channel_lastpost']),
            '$identities' => $identities,
            '$ident_label' => t('Identities:'),
            '$contact_block' => $contact_block,
            '$change_photo' => t('Change your profile photo'),
            '$copyto' => t('Copy to clipboard'),
            '$copied' => t('Address copied to clipboard'),
            '$editmenu' => self::edit_menu($profile['uid'])
        ]);

        $arr = [
            'profile' => $profile,
            'entry' => $o
        ];

        /**
         * @hooks profile_sidebar
         *   Called when generating the 'channel sidebar' or mini-profile.
         *   * \e array \b profile
         *   * \e string \b entry - The parsed HTML template
         */
        Hook::call('profile_sidebar', $arr);

        return $arr['entry'];
    }

    public static function gender_icon($gender)
    {

        //  logger('gender: ' . $gender);

        // This can easily get throw off if the observer language is different
        // than the channel owner language.

        if (str_contains(strtolower($gender), strtolower(t('Female')))) {
            return 'venus';
        }
        if (str_contains(strtolower($gender), strtolower(t('Male')))) {
            return 'mars';
        }
        if (str_contains(strtolower($gender), strtolower(t('Trans')))) {
            return 'transgender';
        }
        if (str_contains(strtolower($gender), strtolower(t('Inter')))) {
            return 'transgender';
        }
        if (str_contains(strtolower($gender), strtolower(t('Neuter')))) {
            return 'neuter';
        }
        if (str_contains(strtolower($gender), strtolower(t('Non-specific')))) {
            return 'genderless';
        }

        return '';
    }

    public static function pronouns_icon($pronouns)
    {


        // This can easily get throw off if the observer language is different
        // than the channel owner language.

        if (str_contains(strtolower($pronouns), strtolower(t('She')))) {
            return 'venus';
        }
        if (str_contains(strtolower($pronouns), strtolower(t('Him')))) {
            return 'mars';
        }
        if (str_contains(strtolower($pronouns), strtolower(t('Them')))) {
            return 'users';
        }

        return '';
    }


    public static function advanced()
    {

        if (!perm_is_allowed(App::$profile['profile_uid'], get_observer_hash(), 'view_profile')) {
            return '';
        }

        if (App::$profile['fullname']) {
            $profile_fields_basic = Channel::get_profile_fields_basic();
            $profile_fields_advanced = Channel::get_profile_fields_advanced();

            $advanced = (bool)Features::enabled(App::$profile['profile_uid'], 'advanced_profiles');
            if ($advanced) {
                $fields = $profile_fields_advanced;
            } else {
                $fields = $profile_fields_basic;
            }

            $clean_fields = [];
            if ($fields) {
                foreach ($fields as $k => $v) {
                    $clean_fields[] = trim($k);
                }
            }


            $tpl = Theme::get_template('profile_advanced.tpl');

            $profile = [];

            $profile['fullname'] = [t('Full Name:'), App::$profile['fullname']];

            if (App::$profile['gender']) {
                $profile['gender'] = [t('Gender:'), App::$profile['gender']];
            }


            $ob_hash = get_observer_hash();
// this may not work at all any more, but definitely won't work correctly if the liked profile belongs to a group
// comment out until we are able to look at it much closer
//          if($ob_hash && perm_is_allowed(App::$profile['profile_uid'],$ob_hash,'post_like')) {
//              $profile['canlike'] = true;
//              $profile['likethis'] = t('Like this channel');
//              $profile['profile_guid'] = App::$profile['profile_guid'];
//          }

//          $likers = q("select liker, xchan.*  from likes left join xchan on liker = xchan_hash where channel_id = %d and target_type = '%s' and verb = '%s'",
//              intval(App::$profile['profile_uid']),
//              dbesc(ACTIVITY_OBJ_PROFILE),
//              dbesc(ACTIVITY_LIKE)
//          );
//          $profile['likers'] = [];
//          $profile['like_count'] = count($likers);
//          $profile['like_button_label'] = tt('Like','Likes',$profile['like_count'],'noun');

//          if($likers) {
//              foreach($likers as $l)
//                  $profile['likers'][] = array('name' => $l['xchan_name'],'photo' => zid($l['xchan_photo_s']), 'url' => zid($l['xchan_url']));
//          }

            if ((App::$profile['dob']) && (App::$profile['dob'] != '0000-00-00')) {
                $val = '';

                if ((substr(App::$profile['dob'], 5, 2) === '00') || (substr(App::$profile['dob'], 8, 2) === '00')) {
                    $val = substr(App::$profile['dob'], 0, 4);
                }

                $year_bd_format = t('j F, Y');
                $short_bd_format = t('j F');

                if (!$val) {
                    $val = ((intval(App::$profile['dob']))
                        ? day_translate(datetime_convert('UTC', 'UTC', App::$profile['dob'] . ' 00:00 +00:00', $year_bd_format))
                        : day_translate(datetime_convert('UTC', 'UTC', '2001-' . substr(App::$profile['dob'], 5) . ' 00:00 +00:00', $short_bd_format)));
                }
                $profile['birthday'] = [t('Birthday:'), $val];
            }

            if ($age = age(App::$profile['dob'], App::$profile['timezone'])) {
                $profile['age'] = [t('Age:'), $age];
            }

            if (App::$profile['marital']) {
                $profile['marital'] = [t('Status:'), App::$profile['marital']];
            }

            if (App::$profile['partner']) {
                $profile['marital']['partner'] = zidify_links(bbcode(App::$profile['partner']));
            }

            if (strlen(App::$profile['howlong']) && App::$profile['howlong'] > NULL_DATE) {
                $profile['howlong'] = relative_date(App::$profile['howlong'], t('for %1$d %2$s'));
            }

            if (App::$profile['keywords']) {
                $keywords = str_replace(',', ' ', App::$profile['keywords']);
                $keywords = str_replace('  ', ' ', $keywords);
                $karr = explode(' ', $keywords);
                if ($karr) {
                    for ($cnt = 0; $cnt < count($karr); $cnt++) {
                        $karr[$cnt] = '<a href="' . z_root() . '/directory/f=&keywords=' . trim($karr[$cnt]) . '">' . $karr[$cnt] . '</a>';
                    }
                }
                $profile['keywords'] = [t('Tags:'), implode(' ', $karr)];
            }


            if (App::$profile['sexual']) {
                $profile['sexual'] = [t('Sexual Preference:'), App::$profile['sexual']];
            }

            if (App::$profile['pronouns']) {
                $profile['pronouns'] = [t('Pronouns:'), App::$profile['pronouns']];
            }

            if (App::$profile['homepage']) {
                $profile['homepage'] = [t('Homepage:'), linkify(App::$profile['homepage'])];
            }

            if (App::$profile['hometown']) {
                $profile['hometown'] = [t('Hometown:'), linkify(App::$profile['hometown'])];
            }

            if (App::$profile['politic']) {
                $profile['politic'] = [t('Political Views:'), App::$profile['politic']];
            }

            if (App::$profile['religion']) {
                $profile['religion'] = [t('Religion:'), App::$profile['religion']];
            }

            if ($txt = prepare_text(App::$profile['about'])) {
                $profile['about'] = [t('About:'), $txt];
            }

            if ($txt = prepare_text(App::$profile['interest'])) {
                $profile['interest'] = [t('Hobbies/Interests:'), $txt];
            }

            if ($txt = prepare_text(App::$profile['likes'])) {
                $profile['likes'] = [t('Likes:'), $txt];
            }

            if ($txt = prepare_text(App::$profile['dislikes'])) {
                $profile['dislikes'] = [t('Dislikes:'), $txt];
            }

            if ($txt = prepare_text(App::$profile['contact'])) {
                $profile['contact'] = [t('Contact information and Social Networks:'), $txt];
            }

            if ($txt = prepare_text(App::$profile['channels'])) {
                $profile['channels'] = [t('My other channels:'), $txt];
            }

            if ($txt = prepare_text(App::$profile['music'])) {
                $profile['music'] = [t('Musical interests:'), $txt];
            }

            if ($txt = prepare_text(App::$profile['book'])) {
                $profile['book'] = [t('Books, literature:'), $txt];
            }

            if ($txt = prepare_text(App::$profile['tv'])) {
                $profile['tv'] = [t('Television:'), $txt];
            }

            if ($txt = prepare_text(App::$profile['film'])) {
                $profile['film'] = [t('Film/dance/culture/entertainment:'), $txt];
            }

            if ($txt = prepare_text(App::$profile['romance'])) {
                $profile['romance'] = [t('Love/Romance:'), $txt];
            }

            if ($txt = prepare_text(App::$profile['employment'])) {
                $profile['employment'] = [t('Work/employment:'), $txt];
            }

            if ($txt = prepare_text(App::$profile['education'])) {
                $profile['education'] = [t('School/education:'), $txt];
            }

            if (App::$profile['extra_fields']) {
                foreach (App::$profile['extra_fields'] as $f) {
                    $x = q(
                        "select * from profdef where field_name = '%s' limit 1",
                        dbesc($f)
                    );
                    if ($x && $txt = prepare_text(App::$profile[$f])) {
                        $profile[$f] = [$x[0]['field_desc'] . ':', $txt];
                    }
                }
                $profile['extra_fields'] = App::$profile['extra_fields'];
            }

            $things = get_things(App::$profile['profile_guid'], App::$profile['profile_uid']);


            //      logger('mod_profile: things: ' . print_r($things,true), LOGGER_DATA);

            //      $exportlink = ((App::$profile['profile_vcard']) ? zid(z_root() . '/profile/' . App::$profile['channel_address'] . '/vcard') : '');

            return replace_macros($tpl, [
                '$title' => t('Profile'),
                '$canlike' => (bool)$profile['canlike'],
                '$likethis' => t('Like this thing'),
                '$export' => t('Export'),
                '$exportlink' => '', // $exportlink,
                '$profile' => $profile,
                '$fields' => $clean_fields,
                '$editmenu' => self::edit_menu(App::$profile['profile_uid']),
                '$things' => $things
            ]);
        }

        return '';
    }
}
