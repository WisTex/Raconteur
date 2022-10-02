<?php

/** @file */

use Code\Lib\Apps;
use Code\Lib\LibBlock;
use Code\Lib\ThreadStream;
use Code\Lib\ThreadItem;
use Code\Lib\Channel;
use Code\Lib\Features;
use Code\Extend\Hook;
use Code\Access\Permissions;
use Code\Access\PermissionLimits;
use Code\Render\Theme;

/**
 * Render actions localized
 */

function localize_item(&$item)
{

    if (activity_match($item['verb'], ACTIVITY_LIKE) || activity_match($item['verb'], ACTIVITY_DISLIKE) || $item['verb'] === 'Announce') {
        if (! $item['obj']) {
            return;
        }

        if (intval($item['item_thread_top'])) {
            return;
        }

        $obj = ((is_array($item['obj'])) ? $item['obj'] : json_decode($item['obj'], true));
        if (! is_array($obj)) {
            logger('localize_item: failed to decode object: ' . print_r($item['obj'], true));
            return;
        }

        if (isset($obj['actor']) && is_string($obj['actor']) && $obj['actor']) {
            $author_link = $obj['actor'];
        } elseif (isset($obj['attributedTo']) && is_string($obj['attributedTo']) && $obj['attributedTo']) {
            $author_link = $obj['attributedTo'];
        }
        else {
            $author_link = EMPTY_STR;
        }

        $author_name = ((array_path_exists('author/name',$obj) && $obj['author']['name']) ? $obj['author']['name'] : '');

        if (isset($obj['link'])) {
            $item_url = get_rel_link($obj['link'], 'alternate');
        }
        $Bphoto = '';

        switch ($obj['type']) {
            case ACTIVITY_OBJ_PHOTO:
                $post_type = t('photo');
                break;
            case ACTIVITY_OBJ_EVENT:
                $post_type = t('event');
                break;
            case ACTIVITY_OBJ_PERSON:
                $post_type = t('channel');
                $author_name = $obj['title'];
                if ($obj['link']) {
                    $author_link  = get_rel_link($obj['link'], 'alternate');
                    $Bphoto = get_rel_link($obj['link'], 'photo');
                }
                break;
            case ACTIVITY_OBJ_THING:
                $post_type = $obj['title'];
                if ($obj['owner']) {
                    if (array_key_exists('name', $obj['owner'])) {
                        $author_name = $obj['owner']['name'];
                    }
                    if (array_key_exists('link', $obj['owner'])) {
                        $author_link = get_rel_link($obj['owner']['link'], 'alternate');
                    }
                }
                if ($obj['link']) {
                    $Bphoto = get_rel_link($obj['link'], 'photo');
                }
                break;

            case ACTIVITY_OBJ_NOTE:
            default:
                $post_type = t('post');
                if ($obj['id'] != $obj['parent']) {
                    $post_type = t('comment');
                }
                break;
        }

        // If we couldn't parse something useful, don't bother translating.
        // We need something better than zid here, probably magic_link(), but it needs writing

        if ($author_link && $author_name && $item_url) {
            $author  = '[zrl=' . chanlink_url($item['author']['xchan_url']) . ']' . $item['author']['xchan_name'] . '[/zrl]';
            $objauthor =  '[zrl=' . chanlink_url($author_link) . ']' . $author_name . '[/zrl]';

            $plink = '[zrl=' . zid($item_url) . ']' . $post_type . '[/zrl]';

            if (activity_match($item['verb'], ACTIVITY_LIKE)) {
                $bodyverb = t('%1$s likes %2$s\'s %3$s');
            } elseif (activity_match($item['verb'], ACTIVITY_DISLIKE)) {
                $bodyverb = t('%1$s doesn\'t like %2$s\'s %3$s');
            } elseif ($item['verb'] === 'Announce') {
                $bodyverb = t('%1$s repeated %2$s\'s %3$s');
            }

            // short version, in notification strings the author will be displayed separately

            if (activity_match($item['verb'], ACTIVITY_LIKE)) {
                $shortbodyverb = t('likes %1$s\'s %2$s');
            } elseif (activity_match($item['verb'], ACTIVITY_DISLIKE)) {
                $shortbodyverb = t('doesn\'t like %1$s\'s %2$s');
            } elseif ($item['verb'] === 'Announce') {
                $shortbodyverb = t('repeated %1$s\'s %2$s');
            }

            if ($shortbodyverb) {
                $item['shortlocalize'] = sprintf($shortbodyverb, $objauthor, $plink);
            }

            $item['body'] = $item['localize'] = sprintf($bodyverb, $author, $objauthor, $plink);
            if ($Bphoto != "") {
                $item['body'] .= "\n\n\n" . '[zrl=' . chanlink_url($author_link) . '][zmg width=&quot;80&quot; height=&quot;80&quot;]' . $Bphoto . '[/zmg][/zrl]';
            }
        }
    }

    if (activity_match($item['verb'], ACTIVITY_FRIEND)) {
        if ($item['obj_type'] == "" || $item['obj_type'] !== ACTIVITY_OBJ_PERSON) {
            return;
        }

        $Aname = $item['author']['xchan_name'];
        $Alink = $item['author']['xchan_url'];


        $obj = json_decode($item['obj'], true);

        $Blink = $Bphoto = '';

        if ($obj['link']) {
            $Blink  = get_rel_link($obj['link'], 'alternate');
            $Bphoto = get_rel_link($obj['link'], 'photo');
        }
        $Bname = $obj['title'];


        $A = '[zrl=' . chanlink_url($Alink) . ']' . $Aname . '[/zrl]';
        $B = '[zrl=' . chanlink_url($Blink) . ']' . $Bname . '[/zrl]';
        if ($Bphoto != "") {
            $Bphoto = '[zrl=' . chanlink_url($Blink) . '][zmg=80x80]' . $Bphoto . '[/zmg][/zrl]';
        }

        $item['body'] = $item['localize'] = sprintf(t('%1$s is now connected with %2$s'), $A, $B);
        $item['body'] .= "\n\n\n" . $Bphoto;
    }

    if (stristr($item['verb'], ACTIVITY_POKE)) {

        $verb = urldecode(substr($item['verb'], strpos($item['verb'], '#') + 1));
        if (! $verb) {
            return;
        }

        if ($item['obj_type'] == "" || $item['obj_type'] !== ACTIVITY_OBJ_PERSON) {
            return;
        }

        $Aname = $item['author']['xchan_name'];
        $Alink = $item['author']['xchan_url'];

        $obj = json_decode($item['obj'], true);

        $Blink = $Bphoto = '';

        if ($obj['link']) {
            $Blink  = get_rel_link($obj['link'], 'alternate');
            $Bphoto = get_rel_link($obj['link'], 'photo');
        }
        $Bname = $obj['title'];

        $A = '[zrl=' . chanlink_url($Alink) . ']' . $Aname . '[/zrl]';
        $B = '[zrl=' . chanlink_url($Blink) . ']' . $Bname . '[/zrl]';
        if ($Bphoto != "") {
            $Bphoto = '[zrl=' . chanlink_url($Blink) . '][zmg=80x80]' . $Bphoto . '[/zmg][/zrl]';
        }

        // we can't have a translation string with three positions but no distinguishable text
        // So here is the translate string.

        $txt = t('%1$s poked %2$s');

        // now translate the verb

        $txt = str_replace(t('poked'), t($verb), $txt);

        // then do the sprintf on the translation string

        $item['body'] = $item['localize'] = sprintf($txt, $A, $B);
        $item['body'] .= "\n\n\n" . $Bphoto;
    }
    if (stristr($item['verb'], ACTIVITY_MOOD)) {
        $verb = urldecode(substr($item['verb'], strpos($item['verb'], '#') + 1));
        if (! $verb) {
            return;
        }

        $Aname = $item['author']['xchan_name'];
        $Alink = $item['author']['xchan_url'];

        $A = '[zrl=' . chanlink_url($Alink) . ']' . $Aname . '[/zrl]';

        $txt = t('%1$s is %2$s', 'mood');

        $item['body'] = sprintf($txt, $A, t($verb));
    }
}

/**
 * @brief Count the total of comments on this item and its desendants.
 *
 * @param array $item an assoziative item-array which provides:
 *  * \e array \b children
 * @return number
 */

