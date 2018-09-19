<?php

/* Common private message processing functions */

function msg_drop($message_id, $channel_id, $conv_guid) {

    // Delete message
    $r = q("DELETE FROM mail WHERE id = %d AND channel_id = %d",
        intval($message_id),
        intval($channel_id)
    );

    // Get new first message...
    $r = q("SELECT mid, parent_mid FROM mail WHERE conv_guid = '%s' AND channel_id = %d ORDER BY id ASC LIMIT 1",
        dbesc($conv_guid),
        intval($channel_id)
    );
    // ...and if wasn't first before...
    if ($r[0]['mid'] != $r[0]['parent_mid']) {
        // ...refer whole thread to it
        q("UPDATE mail SET parent_mid = '%s', mail_isreply = abs(mail_isreply - 1) WHERE conv_guid = '%s' AND channel_id = %d",
            dbesc($r[0]['mid']),
            dbesc($conv_guid),
            intval($channel_id)
        );
    }

}
