<?php

namespace Zotlabs\Update;

class _1161 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) { 
		$r1 = q("CREATE TABLE \"iconfig\" (
  \"id\" serial NOT NULL,
  \"iid\" bigint NOT NULL DEFAULT '0',
  \"cat\" text NOT NULL DEFAULT '',
  \"k\" text NOT NULL DEFAULT '',
  \"v\" text NOT NULL DEFAULT '',
  PRIMARY_KEY(\"id\")
) ");
$r2 = q("create index \"iconfig_iid\" on iconfig (\"iid\") ");;
$r3 = q("create index \"iconfig_cat\" on iconfig (\"cat\") ");
$r4 = q("create index \"iconfig_k\" on iconfig (\"k\") ");
	$r = $r1 && $r2 && $r3 && $r4;
	}
	else {
		$r = q("CREATE TABLE IF NOT EXISTS `iconfig` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `iid` int(11) NOT NULL DEFAULT '0',
  `cat` char(255) NOT NULL DEFAULT '',
  `k` char(255) NOT NULL DEFAULT '',
  `v` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `iid` (`iid`),
  KEY `cat` (`cat`),
  KEY `k` (`k`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ");

	}

    if($r)
        return UPDATE_SUCCESS;
    return UPDATE_FAILED;
}


}