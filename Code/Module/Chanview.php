<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\Libzot;
use Code\Lib\Zotfinger;
use Code\Lib\Webfinger;
use Code\Lib\ActivityStreams;
use Code\Lib\Activity;
use Code\Render\Theme;
use Code\Lib\ASCollection;
use Code\Lib\Queue;
use Code\Daemon\Run;

class Chanview extends Controller
{

    public function get()
    {

        $load_outbox = false;

        $observer = App::get_observer();
        $xchan = null;

        $r = null;

        if ($_REQUEST['hash']) {
            $r = q(
                "select * from xchan where xchan_hash = '%s' limit 1",
                dbesc($_REQUEST['hash'])
            );
        }
        if ($_REQUEST['address']) {
            $r = q(
                "select * from xchan where xchan_addr = '%s' limit 1",
                dbesc(punify($_REQUEST['address']))
            );
        } elseif (local_channel() && intval($_REQUEST['cid'])) {
            $r = q(
                "SELECT abook.*, xchan.*
                FROM abook left join xchan on abook_xchan = xchan_hash
                WHERE abook_channel = %d and abook_id = %d LIMIT 1",
                intval(local_channel()),
                intval($_REQUEST['cid'])
            );
        } elseif ($_REQUEST['url']) {
            // if somebody re-installed they will have more than one xchan, use the most recent name date as this is
            // the most useful consistently ascending table item we have.

            $r = q(
                "select * from hubloc left join xchan on hubloc_hash = xchan_hash where (hubloc_url = '%s' or hubloc_id_url = '%s') and hubloc_deleted = 0 order by xchan_name_date desc limit 1",
                dbesc($_REQUEST['url']),
                dbesc($_REQUEST['url'])
            );
        }
        if ($r) {
            App::$poi = array_shift($r);
        }

        // Here, let's see if we have an xchan. If we don't, how we proceed is determined by what
        // info we do have. If it's a URL, we can offer to visit it directly. If it's a webbie or
        // address, we can and should try to import it. If it's just a hash, we can't continue, but we
        // probably wouldn't have a hash if we don't already have an xchan for this channel.

        if (!App::$poi) {
            logger('mod_chanview: fallback');
            // This is hackish - construct a zot address from the url
            if ($_REQUEST['url']) {
                if (preg_match('/https?\:\/\/(.*?)(\/channel\/|\/profile\/)(.*?)$/ism', $_REQUEST['url'], $matches)) {
                    $_REQUEST['address'] = $matches[3] . '@' . $matches[1];
                }
                logger('mod_chanview: constructed address ' . print_r($matches, true));
            }

            $r = null;

            if ($_REQUEST['address']) {
                $href = Webfinger::nomad_url(punify($_REQUEST['address']));
                if ($href) {
                    $zf = Zotfinger::exec($href, $channel);
                }
                if (is_array($zf) && array_path_exists('signature/signer', $zf) && $zf['signature']['signer'] === $href && intval($zf['signature']['header_valid'])) {
                    $xc = Libzot::import_xchan($zf['data']);
                    $r = q(
                        "select * from xchan where xchan_addr = '%s' limit 1",
                        dbesc($_REQUEST['address'])
                    );
                    if ($r) {
                        App::$poi = $r[0];
                    }
                }
                if (!$r) {
                    if (discover_by_webbie($_REQUEST['address'])) {
                        $r = q(
                            "select * from xchan where xchan_addr = '%s' limit 1",
                            dbesc($_REQUEST['address'])
                        );
                        if ($r) {
                            App::$poi = $r[0];
                        }
                    }
                }
            }
        }

        if (!App::$poi) {
            notice(t('Channel not found.') . EOL);
            return;
        }

        $is_zot = false;
        $connected = false;

        $url = App::$poi['xchan_url'];
        if (in_array(App::$poi['xchan_network'],['nomad','zot6'])) {
            $is_zot = true;
        }
        if (local_channel()) {
            $c = q(
                "select abook_id, abook_pending from abook where abook_channel = %d and abook_xchan = '%s' limit 1",
                intval(local_channel()),
                dbesc(App::$poi['xchan_hash'])
            );

            // if somebody followed us and we want to find out more, start
            // by viewing their publicly accessible information.
            // Otherwise the primary use of this page is to provide a connect
            // button for anybody in the fediverse - which doesn't have to ask
            // you who you are.

            if ($c && intval($c[0]['abook_pending']) === 0) {
                $connected = true;
            }
            $channel = App::get_channel();
        }

        if ($is_zot && $observer) {
            $url = zid($url);
        }

        // If we are already connected, just go to the profile.

        if ($connected) {
            goaway($url);
        } else {
            $about = false;
            $xprof = q(
                "select * from xprof where xprof_hash = '%s'",
                dbesc(App::$poi['xchan_hash'])
            );
            if ($xprof) {
                $about = zidify_links(bbcode($xprof[0]['xprof_about']));
            }

            $followers = t('Not available');
            $following = t('Not available');

            $f = get_xconfig(App::$poi['xchan_hash'], 'activitypub', 'collections');
            if ($f && isset($f['followers'])) {
                $m = Activity::fetch($f['followers']);
                if (is_array($m) && isset($m['totalItems'])) {
                    $followers = intval($m['totalItems']);
                }
            }
            if ($f && isset($f['following'])) {
                $m = Activity::fetch($f['following']);
                if (is_array($m) && isset($m['totalItems'])) {
                    $following = intval($m['totalItems']);
                }
            }

            $o = replace_macros(Theme::get_template('chanview.tpl'), [
                '$url' => $url,
                '$photo' => get_xconfig(App::$poi['xchan_hash'], 'system', 'cover_photo'),
                '$alt' => t('Cover photo for this channel'),
                '$about' => $about,
                '$followers_txt' => t('Followers'),
                '$following_txt' => t('Following'),
                '$followers' => $followers,
                '$following' => $following,
                '$visit' => t('Visit'),
                '$outbox' => $load_outbox,
                '$view' => t('View Recent'),
                '$recentlink' => z_root() . '/stream/?xchan=' . urlencode(App::$poi['xchan_hash']),
                '$full' => t('toggle full screen mode')
            ]);

            return $o;
        }
    }
}
