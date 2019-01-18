<?php /** @file */

/* Private Message backend API */


use Zotlabs\Lib\Libsync;

require_once('include/attach.php');


function mail_prepare_binary($item) {

	return replace_macros(get_markup_template('item_binary.tpl'), [
		'$download'  => t('Download binary/encrypted content'),
		'$url'       => z_root() . '/mail/' . $item['id'] . '/download'
	]);
}


// send a private message
	

function send_message($uid = 0, $recipient = '', $body = '', $subject = '', $replyto = '', $expires = NULL_DATE, $mimetype = 'text/bbcode', $raw = false) { 

	$ret = array('success' => false);
	$is_reply = false;

	$observer_hash = get_observer_hash();

	$channel = (($uid) ? channelx_by_n($uid) : \App::get_channel());

	if(! $channel) {
		$ret['message'] = t('Unable to determine sender.');
		return $ret;
	}


	$body = cleanup_bbcode($body);
	$results = linkify_tags($a, $body, $uid);

	if(! $raw) {
		if(preg_match_all("/\[attachment\](.*?)\[\/attachment\]/",((strpos($body,'[/crypt]')) ? $_POST['media_str'] : $body),$match)) {
			$attaches = $match[1];
		}
	}

	$attachments = '';

	if((! $raw) && preg_match_all('/(\[attachment\](.*?)\[\/attachment\])/',$body,$match)) {
		$attachments = array();
		foreach($match[2] as $mtch) {
			$hash = substr($mtch,0,strpos($mtch,','));
			$rev = intval(substr($mtch,strpos($mtch,',')));
			$r = attach_by_hash_nodata($hash,get_observer_hash(),$rev);
			if($r['success']) {
				$attachments[] = array(
					'href'     => z_root() . '/attach/' . $r['data']['hash'],
					'length'   =>  $r['data']['filesize'],
					'type'     => $r['data']['filetype'],
					'title'    => urlencode($r['data']['filename']),
					'revision' => $r['data']['revision']
				);
			}
			$body = trim(str_replace($match[1],'',$body));
		}
	}

	$jattach = (($attachments) ? json_encode($attachments) : '');


	if(! $recipient) {
		$ret['message'] = t('No recipient provided.');
		return $ret;
	}
	
	if(! strlen($subject))
		$subject = t('[no subject]');


	// look for any existing conversation structure

	$conv_guid = '';

	if(strlen($replyto)) {
		$is_reply = true;
		$r = q("select conv_guid from mail where channel_id = %d and ( mid = '%s' or parent_mid = '%s' ) limit 1",
			intval(local_channel()),
			dbesc($replyto),
			dbesc($replyto)
		);
		if($r) {
			$conv_guid = $r[0]['conv_guid'];
		}
	}		

	if(! $conv_guid) {

		// create a new conversation

		$retconv = create_conversation($channel,$recipient,$subject);	
		if($retconv) {
			$conv_guid = $retconv['guid'];
		}
	}

	if(! $retconv) {
		$r = q("select * from conv where guid = '%s' and uid = %d limit 1",
			dbesc($conv_guid),
			intval(local_channel())
		);
		if($r) {
			$retconv = $r[0];
		}
	}

	if(! $retconv) {
		$ret['message'] = 'conversation not found';
		return $ret;
	}

	$c = q("update conv set updated = '%s' where guid = '%s' and uid = %d",
		dbesc(datetime_convert()),
		dbesc($conv_guid),
		intval(local_channel())
	);

	// generate a unique message_id

	do {
		$dups = false;
		$hash = random_string();

		$mid = $hash . '@' . App::get_hostname();

		$r = q("SELECT id FROM mail WHERE mid = '%s' LIMIT 1",
			dbesc($mid));
		if(count($r))
			$dups = true;
	} while($dups == true);


	if(! strlen($replyto)) {
		$replyto = $mid;
	}

	/**
	 *
	 * When a photo was uploaded into the message using the (profile wall) ajax 
	 * uploader, The permissions are initially set to disallow anybody but the
	 * owner from seeing it. This is because the permissions may not yet have been
	 * set for the post. If it's private, the photo permissions should be set
	 * appropriately. But we didn't know the final permissions on the post until
	 * now. So now we'll look for links of uploaded messages that are in the
	 * post and set them to the same permissions as the post itself.
	 *
	 */

	$match = null;
	$images = null;
	if(preg_match_all("/\[zmg\=([0-9]*)x([0-9]*)\](.*?)\[\/zmg\]/",((strpos($body,'[/crypt]')) ? $_POST['media_str'] : $body),$match))
		$images = $match[3];

	$match = false;


	if($subject)
		$subject = str_rot47(base64url_encode($subject));
	if(($body )&& (! $raw))
		$body  = str_rot47(base64url_encode($body));
	
	$sig = ''; // placeholder
	$mimetype = ''; //placeholder

	$r = q("INSERT INTO mail ( account_id, conv_guid, mail_obscured, channel_id, from_xchan, to_xchan, mail_mimetype, title, body, sig, attach, mid, parent_mid, created, expires, mail_isreply, mail_raw )
		VALUES ( %d, '%s', %d, %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, %d )",
		intval($channel['channel_account_id']),
		dbesc($conv_guid),
		intval(1),
		intval($channel['channel_id']),
		dbesc($channel['channel_hash']),
		dbesc($recipient),
		dbesc(($mimetype)? $mimetype : 'text/bbcode'),
		dbesc($subject),
		dbesc($body),
		dbesc($sig),
		dbesc($jattach),
		dbesc($mid),
		dbesc($replyto),
		dbesc(datetime_convert()),
		dbescdate($expires),
		intval($is_reply),
		intval($raw)
	);

	// verify the save

	$r = q("SELECT * FROM mail WHERE mid = '%s' and channel_id = %d LIMIT 1",
		dbesc($mid),
		intval($channel['channel_id'])
	);
	if($r) {
		$post_id = $r[0]['id'];
		$retmail = $r[0];
		xchan_mail_query($retmail);
	}
	else {
		$ret['message'] = t('Stored post could not be verified.');
		return $ret;
	}

	if($images) {
		foreach($images as $image) {
			if(! stristr($image,z_root() . '/photo/'))
				continue;
			$image_uri = substr($image,strrpos($image,'/') + 1);
			$image_uri = substr($image_uri,0, strpos($image_uri,'-'));
			$r = q("UPDATE photo SET allow_cid = '%s' WHERE resource_id = '%s' AND uid = %d and allow_cid = '%s'",
				dbesc('<' . $recipient . '>'),
				dbesc($image_uri),
				intval($channel['channel_id']),
				dbesc('<' . $channel['channel_hash'] . '>')
			);
			$r = q("UPDATE attach SET allow_cid = '%s' WHERE hash = '%s' AND is_photo = 1 and uid = %d and allow_cid = '%s'",
				dbesc('<' . $recipient . '>'),
				dbesc($image_uri),
				intval($channel['channel_id']),
				dbesc('<' . $channel['channel_hash'] . '>')
			); 
		}
	}

	if($attaches) {
		foreach($attaches as $attach) {
			$hash = substr($attach,0,strpos($attach,','));
			$rev = intval(substr($attach,strpos($attach,',')));
			attach_store($channel,$observer_hash,$options = 'update', array(
				'hash'      => $hash,
				'revision'  => $rev,
				'allow_cid' => '<' . $recipient . '>',

			));
		}
	}

	Zotlabs\Daemon\Master::Summon(array('Notifier','mail',$post_id));

	$ret['success'] = true;
	$ret['message_item'] = intval($post_id);
	$ret['conv'] = $retconv;
	$ret['mail'] = $retmail;

	return $ret;

}

