<?php

namespace Zotlabs\Lib;
    
/**
 * @file include/account.php
 * @brief Some account related functions.
 */

use App;
use Zotlabs\Lib\Crypto;
use Zotlabs\Lib\System;
use Zotlabs\Lib\Channel;
use Zotlabs\Extend\Hook;
use Zotlabs\Render\Theme;


class Account {
    
    public static function check_email($email)
    {

        $email = punify($email);
        $result = [ 'error' => false, 'message' => '' ];

        // Caution: empty email isn't counted as an error in this function.
        // Check for empty value separately.

        if (! strlen($email)) {
            return $result;
        }

        if (! validate_email($email)) {
            $result['message'] .= t('Not a valid email address') . EOL;
        } elseif (! allowed_email($email)) {
            $result['message'] = t('Your email domain is not among those allowed on this site');
        } else {
            $r = q(
                "select account_email from account where account_email = '%s' limit 1",
                dbesc($email)
            );
            if ($r) {
                $result['message'] .= t('Your email address is already registered at this site.');
            }
        }
        if ($result['message']) {
            $result['error'] = true;
        }

        $arr = array('email' => $email, 'result' => $result);
        Hook::call('check_account_email', $arr);

        return $arr['result'];
    }

    public static function check_password($password)
    {
        $result = [ 'error' => false, 'message' => '' ];

        // The only validation we perform by default is pure Javascript to
        // check minimum length and that both entered passwords match.
        // Use hooked functions to perform complexity requirement checks.

        $arr = [ 'password' => $password, 'result' => $result ];
        Hook::call('check_account_password', $arr);

        return $arr['result'];
    }

    public static function check_invite($invite_code)
    {
        $result = [ 'error' => false, 'message' => '' ];

        $using_invites = get_config('system', 'invitation_only');

        if ($using_invites && defined('INVITE_WORKING')) {
            if (! $invite_code) {
                $result['message'] .= t('An invitation is required.') . EOL;
            }
            $r = q("select * from register where hash = '%s' limit 1", dbesc($invite_code));
            if (! $r) {
                $result['message'] .= t('Invitation could not be verified.') . EOL;
            }
        }
        if (strlen($result['message'])) {
            $result['error'] = true;
        }

        $arr = [ 'invite_code' => $invite_code, 'result' => $result ];
        Hook::call('check_account_invite', $arr);

        return $arr['result'];
    }

    public static function check_admin($arr)
    {
        if (is_site_admin()) {
            return true;
        }
        $admin_email = trim(get_config('system', 'admin_email', ''));
        if (strlen($admin_email) && $admin_email === trim($arr['email'])) {
            return true;
        }
        return false;
    }

    public static function account_total()
    {
        $r = q("select account_id from account where true");
        // Distinguish between an empty array and an error
        if (is_array($r)) {
            return count($r);
        }
        return false;
    }


    public static function account_store_lowlevel($arr)
    {

        $store = [
            'account_parent'           => ((array_key_exists('account_parent', $arr))           ? $arr['account_parent']           : '0'),
            'account_default_channel'  => ((array_key_exists('account_default_channel', $arr))  ? $arr['account_default_channel']  : '0'),
            'account_salt'             => ((array_key_exists('account_salt', $arr))             ? $arr['account_salt']             : ''),
            'account_password'         => ((array_key_exists('account_password', $arr))         ? $arr['account_password']         : ''),
            'account_email'            => ((array_key_exists('account_email', $arr))            ? $arr['account_email']            : ''),
            'account_external'         => ((array_key_exists('account_external', $arr))         ? $arr['account_external']         : ''),
            'account_language'         => ((array_key_exists('account_language', $arr))         ? $arr['account_language']         : 'en'),
            'account_created'          => ((array_key_exists('account_created', $arr))          ? $arr['account_created']          : '0001-01-01 00:00:00'),
            'account_lastlog'          => ((array_key_exists('account_lastlog', $arr))          ? $arr['account_lastlog']          : '0001-01-01 00:00:00'),
            'account_flags'            => ((array_key_exists('account_flags', $arr))            ? $arr['account_flags']            : '0'),
            'account_roles'            => ((array_key_exists('account_roles', $arr))            ? $arr['account_roles']            : '0'),
            'account_reset'            => ((array_key_exists('account_reset', $arr))            ? $arr['account_reset']            : ''),
            'account_expires'          => ((array_key_exists('account_expires', $arr))          ? $arr['account_expires']          : '0001-01-01 00:00:00'),
            'account_expire_notified'  => ((array_key_exists('account_expire_notified', $arr))  ? $arr['account_expire_notified']  : '0001-01-01 00:00:00'),
            'account_service_class'    => ((array_key_exists('account_service_class', $arr))    ? $arr['account_service_class']    : ''),
            'account_level'            => ((array_key_exists('account_level', $arr))            ? $arr['account_level']            : '0'),
            'account_password_changed' => ((array_key_exists('account_password_changed', $arr)) ? $arr['account_password_changed'] : '0001-01-01 00:00:00')
        ];

        return create_table_from_array('account', $store);
    }


