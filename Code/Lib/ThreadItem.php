<?php

/** @file */

namespace Code\Lib;

use App;
use Code\Extend\Hook;
use Code\Render\Theme;


/**
 * A thread item
 */

class ThreadItem
{
    public $data = [];
    private $template = 'conv_item.tpl';
    private $comment_box_template = 'comment_item.tpl';
    private $commentable = false;
    // list of supported reaction emojis - a site can over-ride this via config system.reactions
    // Deprecated. Use your operating system or a browser plugin.
    private $reactions = ['1f60a','1f44f','1f37e','1f48b','1f61e','2665','1f606','1f62e','1f634','1f61c','1f607','1f608'];
    private $toplevel;
    private $children = [];
    private $parent = null;
    private $conversation;
    private $redirect_url = null;
    private $owner_url = '';
    private $owner_photo = '';
    private $owner_name = '';
    private $owner_addr = '';
    private $owner_censored = false;
    private $wall_to_wall = false;
    private $threaded;
    private $visiting = false;
    private $channel = null;
    private $display_mode = 'normal';
    private $reload = '';


    public function __construct($data)
    {

        $this->data = $data;
        $this->toplevel = ($this->get_id() == $this->get_data_value('parent'));
        $this->threaded = get_config('system', 'thread_allow', true);

        $observer = App::get_observer();

        // Prepare the children
        if ($data['children']) {
            foreach ($data['children'] as $item) {
                /*
                 * Only add those that will be displayed
                 */

                if (! visible_activity($item)) {
                    continue;
                }

                // this is a quick hack to hide ActivityPub DMs that we should not be allowed to see
                // but may have been forwarded as part of a conversation

                if (intval($item['item_private']) && (intval($item['item_restrict']) & 1 ) && $item['mid'] !== $item['parent_mid']) {
                    if (! $observer) {
                        continue;
                    }
                }

                $child = new ThreadItem($item);
                $this->add_child($child);
            }
        }

        // allow a site to configure the order and content of the reaction emoji list
        if ($this->toplevel) {
            $x = get_config('system', 'reactions');
            if ($x && is_array($x) && count($x)) {
                $this->reactions = $x;
            }
        }
    }

    /**
     * Get data in a form usable by a conversation template
     *
     * Returns:
     *      _ The data requested on success
     *      _ false on failure
     */

