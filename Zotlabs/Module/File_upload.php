<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Libsync;
use Zotlabs\Web\Controller;

require_once('include/attach.php');
require_once('include/channel.php');
require_once('include/photos.php');


class File_upload extends Controller
{

    public function post()
    {

        logger('file upload: ' . print_r($_REQUEST, true));
        logger('file upload: ' . print_r($_FILES, true));

        $channel = (($_REQUEST['channick']) ? channelx_by_nick($_REQUEST['channick']) : null);

        if (!$channel) {
            logger('channel not found');
            killme();
        }

        $_REQUEST['source'] = 'file_upload';

        if ($channel['channel_id'] != local_channel()) {
            $_REQUEST['contact_allow'] = expand_acl($channel['channel_allow_cid']);
            $_REQUEST['group_allow'] = expand_acl($channel['channel_allow_gid']);
            $_REQUEST['contact_deny'] = expand_acl($channel['channel_deny_cid']);
            $_REQUEST['group_deny'] = expand_acl($channel['channel_deny_gid']);
        }

        $_REQUEST['allow_cid'] = perms2str($_REQUEST['contact_allow']);
        $_REQUEST['allow_gid'] = perms2str($_REQUEST['group_allow']);
        $_REQUEST['deny_cid'] = perms2str($_REQUEST['contact_deny']);
        $_REQUEST['deny_gid'] = perms2str($_REQUEST['group_deny']);

        if ($_REQUEST['filename']) {
            $r = attach_mkdir($channel, get_observer_hash(), $_REQUEST);
            if ($r['success']) {
                $hash = $r['data']['hash'];

                $sync = attach_export_data($channel, $hash);
                if ($sync) {
                    Libsync::build_sync_packet($channel['channel_id'], array('file' => array($sync)));
                }
                goaway(z_root() . '/cloud/' . $channel['channel_address'] . '/' . $r['data']['display_path']);
            }
        } else {
            $matches = [];
            $partial = false;


            if (array_key_exists('HTTP_CONTENT_RANGE', $_SERVER)) {
                $pm = preg_match('/bytes (\d*)\-(\d*)\/(\d*)/', $_SERVER['HTTP_CONTENT_RANGE'], $matches);
                if ($pm) {
                    logger('Content-Range: ' . print_r($matches, true));
                    $partial = true;
                }
            }

            if ($partial) {
                $x = save_chunk($channel, $matches[1], $matches[2], $matches[3]);

                if ($x['partial']) {
                    header('Range: bytes=0-' . (($x['length']) ? $x['length'] - 1 : 0));
                    json_return_and_die($x);
                } else {
                    header('Range: bytes=0-' . (($x['size']) ? $x['size'] - 1 : 0));

                    $_FILES['userfile'] = [
                        'name' => $x['name'],
                        'type' => $x['type'],
                        'tmp_name' => $x['tmp_name'],
                        'error' => $x['error'],
                        'size' => $x['size']
                    ];
                }
            } else {
                if (!array_key_exists('userfile', $_FILES)) {
                    $_FILES['userfile'] = [
                        'name' => $_FILES['files']['name'],
                        'type' => $_FILES['files']['type'],
                        'tmp_name' => $_FILES['files']['tmp_name'],
                        'error' => $_FILES['files']['error'],
                        'size' => $_FILES['files']['size']
                    ];
                }
            }

            $r = attach_store($channel, get_observer_hash(), '', $_REQUEST);
            if ($r['success']) {
                $sync = attach_export_data($channel, $r['data']['hash']);
                if ($sync) {
                    Libsync::build_sync_packet($channel['channel_id'], array('file' => array($sync)));
                }
            }
        }
        goaway(z_root() . '/' . $_REQUEST['return_url']);
    }
}