function create_conversation($channel,$recipient,$subject) {

	// create a new conversation

	$conv_guid = random_string();

	$recip = q("select * from xchan where xchan_hash = '%s' limit 1",
		dbesc($recipient)
	);
	if($recip)
		$recip_handle = $recip[0]['xchan_addr'];

	$sender_handle = channel_reddress($channel);

	$handles = $recip_handle . ';' . $sender_handle;

	if($subject)
		$nsubject = str_rot47(base64url_encode($subject));

	$r = q("insert into conv (uid,guid,creator,created,updated,subject,recips) values(%d, '%s', '%s', '%s', '%s', '%s', '%s') ",
		intval($channel['channel_id']),
		dbesc($conv_guid),
		dbesc($sender_handle),
		dbesc(datetime_convert()),
		dbesc(datetime_convert()),
		dbesc($nsubject),
		dbesc($handles)
	);

	$r = q("select * from conv where guid = '%s' and uid = %d limit 1",
		dbesc($conv_guid),
		intval($channel['channel_id'])
	);
	
	return $r[0];

}






function private_messages_list($uid, $mailbox = '', $start = 0, $numitems = 0) {

	$where = '';
	$limit = '';

	$t0 = dba_timer();

	if($numitems)
		$limit = " LIMIT " . intval($numitems) . " OFFSET " . intval($start);
		
	if($mailbox !== '') {
		$x = q("select channel_hash from channel where channel_id = %d limit 1",
			intval($uid)
		);
		if(! $x)
			return array();

		$channel_hash = dbesc($x[0]['channel_hash']);
		$local_channel = intval(local_channel());

		switch($mailbox) {

			case 'inbox':
				$sql = "SELECT * FROM mail WHERE channel_id = $local_channel AND from_xchan != '$channel_hash' ORDER BY created DESC $limit";
				break;

			case 'outbox':
				$sql = "SELECT * FROM mail WHERE channel_id = $local_channel AND from_xchan = '$channel_hash' ORDER BY created DESC $limit";
				break;

			case 'combined':
			default:
				$parents = q("SELECT mail.parent_mid FROM mail LEFT JOIN conv ON mail.conv_guid = conv.guid WHERE mail.mid = mail.parent_mid AND mail.channel_id = %d ORDER BY conv.updated DESC $limit",
					dbesc($local_channel)
				);
				break;

		}

	}

	$r = null;

	if($parents) {
		foreach($parents as $parent) {
			$all = q("SELECT * FROM mail WHERE parent_mid = '%s' AND channel_id = %d ORDER BY created DESC limit 1",
				dbesc($parent['parent_mid']),
				dbesc($local_channel)
			);

			if($all) {
				foreach($all as $single) {
					$r[] = $single;
				}
			}
		}
	}
	else {
		$r = q($sql);
	}

	if(! $r) {
		return array();
	}

	$chans = array();
	foreach($r as $rr) {
		$s = "'" . dbesc(trim($rr['from_xchan'])) . "'";
		if(! in_array($s,$chans))
			$chans[] = $s;
		$s = "'" . dbesc(trim($rr['to_xchan'])) . "'";
		if(! in_array($s,$chans))
			$chans[] = $s;
 	}

	$c = q("select * from xchan where xchan_hash in (" . protect_sprintf(implode(',',$chans)) . ")");

	foreach($r as $k => $rr) {
		$r[$k]['from'] = find_xchan_in_array($rr['from_xchan'],$c);
		$r[$k]['to']   = find_xchan_in_array($rr['to_xchan'],$c);
		$r[$k]['seen'] = intval($rr['mail_seen']);
		if(intval($r[$k]['mail_obscured'])) {
			if($r[$k]['title'])
				$r[$k]['title'] = base64url_decode(str_rot47($r[$k]['title']));
			if($r[$k]['body'])
				$r[$k]['body'] = base64url_decode(str_rot47($r[$k]['body']));
		}
	}

	return $r;
}



