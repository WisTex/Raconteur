<?php

namespace Zotlabs\Module;

/**
 * @file Zotlabs/Module/Filestorage.php
 * @brief performs edit template and operations on cloud files when in "list" view mode
 */

use App;
use Zotlabs\Lib\PermissionDescription;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libsync;
use Zotlabs\Access\AccessControl;

class Filestorage extends Controller
{

    public function post()
    {

        $channel_id = ((x($_POST, 'uid')) ? intval($_POST['uid']) : 0);

        if ((!$channel_id) || (!local_channel()) || ($channel_id != local_channel())) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        $recurse = ((x($_POST, 'recurse')) ? intval($_POST['recurse']) : 0);
        $resource = ((x($_POST, 'filehash')) ? notags($_POST['filehash']) : '');
        $notify = ((x($_POST, 'notify_edit')) ? intval($_POST['notify_edit']) : 0);

        $newname = ((x($_POST, 'newname')) ? notags(trim($_POST['newname'])) : '');
        $newdir = ((x($_POST, 'newdir')) ? notags($_POST['newdir']) : false);

        if (!$resource) {
            notice(t('Item not found.') . EOL);
            return;
        }

        $channel = App::get_channel();

        if ($newdir || $newname) {
            $changed = false;

            $m = q(
                "select folder from attach where hash = '%s' and uid = %d limit 1",
                dbesc($resource),
                intval($channel_id)
            );

            if ($m) {
                // we should always have $newdir, but only call attach_move()
                // if it is being changed *or* a new filename is set, and
                // account for the fact $newdir can legally be an empty sring
                // to indicate the cloud root directory

                if ($newdir !== false && $newdir !== $m[0]['folder']) {
                    $changed = true;
                }
                if ($newname) {
                    $changed = true;
                }
                if ($changed) {
                    attach_move($channel_id, $resource, $newdir, $newname);
                }
            }
        }

        $acl = new AccessControl($channel);
        $acl->set_from_array($_POST);
        $x = $acl->get();

        $url = get_cloud_url($channel_id, $channel['channel_address'], $resource);

        // get the object before permissions change so we can catch eventual former allowed members
        $object = get_file_activity_object($channel_id, $resource, $url);

        attach_change_permissions($channel_id, $resource, $x['allow_cid'], $x['allow_gid'], $x['deny_cid'], $x['deny_gid'], $recurse, true);

        $sync = attach_export_data($channel, $resource, false);
        if ($sync) {
            Libsync::build_sync_packet($channel_id, array('file' => array($sync)));
        }

//      file_activity($channel_id, $object, $x['allow_cid'], $x['allow_gid'], $x['deny_cid'], $x['deny_gid'], 'post', $notify);

        goaway(dirname($url));
    }

