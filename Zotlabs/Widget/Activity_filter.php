<?php

namespace Zotlabs\Widget;

use App;
use Zotlabs\Lib\Apps;

class Activity_filter
{

    public function widget($arr)
    {

        if (!local_channel()) {
            return EMPTY_STR;
        }

        $cmd = App::$cmd;

        $filter_active = false;
        $events_active = false;
        $video_active = false;
        $polls_active = false;
        $group_active = false;
        $drafts_active = false;
        $forum_active = false;

        $tabs = [];

        $dm_active = ((isset($_GET['dm']) && intval($_GET['dm'])) ? 'active' : '');
        if ($dm_active) {
            $filter_active = 'dm';
        }

        $tabs[] = [
            'label' => t('Direct Messages'),
            'icon' => 'envelope-o',
            'url' => z_root() . '/' . $cmd . '/?dm=1',
            'sel' => $dm_active,
            'title' => t('Show direct (private) messages')
        ];


        $conv_active = ((isset($_GET['conv']) && intval($_GET['conv'])) ? 'active' : '');
        if ($conv_active) {
            $filter_active = 'personal';
        }

        $tabs[] = [
            'label' => t('Personal Posts'),
            'icon' => 'user-circle',
            'url' => z_root() . '/' . $cmd . '/?conv=1',
            'sel' => $conv_active,
            'title' => t('Show posts that mention or involve me')
        ];

        $starred_active = ((isset($_GET['star']) && intval($_GET['star'])) ? 'active' : '');
        if ($starred_active) {
            $filter_active = 'star';
        }

        $tabs[] = [
            'label' => t('Saved Posts'),
            'icon' => 'star',
            'url' => z_root() . '/' . $cmd . '/?star=1',
            'sel' => $starred_active,
            'title' => t('Show posts that I have saved')
        ];

        if (local_channel() && Apps::system_app_installed(local_channel(), 'Drafts')) {
            $drafts_active = ((isset($_GET['draft']) && intval($_GET['draft'])) ? 'active' : '');
            if ($drafts_active) {
                $filter_active = 'drafts';
            }

            $tabs[] = [
                'label' => t('Drafts'),
                'icon' => 'floppy-o',
                'url' => z_root() . '/' . $cmd . '/?draft=1',
                'sel' => $drafts_active,
                'title' => t('Show drafts that I have saved')
            ];
        }

        if (x($_GET, 'search')) {
            $video_active = (($_GET['search'] == 'video]') ? 'active' : '');
            $filter_active = (($events_active) ? 'videos' : 'search');
        }

        $tabs[] = [
            'label' => t('Videos'),
            'icon' => 'video',
            'url' => z_root() . '/' . $cmd . '/?search=video%5D',
            'sel' => $video_active,
            'title' => t('Show posts that include videos')
        ];

        if (x($_GET, 'verb')) {
            $events_active = (($_GET['verb'] == '.Event') ? 'active' : '');
            $polls_active = (($_GET['verb'] == '.Question') ? 'active' : '');
            $filter_active = (($events_active) ? 'events' : 'polls');
        }

        $tabs[] = [
            'label' => t('Events'),
            'icon' => 'calendar',
            'url' => z_root() . '/' . $cmd . '/?verb=%2EEvent',
            'sel' => $events_active,
            'title' => t('Show posts that include events')
        ];

        $tabs[] = [
            'label' => t('Polls'),
            'icon' => 'bar-chart',
            'url' => z_root() . '/' . $cmd . '/?verb=%2EQuestion',
            'sel' => $polls_active,
            'title' => t('Show posts that include polls')
        ];


        $groups = q(
            "SELECT * FROM pgrp WHERE deleted = 0 AND uid = %d ORDER BY gname ASC",
            intval(local_channel())
        );

        if ($groups || Apps::system_app_installed(local_channel(), 'Virtual Lists')) {
            if ($groups) {
                foreach ($groups as $g) {
                    if (x($_GET, 'gid')) {
                        $group_active = (($_GET['gid'] == $g['id']) ? 'active' : '');
                        $filter_active = 'group';
                    }
                    $gsub[] = [
                        'label' => $g['gname'],
                        'icon' => '',
                        'url' => z_root() . '/' . $cmd . '/?f=&gid=' . $g['id'],
                        'sel' => $group_active,
                        'title' => sprintf(t('Show posts related to the %s access list'), $g['gname'])
                    ];
                }
            }
            if (Apps::system_app_installed(local_channel(), 'Virtual Lists')) {
                foreach ([':1', ':2', ':3'] as $l) {
                    switch ($l) {
                        case ':1':
                            $gname = t('Connections');
                            break;
                        case ':2':
                            $gname = t('Nomad');
                            break;
                        case ':3':
                            $gname = t('ActivityPub');
                            break;
                        default:
                            break;
                    }

                    if (x($_GET, 'gid')) {
                        $group_active = (($_GET['gid'] == $l) ? 'active' : '');
                        $filter_active = 'group';
                    }

                    $gsub[] = [
                        'label' => $gname,
                        'icon' => '',
                        'url' => z_root() . '/' . $cmd . '/?f=&gid=' . $l,
                        'sel' => $group_active,
                        'title' => sprintf(t('Show posts related to the %s access list'), $gname)
                    ];
                }
            }
            $tabs[] = [
                'id' => 'privacy_groups',
                'label' => t('Lists'),
                'icon' => 'users',
                'url' => '#',
                'sel' => (($filter_active == 'group') ? true : false),
                'title' => t('Show my access lists'),
                'sub' => $gsub
            ];
        }

        $forums = get_forum_channels(local_channel(), 1);

        if ($forums) {
            foreach ($forums as $f) {
                if (x($_GET, 'pf') && x($_GET, 'cid')) {
                    $forum_active = ((x($_GET, 'pf') && $_GET['cid'] == $f['abook_id']) ? 'active' : '');
                    $filter_active = 'forums';
                }
                $fsub[] = [
                    'label' => $f['xchan_name'],
                    'img' => $f['xchan_photo_s'],
                    'url' => z_root() . '/' . $cmd . '/?f=&pf=1&cid=' . $f['abook_id'],
                    'sel' => $forum_active,
                    'title' => t('Show posts to this group'),
                    'lock' => ((isset($f['private_forum']) && $f['private_forum']) ? 'lock' : ''),
                    'edit' => t('New post'),
                    'edit_url' => $f['xchan_url']
                ];
            }

            $tabs[] = [
                'id' => 'forums',
                'label' => t('Groups'),
                'icon' => 'comments-o',
                'url' => '#',
                'sel' => (($filter_active == 'forums') ? true : false),
                'title' => t('Show groups'),
                'sub' => $fsub
            ];
        }

        if (feature_enabled(local_channel(), 'filing')) {
            $terms = q(
                "select distinct term from term where uid = %d and ttype = %d order by term asc",
                intval(local_channel()),
                intval(TERM_FILE)
            );

            if ($terms) {
                foreach ($terms as $t) {
                    if (x($_GET, 'file')) {
                        $file_active = (($_GET['file'] == $t['term']) ? 'active' : '');
                        $filter_active = 'file';
                    }
                    $tsub[] = [
                        'label' => $t['term'],
                        'icon' => '',
                        'url' => z_root() . '/' . $cmd . '/?f=&file=' . $t['term'],
                        'sel' => $file_active,
                        'title' => sprintf(t('Show posts that I have filed to %s'), $t['term']),
                    ];
                }

                $tabs[] = [
                    'id' => 'saved_folders',
                    'label' => t('Saved Folders'),
                    'icon' => 'folder',
                    'url' => '#',
                    'sel' => (($filter_active == 'file') ? true : false),
                    'title' => t('Show filed post categories'),
                    'sub' => $tsub

                ];
            }
        }

        $ft = get_pconfig(local_channel(), 'system', 'followed_tags', EMPTY_STR);
        if (is_array($ft) && $ft) {
            foreach ($ft as $t) {
                $tag_active = ((isset($_GET['netsearch']) && $_GET['netsearch'] === '#' . $t) ? 'active' : '');
                if ($tag_active) {
                    $filter_active = 'tags';
                }

                $tsub[] = [
                    'label' => '#' . $t,
                    'icon' => '',
                    'url' => z_root() . '/' . $cmd . '/?search=' . '%23' . $t,
                    'sel' => $tag_active,
                    'title' => sprintf(t('Show posts with hashtag %s'), '#' . $t),
                ];
            }

            $tabs[] = [
                'id' => 'followed_tags',
                'label' => t('Followed Hashtags'),
                'icon' => 'bookmark',
                'url' => '#',
                'sel' => (($filter_active == 'tags') ? true : false),
                'title' => t('Show followed hashtags'),
                'sub' => $tsub
            ];
        }


//      if(x($_GET,'search')) {
//          $filter_active = 'search';
//          $tabs[] = [
//              'label' => t('Search'),
//              'icon' => 'search',
//              'url' => z_root() . '/' . $cmd . '/?search=' . $_GET['search'],
//              'sel' => 'active disabled',
//              'title' => t('Panel search')
//          ];
//      }

        $name = [];
        if (isset($_GET['name']) && $_GET['name']) {
            $filter_active = 'name';
        }
        $name = [
            'label' => x($_GET, 'name') ? $_GET['name'] : t('Name'),
            'icon' => 'filter',
            'url' => z_root() . '/' . $cmd . '/',
            'sel' => $filter_active == 'name' ? 'is-valid' : '',
            'title' => ''
        ];

        $reset = [];
        if ($filter_active) {
            $reset = [
                'label' => '',
                'icon' => 'remove',
                'url' => z_root() . '/' . $cmd,
                'sel' => '',
                'title' => t('Remove active filter')
            ];
        }

        $arr = ['tabs' => $tabs];

        call_hooks('activity_filter', $arr);

        $o = '';

        if ($arr['tabs']) {
            $content = replace_macros(get_markup_template('common_pills.tpl'), [
                '$pills' => $arr['tabs']
            ]);

            $o .= replace_macros(get_markup_template('activity_filter_widget.tpl'), [
                '$title' => t('Stream Filters'),
    			'$content_id' => 'activity-filter-widget',
                '$reset' => $reset,
                '$content' => $content,
                '$name' => $name
            ]);
        }

        return $o;
    }
}
