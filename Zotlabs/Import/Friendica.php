<?php
namespace Zotlabs\Import;

use App;
use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\PConfig;
use Zotlabs\Access\PermissionLimits;


class Friendica {

	private $data;
	private $settings;

	private $default_group = null;

	private $groups = null;
	private $members = null;


	function __construct($data, $settings) {
		$this->data = $data;
		$this->settings = $settings;
		$this->extract();
	}

	function extract() {

		// channel stuff

		$channel = [
			'channel_name' => escape_tags($this->data['user']['username']),
			'channel_parent' => 0,
			'channel_address' => escape_tags($this->data['user']['nickname']),
			'channel_guid' => escape_tags($this->data['user']['guid']),
			'channel_guid_sig' => Libzot::sign($this->data['user']['guid'],$this->data['user']['prvkey']),
			'channel_hash' => Libzot::make_xchan_hash($this->data['user']['guid'],$this->data['user']['pubkey']),
			'channel_prvkey' => $this->data['user']['prvkey'],
			'channel_pubkey' => $this->data['user']['pubkey'],
			'channel_pageflags' => PAGE_NORMAL,
			'channel_expire_days' => intval($this->data['user']['expire']),
			'channel_timezone' => escape_tags($this->data['user']['timezone']),
			'channel_location' => escape_tags($this->data['user']['default-location'])
		];

		// save channel or die

		$channel = import_channel($channel,$this->settings['account_id'],$this->settings['sieze'],$this->settings['newname']);
		if (! $channel) {
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
		PermissionLimits::Set(local_channel(),'post_comments',$post_comments);
			
		PConfig::Set($channel['channel_id'],'system','permissions_role',$permissions_role);
		PConfig::Set($channel['channel_id'],'system','use_browser_location', (string) intval($this->data['user']['allow_location']));

		// find the self contact
		
		$self = null;

		if (isset($this->data['contact']) && is_array($this->data['contact'])) {
			foreach ($this->data['contact'] as $contact) {
				if (isset($contact['self']) && intval($contact['self'])) {
					$self = $contact;
					break;
				}
			}
		}

		if (! is_array($self)) {
			logger('self contact not found.');
			return;
		}

		// find relevant profile fields.



		// import contacts

		if (isset($this->data['contact']) && is_array($this->data['contact'])) {
			foreach ($this->data['contact'] as $contact) {
				if (isset($contact['self']) && intval($contact['self'])) {
					continue;
				}

				// build/store xchan, xprof, hubloc, abook and abconfig


			}
		}




		// import pconfig
		// it is unlikely we can make use of these unless we recongise them. 
		
		if (iset($this->data['pconfig']) && is_array($this->data['pconfig'])) {
			foreach ($this->data['pconfig'] as $pc) {
				$entry = [
					'cat' => escape_tags(str_replace('.','__',$pc['cat'])),
					'k' => escape_tags(str_replace('.','__',$pc['k'])),
					'v' => ((preg_match('|^a:[0-9]+:{.*}$|s', $pc['v'])) ? serialise(unserialize($pc['v'])) : $pc['v']),
				];
				PConfig::Set($channel['channel_id'],$entry['cat'],$entry['k'],$entry['v']);
			}
		}

		
		// update system
		

	}


}