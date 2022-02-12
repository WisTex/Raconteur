<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Libprofile;
use Zotlabs\Lib\MarkdownSoap;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Channel;
use Zotlabs\Render\Theme;



require_once('include/conversation.php');

class Editblock extends Controller
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

        if (!($post_id && $owner)) {
            notice(t('Item not found') . EOL);
            return;
        }

        $itm = q(
            "SELECT * FROM item WHERE id = %d and uid = %s LIMIT 1",
            intval($post_id),
            intval($owner)
        );
        if ($itm) {
            $item_id = q(
                "select * from iconfig where cat = 'system' and k = 'BUILDBLOCK' and iid = %d limit 1",
                intval($itm[0]['id'])
            );
            if ($item_id) {
                $block_title = $item_id[0]['v'];
            }
        } else {
            notice(t('Item not found') . EOL);
            return;
        }

        $mimetype = $itm[0]['mimetype'];

        $content = $itm[0]['body'];
        if ($itm[0]['mimetype'] === 'text/markdown') {
            $content = MarkdownSoap::unescape($itm[0]['body']);
        }


        $rp = 'blocks/' . $channel['channel_address'];

        $x = array(
			'nickname' => $channel['channel_address'],
			'bbco_autocomplete'=> ((in_array($mimetype, [ 'text/bbcode', 'text/x-multicode' ])) ? 'bbcode' : 'comanche-block'),
			'return_path' => $rp,
			'webpage' => ITEM_TYPE_BLOCK,
			'ptlabel' => t('Block Name'),
			'button' => t('Edit'),
			'writefiles' => ((in_array($mimetype, [ 'text/bbcode', 'text/x-multicode' ])) ? perm_is_allowed($owner, get_observer_hash(), 'write_storage') : false),
			'weblink' => ((in_array($mimetype, [ 'text/bbcode' , 'text/x-multicode' ])) ? t('Insert web link') : false),
			'hide_voting' => true,
			'hide_future' => true,
			'hide_location' => true,
			'hide_expire' => true,
			'showacl' => false,
			'ptyp' => $itm[0]['type'],
			'mimeselect' => true,
			'mimetype' => $itm[0]['mimetype'],
			'body' => undo_post_tagging($content),
			'post_id' => $post_id,
			'visitor' => true,
			'title' => htmlspecialchars($itm[0]['title'],ENT_COMPAT,'UTF-8'),
			'placeholdertitle' => t('Title (optional)'),
			'pagetitle' => $block_title,
			'profile_uid' => (intval($channel['channel_id'])),
			'bbcode' => ((in_array($mimetype, [ 'text/bbcode' , 'text/x-multicode' ])) ? true : false)
        );

        $editor = status_editor($x);

        $o .= replace_macros(Theme::get_template('edpost_head.tpl'), array(
            '$title' => t('Edit Block'),
            '$delete' => ((($itm[0]['author_xchan'] === $ob_hash) || ($itm[0]['owner_xchan'] === $ob_hash)) ? t('Delete') : false),
            '$id' => $itm[0]['id'],
            '$cancel' => t('Cancel'),
            '$editor' => $editor
        ));

        return $o;
    }
}
