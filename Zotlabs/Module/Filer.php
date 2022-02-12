<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Render\Theme;


require_once('include/security.php');
require_once('include/bbcode.php');


class Filer extends Controller
{

    public function get()
    {

        if (!local_channel()) {
            killme();
        }

        $term = unxmlify(trim($_GET['term']));
        $item_id = ((App::$argc > 1) ? intval(App::$argv[1]) : 0);

        logger('filer: tag ' . $term . ' item ' . $item_id);

        if ($item_id && strlen($term)) {
            // file item
            store_item_tag(local_channel(), $item_id, TERM_OBJ_POST, TERM_FILE, $term, '');

            // protect the entire conversation from periodic expiration

            $r = q(
                "select parent from item where id = %d and uid = %d limit 1",
                intval($item_id),
                intval(local_channel())
            );
            if ($r) {
                $x = q(
                    "update item set item_retained = 1 where id = %d and uid = %d",
                    intval($r[0]['parent']),
                    intval(local_channel())
                );
            }
        } else {
            $filetags = [];
            $r = q(
                "select distinct(term) from term where uid = %d and ttype = %d order by term asc",
                intval(local_channel()),
                intval(TERM_FILE)
            );
            if (count($r)) {
                foreach ($r as $rr) {
                    $filetags[] = $rr['term'];
                }
            }
            $tpl = Theme::get_template("filer_dialog.tpl");
            $o = replace_macros($tpl, array(
                '$field' => array('term', t('Enter a folder name'), '', '', $filetags, 'placeholder="' . t('or select an existing folder (doubleclick)') . '"'),
                '$submit' => t('Save'),
                '$title' => t('Save to Folder'),
                '$cancel' => t('Cancel')
            ));

            echo $o;
        }
        killme();
    }
}