    public function get_template_data($conv_responses, $thread_level = 1)
    {

        $result = [];

        $item     = $this->get_data();

        $dropping = false;
        $star = false;
        $is_comment = false;
        $total_children = $this->count_descendants();
        $unseen_comments = ((isset($item['real_uid']) && $item['real_uid']) ? 0 : $this->count_unseen_descendants());
        $privacy_warning = false;

        $conv = $this->get_conversation();
        $observer = $conv->get_observer();

        $lock = t('Public visibility');
        if (intval($item['item_private']) === 2) {
            $lock = t('Direct message (private mail)');
        }
        if (intval($item['item_private']) === 1) {
            $lock = t('Restricted visibility');
        }

        $locktype = intval($item['item_private']);

        $shareable = ((($conv->get_profile_owner() == local_channel() && local_channel()) && (! intval($item['item_private']))) ? true : false);

        // allow an exemption for sharing stuff from your private feeds
        if ($item['author']['xchan_network'] === 'rss') {
            $shareable = true;
        }

        // @fixme
        // Have recently added code to properly handle polls in group reshares by redirecting all of the poll responses to the group.
        // Sharing a poll using a regular embedded share is harder because the poll will need to fork. This is due to comment permissions.
        // The original poll author may not accept responses from strangers. Forking the poll will receive responses from the sharer's
        // followers, but there's no elegant way to merge these two sets of results together. For now, we'll disable sharing polls.

        if ($item['obj_type'] === 'Question') {
            $shareable = false;
        }


        if ($item['item_restrict'] & 2) {
            $privacy_warning = true;
            $lock = t('This comment is part of a private conversation, yet was shared with the public. Discretion advised.');
        }

        $mode = $conv->get_mode();

        $edlink = 'editpost';

        if (local_channel() && $observer['xchan_hash'] === $item['author_xchan']) {
            $edpost = [z_root() . '/' . $edlink . '/' . $item['id'], t('Edit')];
        } else {
            $edpost = false;
        }

        if (local_channel() && $observer['xchan_hash'] === $item['owner_xchan']) {
            $myconv = true;
        } else {
            $myconv = false;
        }


        if ($item['verb'] === 'Announce') {
            $edpost = false;
        }


        if (
            $observer && $observer['xchan_hash']
            && ( $observer['xchan_hash'] == $this->get_data_value('author_xchan')
            || $observer['xchan_hash'] == $this->get_data_value('owner_xchan')
            || $observer['xchan_hash'] == $this->get_data_value('source_xchan')
            || $this->get_data_value('uid') == local_channel())
        ) {
            $dropping = true;
        }


        if (array_key_exists('real_uid', $item)) {
            $edpost = false;
            $dropping = false;
        }


        if ($dropping) {
            $drop = [
                'dropping' => $dropping,
                'delete' => t('Delete'),
            ];
        } elseif (is_site_admin()) {
            $drop = [ 'dropping' => true, 'delete' => t('Admin Delete') ];
        }

        if (isset($observer_is_pageowner) && $observer_is_pageowner) {
            $multidrop = [
                'select' => t('Select'),
            ];
        }

        $filer = ((($conv->get_profile_owner() == local_channel()) && (! array_key_exists('real_uid', $item))) ? t('Save to Folder') : false);

        $large_avatar = $item['author']['xchan_photo_l'];
        $profile_avatar = $item['author']['xchan_photo_m'];
        $profile_link   = chanlink_hash($item['author_xchan']);
        $profile_name   = $item['author']['xchan_name'];

        $profile_addr = $item['author']['xchan_addr'] ?: $item['author']['xchan_url'];

        $location = format_location($item);
        $isevent = false;
        $attend = null;

        // process action responses - e.g. like/dislike/attend/agree/whatever
        $response_verbs = [ 'like', 'dislike' ];

        if ($item['obj_type'] === ACTIVITY_OBJ_EVENT) {
            $response_verbs[] = 'attendyes';
            $response_verbs[] = 'attendno';
            $response_verbs[] = 'attendmaybe';
            if ($this->is_commentable() && $observer) {
                $isevent = true;
                $attend = [t('I will attend'), t('I will not attend'), t('I might attend')];
                $undo_attend = t('Undo attendance');
            }
        }

        $responses = get_responses($conv_responses, $response_verbs, $this, $item);

        $my_responses = [];
        foreach ($response_verbs as $v) {
            $my_responses[$v] = ((isset($conv_responses[$v][$item['mid'] . '-m']) && $conv_responses[$v][$item['mid'] . '-m']) ? 1 : 0);
        }

        $like_count = ((x($conv_responses['like'], $item['mid'])) ? $conv_responses['like'][$item['mid']] : '');
        $like_list = ((x($conv_responses['like'], $item['mid'])) ? $conv_responses['like'][$item['mid'] . '-l'] : '');
        if (($like_list) && (count($like_list) > MAX_LIKERS)) {
            $like_list_part = array_slice($like_list, 0, MAX_LIKERS);
            array_push($like_list_part, '<a class="dropdown-item" href="#" data-toggle="modal" data-target="#likeModal-' . $this->get_id() . '"><b>' . t('View all') . '</b></a>');
        } else {
            $like_list_part = '';
        }
        if (get_config('system', 'show_like_counts', true)) {
            $like_button_label = tt('Like', 'Likes', $like_count, 'noun');
        } else {
            $like_button_label = t('Likes', 'noun');
        }

        $dislike_count = ((x($conv_responses['dislike'], $item['mid'])) ? $conv_responses['dislike'][$item['mid']] : '');
        $dislike_list = ((x($conv_responses['dislike'], $item['mid'])) ? $conv_responses['dislike'][$item['mid'] . '-l'] : '');
        if (get_config('system', 'show_like_counts', true)) {
                $dislike_button_label = tt('Dislike', 'Dislikes', $dislike_count, 'noun');
        } else {
                $dislike_button_label = t('Dislikes', 'noun');
        }

        if (($dislike_list) && (count($dislike_list) > MAX_LIKERS)) {
            $dislike_list_part = array_slice($dislike_list, 0, MAX_LIKERS);
            array_push($dislike_list_part, '<a class="dropdown-item" href="#" data-toggle="modal" data-target="#dislikeModal-' . $this->get_id() . '"><b>' . t('View all') . '</b></a>');
        } else {
            $dislike_list_part = '';
        }

        /*
         * We should avoid doing this all the time, but it depends on the conversation mode
         * And the conv mode may change when we change the conv, or it changes its mode
         * Maybe we should establish a way to be notified about conversation changes
         */

        $this->check_wall_to_wall();

        if ($this->is_toplevel()) {
            if (local_channel() && ($conv->get_profile_owner() == local_channel() || intval($item['item_private']) === 0)) {
                $star = [
                    'toggle' => t('Save'),
                    'isstarred' => (bool) $item['item_starred']
                ];
            }
        } else {
            $is_comment = true;
        }


        $verified = (intval($item['item_verified']) ? t('Message signature validated') : '');
        $forged = ((($item['sig']) && (! intval($item['item_verified']))) ? t('Message signature incorrect') : '');
        $unverified = '' ; // (($this->is_wall_to_wall() && (! intval($item['item_verified']))) ? t('Message cannot be verified') : '');


        if ($conv->get_profile_owner() == local_channel()) {
            $tagger = [
                'tagit' => t("Add Tag"),
                'classtagger' => "",
            ];
        }

        $has_bookmarks = false;
        if (isset($item['term']) && is_array($item['term'])) {
            foreach ($item['term'] as $t) {
                if ($t['ttype'] == TERM_BOOKMARK) {
                    $has_bookmarks = true;
                }
            }
        }

        $has_event = false;
        if (($item['obj_type'] === ACTIVITY_OBJ_EVENT) && $conv->get_profile_owner() == local_channel()) {
            $has_event = true;
        }

        if ($this->is_commentable() && $observer) {
            $like = [t('I like this'), t('Undo like')];
            $dislike = [t('I don\'t like this'), t('Undo dislike')];
        }

        $share = $embed = EMPTY_STR;

        if ($shareable) {
            $share = t('Repeat This');
            $embed = t('Share this');
        }

        $dreport = '';

        $keep_reports = intval(get_config('system', 'expire_delivery_reports'));
        if ($keep_reports === 0) {
            $keep_reports = 10;
        }

        if ((! get_config('system', 'disable_dreport')) && strcmp(datetime_convert('UTC', 'UTC', $item['created']), datetime_convert('UTC', 'UTC', "now - $keep_reports days")) > 0) {
            $dreport = t('Delivery Report');
            $dreport_link = gen_link_id($item['mid']);
        }
        $is_new = false;

        if (strcmp(datetime_convert('UTC', 'UTC', $item['created']), datetime_convert('UTC', 'UTC', 'now - 12 hours')) > 0) {
            $is_new = true;
        }

        localize_item($item);

        $opts = [];
        if ($this->is_wall_to_wall()) {
            if ($this->owner_censored) {
                $opts['censored'] = true;
            }
        }

        $body = prepare_body($item, true, $opts);

        // $viewthread (below) is only valid in list mode. If this is a channel page, build the thread viewing link
        // since we can't depend on llink or plink pointing to the right local location.

        $owner_address = substr($item['owner']['xchan_addr'], 0, strpos($item['owner']['xchan_addr'], '@'));
        $viewthread = $item['llink'];
        if ($conv->get_mode() === 'channel') {
            $viewthread = z_root() . '/channel/' . $owner_address . '?f=&mid=' . urlencode(gen_link_id($item['mid']));
        }

        $comment_count_txt = sprintf(tt('%d comment', '%d comments', $total_children), $total_children);
        $list_unseen_txt = (($unseen_comments) ? sprintf(t('%d unseen'), $unseen_comments) : '');

        $children = $this->get_children();


        $has_tags = (($body['tags'] || $body['categories'] || $body['mentions'] || $body['attachments'] || $body['folders']) ? true : false);

        $dropdown_extras_arr = [ 'item' => $item , 'dropdown_extras' => '' ];
        Hook::call('dropdown_extras', $dropdown_extras_arr);
        $dropdown_extras = $dropdown_extras_arr['dropdown_extras'];

        // Pinned item processing
        $allowed_type = (in_array($item['item_type'], get_config('system', 'pin_types', [ ITEM_TYPE_POST ])) ? true : false);
        $pinned_items = ($allowed_type ? get_pconfig($item['uid'], 'pinned', $item['item_type'], []) : []);
        $pinned = ((! empty($pinned_items) && in_array($item['mid'], $pinned_items)) ? true : false);

        $locicon = ($item['verb'] === 'Arrive') ? '<i class="fa fa-fw fa-sign-in"></i>&nbsp' : '';
        if (!$locicon) {
            $locicon = ($item['verb'] === 'Leave') ? '<i class="fa fa-fw fa-sign-out"></i>&nbsp' : '';
        }
        
        $tmp_item = [
            'template' => $this->get_template(),
            'mode' => $mode,
            'item_type' => intval($item['item_type']),
            'comment_order' => $item['comment_order'],
            'parent' => $this->get_data_value('parent'),
            'collapsed' => ((intval($item['comment_order']) > 3) ? true : false),
            'type' => implode("", array_slice(explode("/", $item['verb']), -1)),
            'body' => $body['html'],
            'tags' => $body['tags'],
            'categories' => $body['categories'],
            'mentions' => $body['mentions'],
            'attachments' => $body['attachments'],
            'folders' => $body['folders'],
            'text' => strip_tags($body['html']),
            'id' => $this->get_id(),
            'mid' => $item['mid'],
            'isevent' => $isevent,
            'attend' => $attend,
            'undo_attend' => $undo_attend,
            'consensus' => '',
            'conlabels' => '',
            'linktitle' => sprintf(t('View %s\'s profile - %s'), $profile_name, (($item['author']['xchan_addr']) ? $item['author']['xchan_addr'] : $item['author']['xchan_url'])),
            'olinktitle' => sprintf(t('View %s\'s profile - %s'), $this->get_owner_name(), (($this->get_owner_addr()) ? $this->get_owner_addr() : $this->get_owner_url())),
            'llink' => $item['llink'],
            'viewthread' => $viewthread,
            'to' => t('to'),
            'via' => t('via'),
            'wall' => t('Wall-to-Wall'),
            'vwall' => t('via Wall-To-Wall:'),
            'profile_url' => $profile_link,
            'thread_action_menu' => thread_action_menu($item, $conv->get_mode()),
            'thread_author_menu' => thread_author_menu($item, $conv->get_mode()),
            'dreport' => $dreport,
            'dreport_link' => ((isset($dreport_link) && $dreport_link) ? $dreport_link : EMPTY_STR),
            'myconv' => $myconv,
            'name' => $profile_name,
            'thumb' => $profile_avatar,
            'large_avatar' => $large_avatar,
            'title' => $locicon . $item['title'],
            'title_tosource' => get_pconfig($conv->get_profile_owner(), 'system', 'title_tosource'),
            'ago' => relative_date($item['created']),
            'app' => $item['app'],
            'str_app' => sprintf(t('from %s'), $item['app']),
            'isotime' => datetime_convert('UTC', date_default_timezone_get(), $item['created'], 'c'),
            'localtime' => datetime_convert('UTC', date_default_timezone_get(), $item['created'], 'r'),
            'editedtime' => (($item['edited'] != $item['created']) ? sprintf(t('last edited: %s'), datetime_convert('UTC', date_default_timezone_get(), $item['edited'], 'r')) : ''),
            'expiretime' => (($item['expires'] > NULL_DATE) ? sprintf(t('Expires: %s'), datetime_convert('UTC', date_default_timezone_get(), $item['expires'], 'r')) : ''),
            'lock' => $lock,
            'locktype' => $locktype,
            'delayed' => $item['item_delayed'],
            'privacy_warning' => $privacy_warning,
            'verified' => $verified,
            'unverified' => $unverified,
            'forged' => $forged,
            'location' => $location,
            'divider' => get_pconfig($conv->get_profile_owner(), 'system', 'item_divider'),
            'attend_label' => t('Attend'),
            'attend_title' => t('Attendance Options'),
            'vote_label' => t('Vote'),
            'vote_title' => t('Voting Options'),
            'comment_lbl' => (($this->is_commentable() && $observer) ? t('Reply') : ''),
            'is_comment' => $is_comment,
            'is_new' => $is_new,
            'mod_display' => ((argv(0) === 'display') ? true : false),   // comments are not collapsed when using mod_display
            'owner_url' => $this->get_owner_url(),
            'owner_photo' => $this->get_owner_photo(),
            'owner_name' => $this->get_owner_name(),
            'owner_addr' =>  $this->get_owner_addr(),
            'photo' => $body['photo'],
            'event' => $body['event'],
            'has_tags' => $has_tags,
            'reactions' => $this->reactions,

            // Item toolbar buttons

            'emojis'    => '', // deprecated - use your operating system or a browser plugin
            'like'      => $like,
            'dislike'   => $dislike,
            'share'     => $share,
            'embed'     => $embed,
            'rawmid'    => $item['mid'],
            'plink'     => get_plink($item),
            'edpost'    => $edpost, // ((Features::enabled($conv->get_profile_owner(),'edit_posts')) ? $edpost : ''),
            'star'      => $star,
            'tagger'    => ((Features::enabled($conv->get_profile_owner(), 'commtag')) ? $tagger : ''),
            'filer'     => ((Features::enabled($conv->get_profile_owner(), 'filing')) ? $filer : ''),
            'pinned'    => ($pinned ? t('Pinned post') : ''),
            'pinnable'  => (($this->is_toplevel() && local_channel() && $item['owner_xchan'] == $observer['xchan_hash'] && $allowed_type && $item['item_private'] == 0 && $item['item_delayed'] == 0) ? '1' : ''),
            'pinme'     => ($pinned ? t('Unpin this post') : t('Pin this post')),
            'isdraft'   => boolval($item['item_unpublished']),
            'draft_txt' => t('Saved draft'),
            'bookmark'  => (($conv->get_profile_owner() == local_channel() && local_channel() && $has_bookmarks) ? t('Save Bookmarks') : ''),
            'addtocal'  => (($has_event && ! $item['resource_id']) ? t('Add to Calendar') : ''),
            'drop'      => $drop,
            'multidrop' => ((Features::enabled($conv->get_profile_owner(), 'multi_delete')) ? $multidrop : ''),
            'dropdown_extras' => $dropdown_extras,

            // end toolbar buttons

            'unseen_comments' => $unseen_comments,
            'comment_count' => $total_children,
            'comment_count_txt' => $comment_count_txt,
            'list_unseen_txt' => $list_unseen_txt,
            'markseen' => t('Mark all seen'),
            'responses' => $responses,
            'my_responses' => $my_responses,
            'like_count' => $like_count,
            'like_list' => $like_list,
            'like_list_part' => $like_list_part,
            'like_button_label' => $like_button_label,
            'like_modal_title' => t('Likes', 'noun'),
            'dislike_modal_title' => t('Dislikes', 'noun'),
            'dislike_count' => $dislike_count,
            'dislike_list' => $dislike_list,
            'dislike_list_part' => $dislike_list_part,
            'dislike_button_label' => $dislike_button_label,
            'modal_dismiss' => t('Close'),
            'comment' => ($item['item_delayed'] ? '' : $this->get_comment_box()),
            'previewing' => ($conv->is_preview() ? true : false ),
            'preview_lbl' => t('This is an unsaved preview'),
            'wait' => t('Please wait'),
            'submid' => str_replace(['+','='], ['',''], base64_encode(urlencode($item['mid']))),
            'thread_level' => $thread_level,
            'indentpx' => intval(get_pconfig(local_channel(), 'system', 'thread_indent_px', get_config('system', 'thread_indent_px', 0))),
            'thread_max' => intval(get_config('system', 'thread_maxlevel', 20)) + 1
        ];

        $arr = ['item' => $item, 'output' => $tmp_item];
        Hook::call('display_item', $arr);

        $result = $arr['output'];

        $result['children'] = [];

        if (local_channel() && get_pconfig(local_channel(), 'system', 'activitypub', get_config('system', 'activitypub', ACTIVITYPUB_ENABLED))) {
            // place to store all the author addresses (links if not available) in the thread so we can auto-mention them in JS.
            $result['authors'] = [];
            // fix to add in sub-replies if replying to a comment on your own post from the top level.
            if (!($observer && ($profile_addr === $observer['xchan_hash'] || $profile_addr === $observer['xchan_addr']))) {
                $result['authors'][] = $profile_addr;
            }

            // Add any mentions from the immediate parent, unless they are mentions of the current viewer or duplicates
            if (isset($item['term']) && is_array($item['term'])) {
                $additional_mentions = [];
                foreach ($item['term'] as $t) {
                    if ($t['ttype'] == TERM_MENTION) {
                        $additional_mentions[] = ((($position = strpos($t['url'], 'url=')) !== false) ? urldecode(substr($t['url'], $position + 4)) : $t['url']);
                    }
                }
                if ($additional_mentions) {
                    $r = q("select hubloc_addr, hubloc_id_url, hubloc_hash from hubloc where hubloc_deleted = 0 and hubloc_id_url in (" . protect_sprintf(stringify_array($additional_mentions, true)) . ") ");
                    if ($r) {
                        foreach ($r as $rv) {
                            $ment = (($r[0]['hubloc_addr']) ? $r[0]['hubloc_addr'] : $r[0]['hubloc_id_url']);
                            if ($ment) {
                                if ($observer && $observer['xchan_hash'] !== $rv['hubloc_hash'] && ! in_array($ment, $result['authors'])) {
                                    $result['authors'][] = $ment;
                                }
                            }
                        }
                    }
                }
            }
        }

        $nb_children = count($children);

        $total_children = $this->count_visible_descendants();

        $visible_comments = get_config('system', 'expanded_comments', 3);

        if (($this->get_display_mode() === 'normal') && ($nb_children > 0)) {
            if ($children) {
                foreach ($children as $child) {
                    $xz = $child->get_template_data($conv_responses, $thread_level + 1);
                    $result['children'][] = $xz;
                }
            }
            // Collapse
            if ($total_children > $visible_comments && $thread_level == 1) {
                $result['children'][0]['comment_firstcollapsed'] = true;
                $result['children'][0]['num_comments'] = $comment_count_txt;
                $result['children'][0]['hide_text'] = sprintf(t('%s show all'), '<i class="fa fa-chevron-down"></i>');
            }
        }

        $result['private'] = $item['item_private'];
        $result['toplevel'] = ($this->is_toplevel() ? 'toplevel_item' : '');

        if ($this->is_threaded()) {
            $result['flatten'] = false;
            $result['threaded'] = true;
        } else {
            $result['flatten'] = true;
            $result['threaded'] = false;
        }

        return $result;
    }