function count_descendants($item)
{

    $total = count($item['children']);

    if ($total > 0) {
        foreach ($item['children'] as $child) {
            if (! visible_activity($child)) {
                $total--;
            }

            $total += count_descendants($child);
        }
    }

    return $total;
}

/**
 * @brief Check if the activity of the item is visible.
 *
 * likes (etc.) can apply to other things besides posts. Check if they are post
 * children, in which case we handle them specially. Activities which are unrecognised
 * as having special meaning and hidden will be treated as posts or comments and visible
 * in the stream.
 *
 * @param array $item
 * @return bool
 */
function visible_activity($item)
{
    $hidden_activities = [ ACTIVITY_LIKE, ACTIVITY_DISLIKE, 'Undo' ];

    if (intval($item['item_notshown'])) {
        return false;
    }

    if ($item['obj_type'] === 'Answer') {
        return false;
    }

    // This is an experiment at group federation with microblog platforms.
    // We need the Announce or "boost" for group replies by non-connections to end up in the personal timeline
    // of those patforms. Hide them on our own platform because they make the conversation look like dung.
    // Performance wise this is a mess because we need to send two activities for every group comment.

    if ($item['verb'] === 'Announce' && $item['author_xchan'] === $item['owner_xchan']) {
        return false;
    }

    foreach ($hidden_activities as $act) {
        if ((activity_match($item['verb'], $act)) && ($item['mid'] != $item['parent_mid'])) {
            return false;
        }
    }

    if (in_array($item['obj_type'], [ 'Event', 'Invite' ]) && in_array($item['verb'], [ 'Accept', 'Reject', 'TentativeAccept', 'TentativeReject', 'Ignore' ])) {
        return false;
    }

    return true;
}

/**
 * @brief "Render" a conversation or list of items for HTML display.
 *
 * There are two major forms of display:
 *  - Sequential or unthreaded ("New Item View" or search results)
 *  - conversation view
 *
 * The $mode parameter decides between the various renderings and also
 * figures out how to determine page owner and other contextual items
 * that are based on unique features of the calling module.
 *
 * @param array $items
 * @param string $mode
 * @param bool $update
 * @param string $page_mode default traditional
 * @param string $prepared_item
 * @return string
 */
