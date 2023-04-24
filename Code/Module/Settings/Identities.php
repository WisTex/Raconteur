<?php

namespace Code\Module\Settings;



use Code\Lib\PConfig;

class Identities extends \Code\Web\Controller
{
    public function post()
    {

    }

    public function get()
    {

    }

    protected function getIdentities()
    {
        $identities = PConfig::Get(local_channel(),'system','identities', []);
        return $identities;
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
            $isMe = RelmeValidate($identity[1], $myUrl);
            if ($isMe) {
                if (!$currentRecord) {
                    q("insert into linkid (ident, link, sigtype) values ( '%s', '%s' %d) ",
                        dbesc($myIdentity)
                    );
                }
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
        return q("select * from linkid where ident = '%s' and sigtype = %d",
            dbesc($myIdentity),
            intval(IDLINK_RELME)
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