
CREATE TABLE IF NOT EXISTS `abconfig` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chan` int(10) unsigned NOT NULL DEFAULT 0 ,
  `xchan` char(255) NOT NULL DEFAULT '',
  `cat` char(255) NOT NULL DEFAULT '',
  `k` char(255) NOT NULL DEFAULT '',
  `v` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `chan_xchan` (`chan`, `xchan`(191)),
  KEY `cat` (`cat`(191)),
  KEY `k` (`k`(191))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `abook` (
  `abook_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `abook_account` int(10) unsigned NOT NULL DEFAULT 0 ,
  `abook_channel` int(10) unsigned NOT NULL DEFAULT 0 ,
  `abook_xchan` varchar(255) NOT NULL DEFAULT '',
  `abook_alias` varchar(255) NOT NULL DEFAULT '',
  `abook_closeness` tinyint(3) unsigned NOT NULL DEFAULT 99,
  `abook_created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `abook_updated` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `abook_connected` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `abook_dob` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `abook_flags` int(11) NOT NULL DEFAULT 0 ,
  `abook_censor` int(11) NOT NULL DEFAULT 0 ,
  `abook_blocked` tinyint(4) NOT NULL DEFAULT 0 ,
  `abook_ignored` tinyint(4) NOT NULL DEFAULT 0 ,
  `abook_hidden` tinyint(4) NOT NULL DEFAULT 0 ,
  `abook_archived` tinyint(4) NOT NULL DEFAULT 0 ,
  `abook_pending` tinyint(4) NOT NULL DEFAULT 0 ,
  `abook_unconnected` tinyint(4) NOT NULL DEFAULT 0 ,
  `abook_self` tinyint(4) NOT NULL DEFAULT 0 ,
  `abook_rself` tinyint(4) NOT NULL DEFAULT 0 ,
  `abook_feed` tinyint(4) NOT NULL DEFAULT 0 ,
  `abook_not_here` tinyint(4) NOT NULL DEFAULT 0 ,
  `abook_profile` varchar(255) NOT NULL DEFAULT '',
  `abook_incl` text NOT NULL,
  `abook_excl` text NOT NULL,
  `abook_instance` text NOT NULL,
  PRIMARY KEY (`abook_id`),
  KEY `abook_account` (`abook_account`),
  KEY `abook_channel` (`abook_channel`),
  KEY `abook_xchan` (`abook_xchan`(191)),
  KEY `abook_alias` (`abook_alias`(191)),
  KEY `abook_my_perms` (`abook_my_perms`),
  KEY `abook_their_perms` (`abook_their_perms`),
  KEY `abook_closeness` (`abook_closeness`),
  KEY `abook_created` (`abook_created`),
  KEY `abook_updated` (`abook_updated`),
  KEY `abook_flags` (`abook_flags`),
  KEY `abook_profile` (`abook_profile`(191)),
  KEY `abook_dob` (`abook_dob`),
  KEY `abook_connected` (`abook_connected`),
  KEY `abook_blocked` (`abook_blocked`),
  KEY `abook_ignored` (`abook_ignored`),
  KEY `abook_hidden` (`abook_hidden`),
  KEY `abook_archived` (`abook_archived`),
  KEY `abook_pending` (`abook_pending`),
  KEY `abook_unconnected` (`abook_unconnected`),
  KEY `abook_self` (`abook_self`),
  KEY `abook_not_here` (`abook_not_here`),
  KEY `abook_feed` (`abook_feed`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `account` (
  `account_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_parent` int(10) unsigned NOT NULL DEFAULT 0 ,
  `account_default_channel` int(10) unsigned NOT NULL DEFAULT 0 ,
  `account_salt` varchar(64) NOT NULL DEFAULT '',
  `account_password` varchar(255) NOT NULL DEFAULT '',
  `account_email` varchar(255) NOT NULL DEFAULT '',
  `account_external` varchar(255) NOT NULL DEFAULT '',
  `account_language` varchar(16) NOT NULL DEFAULT 'en',
  `account_created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `account_lastlog` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `account_flags` int(10) unsigned NOT NULL DEFAULT 0 ,
  `account_roles` int(10) unsigned NOT NULL DEFAULT 0 ,
  `account_reset` varchar(255) NOT NULL DEFAULT '',
  `account_expires` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `account_expire_notified` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `account_service_class` varchar(255) NOT NULL DEFAULT '',
  `account_level` int(10) unsigned NOT NULL DEFAULT 0 ,
  `account_password_changed` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  PRIMARY KEY (`account_id`),
  KEY `account_email` (`account_email`(191)),
  KEY `account_service_class` (`account_service_class`(191)),
  KEY `account_parent` (`account_parent`),
  KEY `account_flags` (`account_flags`),
  KEY `account_roles` (`account_roles`),
  KEY `account_lastlog` (`account_lastlog`),
  KEY `account_expires` (`account_expires`),
  KEY `account_default_channel` (`account_default_channel`),
  KEY `account_external` (`account_external`(191)),
  KEY `account_level` (`account_level`),
  KEY `account_password_changed` (`account_password_changed`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `addon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aname` varchar(255) NOT NULL DEFAULT '',
  `version` varchar(255) NOT NULL DEFAULT '',
  `installed` tinyint(1) NOT NULL DEFAULT 0 ,
  `hidden` tinyint(1) NOT NULL DEFAULT 0 ,
  `tstamp` bigint(20) NOT NULL DEFAULT 0 ,
  `plugin_admin` tinyint(1) NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`id`),
  KEY `hidden` (`hidden`),
  KEY `aname` (`aname`(191)),
  KEY `installed` (`installed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `app` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `app_id` varchar(255) NOT NULL DEFAULT '',
  `app_sig` varchar(255) NOT NULL DEFAULT '',
  `app_author` varchar(255) NOT NULL DEFAULT '',
  `app_name` varchar(255) NOT NULL DEFAULT '',
  `app_desc` text NOT NULL,
  `app_url` varchar(255) NOT NULL DEFAULT '',
  `app_photo` varchar(255) NOT NULL DEFAULT '',
  `app_version` varchar(255) NOT NULL DEFAULT '',
  `app_channel` int(11) NOT NULL DEFAULT 0 ,
  `app_addr` varchar(255) NOT NULL DEFAULT '',
  `app_price` varchar(255) NOT NULL DEFAULT '',
  `app_page` varchar(255) NOT NULL DEFAULT '',
  `app_requires` varchar(512) NOT NULL DEFAULT '',
  `app_deleted` int(11) NOT NULL DEFAULT 0 ,
  `app_system` int(11) NOT NULL DEFAULT 0 ,
  `app_plugin` varchar(255) NOT NULL DEFAULT '',
  `app_options` int(11) NOT NULL DEFAULT 0 ,
  `app_created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `app_edited` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  PRIMARY KEY (`id`),
  KEY `app_id` (`app_id`(191)),
  KEY `app_name` (`app_name`(191)),
  KEY `app_url` (`app_url`(191)),
  KEY `app_photo` (`app_photo`(191)),
  KEY `app_version` (`app_version`(191)),
  KEY `app_channel` (`app_channel`),
  KEY `app_price` (`app_price`(191)),
  KEY `app_created` (`app_created`),
  KEY `app_deleted` (`app_deleted`),
  KEY `app_system` (`app_system`),
  KEY `app_edited` (`app_edited`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS `atoken` (
  `atoken_id` int(11) NOT NULL AUTO_INCREMENT,
  `atoken_guid` varchar(255) NOT NULL DEFAULT '',
  `atoken_aid` int(11) NOT NULL DEFAULT 0 ,
  `atoken_uid` int(11) NOT NULL DEFAULT 0 ,
  `atoken_name` varchar(255) NOT NULL DEFAULT '',
  `atoken_token` varchar(255) NOT NULL DEFAULT '',
  `atoken_expires` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  PRIMARY KEY (`atoken_id`),
  KEY `atoken_guid` (`atoken_guid`(191)),
  KEY `atoken_aid` (`atoken_aid`),
  KEY `atoken_uid` (`atoken_uid`),
  KEY `atoken_name` (`atoken_name`(191)),
  KEY `atoken_token` (`atoken_token`(191)),
  KEY `atoken_expires` (`atoken_expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attach` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `uid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `hash` varchar(255) NOT NULL DEFAULT '',
  `creator` varchar(255) NOT NULL DEFAULT '',
  `filename` varchar(4095) NOT NULL DEFAULT '',
  `filetype` varchar(255) NOT NULL DEFAULT '',
  `filesize` int(10) unsigned NOT NULL DEFAULT 0 ,
  `revision` int(10) unsigned NOT NULL DEFAULT 0 ,
  `folder` varchar(255) NOT NULL DEFAULT '',
  `flags` int(10) unsigned NOT NULL DEFAULT 0 ,
  `is_dir` tinyint(1) NOT NULL DEFAULT 0 ,
  `is_photo` tinyint(1) NOT NULL DEFAULT 0 ,
  `os_storage` tinyint(1) NOT NULL DEFAULT 0 ,
  `os_path` mediumtext NOT NULL,
  `display_path` mediumtext NOT NULL,
  `content` longblob NOT NULL,
  `created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `edited` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `allow_cid` mediumtext NOT NULL,
  `allow_gid` mediumtext NOT NULL,
  `deny_cid` mediumtext NOT NULL,
  `deny_gid` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `aid` (`aid`),
  KEY `uid` (`uid`),
  KEY `hash` (`hash`(191)),
  KEY `filename` (`filename`(191)),
  KEY `filetype` (`filetype`(191)),
  KEY `filesize` (`filesize`),
  KEY `created` (`created`),
  KEY `edited` (`edited`),
  KEY `revision` (`revision`),
  KEY `folder` (`folder`(191)),
  KEY `flags` (`flags`),
  KEY `creator` (`creator`(191)),
  KEY `is_dir` (`is_dir`),
  KEY `is_photo` (`is_photo`),
  KEY `os_storage` (`os_storage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `auth_codes` (
  `id` varchar(16384) NOT NULL DEFAULT '',
  `client_id` varchar(255) NOT NULL DEFAULT '',
  `redirect_uri` varchar(512) NOT NULL DEFAULT '',
  `expires` int(11) NOT NULL DEFAULT 0 ,
  `auth_scope` varchar(512) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `block` (
  `block_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `block_channel_id` int(10) UNSIGNED NOT NULL,
  `block_entity` varchar(1023) NOT NULL,
  `block_type` int(11) NOT NULL,
  `block_comment` mediumtext NOT NULL,
  PRIMARY KEY (`block_id`),
  KEY `block_channel_id` (`block_channel_id`),
  KEY `block_entity` (`block_entity`(191)),
  KEY `block_type` (`block_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cache` (
  `k` char(512) NOT NULL DEFAULT '',
  `v` text NOT NULL,
  `updated` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  PRIMARY KEY (`k`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cal` (
  `cal_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cal_aid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `cal_uid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `cal_hash` varchar(255) NOT NULL DEFAULT '',
  `cal_name` varchar(255) NOT NULL DEFAULT '',
  `uri` varchar(1023) NOT NULL DEFAULT '',
  `logname` varchar(255) NOT NULL DEFAULT '',
  `pass` varchar(255) NOT NULL DEFAULT '',
  `ctag` varchar(255) NOT NULL DEFAULT '',
  `synctoken` varchar(255) NOT NULL DEFAULT '',
  `cal_types` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`cal_id`),
  KEY `cal_aid` (`cal_aid`),
  KEY `cal_uid` (`cal_uid`),
  KEY `cal_hash` (`cal_hash`(191)),
  KEY `cal_name` (`cal_name`(191)),
  KEY `cal_types` (`cal_types`(191))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `channel` (
  `channel_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `channel_account_id` int(10) unsigned NOT NULL DEFAULT 0 ,
  `channel_primary` tinyint(1) unsigned NOT NULL DEFAULT 0 ,
  `channel_name` varchar(255) NOT NULL DEFAULT '',
  `channel_parent` varchar(255) NOT NULL DEFAULT '',
  `channel_address` varchar(255) NOT NULL DEFAULT '',
  `channel_guid` varchar(255) NOT NULL DEFAULT '',
  `channel_guid_sig` text NOT NULL,
  `channel_hash` varchar(255) NOT NULL DEFAULT '',
  `channel_timezone` varchar(255) NOT NULL DEFAULT 'UTC',
  `channel_location` varchar(255) NOT NULL DEFAULT '',
  `channel_theme` varchar(255) NOT NULL DEFAULT '',
  `channel_startpage` varchar(255) NOT NULL DEFAULT '',
  `channel_pubkey` text NOT NULL,
  `channel_prvkey` text NOT NULL,
  `channel_notifyflags` int(10) unsigned NOT NULL DEFAULT 65535,
  `channel_pageflags` int(10) unsigned NOT NULL DEFAULT 0 ,
  `channel_dirdate` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `channel_lastpost` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `channel_deleted` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `channel_active` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `channel_max_anon_mail` int(10) unsigned NOT NULL DEFAULT 10,
  `channel_max_friend_req` int(10) unsigned NOT NULL DEFAULT 10,
  `channel_expire_days` int(11) NOT NULL DEFAULT 0 ,
  `channel_passwd_reset` varchar(255) NOT NULL DEFAULT '',
  `channel_default_group` varchar(255) NOT NULL DEFAULT '',
  `channel_allow_cid` mediumtext NOT NULL,
  `channel_allow_gid` mediumtext NOT NULL,
  `channel_deny_cid` mediumtext NOT NULL,
  `channel_deny_gid` mediumtext NOT NULL,
  `channel_removed` tinyint(1) NOT NULL DEFAULT 0 ,
  `channel_system` tinyint(1) NOT NULL DEFAULT 0 ,
  `channel_moved` varchar(255) NOT NULL DEFAULT '',
  `channel_password` varchar(255) NOT NULL,
  `channel_salt` varchar(255) NOT NULL,
  PRIMARY KEY (`channel_id`),
  KEY `channel_address` (`channel_address`(191)),
  KEY `channel_account_id` (`channel_account_id`),
  KEY `channel_primary` (`channel_primary`),
  KEY `channel_name` (`channel_name`(191)),
  KEY `channel_parent` (`channel_parent`(191)),
  KEY `channel_timezone` (`channel_timezone`(191)),
  KEY `channel_location` (`channel_location`(191)),
  KEY `channel_theme` (`channel_theme`(191),
  KEY `channel_notifyflags` (`channel_notifyflags`),
  KEY `channel_pageflags` (`channel_pageflags`),
  KEY `channel_max_anon_mail` (`channel_max_anon_mail`),
  KEY `channel_max_friend_req` (`channel_max_friend_req`),
  KEY `channel_default_gid` (`channel_default_group`),
  KEY `channel_guid` (`channel_guid`(191)),
  KEY `channel_hash` (`channel_hash`(191)),
  KEY `channel_expire_days` (`channel_expire_days`),
  KEY `channel_deleted` (`channel_deleted`),
  KEY `channel_active` (`channel_active`),
  KEY `channel_dirdate` (`channel_dirdate`),
  KEY `channel_removed` (`channel_removed`),
  KEY `channel_system` (`channel_system`),
  KEY `channel_lastpost` (`channel_lastpost`),
  KEY `channel_moved` (`channel_moved`(191))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chat` (
  `chat_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chat_room` int(10) unsigned NOT NULL DEFAULT 0 ,
  `chat_xchan` varchar(255) NOT NULL DEFAULT '',
  `chat_text` mediumtext NOT NULL,
  `created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  PRIMARY KEY (`chat_id`),
  KEY `chat_room` (`chat_room`),
  KEY `chat_xchan` (`chat_xchan`(191)),
  KEY `created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chatpresence` (
  `cp_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cp_room` int(10) unsigned NOT NULL DEFAULT 0 ,
  `cp_xchan` varchar(255) NOT NULL DEFAULT '',
  `cp_last` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `cp_status` varchar(255) NOT NULL DEFAULT '',
  `cp_client` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`cp_id`),
  KEY `cp_room` (`cp_room`),
  KEY `cp_xchan` (`cp_xchan`(191)),
  KEY `cp_last` (`cp_last`),
  KEY `cp_status` (`cp_status`(191))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chatroom` (
  `cr_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cr_aid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `cr_uid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `cr_name` varchar(255) NOT NULL DEFAULT '',
  `cr_created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `cr_edited` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `cr_expire` int(10) unsigned NOT NULL DEFAULT 0 ,
  `allow_cid` mediumtext NOT NULL,
  `allow_gid` mediumtext NOT NULL,
  `deny_cid` mediumtext NOT NULL,
  `deny_gid` mediumtext NOT NULL,
  PRIMARY KEY (`cr_id`),
  KEY `cr_aid` (`cr_aid`),
  KEY `cr_uid` (`cr_uid`),
  KEY `cr_name` (`cr_name`(191)),
  KEY `cr_created` (`cr_created`),
  KEY `cr_edited` (`cr_edited`),
  KEY `cr_expire` (`cr_expire`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `clients` (
  `client_id` varchar(255) NOT NULL DEFAULT '',
  `pw` varchar(191) NOT NULL DEFAULT '',
  `redirect_uri` varchar(200) NOT NULL DEFAULT '',
  `clname` text,
  `icon` text,
  `uid` int(11) NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`client_id`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cat` varchar(255) NOT NULL DEFAULT '',
  `k` varchar(255) NOT NULL DEFAULT '',
  `v` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `access` (`cat`(191),`k`(191))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dreport` (
  `dreport_id` int(11) NOT NULL AUTO_INCREMENT,
  `dreport_channel` int(11) NOT NULL DEFAULT 0 ,
  `dreport_mid` varchar(255) NOT NULL DEFAULT '',
  `dreport_site` varchar(255) NOT NULL DEFAULT '',
  `dreport_recip` varchar(255) NOT NULL DEFAULT '',
  `dreport_name` varchar(255) NOT NULL DEFAULT '',
  `dreport_result` varchar(255) NOT NULL DEFAULT '',
  `dreport_time` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `dreport_xchan` varchar(255) NOT NULL DEFAULT '',
  `dreport_queue` varchar(255) NOT NULL DEFAULT '',
  `dreport_log` text NOT NULL,
  PRIMARY KEY (`dreport_id`),
  KEY `dreport_mid` (`dreport_mid`(191)),
  KEY `dreport_site` (`dreport_site`(191)),
  KEY `dreport_time` (`dreport_time`),
  KEY `dreport_xchan` (`dreport_xchan`(191)),
  KEY `dreport_queue` (`dreport_queue`(191)),
  KEY `dreport_channel` (`dreport_channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `event` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `uid` int(11) NOT NULL DEFAULT 0 ,
  `cal_id` int(11) unsigned NOT NULL DEFAULT 0 ,
  `event_xchan` varchar(255) NOT NULL DEFAULT '',
  `event_hash` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `edited` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `dtstart` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `dtend` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `summary` text NOT NULL,
  `description` text NOT NULL,
  `location` text NOT NULL,
  `etype` varchar(255) NOT NULL DEFAULT '',
  `nofinish` tinyint(1) NOT NULL DEFAULT 0 ,
  `adjust` tinyint(1) NOT NULL DEFAULT 1,
  `dismissed` tinyint(1) NOT NULL DEFAULT 0 ,
  `allow_cid` mediumtext NOT NULL,
  `allow_gid` mediumtext NOT NULL,
  `deny_cid` mediumtext NOT NULL,
  `deny_gid` mediumtext NOT NULL,
  `event_status` varchar(255) NOT NULL DEFAULT '',
  `event_status_date` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `event_percent` smallint(6) NOT NULL DEFAULT 0 ,
  `event_repeat` text NOT NULL,
  `event_sequence` smallint(6) NOT NULL DEFAULT 0 ,
  `event_priority` smallint(6) NOT NULL DEFAULT 0 ,
  `event_vdata` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `cal_id` (`cal_id`),
  KEY `etype` (`etype`(191)),
  KEY `dtstart` (`dtstart`),
  KEY `dtend` (`dtend`),
  KEY `adjust` (`adjust`),
  KEY `nofinish` (`nofinish`),
  KEY `dismissed` (`dismissed`),
  KEY `aid` (`aid`),
  KEY `event_hash` (`event_hash`(191)),
  KEY `event_xchan` (`event_xchan`(191)),
  KEY `event_status` (`event_status`(191)),
  KEY `event_sequence` (`event_sequence`),
  KEY `event_priority` (`event_priority`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pgrp` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(255) NOT NULL DEFAULT '',
  `uid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `visible` tinyint(1) NOT NULL DEFAULT 0 ,
  `deleted` tinyint(1) NOT NULL DEFAULT 0 ,
  `gname` varchar(255) NOT NULL DEFAULT '',
  `rule` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `visible` (`visible`),
  KEY `deleted` (`deleted`),
  KEY `hash` (`hash`(191)),
  KEY `gname` (`gname`(191)),
  KEY `rule` (`rule`(191))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pgrp_member` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `gid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `xchan` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `gid` (`gid`),
  KEY `xchan` (`xchan`(191))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `hook` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hook` varchar(255) NOT NULL DEFAULT '',
  `file` varchar(255) NOT NULL DEFAULT '',
  `fn` varchar(255) NOT NULL DEFAULT '',
  `priority` smallint NOT NULL DEFAULT 0 ,
  `hook_version` int(11) NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`id`),
  KEY `hook` (`hook`(191)),
  KEY `priority` (`priority`),
  KEY `hook_version` (`hook_version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS `hubloc` (
  `hubloc_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hubloc_guid` varchar(255) NOT NULL DEFAULT '',
  `hubloc_guid_sig` text NOT NULL,
  `hubloc_id_url` varchar(255) NOT NULL DEFAULT '0',
  `hubloc_hash` varchar(255) NOT NULL DEFAULT '',
  `hubloc_addr` varchar(255) NOT NULL DEFAULT '',
  `hubloc_network` varchar(255) NOT NULL DEFAULT '',
  `hubloc_flags` int(10) unsigned NOT NULL DEFAULT 0 ,
  `hubloc_status` int(10) unsigned NOT NULL DEFAULT 0 ,
  `hubloc_url` varchar(255) NOT NULL DEFAULT '',
  `hubloc_url_sig` text NOT NULL,
  `hubloc_site_id` varchar(255) NOT NULL DEFAULT '',
  `hubloc_host` varchar(255) NOT NULL DEFAULT '',
  `hubloc_callback` varchar(255) NOT NULL DEFAULT '',
  `hubloc_connect` varchar(255) NOT NULL DEFAULT '',
  `hubloc_sitekey` text NOT NULL,
  `hubloc_updated` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `hubloc_connected` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `hubloc_primary` tinyint(1) NOT NULL DEFAULT 0 ,
  `hubloc_orphancheck` tinyint(1) NOT NULL DEFAULT 0 ,
  `hubloc_error` tinyint(1) NOT NULL DEFAULT 0 ,
  `hubloc_deleted` tinyint(1) NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`hubloc_id`),
  KEY `hubloc_url` (`hubloc_url`(191)),
  KEY `hubloc_site_id` (`hubloc_site_id`(191)),
  KEY `hubloc_guid` (`hubloc_guid`(191)),
  KEY `hubloc_id_url` (`hubloc_id_url`(191)),
  KEY `hubloc_hash` (`hubloc_hash`(191)),
  KEY `hubloc_flags` (`hubloc_flags`),
  KEY `hubloc_connect` (`hubloc_connect`(191)),
  KEY `hubloc_host` (`hubloc_host`(191)),
  KEY `hubloc_addr` (`hubloc_addr`(191)),
  KEY `hubloc_updated` (`hubloc_updated`),
  KEY `hubloc_connected` (`hubloc_connected`),
  KEY `hubloc_status` (`hubloc_status`),
  KEY `hubloc_network` (`hubloc_network`(191)),
  KEY `hubloc_primary` (`hubloc_primary`),
  KEY `hubloc_orphancheck` (`hubloc_orphancheck`),
  KEY `hubloc_deleted` (`hubloc_deleted`),
  KEY `hubloc_error` (`hubloc_error`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `iconfig` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `iid` int(11) NOT NULL DEFAULT 0 ,
  `cat` varchar(255) NOT NULL DEFAULT '',
  `k` varchar(255) NOT NULL DEFAULT '',
  `v` mediumtext NOT NULL,
  `sharing` int(11) NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`id`),
  KEY `iid` (`iid`),
  KEY `cat` (`cat`(191)),
  KEY `k` (`k`(191)),
  KEY `sharing` (`sharing`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `issue` (
  `issue_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `issue_created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `issue_updated` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `issue_assigned` varchar(255) NOT NULL DEFAULT '',
  `issue_priority` int(11) NOT NULL DEFAULT 0 ,
  `issue_status` int(11) NOT NULL DEFAULT 0 ,
  `issue_component` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`issue_id`),
  KEY `issue_created` (`issue_created`),
  KEY `issue_updated` (`issue_updated`),
  KEY `issue_assigned` (`issue_assigned`(191)),
  KEY `issue_priority` (`issue_priority`),
  KEY `issue_status` (`issue_status`),
  KEY `issue_component` (`issue_component`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mid` varchar(512) NOT NULL DEFAULT '',
  `uuid` varchar(255) NOT NULL DEFAULT '',
  `aid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `uid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `parent` int(10) unsigned NOT NULL DEFAULT 0 ,
  `parent_mid` varchar(512) NOT NULL DEFAULT '',
  `thr_parent` varchar(512) NOT NULL DEFAULT '',
  `item_level` int(10) unsigned NOT NULL DEFAULT 0,
  `created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `edited` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `expires` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `commented` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `received` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `changed` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `comments_closed` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `owner_xchan` varchar(255) NOT NULL DEFAULT '',
  `author_xchan` varchar(255) NOT NULL DEFAULT '',
  `source_xchan` varchar(255) NOT NULL DEFAULT '',
  `mimetype` varchar(255) NOT NULL DEFAULT '',
  `replyto` text NOT NULL,
  `title` text NOT NULL,
  `summary` mediumtext NOT NULL,
  `body` mediumtext NOT NULL,
  `html` mediumtext NOT NULL,
  `app` varchar(255) NOT NULL DEFAULT '',
  `lang` varchar(255) NOT NULL DEFAULT '',
  `revision` int(10) unsigned NOT NULL DEFAULT 0 ,
  `verb` varchar(255) NOT NULL DEFAULT '',
  `obj_type` varchar(255) NOT NULL DEFAULT '',
  `obj` text NOT NULL,
  `tgt_type` varchar(255) NOT NULL DEFAULT '',
  `target` text NOT NULL,
  `layout_mid` varchar(255) NOT NULL DEFAULT '',
  `postopts` text NOT NULL,
  `route` text NOT NULL,
  `llink` varchar(255) NOT NULL DEFAULT '',
  `plink` varchar(255) NOT NULL DEFAULT '',
  `resource_id` varchar(255) NOT NULL DEFAULT '',
  `resource_type` varchar(255) NOT NULL DEFAULT '',
  `attach` mediumtext NOT NULL,
  `sig` text NOT NULL,
  `location` varchar(255) NOT NULL DEFAULT '',
  `coord` varchar(255) NOT NULL DEFAULT '',
  `public_policy` varchar(255) NOT NULL DEFAULT '',
  `comment_policy` varchar(255) NOT NULL DEFAULT '',
  `allow_cid` mediumtext NOT NULL,
  `allow_gid` mediumtext NOT NULL,
  `deny_cid` mediumtext NOT NULL,
  `deny_gid` mediumtext NOT NULL,
  `item_restrict` int(11) NOT NULL DEFAULT 0 ,
  `item_flags` int(11) NOT NULL DEFAULT 0 ,
  `item_private` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_origin` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_unseen` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_starred` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_uplink` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_consensus` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_wall` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_thread_top` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_notshown` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_nsfw` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_relay` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_mentionsme` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_nocomment` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_obscured` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_verified` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_retained` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_rss` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_deleted` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_type` int(11) NOT NULL DEFAULT 0 ,
  `item_hidden` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_unpublished` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_delayed` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_pending_remove` tinyint(1) NOT NULL DEFAULT 0 ,
  `item_blocked` tinyint(1) NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`id`),
  KEY `parent` (`parent`),
  KEY `created` (`created`),
  KEY `edited` (`edited`),
  KEY `received` (`received`),
  KEY `uid_commented` (`uid`, `commented`),
  KEY `uid_created` (`uid`, `created`),
  KEY `uid_item_unseen` (`uid`, `item_unseen`),
  KEY `uid_item_type` (`uid`, `item_type`),
  KEY `uid_item_thread_top` (`uid`, `item_thread_top`),
  KEY `uid_item_blocked` (`uid`, `item_blocked`),
  KEY `uid_item_wall` (`uid`, `item_wall`),
  KEY `uid_item_starred` (`uid`, `item_starred`),
  KEY `uid_item_retained` (`uid`, `item_retained`),
  KEY `uid_item_private` (`uid`, `item_private`),
  KEY `uid_resource_type` (`uid`, `resource_type`),
  KEY `owner_xchan` (`owner_xchan`(191)),
  KEY `author_xchan` (`author_xchan`(191)),
  KEY `resource_id` (`resource_id`(191)),
  KEY `resource_type` (`resource_type`(191)),
  KEY `commented` (`commented`),
  KEY `verb` (`verb`(191)),
  KEY `obj_type` (`obj_type`(191)),
  KEY `llink` (`llink`(191)),
  KEY `expires` (`expires`),
  KEY `revision` (`revision`),
  KEY `mimetype` (`mimetype`(191)),
  KEY `mid` (`mid`(191)),
  KEY `uuid` (`uuid`(191)),
  KEY `parent_mid` (`parent_mid`(191)),
  KEY `thr_parent` (`thr_parent`(191)),
  KEY `uid_mid` (`uid`,`mid`(191)),
  KEY `comment_policy` (`comment_policy`(191)),
  KEY `layout_mid` (`layout_mid`(191)),
  KEY `public_policy` (`public_policy`(191)),
  KEY `comments_closed` (`comments_closed`),
  KEY `changed` (`changed`),
  KEY `item_origin` (`item_origin`),
  KEY `item_wall` (`item_wall`),
  KEY `item_hidden` (`item_hidden`),
  KEY `item_unpublished` (`item_unpublished`),
  KEY `item_delayed` (`item_delayed`),
  KEY `item_unseen` (`item_unseen`),
  KEY `item_uplink` (`item_uplink`),
  KEY `item_notshown` (`item_notshown`),
  KEY `item_nsfw` (`item_nsfw`),
  KEY `item_relay` (`item_relay`),
  KEY `item_mentionsme` (`item_mentionsme`),
  KEY `item_nocomment` (`item_nocomment`),
  KEY `item_obscured` (`item_obscured`),
  KEY `item_verified` (`item_verified`),
  KEY `item_rss` (`item_rss`),
  KEY `item_consensus` (`item_consensus`),
  KEY `item_deleted_pending_remove_changed` (`item_deleted`, `item_pending_remove`, `changed`),
  KEY `item_pending_remove_changed` (`item_pending_remove`, `changed`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `likes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `channel_id` int(10) unsigned NOT NULL DEFAULT 0 ,
  `liker` varchar(255) NOT NULL DEFAULT '',
  `likee` varchar(255) NOT NULL DEFAULT '',
  `iid` int(11) unsigned NOT NULL DEFAULT 0 ,
  `i_mid` varchar(255) NOT NULL DEFAULT '',
  `verb` varchar(255) NOT NULL DEFAULT '',
  `target_type` varchar(255) NOT NULL DEFAULT '',
  `target_id` varchar(255) NOT NULL DEFAULT '',
  `target` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `liker` (`liker`(191)),
  KEY `likee` (`likee`(191)),
  KEY `iid` (`iid`),
  KEY `i_mid` (`i_mid`(191)),
  KEY `verb` (`verb`(191)),
  KEY `target_type` (`target_type`(191)),
  KEY `channel_id` (`channel_id`),
  KEY `target_id` (`target_id`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `linkid` (
  `link_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ident` varchar(255) NOT NULL DEFAULT '',
  `link` varchar(255) NOT NULL DEFAULT '',
  `ikey` text NOT NULL,
  `lkey` text NOT NULL,
  `isig` text NOT NULL,
  `lsig` text NOT NULL,
  `sigtype` int(11) NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`link_id`),
  KEY `ident` (`ident`(191)),
  KEY `link` (`link`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS listeners (
  id int(11) NOT NULL AUTO_INCREMENT,
  target_id varchar(255) CHARACTER SET utf8mb4 NOT NULL DEFAULT '',
  portable_id varchar(255) CHARACTER SET utf8mb4 NOT NULL DEFAULT '',
  ltype int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (id),
  KEY target_id (target_id(191)),
  KEY portable_id (portable_id(191)),
  KEY ltype (ltype)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `menu` (
  `menu_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `menu_channel_id` int(10) unsigned NOT NULL DEFAULT 0 ,
  `menu_name` varchar(255) NOT NULL DEFAULT '',
  `menu_desc` varchar(255) NOT NULL DEFAULT '',
  `menu_flags` int(11) NOT NULL DEFAULT 0 ,
  `menu_created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `menu_edited` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  PRIMARY KEY (`menu_id`),
  KEY `menu_channel_id` (`menu_channel_id`),
  KEY `menu_name` (`menu_name`(191)),
  KEY `menu_flags` (`menu_flags`),
  KEY `menu_created` (`menu_created`),
  KEY `menu_edited` (`menu_edited`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `menu_item` (
  `mitem_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mitem_link` varchar(1024) NOT NULL DEFAULT '',
  `mitem_desc` varchar(1024) NOT NULL DEFAULT '',
  `mitem_flags` int(11) NOT NULL DEFAULT 0 ,
  `allow_cid` mediumtext NOT NULL,
  `allow_gid` mediumtext NOT NULL,
  `deny_cid` mediumtext NOT NULL,
  `deny_gid` mediumtext NOT NULL,
  `mitem_channel_id` int(10) unsigned NOT NULL DEFAULT 0 ,
  `mitem_menu_id` int(10) unsigned NOT NULL DEFAULT 0 ,
  `mitem_order` int(11) NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`mitem_id`),
  KEY `mitem_channel_id` (`mitem_channel_id`),
  KEY `mitem_menu_id` (`mitem_menu_id`),
  KEY `mitem_flags` (`mitem_flags`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notify` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hash` varchar(255) NOT NULL DEFAULT '',
  `xname` varchar(255) NOT NULL DEFAULT '',
  `url` varchar(255) NOT NULL DEFAULT '',
  `photo` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `msg` mediumtext NOT NULL,
  `aid` int(11) NOT NULL DEFAULT 0 ,
  `uid` int(11) NOT NULL DEFAULT 0 ,
  `link` varchar(255) NOT NULL DEFAULT '',
  `parent` varchar(255) NOT NULL DEFAULT '',
  `seen` tinyint(1) NOT NULL DEFAULT 0 ,
  `ntype` int(11) NOT NULL DEFAULT 0 ,
  `verb` varchar(255) NOT NULL DEFAULT '',
  `otype` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `ntype` (`ntype`),
  KEY `seen` (`seen`),
  KEY `uid` (`uid`),
  KEY `created` (`created`),
  KEY `hash` (`hash`(191)),
  KEY `parent` (`parent`(191)),
  KEY `link` (`link`(191)),
  KEY `otype` (`otype`(191)),
  KEY `aid` (`aid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `obj` (
  `obj_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `obj_page` varchar(255) NOT NULL DEFAULT '',
  `obj_verb` varchar(255) NOT NULL DEFAULT '',
  `obj_type` int(10) unsigned NOT NULL DEFAULT 0 ,
  `obj_obj` varchar(255) NOT NULL DEFAULT '',
  `obj_channel` int(10) unsigned NOT NULL DEFAULT 0 ,
  `obj_term` varchar(255) NOT NULL DEFAULT '',
  `obj_url` varchar(255) NOT NULL DEFAULT '',
  `obj_imgurl` varchar(255) NOT NULL DEFAULT '',
  `obj_created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `obj_edited` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `obj_quantity` int(11) NOT NULL DEFAULT 0 ,
  `allow_cid` mediumtext NOT NULL,
  `allow_gid` mediumtext NOT NULL,
  `deny_cid` mediumtext NOT NULL,
  `deny_gid` mediumtext NOT NULL,
  PRIMARY KEY (`obj_id`),
  KEY `obj_verb` (`obj_verb`(191)),
  KEY `obj_page` (`obj_page`(191)),
  KEY `obj_type` (`obj_type`),
  KEY `obj_channel` (`obj_channel`),
  KEY `obj_term` (`obj_term`(191)),
  KEY `obj_url` (`obj_url`(191)),
  KEY `obj_imgurl` (`obj_imgurl`),
  KEY `obj_created` (`obj_created`),
  KEY `obj_edited` (`obj_edited`),
  KEY `obj_quantity` (`obj_quantity`),
  KEY `obj_obj` (`obj_obj`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `outq` (
  `outq_hash` varchar(255) NOT NULL,
  `outq_account` int(10) unsigned NOT NULL DEFAULT 0 ,
  `outq_channel` int(10) unsigned NOT NULL DEFAULT 0 ,
  `outq_driver` varchar(128) NOT NULL DEFAULT '',
  `outq_posturl` varchar(255) NOT NULL DEFAULT '',
  `outq_async` tinyint(1) NOT NULL DEFAULT 0 ,
  `outq_delivered` tinyint(1) NOT NULL DEFAULT 0 ,
  `outq_created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `outq_updated` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `outq_scheduled` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `outq_notify` mediumtext NOT NULL,
  `outq_msg` mediumtext NOT NULL,
  `outq_priority` smallint(6) NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`outq_hash`(191)),
  KEY `outq_account` (`outq_account`),
  KEY `outq_channel` (`outq_channel`),
  KEY `outq_hub` (`outq_posturl`(191)),
  KEY `outq_created` (`outq_created`),
  KEY `outq_updated` (`outq_updated`),
  KEY `outq_scheduled` (`outq_scheduled`),
  KEY `outq_async` (`outq_async`),
  KEY `outq_delivered` (`outq_delivered`),
  KEY `outq_priority` (`outq_priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS `pconfig` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT 0 ,
  `cat` varchar(255) NOT NULL DEFAULT '',
  `k` varchar(255) NOT NULL DEFAULT '',
  `v` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `access` (`uid`,`cat`(191),`k`(191))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `photo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `uid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `xchan` varchar(255) NOT NULL DEFAULT '',
  `resource_id` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `edited` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `expires` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `title` varchar(255) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `album` varchar(255) NOT NULL DEFAULT '',
  `filename` varchar(4095) NOT NULL DEFAULT '',
  `mimetype` varchar(255) NOT NULL DEFAULT 'image/jpeg',
  `height` smallint(6) NOT NULL DEFAULT 0 ,
  `width` smallint(6) NOT NULL DEFAULT 0 ,
  `filesize` int(10) unsigned NOT NULL DEFAULT 0 ,
  `content` mediumblob NOT NULL,
  `imgscale` tinyint(3) NOT NULL DEFAULT 0 ,
  `photo_usage` smallint(6) NOT NULL DEFAULT 0 ,
  `profile` tinyint(1) NOT NULL DEFAULT 0 ,
  `is_nsfw` tinyint(1) NOT NULL DEFAULT 0 ,
  `os_storage` tinyint(1) NOT NULL DEFAULT 0 ,
  `os_path` mediumtext NOT NULL,
  `display_path` mediumtext NOT NULL,
  `photo_flags` int(10) unsigned NOT NULL DEFAULT 0 ,
  `allow_cid` mediumtext NOT NULL,
  `allow_gid` mediumtext NOT NULL,
  `deny_cid` mediumtext NOT NULL,
  `deny_gid` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `album` (`album`(191)),
  KEY `imgscale` (`imgscale`),
  KEY `profile` (`profile`),
  KEY `expires` (`expires`),
  KEY `photo_flags` (`photo_flags`),
  KEY `mimetype` (`mimetype`(191)),
  KEY `aid` (`aid`),
  KEY `xchan` (`xchan`(191)),
  KEY `filesize` (`filesize`),
  KEY `resource_id` (`resource_id`),
  KEY `is_nsfw` (`is_nsfw`),
  KEY `os_storage` (`os_storage`),
  KEY `photo_usage` (`photo_usage`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `poll` (
  `poll_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `poll_guid` varchar(255) NOT NULL,
  `poll_channel` int(10) unsigned NOT NULL DEFAULT 0 ,
  `poll_author` varchar(255) NOT NULL,
  `poll_desc` text NOT NULL,
  `poll_flags` int(11) NOT NULL DEFAULT 0 ,
  `poll_votes` int(11) NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`poll_id`),
  KEY `poll_guid` (`poll_guid`(191)),
  KEY `poll_channel` (`poll_channel`),
  KEY `poll_author` (`poll_author`(191)),
  KEY `poll_flags` (`poll_flags`),
  KEY `poll_votes` (`poll_votes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `poll_elm` (
  `pelm_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pelm_guid` varchar(255) NOT NULL,
  `pelm_poll` int(10) unsigned NOT NULL DEFAULT 0 ,
  `pelm_desc` text NOT NULL,
  `pelm_flags` int(11) NOT NULL DEFAULT 0 ,
  `pelm_result` float NOT NULL DEFAULT 0 ,
  `pelm_order` int(11) NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`pelm_id`),
  KEY `pelm_guid` (`pelm_guid`(191)),
  KEY `pelm_poll` (`pelm_poll`),
  KEY `pelm_result` (`pelm_result`),
  KEY `pelm_order` (`pelm_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `profdef` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `field_name` varchar(255) NOT NULL DEFAULT '',
  `field_type` varchar(255) NOT NULL DEFAULT '',
  `field_desc` varchar(255) NOT NULL DEFAULT '',
  `field_help` varchar(255) NOT NULL DEFAULT '',
  `field_inputs` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `field_name` (`field_name`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `profext` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `channel_id` int(10) unsigned NOT NULL DEFAULT 0 ,
  `hash` varchar(255) NOT NULL DEFAULT '',
  `k` varchar(255) NOT NULL DEFAULT '',
  `v` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `channel_id` (`channel_id`),
  KEY `hash` (`hash`(191)),
  KEY `k` (`k`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `profile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profile_guid` varchar(255) NOT NULL DEFAULT '',
  `aid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `uid` int(11) NOT NULL DEFAULT 0 ,
  `profile_name` varchar(255) NOT NULL DEFAULT '',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 ,
  `hide_friends` tinyint(1) NOT NULL DEFAULT 0 ,
  `fullname` varchar(255) NOT NULL DEFAULT '',
  `pdesc` varchar(255) NOT NULL DEFAULT '',
  `chandesc` text NOT NULL,
  `dob` varchar(255) NOT NULL DEFAULT '0000-00-00',
  `dob_tz` varchar(255) NOT NULL DEFAULT 'UTC',
  `address` varchar(255) NOT NULL DEFAULT '',
  `locality` varchar(255) NOT NULL DEFAULT '',
  `region` varchar(255) NOT NULL DEFAULT '',
  `postal_code` varchar(255) NOT NULL DEFAULT '',
  `country_name` varchar(255) NOT NULL DEFAULT '',
  `hometown` varchar(255) NOT NULL DEFAULT '',
  `gender` varchar(255) NOT NULL DEFAULT '',
  `marital` varchar(255) NOT NULL DEFAULT '',
  `partner` text NOT NULL,
  `howlong` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `pronouns` varchar(255) NOT NULL DEFAULT '',
  `sexual` varchar(255) NOT NULL DEFAULT '',
  `politic` varchar(255) NOT NULL DEFAULT '',
  `religion` varchar(255) NOT NULL DEFAULT '',
  `keywords` text NOT NULL,
  `likes` text NOT NULL,
  `dislikes` text NOT NULL,
  `about` text NOT NULL,
  `summary` varchar(8192) NOT NULL DEFAULT '',
  `music` text NOT NULL,
  `book` text NOT NULL,
  `tv` text NOT NULL,
  `film` text NOT NULL,
  `interest` text NOT NULL,
  `romance` text NOT NULL,
  `employment` text NOT NULL,
  `education` text NOT NULL,
  `contact` text NOT NULL,
  `channels` text NOT NULL,
  `homepage` varchar(255) NOT NULL DEFAULT '',
  `photo` varchar(255) NOT NULL DEFAULT '',
  `thumb` varchar(255) NOT NULL DEFAULT '',
  `publish` tinyint(1) NOT NULL DEFAULT 0 ,
  `profile_vcard` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `guid` (`profile_guid`(191),`uid`),
  KEY `uid` (`uid`),
  KEY `locality` (`locality`(191)),
  KEY `hometown` (`hometown`(191)),
  KEY `gender` (`gender`(191)),
  KEY `marital` (`marital`(191)),
  KEY `sexual` (`sexual`(191)),
  KEY `publish` (`publish`),
  KEY `aid` (`aid`),
  KEY `is_default` (`is_default`),
  KEY `hide_friends` (`hide_friends`),
  KEY `postal_code` (`postal_code`(191)),
  KEY `country_name` (`country_name`(191)),
  KEY `profile_guid` (`profile_guid`(191))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `register` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `uid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `password` varchar(255) NOT NULL DEFAULT '',
  `lang` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `hash` (`hash`(191)),
  KEY `created` (`created`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `session` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sid` varchar(255) NOT NULL DEFAULT '',
  `sess_data` text NOT NULL,
  `expire` bigint(20) unsigned NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`id`),
  KEY `sid` (`sid`(191)),
  KEY `expire` (`expire`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `shares` (
  `share_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `share_type` int(11) NOT NULL DEFAULT 0 ,
  `share_target` int(10) unsigned NOT NULL DEFAULT 0 ,
  `share_xchan` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`share_id`),
  KEY `share_type` (`share_type`),
  KEY `share_target` (`share_target`),
  KEY `share_xchan` (`share_xchan`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sign` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `iid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `retract_iid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `signed_text` mediumtext NOT NULL,
  `signature` text NOT NULL,
  `signer` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `iid` (`iid`),
  KEY `retract_iid` (`retract_iid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `site` (
  `site_url` varchar(255) NOT NULL,
  `site_access` int(11) NOT NULL DEFAULT 0 ,
  `site_flags` int(11) NOT NULL DEFAULT 0 ,
  `site_update` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `site_pull` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `site_sync` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `site_directory` varchar(255) NOT NULL DEFAULT '',
  `site_register` int(11) NOT NULL DEFAULT 0 ,
  `site_sellpage` varchar(255) NOT NULL DEFAULT '',
  `site_location` varchar(255) NOT NULL DEFAULT '',
  `site_realm` varchar(255) NOT NULL DEFAULT '',
  `site_valid` smallint NOT NULL DEFAULT 0 ,
  `site_dead` smallint NOT NULL DEFAULT 0 ,
  `site_type` smallint NOT NULL DEFAULT 0 ,
  `site_project` varchar(255) NOT NULL DEFAULT '',
  `site_version` varchar(255) NOT NULL DEFAULT '',
  `site_crypto` text NOT NULL,
  PRIMARY KEY (`site_url`),
  KEY `site_flags` (`site_flags`),
  KEY `site_update` (`site_update`),
  KEY `site_directory` (`site_directory`(191)),
  KEY `site_register` (`site_register`),
  KEY `site_access` (`site_access`),
  KEY `site_sellpage` (`site_sellpage`(191)),
  KEY `site_pull` (`site_pull`),
  KEY `site_realm` (`site_realm`(191)),
  KEY `site_valid` (`site_valid`),
  KEY `site_dead` (`site_dead`),
  KEY `site_type` (`site_type`),
  KEY `site_project` (`site_project`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `source` (
  `src_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `src_channel_id` int(10) unsigned NOT NULL DEFAULT 0 ,
  `src_channel_xchan` varchar(255) NOT NULL DEFAULT '',
  `src_xchan` varchar(255) NOT NULL DEFAULT '',
  `src_patt` mediumtext NOT NULL,
  `src_tag` mediumtext NOT NULL,
  PRIMARY KEY (`src_id`),
  KEY `src_channel_id` (`src_channel_id`),
  KEY `src_channel_xchan` (`src_channel_xchan`(191)),
  KEY `src_xchan` (`src_xchan`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sys_perms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cat` varchar(255) NOT NULL DEFAULT '',
  `k` varchar(255) NOT NULL DEFAULT '',
  `v` mediumtext NOT NULL,
  `public_perm` tinyint(1) unsigned NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `term` (
  `tid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `uid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `oid` int(10) unsigned NOT NULL DEFAULT 0 ,
  `otype` tinyint(3) unsigned NOT NULL DEFAULT 0 ,
  `ttype` tinyint(3) unsigned NOT NULL DEFAULT 0 ,
  `term` varchar(255) NOT NULL DEFAULT '',
  `url` varchar(255) NOT NULL DEFAULT '',
  `imgurl` varchar(255) NOT NULL DEFAULT '',
  `term_hash` varchar(255) NOT NULL DEFAULT '',
  `parent_hash` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`tid`),
  KEY `oid` (`oid`),
  KEY `otype` (`otype`),
  KEY `ttype` (`ttype`),
  KEY `term` (`term`(191)),
  KEY `uid` (`uid`),
  KEY `aid` (`aid`),
  KEY `imgurl` (`imgurl`(191)),
  KEY `term_hash` (`term_hash`(191)),
  KEY `parent_hash` (`parent_hash`(191)),
  KEY `term_ttype` (`term`,`ttype`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tokens` (
  `id` varchar(4096) NOT NULL DEFAULT '',
  `secret` text NOT NULL,
  `client_id` varchar(255) NOT NULL DEFAULT '',
  `expires` bigint(20) unsigned NOT NULL DEFAULT 0 ,
  `auth_scope` varchar(1024) NOT NULL DEFAULT '',
  `uid` int(11) NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`id`(191)),
  KEY `client_id` (`client_id`(191)),
  KEY `expires` (`expires`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `updates` (
  `ud_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ud_hash` varchar(255) NOT NULL DEFAULT '',
  `ud_guid` varchar(255) NOT NULL DEFAULT '',
  `ud_date` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `ud_last` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `ud_flags` int(11) NOT NULL DEFAULT 0 ,
  `ud_addr` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`ud_id`),
  KEY `ud_date` (`ud_date`),
  KEY `ud_guid` (`ud_guid`(191)),
  KEY `ud_hash` (`ud_hash`(191)),
  KEY `ud_flags` (`ud_flags`),
  KEY `ud_addr` (`ud_addr`(191)),
  KEY `ud_last` (`ud_last`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `verify` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `channel` int(10) unsigned NOT NULL DEFAULT 0 ,
  `vtype` varchar(255) NOT NULL DEFAULT '',
  `token` varchar(255) NOT NULL DEFAULT '',
  `meta` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  PRIMARY KEY (`id`),
  KEY `channel` (`channel`),
  KEY `vtype` (`vtype`(191)),
  KEY `token` (`token`(191)),
  KEY `meta` (`meta`(191)),
  KEY `created` (`created`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vote` (
  `vote_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vote_guid` varchar(255) NOT NULL, 
  `vote_poll` int(11) NOT NULL DEFAULT 0 ,
  `vote_element` int(11) NOT NULL DEFAULT 0 ,
  `vote_result` text NOT NULL,
  `vote_xchan` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`vote_id`),
  UNIQUE KEY `vote_vote` (`vote_poll`,`vote_element`,`vote_xchan`(191)),
  KEY `vote_guid` (`vote_guid`(191)),
  KEY `vote_poll` (`vote_poll`),
  KEY `vote_element` (`vote_element`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `xchan` (
  `xchan_hash` varchar(255) NOT NULL,
  `xchan_guid` varchar(255) NOT NULL DEFAULT '',
  `xchan_guid_sig` text NOT NULL,
  `xchan_pubkey` text NOT NULL,
  `xchan_photo_mimetype` varchar(255) NOT NULL DEFAULT 'image/jpeg',
  `xchan_photo_l` varchar(255) NOT NULL DEFAULT '',
  `xchan_photo_m` varchar(255) NOT NULL DEFAULT '',
  `xchan_photo_s` varchar(255) NOT NULL DEFAULT '',
  `xchan_addr` varchar(255) NOT NULL DEFAULT '',
  `xchan_url` varchar(255) NOT NULL DEFAULT '',
  `xchan_connurl` varchar(255) NOT NULL DEFAULT '',
  `xchan_follow` varchar(255) NOT NULL DEFAULT '',
  `xchan_connpage` varchar(255) NOT NULL DEFAULT '',
  `xchan_name` varchar(255) NOT NULL DEFAULT '',
  `xchan_network` varchar(255) NOT NULL DEFAULT '',
  `xchan_created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `xchan_updated` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `xchan_photo_date` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `xchan_name_date` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `xchan_hidden` tinyint(1) NOT NULL DEFAULT 0 ,
  `xchan_orphan` tinyint(1) NOT NULL DEFAULT 0 ,
  `xchan_censored` tinyint(1) NOT NULL DEFAULT 0 ,
  `xchan_selfcensored` tinyint(1) NOT NULL DEFAULT 0 ,
  `xchan_system` tinyint(1) NOT NULL DEFAULT 0 ,
  `xchan_type` tinyint(1) NOT NULL DEFAULT 0 ,
  `xchan_deleted` tinyint(1) NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`xchan_hash`(191)),
  KEY `xchan_guid` (`xchan_guid`(191)),
  KEY `xchan_addr` (`xchan_addr`(191)),
  KEY `xchan_name` (`xchan_name`(191)),
  KEY `xchan_network` (`xchan_network`(191)),
  KEY `xchan_url` (`xchan_url`(191)),
  KEY `xchan_connurl` (`xchan_connurl`(191)),
  KEY `xchan_follow` (`xchan_follow`(191(),
  KEY `xchan_hidden` (`xchan_hidden`),
  KEY `xchan_orphan` (`xchan_orphan`),
  KEY `xchan_censored` (`xchan_censored`),
  KEY `xchan_selfcensored` (`xchan_selfcensored`),
  KEY `xchan_system` (`xchan_system`),
  KEY `xchan_created` (`xchan_created`),
  KEY `xchan_updated` (`xchan_updated`),
  KEY `xchan_type` (`xchan_type`),
  KEY `xchan_deleted` (`xchan_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `xchat` (
  `xchat_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `xchat_url` varchar(255) NOT NULL DEFAULT '',
  `xchat_desc` varchar(255) NOT NULL DEFAULT '',
  `xchat_xchan` varchar(255) NOT NULL DEFAULT '',
  `xchat_edited` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  PRIMARY KEY (`xchat_id`),
  KEY `xchat_url` (`xchat_url`(191)),
  KEY `xchat_desc` (`xchat_desc`(191)),
  KEY `xchat_xchan` (`xchat_xchan`(191)),
  KEY `xchat_edited` (`xchat_edited`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `xconfig` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `xchan` varchar(255) NOT NULL DEFAULT '',
  `cat` varchar(255) NOT NULL DEFAULT '',
  `k` varchar(255) NOT NULL DEFAULT '',
  `v` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `xchan` (`xchan`(191)),
  KEY `cat` (`cat`(191)),
  KEY `k` (`k`(191))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `xign` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT 0 ,
  `xchan` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `xchan` (`xchan`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `xlink` (
  `xlink_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `xlink_xchan` varchar(255) NOT NULL DEFAULT '',
  `xlink_link` varchar(255) NOT NULL DEFAULT '',
  `xlink_rating` int(11) NOT NULL DEFAULT 0 ,
  `xlink_rating_text` text NOT NULL,
  `xlink_updated` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `xlink_static` tinyint(1) NOT NULL DEFAULT 0 ,
  `xlink_sig` text NOT NULL,
  PRIMARY KEY (`xlink_id`),
  KEY `xlink_xchan` (`xlink_xchan`(191)),
  KEY `xlink_link` (`xlink_link`(191)),
  KEY `xlink_updated` (`xlink_updated`),
  KEY `xlink_rating` (`xlink_rating`),
  KEY `xlink_static` (`xlink_static`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `xperm` (
  `xp_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `xp_client` varchar(255) NOT NULL DEFAULT '',
  `xp_channel` int(10) unsigned NOT NULL DEFAULT 0 ,
  `xp_perm` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`xp_id`),
  KEY `xp_client` (`xp_client`(191)),
  KEY `xp_channel` (`xp_channel`),
  KEY `xp_perm` (`xp_perm`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `xprof` (
  `xprof_hash` varchar(255) NOT NULL,
  `xprof_age` tinyint(3) unsigned NOT NULL DEFAULT 0 ,
  `xprof_desc` varchar(255) NOT NULL DEFAULT '',
  `xprof_dob` varchar(255) NOT NULL DEFAULT '',
  `xprof_gender` varchar(255) NOT NULL DEFAULT '',
  `xprof_marital` varchar(255) NOT NULL DEFAULT '',
  `xprof_sexual` varchar(255) NOT NULL DEFAULT '',
  `xprof_locale` varchar(255) NOT NULL DEFAULT '',
  `xprof_region` varchar(255) NOT NULL DEFAULT '',
  `xprof_postcode` varchar(255) NOT NULL DEFAULT '',
  `xprof_country` varchar(255) NOT NULL DEFAULT '',
  `xprof_keywords` text NOT NULL,
  `xprof_about` text NOT NULL,
  `xprof_pronouns` varchar(255) NOT NULL DEFAULT '',
  `xprof_homepage` varchar(255) NOT NULL DEFAULT '',
  `xprof_hometown` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`xprof_hash`(191)),
  KEY `xprof_desc` (`xprof_desc`(191)),
  KEY `xprof_dob` (`xprof_dob`(191)),
  KEY `xprof_gender` (`xprof_gender`(191)),
  KEY `xprof_marital` (`xprof_marital`(191)),
  KEY `xprof_sexual` (`xprof_sexual`(191)),
  KEY `xprof_locale` (`xprof_locale`(191)),
  KEY `xprof_region` (`xprof_region`(191)),
  KEY `xprof_postcode` (`xprof_postcode`(191)),
  KEY `xprof_country` (`xprof_country`(191)),
  KEY `xprof_age` (`xprof_age`),
  KEY `xprof_hometown` (`xprof_hometown`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `xtag` (
  `xtag_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `xtag_hash` varchar(255) NOT NULL DEFAULT '',
  `xtag_term` varchar(255) NOT NULL DEFAULT '',
  `xtag_flags` int(11) NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`xtag_id`),
  KEY `xtag_term` (`xtag_term`(191)),
  KEY `xtag_hash` (`xtag_hash`(191)),
  KEY `xtag_flags` (`xtag_flags`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists addressbooks (
    id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    principaluri VARBINARY(255),
    displayname VARCHAR(255),
    uri VARBINARY(200),
    description TEXT,
    synctoken INT(11) UNSIGNED NOT NULL DEFAULT '1',
    UNIQUE(principaluri(100), uri(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists cards (
    id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    addressbookid INT(11) UNSIGNED NOT NULL,
    carddata MEDIUMBLOB,
    uri VARBINARY(200),
    lastmodified INT(11) UNSIGNED,
    etag VARBINARY(32),
    size INT(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists addressbookchanges (
    id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    uri VARBINARY(200) NOT NULL,
    synctoken INT(11) UNSIGNED NOT NULL,
    addressbookid INT(11) UNSIGNED NOT NULL,
    operation TINYINT(1) NOT NULL,
    INDEX addressbookid_synctoken (addressbookid, synctoken)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists calendarobjects (
    id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    calendardata MEDIUMBLOB,
    uri VARBINARY(200),
    calendarid INTEGER UNSIGNED NOT NULL,
    lastmodified INT(11) UNSIGNED,
    etag VARBINARY(32),
    size INT(11) UNSIGNED NOT NULL,
    componenttype VARBINARY(8),
    firstoccurence INT(11) UNSIGNED,
    lastoccurence INT(11) UNSIGNED,
    uid VARBINARY(200),
    UNIQUE(calendarid, uri),
    INDEX calendarid_time (calendarid, firstoccurence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists calendars (
    id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    synctoken INTEGER UNSIGNED NOT NULL DEFAULT '1',
    components VARBINARY(21)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists calendarinstances (
    id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    calendarid INTEGER UNSIGNED NOT NULL,
    principaluri VARBINARY(100),
    access TINYINT(1) NOT NULL DEFAULT '1' COMMENT '1 = owner, 2 = read, 3 = readwrite',
    displayname VARCHAR(100),
    uri VARBINARY(200),
    description TEXT,
    calendarorder INT(11) UNSIGNED NOT NULL DEFAULT '0',
    calendarcolor VARBINARY(10),
    timezone TEXT,
    transparent TINYINT(1) NOT NULL DEFAULT '0',
    share_href VARBINARY(100),
    share_displayname VARCHAR(100),
    share_invitestatus TINYINT(1) NOT NULL DEFAULT '2' COMMENT '1 = noresponse, 2 = accepted, 3 = declined, 4 = invalid',
    UNIQUE(principaluri, uri),
    UNIQUE(calendarid, principaluri),
    UNIQUE(calendarid, share_href)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists calendarchanges (
    id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    uri VARBINARY(200) NOT NULL,
    synctoken INT(11) UNSIGNED NOT NULL,
    calendarid INT(11) UNSIGNED NOT NULL,
    operation TINYINT(1) NOT NULL,
    INDEX calendarid_synctoken (calendarid, synctoken)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists calendarsubscriptions (
    id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    uri VARBINARY(200) NOT NULL,
    principaluri VARBINARY(100) NOT NULL,
    source TEXT,
    displayname VARCHAR(100),
    refreshrate VARCHAR(10),
    calendarorder INT(11) UNSIGNED NOT NULL DEFAULT '0',
    calendarcolor VARBINARY(10),
    striptodos TINYINT(1) NULL,
    stripalarms TINYINT(1) NULL,
    stripattachments TINYINT(1) NULL,
    lastmodified INT(11) UNSIGNED,
    UNIQUE(principaluri, uri)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists schedulingobjects (
    id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    principaluri VARBINARY(255),
    calendardata MEDIUMBLOB,
    uri VARBINARY(200),
    lastmodified INT(11) UNSIGNED,
    etag VARBINARY(32),
    size INT(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists locks (
    id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    owner VARCHAR(100),
    timeout INTEGER UNSIGNED,
    created INTEGER,
    token VARBINARY(100),
    scope TINYINT,
    depth TINYINT,
    uri VARBINARY(1000),
    INDEX(token),
    INDEX(uri(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists principals (
    id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    uri VARBINARY(200) NOT NULL,
    email VARBINARY(80),
    displayname VARCHAR(80),
    UNIQUE(uri)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists groupmembers (
    id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    principal_id INTEGER UNSIGNED NOT NULL,
    member_id INTEGER UNSIGNED NOT NULL,
    UNIQUE(principal_id, member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists propertystorage (
    id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    path VARBINARY(1024) NOT NULL,
    name VARBINARY(100) NOT NULL,
    valuetype INT UNSIGNED,
    value MEDIUMBLOB
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE UNIQUE INDEX path_property ON propertystorage (path(600), name(100));

CREATE TABLE if not exists users (
    id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    username VARBINARY(50),
    digesta1 VARBINARY(32),
    UNIQUE(username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists calendarinstances (
    id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    calendarid INTEGER UNSIGNED NOT NULL,
    principaluri VARBINARY(100),
    access TINYINT(1) NOT NULL DEFAULT '1' COMMENT '1 = owner, 2 = read, 3 = readwrite',
    displayname VARCHAR(100),
    uri VARBINARY(200),
    description TEXT,
    calendarorder INT(11) UNSIGNED NOT NULL DEFAULT '0',
    calendarcolor VARBINARY(10),
    timezone TEXT,
    transparent TINYINT(1) NOT NULL DEFAULT '0',
    share_href VARBINARY(100),
    share_displayname VARCHAR(100),
    share_invitestatus TINYINT(1) NOT NULL DEFAULT '2' COMMENT '1 = noresponse, 2 = accepted, 3 = declined, 4 = invalid',
    UNIQUE(principaluri, uri),
    UNIQUE(calendarid, principaluri),
    UNIQUE(calendarid, share_href)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE if not exists oauth_clients (
  client_id             VARCHAR(80)   NOT NULL,
  client_secret         VARCHAR(80),
  redirect_uri          VARCHAR(2000),
  grant_types           VARCHAR(80),
  scope                 VARCHAR(4000),
  user_id               int(10) unsigned NOT NULL DEFAULT 0,
  client_name           VARCHAR(80),
  PRIMARY KEY (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists oauth_access_tokens (
  id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  access_token         VARCHAR(1500)    NOT NULL,
  client_id            VARCHAR(80)    NOT NULL,
  user_id              int(10) unsigned NOT NULL DEFAULT 0,
  expires              TIMESTAMP      NOT NULL,
  scope                VARCHAR(4000),
  KEY `access_token` (`access_token`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists oauth_authorization_codes (
  authorization_code  VARCHAR(80)     NOT NULL,
  client_id           VARCHAR(80)     NOT NULL,
  user_id             int(10) unsigned NOT NULL DEFAULT 0,
  redirect_uri        VARCHAR(2000),
  expires             TIMESTAMP       NOT NULL,
  scope               VARCHAR(4000),
  id_token            VARCHAR(1500),
  PRIMARY KEY (authorization_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists oauth_refresh_tokens (
  refresh_token       VARCHAR(40)     NOT NULL,
  client_id           VARCHAR(80)     NOT NULL,
  user_id             int(10) unsigned NOT NULL DEFAULT 0,
  expires             TIMESTAMP       NOT NULL,
  scope               VARCHAR(4000),
  PRIMARY KEY (refresh_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists oauth_scopes (
  scope               VARCHAR(255)    NOT NULL,
  is_default          TINYINT(1),
  PRIMARY KEY (scope(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE if not exists oauth_jwt (
  client_id           VARCHAR(80)     NOT NULL,
  subject             VARCHAR(80),
  public_key          VARCHAR(2000)   NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
