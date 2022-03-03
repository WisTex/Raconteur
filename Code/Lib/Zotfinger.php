<?php

namespace Code\Lib;

use Code\Web\HTTPSig;
use Code\Lib\Channel;
    
class Zotfinger
{

    public static function exec($resource, $channel = null, $verify = true, $recurse = true)
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
				'Accept' => 'application/x-nomad+json, application/x-zot+json', 
				'Content-Type' => 'application/x-nomad+json',
                'X-Zot-Token' => random_string(),
                'Digest' => HTTPSig::generate_digest_header($data),
                'Host' => $m['host'],
                '(request-target)' => 'post ' . get_request_string($resource)
            ];
            $h = HTTPSig::create_sig($headers, $channel['channel_prvkey'], Channel::url($channel), false);
        }
        else {
            $h = ['Accept: application/x-nomad+json, application/x-zot+json'];
        }

        $result = [];

        $redirects = 0;
        $x = z_post_url($resource, $data, $redirects, ['headers' => $h]);

        if (in_array(intval($x['return_code']), [ 404, 410 ]) && $recurse) {

            // The resource has been deleted or doesn't exist at this location.
            // Try to find another nomadic resource for this channel and return that.

            // First, see if there's a hubloc for this site. Fetch that record to
            // obtain the nomadic identity hash. Then use that to find any additional
            // nomadic locations.
    
            $h = Activity::get_actor_hublocs($resource, 'nomad');
            if ($h) {
                // mark this location deleted
                hubloc_delete($h[0]);
                $hubs = Activity::get_actor_hublocs($h[0]['hubloc_hash']);
                if ($hubs) {
                    foreach ($hubs as $hub) {
                        if ($hub['hubloc_id_url'] !== $resource and !$hub['hubloc_deleted']) {
                            $rzf = self::exec($hub['hubloc_id_url'],$channel,$verify);
                            if ($rzf) {
                                return $rzf;
                            }
                        }
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