function private_messages_fetch_message($channel_id, $messageitem_id, $updateseen = false) {

	$messages = q("select * from mail where id = %d and channel_id = %d order by created asc",
		dbesc($messageitem_id),
		intval($channel_id)
	);

	if(! $messages)
		return array();

	$chans = array();
	foreach($messages as $rr) {
		$s = "'" . dbesc(trim($rr['from_xchan'])) . "'";
		if(! in_array($s,$chans))
			$chans[] = $s;
		$s = "'" . dbesc(trim($rr['to_xchan'])) . "'";
		if(! in_array($s,$chans))
			$chans[] = $s;
	}

	$c = q("select * from xchan where xchan_hash in (" . protect_sprintf(implode(',',$chans)) . ")");

	foreach($messages as $k => $message) {
		$messages[$k]['from'] = find_xchan_in_array($message['from_xchan'],$c);
		$messages[$k]['to']   = find_xchan_in_array($message['to_xchan'],$c);
		if(intval($messages[$k]['mail_obscured'])) {
			if($messages[$k]['title'])
				$messages[$k]['title'] = base64url_decode(str_rot47($messages[$k]['title']));
			if($messages[$k]['body'])
				$messages[$k]['body'] = base64url_decode(str_rot47($messages[$k]['body']));
		}
	}


	if($updateseen) {
		$r = q("UPDATE mail SET mail_seen = 1 where mail_seen = 0 and id = %d AND channel_id = %d",
			dbesc($messageitem_id),
			intval($channel_id)
		);
	}

	return $messages;

}


