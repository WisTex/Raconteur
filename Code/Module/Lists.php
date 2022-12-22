<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\Libsync;
use Code\Lib\AccessList;
use Code\Lib\ActivityStreams;
use Code\Lib\Activity;
use Code\Web\HTTPSig;
use Code\Lib\Config;
use Code\Lib\Channel;
use Code\Lib\Navbar;
use Code\Render\Theme;


class Lists extends Controller
{

    public function init()
    {
        if (ActivityStreams::is_as_request()) {
            $item_id = argv(1);
            if (!$item_id) {
                http_status_exit(404, 'Not found');
            }
            $x = q(
                "select * from pgrp where hash = '%s' limit 1",
                dbesc($item_id)
            );
            if (!$x) {
                http_status_exit(404, 'Not found');
            }

            $group = array_shift($x);

            // process an authenticated fetch

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
            } elseif (Config::Get('system', 'require_authenticated_fetch')) {
                http_status_exit(403, 'Permission denied');
            }

            $observer_hash = get_observer_hash();
            $hasPermission = perm_is_allowed($group['uid'], $observer_hash, 'view_contacts');

            $channel = Channel::from_id($group['uid']);

            if (!$channel) {
                http_status_exit(404, 'Not found');
            }

            $sqlExtra = '';
            if (!$group['visible'] || !$hasPermission) {
                if ($observer_hash) {
                    if ($observer_hash !== $channel['channel_hash']) {
                        $sqlExtra = " AND xchan_hash = '" . dbesc(get_observer_hash()) . "' ";
                    }
                }
                else {
                    http_status_exit(403, 'Permission denied');
                }
            }


            $total = AccessList::members($group['uid'], $group['id'], true, sqlExtra: $sqlExtra);
            if ($total) {
                App::set_pager_total($total);
                App::set_pager_itemspage(100);
            }

            if (App::$pager['unset'] && $total > 100) {
                $ret = Activity::paged_collection_init($total, App::$query_string);
            } else {
                $members = AccessList::members($group['uid'], $group['id'], false, App::$pager['start'],
                    App::$pager['itemspage'], sqlExtra: $sqlExtra);
                $ret = Activity::encode_follow_collection($members, App::$query_string, 'OrderedCollection', $total);
            }
            if (! $sqlExtra) {
                $ret['name'] = $group['gname'];
            }
            $ret['attributedTo'] = Channel::url($channel);

            as_return_and_die($ret, $channel);
        }

        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        App::$profile_uid = local_channel();
        Navbar::set_selected('Access Lists');
    }

    public function post()
    {

        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        if ((argc() == 2) && (argv(1) === 'new')) {
            check_form_security_token_redirectOnErr('/lists/new', 'group_edit');

            $name = notags(trim($_POST['groupname']));
            $public = intval($_POST['public']);
            $r = AccessList::add(local_channel(), $name, $public);
            if ($r) {
                info(t('Access list created.') . EOL);
            } else {
                notice(t('Could not create access list.') . EOL);
            }
            goaway(z_root() . '/lists');
        }
        if ((argc() == 2) && (intval(argv(1)))) {
            check_form_security_token_redirectOnErr('/lists', 'group_edit');

            $r = q(
                "SELECT * FROM pgrp WHERE id = %d AND uid = %d LIMIT 1",
                intval(argv(1)),
                intval(local_channel())
            );
            if (!$r) {
                $r = q(
                    "select * from pgrp where id = %d limit 1",
                    intval(argv(1))
                );
                if ($r) {
                    notice(t('Permission denied.') . EOL);
                } else {
                    notice(t('Access list not found.') . EOL);
                }
                goaway(z_root() . '/connections');
            }
            $group = array_shift($r);
            $groupname = notags(trim($_POST['groupname']));
            $public = intval($_POST['public']);

            if ((strlen($groupname)) && (($groupname != $group['gname']) || ($public != $group['visible']))) {
                $r = q(
                    "UPDATE pgrp SET gname = '%s', visible = %d  WHERE uid = %d AND id = %d",
                    dbesc($groupname),
                    intval($public),
                    intval(local_channel()),
                    intval($group['id'])
                );
                if ($r) {
                    info(t('Access list updated.') . EOL);
                }
                Libsync::build_sync_packet(local_channel(), null, true);
            }

            goaway(z_root() . '/lists/' . argv(1) . '/' . argv(2));
        }
    }

    public function get()
    {

        $change = false;

        // logger('mod_lists: ' . App::$cmd, LOGGER_DEBUG);

        // Switch to text mode interface if we have more than 'n' contacts or group members, else loading avatars will lead to poor interactivity

        $switchtotext = get_pconfig(local_channel(), 'system', 'listedit_image_limit', get_config('system', 'listedit_image_limit', 1000));

        if ((argc() == 1) || ((argc() == 2) && (argv(1) === 'new'))) {
	        if (!local_channel()) {
    	        notice(t('Permission denied') . EOL);
        	    return '';
        	}

            $new = (argc() == 2) && (argv(1) === 'new');

            $groups = q(
                "SELECT id, gname FROM pgrp WHERE deleted = 0 AND uid = %d ORDER BY gname ASC",
                intval(local_channel())
            );

            $i = 0;
            foreach ($groups as $group) {
                $entries[$i]['name'] = $group['gname'];
                $entries[$i]['id'] = $group['id'];
                $entries[$i]['count'] = count(AccessList::members(local_channel(), $group['id']));
                $i++;
            }

            $tpl = Theme::get_template('privacy_groups.tpl');
            return replace_macros($tpl, [
                '$title' => t('Access Lists'),
                '$add_new_label' => t('Create access list'),
                '$new' => $new,

                // new group form
                '$gname' => ['groupname', t('Access list name')],
                '$public' => ['public', t('Members are visible to other channels'), false],
                '$form_security_token' => get_form_security_token("group_edit"),
                '$submit' => t('Submit'),

                // groups list
                '$name_label' => t('Name'),
                '$count_label' => t('Members'),
                '$entries' => $entries
            ]);
        }

        $context = ['$submit' => t('Submit')];
        $tpl = Theme::get_template('group_edit.tpl');

        if ((argc() == 3) && (argv(1) === 'drop')) {
	        if (!local_channel()) {
    	        notice(t('Permission denied') . EOL);
        	    return '';
        	}


			check_form_security_token_redirectOnErr('/lists', 'group_drop', 't');

            if (intval(argv(2))) {
                $r = q(
                    "SELECT gname FROM pgrp WHERE id = %d AND uid = %d LIMIT 1",
                    intval(argv(2)),
                    intval(local_channel())
                );
                if ($r) {
                    $result = AccessList::remove(local_channel(), $r[0]['gname']);
                }
                if ($result) {
                    info(t('Access list removed.') . EOL);
                } else {
                    notice(t('Unable to remove access list.') . EOL);
                }
            }
            goaway(z_root() . '/lists');
            // NOTREACHED
        }


        if ((argc() > 2) && intval(argv(1)) && argv(2)) {
		    if (!local_channel()) {
            	notice(t('Permission denied') . EOL);
            	return '';
        	}

            check_form_security_token_ForbiddenOnErr('group_member_change', 't');

            $r = q(
                "SELECT abook_xchan from abook left join xchan on abook_xchan = xchan_hash where abook_xchan = '%s' and abook_channel = %d and xchan_deleted = 0 and abook_self = 0 and abook_blocked = 0 and abook_pending = 0 limit 1",
                dbesc(base64url_decode(argv(2))),
                intval(local_channel())
            );
            if (count($r)) {
                $change = base64url_decode(argv(2));
            }
        }

        if (argc() > 1) {

            if (strlen(argv(1)) <= 11 && intval(argv(1))) {
                $r = q(
                    "SELECT * FROM pgrp WHERE id = %d AND deleted = 0 LIMIT 1",
                    intval(argv(1))
                );
            } else {
                $r = q(
                    "SELECT * FROM pgrp WHERE hash = '%s' AND deleted = 0 LIMIT 1",
                    dbesc(argv(1))
                );
            }

			if (! $r) {
                notice(t('Access list not found.') . EOL);
				return '';
			}

			$group = array_shift($r);
            $uid = $group['uid'];
			$owner = (local_channel() && intval(local_channel()) === intval($group['uid']));

			if (!$owner) {
				// public view of group members if permitted
				if (!($group['visible'] && perm_is_allowed($uid, get_observer_hash(), 'view_contacts'))) {
                    notice(t('Permission denied') . EOL);
                    return '';
                }
                $members = [];
                $memberlist = AccessList::members($uid, $group['id']);

                if ($memberlist) {
                    foreach ($memberlist as $member) {
                        $members[] = micropro($member, true, 'mpgroup', 'card');
                    }
                }
                return replace_macros(Theme::get_template('listmembers.tpl'), [
                    '$title' => t('List members'),
                    '$members' => $members
                ]);
			}

            $members = AccessList::members(local_channel(), $group['id']);

            $preselected = [];
            if (count($members)) {
                foreach ($members as $member) {
                    if (!in_array($member['xchan_hash'], $preselected)) {
                        $preselected[] = $member['xchan_hash'];
                    }
                }
            }

            if ($change) {
                if (in_array($change, $preselected)) {
                    AccessList::member_remove(local_channel(), $group['gname'], $change);
                } else {
                    AccessList::member_add(local_channel(), $group['gname'], $change);
                }

                $members = AccessList::members(local_channel(), $group['id']);

                $preselected = [];
                if (count($members)) {
                    foreach ($members as $member) {
                        $preselected[] = $member['xchan_hash'];
                    }
                }
            }

            $context = $context + [
                    '$title' => sprintf(t('Access List: %s'), $group['gname']),
                    '$details_label' => t('Edit'),
                    '$gname' => ['groupname', t('Access list name: '), $group['gname'], ''],
                    '$gid' => $group['id'],
                    '$public' => ['public', t('Members are visible to other channels'), $group['visible'], ''],
                    '$form_security_token_edit' => get_form_security_token('group_edit'),
                    '$delete' => t('Delete access list'),
                    '$form_security_token_drop' => get_form_security_token("group_drop"),
                ];
        }

        if (!isset($group)) {
            return '';
        }

        $groupeditor = [
            'label_members' => t('List members'),
            'members' => [],
            'label_contacts' => t('Not in this list'),
            'contacts' => [],
        ];

        $sec_token = addslashes(get_form_security_token('group_member_change'));
        $textmode = (($switchtotext && (count($members) > $switchtotext)) ? true : 'card');
        foreach ($members as $member) {
            if ($member['xchan_url']) {
                $member['archived'] = (bool)$member['abook_archived'];
                $member['click'] = 'groupChangeMember(' . $group['id'] . ',\'' . base64url_encode($member['xchan_hash']) . '\',\'' . $sec_token . '\'); return false;';
                $groupeditor['members'][] = micropro($member, true, 'mpgroup', $textmode);
            } else {
                AccessList::member_remove(local_channel(), $group['gname'], $member['xchan_hash']);
            }
        }

        $r = q(
            "SELECT abook.*, xchan.* FROM abook left join xchan on abook_xchan = xchan_hash WHERE abook_channel = %d AND abook_self = 0 and abook_blocked = 0 and abook_pending = 0 and xchan_deleted = 0 order by xchan_name asc",
            intval(local_channel())
        );

        if (count($r)) {
            $textmode = (($switchtotext && (count($r) > $switchtotext)) ? true : 'card');
            foreach ($r as $member) {
                if (!in_array($member['xchan_hash'], $preselected)) {
                    $member['archived'] = (bool)$member['abook_archived'];
                    $member['click'] = 'groupChangeMember(' . $group['id'] . ',\'' . base64url_encode($member['xchan_hash']) . '\',\'' . $sec_token . '\'); return false;';
                    $groupeditor['contacts'][] = micropro($member, true, 'mpall', $textmode);
                }
            }
        }

        $context['$groupeditor'] = $groupeditor;
        $context['$desc'] = t('Select a channel to toggle membership');

        if ($change) {
            $tpl = Theme::get_template('groupeditor.tpl');
            echo replace_macros($tpl, $context);
            killme();
        }

        return replace_macros($tpl, $context);
    }
}