function conversation($items, $mode, $update, $page_mode = 'traditional', $prepared_item = '')
{

    $content_html = '';
    $o = '';

    require_once('bbcode.php');

    $ssl_state = (bool)local_channel();

    if (local_channel()) {
        load_pconfig(local_channel());
    }

    $profile_owner   = 0;
    $page_writeable  = false;
    $live_update_div = '';
    $jsreload        = '';

    $preview = $page_mode === 'preview';
    $previewing = (($preview) ? ' preview ' : '');
    $preview_lbl = t('This is an unsaved preview');

    if (in_array($mode, [ 'stream', 'pubstream'])) {
        $profile_owner = local_channel();
        $page_writeable = (bool)local_channel();

        if (!$update) {
            // The special div is needed for liveUpdate to kick in for this page.
            // We only launch liveUpdate if you aren't filtering in some incompatible
            // way and also you aren't writing a comment (discovered in javascript).

            $live_update_div = '<div id="live-stream"></div>' . "\r\n"
                . "<script> let profile_uid = " . ((isset($_SESSION['uid'])) ? intval($_SESSION['uid']) : 0)
                . "; let netargs = '" . substr(App::$cmd, 8)
                . '?f='
                . ((x($_GET, 'cid'))    ? '&cid='    . $_GET['cid']    : '')
                . ((x($_GET, 'search')) ? '&search=' . $_GET['search'] : '')
                . ((x($_GET, 'star'))   ? '&star='   . $_GET['star']   : '')
                . ((x($_GET, 'order'))  ? '&order='  . $_GET['order']  : '')
                . ((x($_GET, 'bmark'))  ? '&bmark='  . $_GET['bmark']  : '')
                . ((x($_GET, 'liked'))  ? '&liked='  . $_GET['liked']  : '')
                . ((x($_GET, 'conv'))   ? '&conv='   . $_GET['conv']   : '')
                . ((x($_GET, 'spam'))   ? '&spam='   . $_GET['spam']   : '')
                . ((x($_GET, 'nets'))   ? '&nets='   . $_GET['nets']   : '')
                . ((x($_GET, 'cmin'))   ? '&cmin='   . $_GET['cmin']   : '')
                . ((x($_GET, 'cmax'))   ? '&cmax='   . $_GET['cmax']   : '')
                . ((x($_GET, 'file'))   ? '&file='   . $_GET['file']   : '')
                . ((x($_GET, 'uri'))    ? '&uri='    . $_GET['uri']   : '')
                . ((x($_GET, 'pf'))     ? '&pf='     . $_GET['pf']   : '')
                . "'; let profile_page = " . App::$pager['page'] . "; </script>\r\n";
        }
    } elseif ($mode === 'channel') {
        $profile_owner = App::$profile['profile_uid'];
        $page_writeable = ($profile_owner == local_channel());

        if (!$update) {
            $tab = notags(trim($_GET['tab']));
            if ($tab === 'posts') {
                // This is ugly, but we can't pass the profile_uid through the session to the ajax updater,
                // because browser prefetching might change it on us. We have to deliver it with the page.

                $live_update_div = '<div id="live-channel"></div>' . "\r\n"
                    . "<script> let profile_uid = " . App::$profile['profile_uid']
                    . "; let netargs = '?f='; let profile_page = " . App::$pager['page'] . "; </script>\r\n";
            }
        }
    } elseif ($mode === 'display') {
        $profile_owner = local_channel();
        $page_writeable = false;
        $live_update_div = '<div id="live-display"></div>' . "\r\n";
    } elseif ($mode === 'page') {
        $profile_owner = App::$profile['uid'];
        $page_writeable = ($profile_owner == local_channel());
        $live_update_div = '<div id="live-page"></div>' . "\r\n";
    } elseif ($mode === 'search') {
        $live_update_div = '<div id="live-search"></div>' . "\r\n";
    } elseif ($mode === 'moderate') {
        $profile_owner = local_channel();
    } elseif ($mode === 'photos') {
        $profile_owner = App::$profile['profile_uid'];
        $page_writeable = ($profile_owner == local_channel());
        $live_update_div = '<div id="live-photos"></div>' . "\r\n";
        // for photos we've already formatted the top-level item (the photo)
        $content_html = App::$data['photo_html'];
    }

    $page_dropping = ((local_channel() && local_channel() == $profile_owner) ? true : false);

    if (! Features::enabled($profile_owner, 'multi_delete')) {
        $page_dropping = false;
    }

    $uploading = ((local_channel()) ? true : false);

    $channel = App::get_channel();
    $observer = App::get_observer();

    if ($update && isset($_SESSION['return_url'])) {
        $return_url = $_SESSION['return_url'];
    } else {
        $return_url = $_SESSION['return_url'] = App::$query_string;
    }

    load_contact_links(local_channel());

    $cb = ['items' => $items, 'mode' => $mode, 'update' => $update, 'preview' => $preview];
    Hook::call('conversation_start', $cb);

    $items = $cb['items'];

    $conv_responses = [
        'like'        => [ 'title' => t('Likes', 'title') ],
        'dislike'     => [ 'title' => t('Dislikes', 'title') ],
        'attendyes'   => [ 'title' => t('Attending', 'title') ],
        'attendno'    => [ 'title' => t('Not attending', 'title') ],
        'attendmaybe' => [ 'title' => t('Might attend', 'title') ]
    ];


    // array with html for each thread (parent+comments)
    $threads = [];
    $threadsid = -1;

    $page_template = Theme::get_template("conversation.tpl");

    if ($items) {
        if (in_array($mode, [ 'stream-new', 'search', 'community', 'moderate' ])) {
            // "New Item View" on stream page or search page results
            // - just loop through the items and format them minimally for display

            $tpl = 'search_item.tpl';

            foreach ($items as $item) {
                $x = [
                    'mode' => $mode,
                    'item' => $item
                ];
                Hook::call('stream_item', $x);

                $item = $x['item'];

                $threadsid++;

                $comment     = '';
                $owner_url   = '';
                $owner_photo = '';
                $owner_name  = '';
                $sparkle     = '';
                $is_new      = false;

                if ($mode === 'search' || $mode === 'community') {
                    if (
                        ((activity_match($item['verb'], ACTIVITY_LIKE)) || (activity_match($item['verb'], ACTIVITY_DISLIKE)))
                        && ($item['id'] != $item['parent'])
                    ) {
                        continue;
                    }
                }

                $sp = false;
//              $profile_link = best_link_url($item,$sp);
//              if($sp)
//                  $sparkle = ' sparkle';
//              else
//                  $profile_link = zid($profile_link);

                $profile_name = $item['author']['xchan_name'];
                $profile_link = $item['author']['xchan_url'];
                $profile_avatar = $item['author']['xchan_photo_m'];

                if ($item['mid'] === $item['parent_mid'] && $item['author_xchan'] !== $item['owner_xchan']) {
                    $owner_name = $item['owner']['xchan_name'];
                    $owner_url = $item['owner']['xchan_url'];
                    $owner_photo = $item['owner']['xchan_photo'];
                }


                $location = format_location($item);

                localize_item($item);
                if ($mode === 'stream-new') {
                    $dropping = true;
                } else {
                    $dropping = false;
                }

                $drop = [
                    'pagedropping' => $page_dropping,
                    'dropping' => $dropping,
                    'select' => t('Select'),
                    'delete' => t('Delete'),
                ];

                $star = [
                    'toggle' => t("Toggle Star Status"),
                    'isstarred' => ((intval($item['item_starred'])) ? true : false),
                ];

                $lock = t('Public visibility');
                if (intval($item['item_private']) === 2) {
                    $lock = t('Direct message (private mail)');
                }
                if (intval($item['item_private']) === 1) {
                    $lock = t('Restricted visibility');
                }

                $locktype = intval($item['item_private']);

                $likebuttons = false;
                $shareable = false;

                $verified = (intval($item['item_verified']) ? t('Message signature validated') : '');
                $forged = ((($item['sig']) && (! intval($item['item_verified']))) ? t('Message signature incorrect') : '');

                $unverified = '';

//              $tags=[];
//              $terms = get_terms_oftype($item['term'],array(TERM_HASHTAG,TERM_MENTION,TERM_UNKNOWN,TERM_COMMUNITYTAG));
//              if(count($terms))
//                  foreach($terms as $tag)
//                      $tags[] = format_term_for_display($tag);

                $body = prepare_body($item, true);

                $has_tags = (($body['tags'] || $body['categories'] || $body['mentions'] || $body['attachments'] || $body['folders']) ? true : false);

                if (strcmp(datetime_convert('UTC', 'UTC', $item['created']), datetime_convert('UTC', 'UTC', 'now - 12 hours')) > 0) {
                    $is_new = true;
                }

                $conv_link_mid = (($mode == 'moderate') ? $item['parent_mid'] : $item['mid']);

                $conv_link = ((in_array($item['item_type'], [ ITEM_TYPE_CARD, ITEM_TYPE_ARTICLE])) ? $item['plink'] : z_root() . '/display/?mid=' . gen_link_id($conv_link_mid));

                $allowed_type = (in_array($item['item_type'], get_config('system', 'pin_types', [ ITEM_TYPE_POST ])) ? true : false);
                $pinned_items = ($allowed_type ? get_pconfig($item['uid'], 'pinned', $item['item_type'], []) : []);
                $pinned = ((! empty($pinned_items) && in_array($item['mid'], $pinned_items)) ? true : false);

                $tmp_item = [
                    'template' => $tpl,
                    'toplevel' => 'toplevel_item',
                    'item_type' => intval($item['item_type']),
                    'mode' => $mode,
                    'approve' => t('Approve'),
                    'delete' => t('Delete'),
                    'preview_lbl' => $preview_lbl,
                    'id' => (($preview) ? 'P0' : $item['item_id']),
                    'linktitle' => sprintf(t('View %s\'s profile @ %s'), $profile_name, $profile_link),
                    'profile_url' => $profile_link,
                    'thread_action_menu' => thread_action_menu($item, $mode),
                    'thread_author_menu' => thread_author_menu($item, $mode),
                    'name' => $profile_name,
                    'sparkle' => $sparkle,
                    'lock' => $lock,
                    'locktype' => $locktype,
                    'thumb' => $profile_avatar,
                    'title' => $item['title'],
                    'body' => $body['html'],
                    'event' => $body['event'],
                    'photo' => $body['photo'],
                    'tags' => $body['tags'],
                    'categories' => $body['categories'],
                    'mentions' => $body['mentions'],
                    'attachments' => $body['attachments'],
                    'folders' => $body['folders'],
                    'verified' => $verified,
                    'unverified' => $unverified,
                    'forged' => $forged,
                    'repeated' => ($item['verb'] === 'Announce'),
                    'txt_cats' => t('Categories:'),
                    'txt_folders' => t('Filed under:'),
                    'has_cats' => (($body['categories']) ? 'true' : ''),
                    'has_folders' => (($body['folders']) ? 'true' : ''),
                    'text' => strip_tags($body['html']),
                    'via' => t('via'),
                    'ago' => relative_date($item['created']),
                    'app' => $item['app'],
                    'str_app' => sprintf(t('from %s'), $item['app']),
                    'isotime' => datetime_convert('UTC', date_default_timezone_get(), $item['created'], 'c'),
                    'localtime' => datetime_convert('UTC', date_default_timezone_get(), $item['created'], 'r'),
                    'editedtime' => (($item['edited'] != $item['created']) ? sprintf(t('last edited: %s'), datetime_convert('UTC', date_default_timezone_get(), $item['edited'], 'r')) : ''),
                    'expiretime' => (($item['expires'] > NULL_DATE) ? sprintf(t('Expires: %s'), datetime_convert('UTC', date_default_timezone_get(), $item['expires'], 'r')) : ''),
                    'location' => $location,
                    'divider' => false,
                    'indent' => '',
                    'owner_name' => $owner_name,
                    'owner_url' => $owner_url,
                    'owner_photo' => $owner_photo,
                    'plink' => get_plink($item, false),
                    'edpost' => false,
                    'star' => ((Features::enabled(local_channel(), 'star_posts')) ? $star : ''),
                    'drop' => $drop,
                    'vote' => $likebuttons,
                    'like' => '',
                    'dislike' => '',
                    'comment' => '',
                    'pinned'    => ($pinned ? t('Pinned post') : ''),
                    'pinnable'  => (($item['mid'] === $item['parent_mid'] && local_channel() && $item['owner_xchan'] == $observer['xchan_hash'] && $allowed_type && $item['item_private'] == 0) ? '1' : ''),
                    'pinme'     => ($pinned ? t('Unpin this post') : t('Pin this post')),
                    'conv' => (($preview) ? '' : ['href' => $conv_link, 'title' => t('View Conversation')]),
                    'previewing' => $previewing,
                    'wait' => t('Please wait'),
                    'thread_level' => 1,
                    'has_tags' => $has_tags,
                    'is_new' => $is_new
                ];

                $arr = ['item' => $item, 'output' => $tmp_item];
                Hook::call('display_item', $arr);

//              $threads[$threadsid]['id'] = $item['item_id'];
                $threads[] = $arr['output'];
            }
        } else {
            // Normal View
//          logger('conv: items: ' . print_r($items,true));

            $conv = new ThreadStream($mode, $preview, $uploading, $prepared_item);

            // In the display mode we don't have a profile owner.

            if ($mode === 'display' && $items) {
                $conv->set_profile_owner($items[0]['uid']);
            }

            // get all the topmost parents
            // this shouldn't be needed, as we should have only them in our array
            // But for now, this array respects the old style, just in case

            $threads = [];
            foreach ($items as $item) {
                $x = [ 'mode' => $mode, 'item' => $item ];
                Hook::call('stream_item', $x);

                $item = $x['item'];

                builtin_activity_puller($item, $conv_responses);

                if (! visible_activity($item)) {
                    continue;
                }


                $item['pagedrop'] = $page_dropping;

                if ($item['id'] == $item['parent']) {
                    $item_object = new ThreadItem($item);
                    $conv->add_thread($item_object);
                    if (($page_mode === 'list') || ($page_mode === 'pager_list')) {
                        $item_object->set_template('conv_list.tpl');
                        $item_object->set_display_mode('list');
                    }
                    if ($mode === 'cards' || $mode === 'articles') {
                        $item_object->set_reload($jsreload);
                    }
                }
            }

            $threads = $conv->get_template_data($conv_responses);
            if (!$threads) {
                logger('[ERROR] conversation : Failed to get template data.', LOGGER_DEBUG);
                $threads = [];
            }
            //logger('threads: ' . print_r($threads,true), LOGGER_DATA);
        }
    }

    if (in_array($page_mode, [ 'traditional', 'preview', 'pager_list'])) {
        $page_template = Theme::get_template("threaded_conversation.tpl");
    } elseif ($update) {
        $page_template = Theme::get_template("convobj.tpl");
    } else {
        $page_template = Theme::get_template("conv_frame.tpl");
        $threads = null;
    }

//  if($page_mode === 'preview')
//      logger('preview: ' . print_r($threads,true));

//  Do not un-comment if smarty3 is in use
//  logger('page_template: ' . $page_template);

//  logger('nouveau: ' . print_r($threads,true));

// logger('page_template: ' . print_r($page_template,true));
    $o .= replace_macros($page_template, [
        '$baseurl' => z_root(),
        '$photo_item' => $content_html,
        '$live_update' => $live_update_div,
        '$remove' => t('remove'),
        '$mode' => $mode,
        '$user' => App::$user,
        '$threads' => $threads,
        '$wait' => t('Loading...'),
        '$dropping' => ($page_dropping ? t('Delete Selected Items') : false),
    ]);

    return $o;
}


