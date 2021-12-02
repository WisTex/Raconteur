<?php

namespace Zotlabs\Widget;

use App;
use Zotlabs\Lib\Apps;

class Categories
{

    public function widget($arr)
    {

        $cards = ((array_key_exists('cards', $arr) && $arr['cards']) ? true : false);

        if (($cards) && (!Apps::system_app_installed(App::$profile['profile_uid'], 'Cards')))
            return '';

        $articles = ((array_key_exists('articles', $arr) && $arr['articles']) ? true : false);

        if (($articles) && (!Apps::addon_app_installed(App::$profile['profile_uid'], 'articles')))
            return '';


        if ((!App::$profile['profile_uid'])
            || (!perm_is_allowed(App::$profile['profile_uid'], get_observer_hash(), (($cards || $articles) ? 'view_pages' : 'view_articles')))) {
            return '';
        }

        $cat = ((x($_REQUEST, 'cat')) ? htmlspecialchars($_REQUEST['cat'], ENT_COMPAT, 'UTF-8') : '');
        $srchurl = (($cards) ? App::$argv[0] . '/' . App::$argv[1] : App::$query_string);
        $srchurl = rtrim(preg_replace('/cat\=[^\&].*?(\&|$)/is', '', $srchurl), '&');
        $srchurl = str_replace(array('?f=', '&f='), array('', ''), $srchurl);

        if ($cards)
            return self::cardcategories_widget($srchurl, $cat);
        elseif ($articles)
            return self::articlecategories_widget($srchurl, $cat);
        else
            return self::categories_widget($srchurl, $cat);

    }


    public static function articlecategories_widget($baseurl, $selected = '')
    {

        if (!Apps::system_app_installed(App::$profile['profile_uid'], 'Categories'))
            return '';

        $sql_extra = item_permissions_sql(App::$profile['profile_uid']);

        $item_normal = "and item.item_hidden = 0 and item.item_type = 7 and item.item_deleted = 0
			and item.item_unpublished = 0 and item.item_delayed = 0 and item.item_pending_remove = 0
			and item.item_blocked = 0 ";

        $terms = [];
        $r = q("select distinct(term.term)
			from term join item on term.oid = item.id
			where item.uid = %d
			and term.uid = item.uid
			and term.ttype = %d
			and term.otype = %d
			and item.owner_xchan = '%s'
			$item_normal
			$sql_extra
			order by term.term asc",
            intval(App::$profile['profile_uid']),
            intval(TERM_CATEGORY),
            intval(TERM_OBJ_POST),
            dbesc(App::$profile['channel_hash'])
        );
        if ($r && count($r)) {
            foreach ($r as $rr)
                $terms[] = array('name' => $rr['term'], 'selected' => (($selected == $rr['term']) ? 'selected' : ''));

            return replace_macros(get_markup_template('categories_widget.tpl'), array(
                '$title' => t('Categories'),
                '$desc' => '',
                '$sel_all' => (($selected == '') ? 'selected' : ''),
                '$all' => t('Everything'),
                '$terms' => $terms,
                '$base' => $baseurl,

            ));
        }
        return '';
    }

    public static function cardcategories_widget($baseurl, $selected = '')
    {

        if (!Apps::system_app_installed(App::$profile['profile_uid'], 'Categories'))
            return '';

        $sql_extra = item_permissions_sql(App::$profile['profile_uid']);

        $item_normal = "and item.item_hidden = 0 and item.item_type = 6 and item.item_deleted = 0
			and item.item_unpublished = 0 and item.item_delayed = 0 and item.item_pending_remove = 0
			and item.item_blocked = 0 ";

        $terms = [];
        $r = q("select distinct(term.term)
			from term join item on term.oid = item.id
			where item.uid = %d
			and term.uid = item.uid
			and term.ttype = %d
			and term.otype = %d
			and item.owner_xchan = '%s'
			$item_normal
			$sql_extra
			order by term.term asc",
            intval(App::$profile['profile_uid']),
            intval(TERM_CATEGORY),
            intval(TERM_OBJ_POST),
            dbesc(App::$profile['channel_hash'])
        );
        if ($r && count($r)) {
            foreach ($r as $rr)
                $terms[] = array('name' => $rr['term'], 'selected' => (($selected == $rr['term']) ? 'selected' : ''));

            return replace_macros(get_markup_template('categories_widget.tpl'), array(
                '$title' => t('Categories'),
                '$desc' => '',
                '$sel_all' => (($selected == '') ? 'selected' : ''),
                '$all' => t('Everything'),
                '$terms' => $terms,
                '$base' => $baseurl,

            ));
        }
        return '';
    }


    public static function categories_widget($baseurl, $selected = '')
    {

        if (!Apps::system_app_installed(App::$profile['profile_uid'], 'Categories'))
            return '';

        require_once('include/security.php');

        $sql_extra = item_permissions_sql(App::$profile['profile_uid']);

        $item_normal = item_normal();

        $terms = [];
        $r = q("select distinct(term.term) from term join item on term.oid = item.id
			where item.uid = %d
			and term.uid = item.uid
			and term.ttype = %d
			and term.otype = %d
			and item.owner_xchan = '%s'
			and item.item_wall = 1
			and item.verb != '%s'
			$item_normal
			$sql_extra
			order by term.term asc",
            intval(App::$profile['profile_uid']),
            intval(TERM_CATEGORY),
            intval(TERM_OBJ_POST),
            dbesc(App::$profile['channel_hash']),
            dbesc(ACTIVITY_UPDATE)
        );
        if ($r && count($r)) {
            foreach ($r as $rr)
                $terms[] = array('name' => $rr['term'], 'selected' => (($selected == $rr['term']) ? 'selected' : ''));

            return replace_macros(get_markup_template('categories_widget.tpl'), array(
                '$title' => t('Categories'),
                '$desc' => '',
                '$sel_all' => (($selected == '') ? 'selected' : ''),
                '$all' => t('Everything'),
                '$terms' => $terms,
                '$base' => $baseurl,

            ));
        }
        return '';
    }

}
