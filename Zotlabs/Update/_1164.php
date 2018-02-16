<?php

namespace Zotlabs\Update;

class _1164 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$r1 = q("CREATE TABLE \"abconfig\" (
			\"id\" serial  NOT NULL,
		 	\"chan\" text NOT NULL,
			\"xchan\" text NOT NULL,
			\"cat\" text NOT NULL,
			\"k\" text NOT NULL,
			\"v\" text NOT NULL,
			PRIMARY KEY (\"id\") ");
		$r2 = q("create index \"abconfig_chan\" on abconfig (\"chan\") ");
		$r3 = q("create index \"abconfig_xchan\" on abconfig (\"xchan\") ");
		$r4 = q("create index \"abconfig_cat\" on abconfig (\"cat\") ");
		$r5 = q("create index \"abconfig_k\" on abconfig (\"k\") ");
		$r = $r1 && $r2 && $r3 && $r4 && $r5;
	}
	else {
		$r = q("CREATE TABLE IF NOT EXISTS `abconfig` (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`chan` char(255) NOT NULL DEFAULT '',
			`xchan` char(255) NOT NULL DEFAULT '',
			`cat` char(255) NOT NULL DEFAULT '',
			`k` char(255) NOT NULL DEFAULT '',
			`v` mediumtext NOT NULL,
			PRIMARY KEY (`id`),
			KEY `chan` (`chan`),
			KEY `xchan` (`xchan`),
			KEY `cat` (`cat`),
			KEY `k` (`k`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ");

	}
    if($r)
        return UPDATE_SUCCESS;
    return UPDATE_FAILED;
}


}