    public function get_id()
    {
        return $this->get_data_value('id');
    }

    public function get_display_mode()
    {
        return $this->display_mode;
    }

    public function set_display_mode($mode)
    {
        $this->display_mode = $mode;
    }

    public function is_threaded()
    {
        return $this->threaded;
    }

    public function get_author()
    {
        $xchan = $this->get_data_value('author');
        if ($xchan['xchan_addr']) {
            return $xchan['xchan_addr'];
        }
        return $xchan['xchan_url'];
    }

    public function set_reload($val)
    {
        $this->reload = $val;
    }

    public function get_reload()
    {
        return $this->reload;
    }

    public function set_commentable($val)
    {
        $this->commentable = $val;
        foreach ($this->get_children() as $child) {
            $child->set_commentable($val);
        }
    }

    public function is_commentable()
    {
        return $this->commentable;
    }

    /**
     * Add a child item
     */
    public function add_child($item)
    {
        $item_id = $item->get_id();
        if (!$item_id) {
            logger('[ERROR] Item::add_child : Item has no ID!!', LOGGER_DEBUG);
            return false;
        }
        if ($this->get_child($item->get_id())) {
            logger('[WARN] Item::add_child : Item already exists (' . $item->get_id() . ').', LOGGER_DEBUG);
            return false;
        }

        /*
         * Only add what will be displayed
         */

        if (activity_match($item->get_data_value('verb'), ACTIVITY_LIKE) || activity_match($item->get_data_value('verb'), ACTIVITY_DISLIKE)) {
            return false;
        }

        $item->set_parent($this);
        $this->children[] = $item;
        return end($this->children);
    }