    public static function create($arr)
    {

        // Required: { email, password }

        $result = [ 'success' => false, 'email' => '', 'password' => '', 'message' => '' ];

        $invite_code = ((isset($arr['invite_code']))   ? notags(trim($arr['invite_code']))  : '');
        $email       = ((isset($arr['email']))         ? notags(punify(trim($arr['email']))) : '');
        $password    = ((isset($arr['password']))      ? trim($arr['password'])             : '');
        $password2   = ((isset($arr['password2']))     ? trim($arr['password2'])            : '');
        $parent      = ((isset($arr['parent']))        ? intval($arr['parent'])             : 0 );
        $flags       = ((isset($arr['account_flags'])) ? intval($arr['account_flags'])      : ACCOUNT_OK);
        $roles       = ((isset($arr['account_roles'])) ? intval($arr['account_roles'])      : 0 );
        $expires     = ((isset($arr['expires']))       ? intval($arr['expires'])            : NULL_DATE);

        $default_service_class = get_config('system', 'default_service_class', EMPTY_STR);

        if (! ($email && $password)) {
            $result['message'] = t('Please enter the required information.');
            return $result;
        }

        // prevent form hackery

        if (($roles & ACCOUNT_ROLE_ADMIN) && (! self::check_admin($arr))) {
            $roles = $roles - ACCOUNT_ROLE_ADMIN;
        }

        // allow the admin_email account to be admin, but only if it's the first account.

        $c = self::account_total();
        if (($c === 0) && (self::check_admin($arr))) {
            $roles |= ACCOUNT_ROLE_ADMIN;
        }

        // Ensure that there is a host keypair.

        if ((! get_config('system', 'pubkey')) && (! get_config('system', 'prvkey'))) {
            $hostkey = Crypto::new_keypair(4096);
            set_config('system', 'pubkey', $hostkey['pubkey']);
            set_config('system', 'prvkey', $hostkey['prvkey']);
        }

        $invite_result = check_account_invite($invite_code);
        if ($invite_result['error']) {
            $result['message'] = $invite_result['message'];
            return $result;
        }

        $email_result = check_account_email($email);

        if ($email_result['error']) {
            $result['message'] = $email_result['message'];
            return $result;
        }

        $password_result = check_account_password($password);

        if ($password_result['error']) {
            $result['message'] = $password_result['message'];
            return $result;
        }

        $salt = random_string(32);
        $password_encoded = hash('whirlpool', $salt . $password);

        $r = self::account_store_lowlevel(
            [
                'account_parent'        => intval($parent),
                'account_salt'          => $salt,
                'account_password'      => $password_encoded,
                'account_email'         => $email,
                'account_language'      => get_best_language(),
                'account_created'       => datetime_convert(),
                'account_flags'         => intval($flags),
                'account_roles'         => intval($roles),
                'account_expires'       => $expires,
                'account_service_class' => $default_service_class
            ]
        );
        if (! $r) {
            logger('create_account: DB INSERT failed.');
            $result['message'] = t('Failed to store account information.');
            return($result);
        }

        $r = q(
            "select * from account where account_email = '%s' and account_password = '%s' limit 1",
            dbesc($email),
            dbesc($password_encoded)
        );
        if ($r && is_array($r) && count($r)) {
            $result['account'] = $r[0];
        } else {
            logger('create_account: could not retrieve newly created account');
        }

        // Set the parent record to the current record_id if no parent was provided

        if (! $parent) {
            $r = q(
                "update account set account_parent = %d where account_id = %d",
                intval($result['account']['account_id']),
                intval($result['account']['account_id'])
            );
            if (! $r) {
                logger('create_account: failed to set parent');
            }
            $result['account']['parent'] = $result['account']['account_id'];
        }

        $result['success']  = true;
        $result['email']    = $email;
        $result['password'] = $password;

        Hook::call('register_account', $result);

        return $result;
    }



