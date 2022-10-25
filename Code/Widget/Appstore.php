<?php

namespace Code\Widget;

use Code\Render\Theme;


class Appstore implements WidgetInterface
{

    public function widget(array $arguments): string
    {
        $store = ((argc() > 1 && argv(1) === 'available') ? 1 : 0);
        return replace_macros(Theme::get_template('appstore.tpl'), [
            '$title' => t('App Collections'),
            '$options' => [
                [z_root() . '/apps', t('Installed Apps'), 1 - $store],
                [z_root() . '/apps/available', t('Available Apps'), $store]
            ]
        ]);
    }
}