function best_link_url($item)
{

    $best_url = '';
    $sparkle  = false;

    $clean_url = normalise_link($item['author-link']);

    if ((local_channel()) && (local_channel() == $item['uid'])) {
        if (isset(App::$contacts) && x(App::$contacts, $clean_url)) {
            $best_url = App::$contacts[$clean_url]['url'];
        }
    }
    if (! $best_url) {
        if (strlen($item['author-link'])) {
            $best_url = $item['author-link'];
        } else {
            $best_url = $item['url'];
        }
    }

    return $best_url;
}



function thread_action_menu($item, $mode = '')
{

    $menu = [];

    if ((local_channel()) && local_channel() == $item['uid']) {
        $menu[] = [
            'menu' => 'view_source',
            'title' => t('View Source'),
            'icon' => 'code',
            'action' => 'viewsrc(' . $item['id'] . '); return false;',
            'href' => '#'
        ];

        if (! in_array($mode, [ 'stream-new', 'search', 'community'])) {
            if ($item['parent'] == $item['id'] && (get_observer_hash() != $item['author_xchan'])) {
                $menu[] = [
                    'menu' => 'follow_thread',
                    'title' => t('Follow Thread'),
                    'icon' => 'plus',
                    'action' => 'dosubthread(' . $item['id'] . '); return false;',
                    'href' => '#'
                ];
            }

            $menu[] = [
                'menu' => 'unfollow_thread',
                'title' => t('Unfollow Thread'),
                'icon' => 'minus',
                'action' => 'dounsubthread(' . $item['id'] . '); return false;',
                'href' => '#'
            ];
        }
    }

    $args = [ 'item' => $item, 'mode' => $mode, 'menu' => $menu ];
    Hook::call('thread_action_menu', $args);

    return $args['menu'];
}

function author_is_pmable($xchan, $abook)
{

    $x = [ 'xchan' => $xchan, 'abook' => $abook, 'result' => 'unset' ];
    Hook::call('author_is_pmable', $x);
    if ($x['result'] !== 'unset') {
        return $x['result'];
    }
    if (in_array($xchan['xchan_network'],['nomad','zot6']) && get_observer_hash()) {
        return true;
    }
    return false;
}


function thread_author_menu($item, $mode = '')
{

    $menu = [];

    $local_channel = local_channel();

    if ($local_channel) {
        if (! count(App::$contacts)) {
            load_contact_links($local_channel);
        }
        $channel = App::get_channel();
    }

    $profile_link = chanlink_hash($item['author_xchan']);
    $contact = false;

    if ($channel['channel_hash'] !== $item['author_xchan']) {
        if (App::$contacts && array_key_exists($item['author_xchan'], App::$contacts)) {
            $contact = App::$contacts[$item['author_xchan']];
        } else {
            if ($local_channel && (! in_array($item['author']['xchan_network'], [ 'rss', 'anon','token','unknown' ]))) {
                $follow_url = z_root() . '/follow/?f=&url=' . urlencode(($item['author']['xchan_addr']) ? $item['author']['xchan_addr'] : $item['author']['xchan_url']) . '&interactive=0';
            }
        }
    }

    $poke_label = ucfirst(t(get_pconfig($local_channel, 'system', 'pokeverb', 'poke')));

    if ($contact) {
        if (! (isset($contact['abook_self']) && intval($contact['abook_self']))) {
            $contact_url = z_root() . '/connedit/' . $contact['abook_id'];
        }
        $posts_link = z_root() . '/stream/?cid=' . $contact['abook_id'];
        $clean_url = $item['author']['xchan_url'];
    }

    $can_dm = false;

    if ($local_channel && $contact) {
        $can_dm = perm_is_allowed($local_channel, $item['author_xchan'], 'post_mail') && intval($contact['xchan_type']) !== XCHAN_TYPE_GROUP ;
    } elseif ($item['author']['xchan_network'] === 'activitypub') {
        $can_dm = true;
    }
    if ($can_dm) {
        $pm_url = z_root()
        . '/rpost?to='
        . urlencode($item['author_xchan']);
    }

    if ($profile_link) {
        $menu[] = [
            'menu' => 'view_profile',
            'title' => t('Visit'),
            'icon' => 'fw',
            'action' => '',
            'href' => $profile_link
        ];
    }

    if (isset($posts_link) && $posts_link) {
        $menu[] = [
            'menu' => 'view_posts',
            'title' => t('Recent Activity'),
            'icon' => 'fw',
            'action' => '',
            'href' => $posts_link
        ];
    }

    if (isset($follow_url) && $follow_url) {
        $menu[] = [
            'menu' => 'follow',
            'title' => t('Connect'),
            'icon' => 'fw',
            'action' => 'doFollowAuthor(\'' . $follow_url . '\'); return false;',
            'href' => '#',
        ];
    }

    if (isset($contact_url) && $contact_url) {
        $menu[] = [
            'menu' => 'connedit',
            'title' => t('Edit Connection'),
            'icon' => 'fw',
            'action' => '',
            'href' => $contact_url
        ];
    }

    if (isset($pm_url) && $pm_url) {
        $menu[] = [
            'menu' => 'prv_message',
            'title' => t('Direct Message'),
            'icon' => 'fw',
            'action' => '',
            'href' => $pm_url
        ];
    }

    if (Apps::system_app_installed($local_channel, 'Poke')) {
        $menu[] = [
            'menu'   => 'poke',
            'title'  => $poke_label,
            'icon'   => 'fw',
            'action' => 'doPoke(\'' . urlencode($item['author_xchan']) . '\'); return false;',
            'href'   => '#'
        ];
    }

    if (local_channel()) {
        $menu[] = [
            'menu'   => 'superblocksite',
            'title'  => t('Block author\'s site'),
            'icon'   => 'fw',
            'action' => 'blocksite(\'' . urlencode($item['author_xchan']) . '\',' . $item['id'] . '); return false;',
            'href'   => '#'
        ];
        $menu[] = [
            'menu'   => 'superblock',
            'title'  => t('Block author'),
            'icon'   => 'fw',
            'action' => 'superblock(\'' . urlencode($item['author_xchan']) . '\',' . $item['id'] . '); return false;',
            'href'   => '#'
        ];
    }

    $args = [ 'item' => $item, 'mode' => $mode, 'menu' => $menu ];
    Hook::call('thread_author_menu', $args);

    return $args['menu'];
}





/**
 * @brief Checks item to see if it is one of the builtin activities (like/dislike, event attendance, consensus items, etc.)
 *
 * Increments the count of each matching activity and adds a link to the author as needed.
 *
 * @param array $item
 * @param array &$conv_responses (already created with builtin activity structure)
 */
