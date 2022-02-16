<?php

namespace Code\Lib;

use App;
use Code\Lib\Apps;
use Code\Lib\PermissionDescription;
use Code\Render\Theme;


class Libacl
{
    public static function fixacl(&$item)
    {
        $item = str_replace([ '<', '>' ], [ '', '' ], $item);
    }

    /**
    * Builds a modal dialog for editing permissions, using acl_selector.tpl as the template.
    *
    * @param array   $defaults Optional access control list for the initial state of the dialog.
    * @param bool $show_jotnets Whether plugins for federated networks should be included in the permissions dialog
    * @param PermissionDescription $emptyACL_description - An optional description for the permission implied by selecting an empty ACL. Preferably an instance of PermissionDescription.
    * @param string  $dialog_description Optional message to include at the top of the dialog. E.g. "Warning: Post permissions cannot be changed once sent".
    * @param string  $context_help Allows the dialog to present a help icon. E.g. "acl_dialog_post"
    * @param bool $readonly Not implemented yet. When implemented, the dialog will use acl_readonly.tpl instead, so that permissions may be viewed for posts that can no longer have their permissions changed.
    *
    * @return string html modal dialog built from acl_selector.tpl
    */
    public static function populate($defaults = null, $show_jotnets = true, $emptyACL_description = '', $dialog_description = '', $context_help = '', $readonly = false)
    {

        $allow_cid = $allow_gid = $deny_cid = $deny_gid = false;
        $showall_origin = '';
        $showall_icon   = 'fa-globe';
        $role = get_pconfig(local_channel(), 'system', 'permissions_role');

        if (! $emptyACL_description) {
            $showall_caption = t('Visible to your default audience');
        } elseif (is_a($emptyACL_description, '\\Code\\Lib\\PermissionDescription')) {
            $showall_caption = $emptyACL_description->get_permission_description();
            $showall_origin  = (($role === 'custom') ? $emptyACL_description->get_permission_origin_description() : '');
            $showall_icon    = $emptyACL_description->get_permission_icon();
        } else {
            // For backwards compatibility we still accept a string... for now!
            $showall_caption = $emptyACL_description;
        }


        if (is_array($defaults)) {
            $allow_cid = ((strlen($defaults['allow_cid']))
                ? explode('><', $defaults['allow_cid']) : [] );
            $allow_gid = ((strlen($defaults['allow_gid']))
                ? explode('><', $defaults['allow_gid']) : [] );
            $deny_cid  = ((strlen($defaults['deny_cid']))
                ? explode('><', $defaults['deny_cid']) : [] );
            $deny_gid  = ((strlen($defaults['deny_gid']))
                ? explode('><', $defaults['deny_gid']) : [] );
            array_walk($allow_cid, ['\\Code\\Lib\\Libacl', 'fixacl']);
            array_walk($allow_gid, ['\\Code\\Lib\\Libacl', 'fixacl']);
            array_walk($deny_cid, ['\\Code\\Lib\\Libacl','fixacl']);
            array_walk($deny_gid, ['\\Code\\Lib\\Libacl','fixacl']);
        }

        $channel = ((local_channel()) ? App::get_channel() : '');
        $has_acl = false;
        $single_group = false;
        $just_me = false;
        $custom = false;

        if ($allow_cid || $allow_gid || $deny_gid || $deny_cid) {
            $has_acl = true;
            $custom = true;
        }

        if (count($allow_gid) === 1 && (! $allow_cid) && (! $deny_gid) && (! $deny_cid)) {
            $single_group = true;
            $custom = false;
        }

        if (count($allow_cid) === 1 && $channel && $allow_cid[0] === $channel['channel_hash'] && (! $allow_gid) && (! $deny_gid) && (! $deny_cid)) {
            $just_me = true;
            $custom = false;
        }

        $groups = EMPTY_STR;

        $r = q(
            "SELECT id, hash, gname FROM pgrp WHERE deleted = 0 AND uid = %d ORDER BY gname ASC",
            intval(local_channel())
        );

        if ($r) {
            foreach ($r as $rr) {
                $selected = (($single_group && $rr['hash'] === $allow_gid[0]) ? ' selected = "selected" ' : '');
                $groups .= '<option id="' . $rr['id'] . '" value="' . $rr['hash'] . '"' . $selected . '>' . $rr['gname'] . ' ' . t('(List)') . '</option>' . "\r\n";
            }
        }

        if ($channel && Apps::system_app_installed($channel['channel_id'], 'Virtual Lists')) {
            $selected = (($single_group && 'connections:' . $channel['channel_hash'] === $allow_gid[0]) ? ' selected = "selected" ' : '');
            $groups .= '<option id="vg1" value="connections:' . $channel['channel_hash'] . '"' . $selected . '>' . t('My connections') . ' ' . t('(Virtual List)') . '</option>' . "\r\n";
            if (get_pconfig($channel['channel_id'], 'system', 'activitypub', get_config('system', 'activitypub', ACTIVITYPUB_ENABLED))) {
                $selected = (($single_group && 'activitypub:' . $channel['channel_hash'] === $allow_gid[0]) ? ' selected = "selected" ' : '');
                $groups .= '<option id="vg2" value="activitypub:' . $channel['channel_hash'] . '"' . $selected . '>' . t('My ActivityPub connections') . ' ' . t('(Virtual List)') . '</option>' . "\r\n";
            }
            $selected = (($single_group && 'zot:' . $channel['channel_hash'] === $allow_gid[0]) ? ' selected = "selected" ' : '');
            $groups .= '<option id="vg3" value="zot:' . $channel['channel_hash'] . '"' . $selected . '>' . t('My Nomad connections') . ' ' . t('(Virtual List)') . '</option>' . "\r\n";
        }


        $forums = get_forum_channels(local_channel(), 1);
        $selected = false;
        if ($forums) {
            foreach ($forums as $f) {
                $selected = (($single_group && $f['hash'] === $allow_cid[0]) ? ' selected = "selected" ' : '');
                $groups .= '<option id="^' . $f['abook_id'] . '" value="^' . $f['xchan_hash'] . '"' . $selected . '>' . $f['xchan_name'] . ' ' . t('(Group)') . '</option>' . "\r\n";
            }
        }

        // preset acl with DM to a single xchan (not a group)
        if ($selected === false && count($allow_cid) === 1 && $channel && $allow_cid[0] !== $channel['channel_hash'] && (! $allow_gid) && (! $deny_gid) && (! $deny_cid)) {
            $f = q(
                "select * from xchan where xchan_hash = '%s'",
                dbesc($allow_cid[0])
            );
            if ($f) {
                $custom = false;
                $selected = ' selected="selected" ';
                $groups .= '<option id="^DM" value="^' . $f[0]['xchan_hash'] . '"' . $selected . '>' . $f[0]['xchan_name'] . ' ' . t('(DM)') . '</option>' . "\r\n";
            }
        }

        $tpl = Theme::get_template("acl_selector.tpl");
        $o = replace_macros($tpl, array(
            '$showall'         => $showall_caption,
            '$onlyme'          => t('Only me'),
            '$groups'          => $groups,
            '$public_selected' => (($has_acl) ? false : ' selected="selected" '),
            '$justme_selected' => (($just_me) ? ' selected="selected" ' : ''),
            '$custom_selected' => (($custom) ? ' selected="selected" ' : ''),
            '$showallOrigin'   => $showall_origin,
            '$showallIcon'     => $showall_icon,
            '$select_label'    => t('Who can see this?'),
            '$custom'          => t('Custom selection'),
            '$showlimitedDesc' => t('Select "Show" to allow viewing. "Don\'t show" lets you override and limit the scope of "Show".'),
            '$show'            => t('Show'),
            '$hide'            => t("Don't show"),
            '$search'          => t('Search'),
            '$allowcid'        => json_encode($allow_cid),
            '$allowgid'        => json_encode($allow_gid),
            '$denycid'         => json_encode($deny_cid),
            '$denygid'         => json_encode($deny_gid),
            '$aclModalTitle'   => t('Permissions'),
            '$aclModalDesc'    => $dialog_description,
            '$aclModalDismiss' => t('Close'),
    //      '$helpUrl'         => (($context_help == '') ? '' : (z_root() . '/help/' . $context_help))
        ));

        return $o;
    }

    /**
     * Returns a string that's suitable for passing as the $dialog_description argument to a
     * populate() call for wall posts or network posts.
     *
     * This string is needed in 3 different files, and our .po translation system currently
     * cannot be used as a string table (because the value is always the key in english) so
     * I've centralized the value here (making this function name the "key") until we have a
     * better way.
     *
     * @return string Description to present to user in modal permissions dialog
     */
    public static function get_post_aclDialogDescription()
    {

        // I'm trying to make two points in this description text - warn about finality of wall
        // post permissions, and try to clear up confusion that these permissions set who is
        // *shown* the post, istead of who is able to see the post, i.e. make it clear that clicking
        // the "Show"  button on a group does not post it to the feed of people in that group, it
        // mearly allows those people to view the post if they are viewing/following this channel.
        $description = t('Post permissions cannot be changed after a post is shared.<br>These permissions set who is allowed to view the post.');

        // Lets keep the emphasis styling seperate from the translation. It may change.
        //$emphasisOpen  = '<b><a href="' . z_root() . '/help/acl_dialog_post" target="hubzilla-help">';
        //$emphasisClose = '</a></b>';

        return $description;
    }

    
}
