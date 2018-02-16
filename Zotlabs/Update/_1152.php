<?php

namespace Zotlabs\Update;

class _1152 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) { 

		$r1 = q("CREATE TABLE IF NOT EXISTS \"dreport\" (
  \"dreport_id\" serial NOT NULL,
  \"dreport_channel\" int(11) NOT NULL DEFAULT '0',
  \"dreport_mid\" char(255) NOT NULL DEFAULT '',
  \"dreport_site\" char(255) NOT NULL DEFAULT '',
  \"dreport_recip\" char(255) NOT NULL DEFAULT '',
  \"dreport_result\" char(255) NOT NULL DEFAULT '',
  \"dreport_time\" timestamp NOT NULL DEFAULT '0001-01-01 00:00:00',
  \"dreport_xchan\" char(255) NOT NULL DEFAULT '',
  PRIMARY KEY (\"dreport_id\") ");

	$r2 = q("create index \"dreport_mid\" on dreport (\"dreport_mid\") ");
	$r3 = q("create index \"dreport_site\" on dreport (\"dreport_site\") ");
	$r4 = q("create index \"dreport_time\" on dreport (\"dreport_time\") ");
	$r5 = q("create index \"dreport_xchan\" on dreport (\"dreport_xchan\") ");
	$r6 = q("create index \"dreport_channel\" on dreport (\"dreport_channel\") ");

	$r = $r1 && $r2 && $r3 && $r4 && $r5 && $r6;

	}
	else {
		$r = q("CREATE TABLE IF NOT EXISTS `dreport` (
  `dreport_id` int(11) NOT NULL AUTO_INCREMENT,
  `dreport_channel` int(11) NOT NULL DEFAULT '0',
  `dreport_mid` char(255) NOT NULL DEFAULT '',
  `dreport_site` char(255) NOT NULL DEFAULT '',
  `dreport_recip` char(255) NOT NULL DEFAULT '',
  `dreport_result` char(255) NOT NULL DEFAULT '',
  `dreport_time` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `dreport_xchan` char(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`dreport_id`),
  KEY `dreport_mid` (`dreport_mid`),
  KEY `dreport_site` (`dreport_site`),
  KEY `dreport_time` (`dreport_time`),
  KEY `dreport_xchan` (`dreport_xchan`),
  KEY `dreport_channel` (`dreport_channel`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ");

	}

    if($r)
        return UPDATE_SUCCESS;
    return UPDATE_FAILED;

}


}