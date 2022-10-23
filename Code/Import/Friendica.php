<?php

namespace Code\Import;

use App;
use Code\Lib\Libzot;
use Code\Lib\PConfig;
use Code\Lib\Connect;
use Code\Lib\Channel;
use Code\Lib\ServiceClass;
use Code\Lib\AccessList;
use Code\Lib\Url;
use Code\Access\PermissionLimits;
use Code\Access\PermissionRoles;
use Code\Access\Permissions;
use Code\Daemon\Run;
use Code\Extend\Hook;

class Friendica
{

    private $data;
    private $settings;
    private $contacts = null;


    public function __construct($data, $settings)
    {
        $this->data = $data;
        $this->settings = $settings;
        $this->extract();
    }

    public function extract()
    {

        // channel stuff

        $channel = [
            'channel_name' => escape_tags($this->data['user']['username']),
            'channel_address' => escape_tags($this->data['user']['nickname']),
            'channel_guid' => escape_tags($this->data['user']['guid']),
            'channel_guid_sig' => Libzot::sign($this->data['user']['guid'], $this->data['user']['prvkey']),
            'channel_hash' => Libzot::make_xchan_hash($this->data['user']['guid'], $this->data['user']['pubkey']),
            'channel_prvkey' => $this->data['user']['prvkey'],
            'channel_pubkey' => $this->data['user']['pubkey'],
            'channel_pageflags' => PAGE_NORMAL,
            'channel_expire_days' => intval($this->data['user']['expire']),
            'channel_timezone' => escape_tags($this->data['user']['timezone']),
            'channel_location' => escape_tags($this->data['user']['default-location'])
        ];

        $account_id = $this->settings['account_id'];

        $max_identities = ServiceClass::account_fetch($account_id, 'total_identities');

        if ($max_identities !== false) {
            $r = q(
                "select channel_id from channel where channel_account_id = %d and channel_removed = 0 ",
                intval($account_id)
            );
            if ($r && count($r) > $max_identities) {
                notice(sprintf(t('Your service plan only allows %d channels.'), $max_identities) . EOL);
                return;
            }
        }

        // save channel or die


        $channel = import_channel($channel, $this->settings['account_id'], $this->settings['seize'], $this->settings['newname']);
        if (!$channel) {
            logger('no channel');
            return;
        }


        // figure out channel permission roles

        $permissions_role = 'social';

        $pageflags = ((isset($this->data['user']['page-flags'])) ? intval($this->data['user']['page-flags']) : 0);

        if ($pageflags === 2) {
            $permissions_role = 'forum';
        }
        if ($pageflags === 5) {
            $permissions_role = 'forum_restricted';
        }

        if ($pageflags === 0 && isset($this->data['user']['allow_gid']) && $this->data['user']['allow_gid']) {
            $permissions_role = 'social_restricted';
        }

        // Friendica folks only have PERMS_AUTHED and "just me"

        $post_comments = (($pageflags === 1) ? 0 : PERMS_AUTHED);
        PermissionLimits::Set(local_channel(), 'post_comments', $post_comments);

        PConfig::Set($channel['channel_id'], 'system', 'permissions_role', $permissions_role);
        PConfig::Set($channel['channel_id'], 'system', 'use_browser_location', (string)intval($this->data['user']['allow_location']));

        // find the self contact

        $self_contact = null;

        if (isset($this->data['contact']) && is_array($this->data['contact'])) {
            foreach ($this->data['contact'] as $contact) {
                if (isset($contact['self']) && intval($contact['self'])) {
                    $self_contact = $contact;
                    break;
                }
            }
        }

        if (!is_array($self_contact)) {
            logger('self contact not found.');
            return;
        }

        // Create a verified hub location pointing to this site.

        $r = hubloc_store_lowlevel(
            [
                'hubloc_guid' => $channel['channel_guid'],
                'hubloc_guid_sig' => $channel['channel_guid_sig'],
                'hubloc_id_url' => Channel::url($channel),
                'hubloc_hash' => $channel['channel_hash'],
                'hubloc_addr' => Channel::get_webfinger($channel),
                'hubloc_primary' => 1,
                'hubloc_url' => z_root(),
                'hubloc_url_sig' => Libzot::sign(z_root(), $channel['channel_prvkey']),
                'hubloc_site_id' => Libzot::make_xchan_hash(z_root(), get_config('system', 'pubkey')),
                'hubloc_host' => App::get_hostname(),
                'hubloc_callback' => z_root() . '/nomad',
                'hubloc_sitekey' => get_config('system', 'pubkey'),
                'hubloc_network' => 'nomad',
                'hubloc_updated' => datetime_convert()
            ]
        );
        if (!$r) {
            logger('Unable to store hub location');
        }


        if ($self_contact['avatar']) {
            $p = Url::get($self_contact['avatar']);
            if ($p['success']) {
                $h = explode("\n", $p['header']);
                foreach ($h as $l) {
                    list($k, $v) = array_map("trim", explode(":", trim($l), 2));
                    $hdrs[strtolower($k)] = $v;
                }
                if (array_key_exists('content-type', $hdrs)) {
                    $phototype = $hdrs['content-type'];
                } else {
                    $phototype = 'image/jpeg';
                }

                import_channel_photo($p['body'], $phototype, $account_id, $channel['channel_id']);
            }
        }

        $newuid = $channel['channel_id'];

        xchan_store_lowlevel([
            'xchan_hash' => $channel['channel_hash'],
            'xchan_guid' => $channel['channel_guid'],
            'xchan_guid_sig' => $channel['channel_guid_sig'],
            'xchan_pubkey' => $channel['channel_pubkey'],
            'xchan_photo_mimetype' => (($phototype) ?: 'image/png'),
            'xchan_photo_l' => z_root() . "/photo/profile/l/$newuid",
            'xchan_photo_m' => z_root() . "/photo/profile/m/$newuid",
            'xchan_photo_s' => z_root() . "/photo/profile/s/$newuid",
            'xchan_addr' => Channel::get_webfinger($channel),
            'xchan_url' => Channel::url($channel),
            'xchan_follow' => z_root() . '/follow?f=&url=%s',
            'xchan_connurl' => z_root() . '/poco/' . $channel['channel_address'],
            'xchan_name' => $channel['channel_name'],
            'xchan_network' => 'nomad',
            'xchan_updated' => datetime_convert(),
            'xchan_photo_date' => datetime_convert(),
            'xchan_name_date' => datetime_convert(),
            'xchan_system' => 0
        ]);

        Channel::profile_store_lowlevel([
            'aid' => intval($channel['channel_account_id']),
            'uid' => intval($newuid),
            'profile_guid' => new_uuid(),
            'profile_name' => t('Default Profile'),
            'is_default' => 1,
            'publish' => ((isset($this->data['profile']['publish'])) ? $this->data['profile']['publish'] : 1),
            'fullname' => $channel['channel_name'],
            'photo' => z_root() . "/photo/profile/l/$newuid",
            'thumb' => z_root() . "/photo/profile/m/$newuid",
            'homepage' => ((isset($this->data['profile']['homepage'])) ? $this->data['profile']['homepage'] : EMPTY_STR),
        ]);

        if ($role_permissions) {
            $myperms = ((array_key_exists('perms_connect', $role_permissions)) ? $role_permissions['perms_connect'] : []);
        } else {
            $x = PermissionRoles::role_perms('social');
            $myperms = $x['perms_connect'];
        }

        abook_store_lowlevel([
            'abook_account' => intval($channel['channel_account_id']),
            'abook_channel' => intval($newuid),
            'abook_xchan' => $channel['channel_hash'],
            'abook_closeness' => 0,
            'abook_created' => datetime_convert(),
            'abook_updated' => datetime_convert(),
            'abook_self' => 1
        ]);


        $x = Permissions::serialise(Permissions::FilledPerms($myperms));
        set_abconfig($newuid, $channel['channel_hash'], 'system', 'my_perms', $x);

        if (intval($channel['channel_account_id'])) {
            // Save our permissions role so we can perhaps call it up and modify it later.

            if ($role_permissions) {
                if (array_key_exists('online', $role_permissions)) {
                    set_pconfig($newuid, 'system', 'hide_presence', 1 - intval($role_permissions['online']));
                }
                if (array_key_exists('perms_auto', $role_permissions)) {
                    $autoperms = intval($role_permissions['perms_auto']);
                    set_pconfig($newuid, 'system', 'autoperms', $autoperms);
                }
            }

            // Create a group with yourself as a member. This allows somebody to use it
            // right away as a default group for new contacts.

            AccessList::add($newuid, t('Friends'));
            AccessList::member_add($newuid, t('Friends'), $channel['channel_hash']);

            // if our role_permissions indicate that we're using a default collection ACL, add it.

            if (is_array($role_permissions) && $role_permissions['default_collection']) {
                $r = q(
                    "select hash from pgrp where uid = %d and gname = '%s' limit 1",
                    intval($newuid),
                    dbesc(t('Friends'))
                );
                if ($r) {
                    q(
                        "update channel set channel_default_group = '%s', channel_allow_gid = '%s' where channel_id = %d",
                        dbesc($r[0]['hash']),
                        dbesc('<' . $r[0]['hash'] . '>'),
                        intval($newuid)
                    );
                }
            }

            set_pconfig($channel['channel_id'], 'system', 'photo_path', '%Y/%Y-%m');
            set_pconfig($channel['channel_id'], 'system', 'attach_path', '%Y/%Y-%m');


            // auto-follow any of the hub's pre-configured channel choices.
            // Only do this if it's the first channel for this account;
            // otherwise it could get annoying. Don't make this list too big,
            // or it will impact registration time.

            $accts = get_config('system', 'auto_follow');
            if (($accts) && (!$total_identities)) {
                if (!is_array($accts)) {
                    $accts = [$accts];
                }

                foreach ($accts as $acct) {
                    if (trim($acct)) {
                        $f = Channel::connect_and_sync($channel, trim($acct));
                        if ($f['success']) {
                            $can_view_stream = their_perms_contains($channel['channel_id'], $f['abook']['abook_xchan'], 'view_stream');

                            // If we can view their stream, pull in some posts

                            if (($can_view_stream) || ($f['abook']['xchan_network'] === 'rss')) {
                                Run::Summon(['Onepoll', $f['abook']['abook_id']]);
                            }
                        }
                    }
                }
            }

            Hook::call('create_identity', $newuid);
        }

        $groups = ((isset($this->data['group'])) ? $this->data['group'] : null);
        $members = ((isset($this->data['group_member'])) ? $this->data['group_member'] : null);

        // import contacts

        if (isset($this->data['contact']) && is_array($this->data['contact'])) {
            foreach ($this->data['contact'] as $contact) {
                if (isset($contact['self']) && intval($contact['self'])) {
                    continue;
                }
                logger('connecting: ' . $contact['url'], LOGGER_DEBUG);
                $result = Connect::connect($channel, (($contact['addr']) ?: $contact['url']));
                if ($result['success'] && isset($result['abook'])) {
                    $contact['xchan_hash'] = $result['abook']['abook_xchan'];
                    $this->contacts[] = $contact;
                }
            }
        }

        // import pconfig
        // it is unlikely we can make use of these unless we recongise them.

        if (isset($this->data['pconfig']) && is_array($this->data['pconfig'])) {
            foreach ($this->data['pconfig'] as $pc) {
                $entry = [
                    'cat' => escape_tags(str_replace('.', '__', $pc['cat'])),
                    'k' => escape_tags(str_replace('.', '__', $pc['k'])),
                    'v' => ((preg_match('|^a:[0-9]+:{.*}$|s', $pc['v'])) ? serialise(unserialize($pc['v'])) : $pc['v']),
                ];
                PConfig::Set($channel['channel_id'], $entry['cat'], $entry['k'], $entry['v']);
            }
        }

        // The default 'Friends' group is already created and possibly populated.
        // So some of the following code is redundant in that regard.
        // Mostly this is used to create and populate any other groups.

        if ($groups) {
            foreach ($groups as $group) {
                if (!intval($group['deleted'])) {
                    AccessList::add($channel['channel_id'], $group['name'], intval($group['visible']));
                    if ($members) {
                        foreach ($members as $member) {
                            if (intval($member['gid']) === intval(AccessList::byname($channel['channel_id'], $group['name']))) {
                                $contact_id = $member['contact-id'];
                                if ($this->contacts) {
                                    foreach ($this->contacts as $contact) {
                                        if (intval($contact['id']) === intval($contact_id)) {
                                            AccessList::member_add($channel['channel_id'], $group['name'], $contact['xchan_hash']);
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        change_channel($channel['channel_id']);
        notice(t('Import complete.') . EOL);

        goaway(z_root() . '/stream');
    }
}

