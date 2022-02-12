<?php

namespace Zotlabs\Module;

use Zotlabs\Web\Controller;
use Zotlabs\Lib\System;
use Zotlabs\Lib\Channel;
use Zotlabs\Extend\Hook;
use App;

class Webfinger extends Controller
{

    public function init()
    {

        logger('webfinger from ' . $_SERVER['REMOTE_ADDR']);

        // This is a public resource with relaxed CORS policy. Close the current login session.
        session_write_close();

        header('Access-Control-Allow-Origin: *');

        $result = [];

        if (!is_https_request()) {
            header($_SERVER['SERVER_PROTOCOL'] . ' ' . 500 . ' ' . 'Webfinger requires HTTPS');
            killme();
        }

        $resource = $_REQUEST['resource'];

        if (!$resource) {
            http_status_exit(404, 'Not found');
        }

        logger('webfinger: ' . $resource, LOGGER_DEBUG);

        // Determine whether to respond as a site actor
        // or a normal channel

        $site_query = false;
        if (
            strcasecmp(rtrim($resource, '/'), z_root()) === 0
            || strcasecmp($resource, 'acct:sys@' . App::get_hostname()) === 0
            || $resource === z_root() . '/channel/sys'
        ) {
            $site_query = true;
            $result['subject'] = $resource;
            $resource = z_root() . '/channel/sys';
        }

        if (strpos($resource, 'tag:' === 0)) {
            $arr = explode(':', $resource);
            if (count($arr) > 3 && $arr[2] === 'zotid') {
                $hash = $arr[3];
                $channel_target = Channel::from_hash($hash);
            }
        }

        if (strpos($resource, 'acct:') === 0) {
            $channel_nickname = punify(str_replace('acct:', '', $resource));
            if (strrpos($channel_nickname, '@') !== false) {
                $host = punify(substr($channel_nickname, strrpos($channel_nickname, '@') + 1));

                // If the webfinger address points off site, redirect to the correct site

                if (strcasecmp($host, App::get_hostname())) {
                    goaway('https://' . $host . '/.well-known/webfinger?f=&resource=' . $resource);
                }
                $channel_nickname = substr($channel_nickname, 0, strrpos($channel_nickname, '@'));
            }
        }
        if (strpos($resource, 'http') === 0) {
            $channel_nickname = str_replace(['~', '@'], ['', ''], basename($resource));
        }

        if ($channel_nickname) {
            $channel_target = Channel::from_username($channel_nickname);
        }

        if ($channel_target || $site_query) {
            $h = get_hubloc_addrs_by_hash($channel_target['channel_hash']);

            if (!isset($result['subject'])) {
                $result['subject'] = $resource;
            }

            $aliases = [
                z_root() . '/channel/' . $channel_target['channel_address'],
                z_root() . '/~' . $channel_target['channel_address'],
                z_root() . '/@' . $channel_target['channel_address']

            ];

            if ($h) {
                foreach ($h as $hh) {
                    $aliases[] = 'acct:' . $hh['hubloc_addr'];
                }
            }

            $result['aliases'] = [];

            $result['properties'] = [
                'http://webfinger.net/ns/name' => $site_query ? System::get_site_name() : $channel_target['channel_name'],
                'http://xmlns.com/foaf/0.1/name' => $site_query ? System::get_site_name() : $channel_target['channel_name'],
                'https://w3id.org/security/v1#publicKeyPem' => (($site_query) ? get_config('system', 'pubkey') : $channel_target['xchan_pubkey']),
                'http://purl.org/zot/federation' => ((get_config('system', 'activitypub', ACTIVITYPUB_ENABLED)) ? 'nomad,zot6,activitypub' : 'nomad,zot6')
            ];

            if ($site_query) {
                $aliases[] = z_root();
                $aliases[] = 'acct:sys@' . App::get_hostname();
            }

            foreach ($aliases as $alias) {
                if ($alias !== $result['subject']) {
                    $result['aliases'][] = $alias;
                }
            }

            $result['links'] = [

                [
                    'rel' => 'http://webfinger.net/rel/avatar',
                    'type' => $channel_target['xchan_photo_mimetype'],
                    'href' => $channel_target['xchan_photo_l']
                ],

                [
                    'rel' => 'http://webfinger.net/rel/blog',
                    'href' => z_root() . '/channel/' . $channel_target['channel_address'],
                ],

                [
                    'rel' => 'http://openid.net/specs/connect/1.0/issuer',
                    'href' => z_root()
                ],

                [
                    'rel' => 'http://purl.org/zot/protocol/6.0',
                    'type' => 'application/x-zot+json',
                    'href' => (($site_query) ? z_root() : z_root() . '/channel/' . $channel_target['channel_address']),
                ],

				[ 
					'rel'  => 'http://purl.org/nomad', 
					'type' => 'application/x-nomad+json', 
					'href' => (($site_query) ? z_root() : z_root() . '/channel/' . $channel_target['channel_address']),
				],

				[
					'rel'  => 'http://purl.org/openwebauth/v1',
					'type' => 'application/x-nomad+json',
					'href' => z_root() . '/owa'
				],

                [
                    'rel' => 'http://purl.org/openwebauth/v1',
                    'type' => 'application/x-zot+json',
                    'href' => z_root() . '/owa'
                ],

                [
                    'rel' => 'self',
                    'type' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
                    'href' => (($site_query) ? z_root() : z_root() . '/channel/' . $channel_target['channel_address'])
                ],

                [
                    'rel' => 'self',
                    'type' => 'application/activity+json',
                    'href' => (($site_query) ? z_root() : z_root() . '/channel/' . $channel_target['channel_address'])
                ],

                [
                    'rel' => 'http://ostatus.org/schema/1.0/subscribe',
                    'template' => z_root() . '/follow?url={uri}'
                ],
            ];
        }

        if (!$result) {
            header($_SERVER['SERVER_PROTOCOL'] . ' ' . 400 . ' ' . 'Bad Request');
            killme();
        }

        $arr = ['channel' => $channel_target, 'request' => $_REQUEST, 'result' => $result];
        Hook::call('webfinger', $arr);


        json_return_and_die($arr['result'], 'application/jrd+json', true);
    }
}