    /**
     * Get a child by its ID
     */

    public function get_child($id)
    {
        foreach ($this->get_children() as $child) {
            if ($child->get_id() == $id) {
                return $child;
            }
        }
        return null;
    }

    /**
     * Get all our children
     */

    public function get_children()
    {
        return $this->children;
    }

    /**
     * Set our parent
     */
    protected function set_parent($item)
    {
        $parent = $this->get_parent();
        if ($parent) {
            $parent->remove_child($this);
        }
        $this->parent = $item;
        $this->set_conversation($item->get_conversation());
    }

    /**
     * Remove our parent
     */

    protected function remove_parent()
    {
        $this->parent = null;
        $this->conversation = null;
    }

    /**
     * Remove a child
     */

    public function remove_child($item)
    {
        $id = $item->get_id();
        foreach ($this->get_children() as $key => $child) {
            if ($child->get_id() == $id) {
                $child->remove_parent();
                unset($this->children[$key]);
                // Reindex the array, in order to make sure there won't be any trouble on loops using count()
                $this->children = array_values($this->children);
                return true;
            }
        }
        logger('[WARN] Item::remove_child : Item is not a child (' . $id . ').', LOGGER_DEBUG);
        return false;
    }

    /**
     * Get parent item
     */
    protected function get_parent()
    {
        return $this->parent;
    }

