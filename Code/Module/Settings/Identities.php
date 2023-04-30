<?php

namespace Code\Module\Settings;


use App;
use Code\Lib\PConfig;
use Code\Lib\Relme;
use Code\Render\Theme;
use Code\Web\Controller;



class Identities extends Controller
{
    public function post()
    {
        if (!local_channel()) {
            http_status_exit(403, 'Permission denied.');
        }

        $identities = $this->getIdentities();
        $id = (($_REQUEST['id']) ? intval($_REQUEST['id']) - 1 : null);
        $description = (($_REQUEST['description']) ? escape_tags(trim($_REQUEST['description'])) : '' );
        $url = (($_REQUEST['url']) ? escape_tags(trim($_REQUEST['url'])) : '' );

        if (isset($id)) {
            $identities[$id] = [$description, $url];
        }
        else {
            $identities[] = [$description, $url];
        }
        PConfig::Set(local_channel(),'system','identities', $identities);
        $this->check_identities();

        // build_sync_packet
    }

    public function get()
    {
        if (!local_channel()) {
          return login();
        }
        $channel = App::get_channel();

        $identities = $this->getIdentities();
        $id = $_REQUEST['id'] ? intval($_REQUEST['id']) - 1 : null;
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
            '$url' => ['url', t('Site address/URL'), ($record ? $record[1] : ''), ''],
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

    protected function check_identities()
    {
        $channel = App::get_channel();
        $myUrl = z_root() . '/channel/' . $channel['channel_address'];
        $myIdentity = $channel['channel_hash'];
        $identities = $this->getIdentities();
        $links = $this->loadIdentities($myIdentity);

        foreach ($identities as $identity) {
            $currentRecord = $this->matchRecord($identity[1], $links);
            $validator = new Relme();
            $isMe = $validator->RelmeValidate($identity[1], $myUrl);
            if ($currentRecord) {
                q("update linkid set sigtype = %d where link_id = %d",
                    intval($isMe ? IDLINK_RELME : IDLINK_NONE),
                    intval($currentRecord['link_id'])
                );
            }
            else {
                q("insert into linkid (ident, link, sigtype) values ( '%s', '%s', %d) ",
                    dbesc($myIdentity),
                    dbesc($identity[1]),
                    intval($isMe ? IDLINK_RELME : IDLINK_NONE)
                );
            }
        }

        foreach ($links as $link) {
            if (! $this->matchLinks($link['link'], $identities)) {
                q("delete from linkid where link_id = %d",
                    intval($link['link_id'])
                );
            }
        }
        q("delete from linkid where sigtype = %d",
            intval(IDLINK_NONE)
        );
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
