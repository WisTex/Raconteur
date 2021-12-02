<?php /** @file */

/** 
 * OAuth-1 server
 * 
 */

define('REQUEST_TOKEN_DURATION', 300);
define('ACCESS_TOKEN_DURATION', 31536000);

require_once('library/OAuth1.php');


class ZotOAuth1DataStore extends OAuth1DataStore
{

    public function gen_token()
    {
        return md5(base64_encode(pack('N6', mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), uniqid())));
    }

    public function lookup_consumer($consumer_key)
    {
        logger('consumer_key: ' . $consumer_key, LOGGER_DEBUG);

        $r = q("SELECT client_id, pw, redirect_uri FROM clients WHERE client_id = '%s'",
            dbesc($consumer_key)
        );

        if ($r) {
            App::set_oauth_key($consumer_key);
            return new OAuth1Consumer($r[0]['client_id'], $r[0]['pw'], $r[0]['redirect_uri']);
        }
        return null;
    }

    public function lookup_token($consumer, $token_type, $token)
    {

        logger(__function__ . ':' . $consumer . ', ' . $token_type . ', ' . $token, LOGGER_DEBUG);

        $r = q("SELECT id, secret, auth_scope, expires, uid  FROM tokens WHERE client_id = '%s' AND auth_scope = '%s' AND id = '%s'",
            dbesc($consumer->key),
            dbesc($token_type),
            dbesc($token)
        );

        if ($r) {
            $ot = new OAuth1Token($r[0]['id'], $r[0]['secret']);
            $ot->scope = $r[0]['auth_scope'];
            $ot->expires = $r[0]['expires'];
            $ot->uid = $r[0]['uid'];
            return $ot;
        }
        return null;
    }

    public function lookup_nonce($consumer, $token, $nonce, $timestamp)
    {

        $r = q("SELECT id, secret FROM tokens WHERE client_id = '%s' AND id = '%s' AND expires = %d",
            dbesc($consumer->key),
            dbesc($nonce),
            intval($timestamp)
        );

        if ($r) {
            return new OAuth1Token($r[0]['id'], $r[0]['secret']);
        }
        return null;
    }

    public function new_request_token($consumer, $callback = null)
    {

        logger(__function__ . ':' . $consumer . ', ' . $callback, LOGGER_DEBUG);

        $key = $this->gen_token();
        $sec = $this->gen_token();

        if ($consumer->key) {
            $k = $consumer->key;
        } else {
            $k = $consumer;
        }

        $r = q("INSERT INTO tokens (id, secret, client_id, auth_scope, expires, uid) VALUES ('%s','%s','%s','%s', %d, 0)",
            dbesc($key),
            dbesc($sec),
            dbesc($k),
            'request',
            time() + intval(REQUEST_TOKEN_DURATION));

        if (!$r) {
            return null;
        }
        return new OAuth1Token($key, $sec);
    }

    public function new_access_token($token, $consumer, $verifier = null)
    {

        logger(__function__ . ':' . $token . ', ' . $consumer . ', ' . $verifier, LOGGER_DEBUG);

        // return a new access token attached to this consumer
        // for the user associated with this token if the request token
        // is authorized
        // should also invalidate the request token

        $ret = null;

        // get user for this verifier
        $uverifier = get_config("oauth", $verifier);
        logger(__function__ . ':' . $verifier . ', ' . $uverifier, LOGGER_DEBUG);
        if (is_null($verifier) || ($uverifier !== false)) {

            $key = $this->gen_token();
            $sec = $this->gen_token();

            $r = q("INSERT INTO tokens (id, secret, client_id, auth_scope, expires, uid) VALUES ('%s','%s','%s','%s', %d, %d)",
                dbesc($key),
                dbesc($sec),
                dbesc($consumer->key),
                'access',
                time() + intval(ACCESS_TOKEN_DURATION),
                intval($uverifier));

            if ($r) {
                $ret = new OAuth1Token($key, $sec);
            }
        }


        q("DELETE FROM tokens WHERE id='%s'", $token->key);


        if (!is_null($ret) && $uverifier !== false) {
            del_config('oauth', $verifier);
        }
        return $ret;
    }
}

class ZotOAuth1 extends OAuth1Server
{

    public function __construct()
    {
        parent::__construct(new ZotOAuth1DataStore());
        $this->add_signature_method(new OAuth1SignatureMethod_PLAINTEXT());
        $this->add_signature_method(new OAuth1SignatureMethod_HMAC_SHA1());
    }

    public function loginUser($uid)
    {

        logger("ZotOAuth1::loginUser $uid");

        $r = q("SELECT * FROM channel WHERE channel_id = %d LIMIT 1",
            intval($uid)
        );
        if ($r) {
            $record = $r[0];
        } else {
            logger('ZotOAuth1::loginUser failure: ' . print_r($_SERVER, true), LOGGER_DEBUG);
            header('HTTP/1.0 401 Unauthorized');
            echo('This api requires login');
            killme();
        }

        $_SESSION['uid'] = $record['channel_id'];
        $_SESSION['addr'] = $_SERVER['REMOTE_ADDR'];

        $x = q("select * from account where account_id = %d limit 1",
            intval($record['channel_account_id'])
        );
        if ($x) {
            require_once('include/security.php');
            authenticate_success($x[0], null, true, false, true, true);
            $_SESSION['allow_api'] = true;
        }
    }

}

