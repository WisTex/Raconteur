<?php /** @file */

function update_channels_total_stat() {
	$r = q("select count(channel_id) as channels_total from channel left join account on account_id = channel_account_id
			where account_flags = 0 ");
	if($r) {
		$channels_total_stat = intval($r[0]['channels_total']);
		set_config('system','channels_total_stat',$channels_total_stat);
	} else {
		set_config('system','channels_total_stat',0);
	}
}

function update_channels_active_halfyear_stat() {
	$r = q("select channel_id from channel left join account on account_id = channel_account_id
			where account_flags = 0 and account_lastlog > %s - INTERVAL %s",
		db_utcnow(), db_quoteinterval('6 MONTH')
	);
	if($r) {
		set_config('system','channels_active_halfyear_stat',count($r));
	}
	else {
		set_config('system','channels_active_halfyear_stat','0');
	}
}

function update_channels_active_monthly_stat() {
	$r = q("select channel_id from channel left join account on account_id = channel_account_id
			where account_flags = 0 and account_lastlog > %s - INTERVAL %s",
		db_utcnow(), db_quoteinterval('1 MONTH')
	);
	if($r) {
		set_config('system','channels_active_monthly_stat',count($r));
	}
	else {
		set_config('system','channels_active_monthly_stat','0');
	}
}

function update_local_posts_stat() {
	$posts = q("SELECT COUNT(*) AS local_posts FROM item WHERE item_wall = 1 and id = parent");
	if (is_array($posts)) {
		$local_posts_stat = intval($posts[0]["local_posts"]);
		set_config('system','local_posts_stat',$local_posts_stat);
	} else {
		set_config('system','local_posts_stat',0);
	}
}

function update_local_comments_stat() {
   $posts = q("SELECT COUNT(*) AS local_posts FROM item WHERE item_wall = 1 and id != parent");
    if (!is_array($posts))
        $local_posts = 0;
    else
        $local_posts = $posts[0]["local_posts"];

    set_config('system','local_comments_stat', $local_posts);
}