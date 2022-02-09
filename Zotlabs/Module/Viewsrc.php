<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Channel;

class Viewsrc extends Controller
{

    public function get()
    {

        $o = '';

        $sys = Channel::get_system();

        $item_id = ((argc() > 1) ? intval(argv(1)) : 0);
        $json = ((argc() > 2 && argv(2) === 'json') ? true : false);
        $dload = ((argc() > 2 && argv(2) === 'download') ? true : false);

        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
        }

        if (!$item_id) {
            App::$error = 404;
            notice(t('Item not found.') . EOL);
        }

        $item_normal = item_normal_search();

        if (local_channel() && $item_id) {
            $r = q(
                "select id, item_flags, mimetype, item_obscured, body, llink, plink from item where uid in (%d , %d) and id = %d $item_normal limit 1",
                intval(local_channel()),
                intval($sys['channel_id']),
                intval($item_id)
            );

            if ($r) {
                if (intval($r[0]['item_obscured'])) {
                    $dload = true;
                }

                if ($dload) {
                    header('Content-type: ' . $r[0]['mimetype']);
                    header('Content-Disposition: attachment; filename="' . t('item') . '-' . $item_id . '"');
                    echo $r[0]['body'];
                    killme();
                }

                $content = escape_tags($r[0]['body']);
                $o = (($json) ? json_encode($content) : str_replace("\n", '<br>', $content));
            }
        }

        $inspect = ((is_site_admin()) ? '| <a href="' . z_root() . '/inspect/item/' . $r[0]['id'] . '" target="_blank">' . t('Inspect') . '</a>' : EMPTY_STR);


        if (is_ajax()) {
            echo '<div class="p-1">';
            echo '<div>' . t('Local id:') . ' ' . $r[0]['id'] . ' | <a href="' . $r[0]['plink'] . '" target="_blank">' . t('Permanent link') . '</a> | <a href="' . $r[0]['llink'] . '" target="_blank">' . t('Local link') . '</a>' . $inspect . '</div>';
            echo '<hr>';
            echo '<pre class="p-1">' . $o . '</pre>';
            echo '</div>';
            killme();
        }

        return $o;
    }
}
