<?php

namespace Code\Update;

class _1091
{
    public function run()
    {
        @os_mkdir('cache/smarty3', STORAGE_DEFAULT_PERMISSIONS, true);
        @file_put_contents('cache/locks', '');
        return UPDATE_SUCCESS;
    }
}
