<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libzotdir;
use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\LibBlock;

require_once('include/socgraph.php');
require_once('include/bbcode.php');
require_once('include/html2plain.php');

define( 'DIRECTORY_PAGESIZE', 60);

class Directory extends Controller
{

    public function init()
    {
        App::set_pager_itemspage(DIRECTORY_PAGESIZE);

        if (x($_GET, 'ignore') && local_channel()) {
            q("insert into xign ( uid, xchan ) values ( %d, '%s' ) ",
                intval(local_channel()),
                dbesc($_GET['ignore'])
            );

            Libsync::build_sync_packet(local_channel(), ['xign' => [['uid' => local_channel(), 'xchan' => $_GET['ignore']]]]);
            if ($_REQUEST['return']) {
                goaway(z_root() . '/' . base64_decode($_REQUEST['return']));
            }
            goaway(z_root() . '/directory?f=&suggest=1');
        }

        $observer = get_observer_hash();
        $global_changed = false;
        $safe_changed = false;
        $type_changed = false;
        $active_changed = false;

        if (array_key_exists('global', $_REQUEST)) {
            $globaldir = intval($_REQUEST['global']);
            if (get_config('system', 'localdir_hide')) {
                $globaldir = 1;
            }
            $global_changed = true;
        }
        if ($global_changed) {
            $_SESSION['globaldir'] = $globaldir;
            if ($observer) {
                set_xconfig($observer, 'directory', 'globaldir', $globaldir);
            }
        }

        if (array_key_exists('safe', $_REQUEST)) {
            $safemode = intval($_REQUEST['safe']);
            $safe_changed = true;
        }
        if ($safe_changed) {
            $_SESSION['safemode'] = $safemode;
            if ($observer) {
                set_xconfig($observer, 'directory', 'safemode', $safemode);
            }
        }

        if (array_key_exists('type', $_REQUEST)) {
            $type = intval($_REQUEST['type']);
            $type_changed = true;
        }
        if ($type_changed) {
            $_SESSION['chantype'] = $type;
            if ($observer) {
                set_xconfig($observer, 'directory', 'chantype', $type);
            }
        }

        if (array_key_exists('active', $_REQUEST)) {
            $active = intval($_REQUEST['active']);
            $active_changed = true;
        }
        if ($active_changed) {
            $_SESSION['activedir'] = $active;
            if ($observer) {
                set_xconfig($observer, 'directory', 'activedir', $active);
            }
        }

    }