function builtin_activity_puller($item, &$conv_responses)
{

    // if this item is a post or comment there's nothing for us to do here, just return.

    if (in_array($item['verb'], ['Create'])) {
        return;
    }


    foreach ($conv_responses as $mode => $v) {
        $url = '';

        switch ($mode) {
            case 'like':
                $verb = ACTIVITY_LIKE;
                break;
            case 'dislike':
                $verb = ACTIVITY_DISLIKE;
                break;
            case 'attendyes':
                $verb = 'Accept';
                break;
            case 'attendno':
                $verb = 'Reject';
                break;
            case 'attendmaybe':
                $verb = 'TentativeAccept';
                break;
            default:
                return;
        }

        if ((activity_match($item['verb'], $verb)) && ($item['id'] != $item['parent'])) {
            $name = (($item['author']['xchan_name']) ? $item['author']['xchan_name'] : t('Unknown'));
            $url = (($item['author_xchan'] && $item['author']['xchan_photo_s'])
                ? '<a class="dropdown-item" href="' . chanlink_hash($item['author_xchan']) . '">' . '<img class="menu-img-1" src="' . zid($item['author']['xchan_photo_s'])  . '" alt="' . urlencode($name) . '" /> ' . $name . '</a>'
                : '<a class="dropdown-item" href="#" class="disabled">' . $name . '</a>'
            );

            if (! $item['thr_parent']) {
                $item['thr_parent'] = $item['parent_mid'];
            }

            if (
                ! ((isset($conv_responses[$mode][$item['thr_parent'] . '-l']))
                && (is_array($conv_responses[$mode][$item['thr_parent'] . '-l'])))
            ) {
                $conv_responses[$mode][$item['thr_parent'] . '-l'] = [];
            }

            // only list each unique author once
            if (in_array($url, $conv_responses[$mode][$item['thr_parent'] . '-l'])) {
                continue;
            }

            if (! isset($conv_responses[$mode][$item['thr_parent']])) {
                $conv_responses[$mode][$item['thr_parent']] = 1;
            } else {
                $conv_responses[$mode][$item['thr_parent']] ++;
            }

            $conv_responses[$mode][$item['thr_parent'] . '-l'][] = $url;
            if (get_observer_hash() && get_observer_hash() === $item['author_xchan']) {
                $conv_responses[$mode][$item['thr_parent'] . '-m'] = true;
            }

            // there can only be one activity verb per item so if we found anything, we can stop looking
            return;
        }
    }
}



/**
 * Wrapper to allow addons to replace the status editor if desired.
 */
function status_editor($x, $popup = false, $module = '')
{
    $hook_info = ['editor_html' => '', 'x' => $x, 'popup' => $popup, 'module' => $module];
    Hook::call('status_editor', $hook_info);
    if ($hook_info['editor_html'] == '') {
        return z_status_editor($x, $popup);
    } else {
        return $hook_info['editor_html'];
    }
}


/**
 * This is our general purpose content editor.
 * It was once nicknamed "jot" and you may see references to "jot" littered throughout the code.
 * They are referring to the content editor or components thereof.
 */
