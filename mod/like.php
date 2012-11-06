<?php

require_once('include/security.php');
require_once('include/bbcode.php');
require_once('include/items.php');


function like_content(&$a) {


	$observer = $a->get_observer();



	$verb = notags(trim($_GET['verb']));

	if(! $verb)
		$verb = 'like';


	switch($verb) {
		case 'like':
		case 'unlike':
			$activity = ACTIVITY_LIKE;
			break;
		case 'dislike':
		case 'undislike':
			$activity = ACTIVITY_DISLIKE;
			break;
		default:
			return;
			break;
	}


	$item_id = ((argc() > 1) ? notags(trim(argv(1))) : 0);

	logger('like: verb ' . $verb . ' item ' . $item_id, LOGGER_DEBUG);


	$r = q("SELECT * FROM item WHERE id = %d and item_restrict = 0 LIMIT 1",
		dbesc($item_id)
	);

	if(! $item_id || (! $r)) {
		logger('like: no item ' . $item_id);
		return;
	}

	$item = $r[0];

	$owner_uid = $item['uid'];

	if(! perm_is_allowed($owner_uid,$observer['xchan_hash'],'post_comments')) {
		notice( t('Permission denied') . EOL);
		return;
	}

	$remote_owner = $item['owner_xchan'];



// fixme
//	if(! $item['wall']) {
		// The top level post may have been written by somebody on another system

//		$r = q("SELECT * FROM `contact` WHERE `id` = %d AND `uid` = %d LIMIT 1",
//			intval($item['contact-id']),
//			intval($item['uid'])
//		);
//		if(! count($r))
//			return;
//		if(! $r[0]['self'])
//			$remote_owner = $r[0];
//	}

	// this represents the post owner on this system. 

//	$r = q("SELECT `contact`.*, `user`.`nickname` FROM `contact` LEFT JOIN `user` ON `contact`.`uid` = `user`.`uid`
//		WHERE `contact`.`self` = 1 AND `contact`.`uid` = %d LIMIT 1",
//		intval($owner_uid)
//	);
//	if(count($r))
//		$owner = $r[0];

//	if(! $owner) {
//		logger('like: no owner');
//		return;
//	}

//	if(! $remote_owner)
//		$remote_owner = $owner;


	// This represents the person posting

//	if((local_user()) && (local_user() == $owner_uid)) {
//		$contact = $owner;
//	}
//	else {
//		$r = q("SELECT * FROM `contact` WHERE `id` = %d AND `uid` = %d LIMIT 1",
//			intval($_SESSION['visitor_id']),
//			intval($owner_uid)
//		);
//		if(count($r))
//			$contact = $r[0];
//	}
//	if(! $contact) {
//		return;
//	}


	$r = q("SELECT * FROM item WHERE verb = '%s' AND ( item_restrict & %d )
		AND owner_xchan = '%s' AND ( parent = %d OR thr_parent = '%s') LIMIT 1",
		dbesc($activity),
		intval(ITEM_DELETED),
		dbesc($remote_owner),
		intval($item_id),
		dbesc($item['uri'])
	);
	if($r) {
		$like_item = $r[0];

		// Already liked/disliked it, delete it

		$r = q("UPDATE item SET item_restrict = ( item_restrict ^ %d ), changed = '%s' WHERE id = %d LIMIT 1",
			intval(ITEM_DELETED),
			dbesc(datetime_convert()),
			intval($like_item['id'])
		);

		proc_run('php',"include/notifier.php","like",$like_item['id']);
		return;
	}



	$uri = item_message_id();

	$post_type = (($item['resource_id'] === 'photo') ? $t('photo') : t('status'));

	$links = array(array('rel' => 'alternate','type' => 'text/html', 
		'href' => z_root() . '/display/' . $item['uri']));
	$objtype = (($item['resource_id'] === 'photo') ? ACTIVITY_OBJ_PHOTO : ACTIVITY_OBJ_NOTE ); 

	$body = $item['body'];

	$obj = json_encode(array(
		'type' => $objtype,
		'id'   => $item['uri'],
		'link' => $links,
		'title' => $item['title'],
		'content' => $item['body']
	));

	if($verb === 'like')
		$bodyverb = t('%1$s likes %2$s\'s %3$s');
	if($verb === 'dislike')
		$bodyverb = t('%1$s doesn\'t like %2$s\'s %3$s');

	if(! isset($bodyverb))
			return; 

	$item_flags = ITEM_ORIGIN;
	if($item['item_flags'] & ITEM_WALL)
		$item_flags |= ITEM_WALL;
	

	$arr = array();

	$arr['uri']          = $uri;
	$arr['uid']          = $owner_uid;
	$arr['item_flags']   = $item_flags;
	$arr['parent']       = $item['id'];
	$arr['parent_uri']   = $item['uri'];
	$arr['thr_parent']   = $item['uri'];
	$arr['owner_xchan']  = $remote_owner;
	$arr['author_xchan'] = $observer['xchan_hash'];

	
	$ulink = '[url=' . $remote_owner['xchan_url'] . ']' . $remote_owner['xchan_name'] . '[/url]';
	$alink = '[url=' . $observer['xchan_url'] . ']' . $observer['xchan_name'] . '[/url]';
	$plink = '[url=' . $a->get_baseurl() . '/display/' . $item['uri'] . ']' . $post_type . '[/url]';
	$arr['body'] =  sprintf( $bodyverb, $ulink, $alink, $plink );

	$arr['verb'] = $activity;
	$arr['obj_type'] = $objtype;
	$arr['object'] = $obj;

	$arr['allow_cid'] = $item['allow_cid'];
	$arr['allow_gid'] = $item['allow_gid'];
	$arr['deny_cid'] = $item['deny_cid'];
	$arr['deny_gid'] = $item['deny_gid'];


	$post_id = item_store($arr);	

	$arr['id'] = $post_id;

	call_hooks('post_local_end', $arr);

	proc_run('php',"include/notifier.php","like","$post_id");

	killme();
}


