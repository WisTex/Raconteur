<?php

namespace Zotlabs\Widget;

/*
 * Show pinned content
 *
 */

use App;

class Pinned
{

    private $allowed_types = 0;
    private $uid = 0;


    /*
     * @brief Displays pinned items
     *
     */

    public function widget($args)
    {

        $ret = '';

        $this->allowed_types = get_config('system', 'pin_types', [ITEM_TYPE_POST]);

        $this->uid = App::$profile_uid;

        $types = (($args['types']) ?: [ITEM_TYPE_POST]);

        $id_list = $this->list($types);

        // logger('id_list: ' . print_r($id_list,true));

        if (empty($id_list)) {
            return $ret;
        }

        $o = conversation($id_list, 'stream-new', 0, 'traditional');

        // change some id and class names so that auto-update doesn't stumble over them

        $o = str_replace('<div id="threads-begin">', '<div id="pins-begin">', $o);
        $o = str_replace('<div id="threads-end">', '<div id="pins-end">', $o);
        $o = str_replace('<div id="conversation-end">', '<div id="pin-widget-end">', $o);
        $o = str_replace('class="thread-wrapper ', 'class="pin-thread-wrapper ', $o);
        $o = str_replace('class="wall-item-ago', 'class="wall-item-ago pinned', $o);

        // logger('output: ' . $o);
        return '<hr>' . $o . '<hr>';
    }


    /*
     * @brief List pinned items depend on type
     *
     * @param $types
     * @return array of pinned items
     *
     */
    private function list($types)
    {

        if (empty($types) || (!is_array($types))) {
            return [];
        }

        $item_types = array_intersect($this->allowed_types, $types);

        if (empty($item_types)) {
            return [];
        }

        $mids_list = [];

        foreach ($item_types as $type) {
            $mids = get_pconfig($this->uid, 'pinned', $type, []);
            if ($mids) {
                foreach ($mids as $mid) {
                    if ($mid) {
                        $mids_list[] = $mid;
                    }
                }
            }
        }
        if (empty($mids_list)) {
            return [];
        }

        $item_normal = item_normal();
        $sql_extra = item_permissions_sql($this->uid);

        $r = q(
            "SELECT *, id as item_id FROM item WHERE parent_mid IN (" . protect_sprintf(stringify_array($mids_list, true)) . ") AND uid = %d AND id = parent $item_normal $sql_extra ORDER BY created DESC",
            intval($this->uid)
        );
        if ($r) {
            xchan_query($r, true);
            $items = fetch_post_tags($r, true);

            for ($x = 0; $x < count($items); $x++) {
                $items[$x]['item_id'] = 'pin-' . $items[$x]['item_id'];
            }

            return $items;
        }
        return [];
    }
}
