<?php

namespace Zotlabs\Update;

class _1098 {
function run() {
	$r = q("ALTER TABLE `channel` CHANGE `channel_r_stream` `channel_r_stream` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0'");
	$r2 = q("ALTER TABLE `channel` CHANGE `channel_r_profile` `channel_r_profile` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0'");
	$r3 = q("ALTER TABLE `channel` CHANGE `channel_r_photos` `channel_r_photos` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0'");
	$r4 = q("ALTER TABLE `channel` CHANGE `channel_r_abook` `channel_r_abook` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0'");
	$r4 = q("ALTER TABLE `channel` CHANGE `channel_w_stream` `channel_w_stream` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0'");
	$r5 = q("ALTER TABLE `channel` CHANGE `channel_w_wall` `channel_w_wall` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0'");
	$r6 = q("ALTER TABLE `channel` CHANGE `channel_w_tagwall` `channel_w_tagwall` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0'");
	$r7 = q("ALTER TABLE `channel` CHANGE `channel_w_comment` `channel_w_comment` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0'");
	$r8 = q("ALTER TABLE `channel` CHANGE `channel_w_mail` `channel_w_mail` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0'");
	$r9 = q("ALTER TABLE `channel` CHANGE `channel_w_photos` `channel_w_photos` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0'");
	$r10 = q("ALTER TABLE `channel` CHANGE `channel_w_chat` `channel_w_chat` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0'");
	$r11 = q("ALTER TABLE `channel` CHANGE `channel_a_delegate` `channel_a_delegate` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0'");
	if($r && $r2 && $r3 && $r3 && $r5 && $r6 && $r7 && $r8 && $r9 && $r9 && $r10 && $r11)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}