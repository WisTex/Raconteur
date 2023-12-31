<?php

namespace Code\Module;

use App;
use Code\Lib\Libzot;
use Code\Web\Controller;
use Code\Web\HTTPSig;
use Code\Lib\SConfig;
use Code\Lib\Channel;
use Code\Lib\Url;
use Code\Extend\Hook;

class Magic extends Controller
{

    public function init()
    {

        logger('mod_magic: invoked', LOGGER_DEBUG);

        logger('args: ' . print_r($_REQUEST, true), LOGGER_DATA);

        $addr = ((x($_REQUEST, 'addr')) ? $_REQUEST['addr'] : '');
        $bdest = ((x($_REQUEST, 'bdest')) ? $_REQUEST['bdest'] : '');
        $dest = ((x($_REQUEST, 'dest')) ? $_REQUEST['dest'] : '');
        $rev = ((x($_REQUEST, 'rev')) ? intval($_REQUEST['rev']) : 0);
        $owa = ((x($_REQUEST, 'owa')) ? intval($_REQUEST['owa']) : 0);
        $delegate = ((x($_REQUEST, 'delegate')) ? $_REQUEST['delegate'] : '');

        // bdest is preferred as it is hex-encoded and can survive url rewrite and argument parsing

        if ($bdest) {
            $dest = hex2bin($bdest);
        }

        $parsed = parse_url($dest);

        if (!$parsed) {
            goaway($dest);
        }

        $basepath = $parsed['scheme'] . '://' . $parsed['host'] . (($parsed['port']) ? ':' . $parsed['port'] : '');
        $owapath = SConfig::Get($basepath, 'system', 'openwebauth', $basepath . '/owa');

        // This is ready-made for a plugin that provides a blacklist or "ask me" before blindly authenticating.
        // By default, we'll proceed without asking.

        $arr = [
            'channel_id' => local_channel(),
            'destination' => $dest,
            'proceed' => true
        ];

        Hook::call('magic_auth', $arr);
        $dest = $arr['destination'];
        if (!$arr['proceed']) {
            goaway($dest);
        }

        if ((get_observer_hash()) && (stripos($dest, z_root()) === 0)) {
            // We are already authenticated on this site and a registered observer.
            // First check if this is a delegate request on the local system and process accordingly.
            // Otherwise redirect.

            if ($delegate) {
                $r = q(
                    "select * from channel left join hubloc on channel_hash = hubloc_hash where hubloc_addr = '%s' and hubloc_deleted = 0 limit 1",
                    dbesc($delegate)
                );

                if ($r) {
                    $c = array_shift($r);
                    if (perm_is_allowed($c['channel_id'], get_observer_hash(), 'delegate')) {
                        $tmp = $_SESSION;
                        $_SESSION['delegate_push'] = $tmp;
                        $_SESSION['delegate_channel'] = $c['channel_id'];
                        $_SESSION['delegate'] = get_observer_hash();
                        $_SESSION['account_id'] = intval($c['channel_account_id']);

                        change_channel($c['channel_id']);
                    }
                }
            }

            goaway($dest);
        }

        if (local_channel()) {
            $channel = App::get_channel();

            // OpenWebAuth

            if ($owa) {
                $dest = strip_zids($dest);
                $dest = strip_query_param($dest, 'f');

                $headers = [];
				$headers['Accept'] = Libzot::getAccepts();
				$headers['Content-Type'] = 'application/x-nomad+json';
                $headers['X-Open-Web-Auth'] = random_string();
                $headers['Host'] = $parsed['host'];
                $headers['(request-target)'] = 'get ' . '/owa';

                $headers = HTTPSig::create_sig($headers, $channel['channel_prvkey'], Channel::url($channel), true, 'sha512');

                $x = Url::get($owapath, ['headers' => $headers]);
                logger('owa fetch returned: ' . print_r($x, true), LOGGER_DATA);
                if ($x['success']) {
                    $j = json_decode($x['body'], true);
                    if ($j['success'] && $j['encrypted_token']) {
                        // decrypt the token using our private key
                        $token = '';
                        openssl_private_decrypt(base64url_decode($j['encrypted_token']), $token, $channel['channel_prvkey']);
                        $x = strpbrk($dest, '?&');
                        // redirect using the encrypted token which will be exchanged for an authenticated session
                        $args = (($x) ? '&owt=' . $token : '?f=&owt=' . $token) . (($delegate) ? '&delegate=1' : '');
                        goaway($dest . $args);
                    }
                }
            }
        }
        goaway($dest);
    }
}
