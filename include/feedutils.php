<?php

/**
 * @file include/feedutils.php
 * @brief Some functions to work with XML feeds.
 */

use Zotlabs\Lib\Img_filesize;
use Zotlabs\Extend\Hook;

/**
 * @brief Return an Atom feed for channel.
 *
 * @see get_feed_for()
 *
 * @param array $channel
 * @param array $params associative array which configures the feed
 * @return string with an atom feed
 */
function get_public_feed($channel, $params)
{

    if (! $params) {
        $params = [];
    }

    $params['type']        = ((x($params, 'type'))     ? $params['type']           : 'xml');
    $params['begin']       = ((x($params, 'begin'))    ? $params['begin']          : NULL_DATE);
    $params['end']         = ((x($params, 'end'))      ? $params['end']            : datetime_convert('UTC', 'UTC', 'now'));
    $params['start']       = ((x($params, 'start'))    ? $params['start']          : 0);
    $params['records']     = ((x($params, 'records'))  ? $params['records']        : 40);
    $params['direction']   = ((x($params, 'direction')) ? $params['direction']      : 'desc');
    $params['pages']       = ((x($params, 'pages'))    ? intval($params['pages'])  : 0);
    $params['top']         = ((x($params, 'top'))      ? intval($params['top'])    : 0);
    $params['cat']         = ((x($params, 'cat'))      ? $params['cat']            : '');
    $params['compat']      = ((x($params, 'compat'))   ? intval($params['compat']) : 0);


    switch ($params['type']) {
        case 'json':
            header("Content-type: application/atom+json");
            break;
        case 'xml':
        default:
            header("Content-type: application/atom+xml");
            break;
    }

    return get_feed_for($channel, get_observer_hash(), $params);
}

/**
 * @brief Create an atom feed for $channel from template.
 *
 * @param array $channel
 * @param string $observer_hash xchan_hash from observer
 * @param array $params
 * @return string with an atom feed
 */
function get_feed_for($channel, $observer_hash, $params)
{

    if (! $channel) {
        http_status_exit(401);
    }


    // logger('params: ' . print_r($params,true));


    $interactive = ((is_array($params) && array_key_exists('interactive', $params)) ? intval($params['interactive']) : 0);


    if ($params['pages']) {
        if (! perm_is_allowed($channel['channel_id'], $observer_hash, 'view_pages')) {
            if ($interactive) {
                return '';
            } else {
                http_status_exit(403);
            }
        }
    } else {
        if (! perm_is_allowed($channel['channel_id'], $observer_hash, 'view_stream')) {
            if ($interactive) {
                return '';
            } else {
                http_status_exit(403);
            }
        }
    }


    $feed_template = get_markup_template('atom_feed.tpl');

    $atom = '';

    $feed_author = '';

    $atom .= replace_macros($feed_template, array(
        '$version'       => xmlify(Zotlabs\Lib\System::get_project_version()),
        '$generator'     => xmlify(Zotlabs\Lib\System::get_platform_name()),
		'$generator_uri' => 'https://codeberg.org/' . ((PLATFORM_NAME === 'streams') ? 'streams' : 'zot') . '/' . PLATFORM_NAME,
        '$feed_id'       => xmlify($channel['xchan_url']),
        '$feed_title'    => xmlify($channel['channel_name']),
        '$feed_updated'  => xmlify(datetime_convert('UTC', 'UTC', 'now', ATOM_TIME)),
        '$author'        => $feed_author,
        '$owner'         => $owner,
        '$profile_page'  => xmlify($channel['xchan_url']),
    ));


    $x = [
            'xml' => $atom,
            'channel' => $channel,
            'observer_hash' => $observer_hash,
            'params' => $params
    ];
    /**
     * @hooks atom_feed_top
     *   * \e string \b xml - the generated feed and what will get returned from the hook
     *   * \e array \b channel
     *   * \e string \b observer_hash
     *   * \e array \b params
     */
    Hook::call('atom_feed_top', $x);

    $atom = $x['xml'];

    /**
     * @hooks atom_feed
     *   A much simpler interface than atom_feed_top.
     *   * \e string - the feed after atom_feed_top hook
     */
    Hook::call('atom_feed', $atom);

    $items = items_fetch(
        [
            'wall'       => '1',
            'datequery'  => $params['end'],
            'datequery2' => $params['begin'],
            'start'      => intval($params['start']),
            'records'    => intval($params['records']),
            'direction'  => dbesc($params['direction']),
            'pages'      => $params['pages'],
            'order'      => dbesc('post'),
            'top'        => $params['top'],
            'cat'        => $params['cat'],
            'compat'     => $params['compat']
        ],
        $channel,
        $observer_hash,
        CLIENT_MODE_NORMAL,
        App::$module
    );

    if ($items) {
        $type = 'html';
        foreach ($items as $item) {
            if ($item['item_private']) {
                continue;
            }

            $atom .= atom_entry($item, $type, null, $owner, true, '', $params['compat']);
        }
    }

    /**
     * @hooks atom_feed_end
     *   \e string - The created XML feed as a string without closing tag
     */
    Hook::call('atom_feed_end', $atom);

    $atom .= '</feed>' . "\r\n";

    return $atom;
}

