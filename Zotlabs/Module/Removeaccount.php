<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;

class Removeaccount extends Controller
{

    public function post()
    {

        if (!local_channel()) {
            return;
        }

        if ($_SESSION['delegate']) {
            return;
        }

        if ((!x($_POST, 'qxz_password')) || (!strlen(trim($_POST['qxz_password'])))) {
            return;
        }

        if ((!x($_POST, 'verify')) || (!strlen(trim($_POST['verify'])))) {
            return;
        }

        if ($_POST['verify'] !== $_SESSION['remove_account_verify']) {
            return;
        }

        $account = App::get_account();
        $account_id = get_account_id();

        if (!($account && $account_id)) {
            return;
        }

        $x = account_verify_password($account['account_email'], $_POST['qxz_password']);
        if (!($x && $x['account'])) {
            return;
        }

        if ($account['account_password_changed'] > NULL_DATE) {
            $d1 = datetime_convert('UTC', 'UTC', 'now - 48 hours');
            if ($account['account_password_changed'] > d1) {
                notice(t('Account removals are not allowed within 48 hours of changing the account password.') . EOL);
                return;
            }
        }

        account_remove($account_id);
    }

    public function get()
    {

        if (!local_channel()) {
            goaway(z_root());
        }

        $hash = random_string();

        $_SESSION['remove_account_verify'] = $hash;

        $o .= replace_macros(get_markup_template('removeaccount.tpl'), [
            '$basedir' => z_root(),
            '$hash' => $hash,
            '$title' => t('Remove This Account'),
            '$desc' => [t('WARNING: '), t('This account and all its channels will be completely removed from this server. '), t('This action is permanent and can not be undone!')],
            '$passwd' => t('Please enter your password for verification:'),
            '$submit' => t('Remove Account')
        ]);

        return $o;
    }
}
