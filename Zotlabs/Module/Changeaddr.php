<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;

/*
 * Change channel nickname
 * Provided for those situations which require it.
 * Must be manually configured by the admin before use.
 */


class Changeaddr extends Controller
{

    public function post()
    {

        if (!get_config('system', 'allow_nick_change')) {
            return;
        }

        if (!local_channel()) {
            return;
        }

        if (isset($_SESSION['delegate']) && $_SESSION['delegate']) {
            return;
        }

        if ((!x($_POST, 'qxz_password')) || (!strlen(trim($_POST['qxz_password']))))
            return;

        if ((!x($_POST, 'verify')) || (!strlen(trim($_POST['verify']))))
            return;

        if ($_POST['verify'] !== $_SESSION['remove_account_verify'])
            return;


        $account = App::get_account();
        $channel = App::get_channel();

        $x = account_verify_password($account['account_email'], $_POST['qxz_password']);
        if (!($x && $x['account'])) {
            return;
        }

        if ($account['account_password_changed'] > NULL_DATE) {
            $d1 = datetime_convert('UTC', 'UTC', 'now - 48 hours');
            if ($account['account_password_changed'] > $d1) {
                notice(t('Channel name changes are not allowed within 48 hours of changing the account password.') . EOL);
                return;
            }
        }

        $new_address = trim($_POST['newname']);

        if ($new_address === $channel['channel_address'])
            return;

        if ($new_address === 'sys') {
            notice(t('Reserved nickname. Please choose another.') . EOL);
            return;
        }

        if (check_webbie(array($new_address)) !== $new_address) {
            notice(t('Nickname has unsupported characters or is already being used on this site.') . EOL);
            return $ret;
        }

        channel_change_address($channel, $new_address);

        goaway(z_root() . '/changeaddr');

    }


    public function get()
    {

        if (!get_config('system', 'allow_nick_change')) {
            notice(t('Feature has been disabled') . EOL);
            return;
        }


        if (!local_channel()) {
            goaway(z_root());
        }

        $channel = App::get_channel();

        $hash = random_string();

        $_SESSION['remove_account_verify'] = $hash;

        $tpl = get_markup_template('channel_rename.tpl');
        $o .= replace_macros($tpl, [
            '$basedir' => z_root(),
            '$hash' => $hash,
            '$title' => t('Change channel nickname/address'),
            '$desc' => array(t('WARNING: '), t('Any/all connections on other networks will be lost!')),
            '$passwd' => t('Please enter your password for verification:'),
            '$newname' => ['newname', t('New channel address'), $channel['channel_address'], ''],
            '$submit' => t('Rename Channel')
        ]);

        return $o;

    }

}
