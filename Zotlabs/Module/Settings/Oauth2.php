<?php

namespace Zotlabs\Module\Settings;

use Zotlabs\Lib\Apps;

class Oauth2
{


    public function post()
    {

        if (x($_POST, 'remove')) {
            check_form_security_token_redirectOnErr('/settings/oauth2', 'settings_oauth2');
            $name = ((x($_POST, 'name')) ? escape_tags(trim($_POST['name'])) : '');
            logger("REMOVE! " . $name . " uid: " . local_channel());
            $key = $_POST['remove'];
            q(
                "DELETE FROM oauth_authorization_codes WHERE client_id='%s' AND user_id=%d",
                dbesc($name),
                intval(local_channel())
            );
            q(
                "DELETE FROM oauth_access_tokens WHERE client_id='%s' AND user_id=%d",
                dbesc($name),
                intval(local_channel())
            );
            q(
                "DELETE FROM oauth_refresh_tokens WHERE client_id='%s' AND user_id=%d",
                dbesc($name),
                intval(local_channel())
            );
            goaway(z_root() . "/settings/oauth2/");
            return;
        }

        if ((argc() > 2) && (argv(2) === 'edit' || argv(2) === 'add') && x($_POST, 'submit')) {
            check_form_security_token_redirectOnErr('/settings/oauth2', 'settings_oauth2');

            $name = ((x($_POST, 'name')) ? escape_tags(trim($_POST['name'])) : '');
            $clid = ((x($_POST, 'clid')) ? escape_tags(trim($_POST['clid'])) : '');
            $secret = ((x($_POST, 'secret')) ? escape_tags(trim($_POST['secret'])) : '');
            $redirect = ((x($_POST, 'redirect')) ? escape_tags(trim($_POST['redirect'])) : '');
            $grant = ((x($_POST, 'grant')) ? escape_tags(trim($_POST['grant'])) : '');
            $scope = ((x($_POST, 'scope')) ? escape_tags(trim($_POST['scope'])) : '');
            logger('redirect: ' . $redirect);
            $ok = true;
            if ($clid == '' || $secret == '') {
                $ok = false;
                notice(t('ID and Secret are required') . EOL);
            }

            if ($ok) {
                if ($_POST['submit'] == t("Update")) {
                    $r = q(
                        "UPDATE oauth_clients SET
								client_name = '%s',
								client_id = '%s',
								client_secret = '%s',
								redirect_uri = '%s',
								grant_types = '%s',
								scope = '%s', 
								user_id = %d
							WHERE client_id='%s' and user_id = %s",
                        dbesc($name),
                        dbesc($clid),
                        dbesc($secret),
                        dbesc($redirect),
                        dbesc($grant),
                        dbesc($scope),
                        intval(local_channel()),
                        dbesc($clid),
                        intval(local_channel())
                    );
                } else {
                    $r = q(
                        "INSERT INTO oauth_clients (client_name, client_id, client_secret, redirect_uri, grant_types, scope, user_id)
						VALUES ('%s','%s','%s','%s','%s','%s',%d)",
                        dbesc($name),
                        dbesc($clid),
                        dbesc($secret),
                        dbesc($redirect),
                        dbesc($grant),
                        dbesc($scope),
                        intval(local_channel())
                    );
                    $r = q(
                        "INSERT INTO xperm (xp_client, xp_channel, xp_perm) VALUES ('%s', %d, '%s') ",
                        dbesc($name),
                        intval(local_channel()),
                        dbesc('all')
                    );
                }
            }
            goaway(z_root() . "/settings/oauth2/");
            return;
        }
    }

