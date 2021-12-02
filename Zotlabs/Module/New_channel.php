<?php
namespace Zotlabs\Module;


use App;
use URLify;
use Zotlabs\Access\PermissionRoles;
use Zotlabs\Web\Controller;

require_once('include/channel.php');
require_once('include/permissions.php');


class New_channel extends Controller
{

    public function init()
    {

        $cmd = ((argc() > 1) ? argv(1) : '');

        if ($cmd === 'autofill.json') {
            $result = array('error' => false, 'message' => '');
            $n = trim($_REQUEST['name']);

            $x = false;

            if (get_config('system', 'unicode_usernames')) {
                $x = punify(mb_strtolower($n));
            }

            if ((!$x) || strlen($x) > 64)
                $x = strtolower(URLify::transliterate($n));

            $test = [];

            // first name
            if (strpos($x, ' '))
                $test[] = legal_webbie(substr($x, 0, strpos($x, ' ')));
            if ($test[0]) {
                // first name plus first initial of last
                $test[] = ((strpos($x, ' ')) ? $test[0] . legal_webbie(trim(substr($x, strpos($x, ' '), 2))) : '');
                // first name plus random number
                $test[] = $test[0] . mt_rand(1000, 9999);
            }
            // fullname
            $test[] = legal_webbie($x);
            // fullname plus random number
            $test[] = legal_webbie($x) . mt_rand(1000, 9999);

            json_return_and_die(check_webbie($test));
        }

        if ($cmd === 'checkaddr.json') {
            $result = array('error' => false, 'message' => '');
            $n = trim($_REQUEST['nick']);
            if (!$n) {
                $n = trim($_REQUEST['name']);
            }

            $x = false;

            if (get_config('system', 'unicode_usernames')) {
                $x = punify(mb_strtolower($n));
            }

            if ((!$x) || strlen($x) > 64)
                $x = strtolower(URLify::transliterate($n));


            $test = [];

            // first name
            if (strpos($x, ' '))
                $test[] = legal_webbie(substr($x, 0, strpos($x, ' ')));
            if ($test[0]) {
                // first name plus first initial of last
                $test[] = ((strpos($x, ' ')) ? $test[0] . legal_webbie(trim(substr($x, strpos($x, ' '), 2))) : '');
                // first name plus random number
                $test[] = $test[0] . mt_rand(1000, 9999);
            }

            $n = legal_webbie($x);
            if (strlen($n)) {
                $test[] = $n;
                $test[] = $n . mt_rand(1000, 9999);
            }

            for ($y = 0; $y < 100; $y++)
                $test[] = 'id' . mt_rand(1000, 9999);

            json_return_and_die(check_webbie($test));
        }


    }

    public function post()
    {

        $arr = $_POST;

        $acc = App::get_account();

        if (local_channel()) {
            $parent_channel = App::get_channel();
            if ($parent_channel) {
                $arr['parent_hash'] = $parent_channel['channel_hash'];
            }
        }

        $arr['account_id'] = get_account_id();

        // prevent execution by delegated channels as well as those not logged in.
        // get_account_id() returns the account_id from the session. But \App::$account
        // may point to the original authenticated account.

        if ((!$acc) || ($acc['account_id'] != $arr['account_id'])) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        $result = create_identity($arr);

        if (!$result['success']) {
            notice($result['message']);
            return;
        }

        $newuid = $result['channel']['channel_id'];

        change_channel($result['channel']['channel_id']);

        $next_page = get_config('system', 'workflow_channel_next', 'profiles');
        goaway(z_root() . '/' . $next_page);

    }

    public function get()
    {

        $acc = App::get_account();

        if ((!$acc) || $acc['account_id'] != get_account_id()) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        $default_role = '';
        $aid = get_account_id();
        if ($aid) {
            $r = q("select count(channel_id) as total from channel where channel_account_id = %d",
                intval($aid)
            );
            if ($r && (!intval($r[0]['total']))) {
                $default_role = get_config('system', 'default_permissions_role', 'social');
            }

            $limit = account_service_class_fetch(get_account_id(), 'total_identities');

            if ($r && ($limit !== false)) {
                $channel_usage_message = sprintf(t("You have created %1$.0f of %2$.0f allowed channels."), $r[0]['total'], $limit);
            } else {
                $channel_usage_message = '';
            }
        }

        $name_help = '<span id="name_help_loading" style="display:none">' . t('Loading') . '</span><span id="name_help_text">';
        $name_help .= (($default_role)
            ? t('Your real name is recommended.')
            : t('Examples: "Bob Jameson", "Lisa and her Horses", "Soccer", "Aviation Group"')
        );
        $name_help .= '</span>';

        $nick_help = '<span id="nick_help_loading" style="display:none">' . t('Loading') . '</span><span id="nick_help_text">';
        $nick_help .= t('This will be used to create a unique network address (like an email address).');
        if (!get_config('system', 'unicode_usernames')) {
            $nick_help .= ' ' . t('Allowed characters are a-z 0-9, - and _');
        }
        $nick_help .= '<span>';

        $privacy_role = ((x($_REQUEST, 'permissions_role')) ? $_REQUEST['permissions_role'] : "");

        $perm_roles = PermissionRoles::roles();

        $name = array('name', t('Channel name'), ((x($_REQUEST, 'name')) ? $_REQUEST['name'] : ''), $name_help, "*");
        $nickhub = '@' . App::get_hostname();
        $nickname = array('nickname', t('Choose a short nickname'), ((x($_REQUEST, 'nickname')) ? $_REQUEST['nickname'] : ''), $nick_help, "*");
        $role = array('permissions_role', t('Channel role and privacy'), ($privacy_role) ? $privacy_role : 'social', t('Select a channel permission role compatible with your usage needs and privacy requirements.'), $perm_roles);

        $o = replace_macros(get_markup_template('new_channel.tpl'), array(
            '$title' => t('Create a Channel'),
            '$desc' => t('A channel is a unique network identity. It can represent a person (social network profile), a forum (group), a business or celebrity page, a newsfeed, and many other things.'),
            '$label_import' => t('or <a href="import">import an existing channel</a> from another location.'),
            '$name' => $name,
            '$role' => $role,
            '$default_role' => $default_role,
            '$nickname' => $nickname,
            '$validate' => t('Validate'),
            '$submit' => t('Create'),
            '$channel_usage_message' => $channel_usage_message
        ));

        return $o;

    }


}
