<?php

namespace Zotlabs\Widget;

use Zotlabs\Render\Theme;


class Appcategories
{

    public function widget($arr)
    {

        if (!local_channel()) {
            return '';
        }

        $selected = ((x($_REQUEST, 'cat')) ? htmlspecialchars($_REQUEST['cat'], ENT_COMPAT, 'UTF-8') : '');

        // @FIXME ??? $srchurl undefined here - commented out until is reviewed
        //$srchurl =  rtrim(preg_replace('/cat\=[^\&].*?(\&|$)/is','',$srchurl),'&');
        //$srchurl = str_replace(array('?f=','&f='),array('',''),$srchurl);

        // Leaving this line which negates the effect of the two invalid lines prior
        $srchurl = z_root() . '/apps';
        if (argc() > 1 && argv(1) === 'available') {
            $srchurl .= '/available';
        }


        $terms = [];

        $r = q(
            "select distinct(term.term)
	        from term join app on term.oid = app.id
    	    where app_channel = %d
        	and term.uid = app_channel
	        and term.otype = %d
    	    and term.term != 'nav_featured_app'
    	    and term.term != 'nav_pinned_app'
        	order by term.term asc",
            intval(local_channel()),
            intval(TERM_OBJ_APP)
        );

        if ($r) {
            foreach ($r as $rr) {
                $terms[] = array('name' => $rr['term'], 'selected' => (($selected == $rr['term']) ? 'selected' : ''));
            }

            return replace_macros(Theme::get_template('categories_widget.tpl'), array(
                '$title' => t('Categories'),
                '$desc' => '',
                '$sel_all' => (($selected == '') ? 'selected' : ''),
                '$all' => t('Everything'),
                '$terms' => $terms,
                '$base' => $srchurl,

            ));
        }
    }
}