/**
 * @brief Return a XML tag with author information.
 *
 * @param string $tag The XML tag to create
 * @param string $nick preferred username
 * @param string $name displayed name of the author
 * @param string $uri
 * @param int $h image height
 * @param int $w image width
 * @param string $type profile photo mime type
 * @param string $photo Fully qualified URL to a profile/avator photo
 * @return string XML tag
 */
function atom_author($tag, $nick, $name, $uri, $h, $w, $type, $photo)
{
    $o = '';
    if (! $tag) {
        return $o;
    }

    $nick = xmlify($nick);
    $name = xmlify($name);
    $uri = xmlify($uri);
    $h = intval($h);
    $w = intval($w);
    $photo = xmlify($photo);

    $o .= "<$tag>\r\n";
    $o .= "  <name>$name</name>\r\n";
    $o .= "  <uri>$uri</uri>\r\n";
    $o .= '  <link rel="photo"  type="' . $type . '" media:width="' . $w . '" media:height="' . $h . '" href="' . $photo . '" />' . "\r\n";
    $o .= '  <link rel="avatar" type="' . $type . '" media:width="' . $w . '" media:height="' . $h . '" href="' . $photo . '" />' . "\r\n";

    /**
     * @hooks atom_author
     *  Possibility to add further tags to returned XML string
     *   * \e string - The created XML tag as a string without closing tag
     */
    Hook::call('atom_author', $o);

    $o .= "</$tag>\r\n";

    return $o;
}


/**
 * @brief Return an atom tag with author information from an xchan.
 *
 * @param string $tag
 * @param array $xchan
 * @return string
 */
function atom_render_author($tag, $xchan)
{

    $nick = xmlify(substr($xchan['xchan_addr'], 0, strpos($xchan['xchan_addr'], '@')));
    $id   = xmlify($xchan['xchan_url']);
    $name = xmlify($xchan['xchan_name']);
    $photo = xmlify($xchan['xchan_photo_l']);
    $type = xmlify($xchan['xchan_photo_mimetype']);
    $w = $h = 300;

    $o = "<$tag>\r\n";
    $o .= "  <name>$name</name>\r\n";
    $o .= "  <uri>$id</uri>\r\n";
    $o .= '  <link rel="photo"  type="' . $type . '" media:width="' . $w . '" media:height="' . $h . '" href="' . $photo . '" />' . "\r\n";
    $o .= '  <link rel="avatar" type="' . $type . '" media:width="' . $w . '" media:height="' . $h . '" href="' . $photo . '" />' . "\r\n";

    /**
     * @hooks atom_render_author
     *   Possibility to add further tags to returned XML string.
     *   * \e string The created XML tag as a string without closing tag
     */
    Hook::call('atom_render_author', $o);

    $o .= "</$tag>\r\n";

    return $o;
}

function compat_photos_list($s)
{

    $ret = [];

    $found = preg_match_all('/\[[zi]mg(.*?)\](.*?)\[/ism', $s, $matches, PREG_SET_ORDER);

    if ($found) {
        foreach ($matches as $match) {
            $entry = [
                'href' => $match[2],
                'type' => guess_image_type($match[2])
            ];
            $sizer = new Img_filesize($match[2]);
            $size = $sizer->getSize();
            if (intval($size)) {
                $entry['length'] = intval($size);
            }

            $ret[] = $entry;
        }
    }

    return $ret;
}


/**
 * @brief Create an item for the Atom feed.
 *
 * @see get_feed_for()
 *
 * @param array $item
 * @param string $type
 * @param array $author
 * @param array $owner
 * @param string $comment default false
 * @param number $cid default 0
 * @param bool $compat default false
 * @return void|string
 */
