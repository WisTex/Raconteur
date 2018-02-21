<?php

namespace Zotlabs\Update;

class _1191 {
function run() {

	$r = q("SELECT 1 FROM principals LIMIT 1");

	if($r !== false) {
		return UPDATE_SUCCESS;
	}
	else {
		if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
			$r1 = q("CREATE TABLE addressbooks (
					id SERIAL NOT NULL,
					principaluri VARCHAR(255),
					displayname VARCHAR(255),
					uri VARCHAR(200),
					description TEXT,
					synctoken INTEGER NOT NULL DEFAULT 1
				);"
			);

			$r2 = q("ALTER TABLE ONLY addressbooks ADD CONSTRAINT addressbooks_pkey PRIMARY KEY (id);");

			$r3 = q("CREATE UNIQUE INDEX addressbooks_ukey ON addressbooks USING btree (principaluri, uri);");

			$r4 = q("CREATE TABLE cards (
					id SERIAL NOT NULL,
					addressbookid INTEGER NOT NULL,
					carddata BYTEA,
					uri VARCHAR(200),
					lastmodified INTEGER,
					etag VARCHAR(32),
					size INTEGER NOT NULL
				);"
			);

			$r5 = q("ALTER TABLE ONLY cards ADD CONSTRAINT cards_pkey PRIMARY KEY (id);");

			$r6 = q("CREATE UNIQUE INDEX cards_ukey ON cards USING btree (addressbookid, uri);");

