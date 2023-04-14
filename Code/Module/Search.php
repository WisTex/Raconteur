<?php

namespace Code\Module;

use App;
use Code\Lib\Libprofile;
use Code\Lib\Libzot;
use Code\Web\Controller;
use Code\Lib\Activity;
use Code\Lib\ActivityStreams;
use Code\Lib\ASCollection;
use Code\Lib\Queue;
use Code\Daemon\Run;
use Code\Lib\Channel;
use Code\Lib\Navbar;
use Code\Render\Theme;
use Code\Lib\LDSignatures;
use Code\Web\HTTPSig;

require_once("include/bbcode.php");
require_once('include/security.php');
require_once('include/conversation.php');


class Search extends Controller
{

    // State passed in from the Update module.

    public $profile_uid = 0;
    public $loading = 0;
    public $updating = 0;
    public $maxtags = 0;
    public $mintags = 0;
    public $search_channel =  null;

    public function init()
    {
        if (x($_REQUEST, 'search')) {
            App::$data['search'] = escape_tags($_REQUEST['search']);
        }
    }


    public function get()
    {

        $format = (($_REQUEST['module_format']) ?: '');
        if (ActivityStreams::is_as_request() || Libzot::is_nomad_request()) {
            $format = 'json';
        }
        if ($format !== '') {
            $this->updating = $this->loading = 1;
        }

        if ($format === 'json') {
            $sigdata = HTTPSig::verify(EMPTY_STR);
            if ($sigdata['portable_id'] && $sigdata['header_valid']) {
                $portable_id = $sigdata['portable_id'];
                if (!check_channelallowed($portable_id)) {
                    http_status_exit(403, 'Permission denied');
                }
                if (!check_siteallowed($sigdata['signer'])) {
                    http_status_exit(403, 'Permission denied');
                }
                observer_auth($portable_id);
            }
        }

        if ($this->profile_uid) {
            $this->search_channel = Channel::from_id($this->profile_uid);
        }

        if (! $this->search_channel) {
            $channel = (argc() > 1) ? Channel::from_username(argv(1)) : Channel::get_system();
            if ($channel) {
                $this->search_channel = $channel;
                // Don't load the profile unless this is a channel search. 
                if (argc() > 1) {
                    Libprofile::load($channel['channel_address'], 0);
                }
            }
        }

        if ($this->search_channel) {
            if (Channel::is_system($this->search_channel['channel_id'])) {
                if (get_config('system', 'block_public_search', 1)) {
                    // Local channels and clones of local channels are permitted when
                    // public search is disabled.
                    $clone = null;
                    if (remote_channel() && !local_channel()) {
                        $clone = Channel::from_hash(get_observer_hash());
                    }
                    if (!$clone && !local_channel()) {
                        http_status_exit(403, 'Permission denied.');
                    }
                }
            }
            else {
                // This is a channel search, not a site search.
                if (!(perm_is_allowed($this->search_channel['channel_id'], get_observer_hash(), 'view_stream')
                      && perm_is_allowed($this->search_channel['channel_id'], get_observer_hash(), 'search_stream'))) {
                    http_status_exit(403, 'Permission denied.');
                }
            }

        }

        if ($this->loading) {
            $_SESSION['loadtime'] = datetime_convert();
        }
        Navbar::set_selected('Search');


        $observer = App::get_observer();
        $observer_hash = (($observer) ? $observer['xchan_hash'] : '');

        $output = '<div id="live-search"></div>' . "\r\n";
        $output .= '<div class="generic-content-wrapper-styled">' . "\r\n";
        $output .= '<h3>' . t('Search') . '</h3>';

        if (!empty(App::$data['search'])) {
            $search = trim(App::$data['search']);
        } else {
            $search = ((x($_GET, 'search')) ? trim(escape_tags(rawurldecode($_GET['search']))) : '');
        }
        $saved_id = 'search=' . urlencode($_GET['search']);
        $tag = false;
        if (x($_GET, 'tag')) {
            $tag = true;
            $search = ((x($_GET, 'tag')) ? trim(escape_tags(rawurldecode($_GET['tag']))) : '');
            $saved_id = 'tag=' . urlencode($_GET['tag']);
        }

        $output .= search($search, 'search-box', '/search' . ((argc() > 1) ? '/' . argv(1) : ''), (bool)local_channel());

        // ActivityStreams object fetches from the navbar

        if (local_channel() && str_starts_with($search, 'https://') && (!$this->updating) && (!$this->loading)) {
            self::apsearch($search);
        }

        $this->maxtags = ($_REQUEST['maxtags']) ? intval($_REQUEST['maxtags']) : 0;
        $this->mintags = ($_REQUEST['mintags']) ? intval($_REQUEST['mintags']) : 0;

        if (str_starts_with($search, '#')) {
            $tag = true;
            $search = substr($search, 1);
            if (preg_match('/(&lt;|&gt;)([0-9]+)/ism',$search,$matches )) {
                if ($matches[1] === '&lt;') {
                    $this->maxtags = $matches[2];
                    $search = substr($search,0,strpos($search,'&lt;'));
                }
                else {
                    $this->mintags = $matches[2];
                    $search = substr($search, 0, strpos($search, '&gt;'));
                }

            }

        }
        if (str_starts_with($search, '@') && $format === '') {
            $search = substr($search, 1);
            goaway(z_root() . '/directory' . '?f=1&navsearch=1&search=' . $search);
        }
        if (str_starts_with($search, '!') && $format === '') {
            $search = substr($search, 1);
            goaway(z_root() . '/directory' . '?f=1&navsearch=1&search=' . $search);
        }
        if (str_starts_with($search, '?') && $format === '') {
            $search = substr($search, 1);
            goaway(z_root() . '/help' . '?f=1&navsearch=1&search=' . $search);
        }

        // look for a naked webbie
        if (str_contains($search, '@') && !str_starts_with($search, 'http') && $format === '') {
            goaway(z_root() . '/directory' . '?f=1&navsearch=1&search=' . $search);
        }

        if ($search) {
            if ($tag) {
                $wildtag = str_replace('*', '%', $search);
                $sql_extra = sprintf(
                    " AND item.id IN (select oid from term where otype = %d and ttype in ( %d , %d) and term like '%s') ",
                    intval(TERM_OBJ_POST),
                    intval(TERM_HASHTAG),
                    intval(TERM_COMMUNITYTAG),
                    dbesc(protect_sprintf($wildtag))
                );
            } else {
                $regstr = db_getfunc('REGEXP');
                $sql_extra = sprintf(" AND (item.title $regstr '%s' OR item.body $regstr '%s') ", dbesc(protect_sprintf(preg_quote($search))), dbesc(protect_sprintf(preg_quote($search))));
            }

        }
        else {
            if ($format === '') {
                return $output;
            }
            // An empty JSON query should return an empty result set.
            $sql_extra = " AND item.id = 0 ";
        }

        if ((!$this->updating) && (!$this->loading)) {
            $static = ((local_channel()) ? Channel::manual_conv_update(local_channel()) : 0);

            // This is ugly, but we can't pass the profile_uid through the session to the ajax updater,
            // because browser prefetching might change it on us. We have to deliver it with the page.

            $output .= '<div id="live-search"></div>' . "\r\n";
            $output .= "<script> var profile_uid = " . intval($this->search_channel['channel_id'])
                . "; var netargs = '?f='; var profile_page = " . App::$pager['page'] . "; </script>\r\n";

            App::$page['htmlhead'] .= replace_macros(Theme::get_template("build_query.tpl"), [
                '$baseurl' => z_root(),
                '$pgtype' => 'search',
                '$uid' => (($this->search_channel['channel_id']) ?: '0'),
                '$gid' => '0',
                '$cid' => '0',
                '$cmin' => '(-1)',
                '$cmax' => '(-1)',
                '$star' => '0',
                '$liked' => '0',
                '$conv' => '0',
                '$spam' => '0',
                '$fh' => '0',
                '$dm' => '0',
                '$nouveau' => '0',
                '$wall' => '0',
                '$draft' => '0',
                '$static' => $static,
                '$list' => ((x($_REQUEST, 'list')) ? intval($_REQUEST['list']) : 0),
                '$page' => ((App::$pager['page'] != 1) ? App::$pager['page'] : 1),
                '$search' => (($tag) ? urlencode('#') : '') . $search,
                '$xchan' => '',
                '$order' => '',
                '$file' => '',
                '$cats' => '',
                '$tags' => '',
                '$mid' => '',
                '$verb' => '',
                '$net' => '',
                '$dend' => '',
                '$dbegin' => '',
                '$distance' => '0',
                '$distance_from' => '',
                '$maxtags' => $this->maxtags,
                '$mintags' => $this->mintags,
            ]);
        }

        $item_normal = item_normal_search();
        $pub_sql = item_permissions_sql(0, $observer_hash);

        if (Channel::is_system($this->search_channel['channel_id'])) {
            $searchables = [];
            $allChannels = q("select channel_id from channel where channel_removed = 0");
            if ($allChannels) {
                foreach ($allChannels as $oneChannel) {
                    if (Channel::is_system($oneChannel['channel_id'])) {
                        continue;
                    }
                    if (perm_is_allowed($oneChannel['channel_id'], $observer_hash, 'view_stream')
                        && perm_is_allowed($oneChannel['channel_id'], $observer_hash, 'search_stream')) {
                        $searchables[] = $oneChannel['channel_id'];
                    }
                }
                if ($searchables) {
                    $searchIds = implode(',', $searchables);
                } else {
                    $searchIds = 0;
                }
            }
            // We might arrive at this point if public search is allowed, but nobody on this site
            // has made content available either to the public or to the requestor.
            if (!$searchIds) {
                http_status_exit(403, 'Permission denied.');
            }
        }

        $r = null;

        if (($this->updating) && ($this->loading)) {
            $itemspage = get_pconfig(local_channel(), 'system', 'itemspage');
            App::set_pager_itemspage(((intval($itemspage)) ? $itemspage : 20));
            $pager_sql = sprintf(" LIMIT %d OFFSET %d ", intval(App::$pager['itemspage']), intval(App::$pager['start']));

            // if logged in locally, first look in the items you own
            // and if this returns zero results, resort to searching elsewhere on the site.
            // Ideally these results would be merged but this can be difficult
            // and results in lots of duplicated content and/or messed up pagination

            if (Channel::is_system($this->search_channel['channel_id'])) {
                $r = q("SELECT mid, MAX(id) as item_id from item WHERE true
                    $pub_sql
                    $item_normal
                    $sql_extra
                    and uid in ($searchIds)
                    group by mid, created order by created desc $pager_sql");
            }
            else {
                $r = q(
                    "SELECT mid, MAX(id) as item_id from item where uid = %d
                    $pub_sql 
                    $item_normal
                    $sql_extra
                    group by mid, created order by created desc $pager_sql ",
                    intval($this->search_channel['channel_id'])
                );
            }

            if ($r) {
                $str = ids_to_querystr($r, 'item_id');
                $r = q("select *, id as item_id from item where id in ( " . $str . ") order by created desc ");
            }
        }

        if ($r) {
            xchan_query($r);
            $items = fetch_post_tags($r);
        }
        else {
            $items = [];
        }

        $filteredItems = [];
        if ($this->maxtags) {
            foreach ($items as $item) {
                if ($item['term']) {
                    $ntags = $this->count_hashtags($item['term']);
                    if ($ntags < $this->maxtags) {
                        $filteredItems[] = $item;
                    }
                }
            }
        }

        if ($this->mintags) {
            foreach ($items as $item) {
                if ($item['term']) {
                    $ntags = $this->count_hashtags($item['term']);
                    if ($ntags > $this->maxtags) {
                        $filteredItems[] = $item;
                    }
                }
            }
        }
        if ($this->maxtags || $this->mintags) {
            $items = $filteredItems;
        }
    
        if ($format === 'json') {

            $chan = Channel::get_system();
    
            $i = Activity::encode_item_collection($items, 'search?' . $saved_id , 'OrderedCollection', true, count($items));
    
            $x = array_merge(Activity::ap_context(), $i);

            $headers = [];
            $headers['Content-Type'] = 'application/x-nomad+json';
            $x['signature'] = LDSignatures::sign($x, $chan);
            $ret = json_encode($x, JSON_UNESCAPED_SLASHES);
            $headers['Digest'] = HTTPSig::generate_digest_header($ret);
            $headers['(request-target)'] = strtolower($_SERVER['REQUEST_METHOD']) . ' ' . $_SERVER['REQUEST_URI'];
            $h = HTTPSig::create_sig($headers, $chan['channel_prvkey'], Channel::url($chan));
            HTTPSig::set_headers($h);
            echo $ret;
            killme();
        }

        if ($tag) {
            $output .= '<h2>' . sprintf(t('Items tagged with: %s'), $search) . '</h2>';
        }
        else {
            $output .= '<h2>' . sprintf(t('Search results for: %s'), $search) . '</h2>';
        }

        $output .= conversation($items, 'search', $this->updating, 'client');

        $output .= '</div>';

        return $output;
    }

    public static function apsearch($search) {
        logger('searching for ActivityPub');
        if (($pos = strpos($search,'b64.')) !== false) {
            $search = substr($search,$pos + 4);
            if (($pos2 = strpos($search,'?')) !== false) {
                $search = substr($search,0,$pos2);
            }
            $search = base64_decode($search);
        }
        logger('Search: ' . $search);
        $url = htmlspecialchars_decode($search);
        $channel = App::get_channel();
        $hash = EMPTY_STR;
        $j = Activity::fetch($url, $channel);
        if ($j) {
            if (isset($j['type']) && ActivityStreams::is_an_actor($j['type'])) {
                Activity::actor_store($j['id'], $j, true);
                goaway(z_root() . '/directory' . '?f=1&navsearch=1&search=' . $search);
            }
            $AS = new ActivityStreams($j, null, true);
            if ($AS->is_valid() && isset($AS->data['type'])) {
                if (is_array($AS->obj)) {
                    // matches Collection and orderedCollection
                    if (isset($AS->obj['type']) && str_contains($AS->obj['type'], 'Collection')) {
                        // Collections are awkward to process because they can be huge.
                        // Our strategy is to limit a navbar search to 100 Collection items
                        // and only fetch the first 10 conversations in the foreground.
                        // We'll queue the rest, and then send you to a page where
                        // you can see something we've imported.
                        // You should start to see notifications as other conversations
                        // are fetched in the background while you're looking at the first ones.

                        $max = intval(get_config('system', 'max_imported_search_collection', 100));

                        if (intval($max)) {
                            $obj = new ASCollection($url, $channel, 0, $max);
                            $messages = $obj->get();
                            // logger('received: ' . print_r($messages,true));
                            $author = null;
                            if ($messages) {
                                logger('received ' . count($messages) . ' items from collection.', LOGGER_DEBUG);
                                $processed = 0;
                                foreach ($messages as $message) {
                                    $processed++;
                                    // only process the first several items in the foreground and
                                    // queue the remainder.
                                    if ($processed > 10) {
                                        $fetch_url = ((is_string($message)) ? $message : EMPTY_STR);
                                        $fetch_url = ((is_array($message) && array_key_exists('id', $message)) ? $message['id'] : $fetch_url);

                                        if (!$fetch_url) {
                                            continue;
                                        }

                                        $hash = new_uuid();
                                        Queue::insert(
                                            [
                                                'hash' => $hash,
                                                'account_id' => $channel['channel_account_id'],
                                                'channel_id' => $channel['channel_id'],
                                                'posturl' => $fetch_url,
                                                'notify' => EMPTY_STR,
                                                'msg' => EMPTY_STR,
                                                'driver' => 'asfetch'
                                            ]
                                        );
                                        continue;
                                    }

                                    if (is_string($message)) {
                                        $message = Activity::fetch($message, App::get_channel());
                                    }
                                    $AS = new ActivityStreams($message, null, true);
                                    if ($AS->is_valid() && is_array($AS->obj)) {
                                        $item = Activity::decode_note($AS, true);
                                    }
                                    if ($item) {
                                        if (!$author) {
                                            $author = $item['author_xchan'];
                                        }
                                        Activity::store(App::get_channel(), get_observer_hash(), $AS, $item, true, true);
                                    }
                                }
                                if ($hash) {
                                    Run::Summon(['Deliver', $hash]);
                                }
                            }

                            // This will go to the right place most but not all the time.
                            // It will go to a relevant place all the time, so we'll use it.

                            if ($author) {
                                goaway(z_root() . '/stream/?xchan=' . urlencode($author));
                            }
                            goaway(z_root() . '/stream');
                        }
                    }
                    else {
                        // It wasn't a Collection object and wasn't an Actor object,
                        // so let's see if it decodes. The boolean flag enables html
                        // cache of the item
                        $item = Activity::decode_note($AS, true);

                        if ($item) {
                            Activity::store(App::get_channel(), get_observer_hash(), $AS, $item, true, true);
                            goaway(z_root() . '/display/?mid=' . gen_link_id($item['mid']));
                        }
                        else {
                            notice( t('Item not found.') . EOL);
                            return '';
                        }
                    }
                }
            }
        }
        return '';
    }

    public function count_hashtags($terms): int
    {
        $total = 0;
        if (is_array($terms)) {
            foreach ($terms as $term) {
                if ($term['ttype'] === TERM_HASHTAG) {
                    $total ++;
                }
            }
        }
        return $total;
    }
}
