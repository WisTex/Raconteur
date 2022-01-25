<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Access\PermissionLimits;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\Libprofile;
use Zotlabs\Lib\Channel;
use Zotlabs\Lib\Navbar;
use Zotlabs\Daemon\Run;
use Sabre\VObject\Reader;

class Profiles extends Controller
{

    public function init()
    {

        nav_set_selected('Profiles');

        if (!local_channel()) {
            return;
        }

        if ((argc() > 2) && (argv(1) === "drop") && intval(argv(2))) {
            $r = q(
                "SELECT * FROM profile WHERE id = %d AND uid = %d AND is_default = 0 LIMIT 1",
                intval(argv(2)),
                intval(local_channel())
            );
            if (!$r) {
                notice(t('Profile not found.') . EOL);
                goaway(z_root() . '/profiles');
            }
            $profile_guid = $r[0]['profile_guid'];

            check_form_security_token_redirectOnErr('/profiles', 'profile_drop', 't');

            // move every contact using this profile as their default to the user default

            $r = q(
                "UPDATE abook SET abook_profile = (SELECT profile_guid FROM profile WHERE is_default = 1 AND uid = %d LIMIT 1) WHERE abook_profile = '%s' AND abook_channel = %d ",
                intval(local_channel()),
                dbesc($profile_guid),
                intval(local_channel())
            );
            $r = q(
                "DELETE FROM profile WHERE id = %d AND uid = %d",
                intval(argv(2)),
                intval(local_channel())
            );
            if ($r) {
                info(t('Profile deleted.') . EOL);
            }

            // @fixme this is a much more complicated sync - add any changed abook entries and
            // also add deleted flag to profile structure
            // profiles_build_sync is just here as a placeholder - it doesn't work at all here

            // Channel::profiles_build_sync(local_channel());

            goaway(z_root() . '/profiles');
            return; // NOTREACHED
        }


        if ((argc() > 1) && (argv(1) === 'new')) {
            //      check_form_security_token_redirectOnErr('/profiles', 'profile_new', 't');

            $r0 = q(
                "SELECT id FROM profile WHERE uid = %d",
                intval(local_channel())
            );
            $num_profiles = count($r0);

            $name = t('Profile-') . ($num_profiles + 1);

            $r1 = q(
                "SELECT fullname, photo, thumb FROM profile WHERE uid = %d AND is_default = 1 LIMIT 1",
                intval(local_channel())
            );

            $r2 = Channel::profile_store_lowlevel(
                [
                    'aid' => intval(get_account_id()),
                    'uid' => intval(local_channel()),
                    'profile_guid' => new_uuid(),
                    'profile_name' => $name,
                    'fullname' => $r1[0]['fullname'],
                    'photo' => $r1[0]['photo'],
                    'thumb' => $r1[0]['thumb']
                ]
            );

            $r3 = q(
                "SELECT id FROM profile WHERE uid = %d AND profile_name = '%s' LIMIT 1",
                intval(local_channel()),
                dbesc($name)
            );

            info(t('New profile created.') . EOL);
            if (count($r3) == 1) {
                goaway(z_root() . '/profiles/' . $r3[0]['id']);
            }

            goaway(z_root() . '/profiles');
        }

        if ((argc() > 2) && (argv(1) === 'clone')) {
            check_form_security_token_redirectOnErr('/profiles', 'profile_clone', 't');

            $r0 = q(
                "SELECT id FROM profile WHERE uid = %d",
                intval(local_channel())
            );
            $num_profiles = count($r0);

            $name = t('Profile-') . ($num_profiles + 1);
            $r1 = q(
                "SELECT * FROM profile WHERE uid = %d AND id = %d LIMIT 1",
                intval(local_channel()),
                intval(argv(2))
            );
            if (!count($r1)) {
                notice(t('Profile unavailable to clone.') . EOL);
                App::$error = 404;
                return;
            }
            unset($r1[0]['id']);
            $r1[0]['is_default'] = 0;
            $r1[0]['publish'] = 0;
            $r1[0]['profile_name'] = $name;
            $r1[0]['profile_guid'] = new_uuid();

            create_table_from_array('profile', $r1[0]);

            $r3 = q(
                "SELECT id FROM profile WHERE uid = %d AND profile_name = '%s' LIMIT 1",
                intval(local_channel()),
                dbesc($name)
            );
            info(t('New profile created.') . EOL);

            Channel::profiles_build_sync(local_channel());

            if (($r3) && (count($r3) == 1)) {
                goaway(z_root() . '/profiles/' . $r3[0]['id']);
            }

            goaway(z_root() . '/profiles');

            return; // NOTREACHED
        }

        if ((argc() > 2) && (argv(1) === 'export')) {
            $r1 = q(
                "SELECT * FROM profile WHERE uid = %d AND id = %d LIMIT 1",
                intval(local_channel()),
                intval(argv(2))
            );
            if (!$r1) {
                notice(t('Profile unavailable to export.') . EOL);
                App::$error = 404;
                return;
            }
            header('content-type: application/octet_stream');
            header('Content-Disposition: attachment; filename="' . $r1[0]['profile_name'] . '.json"');

            unset($r1[0]['id']);
            unset($r1[0]['aid']);
            unset($r1[0]['uid']);
            unset($r1[0]['is_default']);
            unset($r1[0]['publish']);
            unset($r1[0]['profile_name']);
            unset($r1[0]['profile_guid']);
            echo json_encode($r1[0]);
            killme();
        }

        if (((argc() > 1) && (intval(argv(1)))) || !feature_enabled(local_channel(), 'multi_profiles')) {
            if (feature_enabled(local_channel(), 'multi_profiles')) {
                $id = argv(1);
            } else {
                $x = q(
                    "select id from profile where uid = %d and is_default = 1",
                    intval(local_channel())
                );
                if ($x) {
                    $id = $x[0]['id'];
                }
            }
            $r = q(
                "SELECT * FROM profile WHERE id = %d AND uid = %d LIMIT 1",
                intval($id),
                intval(local_channel())
            );
            if (!count($r)) {
                notice(t('Profile not found.') . EOL);
                App::$error = 404;
                return;
            }

            $chan = App::get_channel();

            Libprofile::load($chan['channel_address'], $r[0]['profile_guid']);
        }
    }

