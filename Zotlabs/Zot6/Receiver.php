<?php

namespace Zotlabs\Zot6;

use Zotlabs\Lib\Config;

class Receiver {

	protected $data;
	protected $encrypted;
	protected $error;
	protected $messagetype;
	protected $sender;
	protected $validated;
	protected $recipients;
	protected $response;
	protected $handler;
	protected $prvkey;

	function __construct($handler) {

		$this->error       = false;
		$this->validated   = false;
		$this->messagetype = '';
		$this->response    = [ 'success' => false ];
		$this->handler     = $handler;
		$this->data        = null;
		$this->prvkey      = Config::get('system','prvkey');

		$json = ltrim(file_get_contents('php://input'));

		if($json) {
			$this->data = json_decode(json,true);
		}
		else {
			$this->error = true;
			$this->response['message'] = 'no data';
		}

		if($this->data && is_array($this->data)) {
			$this->encrypted = ((array_key_exists('encrypted',$this->data)) ? true : false);

			if($this->encrypted && $this->prvkey) {
				$uncrypted = crypto_unencapsulate($data,$prvkey);
				if($uncrypted) {
					$this->data = json_decode($uncrypted,true);
				}
				else {
					$this->error = true;
					$this->response['message'] = 'no data';
				}
			}
		}
	}


	function run() {

		if($this->error) {
			// make timing attacks on the decryption engine a bit more difficult
			usleep(mt_rand(10000,100000));
			json_return_and_die($this->response);
		}

		if($this->data) {
			if(array_key_exists('type',$this->data))
				$this->messagetype = $this->data['type'];

			if(! $this->messagetype) {
				$this->error = true;
				$this->response['message'] = 'no datatype';
			}

			$this->sender     = ((array_key_exists('sender',$this->data)) ? $this->data['sender'] : null);
			$this->recipients = ((array_key_exists('recipients',$this->data)) ? $this->data['recipients'] : null);
		}

		if($this->sender)
			$this->ValidateSender();

		$this->Dispatch();
	}

	function ValidateSender() {

		$hubs = zot_gethub($this->sender,true);
		if (! $hubs) {

			/* Have never seen this guid or this guid coming from this location. Check it and register it. */
			/* (!!) this will validate the sender. */

        	$result = zot_register_hub($this->sender);

        	if ((! $result['success']) || (! ($hubs = zot_gethub($this->sender,true)))) {
            	$this->response['message'] = 'Hub not available.';
	            json_return_and_die($this->response);
    	    }
		}
		foreach($hubs as $hub) {
			update_hub_connected($hub,((array_key_exists('sitekey',$this->sender)) ? $this->sender['sitekey'] : ''));
		}
		$this->validated = true;
    }

		
	function Dispatch() {

		if(! $this->validated) {
			$this->response['message'] = 'Sender not valid';
			json_return_and_die($this->response);
		}

		/* Now handle tasks which require sender validation */

		switch($this->messagetype) {

			case 'request':
				$this->handler->Request($this->data);
				break;

			case 'purge':
				$this->handler->Purge($this->sender,$this->recipients);
				break;

			case 'refresh':
			case 'force_refresh':
				$this->handler->Refresh($this->sender,$this->recipients);
				break;

			case 'notify':
				$this->handler->Notify($this->data);
				break;

			case 'rekey':
				$this->handler->Rekey($this->sender, $this->data);
				break;

			default:
				$this->response['message'] = 'Not implemented';
				json_return_and_die($this->response);
				break;
		}

	}
}



