<?php

namespace Code\Module\Settings;

use App;
use Code\Access\Permissions;
use Code\Access\PermissionLimits;
use Code\Lib\ServiceClass;
use Code\Lib\AccessList;
use Code\Lib\Libsync;
use Code\Render\Theme;


require_once('include/security.php');

class Tokens
{

    public function post()
    {

        $channel = App::get_channel();

        check_form_security_token_redirectOnErr('/settings/tokens', 'settings_tokens');
        $token_errs = 0;
        if (array_key_exists('token', $_POST)) {
            $atoken_id = (($_POST['atoken_id']) ? intval($_POST['atoken_id']) : 0);
            if (!$atoken_id) {
                $atoken_guid = new_uuid();
            }
            $name = trim(escape_tags($_POST['name']));
            $token = trim($_POST['token']);
            if ((!$name) || (!$token)) {
                $token_errs++;
            }
            if (trim($_POST['expires'])) {
                $expires = datetime_convert(date_default_timezone_get(), 'UTC', $_POST['expires']);
            } else {
                $expires = NULL_DATE;
            }
            $max_atokens = ServiceClass::fetch(local_channel(), 'access_tokens');
            if ($max_atokens) {
                $r = q(
                    "select count(atoken_id) as total where atoken_uid = %d",
                    intval(local_channel())
                );
                if ($r && intval($r[0]['total']) >= $max_tokens) {
                    notice(sprintf(t('This channel is limited to %d tokens'), $max_tokens) . EOL);
                    return;
                }
            }
        }
        if ($token_errs) {
            notice(t('Name and Password are required.') . EOL);
            return;
        }

        $old_atok = q(
            "select * from atoken where atoken_uid = %d and atoken_name = '%s'",
            intval($channel['channel_id']),
            dbesc($name)
        );
        if ($old_atok) {
            $old_atok = array_shift($old_atok);
            $old_xchan = atoken_xchan($old_atok);
        }

        if ($atoken_id) {
            $r = q(
                "update atoken set atoken_name = '%s', atoken_token = '%s', atoken_expires = '%s' 
				where atoken_id = %d and atoken_uid = %d",
                dbesc($name),
                dbesc($token),
                dbesc($expires),
                intval($atoken_id),
                intval($channel['channel_id'])
            );
        } else {
            $r = q(
                "insert into atoken ( atoken_guid, atoken_aid, atoken_uid, atoken_name, atoken_token, atoken_expires )
				values ( '%s', %d, %d, '%s', '%s', '%s' ) ",
                dbesc($atoken_guid),
                intval($channel['channel_account_id']),
                intval($channel['channel_id']),
                dbesc($name),
                dbesc($token),
                dbesc($expires)
            );
        }
        $atok = q(
            "select * from atoken where atoken_uid = %d and atoken_name = '%s'",
            intval($channel['channel_id']),
            dbesc($name)
        );
        if ($atok) {
            $xchan = atoken_xchan($atok[0]);
            atoken_create_xchan($xchan);
            $atoken_xchan = $xchan['xchan_hash'];
            if ($old_atok && $old_xchan) {
                $r = q(
                    "update xchan set xchan_name = '%s' where xchan_hash = '%s'",
                    dbesc($xchan['xchan_name']),
                    dbesc($old_xchan['xchan_hash'])
                );
            }
        }

        $all_perms = Permissions::Perms();

        $p = EMPTY_STR;

        if ($all_perms) {
            foreach ($all_perms as $perm => $desc) {
                if (array_key_exists('perms_' . $perm, $_POST)) {
                    if ($p) {
                        $p .= ',';
                    }
                    $p .= $perm;
                }
            }
            set_abconfig(local_channel(), $atoken_xchan, 'system', 'my_perms', $p);
            if ($old_atok) {
            }
        }

        if (!$atoken_id) {
            // If this is a new token, create a new abook record

            $closeness = get_pconfig($uid, 'system', 'new_abook_closeness', 80);
            $profile_assign = get_pconfig($uid, 'system', 'profile_assign', '');

            $r = abook_store_lowlevel(
                [
                    'abook_account' => $channel['channel_account_id'],
                    'abook_channel' => $channel['channel_id'],
                    'abook_closeness' => intval($closeness),
                    'abook_xchan' => $atoken_xchan,
                    'abook_profile' => $profile_assign,
                    'abook_feed' => 0,
                    'abook_created' => datetime_convert(),
                    'abook_updated' => datetime_convert(),
                    'abook_instance' => z_root()
                ]
            );

            if (!$r) {
                logger('abook creation failed');
            }

            /** If there is a default group for this channel, add this connection to it */

            if ($channel['channel_default_group']) {
                $g = AccessList::rec_byhash($uid, $channel['channel_default_group']);
                if ($g) {
                    AccessList::member_add($uid, '', $atoken_xchan, $g['id']);
                }
            }

            $r = q(
                "SELECT abook.*, xchan.*
				FROM abook left join xchan on abook_xchan = xchan_hash
				WHERE abook_channel = %d and abook_xchan = '%s' LIMIT 1",
                intval($channel['channel_id']),
                dbesc($atoken_xchan)
            );

            if (!$r) {
                logger('abook or xchan record not saved correctly');
                return;
            }

            $clone = array_shift($r);

            unset($clone['abook_id']);
            unset($clone['abook_account']);
            unset($clone['abook_channel']);

            $abconfig = load_abconfig($channel['channel_id'], $clone['abook_xchan']);
            if ($abconfig) {
                $clone['abconfig'] = $abconfig;
            }

            Libsync::build_sync_packet(
                $channel['channel_id'],
                ['abook' => [$clone], 'atoken' => $atok],
                true
            );
        }

        info(t('Token saved.') . EOL);
        return;
    }


