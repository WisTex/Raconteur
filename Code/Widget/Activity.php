<?php

namespace Code\Widget;

use Code\Lib\LibBlock;
use Code\Extend\Hook;

class Activity implements WidgetInterface
{

    public function widget(array $arguments): string
    {

        if (!local_channel()) {
            return '';
        }

        $o = EMPTY_STR;

        if (is_array($arguments) && array_key_exists('limit', $arguments)) {
            $limit = " limit " . intval($arguments['limit']) . " ";
        } else {
            $limit = EMPTY_STR;
        }

        $perms_sql = item_permissions_sql(local_channel()) . item_normal();

        $items = q(
            "select author_xchan from item where item_unseen = 1 and uid = %d $perms_sql",
            intval(local_channel())
        );

        $contributors = [];
        $tmpArray = [];

        if ($items) {
            foreach ($items as $item) {
                if (array_key_exists($item['author_xchan'], $contributors)) {
                    $contributors[$item['author_xchan']]++;
                } else {
                    $contributors[$item['author_xchan']] = 1;
                }
            }
            foreach ($contributors as $k => $v) {
                if (!LibBlock::fetch_by_entity(local_channel(), $k)) {
                    $tmpArray[] = ['author_xchan' => $k, 'total' => $v];
                }
            }
            usort($tmpArray, 'total_sort');
            xchan_query($tmpArray);
        }

        $x = ['entries' => $tmpArray];
        Hook::call('activity_widget', $x);
        $tmpArray = $x['entries'];

        if ($tmpArray) {
            $o .= '<div class="widget">';
            $o .= '<h3>' . t('Activity', 'widget') . '</h3><ul class="nav nav-pills flex-column">';

            foreach ($tmpArray as $value) {
                $o .= '<li class="nav-item"><a class="nav-link" href="stream?f=&xchan=' . urlencode($value['author_xchan']) . '" ><span class="badge badge-secondary float-end">' . ((intval($value['total'])) ? intval($value['total']) : '') . '</span><img src="' . $value['author']['xchan_photo_s'] . '" class="menu-img-1" /> ' . $value['author']['xchan_name'] . '</a></li>';
            }
            $o .= '</ul></div>';
        }
        return $o;
    }
}
