<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\MarkdownSoap;
use Zotlabs\Lib\PermissionDescription;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libprofile;
use Zotlabs\Lib\Channel;
use Zotlabs\Lib\Libacl;


require_once('include/conversation.php');


class Editwebpage extends Controller
{

    public function init()
    {

        if (argc() > 1 && argv(1) === 'sys' && is_site_admin()) {
            $sys = Channel::get_system();
            if ($sys && intval($sys['channel_id'])) {
                App::$is_sys = true;
            }
        }

        if (argc() > 1) {
            $which = argv(1);
        } else {
            return;
        }

        Libprofile::load($which);
    }

    public function get()
    {

        if (!App::$profile) {
            notice(t('Requested profile is not available.') . EOL);
            App::$error = 404;
            return;
        }

        $which = argv(1);

        $uid = local_channel();
        $owner = 0;
        $channel = null;
        $observer = App::get_observer();

        $channel = App::get_channel();

        if (App::$is_sys && is_site_admin()) {
            $sys = Channel::get_system();
            if ($sys && intval($sys['channel_id'])) {
                $uid = $owner = intval($sys['channel_id']);
                $channel = $sys;
                $observer = $sys;
            }
        }

        if (!$owner) {
            // Figure out who the page owner is.
            $r = q(
                "select channel_id from channel where channel_address = '%s'",
                dbesc($which)
            );
            if ($r) {
                $owner = intval($r[0]['channel_id']);
            }
        }

        $ob_hash = (($observer) ? $observer['xchan_hash'] : '');

        if (!perm_is_allowed($owner, $ob_hash, 'write_pages')) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        $is_owner = (($uid && $uid == $owner) ? true : false);

        $o = '';

        // Figure out which post we're editing
        $post_id = ((argc() > 2) ? intval(argv(2)) : 0);

        if (!$post_id) {
            notice(t('Item not found') . EOL);
            return;
        }

        $ob_hash = (($observer) ? $observer['xchan_hash'] : '');

        $perms = get_all_perms($owner, $ob_hash);

        if (!$perms['write_pages']) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        // We've already figured out which item we want and whose copy we need,
        // so we don't need anything fancy here

        $sql_extra = item_permissions_sql($owner);

        $itm = q(
            "SELECT * FROM item WHERE id = %d and uid = %s $sql_extra LIMIT 1",
            intval($post_id),
            intval($owner)
        );

        // don't allow web editing of potentially binary content (item_obscured = 1)
        // @FIXME how do we do it instead?

        if ((!$itm) || intval($itm[0]['item_obscured'])) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        $item_id = q(
            "select * from iconfig where cat = 'system' and k = 'WEBPAGE' and iid = %d limit 1",
            intval($itm[0]['id'])
        );
        if ($item_id) {
            $page_title = urldecode($item_id[0]['v']);
        }

        $mimetype = $itm[0]['mimetype'];

        if ($mimetype === 'application/x-php') {
            if ((!$uid) || ($uid != $itm[0]['uid'])) {
                notice(t('Permission denied.') . EOL);
                return;
            }
        }

        $layout = $itm[0]['layout_mid'];

        $content = $itm[0]['body'];
        if ($itm[0]['mimetype'] === 'text/markdown') {
            $content = MarkdownSoap::unescape($itm[0]['body']);
        }

        $rp = 'webpages/' . $which;

        $x = array(
            'nickname' => $channel['channel_address'],
            'bbco_autocomplete' => ((in_array($mimetype, [ 'text/bbcode', 'text/x-multicode'])) ? 'bbcode' : ''),
            'return_path' => $rp,
            'webpage' => ITEM_TYPE_WEBPAGE,
            'ptlabel' => t('Page link'),
            'pagetitle' => $page_title,
            'writefiles' => ((in_array($mimetype, [ 'text/bbcode', 'text/x-multicode'])) ? perm_is_allowed($owner, get_observer_hash(), 'write_storage') : false),
            'button' => t('Edit'),
            'weblink' => ((in_array($mimetype, [ 'text/bbcode', 'text/x-multicode'])) ? t('Insert web link') : false),
            'hide_location' => true,
            'hide_voting' => true,
            'ptyp' => $itm[0]['type'],
            'body' => undo_post_tagging($content),
            'post_id' => $post_id,
            'visitor' => ($is_owner) ? true : false,
            'acl' => Libacl::populate($itm[0], false, PermissionDescription::fromGlobalPermission('view_pages')),
            'permissions' => $itm[0],
            'showacl' => ($is_owner) ? true : false,
            'mimetype' => $mimetype,
            'mimeselect' => true,
            'layout' => $layout,
            'layoutselect' => true,
            'title' => htmlspecialchars($itm[0]['title'], ENT_COMPAT, 'UTF-8'),
            'lockstate' => (((strlen($itm[0]['allow_cid'])) || (strlen($itm[0]['allow_gid'])) || (strlen($itm[0]['deny_cid'])) || (strlen($itm[0]['deny_gid']))) ? 'lock' : 'unlock'),
            'profile_uid' => (intval($owner)),
            'bbcode' => ((in_array($mimetype, ['text/bbcode', 'text/x-multicode'])) ? true : false)
        );

        $editor = status_editor($x);

        $o .= replace_macros(get_markup_template('edpost_head.tpl'), array(
            '$title' => t('Edit Webpage'),
            '$delete' => ((($itm[0]['author_xchan'] === $ob_hash) || ($itm[0]['owner_xchan'] === $ob_hash)) ? t('Delete') : false),
            '$editor' => $editor,
            '$cancel' => t('Cancel'),
            '$id' => $itm[0]['id']
        ));

        return $o;
    }
}