function private_messages_drop($channel_id, $messageitem_id, $drop_conversation = false) {


	$x = q("select * from mail where id = %d and channel_id = %d limit 1",
		intval($messageitem_id),
		intval($channel_id)
	);
	if(! $x)
		return false;

	$conversation = null;

	if($x[0]['conv_guid']) {
		$y = q("select * from conv where guid = '%s' and uid = %d limit 1",
			dbesc($x[0]['conv_guid']),
			intval($channel_id)
		);
		if($y) {
			$conversation = $y[0];
			$conversation['subject'] = base64url_decode(str_rot47($conversation['subject']));
		}
	}

	if($drop_conversation) {
		$m = array();
		$m['conv'] = array($conversation);
		$m['conv'][0]['deleted'] = 1;

		$z = q("select * from mail where parent_mid = '%s' and channel_id = %d",
			dbesc($x[0]['parent_mid']),
			intval($channel_id)
		);
		if($z) {
			if($x[0]['conv_guid']) {
				q("delete from conv where guid = '%s' and uid = %d",
					dbesc($x[0]['conv_guid']),
					intval($channel_id)
				);
			}		
			$m['mail'] = array();
			foreach($z as $zz) {
				xchan_mail_query($zz);
				$zz['mail_deleted'] = 1;
				$m['mail'][] = encode_mail($zz,true);
			}
			q("DELETE FROM mail WHERE parent_mid = '%s' AND channel_id = %d ",
				dbesc($x[0]['parent_mid']),
				intval($channel_id)
			);
		}
		Libsync::build_sync_packet($channel_id,$m);
		return true;
	}
	else {
		xchan_mail_query($x[0]);

		$x[0]['mail_deleted'] = true;		
		$r = q("DELETE FROM mail WHERE id = %d AND channel_id = %d",
			intval($messageitem_id),
			intval($channel_id)
		);
		Libsync::build_sync_packet($channel_id,array('mail' => array(encode_mail($x,true))));
		return true;
	}
	return false;

}


