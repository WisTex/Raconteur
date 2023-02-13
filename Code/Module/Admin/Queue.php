<?php

namespace Code\Module\Admin;

use Code\Lib\Queue as ZQueue;

use Code\Render\Theme;


class Queue
{

    public function get()
    {

        $o = '';

        $expert = 1; // ((array_key_exists('expert', $_REQUEST)) ? intval($_REQUEST['expert']) : 0);

        if ($_REQUEST['dropsite']) {
            hubloc_mark_as_down($_REQUEST['dropsite']);
            ZQueue::remove_by_posturl($_REQUEST['dropsite']);
        }

        if ($_REQUEST['emptysite']) {
            ZQueue::remove_by_posturl($_REQUEST['emptysite']);
        }

        if ($_REQUEST['details']) {
            $logs = q("select outq_created, outq_log from outq where outq_posturl = '%s' and outq_log != '' order by outq_created",
                dbesc(escape_tags($_REQUEST['details']))
            );
            $output = replace_macros(Theme::get_template('admin_queue_details.tpl'), [
                '$banner' => escape_tags($_REQUEST['details']),
                '$logs' => $logs,
                '$created_text' => t('Queue entry created:'),
                '$log_text' => t('Delivery/tracking log:'),
                '$nothing' => t('No entries'),
            ]);
            return $output;
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
            '$nukesite' => t('Mark site permanently offline'),
            '$empty' => t('Empty queue for this site'),
            '$examine' => t('Examine delivery logs'),
            '$lastconn' => t('Last known contact'),
            '$hasentries' => ((count($r)) ? true : false),
            '$entries' => $r,
            '$expert' => $expert
        ));

        return $o;
    }
}
