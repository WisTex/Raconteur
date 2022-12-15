<?php

namespace Code\Module;

use App;
use Code\Lib\Libzot;
use Code\Lib\ActivityStreams;
use Code\Lib\Activity;
use Code\Lib\LDSignatures;
use Code\Lib\Crypto;
use Code\Web\HTTPSig;
use Code\Web\Controller;
use Code\Lib\Channel;
use Code\Extend\Hook;

require_once('include/conversation.php');

class Home extends Controller
{

    public function init()
    {


        $ret = [];

        Hook::call('home_init', $ret);

        if (ActivityStreams::is_as_request()) {
            $x = array_merge(Activity::ap_context(), Activity::encode_site());

            $headers = [];
            $headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
            $x['signature'] = LDSignatures::sign($x, ['channel_address' => z_root(), 'channel_prvkey' => get_config('system', 'prvkey')]);
            $ret = json_encode($x, JSON_UNESCAPED_SLASHES);
            logger('data: ' . jindent($ret), LOGGER_DATA);
            $headers['Date'] = datetime_convert('UTC', 'UTC', 'now', 'D, d M Y H:i:s \\G\\M\\T');
            $headers['Digest'] = HTTPSig::generate_digest_header($ret);
            $headers['(request-target)'] = strtolower($_SERVER['REQUEST_METHOD']) . ' ' . $_SERVER['REQUEST_URI'];

            $h = HTTPSig::create_sig($headers, get_config('system', 'prvkey'), z_root());
            HTTPSig::set_headers($h);

            echo $ret;
            killme();
        }

        if (Libzot::is_zot_request()) {
            $channel = Channel::get_system();
            $sigdata = HTTPSig::verify(file_get_contents('php://input'), EMPTY_STR, 'zot6');

            if ($sigdata && $sigdata['signer'] && $sigdata['header_valid']) {
                $data = json_encode(Libzot::zotinfo(['guid_hash' => $channel['channel_hash'], 'target_url' => $sigdata['signer']]));
                $s = q(
                    "select site_crypto, hubloc_sitekey from site left join hubloc on hubloc_url = site_url where hubloc_id_url = '%s' and hubloc_network in ('zot6','nomad') and hubloc_deleted = 0 limit 1",
                    dbesc($sigdata['signer'])
                );

                if ($s && $s[0]['hubloc_sitekey'] && $s[0]['site_crypto']) {
                    $data = json_encode(Crypto::encapsulate($data, $s[0]['hubloc_sitekey'], Libzot::best_algorithm($s[0]['site_crypto'])));
                }
            } else {
                $data = json_encode(Libzot::zotinfo(['guid_hash' => $channel['channel_hash']]));
            }

            $headers = [
                'Content-Type' => 'application/x-nomad+json',
                'Digest' => HTTPSig::generate_digest_header($data),
                '(request-target)' => strtolower($_SERVER['REQUEST_METHOD']) . ' ' . $_SERVER['REQUEST_URI']
            ];
            $h = HTTPSig::create_sig($headers, get_config('system', 'prvkey'), z_root());
            HTTPSig::set_headers($h);
            echo $data;
            killme();
        }


        $splash = ((argc() > 1 && argv(1) === 'splash') ? true : false);

        $channel = App::get_channel();
        if (local_channel() && $channel && $channel['xchan_url'] && !$splash) {
            $dest = $channel['channel_startpage'];
            if (!$dest) {
                $dest = get_pconfig(local_channel(), 'system', 'startpage');
            }
            if (!$dest) {
                $dest = get_config('system', 'startpage');
            }
            if (!$dest) {
                $dest = z_root() . '/stream';
            }
            goaway($dest);
        }

        if (remote_channel() && (!$splash) && $_SESSION['atoken']) {
            $r = q(
                "select * from atoken where atoken_id = %d",
                intval($_SESSION['atoken'])
            );
            if ($r) {
                $x = Channel::from_id($r[0]['atoken_uid']);
                if ($x) {
                    goaway(z_root() . '/channel/' . $x['channel_address']);
                }
            }
        }


        if (get_account_id() && !$splash) {
            goaway(z_root() . '/new_channel');
        }
    }


    public function get()
    {

        $o = EMPTY_STR;

        if (x($_SESSION, 'theme')) {
            unset($_SESSION['theme']);
        }
        if (x($_SESSION, 'mobile_theme')) {
            unset($_SESSION['mobile_theme']);
        }

        $splash = ((argc() > 1 && argv(1) === 'splash') ? true : false);

        Hook::call('home_content', $o);
        if ($o) {
            return $o;
        }

        $frontpage = get_config('system', 'frontpage');
        if ($frontpage) {
            if (str_contains($frontpage, 'include:')) {
                $file = trim(str_replace('include:', '', $frontpage));
                if (file_exists($file)) {
                    App::$page['template'] = 'full';
                    App::$page['title'] = t('$Projectname');
                    $o .= file_get_contents($file);
                    return $o;
                }
            }
            if (!str_starts_with($frontpage, 'http')) {
                $frontpage = z_root() . '/' . $frontpage;
            }
            if (intval(get_config('system', 'mirror_frontpage'))) {
                $o = '<html><head><title>' . t('$Projectname') . '</title></head><body style="margin: 0; padding: 0; border: none;" ><iframe src="' . $frontpage . '" width="100%" height="100%" style="margin: 0; padding: 0; border: none;" ></iframe></body></html>';
                echo $o;
                killme();
            }
            goaway($frontpage);
        }


        $sitename = get_config('system', 'sitename');
        if ($sitename) {
            $o .= '<h1 class="home-welcome">' . sprintf(t('Welcome to %s'), $sitename) . '</h1>';
        }

        $loginbox = get_config('system', 'login_on_homepage');
        if (intval($loginbox) || $loginbox === false) {
            $o .= login(true);
        }

        return $o;
    }
}
