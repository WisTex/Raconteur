<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libprofile;
use Zotlabs\Render\Theme;


class Viewconnections extends Controller
{

    public function init()
    {

        if (observer_prohibited()) {
            return;
        }

        if (argc() > 1) {
            Libprofile::load(argv(1));
        }
    }

    public function get()
    {

        // logger('request: ' . print_r($_REQUEST,true));

        if (observer_prohibited()) {
            notice(t('Public access denied.') . EOL);
            return;
        }

        if (((!(is_array(App::$profile) && count(App::$profile))) || (App::$profile['hide_friends']))) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        if (!perm_is_allowed(App::$profile['uid'], get_observer_hash(), 'view_contacts')) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        if (!$_REQUEST['aj']) {
            $_SESSION['return_url'] = App::$query_string;
        }

        $is_owner = ((local_channel() && local_channel() == App::$profile['uid']) ? true : false);

        $abook_flags = " and abook_pending = 0 and abook_self = 0 ";
        $sql_extra = '';

        if (!$is_owner) {
            $abook_flags .= " and abook_hidden = 0 ";
            $sql_extra = " and xchan_hidden = 0 ";
        }

        $r = q(
            "SELECT * FROM abook left join xchan on abook_xchan = xchan_hash where abook_channel = %d $abook_flags and xchan_orphan = 0 and xchan_deleted = 0 $sql_extra order by xchan_name LIMIT %d OFFSET %d ",
            intval(App::$profile['uid']),
            intval(App::$pager['itemspage']),
            intval(App::$pager['start'])
        );

        if ((!$r) && (!$_REQUEST['aj'])) {
            info(t('No connections.') . EOL);
            return $o;
        }

        $contacts = [];

        foreach ($r as $rr) {
            $oneway = false;
            if (!their_perms_contains(App::$profile['uid'], $rr['xchan_hash'], 'post_comments')) {
                $oneway = true;
            }

            $url = chanlink_hash($rr['xchan_hash']);
            if ($url) {
                $contacts[] = [
                    'id' => $rr['abook_id'],
                    'archived' => (intval($rr['abook_archived']) ? true : false),
                    'img_hover' => sprintf(t('Visit %1$s\'s profile [%2$s]'), $rr['xchan_name'], $rr['xchan_url']),
                    'thumb' => $rr['xchan_photo_m'],
                    'name' => substr($rr['xchan_name'], 0, 20),
                    'username' => $rr['xchan_addr'],
                    'link' => $url,
                    'sparkle' => '',
                    'itemurl' => $rr['url'],
                    'network' => '',
                    'oneway' => $oneway
                ];
            }
        }


        if ($_REQUEST['aj']) {
            if ($contacts) {
                $o = replace_macros(Theme::get_template('viewcontactsajax.tpl'), [
                    '$contacts' => $contacts
                ]);
            } else {
                $o = '<div id="content-complete"></div>';
            }
            echo $o;
            killme();
        } else {
            $o .= "<script> var page_query = '" . escape_tags($_GET['req']) . "'; var extra_args = '" . extra_query_args() . "' ; </script>";
            $o .= replace_macros(Theme::get_template('viewcontact_template.tpl'), [
                '$title' => t('View Connections'),
                '$contacts' => $contacts,
            ]);
        }

        if (!$contacts) {
            $o .= '<div id="content-complete"></div>';
        }
        return $o;
    }
}
