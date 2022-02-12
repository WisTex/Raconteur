<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Libzotdir;
use Zotlabs\Lib\LibBlock;
use Zotlabs\Web\Controller;
use Zotlabs\Render\Theme;


class Sites extends Controller
{

    public function get()
    {

        $sql_extra = (($_REQUEST['project']) ? " and site_project = '" . escape_tags(protect_sprintf(dbesc($_REQUEST['project']))) . "' " : "");

        $desc = t('This page provides information about related projects and websites that are currently known to this system. These are a small fraction of the thousands of websites and dozens of projects and providers which make up the fediverse.');

        $j = [];

        $r = q("select * from site where site_flags != 256 and site_dead = 0 $sql_extra order by site_update desc");


        if ($r) {
            $blocked = LibBlock::fetch($channel['channel_id'], BLOCKTYPE_SERVER);
            foreach ($r as $rr) {
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

                if (!check_siteallowed($rr['site_url'])) {
                    continue;
                }

                if ($rr['site_access'] == ACCESS_FREE) {
                    $access = t('free');
                } elseif ($rr['site_access'] == ACCESS_PAID) {
                    $access = t('subscription');
                } elseif ($rr['site_access'] == ACCESS_TIERED) {
                    $access = t('tiered service plans');
                } else {
                    $access = 'private';
                }

                if ($rr['site_register'] == REGISTER_OPEN) {
                    $register = t('Register');
                } elseif ($rr['site_register'] == REGISTER_APPROVE) {
                    $register = t('Register (requires approval)');
                } else {
                    $register = 'closed';
                }


                $sitename = get_sconfig($rr['site_url'], 'system', 'sitename', $rr['site_url']);
                if ($sitename !== $rr['site_url']) {
                    $sitename .= ' (' . $rr['site_url'] . ')';
                }
                $disabled = (($access === 'private' || $register === 'closed') ? true : false);
                $logo = get_sconfig($rr['site_url'], 'system', 'logo');
                $about = get_sconfig($rr['site_url'], 'system', 'about');

                if (!$logo && file_exists('images/' . strtolower($rr['site_project']) . '.png')) {
                    $logo = 'images/' . strtolower($rr['site_project']) . '.png';
                }
                if (!$logo) {
                    if (intval($rr['site_type']) != 0) {
                        $logo = 'images/activitypub-300.png';
                    } else {
                        $logo = 'images/default_profile_photos/red_koala_trans/300.png';
                    }
                }

                if ($rr['site_sellpage']) {
                    $register_link = $rr['site_sellpage'];
                } else {
                    $register_link = $rr['site_url'] . '/register';
                }

                $j[] = [
                    'profile_link' => $rr['site_url'],
                    'name' => $sitename,
                    'access' => $access,
                    'register' => $register_link,
                    'sellpage' => $rr['site_sellpage'],
                    'location_label' => t('Location'),
                    'location' => $rr['site_location'],
                    'project' => $rr['site_project'],
                    'version' => $rr['site_version'],
                    'photo' => $logo,
                    'about' => bbcode($about),
                    'hash' => substr(hash('sha256', $rr['site_url']), 0, 16),
                    'network_label' => t('Project'),
                    'network' => $rr['site_project'],
                    'version_label' => t('Version'),
                    'version' => $rr['site_version'],
                    'private' => $disabled,
                    'connect' => (($disabled) ? '' : $register_link),
                    'connect_label' => $register,
                    'access' => (($access === 'private') ? '' : $access),
                    'access_label' => t('Access type'),
                ];
            }
        }

        $o = replace_macros(Theme::get_template('sitentry_header.tpl'), [
            '$dirlbl' => t('Affiliated Sites'),
            '$desc' => $desc,
            '$entries' => $j,
        ]);


        return $o;
    }

    public function sort_sites($a)
    {
        $ret = [];
        if ($a) {
            foreach ($a as $e) {
                $projectname = explode(' ', $e['project']);
                $ret[$projectname[0]][] = $e;
            }
        }
        $projects = array_keys($ret);
        rsort($projects);

        $newret = [];
        foreach ($projects as $p) {
            $newret[$p] = $ret[$p];
        }

        return $newret;
    }

    public function sort_versions($a, $b)
    {
        return version_compare($b['version'], $a['version']);
    }
}