			$r7 = q("CREATE TABLE addressbookchanges (
					id SERIAL NOT NULL,
					uri VARCHAR(200) NOT NULL,
					synctoken INTEGER NOT NULL,
					addressbookid INTEGER NOT NULL,
					operation SMALLINT NOT NULL
				);"
			);

			$r8 = q("ALTER TABLE ONLY addressbookchanges ADD CONSTRAINT addressbookchanges_pkey PRIMARY KEY (id);");

			$r9 = q("CREATE INDEX addressbookchanges_addressbookid_synctoken_ix ON addressbookchanges USING btree (addressbookid, synctoken);");

			$r10 = q("CREATE TABLE calendarobjects (
					id SERIAL NOT NULL,
					calendardata BYTEA,
					uri VARCHAR(200),
					calendarid INTEGER NOT NULL,
					lastmodified INTEGER,
					etag VARCHAR(32),
					size INTEGER NOT NULL,
					componenttype VARCHAR(8),
					firstoccurence INTEGER,
					lastoccurence INTEGER,
					uid VARCHAR(200)
				);"
			);

			$r11 = q("ALTER TABLE ONLY calendarobjects ADD CONSTRAINT calendarobjects_pkey PRIMARY KEY (id);");

			$r12 = q("CREATE UNIQUE INDEX calendarobjects_ukey ON calendarobjects USING btree (calendarid, uri);");

			$r13 = q("CREATE TABLE calendars (
					id SERIAL NOT NULL,
					synctoken INTEGER NOT NULL DEFAULT 1,
					components VARCHAR(21)
				);"
			);

			$r14 = q("ALTER TABLE ONLY calendars ADD CONSTRAINT calendars_pkey PRIMARY KEY (id);");

			$r15 = q("CREATE TABLE calendarinstances (
					id SERIAL NOT NULL,
					calendarid INTEGER NOT NULL,
					principaluri VARCHAR(100),
					access SMALLINT NOT NULL DEFAULT '1', -- '1 = owner, 2 = read, 3 = readwrite'
					displayname VARCHAR(100),
					uri VARCHAR(200),
					description TEXT,
					calendarorder INTEGER NOT NULL DEFAULT 0,
					calendarcolor VARCHAR(10),
					timezone TEXT,
					transparent SMALLINT NOT NULL DEFAULT '0',
					share_href VARCHAR(100),
					share_displayname VARCHAR(100),
					share_invitestatus SMALLINT NOT NULL DEFAULT '2' --  '1 = noresponse, 2 = accepted, 3 = declined, 4 = invalid'
				);"
			);

			$r16 = q("ALTER TABLE ONLY calendarinstances ADD CONSTRAINT calendarinstances_pkey PRIMARY KEY (id);");

			$r17 = q("CREATE UNIQUE INDEX calendarinstances_principaluri_uri ON calendarinstances USING btree (principaluri, uri);");

			$r18 = q("CREATE UNIQUE INDEX calendarinstances_principaluri_calendarid ON calendarinstances USING btree (principaluri, calendarid);");

			$r19 = q("CREATE UNIQUE INDEX calendarinstances_principaluri_share_href ON calendarinstances USING btree (principaluri, share_href);");

			$r20 = q("CREATE TABLE calendarsubscriptions (
					id SERIAL NOT NULL,
					uri VARCHAR(200) NOT NULL,
					principaluri VARCHAR(100) NOT NULL,
					source TEXT,
					displayname VARCHAR(100),
					refreshrate VARCHAR(10),
					calendarorder INTEGER NOT NULL DEFAULT 0,
					calendarcolor VARCHAR(10),
					striptodos SMALLINT NULL,
					stripalarms SMALLINT NULL,
					stripattachments SMALLINT NULL,
					lastmodified INTEGER
				);"
			);

			$r21 = q("ALTER TABLE ONLY calendarsubscriptions ADD CONSTRAINT calendarsubscriptions_pkey PRIMARY KEY (id);");

			$r22 = q("CREATE UNIQUE INDEX calendarsubscriptions_ukey ON calendarsubscriptions USING btree (principaluri, uri);");

			$r23 = q("CREATE TABLE calendarchanges (
					id SERIAL NOT NULL,
					uri VARCHAR(200) NOT NULL,
					synctoken INTEGER NOT NULL,
					calendarid INTEGER NOT NULL,
					operation SMALLINT NOT NULL DEFAULT 0
				);"
			);

			$r24 = q("ALTER TABLE ONLY calendarchanges ADD CONSTRAINT calendarchanges_pkey PRIMARY KEY (id);");

			$r25 = q("CREATE INDEX calendarchanges_calendarid_synctoken_ix ON calendarchanges USING btree (calendarid, synctoken);");

			$r26 = q("CREATE TABLE schedulingobjects (
					id SERIAL NOT NULL,
					principaluri VARCHAR(255),
					calendardata BYTEA,
					uri VARCHAR(200),
					lastmodified INTEGER,
					etag VARCHAR(32),
					size INTEGER NOT NULL
				);"
			);

			$r27 = q("CREATE TABLE locks (
					id SERIAL NOT NULL,
					owner VARCHAR(100),
					timeout INTEGER,
					created INTEGER,
					token VARCHAR(100),
					scope SMALLINT,
					depth SMALLINT,
					uri TEXT
				);"
			);

			$r28 = q("ALTER TABLE ONLY locks ADD CONSTRAINT locks_pkey PRIMARY KEY (id);");

			$r29 = q("CREATE INDEX locks_token_ix ON locks USING btree (token);");

			$r30 = q("CREATE INDEX locks_uri_ix ON locks USING btree (uri);");

			$r31 = q("CREATE TABLE principals (
					id SERIAL NOT NULL,
					uri VARCHAR(200) NOT NULL,
					email VARCHAR(80),
					displayname VARCHAR(80)
				);"
			);

			$r32 = q("ALTER TABLE ONLY principals ADD CONSTRAINT principals_pkey PRIMARY KEY (id);");

			$r33 = q("CREATE UNIQUE INDEX principals_ukey ON principals USING btree (uri);");

			$r34 = q("CREATE TABLE groupmembers (
					id SERIAL NOT NULL,
					principal_id INTEGER NOT NULL,
					member_id INTEGER NOT NULL
				);"
			);

			$r35 = q("ALTER TABLE ONLY groupmembers ADD CONSTRAINT groupmembers_pkey PRIMARY KEY (id);");

			$r36 = q("CREATE UNIQUE INDEX groupmembers_ukey ON groupmembers USING btree (principal_id, member_id);");

			$r37 = q("CREATE TABLE propertystorage (
					id SERIAL NOT NULL,
					path VARCHAR(1024) NOT NULL,
					name VARCHAR(100) NOT NULL,
					valuetype INT,
					value BYTEA
				);"
			);

			$r38 = q("ALTER TABLE ONLY propertystorage ADD CONSTRAINT propertystorage_pkey PRIMARY KEY (id);");

			$r39 = q("CREATE UNIQUE INDEX propertystorage_ukey ON propertystorage (path, name);");

			$r40 = q("CREATE TABLE users (
					id SERIAL NOT NULL,
					username VARCHAR(50),
					digesta1 VARCHAR(32)
				);"
			);

			$r41 = q("ALTER TABLE ONLY users ADD CONSTRAINT users_pkey PRIMARY KEY (id);");

			$r42 = q("CREATE UNIQUE INDEX users_ukey ON users USING btree (username);");

			if(
				$r1 && $r2 && $r3 && $r4 && $r5 && $r6 && $r7 && $r8 && $r9 && $r10
				&& $r11 && $r12 && $r13 && $r14 && $r15 && $r16 && $r17 && $r18 && $r19 && $r20
				&& $r21 && $r22 && $r23 && $r24 && $r25 && $r26 && $r27 && $r28 && $r29 && $r30
				&& $r31 && $r32 && $r33 && $r34 && $r35 && $r36 && $r37 && $r38 && $r39 && $r40
				&& $r41 && $r42
			)
				return UPDATE_SUCCESS;
			return UPDATE_FAILED;
		}
		else {
			$r1 = q("CREATE TABLE if not exists addressbooks (
					id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
					principaluri VARBINARY(255),
					displayname VARCHAR(255),
					uri VARBINARY(200),
					description TEXT,
					synctoken INT(11) UNSIGNED NOT NULL DEFAULT '1',
					UNIQUE(principaluri(100), uri(100))
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
			);

			$r2 = q("CREATE TABLE if not exists cards (
					id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
					addressbookid INT(11) UNSIGNED NOT NULL,
					carddata MEDIUMBLOB,
					uri VARBINARY(200),
					lastmodified INT(11) UNSIGNED,
					etag VARBINARY(32),
					size INT(11) UNSIGNED NOT NULL
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
			);

			$r3 = q("CREATE TABLE if not exists addressbookchanges (
					id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
					uri VARBINARY(200) NOT NULL,
					synctoken INT(11) UNSIGNED NOT NULL,
					addressbookid INT(11) UNSIGNED NOT NULL,
					operation TINYINT(1) NOT NULL,
					INDEX addressbookid_synctoken (addressbookid, synctoken)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
			);

			$r4 = q("CREATE TABLE if not exists calendarobjects (
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
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
			);

			$r5 = q("CREATE TABLE if not exists calendars (
					id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
					synctoken INTEGER UNSIGNED NOT NULL DEFAULT '1',
					components VARBINARY(21)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
			);

			$r6 = q("CREATE TABLE if not exists calendarinstances (
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
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
			);

			$r7 = q("CREATE TABLE if not exists calendarchanges (
					id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
					uri VARBINARY(200) NOT NULL,
					synctoken INT(11) UNSIGNED NOT NULL,
					calendarid INT(11) UNSIGNED NOT NULL,
					operation TINYINT(1) NOT NULL,
					INDEX calendarid_synctoken (calendarid, synctoken)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
			);

			$r8 = q("CREATE TABLE if not exists calendarsubscriptions (
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
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
			);

			$r9 = q("CREATE TABLE if not exists schedulingobjects (
					id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
					principaluri VARBINARY(255),
					calendardata MEDIUMBLOB,
					uri VARBINARY(200),
					lastmodified INT(11) UNSIGNED,
					etag VARBINARY(32),
					size INT(11) UNSIGNED NOT NULL
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
			);

			$r10 = q("CREATE TABLE if not exists locks (
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
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
			);

			$r11 = q("CREATE TABLE if not exists principals (
					id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
					uri VARBINARY(200) NOT NULL,
					email VARBINARY(80),
					displayname VARCHAR(80),
					UNIQUE(uri)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
			);

			$r12 = q("CREATE TABLE if not exists groupmembers (
					id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
					principal_id INTEGER UNSIGNED NOT NULL,
					member_id INTEGER UNSIGNED NOT NULL,
					UNIQUE(principal_id, member_id)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
			);

			$r13 = q("CREATE TABLE if not exists propertystorage (
					id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
					path VARBINARY(1024) NOT NULL,
					name VARBINARY(100) NOT NULL,
					valuetype INT UNSIGNED,
					value MEDIUMBLOB
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
			);

			$r14 = q("CREATE UNIQUE INDEX path_property ON propertystorage (path(600), name(100));");

			$r15 = q("CREATE TABLE if not exists users (
					id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
					username VARBINARY(50),
					digesta1 VARBINARY(32),
					UNIQUE(username)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
			);

			$r16 = q("CREATE TABLE if not exists calendarinstances (
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
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
			);

			if($r1 && $r2 && $r3 && $r4 && $r5 && $r6 && $r7 && $r8 && $r9 && $r10 && $r11 && $r12 && $r13 && $r14 && $r15 && $r16)
				return UPDATE_SUCCESS;
			return UPDATE_FAILED;
		}
	}
}


}