    /**
     * set conversation
     */
    public function set_conversation($conv)
    {
        $this->conversation = $conv;

        // Set it on our children too
        foreach ($this->get_children() as $child) {
            $child->set_conversation($conv);
        }
    }

    /**
     * get conversation
     */
    public function get_conversation()
    {
        return $this->conversation;
    }

    /**
     * Get raw data
     *
     * We shouldn't need this
     */
    public function get_data()
    {
        return $this->data;
    }

    /**
     * Get a data value
     *
     * Returns:
     *      _ value on success
     *      _ false on failure
     */
    public function get_data_value($name)
    {
        if (!isset($this->data[$name])) {
            return false;
        }

        return $this->data[$name];
    }

    /**
     * Get template
     */
    public function get_template()
    {
        return $this->template;
    }


    public function set_template($t)
    {
        $this->template = $t;
    }

    /**
     * Check if this is a toplevel post
     */
    private function is_toplevel()
    {
        return $this->toplevel;
    }

    /**
     * Count the total of our descendants
     */
    private function count_descendants()
    {
        $children = $this->get_children();
        $total = count($children);
        if ($total > 0) {
            foreach ($children as $child) {
                $total += $child->count_descendants();
            }
        }
        return $total;
    }

    public function count_visible_descendants()
    {
        $total = 0;
        $children = $this->get_children();
        if ($children) {
            foreach ($children as $child) {
                if (! visible_activity($child->data)) {
                    continue;
                }
                $total++;
                $total += $child->count_visible_descendants();
            }
        }
        return $total;
    }

