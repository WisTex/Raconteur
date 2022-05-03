<?php

namespace Code\Module\Admin;


use Code\Render\Theme;
use Code\Lib\Apps;


class Customsql
{


    public function get()
    {

        $o = '';

        if (!is_site_admin()) {
            logger('admin denied.');
            return;
        }

        if (!Apps::system_app_installed(local_channel(), 'Custom SQL')) {
            $text = t('Once installed, this app provides an admin-only interface to execute arbitrary SQL statements.');
            return '<div class="section-content-info-wrapper">' . $text . '</div>';
        }
    
        $query = [ 'query', 'Enter an SQL query', '', '' ];
        $ok =    [ 'ok', 'OK to show all results?', '', '' ];
    
        $o = replace_macros(Theme::get_template('customsql.tpl'), [
            '$title' => t('Custom SQL query'),
            '$warn' => t('If you do not know exactly what you are doing, please leave this page now.'),
            '$query' => $query,
            '$form_security_token' => get_form_security_token('admin_customsql'),
            '$ok' => $ok,
            '$submit' => t('Submit')
        ]);

        if (isset($_REQUEST['query']) && $_REQUEST['query']) {
            check_form_security_token_redirectOnErr('/admin/customsql', 'admin_customsql');
            $o .= EOL . EOL;
            $r = q($_REQUEST['query']);
            if (is_array($r) && count($r) > 500 && !isset($_REQUEST['ok'])) { 
                notice ( t('Too many results.') . EOL);
            }
            elseif (is_array($r)) {
                $o .= '"' . $_REQUEST['query'] . '" ' . sprintf( t('returned %d results'), count($r));
                $o .= EOL. EOL;
                $o .= '<pre>' . print_array($r) . '</pre>';
            }
            else {
                $o .= t('Query returned: ') . (($r) ? t('true') : t('false')) ;
            }
        }
    

        return $o;
    }
}
