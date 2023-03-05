<?php

namespace Code\Module;

use App;
use Code\Lib\Apps;
use Code\Lib\AConfig;
use Code\Lib\System;
use Code\Render\Theme;
use Code\Web\Controller;
use OTPHP\TOTP;

class Totp_check extends Controller
{

    function post()
    {
        $retval = ['status' => false];

        if (!local_channel()) {
            json_return_and_die($retval);
        }

        $account = App::get_account();
        if (!$account) {
            json_return_and_die($retval);
        }
        $secret = $account['account_external'];
        $input = (isset($_POST['totp_code'])) ? trim($_POST['totp_code']) : '';

        if ($secret && $input) {
            $otp = TOTP::create($secret); // create TOTP object from the secret.
            if ($otp->verify($_POST['totp_code']) || $input === $secret ) {
                logger('otp_success');
                $_SESSION['2FA_VERIFIED'] = true;
                $retval['status'] = true;
                json_return_and_die($retval);
            }
            logger('otp_fail');
        }
        json_return_and_die($retval);
    }




    function totp_installed() {
        $id = local_channel();
        if (!$id) {
            return false;
        }
        return Apps::addon_app_installed($id, 'totp');
    }

    function get_secret($acct_id) {
        return AConfig::get($acct_id, 'totp', 'secret', null);
    }

    function get() {
        $account = App::get_account();
        if (!$account) {
            return t('Account not found.');
        }
        return replace_macros(Theme::get_template('totp.tpl'),
            [
                '$header' => t('Multifactor Verification'),
                '$desc'   => t('Please enter the verification key from your authenticator app'),
                '$success' => t('Success!'),
                '$fail' => t('Invalid code, please try again.'),
                '$maxfails' => t('Too many invalid codes...'),
                '$submit' => t('Verify')
            ]
        );
    }
}

