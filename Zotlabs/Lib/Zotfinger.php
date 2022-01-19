<?php

namespace Zotlabs\Lib;

use Zotlabs\Web\HTTPSig;

class Zotfinger
{

    public static function exec($resource, $channel = null, $verify = true)
    {

        if (!$resource) {
            return false;
        }

        $m = parse_url($resource);

        if ($m['host'] !== punify($m['host'])) {
            $url = str_replace($m['host'], punify($m['host']), $url);
            $m['host'] = punify($m['host']);
        }

        $data = json_encode(['zot_token' => random_string()]);

        if ($channel && $m) {
            $headers = [
                'Accept' => 'application/x-zot+json',
                'Content-Type' => 'application/x-zot+json',
                'X-Zot-Token' => random_string(),
                'Digest' => HTTPSig::generate_digest_header($data),
                'Host' => $m['host'],
                '(request-target)' => 'post ' . get_request_string($resource)
            ];
            $h = HTTPSig::create_sig($headers, $channel['channel_prvkey'], channel_url($channel), false);
        } else {
            $h = ['Accept: application/x-zot+json'];
        }

        $result = [];

        $redirects = 0;
        $x = z_post_url($resource, $data, $redirects, ['headers' => $h]);

        if (intval($x['return_code'] === 404)) {

            // if this resource returns "not found", mark any corresponding hubloc deleted and
            // change the primary if needed. We need to catch it at this level because we
            // can't really sync the locations if we've got no data to work with. 
        
            $h = Activity::get_actor_hublocs($resource, 'zot6,not_deleted');
            if ($h) {
                $primary = intval($h[0]['hubloc_primary']);

                q("update hubloc set hubloc_deleted = 1, hubloc_primary = 0 where hubloc_id = %d",
                    intval($h[0]['hubloc_id'])
                );
                if ($primary) {
                    // find another hub that can act as primary since this one cannot. If this
                    // fails, it may be that there are no other instances of the channel known
                    // to this site. In that case, we'll just leave the entry without a primary
                    // until/id we hear from them again at a new location. 
                    $a = q("select * from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0",
                        dbesc($h[0]['hubloc_hash'])
                    );
                    if ($a) {
                        hubloc_change_primary(array_shift($a));
                    }
               }
            }
        }

    
        if ($x['success']) {
            if ($verify) {
                $result['signature'] = HTTPSig::verify($x, EMPTY_STR, 'zot6');
            }

            $result['data'] = json_decode($x['body'], true);

            if ($result['data'] && is_array($result['data']) && array_key_exists('encrypted', $result['data']) && $result['data']['encrypted']) {
                $result['data'] = json_decode(Crypto::unencapsulate($result['data'], get_config('system', 'prvkey')), true);
            }

            return $result;
        }

        return false;
    }
}
