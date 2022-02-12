<?php

namespace Zotlabs\Widget;

use Zotlabs\Render\Theme;


class Site_projects
{

    public function widget($args)
    {


        $r = q("select site_project, site_type, count(site_project) as total from site where site_project != '' and site_flags != 256 and site_dead = 0 group by site_project order by site_project desc");

        $results = [];

        usort($r, ['self', 'site_sort']);

        if ($r) {
            foreach ($r as $rv) {
                $result = [];
                $result['name'] = $rv['site_project'];
                $result['type'] = $rv['site_type'];
                $result['cname'] = ucfirst($result['name']);
                if ($rv['site_project'] === $_REQUEST['project']) {
                    $result['selected'] = true;
                }
                $result['total'] = $rv['total'];
                $results[] = $result;
            }

            $o = replace_macros(Theme::get_template('site_projects.tpl'), [
                '$title' => t('Projects'),
                '$desc' => '',
                '$all' => t('All projects'),
                'base' => z_root() . '/sites',
                '$sel_all' => (($_REQUEST['project']) ? false : true),
                '$terms' => $results
            ]);

            return $o;
        }
    }

    public static function site_sort($a, $b)
    {
        if ($a['site_type'] === $b['site_type']) {
            return strcasecmp($b['site_project'], $a['site_project']);
        }
        return (($a['site_type'] < $b['site_type']) ? -1 : 1);
    }
}
