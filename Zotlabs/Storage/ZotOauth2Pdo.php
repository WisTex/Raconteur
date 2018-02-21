<?php

namespace Zotlabs\Storage;

class ZotOauth2Pdo extends \OAuth2\Storage\Pdo {
	public function getConfig()
    {
		return $this->config;
    }
}
