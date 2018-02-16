<?php

namespace Zotlabs\Update;

class _1006 {
function run() {

	$r = q("CREATE TABLE IF NOT EXISTS `xprof` (
  `xprof_hash` char(255) NOT NULL,
  `xprof_desc` char(255) NOT NULL DEFAULT '',
  `xprof_dob` char(12) NOT NULL DEFAULT '',
  `xprof_gender` char(255) NOT NULL DEFAULT '',
  `xprof_marital` char(255) NOT NULL DEFAULT '',
  `xprof_sexual` char(255) NOT NULL DEFAULT '',
  `xprof_locale` char(255) NOT NULL DEFAULT '',
  `xprof_region` char(255) NOT NULL DEFAULT '',
  `xprof_postcode` char(32) NOT NULL DEFAULT '',
  `xprof_country` char(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`xprof_hash`),
  KEY `xprof_desc` (`xprof_desc`),
  KEY `xprof_dob` (`xprof_dob`),
  KEY `xprof_gender` (`xprof_gender`),
  KEY `xprof_marital` (`xprof_marital`),
  KEY `xprof_sexual` (`xprof_sexual`),
  KEY `xprof_locale` (`xprof_locale`),
  KEY `xprof_region` (`xprof_region`),
  KEY `xprof_postcode` (`xprof_postcode`),
  KEY `xprof_country` (`xprof_country`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");

	$r2 = q("CREATE TABLE IF NOT EXISTS `xtag` (
  `xtag_hash` char(255) NOT NULL,
  `xtag_term` char(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`xtag_hash`),
  KEY `xtag_term` (`xtag_term`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");

	if($r && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}