    public function get()
    {

        $channel = App::get_channel();

        $atoken = null;
        $atoken_xchan = '';

        if (argc() > 2) {
            $id = argv(2);

            $atoken = q(
                "select * from atoken where atoken_id = %d and atoken_uid = %d",
                intval($id),
                intval(local_channel())
            );

            if ($atoken) {
                $atoken = $atoken[0];
                $atoken_xchan = substr($channel['channel_hash'], 0, 16) . '.' . $atoken['atoken_guid'];
            }

            if ($atoken && argc() > 3 && argv(3) === 'drop') {
                $atoken['deleted'] = true;

                $r = q(
                    "SELECT abook.*, xchan.*
					FROM abook left join xchan on abook_xchan = xchan_hash
					WHERE abook_channel = %d and abook_xchan = '%s' LIMIT 1",
                    intval($channel['channel_id']),
                    dbesc($atoken_xchan)
                );
                if (!$r) {
                    return;
                }

                $clone = array_shift($r);

                unset($clone['abook_id']);
                unset($clone['abook_account']);
                unset($clone['abook_channel']);

                $clone['entry_deleted'] = true;

                $abconfig = load_abconfig($channel['channel_id'], $clone['abook_xchan']);
                if ($abconfig) {
                    $clone['abconfig'] = $abconfig;
                }

                atoken_delete($id);
                Libsync::build_sync_packet(
                    $channel['channel_id'],
                    ['abook' => [$clone], 'atoken' => [$atoken]],
                    true
                );

                $atoken = null;
                $atoken_xchan = '';
            }
        }

        $t = q(
            "select * from atoken where atoken_uid = %d",
            intval(local_channel())
        );

        $desc = t('Use this form to create temporary access identifiers to share things with non-members. These identities may be used in Access Control Lists and visitors may login using these credentials to access private content.');

        $desc2 = t('You may also provide <em>dropbox</em> style access links to friends and associates by adding the Login Password to any specific site URL as shown. Examples:');


        $global_perms = Permissions::Perms();
        $existing = get_all_perms(local_channel(), (($atoken_xchan) ? $atoken_xchan : EMPTY_STR));

        $theirs = get_abconfig(local_channel(), $atoken_xchan, 'system', 'their_perms', EMPTY_STR);

        $their_perms = Permissions::FilledPerms(explode(',', $theirs));
        foreach ($global_perms as $k => $v) {
            if (!array_key_exists($k, $their_perms)) {
                $their_perms[$k] = 1;
            }
        }

        $my_perms = explode(',', get_abconfig(local_channel(), $atoken_xchan, 'system', 'my_perms', EMPTY_STR));

        foreach ($global_perms as $k => $v) {
            $thisperm = ((in_array($k, $my_perms)) ? 1 : 0);

            $checkinherited = PermissionLimits::Get(local_channel(), $k);

            // For auto permissions (when $self is true) we don't want to look at existing
            // permissions because they are enabled for the channel owner
            if ((!$self) && ($existing[$k])) {
                $thisperm = "1";
            }

            $perms[] = array('perms_' . $k, $v, ((array_key_exists($k, $their_perms)) ? intval($their_perms[$k]) : ''), $thisperm, 1, (($checkinherited & PERMS_SPECIFIC) ? '' : '1'), '', $checkinherited);
        }


        $tpl = Theme::get_template("settings_tokens.tpl");
        $o .= replace_macros($tpl, array(
            '$form_security_token' => get_form_security_token("settings_tokens"),
            '$title' => t('Guest Access Tokens'),
            '$desc' => $desc,
            '$desc2' => $desc2,
            '$tokens' => $t,
            '$atoken' => $atoken,
            '$atoken_xchan' => $atoken_chan,
            '$url1' => z_root() . '/channel/' . $channel['channel_address'],
            '$url2' => z_root() . '/photos/' . $channel['channel_address'],
            '$name' => array('name', t('Login Name') . ' <span class="required">*</span>', (($atoken) ? $atoken['atoken_name'] : ''), ''),
            '$token' => array('token', t('Login Password') . ' <span class="required">*</span>', (($atoken) ? $atoken['atoken_token'] : new_token()), ''),
            '$expires' => array('expires', t('Expires (yyyy-mm-dd)'), (($atoken['atoken_expires'] && $atoken['atoken_expires'] > NULL_DATE) ? datetime_convert('UTC', date_default_timezone_get(), $atoken['atoken_expires']) : ''), ''),
            '$them' => t('Their Settings'),
            '$me' => t('My Settings'),
            '$perms' => $perms,
            '$inherited' => t('inherited'),
            '$notself' => 1,
            '$self' => 0,
            '$permlbl' => t('Individual Permissions'),
            '$permnote' => t('Some permissions may be inherited from your channel\'s <a href="settings"><strong>privacy settings</strong></a>, which have higher priority than individual settings. You can <strong>not</strong> change those settings here.'),
            '$submit' => t('Submit')
        ));
        return $o;
    }
}
