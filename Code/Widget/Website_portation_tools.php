<?php

namespace Code\Widget;

use App;

class Website_portation_tools implements WidgetInterface
{

    public function widget(array $arr): string
    {

        // mod menu doesn't load a profile. For any modules which load a profile, check it.
        // otherwise local_channel() is sufficient for permissions.

        if (App::$profile['profile_uid']) {
            if ((App::$profile['profile_uid'] != local_channel()) && (!App::$is_sys)) {
                return '';
            }
        }

        if (!local_channel()) {
            return '';
        }

        return website_portation_tools();
    }
}
