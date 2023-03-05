<?php

namespace Code\Module\Settings;

use App;
use chillerlan\QRCode\QRCode;
use Code\Lib\System;
use Code\Render\Theme;
use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;


class Multifactor
{
    public function init()
    {
        $account = App::get_account();
        if (!$account) {
            return;
        }
    }

    public function get()
    {
        $hasNewSecret = false;
        $account = App::get_account();
        if (!$account) {
            return '';
        }

        if (!$account['account_external']) {
            $otp = TOTP::create();
            $otp->setLabel('label');
            $otp->setIssuer('issuer');

            $mySecret = trim(Base32::encodeUpper(random_bytes(32)), '=');
            $otp = TOTP::create($mySecret);
            q("UPDATE account set account_external = '%s' where account_id = %d",
                dbesc($otp->getSecret()),
                intval($account['account_id'])
            );
            $account['account_external'] = $otp->getSecret();
            $hasNewSecret = true;
        }

        $otp = TOTP::create($account['account_external']);
        $otp->setLabel(System::get_platform_name());
        $uri = $otp->getProvisioningUri();
        return replace_macros(Theme::get_template('totp_setup.tpl'),
            [
                '$form_security_token' => get_form_security_token("settings_mfa"),
                '$title' => t('Multifactor Settings'),
                '$totp_setup_text' => t('Multi-Factor Authentication Setup'),
                '$test_title' => t('Please enter the code from your authenticator'),
                '$qrcode' => (new QRCode())->render($uri),
                '$uri' => $uri,
                '$secret' => ($account['account_external'] ?? ''),


                '$enable_mfa' => ['enable_mfa', t('Enable Multi-factor Authentication'), ($hasNewSecret) ? false : (bool)$account['account_external'], '', [t('No'), t('Yes')]],
                '$submit' => t('Submit'),
            ]
        );
    }
}
