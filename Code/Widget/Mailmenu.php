<?php

namespace Code\Widget;

use Code\Render\Theme;


class Mailmenu
{

    public function widget($arr)
    {

        if (!local_channel()) {
            return;
        }

        return replace_macros(Theme::get_template('message_side.tpl'), array(
            '$title' => t('Private Mail Menu'),
            '$combined' => array(
                'label' => t('Combined View'),
                'url' => z_root() . '/mail/combined',
                'sel' => (argv(1) == 'combined'),
            ),
            '$inbox' => array(
                'label' => t('Inbox'),
                'url' => z_root() . '/mail/inbox',
                'sel' => (argv(1) == 'inbox'),
            ),
            '$outbox' => array(
                'label' => t('Outbox'),
                'url' => z_root() . '/mail/outbox',
                'sel' => (argv(1) == 'outbox'),
            ),
            '$new' => array(
                'label' => t('New Message'),
                'url' => z_root() . '/mail/new',
                'sel' => (argv(1) == 'new'),
            )
        ));
    }
}
