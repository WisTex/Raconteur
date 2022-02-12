<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Daemon\Run;
use Zotlabs\Render\Theme;


class Locs extends Controller
{

    public function post()
    {

        if (!local_channel()) {
            return;
        }

        $channel = App::get_channel();

        if ($_REQUEST['primary']) {
            $hubloc_id = intval($_REQUEST['primary']);
            if ($hubloc_id) {
                $r = q(
                    "select hubloc_id from hubloc where hubloc_id = %d and hubloc_hash = '%s' limit 1",
                    intval($hubloc_id),
                    dbesc($channel['channel_hash'])
                );

                if (!$r) {
                    notice(t('Location not found.') . EOL);
                    return;
                }

                $r = q(
                    "update hubloc set hubloc_primary = 0 where hubloc_primary = 1 and hubloc_hash = '%s' ",
                    dbesc($channel['channel_hash'])
                );
                $r = q(
                    "update hubloc set hubloc_primary = 1 where hubloc_id = %d and hubloc_hash = '%s'",
                    intval($hubloc_id),
                    dbesc($channel['channel_hash'])
                );

                $x = q(
                    "select * from hubloc where hubloc_id = %d and hubloc_hash = '%s' ",
                    intval($hubloc_id),
                    dbesc($channel['channel_hash'])
                );
                if ($x) {
                    hubloc_change_primary($x[0]);
                }

                Run::Summon(['Notifier', 'refresh_all', $channel['channel_id']]);
                return;
            }
        }


        if ($_REQUEST['drop']) {
            $hubloc_id = intval($_REQUEST['drop']);

            if ($hubloc_id) {
                $r = q(
                    "select * from hubloc where hubloc_id = %d and hubloc_url != '%s' and hubloc_hash = '%s' limit 1",
                    intval($hubloc_id),
                    dbesc(z_root()),
                    dbesc($channel['channel_hash'])
                );

                if (!$r) {
                    notice(t('Location not found.') . EOL);
                    return;
                }
                if (intval($r[0]['hubloc_primary'])) {
                    $x = q(
                        "select hubloc_id from hubloc where hubloc_primary = 1 and hubloc_hash = '%s'",
                        dbesc($channel['channel_hash'])
                    );
                    if (!$x) {
                        notice(t('Location lookup failed.'));
                        return;
                    }
                    if (count($x) == 1) {
                        notice(t('Please select another location to become primary before removing the primary location.') . EOL);
                        return;
                    }
                }

                $r = q(
                    "update hubloc set hubloc_deleted = 1 where hubloc_id = %d and hubloc_hash = '%s'",
                    intval($hubloc_id),
                    dbesc($channel['channel_hash'])
                );
                Run::Summon(['Notifier', 'refresh_all', $channel['channel_id']]);
                return;
            }
        }
    }


    public function get()
    {


        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        $channel = App::get_channel();

        if ($_REQUEST['sync']) {
            Run::Summon(['Notifier', 'refresh_all', $channel['channel_id']]);
            info(t('Pushing location info') . EOL);
            goaway(z_root() . '/locs');
        }


        $r = q(
            "select * from hubloc where hubloc_hash = '%s'",
            dbesc($channel['channel_hash'])
        );

        if (!$r) {
            notice(t('No locations found.') . EOL);
            return;
        }

        $o = replace_macros(Theme::get_template('locmanage.tpl'), [
            '$header' => t('Manage Channel Locations'),
            '$loc' => t('Location'),
            '$addr' => t('Address'),
            '$mkprm' => t('Primary'),
            '$drop' => t('Drop'),
            '$submit' => t('Submit'),
            '$sync' => t('Publish these settings'),
            '$sync_text' => t('Please wait several minutes between consecutive operations.'),
            '$drop_text' => t('When possible, drop a location by logging into that website/hub and removing your channel.'),
            '$last_resort' => t('Use this form to drop the location if the hub is no longer operating.'),
            '$hubs' => $r,
            '$base_url' => z_root()
        ]);

        return $o;
    }
}
