<?php

namespace Code\Widget;

use Code\Lib\Apps;
use Code\Extend\Hook;
use Code\Render\Theme;
use App;

class Stream_order implements WidgetInterface
{

    public function widget(array $arr): string
    {

        if (!local_channel()) {
            return '';
        }

        $module = 'mod_' . App::$module;


        if (!Apps::addon_app_installed(local_channel(), 'stream_order')) {
            set_pconfig(local_channel(), $module, 'order', 0);
            return '';
        }

        $commentord_active = '';
        $postord_active = '';
        $unthreaded_active = '';
        $received_active = '';
    
        if (x($_GET, 'order')) {
            switch ($_GET['order']) {
                case 'post':
                    $postord_active = 'active';
                    set_pconfig(local_channel(), $module, 'order', 1);
                    break;
                case 'comment':
                    $commentord_active = 'active';
                    set_pconfig(local_channel(), $module, 'order', 0);
                    break;
                case 'received':
                    $received_active = 'active';
                    set_pconfig(local_channel(), $module, 'order', 3);
                    break;
                case 'unthreaded':
                    $unthreaded_active = 'active';
                    set_pconfig(local_channel(), $module, 'order', 2);
                    break;
                default:
                    $commentord_active = 'active';
                    break;
            }
        } else {
            $order = get_pconfig(local_channel(), $module, 'order', 0);
            switch ($order) {
                case 0:
                    $commentord_active = 'active';
                    break;
                case 1:
                    $postord_active = 'active';
                    break;
                case 2:
                    $unthreaded_active = 'active';
                    break;
                case 3:
                    $received_active = 'active';
                    break;
                default:
                    $commentord_active = 'active';
            }
        }

        // override order for search, filer and cid results
        if (x($_GET, 'search') || x($_GET, 'file') || (!x($_GET, 'pf') && x($_GET, 'cid'))) {
            $unthreaded_active = 'active';
            $commentord_active = $postord_active = 'disabled';
        }

        $cmd = App::$cmd;

        $filter = '';

        if (x($_GET, 'cid')) {
            $filter .= '&cid=' . $_GET['cid'];
        }

        if (x($_GET, 'gid')) {
            $filter .= '&gid=' . $_GET['gid'];
        }

        if (x($_GET, 'star')) {
            $filter .= '&star=' . $_GET['star'];
        }

        if (x($_GET, 'conv')) {
            $filter .= '&conv=' . $_GET['conv'];
        }

        if (x($_GET, 'file')) {
            $filter .= '&file=' . $_GET['file'];
        }

        if (x($_GET, 'pf')) {
            $filter .= '&pf=' . $_GET['pf'];
        }


        // tabs
        $tabs = [];

        $tabs[] = [
            'label' => t('Commented Date'),
            'icon' => '',
            'url' => z_root() . '/' . $cmd . '?f=&order=comment' . $filter,
            'sel' => $commentord_active,
            'title' => t('Order by commented date'),
        ];
        $tabs[] = [
            'label' => t('Posted Date'),
            'icon' => '',
            'url' => z_root() . '/' . $cmd . '?f=&order=post' . $filter,
            'sel' => $postord_active,
            'title' => t('Order by posted date'),
        ];
        $tabs[] = [
            'label' => t('Received Date'),
            'icon' => '',
            'url' => z_root() . '/' . $cmd . '?f=&order=received' . $filter,
            'sel' => $received_active,
            'title' => t('Order by received date'),
        ];
        $tabs[] = [
            'label' => t('Date Unthreaded'),
            'icon' => '',
            'url' => z_root() . '/' . $cmd . '?f=&order=unthreaded' . $filter,
            'sel' => $unthreaded_active,
            'title' => t('Order unthreaded by received date'),
        ];

        $arr = ['tabs' => $tabs];

        Hook::call('activity_order', $arr);

        $o = '';

        if ($arr['tabs']) {
            $content = replace_macros(Theme::get_template('common_pills.tpl'), [
                '$pills' => $arr['tabs'],
            ]);

            $o = replace_macros(Theme::get_template('common_widget.tpl'), [
                '$title' => t('Stream Order'),
    			'$content_id' => 'widget-stream-order',
                '$content' => $content,
            ]);
        }

        return $o;
    }
}
