<?php

namespace Zotlabs\Update;

use Zotlabs\Lib\LibBlock;

class _1239
{

    public function run()
    {
        $r = q("select channel_id from channel where true");
        if ($r) {
            foreach ($r as $rv) {
                $a = get_pconfig($rv['channel_id'], 'system', 'blocked');
                if ($a) {
                    $list = explode(',', $a);
                    if ($list) {
                        foreach ($list as $l) {
                            if (trim($l)) {
                                LibBlock::store([
                                    'block_channel_id' => intval($rv['channel_id']),
                                    'block_type' => 0,
                                    'block_entity' => trim($l),
                                    'block_comment' => t('Added by superblock')
                                ]);
                            }
                        }
                    }
                    del_pconfig($rv['channel_id'], 'system', 'blocked');
                }
            }
        }
        return UPDATE_SUCCESS;
    }


    public function verify()
    {

        $r = q("select * from pconfig where cat = 'system' and k = 'blocked'");
        if ($r) {
            return false;
        }
        return true;
    }

}
