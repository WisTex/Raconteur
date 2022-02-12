<?php

namespace Zotlabs\Lib;

use Zotlabs\Extend\Hook;

class DReport
{

    private $location;
    private $sender;
    private $recipient;
    private $message_id;
    private $status;
    private $date;

    public function __construct($location, $sender, $recipient, $message_id, $status = 'deliver')
    {
        $this->location = $location;
        $this->sender = $sender;
        $this->recipient = $recipient;
        $this->name = EMPTY_STR;
        $this->message_id = $message_id;
        $this->status = $status;
        $this->date = datetime_convert();
    }

    public function update($status)
    {
        $this->status = $status;
        $this->date = datetime_convert();
    }

    public function set_name($name)
    {
        $this->name = $name;
    }

    public function addto_update($status)
    {
        $this->status = $this->status . ' ' . $status;
    }


    public function set($arr)
    {
        $this->location = $arr['location'];
        $this->sender = $arr['sender'];
        $this->recipient = $arr['recipient'];
        $this->name = $arr['name'];
        $this->message_id = $arr['message_id'];
        $this->status = $arr['status'];
        $this->date = $arr['date'];
    }

    public function get()
    {
        return array(
            'location' => $this->location,
            'sender' => $this->sender,
            'recipient' => $this->recipient,
            'name' => $this->name,
            'message_id' => $this->message_id,
            'status' => $this->status,
            'date' => $this->date
        );
    }

    /**
     * @brief decide whether to store a returned delivery report
     *
     * @param array $dr
     * @return bool
     */

    public static function is_storable($dr)
    {

        if (get_config('system', 'disable_dreport')) {
            return false;
        }

        /**
         * @hooks dreport_is_storable
         *   Called before storing a dreport record to determine whether to store it.
         *   * \e array
         */

        Hook::call('dreport_is_storable', $dr);

        // let plugins accept or reject - if neither, continue on
        if (array_key_exists('accept', $dr) && intval($dr['accept'])) {
            return true;
        }
        if (array_key_exists('reject', $dr) && intval($dr['reject'])) {
            return false;
        }

        if (!($dr['sender'])) {
            return false;
        }

        // Is the sender one of our channels?

        $c = q(
            "select channel_id from channel where channel_hash = '%s' limit 1",
            dbesc($dr['sender'])
        );
        if (!$c) {
            return false;
        }


        // is the recipient one of our connections, or do we want to store every report?


        $rxchan = $dr['recipient'];
        $pcf = get_pconfig($c[0]['channel_id'], 'system', 'dreport_store_all');
        if ($pcf) {
            return true;
        }

        // We always add ourself as a recipient to private and relayed posts
        // So if a remote site says they can't find us, that's no big surprise
        // and just creates a lot of extra report noise

        if (($dr['location'] !== z_root()) && ($dr['sender'] === $rxchan) && ($dr['status'] === 'recipient not found')) {
            return false;
        }

        // If you have a private post with a recipient list, every single site is going to report
        // back a failed delivery for anybody on that list that isn't local to them. We're only
        // concerned about this if we have a local hubloc record which says we expected them to
        // have a channel on that site.

        $r = q(
            "select hubloc_id from hubloc where hubloc_hash = '%s' and hubloc_url = '%s'",
            dbesc($rxchan),
            dbesc($dr['location'])
        );
        if ((!$r) && ($dr['status'] === 'recipient not found')) {
            return false;
        }

        $r = q(
            "select abook_id from abook where abook_xchan = '%s' and abook_channel = %d limit 1",
            dbesc($rxchan),
            intval($c[0]['channel_id'])
        );
        if ($r) {
            return true;
        }

        return false;
    }
}
