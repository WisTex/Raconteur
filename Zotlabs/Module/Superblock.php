<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\LibBlock;
use Zotlabs\Lib\Libzot;
use Zotlabs\Render\Theme;


class Superblock extends Controller
{

    public function init()
    {

        if (!local_channel()) {
            return;
        }

        $inline = (isset($_REQUEST['manual_block']) ? true : false);

        $type = BLOCKTYPE_CHANNEL;
        $blocked = trim($_REQUEST['block']);
        if (!$blocked) {
            $blocked = trim($_REQUEST['blocksite']);
            if ($blocked) {
                $type = BLOCKTYPE_SERVER;
            }
        }

        $m = parse_url($blocked);
        if ($m['scheme'] && $m['host'] && (($type === BLOCKTYPE_SERVER) || (!$m['path']))) {
            if (strcasecmp($m['host'], App::get_hostname()) === 0) {
                notice(t('Blocking this site is not permitted.'));
                if ($inline) {
                    return;
                }
                killme();
            }
            $type = BLOCKTYPE_SERVER;
            $blocked = $m['host'];
        }

        $handled = false;
        $ignored = [];

        if ($blocked) {
            $handled = true;
            if ($type === BLOCKTYPE_CHANNEL) {
                $r = q(
                    "select * from xchan where ( xchan_hash = '%s' or xchan_addr = '%s' or xchan_url = '%s' )",
                    dbesc($blocked),
                    dbesc($blocked),
                    dbesc($blocked)
                );

                if (!$r) {
                    // not in cache - try discovery
                    $wf = discover_by_webbie($blocked, '', false);

                    if (!$wf) {
                        notice(t('Channel not found.') . EOL);
                        if ($inline) {
                            return;
                        }
                        killme();
                    }

                    if ($wf) {
                        // something was discovered - find the record which was just created.

                        $r = q(
                            "select * from xchan where ( xchan_hash = '%s' or xchan_url = '%s' or xchan_addr = '%s' )",
                            dbesc(($wf) ? $wf : $blocked),
                            dbesc($blocked),
                            dbesc($blocked)
                        );
                    }
                }

                if ($r) {
                    $r = Libzot::zot_record_preferred($r, 'xchan_network');
                    $blocked = $r['xchan_hash'];
                }
            }

            $bl = [
                'block_channel_id' => local_channel(),
                'block_entity' => $blocked,
                'block_type' => $type,
                'block_comment' => t('Added by Superblock')
            ];

            LibBlock::store($bl);

            $sync = [];

            $sync['block'] = [LibBlock::fetch_by_entity(local_channel(), $blocked)];

            if ($type === BLOCKTYPE_CHANNEL) {
                $z = q(
                    "insert into xign ( uid, xchan ) values ( %d , '%s' ) ",
                    intval(local_channel()),
                    dbesc($blocked)
                );
                $ab = q(
                    "select * from abook where abook_channel = %d and abook_xchan = '%s'",
                    intval(local_channel()),
                    dbesc($blocked)
                );
                if (($ab) && (!intval($ab['abook_blocked']))) {
                    q(
                        "update abook set abook_blocked = 1 where abook_channel = %d and abook_xchan = '%s'",
                        intval(local_channel()),
                        dbesc($blocked)
                    );

                    $r = q(
                        "SELECT abook.*, xchan.* FROM abook left join xchan on abook_xchan = xchan_hash WHERE abook_channel = %d and abook_xchan = '%s' LIMIT 1",
                        intval(local_channel()),
                        dbesc($blocked)
                    );
                    if ($r) {
                        $r = array_shift($r);
                        $abconfig = load_abconfig(local_channel(), $blocked);
                        if ($abconfig) {
                            $r['abconfig'] = $abconfig;
                        }
                        unset($r['abook_id']);
                        unset($r['abook_account']);
                        unset($r['abook_channel']);
                        $sync['abook'] = [$r];
                    }
                }
                $sync['xign'] = [['uid' => local_channel(), 'xchan' => $_GET['block']]];
            }
            Libsync::build_sync_packet(0, $sync);
        }

        $type = BLOCKTYPE_CHANNEL;
        $unblocked = trim($_REQUEST['unblock']);
        if (!$unblocked) {
            $unblocked = trim($_REQUEST['unblocksite']);
            if ($unblocked) {
                $type = BLOCKTYPE_SERVER;
            }
        }
        if ($unblocked) {
            $handled = true;
            if (check_form_security_token('superblock', 'sectok')) {
                $r = LibBlock::fetch_by_entity(local_channel(), $unblocked);
                if ($r) {
                    LibBlock::remove(local_channel(), $unblocked);

                    $sync = [];
                    $sync['block'] = [[
                        'block_channel_id' => local_channel(),
                        'block_entity' => $unblocked,
                        'block_type' => $type,
                        'deleted' => true,
                    ]];
                    if ($type === BLOCKTYPE_CHANNEL) {
                        $ab = q(
                            "select * from abook left join xchan on abook_xchan = xchan_hash where abook_channel = %d and abook_xchan = '%s'",
                            intval(local_channel()),
                            dbesc($unblocked)
                        );
                        if (($ab) && (intval($ab['abook_blocked']))) {
                            q(
                                "update abook set abook_blocked = 1 where abook_channel = %d and abook_xchan = '%s'",
                                intval(local_channel()),
                                dbesc($unblocked)
                            );
                            $ab['abook_blocked'] = 0;
                            $abconfig = load_abconfig(local_channel(), $unblocked);
                            if ($abconfig) {
                                $ab['abconfig'] = $abconfig;
                            }
                            unset($ab['abook_id']);
                            unset($ab['abook_account']);
                            unset($ab['abook_channel']);
                            $sync['abook'] = [$ab];
                        }

                        $z = q(
                            "delete from xign where uid = %d  and xchan = '%s' ",
                            intval(local_channel()),
                            dbesc($unblocked)
                        );
                    }
                    Libsync::build_sync_packet(0, $sync);
                }
            }
        }

        if ($handled) {
            info(t('superblock settings updated') . EOL);

            if ($unblocked || $inline) {
                return;
            }

            killme();
        }
    }