    public function get()
    {

        if (observer_prohibited()) {
            notice(t('Public access denied.') . EOL);
            return;
        }

        $observer = get_observer_hash();

        if (get_config('system', 'block_public_directory', true) && (!$observer)) {
            notice(t('Public access denied.') . EOL);
            return login(false);
        }

        $globaldir = Libzotdir::get_directory_setting($observer, 'globaldir');

        // override your personal global search pref if we're doing a navbar search of the directory
        if (intval($_REQUEST['navsearch'])) {
            $globaldir = 1;
        }

        $safe_mode = Libzotdir::get_directory_setting($observer, 'safemode');

        $type = Libzotdir::get_directory_setting($observer, 'chantype');

        $active = Libzotdir::get_directory_setting($observer, 'activedir');

        $o = '';
        nav_set_selected('Directory');

        if (x($_POST, 'search')) {
            $search = notags(trim($_POST['search']));
        } else {
            $search = ((x($_GET, 'search')) ? notags(trim(rawurldecode($_GET['search']))) : '');
        }

        if (strpos($search, '=')) {
            $advanced = $search;
        }

        $keywords = (($_GET['keywords']) ? $_GET['keywords'] : '');

        // Suggest channels if no search terms or keywords are given
        $suggest = (local_channel() && x($_REQUEST, 'suggest')) ? $_REQUEST['suggest'] : '';

        if ($suggest) {

            // the directory options have no effect in suggestion mode

            $globaldir = 1;
            $safe_mode = 1;
            $active = 1;
            $type = 0;

            // only return DIRECTORY_PAGESIZE suggestions as the suggestion sorting
            // only works if the suggestion query and the directory query have the
            // same number of results

            App::set_pager_itemspage(60);
            $r = suggestion_query(local_channel(), get_observer_hash(), App::$pager['start'], DIRECTORY_PAGESIZE);

            if (!$r) {
                if ($_REQUEST['aj']) {
                    echo '<div id="content-complete"></div>';
                    killme();
                }

                notice(t('No default suggestions were found.') . EOL);
                return;
            }

            // Remember in which order the suggestions were
            $addresses = [];
            $common = [];
            $index = 0;
            foreach ($r as $rr) {
                $common[$rr['xchan_hash']] = ((intval($rr['total']) > 0) ? intval($rr['total']) - 1 : 0);
                $addresses[$rr['xchan_hash']] = $index++;
            }

            // Build query to get info about suggested people
            $advanced = '';
            foreach (array_keys($addresses) as $address) {
                $advanced .= "xhash=\"$address\" ";
            }
            // Remove last space in the advanced query
            $advanced = rtrim($advanced);

        }

        $tpl = get_markup_template('directory_header.tpl');

        $dirmode = intval(get_config('system', 'directory_mode'));

        $directory_admin = false;

        $url = z_root() . '/dirsearch';
        if (is_site_admin()) {
            $directory_admin = true;
        }

        $contacts = [];

        if (local_channel()) {
            $x = q("select abook_xchan from abook where abook_channel = %d",
                intval(local_channel())
            );
            if ($x) {
                foreach ($x as $xx) {
                    $contacts[] = $xx['abook_xchan'];
                }
            }
        }

        if ($url) {

            $numtags = get_config('system', 'directorytags');

            $kw = ((intval($numtags) > 0) ? intval($numtags) : 50);

            if (get_config('system', 'disable_directory_keywords')) {
                $kw = 0;
            }

            $query = $url . '?f=&kw=' . $kw . (($safe_mode != 1) ? '&safe=' . $safe_mode : '');

            if ($token) {
                $query .= '&t=' . $token;
            }

            if (!$globaldir) {
                $query .= '&hub=' . App::get_hostname();
            }
            if ($search) {
                $query .= '&name=' . urlencode($search) . '&keywords=' . urlencode($search);
            }
            if (strpos($search, '@')) {
                $query .= '&address=' . urlencode($search);
            }
            if (strpos($search, 'http') === 0) {
                $query .= '&url=' . urlencode($search);
            }
            if ($keywords) {
                $query .= '&keywords=' . urlencode($keywords);
            }
            if ($advanced) {
                $query .= '&query=' . urlencode($advanced);
            }
            if (!is_null($type)) {
                $query .= '&type=' . intval($type);
            }
            $directory_sort_order = get_config('system', 'directory_sort_order');
            if (!$directory_sort_order) {
                $directory_sort_order = 'date';
            }

            $sort_order = ((x($_REQUEST, 'order')) ? $_REQUEST['order'] : $directory_sort_order);

            if ($sort_order) {
                $query .= '&order=' . urlencode($sort_order);
            }

            if (App::$pager['page'] != 1) {
                $query .= '&p=' . App::$pager['page'];
            }

            if (isset($active)) {
                $query .= '&active=' . intval($active);
            }


            // logger('mod_directory: query: ' . $query);

            $x = z_fetch_url($query);
            // logger('directory: return from upstream: ' . print_r($x,true), LOGGER_DATA);

            if ($x['success']) {
                $t = 0;
                $j = json_decode($x['body'], true);
                if ($j) {

                    if ($j['results']) {

                        $results = $j['results'];
                        if ($suggest) {
                            // change order to "number of common friends descending"
                            $results = self::reorder_results($results, $addresses);
                        }

                        $entries = [];

                        $photo = 'thumb';

                        foreach ($results as $rr) {

                            $profile_link = chanlink_url($rr['url']);

                            $pdesc = (($rr['description']) ? $rr['description'] . '<br>' : '');
                            $connect_link = ((local_channel()) ? z_root() . '/follow?f=&url=' . urlencode($rr['address']) : '');

                            // Checking status is disabled ATM until someone checks the performance impact more carefully
                            //$online = remote_online_status($rr['address']);
                            $online = '';

                            if (in_array($rr['hash'], $contacts)) {
                                $connect_link = '';
                            }

                            $location = '';
                            if (strlen($rr['locale'])) {
                                $location .= $rr['locale'];
                            }
                            if (strlen($rr['region'])) {
                                if (strlen($rr['locale'])) {
                                    $location .= ', ';
                                }
                                $location .= $rr['region'];
                            }
                            if (strlen($rr['country'])) {
                                if (strlen($location)) {
                                    $location .= ', ';
                                }
                                $location .= $rr['country'];
                            }

                            $age = '';
                            if (strlen($rr['birthday'])) {
                                if (($years = age($rr['birthday'], 'UTC', '')) > 0) {
                                    $age = $years;
                                }
                            }

                            $page_type = '';

                            $total_ratings = '';

                            $profile = $rr;

                            $gender = ((x($profile, 'gender') == 1) ? t('Gender: ') . $profile['gender'] : False);
                            $marital = ((x($profile, 'marital') == 1) ? t('Status: ') . $profile['marital'] : False);
                            $homepage = ((x($profile, 'homepage') == 1) ? t('Homepage: ') : False);
                            $homepageurl = ((x($profile, 'homepage') == 1) ? html2plain($profile['homepage']) : '');
                            $hometown = ((x($profile, 'hometown') == 1) ? html2plain($profile['hometown']) : False);
                            $about = ((x($profile, 'about') == 1) ? zidify_links(bbcode($profile['about'])) : False);

                            $keywords = ((x($profile, 'keywords')) ? $profile['keywords'] : '');

                            $out = '';

                            if ($keywords) {
                                $keywords = str_replace(',', ' ', $keywords);
                                $keywords = str_replace('  ', ' ', $keywords);
                                $karr = explode(' ', $keywords);

                                if ($karr) {
                                    if (local_channel()) {
                                        $pk = q("select keywords from profile where uid = %d and is_default = 1 limit 1",
                                            intval(local_channel())
                                        );
                                        if ($pk) {
                                            $keywords = str_replace(',', ' ', $pk[0]['keywords']);
                                            $keywords = str_replace('  ', ' ', $keywords);
                                            $marr = explode(' ', $keywords);
                                        }
                                    }
                                    foreach ($karr as $k) {
                                        if (strlen($out)) {
                                            $out .= ', ';
                                        }
                                        if ($marr && in_arrayi($k, $marr)) {
                                            $out .= '<a href="' . z_root() . '/directory/f=&keywords=' . urlencode($k) . '"><strong>' . $k . '</strong></a>';
                                        } else {
                                            $out .= '<a href="' . z_root() . '/directory/f=&keywords=' . urlencode($k) . '">' . $k . '</a>';
                                        }
                                    }
                                }
                            }

                            $entry = [
                                'id' => ++$t,
                                'profile_link' => $profile_link,
                                'type' => $rr['type'],
                                'photo' => $rr['photo'],
                                'hash' => $rr['hash'],
                                'alttext' => $rr['name'] . ((local_channel() || remote_channel()) ? ' ' . $rr['address'] : ''),
                                'name' => $rr['name'],
                                'age' => $age,
                                'age_label' => t('Age:'),
                                'profile' => $profile,
                                'address' => $rr['address'],
                                'nickname' => substr($rr['address'], 0, strpos($rr['address'], '@')),
                                'location' => $location,
                                'location_label' => t('Location:'),
                                'gender' => $gender,
                                'total_ratings' => $total_ratings,
                                'viewrate' => true,
                                'canrate' => (($rating_enabled && local_channel()) ? true : false),
                                // 'network'           => network_to_name($rr['network']),
                                // 'network_label'     => t('Network:'),
                                'pdesc' => $pdesc,
                                'pdesc_label' => t('Description:'),
                                'censor' => (($directory_admin) ? 'dircensor/' . $rr['hash'] : ''),
                                'censor_label' => (($rr['censored']) ? t('Uncensor') : t('Censor')),
                                'marital' => $marital,
                                'homepage' => $homepage,
                                'homepageurl' => (($safe_mode) ? $homepageurl : linkify($homepageurl)),
                                'hometown' => $hometown,
                                'hometown_label' => t('Hometown:'),
                                'about' => $about,
                                'about_label' => t('About:'),
                                'conn_label' => t('Connect'),
                                'forum_label' => t('Group'),
                                'collections_label' => t('Collection'),
                                'connect' => $connect_link,
                                'online' => $online,
                                'kw' => (($out) ? t('Keywords: ') : ''),
                                'keywords' => $out,
                                'ignlink' => $suggest ? z_root() . '/directory?ignore=' . $rr['hash'] : '',
                                'ignore_label' => t('Don\'t suggest'),
                                'common_friends' => (($common[$rr['hash']]) ? intval($common[$rr['hash']]) : ''),
                                'common_label' => t('Suggestion ranking:'),
                                'common_count' => intval($common[$rr['hash']]),
                                'safe' => $safe_mode
                            ];


                            $blocked = LibBlock::fetch($channel['channel_id'], BLOCKTYPE_SERVER);

                            $found_block = false;
                            if ($blocked) {
                                foreach ($blocked as $b) {
                                    if (strpos($rr['site_url'], $b['block_entity']) !== false) {
                                        $found_block = true;
                                        break;
                                    }
                                }
                                if ($found_block) {
                                    continue;
                                }
                            }

                            if (LibBlock::fetch_by_entity(local_channel(), $entry['hash'])) {
                                continue;
                            }

                            $arr = array('contact' => $rr, 'entry' => $entry);

                            call_hooks('directory_item', $arr);

                            unset($profile);
                            unset($location);

                            if (!$arr['entry']) {
                                continue;
                            }

                            if ($sort_order == '' && $suggest) {
                                $entries[$addresses[$rr['address']]] = $arr['entry']; // Use the same indexes as originally to get the best suggestion first
                            } else {
                                $entries[] = $arr['entry'];
                            }
                        }

                        ksort($entries); // Sort array by key so that foreach-constructs work as expected

                        if ($j['keywords']) {
                            App::$data['directory_keywords'] = $j['keywords'];
                        }

                        // logger('mod_directory: entries: ' . print_r($entries,true), LOGGER_DATA);


                        if ($_REQUEST['aj']) {
                            if ($entries) {
                                $o = replace_macros(get_markup_template('directajax.tpl'), ['$entries' => $entries]);
                            } else {
                                $o = '<div id="content-complete"></div>';
                            }
                            echo $o;
                            killme();
                        } else {
                            $maxheight = 94;

                            $dirtitle = (($globaldir) ? t('Directory') : t('Local Directory'));

                            $o .= "<script> var page_query = '" . escape_tags(urlencode($_GET['req'])) . "'; var extra_args = '" . extra_query_args() . "' ; divmore_height = " . intval($maxheight) . ";  </script>";
                            $o .= replace_macros($tpl, [
                                '$search' => $search,
                                '$desc' => t('Find'),
                                '$finddsc' => t('Finding:'),
                                '$safetxt' => htmlspecialchars($search, ENT_QUOTES, 'UTF-8'),
                                '$entries' => $entries,
                                '$dirlbl' => $suggest ? t('Channel Suggestions') : $dirtitle,
                                '$submit' => t('Find'),
                                '$next' => alt_pager($j['records'], t('next page'), t('previous page')),
                                '$sort' => t('Sort options'),
                                '$normal' => t('Alphabetic'),
                                '$reverse' => t('Reverse Alphabetic'),
                                '$date' => t('Newest to Oldest'),
                                '$reversedate' => t('Oldest to Newest'),
                                '$suggest' => $suggest ? '&suggest=1' : ''
                            ]);
                        }
                    } else {
                        if ($_REQUEST['aj']) {
                            $o = '<div id="content-complete"></div>';
                            echo $o;
                            killme();
                        }
                        if ($search && App::$pager['page'] == 1 && $j['records'] == 0) {
                            if (strpos($search, '@')) {
                                goaway(z_root() . '/chanview/?f=&address=' . $search);
                            } elseif (strpos($search, 'http') === 0) {
                                goaway(z_root() . '/chanview/?f=&url=' . $search);
                            } else {
                                $r = q("select xchan_hash from xchan where xchan_name = '%s' limit 1",
                                    dbesc($search)
                                );
                                if ($r) {
                                    goaway(z_root() . '/chanview/?f=&hash=' . urlencode($r[0]['xchan_hash']));
                                }
                            }
                        }
                        info(t("No entries (some entries may be hidden).") . EOL);
                    }
                }
            }
        }
        return $o;
    }


    public static function reorder_results($results, $suggests)
    {
        if (!$suggests) {
            return $results;
        }

        $out = [];
        foreach ($suggests as $k => $v) {
            foreach ($results as $rv) {
                if ($k == $rv['hash']) {
                    $out[intval($v)] = $rv;
                    break;
                }
            }
        }
        return $out;
    }

}
