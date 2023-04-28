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

    }

    public function get()
    {
      if (!local_channel()) {
          return login();
      }
      $identities = $this->getIdentities();
      $form = $this->getForm();

        return replace_macros(Theme::get_template('identity_settings.tpl'), [
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
