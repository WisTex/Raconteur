<?php

namespace Code\Storage;

use OAuth2\Storage\Pdo;

class ZotOauth2Pdo extends Pdo
{
    public function getConfig()
    {
        return $this->config;
    }
}