    public function get()
    {

        $l = LibBlock::fetch(local_channel(), BLOCKTYPE_CHANNEL);

        $list = ids_to_array($l, 'block_entity');

        stringify_array_elms($list, true);
        $query_str = implode(',', $list);
        if ($query_str) {
            $r = q("select * from xchan where xchan_hash in ( " . $query_str . " ) ");
        } else {
            $r = [];
        }
        if ($r) {
            for ($x = 0; $x < count($r); $x++) {
                $r[$x]['encoded_hash'] = urlencode($r[$x]['xchan_hash']);
            }
        }

        $sc .= replace_macros(Theme::get_template('superblock_list.tpl'), [
            '$blocked' => t('Blocked channels'),
            '$entries' => $r,
            '$nothing' => (($r) ? '' : t('No channels currently blocked')),
            '$token' => get_form_security_token('superblock'),
            '$remove' => t('Remove')
        ]);


        $l = LibBlock::fetch(local_channel(), BLOCKTYPE_SERVER);
        $list = ids_to_array($l, 'block_entity');
        if ($list) {
            for ($x = 0; $x < count($list); $x++) {
                $list[$x] = [$list[$x], urlencode($list[$x])];
            }
        }

        $sc .= replace_macros(Theme::get_template('superblock_serverlist.tpl'), [
            '$blocked' => t('Blocked servers'),
            '$entries' => $list,
            '$nothing' => (($list) ? '' : t('No servers currently blocked')),
            '$token' => get_form_security_token('superblock'),
            '$remove' => t('Remove')
        ]);

        $s .= replace_macros(Theme::get_template('generic_app_settings.tpl'), [
            '$addon' => array('superblock', t('Manage Blocks'), '', t('Submit')),
            '$content' => $sc
        ]);

        return $s;
    }
}
