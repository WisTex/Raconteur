<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\PConfig;
use Code\Lib\ServiceClass;
use Code\Lib\Channel;
use Code\Lib\Navbar;
use Code\Render\Theme;

    
require_once('include/security.php');

class Manage extends Controller
{

    public function get()
    {

        if ((!get_account_id()) || ($_SESSION['delegate'])) {
            notice(t('Permission denied.') . EOL);
            return '';
        }
        $channel = App::get_channel();

        Navbar::set_selected('Manage');


        $change_channel = ((argc() > 1) ? intval(argv(1)) : 0);

        if (argc() > 2) {
            if (argv(2) === 'default') {
                $r = q(
                    "select channel_id from channel where channel_id = %d and channel_account_id = %d limit 1",
                    intval($change_channel),
                    intval(get_account_id())
                );
                if ($r) {
                    q(
                        "update account set account_default_channel = %d where account_id = %d",
                        intval($change_channel),
                        intval(get_account_id())
                    );
                }
                goaway(z_root() . '/manage');
            } elseif (argv(2) === 'menu') {
                $state = intval(PConfig::Get($change_channel, 'system', 'include_in_menu', 0));
                PConfig::Set($change_channel, 'system', 'include_in_menu', 1 - $state);
                goaway(z_root() . '/manage');
            }
        }


        if ($change_channel) {
            $r = change_channel($change_channel);

            if ((argc() > 2) && !(argv(2) === 'default')) {
                goaway(z_root() . '/' . implode('/', array_slice(App::$argv, 2))); // Go to whatever is after /manage/, but with the new channel
            } elseif ($r && $r['channel_startpage']) {
                goaway(z_root() . '/' . $r['channel_startpage']); // If nothing extra is specified, go to the default page
            }
            goaway(z_root());
        }

        $channels = null;

        $r = q(
            "select channel.*, xchan.* from channel left join xchan on channel.channel_hash = xchan.xchan_hash where channel.channel_account_id = %d and channel_removed = 0 order by channel_name ",
            intval(get_account_id())
        );

        $account = App::get_account();

        if ($r && count($r)) {
            $channels = ((is_site_admin()) ? array_merge([Channel::get_system()], $r) : $r);
            for ($x = 0; $x < count($channels); $x++) {
                $channels[$x]['link'] = 'manage/' . intval($channels[$x]['channel_id']);
                $channels[$x]['include_in_menu'] = intval(PConfig::Get($channels[$x]['channel_id'], 'system', 'include_in_menu', 0));
                $channels[$x]['default'] = (($channels[$x]['channel_id'] == $account['account_default_channel']) ? "1" : '');
                $channels[$x]['default_links'] = '1';
                $channels[$x]['collections_label'] = t('Collection');
                $channels[$x]['forum_label'] = t('Group');
                $channels[$x]['msg_system'] = t('System | Site Channel');

                $c = q(
                    "SELECT id, item_wall FROM item
					WHERE item_unseen = 1 and uid = %d " . item_normal(),
                    intval($channels[$x]['channel_id'])
                );

                if ($c) {
                    foreach ($c as $it) {
                        if (intval($it['item_wall'])) {
                            $channels[$x]['home']++;
                        } else {
                            $channels[$x]['network']++;
                        }
                    }
                }


                $intr = q(
                    "SELECT COUNT(abook.abook_id) AS total FROM abook left join xchan on abook.abook_xchan = xchan.xchan_hash where abook_channel = %d and abook_pending = 1 and abook_self = 0 and abook_ignored = 0 and xchan_deleted = 0 and xchan_orphan = 0 ",
                    intval($channels[$x]['channel_id'])
                );

                if ($intr) {
                    $channels[$x]['intros'] = intval($intr[0]['total']);
                }

                $events = q(
                    "SELECT etype, dtstart, adjust FROM event
					WHERE event.uid = %d AND dtstart < '%s' AND dtstart > '%s' and dismissed = 0
					ORDER BY dtstart ASC ",
                    intval($channels[$x]['channel_id']),
                    dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now + 7 days')),
                    dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now - 1 days'))
                );

                if ($events) {
                    $channels[$x]['all_events'] = count($events);

                    if ($channels[$x]['all_events']) {
                        $str_now = datetime_convert('UTC', date_default_timezone_get(), 'now', 'Y-m-d');
                        foreach ($events as $e) {
                            $bd = false;
                            if ($e['etype'] === 'birthday') {
                                $channels[$x]['birthdays']++;
                                $bd = true;
                            } else {
                                $channels[$x]['events']++;
                            }
                            if (datetime_convert('UTC', ((intval($e['adjust'])) ? date_default_timezone_get() : 'UTC'), $e['dtstart'], 'Y-m-d') === $str_now) {
                                $channels[$x]['all_events_today']++;
                                if ($bd) {
                                    $channels[$x]['birthdays_today']++;
                                } else {
                                    $channels[$x]['events_today']++;
                                }
                            }
                        }
                    }
                }
            }
        }

        $r = q(
            "select count(channel_id) as total from channel where channel_account_id = %d and channel_removed = 0",
            intval(get_account_id())
        );
        $limit = ServiceClass::account_fetch(get_account_id(), 'total_identities');
        if ($limit !== false) {
            $channel_usage_message = sprintf(t("You have created %1$.0f of %2$.0f allowed channels."), $r[0]['total'], $limit);
        } else {
            $channel_usage_message = '';
        }


        $create = ['new_channel', t('Create a new channel'), t('Create New')];

        $delegates = null;

        if (local_channel()) {
            $delegates = q(
                "select * from abook left join xchan on abook_xchan = xchan_hash where 
				abook_channel = %d and abook_xchan in ( select xchan from abconfig where chan = %d and cat = 'system' and k = 'their_perms' and v like '%s' )",
                intval(local_channel()),
                intval(local_channel()),
                dbesc('%delegate%')
            );
            $links = q("select * from linkid where ident = '%s' and sigtype = %s",
                dbesc($channel['channel_hash']),
                intval(IDLINK_RELME)
            );
            $linkid_str = ids_to_querystr($links,'link', true);
            if ($linkid_str) {
                $linkedIdentities = q("select * from xchan where (xchan_hash in ($linkid_str) and xchan_network = 'activitypub')
                    or (xchan_url in ($linkid_str) and xchan_network in ('zot6','nomad')) ");
            }
        }

        if ($delegates) {
            for ($x = 0; $x < count($delegates); $x++) {
                $delegates[$x]['link'] = 'magic?f=&bdest=' . bin2hex($delegates[$x]['xchan_url'])
                    . '&delegate=' . urlencode($delegates[$x]['xchan_addr']);
                $delegates[$x]['channel_name'] = $delegates[$x]['xchan_name'];
                $delegates[$x]['delegate'] = 1;
                $delegates[$x]['collections_label'] = t('Collection');
                $delegates[$x]['forum_label'] = t('Group');
            }
        } else {
            $delegates = null;
        }


        if ($linkedIdentities) {
            for ($x = 0; $x < count($linkedIdentities); $x++) {
                $linkedIdentities[$x]['link'] = zid($linkedIdentities[$x]['xchan_url']);
                $linkedIdentities[$x]['channel_name'] = $linkedIdentities[$x]['xchan_name'];
                $linkedIdentities[$x]['delegate'] = 2;
                $linkedIdentities[$x]['collections_label'] = t('Collection');
            }
        } else {
            $linkedIdentities = null;
        }

        return replace_macros(Theme::get_template('channels.tpl'), [
            '$header' => t('Channels'),
            '$msg_selected' => t('Current Channel'),
            '$msg_linked' => t('Linked Identities'),
            '$selected' => local_channel(),
            '$desc' => t('Switch to one of your channels by selecting it.'),
            '$msg_default' => t('Default Login Channel'),
            '$msg_make_default' => t('Make Default'),
            '$msg_include' => t('Add to menu'),
            '$msg_no_include' => t('Add to menu'),
            '$create' => $create,
            '$all_channels' => $channels,
            '$mail_format' => t('%d new messages'),
            '$intros_format' => t('%d new introductions'),
            '$channel_usage_message' => $channel_usage_message,
            '$remote_desc' => t('Linked Identity'),
            '$delegated_desc' => t('Delegated Channel'),
            '$delegates' => $delegates,
            '$links' => $linkedIdentities,
        ]);
    }
}