function private_messages_fetch_conversation($channel_id, $messageitem_id, $updateseen = false) {

	// find the parent_mid of the message being requested

	$r = q("SELECT parent_mid from mail WHERE channel_id = %d and id = %d limit 1",
		intval($channel_id),
		intval($messageitem_id)
	);

	if(! $r) 
		return array();

	$messages = q("select * from mail where parent_mid = '%s' and channel_id = %d order by created asc",
		dbesc($r[0]['parent_mid']),
		intval($channel_id)
	);

	if(! $messages)
		return array();

	$chans = array();
	foreach($messages as $rr) {
		$s = "'" . dbesc(trim($rr['from_xchan'])) . "'";
		if(! in_array($s,$chans))
			$chans[] = $s;
		$s = "'" . dbesc(trim($rr['to_xchan'])) . "'";
		if(! in_array($s,$chans))
			$chans[] = $s;
	}


	$c = q("select * from xchan where xchan_hash in (" . protect_sprintf(implode(',',$chans)) . ")");

	foreach($messages as $k => $message) {
		$messages[$k]['from'] = find_xchan_in_array($message['from_xchan'],$c);
		$messages[$k]['to']   = find_xchan_in_array($message['to_xchan'],$c);
		if(intval($messages[$k]['mail_obscured'])) {
			if($messages[$k]['title'])
				$messages[$k]['title'] = base64url_decode(str_rot47($messages[$k]['title']));
			if($messages[$k]['body'])
				$messages[$k]['body'] = base64url_decode(str_rot47($messages[$k]['body']));
		}
		if($messages[$k]['mail_raw'])
			$messages[$k]['body'] = mail_prepare_binary([ 'id' => $messages[$k]['id'] ]);

	}



	if($updateseen) {
		$r = q("UPDATE mail SET mail_seen = 1 where mail_seen = 0 and parent_mid = '%s' AND channel_id = %d",
			dbesc($r[0]['parent_mid']),
			intval($channel_id)
		);
	}
	
	return $messages;

}

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

function encode_mail($item,$extended = false) {
	$x = [];
	$x['type'] = 'mail';
	$x['encoding'] = 'zot';

	if(array_key_exists('mail_obscured',$item) && intval($item['mail_obscured'])) {
		if($item['title'])
			$item['title'] = base64url_decode(str_rot47($item['title']));
		if($item['body'])
			$item['body'] = base64url_decode(str_rot47($item['body']));
	}

	$x['message_id']     = $item['mid'];
	$x['message_parent'] = $item['parent_mid'];
	$x['created']        = $item['created'];
	$x['expires']        = $item['expires'];
	$x['title']          = $item['title'];
	$x['body']           = $item['body'];
	$x['from']           = encode_item_xchan($item['from']);
	$x['to']             = encode_item_xchan($item['to']);
	$x['raw']            = $item['mail_raw'];
	$x['mimetype']       = $item['mail_mimetype'];

	if($item['attach'])
		$x['attach']     = json_decode($item['attach'],true);

	$x['flags'] = array();

	if(intval($item['mail_recalled'])) {
		$x['flags'][] = 'recalled';
		$x['title'] = '';
		$x['body']  = '';
	}

	if($extended) {
		$x['conv_guid'] = $item['conv_guid'];
		if(intval($item['mail_deleted']))
			$x['flags'][] = 'deleted';
		if(intval($item['mail_replied']))
			$x['flags'][] = 'replied';
		if(intval($item['mail_isreply']))
			$x['flags'][] = 'isreply';
		if(intval($item['mail_seen']))
			$x['flags'][] = 'seen';
	}

	return $x;
}