    public function post()
    {

        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        $namechanged = false;

        // import from json export file.
        // Only import fields that are allowed on this hub

        if (x($_FILES, 'userfile')) {
            $src = $_FILES['userfile']['tmp_name'];
            $filesize = intval($_FILES['userfile']['size']);
            if ($filesize) {
                $j = @json_decode(@file_get_contents($src), true);
                @unlink($src);
                if ($j) {
                    $fields = Channel::get_profile_fields_advanced();
                    if ($fields) {
                        foreach ($j as $jj => $v) {
                            foreach ($fields as $f => $n) {
                                if ($jj == $f) {
                                    $_POST[$f] = $v;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        call_hooks('profile_post', $_POST);


        if ((argc() > 1) && (argv(1) !== "new") && intval(argv(1))) {
            $orig = q(
                "SELECT * FROM profile WHERE id = %d AND uid = %d LIMIT 1",
                intval(argv(1)),
                intval(local_channel())
            );
            if (!count($orig)) {
                notice(t('Profile not found.') . EOL);
                return;
            }

            check_form_security_token_redirectOnErr('/profiles', 'profile_edit');


            $is_default = (($orig[0]['is_default']) ? 1 : 0);

            $profile_name = notags(trim($_POST['profile_name']));
            if (!strlen($profile_name)) {
                notice(t('Profile Name is required.') . EOL);
                return;
            }

            $dob = $_POST['dob'] ? escape_tags(trim($_POST['dob'])) : '0000-00-00';

            $y = substr($dob, 0, 4);
            if ((!ctype_digit($y)) || ($y < 1900)) {
                $ignore_year = true;
            } else {
                $ignore_year = false;
            }

            if ($dob !== '0000-00-00') {
                if (strpos($dob, '0000-') === 0) {
                    $ignore_year = true;
                    $dob = substr($dob, 5);
                }
                $dob = datetime_convert('UTC', 'UTC', (($ignore_year) ? '1900-' . $dob : $dob), (($ignore_year) ? 'm-d' : 'Y-m-d'));
                if ($ignore_year) {
                    $dob = '0000-' . $dob;
                }
            }

            $name = escape_tags(trim($_POST['name']));

            if ($orig[0]['fullname'] != $name) {
                $namechanged = true;

                $v = Channel::validate_channelname($name);
                if ($v) {
                    notice($v);
                    $namechanged = false;
                    $name = $orig[0]['fullname'];
                }
            }

            $pdesc = escape_tags(trim($_POST['pdesc']));
            $gender = escape_tags(trim($_POST['gender']));
            $address = escape_tags(trim($_POST['address']));
            $locality = escape_tags(trim($_POST['locality']));
            $region = escape_tags(trim($_POST['region']));
            $postal_code = escape_tags(trim($_POST['postal_code']));
            $country_name = escape_tags(trim($_POST['country_name']));
            $keywords = escape_tags(trim($_POST['keywords']));
            $marital = escape_tags(trim($_POST['marital']));
            $howlong = escape_tags(trim($_POST['howlong']));
            $sexual = escape_tags(trim($_POST['sexual']));
            $pronouns = escape_tags(trim($_POST['pronouns']));
            $homepage = escape_tags(trim($_POST['homepage']));
            $hometown = escape_tags(trim($_POST['hometown']));
            $politic = escape_tags(trim($_POST['politic']));
            $religion = escape_tags(trim($_POST['religion']));

            $likes = escape_tags(trim($_POST['likes']));
            $dislikes = escape_tags(trim($_POST['dislikes']));

            $about = escape_tags(trim($_POST['about']));
            $interest = escape_tags(trim($_POST['interest']));
            $contact = escape_tags(trim($_POST['contact']));
            $channels = escape_tags(trim($_POST['channels']));
            $music = escape_tags(trim($_POST['music']));
            $book = escape_tags(trim($_POST['book']));
            $tv = escape_tags(trim($_POST['tv']));
            $film = escape_tags(trim($_POST['film']));
            $romance = escape_tags(trim($_POST['romance']));
            $work = escape_tags(trim($_POST['work']));
            $education = escape_tags(trim($_POST['education']));

            $hide_friends = ((intval($_POST['hide_friends'])) ? 1 : 0);

            // start fresh and create a new vcard.
            // @TODO: preserve the original guid or whatever else needs saving
            // $orig_vcard = (($orig[0]['profile_vcard']) ? Reader::read($orig[0]['profile_vcard']) : null);

            $orig_vcard = null;

            $channel = App::get_channel();

            $default_vcard_cat = ((defined('DEFAULT_VCARD_CAT')) ? DEFAULT_VCARD_CAT : 'HOME');

            $defcard = [
                'fn' => $name,
                'title' => $pdesc,
                'photo' => $channel['xchan_photo_l'],
                'adr' => [],
                'adr_type' => [$default_vcard_cat],
                'url' => [$homepage],
                'url_type' => [$default_vcard_cat]
            ];

            $defcard['adr'][] = [
                0 => '',
                1 => '',
                2 => $address,
                3 => $locality,
                4 => $region,
                5 => $postal_code,
                6 => $country_name
            ];

            $profile_vcard = update_vcard($defcard, $orig_vcard);

            $orig_vcard = Reader::read($profile_vcard);

            $profile_vcard = update_vcard($_REQUEST, $orig_vcard);


            require_once('include/text.php');
            linkify_tags($likes, local_channel());
            linkify_tags($dislikes, local_channel());
            linkify_tags($about, local_channel());
            linkify_tags($interest, local_channel());
            linkify_tags($interest, local_channel());
            linkify_tags($contact, local_channel());
            linkify_tags($channels, local_channel());
            linkify_tags($music, local_channel());
            linkify_tags($book, local_channel());
            linkify_tags($tv, local_channel());
            linkify_tags($film, local_channel());
            linkify_tags($romance, local_channel());
            linkify_tags($work, local_channel());
            linkify_tags($education, local_channel());


            $with = ((x($_POST, 'with')) ? escape_tags(trim($_POST['with'])) : '');

            if (!strlen($howlong)) {
                $howlong = NULL_DATE;
            } else {
                $howlong = datetime_convert(date_default_timezone_get(), 'UTC', $howlong);
            }

            // linkify the relationship target if applicable

            $withchanged = false;

            if (strlen($with)) {
                if ($with != strip_tags($orig[0]['partner'])) {
                    $withchanged = true;
                    $prf = '';
                    $lookup = $with;
                    if (strpos($lookup, '@') === 0) {
                        $lookup = substr($lookup, 1);
                    }
                    $lookup = str_replace('_', ' ', $lookup);
                    $newname = $lookup;

                    $r = q(
                        "SELECT * FROM abook left join xchan on abook_xchan = xchan_hash WHERE xchan_name = '%s' AND abook_channel = %d LIMIT 1",
                        dbesc($newname),
                        intval(local_channel())
                    );
                    if (!$r) {
                        $r = q(
                            "SELECT * FROM abook left join xchan on abook_xchan = xchan_hash WHERE xchan_addr = '%s' AND abook_channel = %d LIMIT 1",
                            dbesc($lookup . '@%'),
                            intval(local_channel())
                        );
                    }
                    if ($r) {
                        $prf = $r[0]['xchan_url'];
                        $newname = $r[0]['xchan_name'];
                    }


                    if ($prf) {
                        $with = str_replace($lookup, '<a href="' . $prf . '">' . $newname . '</a>', $with);
                        if (strpos($with, '@') === 0) {
                            $with = substr($with, 1);
                        }
                    }
                } else {
                    $with = $orig[0]['partner'];
                }
            }

            $profile_fields_basic = Channel::get_profile_fields_basic();
            $profile_fields_advanced = Channel::get_profile_fields_advanced();
            $advanced = ((feature_enabled(local_channel(), 'advanced_profiles')) ? true : false);
            if ($advanced) {
                $fields = $profile_fields_advanced;
            } else {
                $fields = $profile_fields_basic;
            }

            $z = q("select * from profdef where true");
            if ($z) {
                foreach ($z as $zz) {
                    if (array_key_exists($zz['field_name'], $fields)) {
                        $w = q(
                            "select * from profext where channel_id = %d and hash = '%s' and k = '%s' limit 1",
                            intval(local_channel()),
                            dbesc($orig[0]['profile_guid']),
                            dbesc($zz['field_name'])
                        );
                        if ($w) {
                            q(
                                "update profext set v = '%s' where id = %d",
                                dbesc(escape_tags(trim($_POST[$zz['field_name']]))),
                                intval($w[0]['id'])
                            );
                        } else {
                            q(
                                "insert into profext ( channel_id, hash, k, v ) values ( %d, '%s', '%s', '%s') ",
                                intval(local_channel()),
                                dbesc($orig[0]['profile_guid']),
                                dbesc($zz['field_name']),
                                dbesc(escape_tags(trim($_POST[$zz['field_name']])))
                            );
                        }
                    }
                }
            }

            $changes = [];
            $value = '';
            if ($is_default) {
                if ($marital != $orig[0]['marital']) {
                    $changes[] = '[color=#ff0000]&hearts;[/color] ' . t('Marital Status');
                    $value = $marital;
                }
                if ($withchanged) {
                    $changes[] = '[color=#ff0000]&hearts;[/color] ' . t('Romantic Partner');
                    $value = strip_tags($with);
                }
                if ($likes != $orig[0]['likes']) {
                    $changes[] = t('Likes');
                    $value = $likes;
                }
                if ($dislikes != $orig[0]['dislikes']) {
                    $changes[] = t('Dislikes');
                    $value = $dislikes;
                }
                if ($work != $orig[0]['employment']) {
                    $changes[] = t('Work/Employment');
                }
                if ($religion != $orig[0]['religion']) {
                    $changes[] = t('Religion');
                    $value = $religion;
                }
                if ($politic != $orig[0]['politic']) {
                    $changes[] = t('Political Views');
                    $value = $politic;
                }
                if ($gender != $orig[0]['gender']) {
                    $changes[] = t('Gender');
                    $value = $gender;
                }
                if ($sexual != $orig[0]['sexual']) {
                    $changes[] = t('Sexual Preference');
                    $value = $sexual;
                }
                if ($homepage != $orig[0]['homepage']) {
                    $changes[] = t('Homepage');
                    $value = $homepage;
                }
                if ($interest != $orig[0]['interest']) {
                    $changes[] = t('Interests');
                    $value = $interest;
                }
                if ($address != $orig[0]['address']) {
                    $changes[] = t('Address');
                    // New address not sent in notifications, potential privacy issues
                    // in case this leaks to unintended recipients. Yes, it's in the public
                    // profile but that doesn't mean we have to broadcast it to everybody.
                }
                if (
                    $locality != $orig[0]['locality'] || $region != $orig[0]['region']
                    || $country_name != $orig[0]['country_name']
                ) {
                    $changes[] = t('Location');
                    $comma1 = ((($locality) && ($region || $country_name)) ? ', ' : ' ');
                    $comma2 = (($region && $country_name) ? ', ' : '');
                    $value = $locality . $comma1 . $region . $comma2 . $country_name;
                }

                self::profile_activity($changes, $value);
            }

            $r = q(
                "UPDATE profile 
				SET profile_name = '%s',
				fullname = '%s',
				pdesc = '%s',
				gender = '%s',
				dob = '%s',
				address = '%s',
				locality = '%s',
				region = '%s',
				postal_code = '%s',
				country_name = '%s',
				marital = '%s',
				partner = '%s',
				howlong = '%s',
				sexual = '%s',
				pronouns = '%s',
				homepage = '%s',
				hometown = '%s',
				politic = '%s',
				religion = '%s',
				keywords = '%s',
				likes = '%s',
				dislikes = '%s',
				about = '%s',
				interest = '%s',
				contact = '%s',
				channels = '%s',
				music = '%s',
				book = '%s',
				tv = '%s',
				film = '%s',
				romance = '%s',
				employment = '%s',
				education = '%s',
				hide_friends = %d,
				profile_vcard = '%s'
				WHERE id = %d AND uid = %d",
                dbesc($profile_name),
                dbesc($name),
                dbesc($pdesc),
                dbesc($gender),
                dbesc($dob),
                dbesc($address),
                dbesc($locality),
                dbesc($region),
                dbesc($postal_code),
                dbesc($country_name),
                dbesc($marital),
                dbesc($with),
                dbesc($howlong),
                dbesc($sexual),
                dbesc($pronouns),
                dbesc($homepage),
                dbesc($hometown),
                dbesc($politic),
                dbesc($religion),
                dbesc($keywords),
                dbesc($likes),
                dbesc($dislikes),
                dbesc($about),
                dbesc($interest),
                dbesc($contact),
                dbesc($channels),
                dbesc($music),
                dbesc($book),
                dbesc($tv),
                dbesc($film),
                dbesc($romance),
                dbesc($work),
                dbesc($education),
                intval($hide_friends),
                dbesc($profile_vcard),
                intval(argv(1)),
                intval(local_channel())
            );

            if ($r) {
                info(t('Profile updated.') . EOL);
            }

            $sync = q(
                "select * from profile where id = %d and uid = %d limit 1",
                intval(argv(1)),
                intval(local_channel())
            );
            if ($sync) {
                Libsync::build_sync_packet(local_channel(), array('profile' => $sync));
            }

            if (Channel::is_system(local_channel())) {
                set_config('system', 'siteinfo', $about);
            }

            $channel = App::get_channel();

            if ($namechanged && $is_default) {
                $r = q(
                    "UPDATE xchan SET xchan_name = '%s', xchan_name_date = '%s' WHERE xchan_hash = '%s'",
                    dbesc($name),
                    dbesc(datetime_convert()),
                    dbesc($channel['xchan_hash'])
                );
                $r = q(
                    "UPDATE channel SET channel_name = '%s' WHERE channel_hash = '%s'",
                    dbesc($name),
                    dbesc($channel['xchan_hash'])
                );
                if (Channel::is_system(local_channel())) {
                    set_config('system', 'sitename', $name);
                }
            }

            if ($is_default) {
                Run::Summon(['Directory', local_channel()]);
                goaway(z_root() . '/profiles/' . $sync[0]['id']);
            }
        }
    }


    public function get()
    {

        $o = '';

        $channel = App::get_channel();

        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        require_once('include/channel.php');

        $profile_fields_basic = Channel::get_profile_fields_basic();
        $profile_fields_advanced = Channel::get_profile_fields_advanced();

        if (((argc() > 1) && (intval(argv(1)))) || !feature_enabled(local_channel(), 'multi_profiles')) {
            if (feature_enabled(local_channel(), 'multi_profiles')) {
                $id = argv(1);
            } else {
                $x = q(
                    "select id from profile where uid = %d and is_default = 1",
                    intval(local_channel())
                );
                if ($x) {
                    $id = $x[0]['id'];
                }
            }
            $r = q(
                "SELECT * FROM profile WHERE id = %d AND uid = %d LIMIT 1",
                intval($id),
                intval(local_channel())
            );
            if (!$r) {
                notice(t('Profile not found.') . EOL);
                return;
            }

            $editselect = 'none';

            App::$page['htmlhead'] .= replace_macros(get_markup_template('profed_head.tpl'), array(
                '$baseurl' => z_root(),
                '$editselect' => $editselect,
            ));

            $advanced = ((feature_enabled(local_channel(), 'advanced_profiles')) ? true : false);
            if ($advanced) {
                $fields = $profile_fields_advanced;
            } else {
                $fields = $profile_fields_basic;
            }

            $hide_friends = array(
                'hide_friends',
                t('Hide your connections list from viewers of this profile'),
                $r[0]['hide_friends'],
                '',
                array(t('No'), t('Yes'))
            );

            $q = q("select * from profdef where true");
            if ($q) {
                $extra_fields = [];

                foreach ($q as $qq) {
                    $mine = q(
                        "select v from profext where k = '%s' and hash = '%s' and channel_id = %d limit 1",
                        dbesc($qq['field_name']),
                        dbesc($r[0]['profile_guid']),
                        intval(local_channel())
                    );

                    if (array_key_exists($qq['field_name'], $fields)) {
                        $extra_fields[] = array($qq['field_name'], $qq['field_desc'], (($mine) ? $mine[0]['v'] : ''), $qq['field_help']);
                    }
                }
            }

            //logger('extra_fields: ' . print_r($extra_fields,true));

            $vc = $r[0]['profile_vcard'];
            $vctmp = (($vc) ? Reader::read($vc) : null);
            $vcard = (($vctmp) ? get_vcard_array($vctmp, $r[0]['id']) : []);

            $f = get_config('system', 'birthday_input_format');
            if (!$f) {
                $f = 'ymd';
            }

            $is_default = (($r[0]['is_default']) ? 1 : 0);

            $tpl = get_markup_template("profile_edit.tpl");
            $o .= replace_macros($tpl, array(
                '$multi_profiles' => ((feature_enabled(local_channel(), 'multi_profiles')) ? true : false),
                '$form_security_token' => get_form_security_token("profile_edit"),
                '$profile_clone_link' => 'profiles/clone/' . $r[0]['id'] . '?t=' . get_form_security_token("profile_clone"),
                '$profile_drop_link' => 'profiles/drop/' . $r[0]['id'] . '?t=' . get_form_security_token("profile_drop"),
                '$fields' => $fields,
                '$vcard' => $vcard,
                '$guid' => $r[0]['profile_guid'],
                '$banner' => t('Edit Profile Details'),
                '$submit' => t('Submit'),
                '$viewprof' => t('View this profile'),
                '$editvis' => t('Edit visibility'),
                '$tools_label' => t('Profile Tools'),
                '$coverpic' => t('Change cover photo'),
                '$profpic' => t('Change profile photo'),
                '$cr_prof' => t('Create a new profile using these settings'),
                '$cl_prof' => t('Clone this profile'),
                '$del_prof' => t('Delete this profile'),
                '$addthing' => t('Add profile things'),
                '$personal' => t('Personal'),
                '$location' => t('Location'),
                '$relation' => t('Relationship'),
                '$miscellaneous' => t('Miscellaneous'),
                '$exportable' => feature_enabled(local_channel(), 'profile_export'),
                '$lbl_import' => t('Import profile from file'),
                '$lbl_export' => t('Export profile to file'),
                '$lbl_gender' => t('Your gender'),
                '$lbl_marital' => t('Marital status'),
                '$lbl_sexual' => t('Sexual preference'),
                '$lbl_pronouns' => t('Pronouns'),
                '$baseurl' => z_root(),
                '$profile_id' => $r[0]['id'],
                '$profile_name' => array('profile_name', t('Profile name'), $r[0]['profile_name'], t('Required'), '*'),
                '$is_default' => $is_default,
                '$default' => '', // t('This is your default profile.') . EOL . translate_scope(map_scope(\Zotlabs\Access\PermissionLimits::Get($channel['channel_id'],'view_profile'))),
                '$advanced' => $advanced,
                '$name' => array('name', t('Your full name'), $r[0]['fullname'], t('Required'), '*'),
                '$pdesc' => array('pdesc', t('Title/Description'), $r[0]['pdesc']),
                '$dob' => dob($r[0]['dob']),
                '$hide_friends' => $hide_friends,
                '$address' => array('address', t('Street address'), $r[0]['address']),
                '$locality' => array('locality', t('Locality/City'), $r[0]['locality']),
                '$region' => array('region', t('Region/State'), $r[0]['region']),
                '$postal_code' => array('postal_code', t('Postal/Zip code'), $r[0]['postal_code']),
                '$country_name' => array('country_name', t('Country'), $r[0]['country_name']),
                '$gender' => self::gender_selector($r[0]['gender']),
                '$gender_min' => self::gender_selector_min($r[0]['gender']),
                '$gender_text' => self::gender_text($r[0]['gender']),
                '$marital' => self::marital_selector($r[0]['marital']),
                '$marital_min' => self::marital_selector_min($r[0]['marital']),
                '$with' => array('with', t("Who (if applicable)"), $r[0]['partner'], t('Examples: cathy123, Cathy Williams, cathy@example.com')),
                '$howlong' => array('howlong', t('Since (date)'), ($r[0]['howlong'] <= NULL_DATE ? '' : datetime_convert('UTC', date_default_timezone_get(), $r[0]['howlong']))),
                '$sexual' => self::sexpref_selector($r[0]['sexual']),
                '$sexual_min' => self::sexpref_selector_min($r[0]['sexual']),
                '$pronouns' => self::pronouns_selector($r[0]['pronouns']),
                '$pronouns_min' => self::pronouns_selector($r[0]['pronouns']),
                '$about' => array('about', t('Tell us about yourself'), $r[0]['about']),
                '$homepage' => array('homepage', t('Homepage URL'), $r[0]['homepage']),
                '$hometown' => array('hometown', t('Hometown'), $r[0]['hometown']),
                '$politic' => array('politic', t('Political views'), $r[0]['politic']),
                '$religion' => array('religion', t('Religious views'), $r[0]['religion']),
                '$keywords' => array('keywords', t('Keywords used in directory listings'), $r[0]['keywords'], t('Example: fishing photography software')),
                '$likes' => array('likes', t('Likes'), $r[0]['likes']),
                '$dislikes' => array('dislikes', t('Dislikes'), $r[0]['dislikes']),
                '$music' => array('music', t('Musical interests'), $r[0]['music']),
                '$book' => array('book', t('Books, literature'), $r[0]['book']),
                '$tv' => array('tv', t('Television'), $r[0]['tv']),
                '$film' => array('film', t('Film/Dance/Culture/Entertainment'), $r[0]['film']),
                '$interest' => array('interest', t('Hobbies/Interests'), $r[0]['interest']),
                '$romance' => array('romance', t('Love/Romance'), $r[0]['romance']),
                '$employ' => array('work', t('Work/Employment'), $r[0]['employment']),
                '$education' => array('education', t('School/Education'), $r[0]['education']),
                '$contact' => array('contact', t('Contact information and social networks'), $r[0]['contact']),
                '$channels' => array('channels', t('My other channels'), $r[0]['channels']),
                '$extra_fields' => $extra_fields,
                '$comms' => t('Communications'),
                '$tel_label' => t('Phone'),
                '$email_label' => t('Email'),
                '$impp_label' => t('Instant messenger'),
                '$url_label' => t('Website'),
                '$adr_label' => t('Address'),
                '$note_label' => t('Note'),
                '$mobile' => t('Mobile'),
                '$home' => t('Home'),
                '$work' => t('Work'),
                '$other' => t('Other'),
                '$add_card' => t('Add Contact'),
                '$add_field' => t('Add Field'),
                '$create' => t('Create'),
                '$update' => t('Update'),
                '$delete' => t('Delete'),
                '$cancel' => t('Cancel'),
            ));

            $arr = array('profile' => $r[0], 'entry' => $o);
            call_hooks('profile_edit', $arr);

            return $o;
        } else {
            $r = q(
                "SELECT * FROM profile WHERE uid = %d",
                local_channel()
            );
            if ($r) {
                $tpl = get_markup_template('profile_entry.tpl');
                foreach ($r as $rr) {
                    $profiles .= replace_macros($tpl, array(
                        '$photo' => $rr['thumb'],
                        '$id' => $rr['id'],
                        '$alt' => t('Profile Image'),
                        '$profile_name' => $rr['profile_name'],
                        '$visible' => (($rr['is_default'])
                            ? '<strong>' . translate_scope(map_scope(PermissionLimits::Get($channel['channel_id'], 'view_profile'))) . '</strong>'
                            : '<a href="' . z_root() . '/profperm/' . $rr['id'] . '" />' . t('Edit visibility') . '</a>')
                    ));
                }

                $tpl_header = get_markup_template('profile_listing_header.tpl');
                $o .= replace_macros($tpl_header, array(
                    '$header' => t('Edit Profiles'),
                    '$cr_new' => t('Create New'),
                    '$cr_new_link' => 'profiles/new?t=' . get_form_security_token("profile_new"),
                    '$profiles' => $profiles
                ));
            }
            return $o;
        }
    }

    public static function profile_activity($changed, $value)
    {

        if (!local_channel() || !is_array($changed) || !count($changed)) {
            return;
        }

        if (!get_pconfig(local_channel(), 'system', 'post_profilechange')) {
            return;
        }

        $self = App::get_channel();

        if (!$self) {
            return;
        }

        $arr = [];
        $uuid = new_uuid();
        $mid = z_root() . '/item/' . $uuid;

        $arr['uuid'] = $uuid;
        $arr['mid'] = $arr['parent_mid'] = $mid;
        $arr['uid'] = local_channel();
        $arr['aid'] = $self['channel_account_id'];
        $arr['owner_xchan'] = $arr['author_xchan'] = $self['xchan_hash'];

        $arr['item_wall'] = 1;
        $arr['item_origin'] = 1;
        $arr['item_thread_top'] = 1;
        $arr['verb'] = ACTIVITY_UPDATE;
        $arr['obj_type'] = ACTIVITY_OBJ_PROFILE;

        $arr['plink'] = z_root() . '/channel/' . $self['channel_address'] . '/?f=&mid=' . urlencode($arr['mid']);

        $A = '[url=' . z_root() . '/channel/' . $self['channel_address'] . ']' . $self['channel_name'] . '[/url]';


        $changes = '';
        $t = count($changed);
        $z = 0;
        foreach ($changed as $ch) {
            if (strlen($changes)) {
                if ($z == ($t - 1)) {
                    $changes .= t(' and ');
                } else {
                    $changes .= t(', ');
                }
            }
            $z++;
            $changes .= $ch;
        }

        $prof = '[url=' . z_root() . '/profile/' . $self['channel_address'] . ']' . t('public profile') . '[/url]';

        if ($t == 1 && strlen($value)) {
            // if it's a url, the HTML quotes will mess it up, so link it and don't try and zidify it because we don't know what it points to.
            $value = preg_replace_callback("/([^\]\='" . '"' . "]|^|\#\^)(https?\:\/\/[a-zA-Z0-9\pL\:\/\-\?\&\;\.\=\@\_\~\#\%\$\!\+\,]+)/ismu", 'red_zrl_callback', $value);
            // take out the bookmark indicator
            if (substr($value, 0, 2) === '#^') {
                $value = str_replace('#^', '', $value);
            }

            $message = sprintf(t('%1$s changed %2$s to &ldquo;%3$s&rdquo;'), $A, $changes, $value);
            $message .= "\n\n" . sprintf(t('Visit %1$s\'s %2$s'), $A, $prof);
        } else {
            $message = sprintf(t('%1$s has an updated %2$s, changing %3$s.'), $A, $prof, $changes);
        }

        $arr['body'] = $message;

        $arr['obj'] = [
            'type' => ACTIVITY_OBJ_PROFILE,
            'summary' => bbcode($message),
            'source' => ['mediaType' => 'text/x-multicode', 'summary' => $message],
            'id' => $self['xchan_url'],
            'url' => z_root() . '/profile/' . $self['channel_address']
        ];


        $arr['allow_cid'] = $self['channel_allow_cid'];
        $arr['allow_gid'] = $self['channel_allow_gid'];
        $arr['deny_cid'] = $self['channel_deny_cid'];
        $arr['deny_gid'] = $self['channel_deny_gid'];

        $res = item_store($arr);
        $i = $res['item_id'];

        if ($i) {
            // FIXME - limit delivery in notifier.php to those specificed in the perms argument
            Run::Summon(['Notifier', 'activity', $i, 'PERMS_R_PROFILE']);
        }
    }

    public static function gender_selector($current = "", $suffix = "")
    {
        $o = '';
        $select = array('', t('Male'), t('Female'), t('Currently Male'), t('Currently Female'), t('Mostly Male'), t('Mostly Female'), t('Transgender'), t('Intersex'), t('Transsexual'), t('Hermaphrodite'), t('Neuter'), t('Non-specific'), t('Other'), t('Undecided'));

        call_hooks('gender_selector', $select);

        $o .= "<select class=\"form-control\" name=\"gender$suffix\" id=\"gender-select$suffix\" size=\"1\" >";
        foreach ($select as $selection) {
            if ($selection !== 'NOTRANSLATION') {
                $selected = (($selection == $current) ? ' selected="selected" ' : '');
                $o .= "<option value=\"$selection\" $selected >$selection</option>";
            }
        }
        $o .= '</select>';
        return $o;
    }

    public static function gender_selector_min($current = "", $suffix = "")
    {
        $o = '';
        $select = array('', t('Male'), t('Female'), t('Other'));

        call_hooks('gender_selector_min', $select);

        $o .= "<select class=\"form-control\" name=\"gender$suffix\" id=\"gender-select$suffix\" size=\"1\" >";
        foreach ($select as $selection) {
            if ($selection !== 'NOTRANSLATION') {
                $selected = (($selection == $current) ? ' selected="selected" ' : '');
                $o .= "<option value=\"$selection\" $selected >$selection</option>";
            }
        }
        $o .= '</select>';
        return $o;
    }

    public static function pronouns_selector($current = "", $suffix = "")
    {
        $o = '';
        $select = array('', t('He/Him'), t('She/Her'), t('They/Them'));

        call_hooks('pronouns_selector', $select);

        $o .= "<select class=\"form-control\" name=\"pronouns$suffix\" id=\"pronouns-select$suffix\" size=\"1\" >";
        foreach ($select as $selection) {
            if ($selection !== 'NOTRANSLATION') {
                $selected = (($selection == $current) ? ' selected="selected" ' : '');
                $o .= "<option value=\"$selection\" $selected >$selection</option>";
            }
        }
        $o .= '</select>';
        return $o;
    }


    public static function gender_text($current = "", $suffix = "")
    {
        $o = '';

        if (!get_config('system', 'profile_gender_textfield')) {
            return $o;
        }

        $o .= "<input type = \"text\" class=\"form-control\" name=\"gender$suffix\" id=\"gender-select$suffix\" value=\"" . urlencode($current) . "\" >";
        return $o;
    }


    public static function sexpref_selector($current = "", $suffix = "")
    {
        $o = '';
        $select = array('', t('Males'), t('Females'), t('Gay'), t('Lesbian'), t('No Preference'), t('Bisexual'), t('Autosexual'), t('Abstinent'), t('Virgin'), t('Deviant'), t('Fetish'), t('Oodles'), t('Nonsexual'));


        call_hooks('sexpref_selector', $select);

        $o .= "<select class=\"form-control\" name=\"sexual$suffix\" id=\"sexual-select$suffix\" size=\"1\" >";
        foreach ($select as $selection) {
            if ($selection !== 'NOTRANSLATION') {
                $selected = (($selection == $current) ? ' selected="selected" ' : '');
                $o .= "<option value=\"$selection\" $selected >$selection</option>";
            }
        }
        $o .= '</select>';
        return $o;
    }


    public static function sexpref_selector_min($current = "", $suffix = "")
    {
        $o = '';
        $select = array('', t('Males'), t('Females'), t('Other'));

        call_hooks('sexpref_selector_min', $select);

        $o .= "<select class=\"form-control\" name=\"sexual$suffix\" id=\"sexual-select$suffix\" size=\"1\" >";
        foreach ($select as $selection) {
            if ($selection !== 'NOTRANSLATION') {
                $selected = (($selection == $current) ? ' selected="selected" ' : '');
                $o .= "<option value=\"$selection\" $selected >$selection</option>";
            }
        }
        $o .= '</select>';
        return $o;
    }


    public static function marital_selector($current = "", $suffix = "")
    {
        $o = '';
        $select = array('', t('Single'), t('Lonely'), t('Available'), t('Unavailable'), t('Has crush'), t('Infatuated'), t('Dating'), t('Unfaithful'), t('Sex Addict'), t('Friends'), t('Friends/Benefits'), t('Casual'), t('Engaged'), t('Married'), t('Imaginarily married'), t('Partners'), t('Cohabiting'), t('Common law'), t('Happy'), t('Not looking'), t('Swinger'), t('Betrayed'), t('Separated'), t('Unstable'), t('Divorced'), t('Imaginarily divorced'), t('Widowed'), t('Uncertain'), t('It\'s complicated'), t('Don\'t care'), t('Ask me'));

        call_hooks('marital_selector', $select);

        $o .= "<select class=\"form-control\" name=\"marital\" id=\"marital-select\" size=\"1\" >";
        foreach ($select as $selection) {
            if ($selection !== 'NOTRANSLATION') {
                $selected = (($selection == $current) ? ' selected="selected" ' : '');
                $o .= "<option value=\"$selection\" $selected >$selection</option>";
            }
        }
        $o .= '</select>';
        return $o;
    }

    public static function marital_selector_min($current = "", $suffix = "")
    {
        $o = '';
        $select = array('', t('Single'), t('Dating'), t('Cohabiting'), t('Married'), t('Separated'), t('Divorced'), t('Widowed'), t('It\'s complicated'), t('Other'));

        call_hooks('marital_selector_min', $select);

        $o .= "<select class=\"form-control\" name=\"marital\" id=\"marital-select\" size=\"1\" >";
        foreach ($select as $selection) {
            if ($selection !== 'NOTRANSLATION') {
                $selected = (($selection == $current) ? ' selected="selected" ' : '');
                $o .= "<option value=\"$selection\" $selected >$selection</option>";
            }
        }
        $o .= '</select>';
        return $o;
    }
}
