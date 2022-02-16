<?php

namespace Code\Lib;

use Exception;
use Code\Extend\Hook;

class Crypto
{

    public static $openssl_algorithms = [

        // zot6 nickname,   opensslname,   keylength, ivlength

        [ 'aes256ctr',      'aes-256-ctr',      32, 16 ],
        [ 'camellia256cfb', 'camellia-256-cfb', 32, 16 ],
        [ 'cast5cfb',       'cast5-cfb',        16, 8  ]

    ];


    public static function methods()
    {
        $ret = [];

        foreach (self::$openssl_algorithms as $ossl) {
            $ret[] = $ossl[0] . '.oaep';
        }

        Hook::call('crypto_methods', $ret);
        return $ret;
    }


    public static function signing_methods()
    {

        $ret = [ 'sha256' ];
        Hook::call('signing_methods', $ret);
        return $ret;
    }


    public static function new_keypair($bits)
    {

        $openssl_options = [
            'digest_alg'       => 'sha1',
            'private_key_bits' => $bits,
            'encrypt_key'      => false
        ];

        $conf = get_config('system', 'openssl_conf_file');

        if ($conf) {
            $openssl_options['config'] = $conf;
        }

        $result = openssl_pkey_new($openssl_options);

        if (empty($result)) {
            return false;
        }

        // Get private key

        $response = [ 'prvkey' => '', 'pubkey' => '' ];

        openssl_pkey_export($result, $response['prvkey']);

        // Get public key
        $pkey = openssl_pkey_get_details($result);
        $response['pubkey'] = $pkey["key"];

        return $response;
    }


    public static function sign($data, $key, $alg = 'sha256')
    {

        if (! $key) {
            return false;
        }

        $sig = '';
        openssl_sign($data, $sig, $key, $alg);
        return $sig;
    }


    public static function verify($data, $sig, $key, $alg = 'sha256')
    {

        if (! $key) {
            return false;
        }

        try {
            $verify = openssl_verify($data, $sig, $key, $alg);
        } catch (Exception $e) {
            $verify = (-1);
        }

        if ($verify === (-1)) {
            while ($msg = openssl_error_string()) {
                logger('openssl_verify: ' . $msg, LOGGER_NORMAL, LOG_ERR);
            }
            btlogger('openssl_verify: key: ' . $key, LOGGER_DEBUG, LOG_ERR);
        }

        return (($verify > 0) ? true : false);
    }

    public static function encapsulate($data, $pubkey, $alg)
    {

        if (! ($alg && $pubkey)) {
            return $data;
        }

        $alg_base = $alg;
        $padding  = OPENSSL_PKCS1_PADDING;

        $exts = explode('.', $alg);
        if (count($exts) > 1) {
            switch ($exts[1]) {
                case 'oaep':
                    $padding = OPENSSL_PKCS1_OAEP_PADDING;
                    break;
                default:
                    break;
            }
            $alg_base = $exts[0];
        }

        $method = null;

        foreach (self::$openssl_algorithms as $ossl) {
            if ($ossl[0] === $alg_base) {
                $method = $ossl;
                break;
            }
        }

        if ($method) {
                $result = [ 'encrypted' => true ];

                $key = openssl_random_pseudo_bytes(256);
                $iv  = openssl_random_pseudo_bytes(256);

                $key1 = substr($key, 0, $method[2]);
                $iv1  = substr($iv, 0, $method[3]);

                $result['data'] = base64url_encode(openssl_encrypt($data, $method[1], $key1, OPENSSL_RAW_DATA, $iv1), true);

                openssl_public_encrypt($key, $k, $pubkey, $padding);
                openssl_public_encrypt($iv, $i, $pubkey, $padding);

                $result['alg'] = $alg;
                $result['key'] = base64url_encode($k, true);
                $result['iv']  = base64url_encode($i, true);
                return $result;
        } else {
            $x = [ 'data' => $data, 'pubkey' => $pubkey, 'alg' => $alg, 'result' => $data ];
            Hook::call('crypto_encapsulate', $x);
            return $x['result'];
        }
    }

    public static function unencapsulate($data, $prvkey)
    {

        if (! (is_array($data) && array_key_exists('encrypted', $data) && array_key_exists('alg', $data) && $data['alg'])) {
            logger('not encrypted');

            return $data;
        }

        $alg_base = $data['alg'];
        $padding  = OPENSSL_PKCS1_PADDING;

        $exts = explode('.', $data['alg']);
        if (count($exts) > 1) {
            switch ($exts[1]) {
                case 'oaep':
                    $padding = OPENSSL_PKCS1_OAEP_PADDING;
                    break;
                default:
                    break;
            }
            $alg_base = $exts[0];
        }

        $method = null;

        foreach (self::$openssl_algorithms as $ossl) {
            if ($ossl[0] === $alg_base) {
                $method = $ossl;
                break;
            }
        }

        if ($method) {
            openssl_private_decrypt(base64url_decode($data['key']), $k, $prvkey, $padding);
            openssl_private_decrypt(base64url_decode($data['iv']), $i, $prvkey, $padding);
            return openssl_decrypt(base64url_decode($data['data']), $method[1], substr($k, 0, $method[2]), OPENSSL_RAW_DATA, substr($i, 0, $method[3]));
        } else {
            $x = [ 'data' => $data, 'prvkey' => $prvkey, 'alg' => $data['alg'], 'result' => $data ];
            Hook::call('crypto_unencapsulate', $x);
            return $x['result'];
        }
    }
}
