<?php

namespace Zotlabs\Update;

class _1177 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$r1 = q("alter table event add cal_id bigint NOT NULL DEFAULT '0'");
		$r2 = q("create index \"event_cal_idx\" on event (\"cal_id\") "); 

		$r3 = q("CREATE TABLE \"cal\" (
			\"cal_id\" serial  NOT NULL,
		 	\"cal_aid\" bigint NOT NULL DEFAULT '0',
		 	\"cal_uid\" bigint NOT NULL DEFAULT '0',
		 	\"cal_hash\" text NOT NULL,
			\"cal_name\" text NOT NULL,
			\"uri\" text NOT NULL,
			\"logname\" text NOT NULL,
			\"pass\" text NOT NULL,
			\"ctag\" text NOT NULL,
			\"synctoken\" text NOT NULL,
			\"cal_types\" text NOT NULL,
			PRIMARY KEY (\"cal_id\") ");
		$r4 = q("create index \"cal_hash_idx\" on cal (\"cal_hash\") ");
		$r5 = q("create index \"cal_name_idx\" on cal (\"cal_name\") ");
		$r6 = q("create index \"cal_types_idx\" on cal (\"cal_types\") ");
		$r7 = q("create index \"cal_aid_idx\" on cal (\"cal_aid\") ");
		$r8 = q("create index \"cal_uid_idx\" on cal (\"cal_uid\") ");
		$r = $r1 && $r2 && $r3 && $r4 && $r5 && $r6 && $r7 && $r8;
	}
	else {
		$r1 = q("alter table event add cal_id int(10) unsigned NOT NULL DEFAULT '0', 
			add index ( cal_id ) ");

		$r2 = q("CREATE TABLE IF NOT EXISTS `cal` (
			`cal_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`cal_aid` int(10) unsigned NOT NULL DEFAULT '0',
			`cal_uid` int(10) unsigned NOT NULL DEFAULT '0',
			`cal_hash` varchar(255) NOT NULL DEFAULT '',
			`cal_name` varchar(255) NOT NULL DEFAULT '',
			`uri` varchar(255) NOT NULL DEFAULT '',
			`logname` varchar(255) NOT NULL DEFAULT '',
			`pass` varchar(255) NOT NULL DEFAULT '',
			`ctag` varchar(255) NOT NULL DEFAULT '',
			`synctoken` varchar(255) NOT NULL DEFAULT '',
			`cal_types` varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY (`cal_id`),
			KEY `cal_aid` (`cal_aid`),
			KEY `cal_uid` (`cal_uid`),
			KEY `cal_hash` (`cal_hash`),
			KEY `cal_name` (`cal_name`),
			KEY `cal_types` (`cal_types`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ");

		$r = $r1 && $r2;
	}

    if($r)
        return UPDATE_SUCCESS;
    return UPDATE_FAILED;
}



}