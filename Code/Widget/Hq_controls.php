<?php

namespace Code\Widget;

use Code\Render\Theme;


class Hq_controls implements WidgetInterface
{

    public function widget(array $arr): string
    {

        if (!local_channel()) {
            return '';
        }

        return replace_macros(
            Theme::get_template('hq_controls.tpl'),
            [
                '$title' => t('HQ Control Panel'),
                '$menu' => [
                    'create' => [
                        'label' => t('Create a new post'),
                        'id' => 'jot-toggle',
                        'href' => '#',
                        'class' => ''
                    ]
                ]
            ]
        );
    }
}
