<?php

namespace Code\Widget;

use Code\Render\Theme;

class Site_projects implements WidgetInterface
{

    public function widget(array $arguments): string
    {
        $results = [];
        $query = q("select site_project, site_type, count(site_project) as total from site
            where site_project != '' and site_flags != 256 and site_dead = 0 group by site_project, site_type order by site_project desc");
        if ($query) {
            usort($query, ['self', 'site_sort']);
            foreach ($query as $rv) {
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

            $output = replace_macros(Theme::get_template('site_projects.tpl'), [
                '$title' => t('Community Types'),
                '$desc' => '',
                '$all' => t('All community types'),
                'base' => z_root() . '/communities',
                '$sel_all' => (($_REQUEST['project']) ? false : true),
                '$terms' => $results
            ]);

            return $output;
        }
    }

    public static function site_sort($a, $b)
    {
        return strcasecmp($a['site_project'], $b['site_project']);
    }
}