function get_mail_elements($x) {

	$arr = array();

	if(intval($x['raw'])) {
		$arr['mail_raw'] = intval($x['raw']);
		$arr['body']     = $x['body'];
	}
	else {
		$arr['body']         = (($x['body']) ? htmlspecialchars($x['body'], ENT_COMPAT,'UTF-8',false) : '');

		$maxlen = get_max_import_size();

		if($maxlen && mb_strlen($arr['body']) > $maxlen) {
			$arr['body'] = mb_substr($arr['body'],0,$maxlen,'UTF-8');
			logger('message length exceeds max_import_size: truncated');
		}
	}

	$arr['title']        = (($x['title'])? htmlspecialchars($x['title'],ENT_COMPAT,'UTF-8',false) : '');
	$arr['mail_mimetype'] = (($x['mimetype']) ? htmlspecialchars($x['mimetype'],ENT_COMPAT,'UTF-8',false) : 'text/bbcode');
	$arr['conv_guid']    = (($x['conv_guid'])? htmlspecialchars($x['conv_guid'],ENT_COMPAT,'UTF-8',false) : '');

	$arr['created']      = datetime_convert('UTC','UTC',$x['created']);
	if((! array_key_exists('expires',$x)) || ($x['expires'] <= NULL_DATE))
		$arr['expires'] = NULL_DATE;
	else
		$arr['expires']      = datetime_convert('UTC','UTC',$x['expires']);

	$arr['mail_flags'] = 0;

	if($x['flags'] && is_array($x['flags'])) {
		if(in_array('recalled',$x['flags'])) {
			$arr['mail_recalled'] = 1;
		}
		if(in_array('replied',$x['flags'])) {
			$arr['mail_replied'] = 1;
		}
		if(in_array('isreply',$x['flags'])) {
			$arr['mail_isreply'] = 1;
		}
		if(in_array('seen',$x['flags'])) {
			$arr['mail_seen'] = 1;
		}
		if(in_array('deleted',$x['flags'])) {
			$arr['mail_deleted'] = 1;
		}
	}

	$key = get_config('system','pubkey');
	$arr['mail_obscured'] = 1;
	if($arr['body']) {
		$arr['body']  = str_rot47(base64url_encode($arr['body']));
	}

	if($arr['title']) {
		$arr['title'] = str_rot47(base64url_encode($arr['title']));
	}
	if($arr['created'] > datetime_convert())
		$arr['created']  = datetime_convert();


	$arr['mid']          = (($x['message_id'])     ? htmlspecialchars($x['message_id'],     ENT_COMPAT,'UTF-8',false) : '');
	$arr['parent_mid']   = (($x['message_parent']) ? htmlspecialchars($x['message_parent'], ENT_COMPAT,'UTF-8',false) : '');

	if($x['attach'])
		$arr['attach'] = activity_sanitise($x['attach']);

	if(($xchan_hash = import_author_xchan($x['from'])) !== false)
		$arr['from_xchan'] = $xchan_hash;
	else
		return array();

	if(($xchan_hash = import_author_xchan($x['to'])) !== false)
		$arr['to_xchan'] = $xchan_hash;
	else
		return array();

	return $arr;
}

