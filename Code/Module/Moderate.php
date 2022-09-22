<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\Libsync;
use Code\Lib\Channel;
use Code\Daemon\Run;
use Code\Access\PermissionRoles;

require_once('include/conversation.php');


class Moderate extends Controller
{


    public function get()
    {
        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
            return '';
        }

        App::set_pager_itemspage(60);
        $pager_sql = sprintf(" LIMIT %d OFFSET %d ", intval(App::$pager['itemspage']), intval(App::$pager['start']));

        $mid = ((!empty($_REQUEST['mid'])) ? $_REQUEST['mid'] : '');

        //show all items
        if (argc() == 1 && !$mid) {
            $r = q(
                "select item.id as item_id, item.* from item where item.uid = %d and item_blocked = %d and item_deleted = 0 order by created desc $pager_sql",
                intval(local_channel()),
                intval(ITEM_MODERATED)
            );
            if (!$r) {
                info(t('No posts requiring approval at this time.') . EOL);
            }
        }

        // show a single item
        $action = '';
        if (argc() == 1 && $mid) {
            $action = 'view';
        }
        if (argc() == 2 && !$mid) {
            $mid = argv(1);
            $action = 'view';
        }
        if ($action) {
            $post_id = unpack_link_id(escape_tags($mid));

            $r = q(
                "select item.id as item_id, item.* from item where item.mid = '%s' and item.uid = %d and item_blocked = %d and item_deleted = 0 order by created desc $pager_sql",
                dbesc($post_id),
                intval(local_channel()),
                intval(ITEM_MODERATED)
            );
        }

        $action = '';
        if (argc() > 2 && !$mid) {
            $mid = intval(argv(1));
            $action = argv(2);
        }
        elseif (argc() == 2 && $mid) {
            $action = argv(1);
        }
        $post_id = $mid;
        if ($action) {
            if (!$post_id) {
                goaway(z_root() . '/moderate');
            }

            $r = q(
                "select * from item where uid = %d and id = %d and item_blocked = %d limit 1",
                intval(local_channel()),
                intval($post_id),
                intval(ITEM_MODERATED)
            );

            if ($r) {
                $item = $r[0];

                if ($action === 'approve') {
                    q(
                        "update item set item_blocked = 0 where uid = %d and id = %d",
                        intval(local_channel()),
                        intval($post_id)
                    );

                    $item['item_blocked'] = 0;

                    item_update_parent_commented($item);

                    notice(t('Comment approved') . EOL);
                } elseif ($action === 'drop') {
                    drop_item($post_id);
                    notice(t('Comment deleted') . EOL);
                }

                // refetch the item after changes have been made

                $r = q(
                    "select * from item where id = %d",
                    intval($post_id)
                );
                if ($r) {
                    xchan_query($r);
                    $sync_item = fetch_post_tags($r);
                    Libsync::build_sync_packet(local_channel(), ['item' => [encode_item($sync_item[0], true)]]);
                }
                if ($action === 'approve') {
                    if ($item['id'] !== $item['parent']) {
                        // if this is a group comment, call tag_deliver() to generate the associated
                        // Announce activity so microblog destinations will see it in their home timeline
                        $role = get_pconfig(local_channel(), 'system', 'permissions_role');
                        $rolesettings = PermissionRoles::role_perms($role);
                        $channel_type = isset($rolesettings['channel_type']) ? $rolesettings['channel_type'] : 'normal';

                        $is_group = (($channel_type === 'group') ? true : false);
                        if ($is_group) {
                            tag_deliver(local_channel(), $post_id);
                        }
                    }
                    Run::Summon(['Notifier', 'comment-new', $post_id]);
                }
                goaway(z_root() . '/moderate');
            }
        }

        if ($r) {
            xchan_query($r);
            $items = fetch_post_tags($r, true);
        } else {
            $items = [];
        }

        $o = conversation($items, 'moderate', false, 'traditional');
        $o .= alt_pager(count($items));
        return $o;
    }
}
