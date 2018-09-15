<?php

/* Common private message processing functions */

function msg_drop($message_id, $channel_id, $conv_guid) {

    // Delete message
    $r = q("DELETE FROM mail WHERE id = %d AND channel_id = %d",
		$message_id,
		$channel_id
	);

	// If it was a first message in thread
	$z = q("SELECT * FROM mail WHERE mid = '%s' AND channel_id = %d",
		$message_id,
		$channel_id
	);
	if (! $z) {
	    // Get new first message...
	    $r = q("SELECT mid FROM mail WHERE conv_guid = '%s' AND channel_id = %d ORDER BY id ASC LIMIT 1",
		    $conv_guid,
		    $channel_id
	    );
	    // ...and refer whole thread to it
	    q("UPDATE mail SET parent_mid = '%s', mail_isreply = abs(mail_isreply - 1) WHERE conv_guid = '%s' AND channel_id = %d",
	        dbesc($r[0]['mid']),
	        $conv_guid,
	        $channel_id
	    );
	    return true;
	} else {
	    return false;
	}

}
