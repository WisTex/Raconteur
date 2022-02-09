<?php

namespace Zotlabs\Module;

use Zotlabs\Web\Controller;
use Zotlabs\Lib\Account;
use Zotlabs\Lib\Channel;
    
class Email_validation extends Controller
{

    public function post()
    {

        $success = false;
        if ($_POST['token']) {
            // This will redirect internally on success unless the channel is auto_created
            if (Account::approve(trim(basename($_POST['token'])))) {
                $success = true;
                if (get_config('system', 'auto_channel_create')) {
                    $next_page = get_config('system', 'workflow_channel_next', 'profiles');
                }
                if ($next_page) {
                    goaway(z_root() . '/' . $next_page);
                }
            }
        }
        if (!$success) {
            notice(t('Token verification failed.') . EOL);
        }
    }


    public function get()
    {

        if (argc() > 1) {
            $email = hex2bin(argv(1));
        }

        $o = replace_macros(get_markup_template('email_validation.tpl'), [
            '$title' => t('Email Verification Required'),
            '$desc' => sprintf(t('A verification token was sent to your email address [%s]. Enter that token here to complete the account verification step. Please allow a few minutes for delivery, and check your spam folder if you do not see the message.'), $email),
            '$resend' => t('Resend Email'),
            '$email' => bin2hex($email),
            '$submit' => t('Submit'),
            '$token' => ['token', t('Validation token'), '', ''],
        ]);

        return $o;
    }
}