    public function get()
    {

        if (!Apps::system_app_installed(local_channel(), 'Clients')) {
            return;
        }

        if ((argc() > 2) && (argv(2) === 'add')) {
            $tpl = get_markup_template("settings_oauth2_edit.tpl");
            $o .= replace_macros($tpl, array(
                '$form_security_token' => get_form_security_token("settings_oauth2"),
                '$title' => t('Add OAuth2 application'),
                '$submit' => t('Submit'),
                '$cancel' => t('Cancel'),
                '$name' => array('name', t('Name'), '', t('Name of application')),
                '$clid' => array('clid', t('Consumer ID'), random_string(16), t('Automatically generated - change if desired. Max length 20')),
                '$secret' => array('secret', t('Consumer Secret'), random_string(16), t('Automatically generated - change if desired. Max length 20')),
                '$redirect' => array('redirect', t('Redirect'), '', t('Redirect URI - leave blank unless your application specifically requires this')),
                '$grant' => array('grant', t('Grant Types'), '', t('leave blank unless your application specifically requires this')),
                '$scope' => array('scope', t('Authorization scope'), '', t('leave blank unless your application specifically requires this')),
            ));
            return $o;
        }

        if ((argc() > 3) && (argv(2) === 'edit')) {
            $r = q(
                "SELECT * FROM oauth_clients WHERE client_id='%s' AND user_id= %d",
                dbesc(argv(3)),
                intval(local_channel())
            );

            if (!$r) {
                notice(t('OAuth2 Application not found.'));
                return;
            }

            $app = $r[0];

            $tpl = get_markup_template("settings_oauth2_edit.tpl");
            $o .= replace_macros($tpl, array(
                '$form_security_token' => get_form_security_token("settings_oauth2"),
                '$title' => t('Add application'),
                '$submit' => t('Update'),
                '$cancel' => t('Cancel'),
                '$name' => array('name', t('Name'), $app['client_name'], t('Name of application')),
                '$clid' => array('clid', t('Consumer ID'), $app['client_id'], t('Automatically generated - change if desired. Max length 20')),
                '$secret' => array('secret', t('Consumer Secret'), $app['client_secret'], t('Automatically generated - change if desired. Max length 20')),
                '$redirect' => array('redirect', t('Redirect'), $app['redirect_uri'], t('Redirect URI - leave blank unless your application specifically requires this')),
                '$grant' => array('grant', t('Grant Types'), $app['grant_types'], t('leave blank unless your application specifically requires this')),
                '$scope' => array('scope', t('Authorization scope'), $app['scope'], t('leave blank unless your application specifically requires this')),
            ));
            return $o;
        }

        if ((argc() > 3) && (argv(2) === 'delete')) {
            check_form_security_token_redirectOnErr('/settings/oauth2', 'settings_oauth2', 't');

            $r = q(
                "DELETE FROM oauth_clients WHERE client_id = '%s' AND user_id = %d",
                dbesc(argv(3)),
                intval(local_channel())
            );
            $r = q(
                "DELETE FROM oauth_access_tokens WHERE client_id = '%s' AND user_id = %d",
                dbesc(argv(3)),
                intval(local_channel())
            );
            $r = q(
                "DELETE FROM oauth_authorization_codes WHERE client_id = '%s' AND user_id = %d",
                dbesc(argv(3)),
                intval(local_channel())
            );
            $r = q(
                "DELETE FROM oauth_refresh_tokens WHERE client_id = '%s' AND user_id = %d",
                dbesc(argv(3)),
                intval(local_channel())
            );
            goaway(z_root() . "/settings/oauth2/");
            return;
        }


        $r = q(
            "SELECT * FROM oauth_clients WHERE user_id = %d ",
            intval(local_channel())
        );

        $c = q(
            "select client_id, access_token from oauth_access_tokens where user_id = %d",
            intval(local_channel())
        );
        if ($r && $c) {
            foreach ($c as $cv) {
                for ($x = 0; $x < count($r); $x++) {
                    if ($r[$x]['client_id'] === $cv['client_id']) {
                        if (!array_key_exists('tokens', $r[$x])) {
                            $r[$x]['tokens'] = [];
                        }
                        $r[$x]['tokens'][] = $cv['access_token'];
                    }
                }
            }
        }

        $tpl = get_markup_template("settings_oauth2.tpl");
        $o .= replace_macros($tpl, array(
            '$form_security_token' => get_form_security_token("settings_oauth2"),
            '$baseurl' => z_root(),
            '$title' => t('Connected OAuth2 Apps'),
            '$add' => t('Add application'),
            '$edit' => t('Edit'),
            '$delete' => t('Delete'),
            '$consumerkey' => t('Client key starts with'),
            '$noname' => t('No name'),
            '$remove' => t('Remove authorization'),
            '$apps' => $r,
        ));
        return $o;
    }
}