function z_status_editor($x, $popup = false)
{

    $o = '';

    $c = Channel::from_id($x['profile_uid']);
    if ($c && $c['channel_moved']) {
        return $o;
    }

    $plaintext = true;
    $webpage = false;
    $feature_voting = false;

    $feature_comment_control = Apps::system_app_installed($x['profile_uid'], 'Comment Control');
    if (x($x, 'disable_comment_control')) {
        $feature_comment_control = false;
    }


    $feature_expire = ((Apps::system_app_installed($x['profile_uid'], 'Expire Posts') && (! $webpage)) ? true : false);
    if (x($x, 'hide_expire')) {
        $feature_expire = false;
    }

    $feature_future = ((Apps::system_app_installed($x['profile_uid'], 'Future Posting') && (! $webpage)) ? true : false);
    if (x($x, 'hide_future')) {
        $feature_future = false;
    }

    $feature_markup = ((Apps::system_app_installed($x['profile_uid'], 'Markup') && (! $webpage)) ? true : false);
    if (x($x, 'hide_markup')) {
        $feature_markup = false;
    }

    $lat = '';
    $lon = '';
    $geotag = (($x['allow_location']) ? replace_macros(Theme::get_template('jot_geotag.tpl'), []) : '');
    $setloc = t('Set your location');
    $clearloc = t('Clear your location');
    $set_location = get_pconfig($x['profile_uid'], 'system', 'set_location');
    if ($set_location) {
        $tmp = explode(',', $set_location);
        if (count($tmp) > 1) {
            $lat = floatval(trim($tmp[0]));
            $lon = floatval(trim($tmp[1]));
        }
    }
    if (x($x, 'hide_location')) {
        $geotag = $setloc = $clearloc = $lat = $lon = '';
    }

    $summaryenabled = ((array_key_exists('allow_summary', $x)) ? intval($x['allow_summary']) : false);

    $mimetype = ((x($x, 'mimetype')) ? $x['mimetype'] : 'text/x-multicode');

    $mimeselect = ((x($x, 'mimeselect')) ? $x['mimeselect'] : false);
    if ($mimeselect) {
        $mimeselect = mimetype_select($x['profile_uid'], $mimetype);
    } else {
        $mimeselect = '<input type="hidden" name="mimetype" value="' . $mimetype . '" />';
    }

    $weblink = (($mimetype === 'text/x-multicode') ? t('Insert web link') : false);
    if (x($x, 'hide_weblink')) {
        $weblink = false;
    }

    $embedPhotos = t('Embed (existing) photo from your photo albums');

    $writefiles = (($mimetype === 'text/x-multicode') ? perm_is_allowed($x['profile_uid'], get_observer_hash(), 'write_storage') : false);
    if (x($x, 'hide_attach')) {
        $writefiles = false;
    }
    if (perm_is_allowed($x['profile_uid'], get_observer_hash(), 'moderated')) {
        $writefiles = false;
    }

    $layout = ((x($x, 'layout')) ? $x['layout'] : '');

    $layoutselect = ((x($x, 'layoutselect')) ? $x['layoutselect'] : false);
    if ($layoutselect) {
        $layoutselect = layout_select($x['profile_uid'], $layout);
    } else {
        $layoutselect = '<input type="hidden" name="layout_mid" value="' . $layout . '" />';
    }

    if (array_key_exists('channel_select', $x) && $x['channel_select']) {
        $id_select = Channel::identity_selector();
    } else {
        $id_select = '';
    }

    $webpage = ((x($x, 'webpage')) ? $x['webpage'] : '');

    $reset = ((x($x, 'reset')) ? $x['reset'] : '');

    $feature_auto_save_draft = ((Features::enabled($x['profile_uid'], 'auto_save_draft')) ? "true" : "false");

    $tpl = Theme::get_template('jot-header.tpl');

    if (! isset(App::$page['htmlhead'])) {
        App::$page['htmlhead'] = EMPTY_STR;
    }

    App::$page['htmlhead'] .= replace_macros($tpl, [
        '$baseurl' => z_root(),
        '$webpage' => $webpage,
        '$editselect' => (($plaintext) ? 'none' : '/(profile-jot-text|prvmail-text)/'),
        '$pretext' => ((x($x, 'pretext')) ? $x['pretext'] : ''),
        '$geotag' => $geotag,
        '$nickname' => $x['nickname'],
        '$linkurl' => t('Please enter a link URL:'),
        '$term' => t('Tag term:'),
        '$whereareu' => t('Where are you right now?') . ' ' . t('(Enter a dot . to use your current device coordinates.)'),
        '$editor_autocomplete' => ((x($x, 'editor_autocomplete')) ? $x['editor_autocomplete'] : ''),
        '$bbco_autocomplete' => ((x($x, 'bbco_autocomplete')) ? $x['bbco_autocomplete'] : ''),
        '$modalchooseimages' => t('Choose images to embed'),
        '$modalchoosealbum' => t('Choose an album'),
        '$modaldiffalbum' => t('Choose a different album...'),
        '$modalerrorlist' => t('Error getting album list'),
        '$modalerrorlink' => t('Error getting photo link'),
        '$modalerroralbum' => t('Error getting album'),
        '$auto_save_draft' => $feature_auto_save_draft,
        '$confirmdelete' => t('Delete this item?'),
        '$reset' => $reset
    ]);

    $tpl = Theme::get_template('jot.tpl');

    $preview = t('Preview');
    if (x($x, 'hide_preview')) {
        $preview = '';
    }

    $defexpire = ((($z = get_pconfig($x['profile_uid'], 'system', 'default_post_expire')) && (! $webpage)) ? $z : '');
    if ($defexpire) {
        $defexpire = datetime_convert('UTC', date_default_timezone_get(), $defexpire, 'Y-m-d H:i');
    } else {
        $defexpire = ((($z = intval(get_pconfig($x['profile_uid'], 'system', 'selfexpiredays'))) && (! $webpage)) ? $z : '');
        if ($defexpire) {
            $defexpire = datetime_convert('UTC', date_default_timezone_get(), "now + $defexpire days", 'Y-m-d H:i');
        }
    }


    $defclosecomm = ((($z = get_pconfig($x['profile_uid'], 'system', 'close_comments', 0)) && (! $webpage)) ? intval($z) : '');
    if ($defclosecomm) {
        $closecommdays = intval($defclosecomm);
    } else {
        $closecommdays = EMPTY_STR;
    }

    $defcommuntil = (($closecommdays) ? datetime_convert('UTC', date_default_timezone_get(), 'now + ' . $closecommdays . ' days') : EMPTY_STR);

    $defpublish = ((($z = get_pconfig($x['profile_uid'], 'system', 'default_post_publish')) && (! $webpage)) ? $z : '');
    if ($defpublish) {
        $defpublish = datetime_convert('UTC', date_default_timezone_get(), $defpublish, 'Y-m-d H:i');
    }

    $cipher = get_pconfig($x['profile_uid'], 'system', 'default_cipher');
    if (! $cipher) {
        $cipher = 'AES-128-CCM';
    }

    if (array_key_exists('catsenabled', $x)) {
        $catsenabled = $x['catsenabled'];
    } else {
        $catsenabled = ((Apps::system_app_installed($x['profile_uid'], 'Categories') && (! $webpage)) ? 'categories' : '');
    }


    // we only need the comment_perms for the editor, but this logic is complicated enough (from Settings/Channel)
    // that we will just duplicate most of that code block

    $global_perms = Permissions::Perms();

    $permiss = [];

    $perm_opts = [
        [ t('Restricted - from connections only'), PERMS_SPECIFIC ],
        [ t('Semi-public - from anybody that can be identified'), PERMS_AUTHED ],
        [ t('Public - from anybody on the internet'), PERMS_PUBLIC ]
    ];

    $limits = PermissionLimits::Get(local_channel());
    $anon_comments = get_config('system', 'anonymous_comments');

    foreach ($global_perms as $k => $perm) {
        $options = [];
        $can_be_public = ((strstr($k, 'view') || ($k === 'post_comments' && $anon_comments)) ? true : false);
        foreach ($perm_opts as $opt) {
            if ($opt[1] == PERMS_PUBLIC && (! $can_be_public)) {
                continue;
            }
            $options[$opt[1]] = $opt[0];
        }
        if ($k === 'post_comments') {
            $comment_perms = [ $k, t('Accept delivery of comments and likes on this post from'), $limits[$k],'',$options ];
        } else {
            $permiss[] = [$k,$perm,$limits[$k],'',$options];
        }
    }

    $defcommpolicy = $limits['post_comments'];

    // avoid illegal offset errors
    if (! array_key_exists('permissions', $x)) {
        $x['permissions'] = [ 'allow_cid' => '', 'allow_gid' => '', 'deny_cid' => '', 'deny_gid' => '' ];
    }

    $jotplugins = '';
    Hook::call('jot_tool', $jotplugins);

    $jotcoll = jot_collections($c, ((array_key_exists('collections', $x)) ? $x['collections'] : []));
    if (! $jotcoll) {
        $jotcoll = EMPTY_STR;
    }

    $jotnets = EMPTY_STR;
    if (x($x, 'jotnets')) {
        Hook::call('jot_networks', $jotnets);
    }

    $permanent_draft = ((intval($x['profile_uid']) && intval($x['profile_uid']) === local_channel() && Apps::system_app_installed($x['profile_uid'], 'Drafts')) ? ('Save draft') : EMPTY_STR);

    $sharebutton = (x($x, 'button') ? $x['button'] : t('Share'));
    $placeholdtext = (x($x, 'content_label') ? $x['content_label'] : $sharebutton);
    
    $o .= replace_macros($tpl, [
        '$return_path' => ((x($x, 'return_path')) ? $x['return_path'] : App::$query_string),
        '$action' =>  z_root() . '/item',
        '$share' => $sharebutton,
        '$placeholdtext' => $placeholdtext,
        '$webpage' => $webpage,
        '$placeholdpagetitle' => ((x($x, 'ptlabel')) ? $x['ptlabel'] : t('Page link name')),
        '$pagetitle' => (x($x, 'pagetitle') ? $x['pagetitle'] : ''),
        '$id_select' => $id_select,
        '$id_seltext' => t('Post as'),
        '$writefiles' => $writefiles,
        '$text_style' => t('Text styles'),
        '$bold' => t('Bold'),
        '$italic' => t('Italic'),
        '$underline' => t('Underline'),
        '$quote' => t('Quote'),
        '$code' => t('Code'),
        '$attach' => t('Attach/Upload file'),
        '$weblink' => $weblink,
        '$linkurl' => t('Please enter a link location (URL)'),
        '$hidden_mentions' => ((x($x, 'hidden_mentions')) ? $x['hidden_mentions'] : ''),
        '$weblink_style' => [ t('Insert link only'), t('Embed content if possible') ],
        '$embedPhotos' => $embedPhotos,
        '$embedPhotosModalTitle' => t('Embed an image from your albums'),
        '$embedPhotosModalCancel' => t('Cancel'),
        '$embedPhotosModalOK' => t('OK'),
        '$setloc' => $setloc,
        '$poll' => t('Toggle poll'),
        '$poll_option_label' => t('Option'),
        '$poll_add_option_label' => t('Add option'),
        '$poll_expire_unit_label' => [t('Minutes'), t('Hours'), t('Days')],
        '$multiple_answers' => ['poll_multiple_answers', t("Allow multiple answers"), '', '', [t('No'), t('Yes')]],
        '$feature_voting' => $feature_voting,
        '$consensus' => ((array_key_exists('item', $x)) ? $x['item']['item_consensus'] : 0),
        '$nocommenttitle' => t('Disable comments'),
        '$nocommenttitlesub' => t('Toggle comments'),
        '$comments_allowed' => [ 'comments_allowed', t('Allow comments on this post'), ((array_key_exists('item', $x)) ? 1 - $x['item']['item_nocomment'] : 1), '', [ t('No'), t('Yes')]],
        '$commentstate' => ((array_key_exists('item', $x)) ? 1 - $x['item']['item_nocomment'] : 1),
        '$feature_comment_control' => $feature_comment_control,
        '$commctrl' => t('Comment Control'),
        '$comments_closed' => ((isset($x['item']) && isset($x['item']['comments_closed']) && $x['item']['comments_closed']) ? $x['item']['comments_closed'] : ''),
        '$commclosedate' => t('Optional: disable comments after (date)'),
        '$comment_perms' => $comment_perms,
        '$defcommpolicy' => $defcommpolicy,
        '$defcommuntil' => $defcommuntil,
        '$clearloc' => $clearloc,
        '$lat' => $lat,
        '$lon' => $lon,
        '$title' => ((x($x, 'title')) ? htmlspecialchars($x['title'], ENT_COMPAT, 'UTF-8') : ''),
        '$placeholdertitle' => ((x($x, 'placeholdertitle')) ? $x['placeholdertitle'] : t('Title (optional)')),
        '$catsenabled' => $catsenabled,
        '$category' => ((x($x, 'category')) ? $x['category'] : ''),
        '$placeholdercategory' => t('Categories (optional, comma-separated list)'),
        '$permset' => t('Permission settings'),
        '$ptyp' => ((x($x, 'ptyp')) ? $x['ptyp'] : ''),
        '$content' => ((x($x, 'body')) ? htmlspecialchars($x['body'], ENT_COMPAT, 'UTF-8') : ''),
        '$attachment' => ((x($x, 'attachment')) ? $x['attachment'] : ''),
        '$post_id' => ((x($x, 'post_id')) ? $x['post_id'] : ''),
        '$defloc' => $x['default_location'],
        '$visitor' => $x['visitor'],
        '$lockstate' => $x['lockstate'],
        '$acl' => $x['acl'],
        '$allow_cid' => acl2json($x['permissions']['allow_cid']),
        '$allow_gid' => acl2json($x['permissions']['allow_gid']),
        '$deny_cid' => acl2json($x['permissions']['deny_cid']),
        '$deny_gid' => acl2json($x['permissions']['deny_gid']),
        '$mimeselect' => $mimeselect,
        '$layoutselect' => $layoutselect,
        '$showacl' => ((array_key_exists('showacl', $x)) ? $x['showacl'] : true),
        '$bang' => $x['bang'],
        '$profile_uid' => $x['profile_uid'],
        '$preview' => $preview,
        '$source' => ((x($x, 'source')) ? $x['source'] : ''),
        '$jotplugins' => $jotplugins,
        '$jotcoll' => $jotcoll,
        '$jotnets' => $jotnets,
        '$jotnets_label' => t('Other networks and post services'),
        '$jotcoll_label' => t('Collections'),
        '$defexpire' => $defexpire,
        '$feature_expire' => $feature_expire,
        '$expires' => t('Set expiration date'),
        '$save' => $permanent_draft,
        '$is_draft' => ((array_key_exists('is_draft', $x) && intval($x['is_draft'])) ? true : false),
        '$defpublish' => $defpublish,
        '$feature_future' => $feature_future,
        '$future_txt' => t('Set publish date'),
        '$feature_markup' => $feature_markup,
        '$feature_encrypt' => ((Apps::system_app_installed($x['profile_uid'], 'Secrets')) ? true : false),
        '$encrypt' => t('Encrypt text'),
        '$cipher' => $cipher,
        '$expiryModalOK' => t('OK'),
        '$expiryModalCANCEL' => t('Cancel'),
        '$commModalOK' => t('OK'),
        '$commModalCANCEL' => t('Cancel'),
        '$linkModalOK' => t('OK'),
        '$linkModalCANCEL' => t('Cancel'),
        '$close' => t('Close'),
        '$expanded' => ((x($x, 'expanded')) ? $x['expanded'] : false),
        '$bbcode' => ((x($x, 'bbcode')) ? $x['bbcode'] : false),
        '$parent' => ((array_key_exists('parent', $x) && $x['parent']) ? $x['parent'] : 0),
        '$summaryenabled' => $summaryenabled,
        '$summary' => ((x($x, 'summary')) ? htmlspecialchars($x['summary'], ENT_COMPAT, 'UTF-8') : ''),
        '$placeholdsummary' => t('Summary'),
        '$discombed' => t('Load remote media players'),
        '$discombed2' => t('This <em>may</em> subject viewers of this post to behaviour tracking'),
        '$embedchecked' => ((get_pconfig($x['profile_uid'], 'system', 'linkinfo_embed', true)) ? ' checked ' : ''),
        '$disczot' => t('Find shareable objects (Zot)'),
        '$reset' => $reset
    ]);

    if ($popup === true) {
        $o = '<div id="jot-popup" style="display:none">' . $o . '</div>';
    }

    return $o;
}