    public function get()
    {

        if (argc() > 1) {
            $channel = channelx_by_nick(argv(1));
        }
        if (!$channel) {
            notice(t('Channel unavailable.') . EOL);
            App::$error = 404;
            return;
        }

        $owner = intval($channel['channel_id']);
        $observer = App::get_observer();

        $ob_hash = (($observer) ? $observer['xchan_hash'] : '');

        $perms = get_all_perms($owner, $ob_hash);

        if (!($perms['view_storage'] || is_site_admin())) {
            notice(t('Permission denied.') . EOL);
            return;
        }


        if (argc() > 3 && argv(3) === 'delete') {
            if (argc() > 4 && argv(4) === 'json') {
                $json_return = true;
            }

            $admin_delete = false;

            if (!$perms['write_storage']) {
                if (is_site_admin()) {
                    $admin_delete = true;
                } else {
                    notice(t('Permission denied.') . EOL);
                    if ($json_return) {
                        json_return_and_die(['success' => false]);
                    }
                    return;
                }
            }

            $file = intval(argv(2));
            $r = q(
                "SELECT hash, creator FROM attach WHERE id = %d AND uid = %d LIMIT 1",
                dbesc($file),
                intval($owner)
            );
            if (!$r) {
                notice(t('File not found.') . EOL);

                if ($json_return) {
                    json_return_and_die(['success' => false]);
                }

                goaway(z_root() . '/cloud/' . $which);
            }

            $f = array_shift($r);

            if (intval(local_channel()) !== $owner) {
                if ($f['creator'] && $f['creator'] !== $ob_hash) {
                    notice(t('Permission denied.') . EOL);

                    if ($json_return) {
                        json_return_and_die(['success' => false]);
                    }
                    goaway(z_root() . '/cloud/' . $which);
                }
            }

            $url = get_cloud_url($channel['channel_id'], $channel['channel_address'], $f['hash']);

            attach_delete($owner, $f['hash']);

            if (!$admin_delete) {
                $sync = attach_export_data($channel, $f['hash'], true);
                if ($sync) {
                    Libsync::build_sync_packet($channel['channel_id'], ['file' => [$sync]]);
                }
            }

            if ($json_return) {
                json_return_and_die(['success' => true]);
            }

            goaway(dirname($url));
        }


        // Since we have ACL'd files in the wild, but don't have ACL here yet, we
        // need to return for anyone other than the owner, despite the perms check for now.

        $is_owner = (((local_channel()) && ($owner == local_channel())) ? true : false);
        if (!($is_owner || is_site_admin())) {
            notice(t('Permission denied.') . EOL);
            return;
        }


        if (argc() > 3 && argv(3) === 'edit') {
            require_once('include/acl_selectors.php');
            if (!$perms['write_storage']) {
                notice(t('Permission denied.') . EOL);
                return;
            }

            $file = intval(argv(2));

            $r = q(
                "select id, uid, folder, filename, revision, flags, is_dir, os_storage, hash, allow_cid, allow_gid, deny_cid, deny_gid from attach where id = %d and uid = %d limit 1",
                intval($file),
                intval($owner)
            );

            $f = array_shift($r);

            $channel = App::get_channel();

            $cloudpath = get_cloudpath($f);

            $aclselect_e = populate_acl($f, false, PermissionDescription::fromGlobalPermission('view_storage'));
            $is_a_dir = (intval($f['is_dir']) ? true : false);

            $lockstate = (($f['allow_cid'] || $f['allow_gid'] || $f['deny_cid'] || $f['deny_gid']) ? 'lock' : 'unlock');

            // Encode path that is used for link so it's a valid URL
            // Keep slashes as slashes, otherwise mod_rewrite doesn't work correctly
            $encoded_path = str_replace('%2F', '/', rawurlencode($cloudpath));
            $folder_list = attach_folder_select_list($channel['channel_id']);

            $o = replace_macros(get_markup_template('attach_edit.tpl'), [
                '$header' => t('Edit file permissions'),
                '$file' => $f,
                '$cloudpath' => z_root() . '/' . $encoded_path,
                '$uid' => $channel['channel_id'],
                '$channelnick' => $channel['channel_address'],
                '$permissions' => t('Permissions'),
                '$aclselect' => $aclselect_e,
                '$allow_cid' => acl2json($f['allow_cid']),
                '$allow_gid' => acl2json($f['allow_gid']),
                '$deny_cid' => acl2json($f['deny_cid']),
                '$deny_gid' => acl2json($f['deny_gid']),
                '$lockstate' => $lockstate,
                '$newname' => ['newname', t('Change filename to'), '', t('Leave blank to keep the existing filename')],
                '$newdir' => ['newdir', t('Move to directory'), $f['folder'], '', $folder_list],
                '$permset' => t('Set/edit permissions'),
                '$recurse' => ['recurse', t('Include all files and sub folders'), 0, '', [t('No'), t('Yes')]],
                '$backlink' => t('Return to file list'),
                '$isadir' => $is_a_dir,
                '$cpdesc' => t('Copy/paste this code to attach file to a post'),
                '$cpldesc' => t('Copy/paste this URL to link file from a web page'),
                '$submit' => t('Submit'),
                '$attach_btn_title' => t('Share this file'),
                '$link_btn_title' => t('Show URL to this file'),
                '$notify' => ['notify_edit', t('Show in your contacts shared folder'), 0, '', [t('No'), t('Yes')]],
            ]);

            echo $o;
            killme();
        }
        goaway(z_root() . '/cloud/' . $which);
    }
}
