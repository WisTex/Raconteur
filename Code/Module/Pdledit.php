<?php

namespace Code\Module;

use Code\Lib\Libsync;
use Code\Web\Controller;
use Code\Render\Theme;

class Pdledit extends Controller
{

    public function post()
    {
        if (!local_channel()) {
            return;
        }
        if (!$_REQUEST['module']) {
            return;
        }

        if (!trim($_REQUEST['content'])) {
            del_pconfig(local_channel(), 'system', 'mod_' . $_REQUEST['module'] . '.pdl');
            goaway(z_root() . '/pdledit');
        }

        set_pconfig(local_channel(), 'system', 'mod_' . $_REQUEST['module'] . '.pdl', escape_tags($_REQUEST['content']));
        Libsync::build_sync_packet();
        info(t('Layout updated.') . EOL);
        goaway(z_root() . '/pdledit/' . $_REQUEST['module']);
    }


    public function get()
    {

        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        if (argc() > 2 && argv(2) === 'reset') {
            del_pconfig(local_channel(), 'system', 'mod_' . argv(1) . '.pdl');
            goaway(z_root() . '/pdledit');
        }

        if (argc() > 1) {
            $module = 'mod_' . argv(1) . '.pdl';
        } else {
            $o .= '<div class="generic-content-wrapper-styled">';
            $o .= '<h1>' . t('Edit System Page Description') . '</h1>';

            $edited = [];

            $r = q(
                "select k from pconfig where uid = %d and cat = 'system' and k like '%s' ",
                intval(local_channel()),
                dbesc('mod_%.pdl')
            );

            if ($r) {
                foreach ($r as $rv) {
                    $edited[] = substr(str_replace('.pdl', '', $rv['k']), 4);
                }
            }

            $files = glob('Code/Module/*.php');
            if ($files) {
                foreach ($files as $f) {
                    $name = lcfirst(basename($f, '.php'));
                    $x = Theme::include('mod_' . $name . '.pdl');
                    if ($x) {
                        $o .= '<a href="pdledit/' . $name . '" >' . $name . '</a>' . ((in_array($name, $edited)) ? ' ' . t('(modified)') . ' <a href="pdledit/' . $name . '/reset" >' . t('Reset') . '</a>' : '') . '<br>';
                    }
                }
            }
            $addons = glob('addon/*/*.pdl');
            if ($addons) {
                foreach ($addons as $a) {
                    $name = substr(basename($a, '.pdl'), 4);
                    $o .= '<a href="pdledit/' . $name . '" >' . $name . '</a>' . ((in_array($name, $edited)) ? ' ' . t('(modified)') . ' <a href="pdledit/' . $name . '/reset" >' . t('Reset') . '</a>' : '') . '<br>';
                }
            }

            $o .= '</div>';

            // list module pdl files
            return $o;
        }

        $t = get_pconfig(local_channel(), 'system', $module);
        $s = @file_get_contents(Theme::include($module));
        if (!$s) {
            $a = glob('addon/*/' . $module);
            if ($a) {
                $s = @file_get_contents($a[0]);
            }
        }
        if (!$t) {
            $t = $s;
        }
        if (!$t) {
            notice(t('Layout not found.') . EOL);
            return '';
        }

        $o = replace_macros(Theme::get_template('pdledit.tpl'), array(
            '$header' => t('Edit System Page Description'),
            '$mname' => t('Module Name:'),
            '$help' => t('Layout Help'),
            '$another' => t('Edit another layout'),
            '$original' => t('System layout'),
            '$module' => argv(1),
            '$src' => $s,
            '$content' => htmlspecialchars($t, ENT_COMPAT, 'UTF-8'),
            '$submit' => t('Submit')
        ));

        return $o;
    }
}
