<?php

namespace Zotlabs\Update;

class _1210 {

	function run() {

	$sql = "CREATE TABLE oauth_clients (
  client_id             VARCHAR(80)   NOT NULL,
  client_secret         VARCHAR(80),
  redirect_uri          VARCHAR(2000),
  grant_types           VARCHAR(80),
  scope                 VARCHAR(4000),
  user_id               VARCHAR(80),
  PRIMARY KEY (client_id)
);

CREATE TABLE oauth_access_tokens (
  access_token         VARCHAR(40)    NOT NULL,
  client_id            VARCHAR(80)    NOT NULL,
  user_id              VARCHAR(255),
  expires              TIMESTAMP      NOT NULL,
  scope                VARCHAR(4000),
  PRIMARY KEY (access_token)
);

CREATE TABLE oauth_authorization_codes (
  authorization_code  VARCHAR(40)     NOT NULL,
  client_id           VARCHAR(80)     NOT NULL,
  user_id             VARCHAR(255),
  redirect_uri        VARCHAR(2000),
  expires             TIMESTAMP       NOT NULL,
  scope               VARCHAR(4000),
  id_token            VARCHAR(1000),
  PRIMARY KEY (authorization_code)
);

CREATE TABLE oauth_refresh_tokens (
  refresh_token       VARCHAR(40)     NOT NULL,
  client_id           VARCHAR(80)     NOT NULL,
  user_id             VARCHAR(255),
  expires             TIMESTAMP       NOT NULL,
  scope               VARCHAR(4000),
  PRIMARY KEY (refresh_token)
);

CREATE TABLE oauth_scopes (
  scope               VARCHAR(191)    NOT NULL,
  is_default          SMALLINT,
  PRIMARY KEY (scope)
);

CREATE TABLE oauth_jwt (
  client_id           VARCHAR(80)     NOT NULL,
  subject             VARCHAR(80),
  public_key          VARCHAR(2000)   NOT NULL
);
";

		$arr = explode(';', $sql);
		$errors = 0;
		foreach($arr as $a) {
			if(strlen(trim($a))) {
				$r = dbq(trim($a));
				if(! $r) {
					$errors ++;
				}
			}
		}

		if(! $errors)
			return UPDATE_SUCCESS;
		return UPDATE_FAILED;

	}

}
