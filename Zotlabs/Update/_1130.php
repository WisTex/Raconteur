<?php

namespace Zotlabs\Update;

class _1130 {
function run() {
	$myperms = PERMS_R_STREAM|PERMS_R_PROFILE|PERMS_R_PHOTOS|PERMS_R_ABOOK
		|PERMS_W_STREAM|PERMS_W_WALL|PERMS_W_COMMENT|PERMS_W_MAIL|PERMS_W_CHAT
		|PERMS_R_STORAGE|PERMS_R_PAGES|PERMS_W_LIKE;

	$r = q("select abook_channel, abook_my_perms from abook where (abook_flags & %d) and abook_my_perms != 0",
		intval(ABOOK_FLAG_SELF)
	);
	if($r) {
		foreach($r as $rr) {
			set_pconfig($rr['abook_channel'],'system','autoperms',$rr['abook_my_perms']);
		}
	}
	$r = q("update abook set abook_my_perms = %d where (abook_flags & %d) and abook_my_perms = 0",
		intval($myperms),
		intval(ABOOK_FLAG_SELF)
	);		

	return UPDATE_SUCCESS;
}


}