function mail_store($arr) {

	if(! $arr['channel_id']) {
		logger('mail_store: no uid');
		return 0;
	}

	$channel = channelx_by_n($arr['channel_id']);

	if(! $arr['mail_obscured']) {
		if((strpos($arr['body'],'<') !== false) || (strpos($arr['body'],'>') !== false))
			$arr['body'] = escape_tags($arr['body']);
	}

	if(array_key_exists('attach',$arr)) {
		if(is_array($arr['attach'])) {
			$arr['attach'] = json_encode($arr['attach'],JSON_UNESCAPED_SLASHES);
		}
	}
	else {
		$arr['attach'] = '';
	}

	$arr['account_id']    = ((x($arr,'account_id'))           ? intval($arr['account_id'])                 : 0);
	$arr['mid']           = ((x($arr,'mid'))           ? notags(trim($arr['mid']))           : random_string());
	$arr['from_xchan']    = ((x($arr,'from_xchan'))  ? notags(trim($arr['from_xchan']))  : '');
	$arr['to_xchan']      = ((x($arr,'to_xchan'))   ? notags(trim($arr['to_xchan']))   : '');
	$arr['created']       = ((x($arr,'created') !== false) ? datetime_convert('UTC','UTC',$arr['created']) : datetime_convert());
	$arr['expires']       = ((x($arr,'expires') !== false) ? datetime_convert('UTC','UTC',$arr['expires']) : NULL_DATE);
	$arr['title']         = ((x($arr,'title'))         ? trim($arr['title'])         : '');
	$arr['parent_mid']    = ((x($arr,'parent_mid'))    ? notags(trim($arr['parent_mid']))    : '');
	$arr['body']          = ((x($arr,'body'))          ? trim($arr['body'])                  : '');
	$arr['sig']           = ((x($arr,'sig'))           ? trim($arr['sig'])                   : '');
	$arr['conv_guid']     = ((x($arr,'conv_guid'))     ? trim($arr['conv_guid'])             : '');
	$arr['mail_mimetype'] = ((x($arr,'mail_mimetype')) ? trim($arr['mail_mimetype'])         : 'text/bbcode');

	$arr['mail_flags']    = ((x($arr,'mail_flags'))    ? intval($arr['mail_flags'])          : 0 );
	$arr['mail_raw']      = ((x($arr,'mail_raw'))      ? intval($arr['mail_raw'])            : 0 );


	if($arr['parent_mid']) {
		$parent_item = q("select * from mail where mid = '%s' and channel_id = %d limit 1",
			dbesc($arr['parent_mid']),
			intval($arr['channel_id'])
		);
		if(($parent_item) && (! $arr['conv_guid'])) {
			$arr['conv_guid'] = $parent_item[0]['conv_guid'];
		}
	}
	else {
		logger('mail_store: missing parent');
		$arr['parent_mid'] = $arr['mid'];
	}

	if($arr['from_xchan'] === $channel['channel_hash'])
		$conversant = $arr['to_xchan'];
	else
		$conversant = $arr['from_xchan'];


	if(! $arr['conv_guid']) {
		$x = create_conversation($channel,$conversant,(($arr['title']) ? base64url_decode(str_rot47($arr['title'])) : ''));
		$arr['conv_guid'] = (($x) ? $x['guid'] : '');
	}


	$r = q("SELECT id FROM mail WHERE mid = '%s' AND channel_id = %d LIMIT 1",
		dbesc($arr['mid']),
		intval($arr['channel_id'])
	);

	if($r) {
		logger('Duplicate item ignored. ' . print_r($arr,true));
		return 0;
	}

	if(! $r && $arr['mail_recalled'] == 1) {
		logger('Recalled item not found. ' . print_r($arr,true));
		return 0;
	}

	/**
	 * @hooks post_mail
	 *   Called when a mail message has been composed.
	 */
	call_hooks('post_mail', $arr);

	if(x($arr,'cancel')) {
		logger('Post cancelled by plugin.');
		return 0;
	}

	logger('mail_store: ' . print_r($arr,true), LOGGER_DATA);

	create_table_from_array('mail', $arr);

	// find the item we just created

	$r = q("SELECT id FROM mail WHERE mid = '%s' AND channel_id = %d ORDER BY id ASC ",
		$arr['mid'],           // already dbesc'd
		intval($arr['channel_id'])
	);

	if($r) {
		$current_post = $r[0]['id'];
		logger('Created item ' . $current_post, LOGGER_DEBUG);
		$arr['id'] = $current_post; // for notification
	}
	else {
		logger('Could not locate created item');
		return 0;
	}
	if(count($r) > 1) {
		logger('Duplicated post occurred. Removing duplicates.');
		q("DELETE FROM mail WHERE mid = '%s' AND channel_id = %d AND id != %d ",
			$arr['mid'],
			intval($arr['channel_id']),
			intval($current_post)
		);
	}
	else {

		$notif_params = array(
			'from_xchan' => $arr['from_xchan'],
			'to_xchan'   => $arr['to_xchan'],
			'type'       => NOTIFY_MAIL,
			'item'       => $arr,
			'verb'       => ACTIVITY_POST,
			'otype'      => 'mail'
		);

		Enotify::submit($notif_params);
	}

	if($arr['conv_guid']) {
		$c = q("update conv set updated = '%s' where guid = '%s' and uid = %d",
			dbesc(datetime_convert()),
			dbesc($arr['conv_guid']),
			intval($arr['channel_id'])
		);
	}

	/**
	 * @hooks post_mail_end
	 *   Called when a mail message has been delivered.
	 */
	call_hooks('post_mail_end', $arr);
	return $current_post;
}

