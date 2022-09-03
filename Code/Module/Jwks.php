<?php

namespace Code\Module;

use Code\Lib\Keyutils;
use Code\Web\Controller;

class Jwks extends Controller
{

    public function init()
    {
        $m = '';
        $e = '';
        Keyutils::pemToMe(get_config('system', 'pubkey'), $m, $e);

        /**
         * RFC7518
         *
         * 6.3.1.1.  "n" (Modulus) Parameter
         *
         * The "n" (modulus) parameter contains the modulus value for the RSA
         * public key.  It is represented as a Base64urlUInt-encoded value.
         *
         * Note that implementers have found that some cryptographic libraries
         * prefix an extra zero-valued octet to the modulus representations they
         * return, for instance, returning 257 octets for a 2048-bit key, rather
         * than 256.  Implementations using such libraries will need to take
         * care to omit the extra octet from the base64url-encoded
         * representation.
         *
         */

        $l = strlen((string)$m);
        if ($l & 1) {
            $m = substr((string)$m, 1);
        }

        $keys = [
            [
                'e' => base64url_encode($e),
                'n' => base64url_encode($m),
                'kty' => 'RSA',
                'kid' => '0',
            ]
        ];


        $ret = [
            'keys' => $keys
        ];

        if (argc() > 1) {
            $entry = intval(argv(1));
            if ($keys[$entry]) {
                unset($keys[$entry]['kid']);
                json_return_and_die($keys[$entry], 'application/jwk+json');
            }
        }

        json_return_and_die($ret, 'application/jwk-set+json');
    }
}
