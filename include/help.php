<?php

use Michelf\MarkdownExtra;
use Code\Lib\IConfig;
use Code\Lib\Channel;

/**
 * @brief
 *
 * @param string $path
 * @return string|unknown
 */

function get_help_fullpath($path, $suffix = null)
{
    return find_docfile($path, App::$language);
}

function find_docfile($name, $language)
{

    foreach ([ $language, 'en' ] as $lang) {
        if (file_exists('doc/site/' . $lang . '/' . $name . '.mc')) {
            return 'doc/site/' . $lang . '/' . $name . '.mc';
        }
        if (file_exists('doc/' . $lang . '/' . $name . '.mc')) {
            return 'doc/' . $lang . '/' . $name . '.mc';
        }
    }
    if (file_exists('doc/site/' . $name . '.mc')) {
        return ('doc/site/' . $name . '.mc');
    }
    if (file_exists('doc/' . $name . '.mc')) {
        return ('doc/' . $name . '.mc');
    }

    return EMPTY_STR;
}


/**
 * @brief
 *
 * @param string $tocpath
 * @return string|unknown
 */
function get_help_content($tocpath = false)
{
    $path = '';
    if (argc() > 1) {
        for ($x = 1; $x < argc(); $x++) {
            if ($path) {
                $path .= '/';
            }
            $path .= App::$argv[$x];
        }
    }

    $fullpath = get_help_fullpath($path);

    $text = load_doc_file($fullpath);

    App::$page['title'] = t('Help');

    $content = bbcode($text);

    return translate_projectname($content);
}

/**
 * @brief
 *
 * @return bool|array
 */
function determine_help_language()
{
    require_once('library/text_languagedetect/Text/LanguageDetect.php');

    $lang_detect = new Text_LanguageDetect();
    // Set this mode to recognize language by the short code like "en", "ru", etc.
    $lang_detect->setNameMode(2);
    // If the language was specified in the URL, override the language preference
    // of the browser. Default to English if both of these are absent.
    if ($lang_detect->languageExists(argv(1))) {
        $lang = argv(1);
        $from_url = true;
    } else {
        $lang = App::$language;
        if (! isset($lang)) {
            $lang = 'en';
        }

        $from_url = false;
    }

    return array('language' => $lang, 'from_url' => $from_url);
}

function load_doc_file($s)
{

    $c = find_doc_file($s);
    if ($c) {
        return $c;
    }
    return '';
}

function find_doc_file($s)
{
    if (file_exists($s)) {
        return file_get_contents($s);
    }
    return '';
}

/**
 * @brief
 *
 * @param string $s
 * @return number|mixed|unknown|bool
 */
function search_doc_files($s)
{


    App::set_pager_itemspage(60);
    $pager_sql = sprintf(" LIMIT %d OFFSET %d ", intval(App::$pager['itemspage']), intval(App::$pager['start']));

    $regexop = db_getfunc('REGEXP');

    $r = q(
        "select iconfig.v, item.* from item left join iconfig on item.id = iconfig.iid
		where iconfig.cat = 'system' and iconfig.k = 'docfile' and
		body $regexop '%s' and item_type = %d $pager_sql",
        dbesc($s),
        intval(ITEM_TYPE_DOC)
    );

    $r = fetch_post_tags($r, true);

    for ($x = 0; $x < count($r); $x++) {
        $position = stripos($r[$x]['body'], $s);
        $dislen = 300;
        $start = $position - floor($dislen / 2);
        if ($start < 0) {
            $start = 0;
        }
        $r[$x]['text'] = substr($r[$x]['body'], $start, $dislen);

        $r[$x]['rank'] = 0;
        if ($r[$x]['term']) {
            foreach ($r[$x]['term'] as $t) {
                if (stristr($t['term'], $s)) {
                    $r[$x]['rank'] ++;
                }
            }
        }
        if (stristr($r[$x]['v'], $s)) {
            $r[$x]['rank'] ++;
        }
        $r[$x]['rank'] += substr_count(strtolower($r[$x]['text']), strtolower($s));
        // bias the results to the observer's native language
        if ($r[$x]['lang'] === App::$language) {
            $r[$x]['rank'] = $r[$x]['rank'] + 10;
        }
    }
    usort($r, 'doc_rank_sort');

    return $r;
}


function doc_rank_sort($s1, $s2)
{
    if ($s1['rank'] == $s2['rank']) {
        return 0;
    }

    return (($s1['rank'] < $s2['rank']) ? 1 : (-1));
}

/**
 * @brief
 *
 * @return string
 */

function load_context_help()
{

    $path = App::$cmd;
    $args = App::$argv;
    $lang = App::$language;

    if (! isset($lang) || !is_dir('doc/context/' . $lang . '/')) {
        $lang = 'en';
    }
    while ($path) {
        $context_help = load_doc_file('doc/context/' . $lang . '/' . $path . '/help.html');
        if (!$context_help) {
            // Fallback to English if the translation is absent
            $context_help = load_doc_file('doc/context/en/' . $path . '/help.html');
        }
        if ($context_help) {
            break;
        }

        array_pop($args);
        $path = implode('/', $args);
    }

    return $context_help;
}

/**
 * @brief
 *
 * @param string $s
 * @return void|bool|number[]|string[]|unknown[]
 */
function store_doc_file($s)
{

    if (is_dir($s)) {
        return;
    }

    $item = [];
    $sys = Channel::get_system();

    $item['aid'] = 0;
    $item['uid'] = $sys['channel_id'];

    $mimetype = 'text/x-multicode';

    require_once('include/html2plain.php');

    $item['body'] = html2plain(prepare_text(file_get_contents($s), $mimetype, [ 'cache' => true ]));
    $item['mimetype'] = 'text/plain';

    $item['plink'] = z_root() . '/' . str_replace('doc', 'help', $s);
    $item['owner_xchan'] = $item['author_xchan'] = $sys['channel_hash'];
    $item['item_type'] = ITEM_TYPE_DOC;

    $r = q(
        "select item.* from item left join iconfig on item.id = iconfig.iid
		where iconfig.cat = 'system' and iconfig.k = 'docfile' and
		iconfig.v = '%s' and item_type = %d limit 1",
        dbesc($s),
        intval(ITEM_TYPE_DOC)
    );

    IConfig::Set($item, 'system', 'docfile', $s);

    if ($r) {
        $item['id'] = $r[0]['id'];
        $item['mid'] = $item['parent_mid'] = $r[0]['mid'];
        $x = item_store_update($item);
    } else {
        $item['uuid'] = new_uuid();
        $item['mid'] = $item['parent_mid'] = z_root() . '/item/' . $item['uuid'];
        $x = item_store($item);
    }

    return $x;
}