    private function count_unseen_descendants()
    {
        $children = $this->get_children();
        $total = count($children);
        if ($total > 0) {
            $total = 0;
            foreach ($children as $child) {
                if (! visible_activity($child->data)) {
                    continue;
                }
                if (intval($child->data['item_unseen'])) {
                    $total++;
                }
            }
        }
        return $total;
    }


    /**
     * Get the template for the comment box
     */
    private function get_comment_box_template()
    {
        return $this->comment_box_template;
    }

    /**
     * Get the comment box
     *
     * Returns:
     *      _ The comment box string (empty if no comment box)
     *      _ false on failure
     */
    private function get_comment_box($indent = 0)
    {

        if (!$this->is_toplevel() && !get_config('system', 'thread_allow', true)) {
            return '';
        }

        $comment_box = '';
        $conv = $this->get_conversation();

//      logger('Commentable conv: ' . $conv->is_commentable());

        if (! $this->is_commentable()) {
            return false;
        }

        $template = Theme::get_template($this->get_comment_box_template());

        $observer = $conv->get_observer();

        $arr = ['comment_buttons' => '','id' => $this->get_id()];
        Hook::call('comment_buttons', $arr);
        $comment_buttons = $arr['comment_buttons'];

        $feature_auto_save_draft = ((Features::enabled($conv->get_profile_owner(), 'auto_save_draft')) ? "true" : "false");
        $permanent_draft = ((intval($conv->get_profile_owner()) === intval(local_channel()) && Apps::system_app_installed($conv->get_profile_owner(), 'Drafts')) ? ('Save draft') : EMPTY_STR);



        $comment_box = replace_macros($template, [
            '$return_path' => '',
            '$threaded' => $this->is_threaded(),
            '$jsreload' => $conv->reload,
            '$type' => (($conv->get_mode() === 'channel') ? 'wall-comment' : 'net-comment'),
            '$id' => $this->get_id(),
            '$parent' => $this->get_id(),
            '$comment_buttons' => $comment_buttons,
            '$profile_uid' =>  $conv->get_profile_owner(),
            '$mylink' => $observer['xchan_url'],
            '$mytitle' => t('This is you'),
            '$myphoto' => $observer['xchan_photo_s'],
            '$comment' => t('Comment'),
            '$submit' => t('Submit'),
            '$edat' => EMPTY_STR,
            '$edbold' => t('Bold'),
            '$editalic' => t('Italic'),
            '$eduline' => t('Underline'),
            '$edquote' => t('Quote'),
            '$edcode' => t('Code'),
            '$edimg' => t('Image'),
            '$edatt' => t('Attach/Upload file'),
            '$edurl' => t('Insert Link'),
            '$edvideo' => t('Video'),
            '$preview' => t('Preview'),
            '$reset' => t('Reset'),
            '$indent' => $indent,
            '$can_upload' => (perm_is_allowed($conv->get_profile_owner(), get_observer_hash(), 'write_storage') && $conv->is_uploadable()),
            '$feature_encrypt' => Apps::system_app_installed($conv->get_profile_owner(), 'Secrets'),
            '$feature_markup' => Apps::system_app_installed($conv->get_profile_owner(), 'Markup'),
            '$encrypt' => t('Encrypt text'),
            '$cipher' => $conv->get_cipher(),
            '$sourceapp' => App::$sourcename,
            '$observer' => get_observer_hash(),
            '$anoncomments' => ($conv->get_mode() === 'channel' || $conv->get_mode() === 'display') && perm_is_allowed($conv->get_profile_owner(), '', 'post_comments'),
            '$anonname' => [ 'anonname', t('Your full name (required)') ],
            '$anonmail' => [ 'anonmail', t('Your email address (required)') ],
            '$anonurl'  => [ 'anonurl',  t('Your website URL (optional)') ],
            '$auto_save_draft' => $feature_auto_save_draft,
            '$save' => $permanent_draft,
            '$top' => $this->is_toplevel()
        ]);

        return $comment_box;
    }

