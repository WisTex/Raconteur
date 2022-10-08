<?php

namespace Code\Widget;

use App;
use Code\Lib\Features;
use Code\Render\Theme;


class Newmember
{

    public function widget($arr)
    {

        if (!local_channel()) {
            return EMPTY_STR;
        }

        $c = App::get_channel();
        if (!$c) {
            return EMPTY_STR;
        }
        $a = App::get_account();
        if (!$a) {
            return EMPTY_STR;
        }

        // @fixme
        if (!Features::enabled(local_channel(), 'start_menu')) {
            return EMPTY_STR;
        }

        $options = [
            t('Profile Creation'),
            [
                'profile_photo' => t('Upload profile photo'),
                'cover_photo' => t('Upload cover photo'),
                'settings/profile_edit' => t('Edit your profile'),
            ],

            t('Find and Connect with others'),
            [
                'directory' => t('View the directory'),
                'directory?f=&suggest=1' => t('View friend suggestions'),
                'connections' => t('Manage your connections'),
            ],

            t('Communicate'),
            [
                'channel/' . $c['channel_address'] => t('View your channel homepage'),
                'stream' => t('View your stream'),
            ],

            t('Miscellaneous'),
            [
                'settings' => t('Settings'),
            ]
        ];

        $public_stream_mode = intval(get_config('system', 'public_stream_mode', PUBLIC_STREAM_NONE));
        // hack to put this in the correct spot of the array

        if ($public_stream_mode) {
            $options[5]['pubstream'] = t('View public stream');
        }


        $content = replace_macros(Theme::get_template('new_member.tpl'), [ '$options' => $options ]);
    
        $o = replace_macros(Theme::get_template('common_widget.tpl'), [
            '$title' => t('New Member Links'),
            '$content_id' => 'widget-new-member',
            '$content' => $content,
        ]);

 
        return $o;
    }
}
