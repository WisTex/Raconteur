<?php

namespace Zotlabs\Lib;

use Zotlabs\Web\HTTPSig;

class JSalmon
{

    public static function sign($data, $key_id, $key, $data_type = 'application/x-zot+json')
    {

        $data = base64url_encode(json_encode($data, true), true); // strip padding
        $encoding = 'base64url';
        $algorithm = 'RSA-SHA256';

        $data = preg_replace('/\s+/', '', $data);

        // precomputed base64url encoding of data_type, encoding, algorithm concatenated with periods

        $precomputed = '.' . base64url_encode($data_type, true) . '.YmFzZTY0dXJs.UlNBLVNIQTI1Ng';

        $signature = base64url_encode(Crypto::sign($data . $precomputed, $key), true);

        return ([
            'signed' => true,
            'data' => $data,
            'data_type' => $data_type,
            'encoding' => $encoding,
            'alg' => $algorithm,
            'sigs' => [
                'value' => $signature,
                'key_id' => base64url_encode($key_id, true)
            ]
        ]);

    }

    public static function verify($x)
    {

        logger('verify');
        $ret = ['results' => []];

        if (!is_array($x)) {
            return false;
        }
        if (!(array_key_exists('signed', $x) && $x['signed'])) {
            return false;
        }

        $signed_data = preg_replace('/\s+/', '', $x['data']) . '.'
            . base64url_encode($x['data_type'], true) . '.'
            . base64url_encode($x['encoding'], true) . '.'
            . base64url_encode($x['alg'], true);

        $key = HTTPSig::get_key(EMPTY_STR, 'zot6', base64url_decode($x['sigs']['key_id']));
        logger('key: ' . print_r($key, true));
        if ($key['portable_id'] && $key['public_key']) {
            if (Crypto::verify($signed_data, base64url_decode($x['sigs']['value']), $key['public_key'])) {
                logger('verified');
                $ret = ['success' => true, 'signer' => $key['portable_id'], 'hubloc' => $key['hubloc']];
            }
        }

        return $ret;

    }

    public static function unpack($data)
    {
        return json_decode(base64url_decode($data), true);
    }


}