    public static function verify_email_address($arr)
    {

        if (array_key_exists('resend', $arr)) {
            $email = $arr['email'];
            $a = q(
                "select * from account where account_email = '%s' limit 1",
                dbesc($arr['email'])
            );
            if (! ($a && ($a[0]['account_flags'] & ACCOUNT_UNVERIFIED))) {
                return false;
            }
            $account = array_shift($a);
            $v = q(
                "select * from register where uid = %d and password = 'verify' limit 1",
                intval($account['account_id'])
            );
            if ($v) {
                $hash = $v[0]['hash'];
            } else {
                return false;
            }
        } else {
            $hash = random_string(24);

            $r = q(
                "INSERT INTO register ( hash, created, uid, password, lang ) VALUES ( '%s', '%s', %d, '%s', '%s' ) ",
                dbesc($hash),
                dbesc(datetime_convert()),
                intval($arr['account']['account_id']),
                dbesc('verify'),
                dbesc($arr['account']['account_language'])
            );
            $account = $arr['account'];
        }

        push_lang(($account['account_language']) ? $account['account_language'] : 'en');

        $email_msg = replace_macros(
            Theme::get_email_template('register_verify_member.tpl'),
            [
                '$sitename' => System::get_site_name(),
                '$siteurl'  => z_root(),
                '$email'    => $arr['email'],
                '$uid'      => $account['account_id'],
                '$hash'     => $hash,
                '$details'  => $details
            ]
        );

        $res = z_mail(
            [
            'toEmail' => $arr['email'],
            'messageSubject' => sprintf(t('Registration confirmation for %s'), System::get_site_name()),
            'textVersion' => $email_msg,
            ]
        );

        pop_lang();

        if ($res) {
            $delivered ++;
        } else {
            logger('send_reg_approval_email: failed to account_id: ' . $arr['account']['account_id']);
        }
        return $res;
    }




