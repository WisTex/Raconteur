<?php

namespace Code\Module\Admin;

use Code\Lib\Queue as ZQueue;

use Code\Render\Theme;


class Queue
{

    public function get()
    {

        $o = '';

        $expert = ((array_key_exists('expert', $_REQUEST)) ? intval($_REQUEST['expert']) : 0);

        if ($_REQUEST['drophub']) {
            hubloc_mark_as_down($_REQUEST['drophub']);
            ZQueue::remove_by_posturl($_REQUEST['drophub']);
        }

        if ($_REQUEST['emptyhub']) {
            ZQueue::remove_by_posturl($_REQUEST['emptyhub']);
        }

        $r = q("select count(outq_posturl) as total, max(outq_priority) as priority, outq_posturl from outq 
			where outq_delivered = 0 group by outq_posturl order by total desc");

        for ($x = 0; $x < count($r); $x++) {
            $r[$x]['eurl'] = urlencode($r[$x]['outq_posturl']);
            $r[$x]['connected'] = datetime_convert('UTC', date_default_timezone_get(), $r[$x]['connected'], 'Y-m-d');
        }

        $o = replace_macros(Theme::get_template('admin_queue.tpl'), array(
            '$banner' => t('Queue Statistics'),
            '$numentries' => t('Total Entries'),
            '$priority' => t('Priority'),
            '$desturl' => t('Destination URL'),
            '$nukehub' => t('Mark hub permanently offline'),
            '$empty' => t('Empty queue for this hub'),
            '$lastconn' => t('Last known contact'),
            '$hasentries' => ((count($r)) ? true : false),
            '$entries' => $r,
            '$expert' => $expert
        ));

        return $o;
    }
}