function jot_collections($channel, $collections)
{

    $output = EMPTY_STR;

    $r = q(
        "select channel_address, channel_name from channel where channel_parent = '%s' and channel_removed = 0 order by channel_name asc",
        dbesc($channel['channel_hash'])
    );
    if (! $r) {
        return $output;
    }

    $size = ((count($r) < 4) ? count($r) : 4);

    $output .= t('Post to Collections');
    $output .= '<select size="' . $size . '" class="form-control" name="collections[]" multiple>';
    foreach ($r as $rv) {
        $selected = ((is_array($collections) && in_array(Channel::get_webfinger($rv), $collections)) ? " selected " : "");
        $output .= '<option value="' . Channel::get_webfinger($rv) . '"' . $selected . '>' . $rv['channel_name'] . '</option>';
    }
    $output .= '</select>';

    return $output;
}


function get_item_children($arr, $parent)
{

    $children = [];
    if (! $arr) {
        return $children;
    }

    $thread_allow = get_config('system', 'thread_allow', true);
    $thread_max   = intval(get_config('system', 'thread_maxlevel', 20));

    foreach ($arr as $item) {
        if (intval($item['id']) !== intval($item['parent'])) {
            if ($thread_allow) {
                $thr_parent = $item['thr_parent'];

                // Fallback to parent_mid if thr_parent is not set
                if ($thr_parent === EMPTY_STR) {
                    $thr_parent = $item['parent_mid'];
                }

                if ($thr_parent === $parent['mid']) {
                    $my_children = get_item_children($arr, $item);
                    if ($item['item_level'] > $thread_max) {
                        // Like and Dislike activities are allowed as children of the last supported level.
                        // After that they are ignored.
                        // Any other children deeper than $thread_max are flattened.
                        if (in_array($item['verb'], [ 'Like','Dislike' ])) {
                            if ($item['item_level'] > ($thread_max + 1)) {
                                continue;
                            }
                        }
                        $children = (($my_children) ? array_merge($children, $my_children) : $children);
                    } else {
                        $item['children'] = $my_children;
                    }
                    $children[] = $item;
                }
            } elseif (intval($item['parent']) === intval($parent['id'])) {
                // threads are disabled. Anything that is in this conversation gets added to children.
                $children[] = $item;
            }
        }
    }
    return $children;
}

function sort_item_children($items)
{
    $result = $items;
    usort($result, 'sort_thr_created_rev');
    foreach ($result as $k => $i) {
        if ($result[$k]['children']) {
            $result[$k]['children'] = sort_item_children($result[$k]['children']);
        }
    }
    return $result;
}

function add_children_to_list($children, &$arr)
{
    foreach ($children as $y) {
        $arr[] = $y;
        if ($y['children']) {
            add_children_to_list($y['children'], $arr);
        }
    }
}

/*
 * separate the incoming array into conversations, with the original post at index 0,
 * and the comments following in reverse date order (newest first). Likes and other hidden activities go to the end.
 * This lets us choose the most recent comments in each conversation (regardless of thread depth)
 * to open by default - while collapsing everything else.
 */

function flatten_and_order($arr, $order)
{
    $narr = [];
    $ret = [];

    foreach ($arr as $a) {
        $narr[$a['parent']][] = $a;
    }

    foreach ($narr as $n) {
        usort($n, ($order === 'received') ? 'sort_flatten_received' : 'sort_flatten');
        for ($x = 0; $x < count($n); $x++) {
            $n[$x]['comment_order'] = $x;
            $ret[] = $n[$x];
        }
    }

    return $ret;
}



function conv_sort($arr, $order)
{

    $parents = [];
    $ret = [];

    if (! (is_array($arr) && count($arr))) {
        return $ret;
    }

    $narr = [];

    foreach ($arr as $item) {
        // perform view filtering if viewer is logged in locally
        // This allows blocking and message filters to work on public stream items
        // or other channel streams on this site which are not owned by the viewer

        if (local_channel()) {
            if (LibBlock::fetch_by_entity(local_channel(), $item['author_xchan'])
                    || LibBlock::fetch_by_entity(local_channel(), $item['owner_xchan'])) {
                continue;
            }

            $message_filter_abook = [];
            if (App::$contacts && array_key_exists($item['author_xchan'], App::$contacts)) {
                $message_filter_abook[] = App::$contacts[$item['author_xchan']];
            }
            if (App::$contacts && array_key_exists($item['owner_xchan'], App::$contacts)) {
                $message_filter_abook[] = App::$contacts[$item['owner_xchan']];
            }

            if (! post_is_importable(local_channel(), $item, $message_filter_abook ? $message_filter_abook : false)) {
                continue;
            }


            $matches = null;
            $found = false;

            $cnt = preg_match_all("/\[share(.*?)portable_id='(.*?)'(.*?)]/ism", $item['body'], $matches, PREG_SET_ORDER);
            if ($cnt) {
                foreach ($matches as $match) {
                    if (LibBlock::fetch_by_entity(local_channel(), $match[2])) {
                        $found = true;
                    }
                }
            }

            if ($found) {
                continue;
            }


            $matches = null;
            $found = false;
            $cnt = preg_match_all("/\[share(.*?)profile='(.*?)'(.*?)]/ism", $item['body'], $matches, PREG_SET_ORDER);
            if ($cnt) {
                foreach ($matches as $match) {
                    $r = q(
                        "select hubloc_hash from hubloc where hubloc_id_url = '%s' and hubloc_deleted = 0",
                        dbesc($match[2])
                    );
                    if ($r) {
                        if (LibBlock::fetch_by_entity(local_channel(), $r[0]['hubloc_hash'])) {
                            $found = true;
                        }
                    }
                }
            }

            if ($found) {
                continue;
            }
        }

        $narr[] = $item;
    }

    $data = [ 'items' => $narr, 'order' => $order ];

    Hook::call('conv_sort', $data);

    $arr = $data['items'];

    if (! (is_array($arr) && count($arr))) {
        return $ret;
    }

    $arr = flatten_and_order($arr, $order);


    foreach ($arr as $x) {
        if (intval($x['id']) === intval($x['parent'])) {
            $parents[] = $x;
        }
    }


    if (stristr($order, 'created')) {
        usort($parents, 'sort_thr_created');
    } elseif (stristr($order, 'distance')) {
        usort($parents, 'sort_thr_distance');
    } elseif (stristr($order, 'commented')) {
        usort($parents, 'sort_thr_commented');
    } elseif (stristr($order, 'updated')) {
        usort($parents, 'sort_thr_updated');
    } elseif (stristr($order, 'changed')) {
        usort($parents, 'sort_thr_received');
    } elseif (stristr($order, 'ascending')) {
        usort($parents, 'sort_thr_created_rev');
    }
    if ($parents) {
        foreach ($parents as $i => $_x) {
            $parents[$i]['children'] = get_item_children($arr, $_x);
        }

        foreach ($parents as $k => $v) {
            if ($v['children']) {
                $parents[$k]['children'] = sort_item_children($parents[$k]['children']);
            }
        }
    }

    if ($parents) {
        foreach ($parents as $x) {
            $ret[] = $x;
            if ($x['children']) {
                add_children_to_list($x['children'], $ret);
            }
        }
    }

    return $ret;
}