    public static function send_reg_approval_email($arr)
    {

        $r = q(
            "select * from account where (account_roles & %d) >= 4096",
            intval(ACCOUNT_ROLE_ADMIN)
        );
        if (! ($r && is_array($r) && count($r))) {
            return false;
        }

        $admins = [];

        foreach ($r as $rr) {
            if (strlen($rr['account_email'])) {
                $admins[] = [ 'email' => $rr['account_email'], 'lang' => $rr['account_lang'] ];
            }
        }

        if (! count($admins)) {
            return false;
        }

        $hash = random_string();

        $r = q(
            "INSERT INTO register ( hash, created, uid, password, lang ) VALUES ( '%s', '%s', %d, '%s', '%s' ) ",
            dbesc($hash),
            dbesc(datetime_convert()),
            intval($arr['account']['account_id']),
            dbesc(''),
            dbesc($arr['account']['account_language'])
        );

        $ip = ((isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : EMPTY_STR);

        $details = (($ip) ? $ip . ' [' . gethostbyaddr($ip) . ']' : '[unknown or stealth IP]');

        $delivered = 0;

        foreach ($admins as $admin) {
            if (strlen($admin['lang'])) {
                push_lang($admin['lang']);
            } else {
                push_lang('en');
            }

            $email_msg = replace_macros(Theme::get_email_template('register_verify_eml.tpl'), [
                '$sitename' => get_config('system', 'sitename'),
                '$siteurl'  =>  z_root(),
                '$email'    => $arr['email'],
                '$uid'      => $arr['account']['account_id'],
                '$hash'     => $hash,
                '$details'  => $details
             ]);

            $res = z_mail(
                [
                'toEmail' => $admin['email'],
                'messageSubject' => sprintf(t('Registration request at %s'), get_config('system', 'sitename')),
                'textVersion' => $email_msg,
                ]
            );

            if ($res) {
                $delivered ++;
            } else {
                logger('send_reg_approval_email: failed to ' . $admin['email'] . 'account_id: ' . $arr['account']['account_id']);
            }
            
            pop_lang();
        }

        return ($delivered ? true : false);
    }

    public static function send_register_success_email($email, $password)
    {

        $email_msg = replace_macros(Theme::get_email_template('register_open_eml.tpl'), [
            '$sitename' => System::get_site_name(),
            '$siteurl' =>  z_root(),
            '$email'    => $email,
            '$password' => t('your registration password'),
        ]);

        $res = z_mail(
            [
                'toEmail' => $email,
                'messageSubject' => sprintf(t('Registration details for %s'), System::get_site_name()),
                'textVersion' => $email_msg,
            ]
        );

        return ($res ? true : false);
    }

    /**
     * @brief Allows a user registration.
     *
     * @param string $hash
     * @return array|bool
     */
    public static function allow($hash)
    {

        $ret = array('success' => false);

        $register = q(
            "SELECT * FROM register WHERE hash = '%s' LIMIT 1",
            dbesc($hash)
        );

        if (! $register) {
            return $ret;
        }

        $account = q(
            "SELECT * FROM account WHERE account_id = %d LIMIT 1",
            intval($register[0]['uid'])
        );

        if (! $account) {
            return $ret;
        }

        $r = q(
            "DELETE FROM register WHERE hash = '%s'",
            dbesc($register[0]['hash'])
        );

        $r = q(
            "update account set account_flags = (account_flags & ~%d) where (account_flags & %d) > 0 and account_id = %d",
            intval(ACCOUNT_BLOCKED),
            intval(ACCOUNT_BLOCKED),
            intval($register[0]['uid'])
        );
        $r = q(
            "update account set account_flags = (account_flags & ~%d) where (account_flags & %d) > 0 and account_id = %d",
            intval(ACCOUNT_PENDING),
            intval(ACCOUNT_PENDING),
            intval($register[0]['uid'])
        );

        push_lang($register[0]['lang']);

        $email_tpl = Theme::get_email_template("register_open_eml.tpl");
        $email_msg = replace_macros($email_tpl, [
            '$sitename' => System::get_site_name(),
            '$siteurl'  =>  z_root(),
            '$username' => $account[0]['account_email'],
            '$email'    => $account[0]['account_email'],
            '$password' => '',
            '$uid'      => $account[0]['account_id']
        ]);

        $res = z_mail(
            [
            'toEmail' => $account[0]['account_email'],
            'messageSubject' => sprintf(t('Registration details for %s'), System::get_site_name()),
            'textVersion' => $email_msg,
            ]
        );

        pop_lang();

        if (get_config('system', 'auto_channel_create')) {
            Channel::auto_create($register[0]['uid']);
        }

        if ($res) {
            info(t('Account approved.') . EOL);
            return true;
        }
    }


    /**
     * @brief Denies an account registration.
     *
     * This does not have to go through user_remove() and save the nickname
     * permanently against re-registration, as the person was not yet
     * allowed to have friends on this system
     *
     * @param string $hash
     * @return bool
     */

    public static function deny($hash)
    {

        $register = q(
            "SELECT * FROM register WHERE hash = '%s' LIMIT 1",
            dbesc($hash)
        );

        if (! $register) {
            return false;
        }

        $account = q(
            "SELECT account_id, account_email FROM account WHERE account_id = %d LIMIT 1",
            intval($register[0]['uid'])
        );

        if (! $account) {
            return false;
        }

        $r = q(
            "DELETE FROM account WHERE account_id = %d",
            intval($register[0]['uid'])
        );

        $r = q(
            "DELETE FROM register WHERE id = %d",
            intval($register[0]['id'])
        );
        notice(sprintf(t('Registration revoked for %s'), $account[0]['account_email']) . EOL);

        return true;
    }

    // called from regver to activate an account from the email verification link

    public static function approve($hash)
    {

        $ret = false;

        // Note: when the password in the register table is 'verify', the uid actually contains the account_id

        $register = q(
            "SELECT * FROM register WHERE hash = '%s' and password = 'verify' LIMIT 1",
            dbesc($hash)
        );

        if (! $register) {
            return $ret;
        }

        $account = q(
            "SELECT * FROM account WHERE account_id = %d LIMIT 1",
            intval($register[0]['uid'])
        );

        if (! $account) {
            return $ret;
        }

        $r = q(
            "DELETE FROM register WHERE hash = '%s' and password = 'verify'",
            dbesc($register[0]['hash'])
        );

        $r = q(
            "update account set account_flags = (account_flags & ~%d) where (account_flags & %d)>0 and account_id = %d",
            intval(ACCOUNT_BLOCKED),
            intval(ACCOUNT_BLOCKED),
            intval($register[0]['uid'])
        );
        $r = q(
            "update account set account_flags = (account_flags & ~%d) where (account_flags & %d)>0 and account_id = %d",
            intval(ACCOUNT_PENDING),
            intval(ACCOUNT_PENDING),
            intval($register[0]['uid'])
        );
        $r = q(
            "update account set account_flags = (account_flags & ~%d) where (account_flags & %d)>0 and account_id = %d",
            intval(ACCOUNT_UNVERIFIED),
            intval(ACCOUNT_UNVERIFIED),
            intval($register[0]['uid'])
        );

        // get a fresh copy after we've modified it.

        $account = q(
            "SELECT * FROM account WHERE account_id = %d LIMIT 1",
            intval($register[0]['uid'])
        );

        if (! $account) {
            return $ret;
        }

        if (get_config('system', 'auto_channel_create')) {
            Channel::auto_create($register[0]['uid']);
        } else {
            $_SESSION['login_return_url'] = 'new_channel';
            authenticate_success($account[0], null, true, true, false, true);
        }

        return true;
    }

        /**
     * Included here for completeness, but this is a very dangerous operation.
     * It is the caller's responsibility to confirm the requestor's intent and
     * authorisation to do this.
     *
     * @param int $account_id
     * @param bool $local (optional) default true
     * @param bool $unset_session (optional) default true
     * @return bool|array
     */

    public static function remove($account_id, $local = true, $unset_session = true)
    {

        logger('account_remove: ' . $account_id);

        // Global removal (all clones) not currently supported
        $local = true;

        if (! intval($account_id)) {
            logger('No account.');
            return false;
        }

        // Don't let anybody nuke the only admin account.

        $r = q(
            "select account_id from account where (account_roles & %d) > 0",
            intval(ACCOUNT_ROLE_ADMIN)
        );

        if ($r !== false && count($r) == 1 && $r[0]['account_id'] == $account_id) {
            logger("Unable to remove the only remaining admin account");
            return false;
        }

        $r = q(
            "select * from account where account_id = %d limit 1",
            intval($account_id)
        );

        if (! $r) {
            logger('No account with id: ' . $account_id);
            return false;
        }

        $account_email = $r[0]['account_email'];

        $x = q(
            "select channel_id from channel where channel_account_id = %d",
            intval($account_id)
        );
        if ($x) {
            foreach ($x as $xx) {
                Channel::channel_remove($xx['channel_id'], $local, false);
            }
        }

        $r = q(
            "delete from account where account_id = %d",
            intval($account_id)
        );

        if ($unset_session) {
            App::$session->nuke();
            notice(sprintf(t('Account \'%s\' deleted'), $account_email) . EOL);
            goaway(z_root());
        }

        return $r;
    }


}