    private function get_redirect_url()
    {
        return $this->redirect_url;
    }

    /**
     * Check if we are a wall to wall item and set the relevant properties
     */
    protected function check_wall_to_wall()
    {
        $conv = $this->get_conversation();
        $this->wall_to_wall = false;
        $this->owner_url = '';
        $this->owner_photo = '';
        $this->owner_name = '';
        $this->owner_censored = false;

        if ($conv->get_mode() === 'channel') {
            return;
        }

        if ($this->is_toplevel() && ($this->get_data_value('author_xchan') != $this->get_data_value('owner_xchan'))) {
            $this->owner_url = chanlink_hash($this->data['owner']['xchan_hash']);
            $this->owner_photo = $this->data['owner']['xchan_photo_m'];
            $this->owner_name = $this->data['owner']['xchan_name'];
            $this->owner_addr = $this->data['owner']['xchan_addr'];
            $this->wall_to_wall = true;
        }

        // present friend-of-friend conversations from hyperdrive as relayed posts from the first friend
        // we find among the respondents.

        if ($this->is_toplevel() && (! $this->data['owner']['abook_id'])) {
            if ($this->data['children']) {
                $friend = $this->find_a_friend($this->data['children']);
                if ($friend) {
                    $this->owner_url = $friend['url'];
                    $this->owner_photo = $friend['photo'];
                    $this->owner_name = $friend['name'];
                    $this->owner_addr = $friend['addr'];
                    $this->owner_censored = $friend['censored'];
                    $this->wall_to_wall = true;
                }
            }
        }
    }

    private function find_a_friend($items)
    {
        $ret = null;
        if ($items) {
            foreach ($items as $child) {
                if ($child['author']['abook_id'] && (! intval($child['author']['abook_self']))) {
                    return [
                        'url' => chanlink_hash($child['author']['xchan_hash']),
                        'photo' => $child['author']['xchan_photo_m'],
                        'name' => $child['author']['xchan_name'],
                        'addr' => $child['author']['xchan_addr'],
                        'censored' => $child['author']['xchan_censored'] || $child['author']['abook_censor']
                    ];
                }
                if ($child['children']) {
                    $ret = $this->find_a_friend($child['children']);
                    if ($ret) {
                        break;
                    }
                }
            }
        }
        return $ret;
    }


    private function is_wall_to_wall()
    {
        return $this->wall_to_wall;
    }

    private function get_owner_url()
    {
        return $this->owner_url;
    }

    private function get_owner_photo()
    {
        return $this->owner_photo;
    }

    private function get_owner_name()
    {
        return $this->owner_name;
    }

    private function get_owner_addr()
    {
        return $this->owner_addr;
    }

    private function is_visiting()
    {
        return $this->visiting;
    }
}
