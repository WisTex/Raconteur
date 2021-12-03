<?php

namespace Zotlabs\Widget;

class Hq_controls
{

    public function widget($arr)
    {

        if (!local_channel())
            return;

        return replace_macros(get_markup_template('hq_controls.tpl'),
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
