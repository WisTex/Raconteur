<?php

namespace Code\Module\Settings;

use App;
use chillerlan\QRCode\QRCode;
use Code\Lib\System;
use Code\Render\Theme;
use OTPHP\TOTP;

class Multifactor
{
    public function post()
    {
        $account = App::get_account();
        if (!$account) {
            return;
        }
        if (!empty($_POST['enable_mfa']) && intval($_POST['enable_mfa']) && ! $account['account_external']) {
            $otp=TOTP::create();
            $otp->setLabel('label');
            $otp->setIssuer('issuer');
            $dbResult = q("UPDATE account set account_external = '%s' where account_id = %d",
                dbesc($otp->getSecret()),
                intval($account['account_id'])
            );
        }
        else {
            $dbResult = q("UPDATE account set account_external = '' where account_id = %d",
                intval($account['account_id'])
            );
        }


    }

    public function get()
    {
        $account = App::get_account();
        if (!$account) {
            return '';
        }

        if (!$account['account_external']) {
            $otp=TOTP::create();
            $otp->setLabel('label');
            $otp->setIssuer('issuer');
            q("UPDATE account set account_external = '%s' where account_id = %d",
                dbesc($otp->getSecret()),
                intval($account['account_id'])
            );
        }

        $otp = TOTP::create($account['account_external']);
        $otp->setLabel(System::get_platform_name());
        $uri = $otp->getProvisioningUri();
        return replace_macros(Theme::get_template('totp_setup.tpl'),
            [
                '$form_security_token' => get_form_security_token("settings_mfa"),
                '$title' => t('Multifactor Settings'),
                '$totp_setup_text' => t('Multi-Factor Authentication Setup'),
                '$qrcode' => (new QRCode())->render($uri),
                '$uri' => $uri,
                '$secret' => ($account['account_external'])
                    ? ['secreturi', t('Secret URI'), $account['account_external'],
                        t('Use this secret to configure your authenticator app if you cannot use the QRcode'), '']
                    : '',
                '$test' => ['totp_test', t('Test your authenticator here'), '', t('If this test does not pass you will be permanently unable to login to this account'), ''],

                '$enable_mfa' => ['enable_mfa', t('Enable Multi-factor Authentication'), (bool)$account['account_external'], '', [t('No'), t('Yes')]],
                '$submit' => t('Submit'),
            ]
        );
    }
}