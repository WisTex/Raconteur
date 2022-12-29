<?php

use OAuth2\Request;
use OAuth2\GrantType;
use Code\Identity\OAuth2Storage;
use Code\Identity\OAuth2Server;
use Code\Lib\Libzot;
use Code\Lib\System;
use Code\Web\HTTPSig;
use Code\Lib\Channel;
use Code\Extend\Hook;

require_once('include/auth.php');
require_once('include/security.php');


/**
 * API Login via basic-auth, OpenWebAuth, or OAuth2
 */

function api_login()
{

    $record = null;
    $remote_auth = false;
    $sigblock = null;

    if (array_key_exists('REDIRECT_REMOTE_USER', $_SERVER) && (! array_key_exists('HTTP_AUTHORIZATION', $_SERVER))) {
        $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_REMOTE_USER'];
    }

    // login with oauth

    try {
        // OAuth 2.0
        $storage = new OAuth2Storage(DBA::$dba->db);
        $server = new OAuth2Server($storage);
        // Add the "Client Credentials" grant type (it is the simplest of the grant types)
        $server->addGrantType(new GrantType\ClientCredentials($storage));
        // Add the "Authorization Code" grant type (this is where the oauth magic happens)
        $server->addGrantType(new GrantType\AuthorizationCode($storage));
        // Add the "Refresh Token" grant type
        $server->addGrantType(new GrantType\RefreshToken($storage));

        $request = Request::createFromGlobals();
        if ($server->verifyResourceRequest($request)) {
            $token = $server->getAccessTokenData($request);
            $uid = $token['user_id'];
            $r = q(
                "SELECT * FROM channel WHERE channel_id = %d LIMIT 1",
                intval($uid)
            );
            if ($r) {
                $record = $r[0];
            } else {
                header('HTTP/1.0 401 Unauthorized');
                echo('This api requires login');
                killme();
            }

            $_SESSION['uid'] = $record['channel_id'];
            $_SESSION['addr'] = $_SERVER['REMOTE_ADDR'];

            $x = q(
                "select * from account where account_id = %d LIMIT 1",
                intval($record['channel_account_id'])
            );
            if ($x) {
                authenticate_success($x[0], false, true, false, true, true);
                $_SESSION['allow_api'] = true;
                Hook::call('logged_in', App::$user);
                return;
            }
        }

    } catch (Exception $e) {
        logger($e->getMessage());
    }


    if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
        /* Basic authentication */

        if (str_starts_with(trim($_SERVER['HTTP_AUTHORIZATION']), 'Basic')) {
            // ignore base64 decoding errors caused by tricksters
            $userpass = @base64_decode(substr(trim($_SERVER['HTTP_AUTHORIZATION']), 6)) ;
            if (strlen($userpass)) {
                list($name, $password) = explode(':', $userpass);
                $_SERVER['PHP_AUTH_USER'] = $name;
                $_SERVER['PHP_AUTH_PW']   = $password;
            }
        }

        /* OpenWebAuth */

        if (str_starts_with(trim($_SERVER['HTTP_AUTHORIZATION']), 'Signature')) {
            $record = null;

            $sigblock = HTTPSig::parse_sigheader($_SERVER['HTTP_AUTHORIZATION']);
            if ($sigblock) {
                $keyId = str_replace('acct:', '', $sigblock['keyId']);
                if ($keyId) {
                    $r = hubloc_id_addr_query($keyId);
                    if (! $r) {
                        HTTPSig::get_zotfinger_key($keyId);
                        $r = hubloc_id_addr_query($keyId);
                    }

                    if ($r) {
                        $r = Libzot::zot_record_preferred($r);
                        $c = Channel::from_hash($r['hubloc_hash']);
                        if ($c) {
                            $a = q(
                                "select * from account where account_id = %d limit 1",
                                intval($c['channel_account_id'])
                            );
                            if ($a) {
                                $record = [ 'channel' => $c, 'account' => $a[0] ];
                                $channel_login = $c['channel_id'];
                            }
                        }
                    }

                    if ($record) {
                        $verified = HTTPSig::verify(EMPTY_STR, $record['channel']['channel_pubkey']);
                        if (! ($verified && $verified['header_signed'] && $verified['header_valid'])) {
                            $record = null;
                        }
                    }
                }
            }
        }
    }


    // process normal login request

    if (isset($_SERVER['PHP_AUTH_USER']) && (! $record)) {
        $channel_login = 0;
        $record = account_verify_password($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        if ($record && $record['channel']) {
            $channel_login = $record['channel']['channel_id'];
        }
    }

    if (isset($record['account'])) {
        authenticate_success($record['account']);

        if ($channel_login) {
            change_channel($channel_login);
        }

        $_SESSION['allow_api'] = true;
        return true;
    } else {
        $_SERVER['PHP_AUTH_PW'] = '*****';
        logger('API_login failure: ' . print_r($_SERVER, true), LOGGER_DEBUG);
        log_failed_login('API login failure');
        retry_basic_auth();
    }
}


function retry_basic_auth($method = 'Basic')
{
    header('WWW-Authenticate: ' . $method . ' realm="' . System::get_project_name() . '"');
    header('HTTP/1.0 401 Unauthorized');
    echo( t('This API method requires authentication.'));
    killme();
}
