<?php

namespace Code\Module;

use Code\Web\HTTPSig;
use Code\Lib\Verify;
use Code\Web\Controller;

/**
 * OpenWebAuth verifier and token generator
 * See spec/OpenWebAuth/Home.md
 * Requests to this endpoint should be signed using HTTP Signatures
 * using the 'Authorization: Signature' authentication method
 * If the signature verifies a token is returned.
 *
 * This token may be exchanged for an authenticated cookie.
 */
class Owa extends Controller
{

    public function init()
    {

        $ret = ['success' => false];

        if (array_key_exists('REDIRECT_REMOTE_USER', $_SERVER) && (!array_key_exists('HTTP_AUTHORIZATION', $_SERVER))) {
            $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_REMOTE_USER'];
        }


        if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER) && str_starts_with(trim($_SERVER['HTTP_AUTHORIZATION']), 'Signature')) {
            $sigblock = HTTPSig::parse_sigheader($_SERVER['HTTP_AUTHORIZATION']);
            if ($sigblock) {
                $keyId = $sigblock['keyId'];
                if ($keyId) {
                    $r = q(
                        "select * from hubloc left join xchan on hubloc_hash = xchan_hash 
						where ( hubloc_addr = '%s' or hubloc_id_url = '%s' ) and hubloc_deleted = 0 and xchan_pubkey != '' ",
                        dbesc(str_replace('acct:', '', $keyId)),
                        dbesc($keyId)
                    );
                    if (!$r) {
                        $found = discover_resource(str_replace('acct:', '', $keyId));
                        if ($found) {
                            $r = q(
                                "select * from hubloc left join xchan on hubloc_hash = xchan_hash 
								where ( hubloc_addr = '%s' or hubloc_id_url = '%s' ) and hubloc_deleted = 0 and xchan_pubkey != '' ",
                                dbesc(str_replace('acct:', '', $keyId)),
                                dbesc($keyId)
                            );
                        }
                    }
                    if ($r) {
                        foreach ($r as $hubloc) {
                            $verified = HTTPSig::verify(file_get_contents('php://input'), $hubloc['xchan_pubkey']);
                            if ($verified && $verified['header_signed'] && $verified['header_valid'] && ($verified['content_valid'] || (!$verified['content_signed']))) {
                                logger('OWA header: ' . print_r($verified, true), LOGGER_DATA);
                                logger('OWA success: ' . $hubloc['hubloc_addr'], LOGGER_DATA);
                                $ret['success'] = true;
                                $token = random_string(32);
                                Verify::create('owt', 0, $token, $hubloc['hubloc_addr']);
                                $result = '';
                                openssl_public_encrypt($token, $result, $hubloc['xchan_pubkey']);
                                $ret['encrypted_token'] = base64url_encode($result);
                                break;
                            } else {
                                logger('OWA fail: ' . $hubloc['hubloc_id'] . ' ' . $hubloc['hubloc_addr']);
                            }
                        }
                    }
                }
            }
        }
        json_return_and_die($ret, 'application/x-nomad+json');
    }
}