function atom_entry($item, $type, $author, $owner, $comment = false, $cid = 0, $compat = false)
{

    if (! $item['parent']) {
        return;
    }

    if ($item['deleted']) {
        return '<at:deleted-entry ref="' . xmlify($item['mid']) . '" when="' . xmlify(datetime_convert('UTC', 'UTC', $item['edited'] . '+00:00', ATOM_TIME)) . '" />' . "\r\n";
    }

    create_export_photo_body($item);

    // provide separate summary and content unless compat is true; as summary represents a content-warning on some networks

    $summary = $item['summary'];

    $body = $item['body'];

    $compat_photos = null;


    $o = "\r\n\r\n<entry>\r\n";

    if (is_array($author)) {
        $o .= atom_render_author('author', $author);
    } else {
        $o .= atom_render_author('author', $item['author']);
    }

    if (($item['parent'] != $item['id']) || ($item['parent_mid'] !== $item['mid']) || (($item['thr_parent'] !== '') && ($item['thr_parent'] !== $item['mid']))) {
        $parent_item = (($item['thr_parent']) ? $item['thr_parent'] : $item['parent_mid']);

        $o .= '<thr:in-reply-to ref="' . xmlify($parent_item) . '" type="text/html" href="' .  xmlify($item['plink']) . '" />' . "\r\n";
    } else {
        $o .= '<title>' . xmlify($item['title']) . '</title>' . "\r\n";
        if ($summary) {
            $o .= '<summary type="' . $type . '" >' . xmlify(prepare_text($summary, $item['mimetype'])) . '</summary>' . "\r\n";
        }
        $o .= '<content type="' . $type . '" >' . xmlify(prepare_text($body, $item['mimetype'])) . '</content>' . "\r\n";
    }

    $o .= '<id>' . xmlify($item['mid']) . '</id>' . "\r\n";
    $o .= '<published>' . xmlify(datetime_convert('UTC', 'UTC', $item['created'] . '+00:00', ATOM_TIME)) . '</published>' . "\r\n";
    $o .= '<updated>' . xmlify(datetime_convert('UTC', 'UTC', $item['edited'] . '+00:00', ATOM_TIME)) . '</updated>' . "\r\n";

    $o .= '<link rel="alternate" type="text/html" href="' . xmlify($item['plink']) . '" />' . "\r\n";


    if ($item['attach']) {
        $enclosures = json_decode($item['attach'], true);
        if ($enclosures) {
            foreach ($enclosures as $enc) {
                $o .= '<link rel="enclosure" '
                . (($enc['href']) ? 'href="' . $enc['href'] . '" ' : '')
                . (($enc['length']) ? 'length="' . $enc['length'] . '" ' : '')
                . (($enc['type']) ? 'type="' . $enc['type'] . '" ' : '')
                . ' />' . "\r\n";
            }
        }
    }

    if ($item['term']) {
        foreach ($item['term'] as $term) {
            $scheme = '';
            $label = '';
            switch ($term['ttype']) {
                case TERM_HASHTAG:
                    $scheme = NAMESPACE_ZOT . '/term/hashtag';
                    $label = '#' . str_replace('"', '', $term['term']);
                    break;
                case TERM_CATEGORY:
                    $scheme = NAMESPACE_ZOT . '/term/category';
                    $label = str_replace('"', '', $term['term']);
                    break;
                default:
                    break;
            }
            if (! $scheme) {
                continue;
            }

            $o .= '<category scheme="' . $scheme . '" term="' . str_replace('"', '', $term['term']) . '" label="' . $label . '" />' . "\r\n";
        }
    }

    $o .= '</entry>' . "\r\n";

    // build array to pass to hook
    $x = [
        'item'     => $item,
        'type'     => $type,
        'author'   => $author,
        'owner'    => $owner,
        'comment'  => $comment,
        'abook_id' => $cid,
        'entry'    => $o
    ];
    /**
     * @hooks atom_entry
     *   * \e array \b item
     *   * \e string \b type
     *   * \e array \b author
     *   * \e array \b owner
     *   * \e string \b comment
     *   * \e number \b abook_id
     *   * \e string \b entry - The generated entry and what will get returned
     */
    Hook::call('atom_entry', $x);

    return $x['entry'];
}

function get_mentions($item, $tags)
{
    $o = '';

    if (! count($tags)) {
        return $o;
    }

    foreach ($tags as $x) {
        if ($x['ttype'] == TERM_MENTION) {
            $o .= "\t\t" . '<link rel="mentioned" href="' . $x['url'] . '" />' . "\r\n";
        }
    }
    return $o;
}