// This is a complicated sort.
// We want the original post at index 0 and all the comments (regardless of thread depth) ordered newest to oldest.
// likes and other invisible activities go to the end of the array beyond the oldest comment.

function sort_flatten($a, $b)
{

    if ($a['parent'] === $a['id']) {
        return -1;
    }
    if ($b['parent'] === $b['id']) {
        return 1;
    }

    if (! visible_activity($a)) {
        return 1;
    }
    if (! visible_activity($b)) {
        return -1;
    }

    return strcmp($b['created'], $a['created']);
}

function sort_flatten_received($a, $b)
{

    if ($a['parent'] === $a['id']) {
        return -1;
    }
    if ($b['parent'] === $b['id']) {
        return 1;
    }

    if (! visible_activity($a)) {
        return 1;
    }
    if (! visible_activity($b)) {
        return -1;
    }

    return strcmp($b['changed'], $a['changed']);
}

    

function sort_thr_created($a, $b)
{
    return strcmp($b['created'], $a['created']);
}

function sort_thr_created_rev($a, $b)
{
    return strcmp($a['created'], $b['created']);
}

function sort_thr_commented($a, $b)
{
    return strcmp($b['commented'], $a['commented']);
}

function sort_thr_received($a, $b)
{
    return strcmp($b['changed'], $a['changed']);
}

function sort_thr_distance($a, $b)
{
    if (!isset($a['distance'])) {
        $a['distance'] = 999999999;
    }
    if (!isset($b['distance'])) {
        $b['distance'] = 999999999;
    }
    return floatval($a['distance']) <=> floatval($b['distance']);
}

function sort_thr_updated($a, $b)
{
    $indexa = (($a['changed'] > $a['edited']) ? $a['changed'] : $a['edited']);
    $indexb = (($b['changed'] > $b['edited']) ? $b['changed'] : $b['edited']);
    return strcmp($indexb, $indexa);
}

function find_thread_parent_index($arr, $x)
{
    foreach ($arr as $k => $v) {
        if ($v['id'] == $x['parent']) {
            return $k;
        }
    }

    return false;
}

function format_location($item)
{

    if (str_starts_with($item['location'], '#')) {
        $location = substr($item['location'], 1);
        $location = ((str_contains($location, '[')) ? zidify_links(bbcode($location)) : $location);
    } else {
        $locate = ['location' => $item['location'], 'lat' => $item['lat'], 'lon' => $item['lon'], 'coord' => $item['coord'], 'html' => ''];
        Hook::call('render_location', $locate);
        $location = ((strlen($locate['html'])) ? $locate['html'] : render_location_default($locate));
    }
    return $location . (!empty($item['distance']) ? t(' distance: ') . sprintf("%05.03f km",$item['distance']) : '');
}

function render_location_default($item)
{

    $location = $item['location'];
    $latitude = $item['lat'];
    $longitude = $item['lon'];

    if ($latitude || $longitude) {
        if ($location) {
            $location .= '&nbsp;<span class="smalltext">(' . $latitude . ',' . $longitude . ')</span>';
        } else {
            $location = '<span class="smalltext">' . $latitude . ',' . $longitude . '</span>';
        }
    }
    return $location;
}


function prepare_page($item)
{

    $naked = 1;
    $preview = substr(urlencode($item['body']), 0, 240);
    $link = z_root() . '/' . App::$cmd;
    if (array_key_exists('webpage', App::$layout) && array_key_exists('authored', App::$layout['webpage'])) {
        if (App::$layout['webpage']['authored'] === 'none') {
            $naked = 1;
        }
        // ... other possible options
    }

    // prepare_body calls unobscure() as a side effect. Do it here so that
    // the template will get passed an unobscured title.

    $body = prepare_body($item, true, [ 'newwin' => false ]);
    if (App::$page['template'] == 'none') {
        $tpl = 'page_display_empty.tpl';

        return replace_macros(Theme::get_template($tpl), [
            '$body' => $body['html']
        ]);
    }

    $tpl = get_pconfig($item['uid'], 'system', 'pagetemplate');
    if (! $tpl) {
        $tpl = 'page_display.tpl';
    }

    return replace_macros(Theme::get_template($tpl), [
        '$author' => (($naked) ? '' : $item['author']['xchan_name']),
        '$auth_url' => (($naked) ? '' : zid($item['author']['xchan_url'])),
        '$date' => (($naked) ? '' : datetime_convert('UTC', date_default_timezone_get(), $item['created'], 'Y-m-d H:i')),
        '$title' => zidify_links(smilies(bbcode($item['title']))),
        '$body' => $body['html'],
        '$preview' => $preview,
        '$link' => $link,
    ]);
}


function get_responses($conv_responses, $response_verbs, $ob, $item)
{

    $ret = [];
    foreach ($response_verbs as $v) {
        $ret[$v] = [];
        $ret[$v]['count'] = ((x($conv_responses[$v], $item['mid'])) ? $conv_responses[$v][$item['mid']] : '');
        $ret[$v]['list']  = ((x($conv_responses[$v], $item['mid'])) ? $conv_responses[$v][$item['mid'] . '-l'] : '');
        $ret[$v]['button'] = get_response_button_text($v, $ret[$v]['count']);
        $ret[$v]['title'] = $conv_responses[$v]['title'];
        if ($ret[$v]['count'] > MAX_LIKERS) {
            $ret[$v]['modal'] = true;
        }
    }

    $count = 0;
    foreach ($ret as $key) {
        if ($key['count'] == true) {
            $count++;
        }
    }

    $ret['count'] = $count;

//logger('ret: ' . print_r($ret,true));

    return $ret;
}

function get_response_button_text($v, $count)
{
    switch ($v) {
        case 'like':
            if (get_config('system', 'show_like_counts', true)) {
                return $count . ' ' . tt('Like', 'Likes', $count, 'noun');
            } else {
                return t('Likes', 'noun');
            }
        case 'dislike':
            if (get_config('system', 'show_like_counts', true)) {
                return $count . ' ' . tt('Dislike', 'Dislikes', $count, 'noun');
            } else {
                return t('Dislikes', 'noun');
            }
        case 'attendyes':
            return $count . ' ' . tt('Attending', 'Attending', $count, 'noun');
        case 'attendno':
            return $count . ' ' . tt('Not Attending', 'Not Attending', $count, 'noun');
        case 'attendmaybe':
            return $count . ' ' . tt('Undecided', 'Undecided', $count, 'noun');
        case 'agree':
            return $count . ' ' . tt('Agree', 'Agrees', $count, 'noun');
        case 'disagree':
            return $count . ' ' . tt('Disagree', 'Disagrees', $count, 'noun');
        case 'abstain':
            return $count . ' ' . tt('Abstain', 'Abstains', $count, 'noun');
        default:
            return '';
    }
}
