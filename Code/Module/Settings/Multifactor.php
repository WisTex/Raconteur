<?php

namespace Code\Module\Settings;

use App;
use chillerlan\QRCode\QRCode;
use Code\Lib\AConfig;
use Code\Lib\System;
use Code\Render\Theme;
use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;


class Multifactor
{
    public function post()
    {
        $account = App::get_account();
        if (!$account) {
            return;
        }
        $enable_mfa = isset($_POST['enable_mfa']) ? (int) $_POST['enable_mfa'] : false;
        AConfig::Set($account['account_id'], 'system', 'mfa_enabled', $enable_mfa);
    }

    public function get()
    {
        $account = App::get_account();
        if (!$account) {
            return '';
        }

        if (!$account['account_external']) {
            $otp = TOTP::create();
            $otp->setLabel(rawurlencode(System::get_project_name()));
            $otp->setIssuer(rawurlencode(System::get_project_name()));

            $mySecret = trim(Base32::encodeUpper(random_bytes(32)), '=');
            $otp = TOTP::create($mySecret);
            q("UPDATE account set account_external = '%s' where account_id = %d",
                dbesc($otp->getSecret()),
                intval($account['account_id'])
            );
            $account['account_external'] = $otp->getSecret();
        }

        $otp = TOTP::create($account['account_external']);
        $otp->setLabel(System::get_platform_name());
        $uri = $otp->getProvisioningUri();
        return replace_macros(Theme::get_template('totp_setup.tpl'),
            [
                '$form_security_token' => get_form_security_token("settings_mfa"),
                '$title' => t('Multifactor Settings'),
                '$totp_setup_text' => t('Multi-Factor Authentication Setup'),
                '$secret_text' => t('This is your generated secret. This may be used in some cases if the QR image cannot be read. Please save it.'),
                '$test_title' => t('Please enter the code from your authenticator'),
                '$qrcode' => (new QRCode())->render($uri),
                '$uri' => $uri,
                '$secret' => ($account['account_external'] ?? ''),
                '$test_pass' => t("That code is correct."),
                '$test_fail' => t("Incorrect code."),
                '$enable_mfa' => [
                    'enable_mfa',
                    t('Enable Multi-factor Authentication'),
                    AConfig::Get($account['account_id'], 'system', 'mfa_enabled'),
                    '',
                    [t('No'), t('Yes')]
                ],
                '$submit' => t('Submit'),
                '$test' => t('Test'),
                ''
            ]
        );
    }
}
