<?php

namespace Code\Update;

class _1262
{

    public function run()
    {
        $str = get_config('system','workflow_channel_next');
        if ($str === 'profiles') {
            set_config('system','workflow_channel_next','settings/profile_edit');
        }
        return UPDATE_SUCCESS;
    }

    public function verify()
    {
        return true;
    }
}
