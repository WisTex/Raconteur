<?php

namespace Code\Module\Settings;


use App;
use Code\Lib\PConfig;
use Code\Lib\Relme;
use Code\Render\Theme;
use Code\Web\Controller;



class Identities extends Controller
{
    public function init()
    {
        if (!local_channel()) {
            http_status_exit(403, 'Permission denied.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (isset($_REQUEST['id']) ? intval($_REQUEST['id']) : null);
        }
        else {
            $id = (argc() > 2) ? intval(argv(2)) : null;

        }


        $identities = $this->getIdentities();
        $delete = ($_REQUEST['drop'] ? boolval($_REQUEST['drop']) : false);
        $edit = ($_REQUEST['edit'] ? boolval($_REQUEST['edit']) : false);
        $description = (($_REQUEST['description']) ? escape_tags(trim($_REQUEST['description'])) : '' );
        $url = (($_REQUEST['url']) ? escape_tags(trim($_REQUEST['url'])) : '' );

        if (!$edit && !$delete && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        if ($delete && isset($id)) {
            unset($identities[$id]);
        }
        else {
            if (isset($id) && $description && $url) {
                $identities[$id] = [$description, $url];
            } elseif ($description && $url) {
                $identities[] = [$description, $url];
            }
        }
        if ($identities) {
            PConfig::Set(local_channel(), 'system', 'identities', array_values($identities));
        }
        else {
            PConfig::Delete(local_channel(), 'system','identities');
        }
        if ($delete) {
            goaway(z_root() . '/settings/identities');
        }
        else
        {
            $this->check_identity($url);
        }
    }

    public function get()
    {
        if (!local_channel()) {
          return login();
        }
        logger('args: ' . print_r(App::$argv,true));
        $channel = App::get_channel();

        $identities = $this->getIdentities();
        $id = (argc() > 2) ? intval(argv(2)) : null;
        if (isset($id)) {
            $record = $identities[$id];
        }

        $verified = $this->loadIdentities($channel['xchan_hash']);
        for ($x = 0; $x < count($identities); $x ++) {
            $identities[$x][2] = $this->matchRecord($identities[$x][1], $verified);
        }

        return replace_macros(Theme::get_template('identity_settings.tpl'), [
            '$title' => t('Manage Identities'),
            '$identities' => $identities,
            '$description' => [ 'description', t('Site name'), ($record ? $record[0] : ''), ''],
            '$help_text' => t('Identities are verified by providing a link on the URL you provide here which links back to your channel home page with a link relation of rel="me"'),
            '$url' => ['url', t('Site address/URL'), ($record ? $record[1] : ''), ''],
            '$edit' => t('Edit'),
            '$drop' => t('Remove'),
            '$id' => $id,
            '$submit' => t('Submit/Verify')
        ]);


    }

    protected function getForm()
    {
        return replace_macros(Theme::get_template('identity_form.tpl'), [
        ]);
    }

    protected function getIdentities()
    {
        return PConfig::Get(local_channel(),'system','identities', []);
    }

    protected function add_identity($name, $link) {
        $identities = $this->getIdentities();
        $identities[] = [$name, $link];
        PConfig::Set(local_channel(),'system','identities', $identities);
    }

    protected function check_identity($url)
    {
        $channel = App::get_channel();
        $myUrl = z_root() . '/channel/' . $channel['channel_address'];
        $myIdentity = $channel['channel_hash'];
        $links = $this->loadIdentities($myIdentity);

        $currentRecord = $this->matchRecord($url, $links);
        $validator = new Relme();
        $isMe = $validator->RelmeValidate($url, $myUrl);
        if ($isMe) {
            if ($currentRecord) {
                q("update linkid set sigtype = %d where link_id = %d",
                    intval(IDLINK_RELME),
                    intval($currentRecord['link_id'])
                );
            }
            else {
                q("insert into linkid (ident, link, ikey, lkey, isig, lsig, sigtype) values ( '%s', '%s', '', '', '', '', %d) ",
                    dbesc($myIdentity),
                    dbesc($url),
                    intval(IDLINK_RELME)
                );
            }
            $links = $this->loadIdentities($myIdentity);
        }
        else {
            q("delete from linkid where ident = '%s' and link = '%s'",
                dbesc($myIdentity),
                dbesc($url)
            );
        }
        return $isMe;
    }

    protected function loadIdentities($myIdentity)
    {
        return q("select * from linkid where ident = '%s' and sigtype in (%d, %d)",
            dbesc($myIdentity),
            intval(IDLINK_RELME),
            intval(IDLINK_NONE)
        );
    }

    protected function matchLinks($link, $identities)
    {
        if ($identities) {
            foreach ($identities as $identity) {
                if ($identity[1] === $link) {
                    return true;
                }
            }
        }
        return false;
    }
    protected function matchRecord($link, $identities)
    {
        if ($identities) {
            foreach ($identities as $identity) {
                if ($identity['link'] === $link) {
                    return $identity;
                }
            }
        }
        return null;
    }

}
