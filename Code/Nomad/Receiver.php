<?php

namespace Code\Nomad;

use Code\Lib\Config;
use Code\Lib\Libzot;
use Code\Lib\Crypto;
use Code\Web\HTTPSig;

class Receiver
{

    protected $data;
    protected $encrypted;
    protected $error;
    protected $messagetype;
    protected $sender;
    protected $site_id;
    protected $validated;
    protected $recipients;
    protected $response;
    protected $handler;
    protected $prvkey;
    protected $rawdata;
    protected $sigdata;

    public function __construct($handler, $localdata = null)
    {

        $this->error = false;
        $this->validated = false;
        $this->messagetype = '';
        $this->response = ['success' => false];
        $this->handler = $handler;
        $this->data = null;
        $this->rawdata = null;
        $this->site_id = null;
        $this->prvkey = Config::get('system', 'prvkey');

        if ($localdata) {
            $this->rawdata = $localdata;
        } else {
            $this->rawdata = file_get_contents('php://input');

            // All access to the zot endpoint must use http signatures

            if (!$this->Valid_Httpsig()) {
                logger('signature failed');
                $this->error = true;
                $this->response['message'] = 'signature invalid';
                return;
            }
        }

        logger('received raw: ' . print_r($this->rawdata, true), LOGGER_DATA);


        if ($this->rawdata) {
            $this->data = json_decode($this->rawdata, true);
            if (($this->data) && (!is_array($this->data)) && (substr($this->data, 0, 1) === "{")) {
                // Multiple json encoding has been seen in the wild and needs to be fixed on the sending side.
                // Proceed anyway and log the event with a backtrace.

                btlogger('multiple encoding detected');
                $this->data = json_decode($this->data, true);
            }
        } else {
            $this->error = true;
            $this->response['message'] = 'no data';
        }

        logger('received_json: ' . json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOGGER_DATA);

        logger('received: ' . print_r($this->data, true), LOGGER_DATA);

        if ($this->data && is_array($this->data)) {
            $this->encrypted = ((array_key_exists('encrypted', $this->data) && intval($this->data['encrypted'])) ? true : false);

            if ($this->encrypted && $this->prvkey) {
                $uncrypted = Crypto::unencapsulate($this->data, $this->prvkey);
                if ($uncrypted) {
                    $this->data = json_decode($uncrypted, true);
                } else {
                    $this->error = true;
                    $this->response['message'] = 'no data';
                }
            }
        }
    }


    public function run()
    {

        if ($this->error) {
            // make timing attacks on the decryption engine a bit more difficult
            usleep(mt_rand(10000, 100000));
            return ($this->response);
        }

        if ($this->data) {
            if (array_key_exists('type', $this->data)) {
                $this->messagetype = $this->data['type'];
            }

            if (!$this->messagetype) {
                $this->error = true;
                $this->response['message'] = 'no datatype';
                return $this->response;
            }

            $this->sender = ((array_key_exists('sender', $this->data)) ? $this->data['sender'] : null);
            $this->recipients = ((array_key_exists('recipients', $this->data)) ? $this->data['recipients'] : null);
            $this->site_id = ((array_key_exists('site_id', $this->data)) ? $this->data['site_id'] : null);
        }

        if ($this->sender) {
            $result = $this->ValidateSender();
            if (!$result) {
                $this->error = true;
                return $this->response;
            }
        }

        return $this->Dispatch();
    }

    public function ValidateSender()
    {

        $hub = Libzot::valid_hub($this->sender, $this->site_id);

        if (!$hub) {
            $x = Libzot::register_hub($this->sigdata['signer']);
            if ($x['success']) {
                $hub = Libzot::valid_hub($this->sender, $this->site_id);
            }
            if (!$hub) {
                $this->response['message'] = 'sender unknown';
                return false;
            }
        }

        if (!check_siteallowed($hub['hubloc_url'])) {
            $this->response['message'] = 'forbidden';
            return false;
        }

        if (!check_channelallowed($this->sender)) {
            $this->response['message'] = 'forbidden';
            return false;
        }

        Libzot::update_hub_connected($hub, $this->site_id);

        $this->validated = true;
        $this->hub = $hub;
        return true;
    }


    public function Valid_Httpsig()
    {

        $result = false;

        $this->sigdata = HTTPSig::verify($this->rawdata, EMPTY_STR, 'zot6');

        if ($this->sigdata && $this->sigdata['header_signed'] && $this->sigdata['header_valid']) {
            $result = true;

            // It is OK to not have signed content - not all messages provide content.
            // But if it is signed, it has to be valid

            if (($this->sigdata['content_signed']) && (!$this->sigdata['content_valid'])) {
                $result = false;
            }
        }
        return $result;
    }

    public function Dispatch()
    {

        switch ($this->messagetype) {
            case 'purge':
                $this->response = $this->handler->Purge($this->sender, $this->recipients, $this->hub);
                break;

            case 'refresh':
                $this->response = $this->handler->Refresh($this->sender, $this->recipients, $this->hub, false);
                break;

            case 'force_refresh':
                $this->response = $this->handler->Refresh($this->sender, $this->recipients, $this->hub, true);
                break;

            case 'rekey':
                $this->response = $this->handler->Rekey($this->sender, $this->data, $this->hub);
                break;

            case 'activity':
            case 'response': // upstream message
            case 'sync':
            default:
                // Only accept these message types with a valid sender
                if ($this->sender) {
                    $this->response = $this->handler->Notify($this->data, $this->hub);
                }
                break;
        }

        logger('response_to_return: ' . print_r($this->response, true), LOGGER_DATA);

        if ($this->encrypted) {
            $this->EncryptResponse();
        }

        return ($this->response);
    }

    public function EncryptResponse()
    {
        $algorithm = Libzot::best_algorithm($this->hub['site_crypto']);
        if ($algorithm) {
            $this->response = Crypto::encapsulate(json_encode($this->response), $this->hub['hubloc_sitekey'], $algorithm);
        }
    }
}
