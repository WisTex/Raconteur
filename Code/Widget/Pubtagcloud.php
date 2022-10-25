<?php

namespace Code\Widget;

class Pubtagcloud implements WidgetInterface
{

    public function widget(array $arguments): string
    {

        $trending = ((array_key_exists('trending', $arguments)) ? intval($arguments['trending']) : 0);

        if (!intval(get_config('system', 'open_pubstream', 0))) {
            if (!local_channel()) {
                return EMPTY_STR;
            }
        }

        $public_stream_mode = intval(get_config('system', 'public_stream_mode', PUBLIC_STREAM_NONE));

        if (!$public_stream_mode) {
            return EMPTY_STR;
        }

        $safemode = get_xconfig(get_observer_hash(), 'directory', 'safemode', 1);


        $limit = ((array_key_exists('limit', $arguments)) ? intval($arguments['limit']) : 75);

        return pubtagblock($public_stream_mode, $limit, $trending, $safemode);

    }
}
