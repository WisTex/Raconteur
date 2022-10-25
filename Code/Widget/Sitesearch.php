<?php

namespace Code\Widget;

use App;
use Code\Render\Theme;


class Sitesearch implements WidgetInterface
{

    public function widget(array $arguments): string
    {

        $search = ((x($_GET, 'search')) ? $_GET['search'] : '');

        $srchurl = App::$query_string;

        $srchurl = rtrim(preg_replace('/search\=[^\&].*?(\&|$)/is', '', $srchurl), '&');
        $srchurl = rtrim(preg_replace('/submit\=[^\&].*?(\&|$)/is', '', $srchurl), '&');
        $srchurl = str_replace(['?f=', '&f='], ['', ''], $srchurl);


        $hasq = ((str_contains($srchurl, '?')) ? true : false);
        $hasamp = ((str_contains($srchurl, '&')) ? true : false);

        if (($hasamp) && (!$hasq)) {
            $srchurl = substr($srchurl, 0, strpos($srchurl, '&')) . '?f=&' . substr($srchurl, strpos($srchurl, '&') + 1);
        }

        $o = '';

        $saved = [];

        $tpl = Theme::get_template("sitesearch.tpl");
        $o = replace_macros($tpl, [
            '$title' => t('Search'),
            '$searchbox' => searchbox($search, 'netsearch-box', $srchurl . (($hasq) ? '' : '?f='), false),
            '$saved' => $saved,
        ]);

        return $o;
    }
}
