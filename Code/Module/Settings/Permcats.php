<?php

namespace Code\Module\Settings;

use App;
use Code\Access\PermissionLimits;
use Code\Access\Permissions;
use Code\Lib\Libsync;
use Code\Lib\Permcat;
use Code\Render\Theme;


class Permcats
{

    public function post()
    {
        //logger('$_REQUEST: ' . print_r($_REQUEST,true));
    
        if (!local_channel()) {
            return;
        }

        $channel = App::get_channel();

        check_form_security_token_redirectOnErr('/settings/permcats', 'settings_permcats');


        $all_perms = Permissions::Perms();

        $name = escape_tags(trim($_POST['name']));
        if (!$name) {
            notice(t('Permission Name is required.') . EOL);
            return;
        }


        $pcarr = [];

        if ($all_perms) {
            foreach ($all_perms as $perm => $desc) {
                if (array_key_exists('perms_' . $perm, $_POST)) {
                    $pcarr[] = $perm;
                }
            }
        }

        Permcat::update(local_channel(), $name, $pcarr);

        Libsync::build_sync_packet();

        info(t('Permission role saved.') . EOL);

        return;
    }


    public function get()
    {

        if (!local_channel()) {
            return;
        }

        $channel = App::get_channel();


        if (argc() > 2) {
            $name = hex2bin(argv(2));
        }

        if (argc() > 3 && argv(3) === 'drop') {
            Permcat::delete(local_channel(), $name);
            Libsync::build_sync_packet();
            json_return_and_die(['success' => true]);
        }


        $desc = t('Use this form to create permission rules for various classes of people or connections.');

        $existing = [];

        $pcat = new Permcat(local_channel());
        $pcatlist = $pcat->listing();
        $permcats = [];
        if ($pcatlist) {
            foreach ($pcatlist as $pc) {
                if (($pc['name']) && ($name) && ($pc['name'] == $name)) {
                    $existing = $pc['perms'];
                }
                if (!$pc['system']) {
                    $permcats[bin2hex($pc['name'])] = $pc['localname'];
                }
            }
        }

        $hidden_perms = [];
        $global_perms = Permissions::Perms();

        foreach ($global_perms as $k => $v) {
            $thisperm = Permcat::find_permcat($existing, $k);

            $checkinherited = PermissionLimits::Get(local_channel(), $k);

            $inherited = (($checkinherited & PERMS_SPECIFIC) ? false : true);

            $thisperm = 0;
            if ($existing) {
                foreach ($existing as $ex) {
                    if ($ex['name'] === $k) {
                        $thisperm = $ex['value'];
                        break;
                    }
                }
            }

            $perms[] = [ 'perms_' . $k, $v, $inherited ? 1 : intval($thisperm), '', [ t('No'), t('Yes') ], (($inherited) ? ' disabled="disabled" ' : '' )];

            if ($inherited) {
                $hidden_perms[] = ['perms_' . $k, 1 ];
            }

        }

    
        $tpl = Theme::get_template("settings_permcats.tpl");
        $o .= replace_macros($tpl, array(
            '$form_security_token' => get_form_security_token("settings_permcats"),
            '$title' => t('Permission Roles'),
            '$desc' => $desc,
            '$desc2' => $desc2,
            '$tokens' => $t,
            '$permcats' => $permcats,
            '$atoken' => $atoken,
            '$url1' => z_root() . '/channel/' . $channel['channel_address'],
            '$url2' => z_root() . '/photos/' . $channel['channel_address'],
            '$name' => array('name', t('Role name') . ' <span class="required">*</span>', (($name) ? $name : ''), ''),
            '$me' => t('My Settings'),
            '$perms' => $perms,
            '$hidden_perms' => $hidden_perms,
            '$inherited' => t('inherited'),
            '$notself' => 0,
            '$self' => 1,
            '$permlbl' => t('Individual Permissions'),
            '$permnote' => t('Some individual permissions may have been preset or locked based on your channel type and privacy settings.'),
            '$submit' => t('Submit')
        ));
        return $o;
    }
}
