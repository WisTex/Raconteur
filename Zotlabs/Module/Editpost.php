<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Channel;
use Zotlabs\Lib\PermissionDescription;

require_once('include/acl_selectors.php');
require_once('include/taxonomy.php');
require_once('include/conversation.php');

class Editpost extends Controller
{

    public function get()
    {

        $output = EMPTY_STR;

        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        $post_id = ((argc() > 1) ? intval(argv(1)) : 0);

        if (!$post_id) {
            notice(t('Item not found') . EOL);
            return;
        }

        $item = q(
            "SELECT * FROM item WHERE id = %d AND ( owner_xchan = '%s' OR author_xchan = '%s' ) LIMIT 1",
            intval($post_id),
            dbesc(get_observer_hash()),
            dbesc(get_observer_hash())
        );

        // don't allow web editing of potentially binary content (item_obscured = 1)

        if ((!$item) || intval($item[0]['item_obscured'])) {
            notice(t('Item is not editable') . EOL);
            return;
        }

        $item = array_shift($item);

        $owner_uid = intval($item['uid']);
        $owner = Channel::from_id($owner_uid);

        if ($item['resource_type'] === 'photo' && $item['resource_id'] && $owner) {
            goaway(z_root() . '/photos/' . $owner['channel_address'] . '/image/' . $item['resource_id'] . '?expandform=1');
        }

        if ($item['resource_type'] === 'event' && $item['resource_id']) {
            goaway(z_root() . '/events/' . $item['resource_id'] . '?expandform=1');
        }


        $channel = App::get_channel();

        $category = '';
        $collections = [];
        $catsenabled = ((Apps::system_app_installed($owner_uid, 'Categories')) ? 'categories' : '');

        // we have a single item, but fetch_post_tags expects an array. Convert it before and after.

        $item = array_shift(fetch_post_tags([$item]));

        if ($catsenabled) {
            $cats = get_terms_oftype($item['term'], TERM_CATEGORY);

            if ($cats) {
                foreach ($cats as $cat) {
                    if (strlen($category)) {
                        $category .= ', ';
                    }
                    $category .= $cat['term'];
                }
            }
        }

        $clcts = get_terms_oftype($item['term'], TERM_PCATEGORY);
        if ($clcts) {
            foreach ($clcts as $clct) {
                $collections[] = $clct['term'];
            }
        }

        if ($item['attach']) {
            $j = json_decode($item['attach'], true);
            if ($j) {
                foreach ($j as $jj) {
                    $item['body'] .= "\n" . '[attachment]' . basename($jj['href']) . ',' . $jj['revision'] . '[/attachment]' . "\n";
                }
            }
        }

        if (intval($item['item_unpublished'])) {
            // clear the old creation date if editing a saved draft. These will always show as just created.
            unset($item['created']);
        }

        if ($item['summary']) {
            $item['body'] = '[summary]' . $item['summary'] . '[/summary]' . "\n\n" . $item['body'];
        }

        $x = [
            'nickname' => $channel['channel_address'],
            'item' => $item,
            'editor_autocomplete' => true,
            'bbco_autocomplete' => 'bbcode',
            'return_path' => $_SESSION['return_url'],
            'button' => t('Submit'),
            'hide_voting' => true,
            'hide_future' => true,
            'hide_location' => true,
            'is_draft' => ((intval($item['item_unpublished'])) ? true : false),
            'parent' => (($item['mid'] === $item['parent_mid']) ? 0 : $item['parent']),
            'mimetype' => $item['mimetype'],
            'ptyp' => $item['obj_type'],
            'body' => htmlspecialchars_decode(undo_post_tagging($item['body']), ENT_COMPAT),
            'post_id' => $post_id,
            'defloc' => $channel['channel_location'],
            'visitor' => true,
            'title' => htmlspecialchars_decode($item['title'], ENT_COMPAT),
            'category' => $category,
            'showacl' => ((intval($item['item_unpublished'])) ? true : false),
            'lockstate' => (($item['allow_cid'] || $item['allow_gid'] || $item['deny_cid'] || $item['deny_gid']) ? 'lock' : 'unlock'),
            'acl' => populate_acl($item, true, PermissionDescription::fromGlobalPermission('view_stream'), get_post_aclDialogDescription(), 'acl_dialog_post'),
            'bang' => EMPTY_STR,
            'permissions' => $item,
            'profile_uid' => $owner_uid,
            'catsenabled' => $catsenabled,
            'collections' => $collections,
            'jotnets' => true,
            'hide_expire' => true,
            'bbcode' => true
        ];

        $editor = status_editor($x);

        $output .= replace_macros(
            get_markup_template('edpost_head.tpl'),
            ['$title' => t('Edit post'), '$cancel' => t('Cancel'), '$editor' => $editor]
        );

        return $output;
    }
}
