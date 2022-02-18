<?php

namespace Code\Widget;

class Groups
{

    public function widget($arr)
    {

        $output = EMPTY_STR;


        if (!local_channel()) {
            return $output;
        }

        if (is_array($arr) && array_key_exists('limit', $arr) && intval($arr['limit']) >= 0) {
            $limit = " limit " . intval($arr['limit']) . " ";
        } else {
            $limit = EMPTY_STR;
        }

        $unseen = 0;
        if (is_array($arr) && array_key_exists('unseen', $arr) && intval($arr['unseen'])) {
            $unseen = 1;
        }

        $perms_sql = item_permissions_sql(local_channel()) . item_normal();

        $xf = false;

        $x1 = q(
            "select xchan from abconfig where chan = %d and cat = 'system' and k = 'their_perms' and not v like '%s'",
            intval(local_channel()),
            dbesc('%send_stream%')
        );
        if ($x1) {
            $xc = ids_to_querystr($x1, 'xchan', true);

            $x2 = q(
                "select xchan from abconfig where chan = %d and cat = 'system' and k = 'their_perms' and v like '%s' and xchan in (" . $xc . ") ",
                intval(local_channel()),
                dbesc('%tag_deliver%')
            );

            if ($x2) {
                $xf = ids_to_querystr($x2, 'xchan', true);

                // private forums
                $x3 = q(
                    "select xchan from abconfig where chan = %d and cat = 'system' and k = 'their_perms' and v like '%s' and xchan in (" . $xc . ") and not xchan in (" . $xf . ") ",
                    intval(local_channel()),
                    dbesc('%post_wall%')
                );
                if ($x3) {
                    $xf = ids_to_querystr(array_merge($x2, $x3), 'xchan', true);
                }
            }
        }

        // note: XCHAN_TYPE_GROUP = 1
        $sql_extra = (($xf) ? " and ( xchan_hash in (" . $xf . ") or xchan_type = 1 ) " : " and xchan_type = 1 ");

        $r1 = q(
            "select abook_id, xchan_hash, xchan_name, xchan_url, xchan_photo_s from abook left join xchan on abook_xchan = xchan_hash where xchan_deleted = 0 and abook_channel = %d and abook_pending = 0 and abook_ignored = 0 and abook_blocked = 0 and abook_archived = 0 $sql_extra order by xchan_name $limit ",
            intval(local_channel())
        );

        if (!$r1) {
            return $output;
        }

        $str = EMPTY_STR;

        // Trying to cram all this into a single query with joins and the proper group by's is tough.
        // There also should be a way to update this via ajax.

        for ($x = 0; $x < count($r1); $x++) {
            $r = q(
                "select sum(item_unseen) as unseen from item 
				where uid = %d and owner_xchan = '%s' and item_unseen = 1 $perms_sql ",
                intval(local_channel()),
                dbesc($r1[$x]['xchan_hash'])
            );
            if ($r) {
                $r1[$x]['unseen'] = $r[0]['unseen'];
            }
        }

        /**
         * @FIXME
         * This SQL makes the counts correct when you get forum posts arriving from different routes/sources
         * (like personal channels). However the stream query for these posts doesn't yet include this
         * correction and it makes the SQL for that query pretty hairy so this is left as a future exercise.
         * It may make more sense in that query to look for the mention in the body rather than another join,
         * but that makes it very inefficient.
         *
         *        $r = q("select sum(item_unseen) as unseen from item left join term on oid = id where otype = %d and owner_xchan != '%s' and item.uid = %d and url = '%s' and ttype = %d $perms_sql ",
         *            intval(TERM_OBJ_POST),
         *            dbesc($r1[$x]['xchan_hash']),
         *            intval(local_channel()),
         *            dbesc($r1[$x]['xchan_url']),
         *            intval(TERM_MENTION)
         *        );
         *        if($r)
         *            $r1[$x]['unseen'] = ((array_key_exists('unseen',$r1[$x])) ? $r1[$x]['unseen'] + $r[0]['unseen'] : $r[0]['unseen']);
         *
         * end @FIXME
         */

        if ($r1) {
            $output .= '<div class="widget">';
            $output .= '<h3>' . t('Groups') . '</h3><ul class="nav nav-pills flex-column">';

            foreach ($r1 as $rr) {
                $link = 'stream?f=&pf=1&cid=' . $rr['abook_id'];
                if ($x3) {
                    foreach ($x3 as $xx) {
                        if ($rr['xchan_hash'] == $xx['xchan']) {
                            $link = zid($rr['xchan_url']);
                        }
                    }
                }

                if ($unseen && (!intval($rr['unseen']))) {
                    continue;
                }


                $output .= '<li class="nav-item"><a class="nav-link" href="' . $link . '" ><span class="badge badge-secondary float-right">' . ((intval($rr['unseen'])) ? intval($rr['unseen']) : '') . '</span><img class ="menu-img-1" src="' . $rr['xchan_photo_s'] . '" /> ' . $rr['xchan_name'] . '</a></li>';
            }
            $output .= '</ul></div>';
        }
        return $output;
    }
}
