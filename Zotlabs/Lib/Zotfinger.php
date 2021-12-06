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
				'Accept' => 'application/x-nomad+json, application/x-zot+json', 
				'Content-Type' => 'application/x-nomad+json',
                'X-Zot-Token' => random_string(),
                'Digest' => HTTPSig::generate_digest_header($data),
                'Host' => $m['host'],
                '(request-target)' => 'post ' . get_request_string($resource)
            ];
            $h = HTTPSig::create_sig($headers, $channel['channel_prvkey'], channel_url($channel), false);
        } else {
            $h = ['Accept: application/x-nomad+json, application/x-zot+json'];
        }

        $result = [];

        $redirects = 0;
        $x = z_post_url($resource, $data, $redirects, ['headers' => $h]);

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

