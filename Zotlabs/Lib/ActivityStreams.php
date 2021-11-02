<?php

namespace Zotlabs\Lib;

use Zotlabs\Web\HTTPSig;


/**
 * @brief ActivityStreams class.
 *
 * Parses an ActivityStream JSON string.
 */

class ActivityStreams {

	public $raw        = null;
	public $data       = null;
	public $hub        = null;
	public $valid      = false;
	public $deleted    = false;
	public $id         = '';
	public $parent_id  = '';
	public $type       = '';
	public $actor      = null;
	public $obj        = null;
	public $tgt        = null;
	public $replyto    = null;
	public $origin     = null;
	public $owner      = null;
	public $signer     = null;
	public $ldsig      = null;
	public $sigok      = false;
	public $recips     = null;
	public $raw_recips = null;
	public $implied_create = false;
	
	/**
	 * @brief Constructor for ActivityStreams.
	 *
	 * Takes a JSON string or previously decode activity array as parameter,
	 * decodes it and sets up this object/activity, fetching any required attributes
	 * which were only referenced by @id/URI.
	 *
	 * @param string $string
	 */
	function __construct($string,$hub = null,$client = null) {

		$this->raw  = $string;
		$this->hub  = $hub;
		
		if (is_array($string)) {
			$this->data = $string;
			$this->raw = json_encode($string,JSON_UNESCAPED_SLASHES);
		}
		else {
			$this->data = json_decode($string, true);
		}

		if ($this->data) {

			// verify and unpack JSalmon signature if present
			// This will only be the case for Zot6 packets
			
			if (is_array($this->data) && array_key_exists('signed',$this->data)) {
				$ret = JSalmon::verify($this->data);
				$tmp = JSalmon::unpack($this->data['data']);
				if ($ret && $ret['success']) {
					if ($ret['signer']) {
						logger('Unpacked: ' . json_encode($tmp,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOGGER_DATA,LOG_DEBUG);
						$saved = json_encode($this->data,JSON_UNESCAPED_SLASHES);
						$this->data = $tmp;
						$this->data['signer'] = $ret['signer'];
						$this->data['signed_data'] = $saved;
						if ($ret['hubloc']) {
							$this->data['hubloc'] = $ret['hubloc'];
						}
					}
				}
			}

			// This indicates only that we have sucessfully decoded JSON.
			$this->valid = true;

			// Special handling for Mastodon "delete actor" activities which will often fail to verify
			// because the key cannot be fetched. We will catch this condition elsewhere. 
			
			if (array_key_exists('type',$this->data) && array_key_exists('actor',$this->data) && array_key_exists('object',$this->data)) {
				if ($this->data['type'] === 'Delete' && $this->data['actor'] === $this->data['object']) {
					$this->deleted = $this->data['actor'];
					$this->valid = false;
				}
			}

		}

		// Attempt to assemble an Activity from what we were given.
		
		if ($this->is_valid()) {
			$this->id      = $this->get_property_obj('id');
			$this->type    = $this->get_primary_type();
			$this->actor   = $this->get_actor('actor','','');
			$this->obj     = $this->get_compound_property('object');
			$this->tgt     = $this->get_compound_property('target'); 
			$this->origin  = $this->get_compound_property('origin');
			$this->recips  = $this->collect_recips();
			$this->replyto = $this->get_property_obj('replyTo');
			
			$this->ldsig = $this->get_compound_property('signature');
			if ($this->ldsig) {
				$this->signer = $this->get_compound_property('creator',$this->ldsig);
				if ($this->signer && is_array($this->signer) && array_key_exists('publicKey',$this->signer)
					&& is_array($this->signer['publicKey']) && $this->signer['publicKey']['publicKeyPem']) {
					$this->sigok = LDSignatures::verify($this->data,$this->signer['publicKey']['publicKeyPem']);
				}
			}

			// Implied create activity required by C2S specification if no object is present
			
			if (! $this->obj) {
				if (! $client) {
					$this->implied_create = true;
				}
				$this->obj = $this->data;
				$this->type = 'Create';
				if (! $this->actor) {
					$this->actor = $this->get_actor('attributedTo',$this->obj);
				}
			}

			// fetch recursive or embedded activities
			
			if ($this->obj && is_array($this->obj) && array_key_exists('object',$this->obj)) {
				$this->obj['object'] = $this->get_compound_property($this->obj['object']);
			}
			
			// Enumerate and store actors in referenced objects
			
			if ($this->obj && is_array($this->obj) && $this->obj['actor']) {
				$this->obj['actor'] = $this->get_actor('actor',$this->obj);
			}
			if ($this->tgt && is_array($this->tgt) && $this->tgt['actor']) {
				$this->tgt['actor'] = $this->get_actor('actor',$this->tgt);
			}

			// Determine if this is a followup or response activity
			
			$this->parent_id = $this->get_property_obj('inReplyTo');

			if ((! $this->parent_id) && is_array($this->obj)) {				
				$this->parent_id = $this->obj['inReplyTo'];
			}
			if ((! $this->parent_id) && is_array($this->obj)) {				
				$this->parent_id = $this->obj['id'];
			}
		}
	}

	/**
	 * @brief Return if instantiated ActivityStream is valid.
	 *
	 * @return boolean Return true if the JSON string could be decoded.
	 */

	function is_valid() {
		return $this->valid;
	}

	function set_recips($arr) {
		$this->saved_recips = $arr;
	}

	/**
	 * @brief Collects all recipients.
	 *
	 * @param string $base
	 * @param string $namespace (optional) default empty
	 * @return array
	 */
	function collect_recips($base = '', $namespace = '') {
		$x = [];

		$fields = [ 'to', 'cc', 'bto', 'bcc', 'audience'];
		foreach ($fields as $f) {
			// don't expand these yet
			$y = $this->get_property_obj($f, $base, $namespace);
			if ($y) {
				if (! is_array($this->raw_recips)) {
					$this->raw_recips = [];
				}
				if (! is_array($y)) {
					$y = [ $y ];
				}
				$this->raw_recips[$f] = $y;
				$x = array_merge($x, $y);
			}
		}
		
// not yet ready for prime time
//		$x = $this->expand($x,$base,$namespace);
		return $x;
	}

	function expand($arr,$base = '',$namespace = '') {
		$ret = [];

		// right now use a hardwired recursion depth of 5

		for ($z = 0; $z < 5; $z ++) {
			if (is_array($arr) && $arr) {
				foreach ($arr as $a) {
					if (is_array($a)) {
						$ret[] = $a;
					}
					else {
						$x = $this->get_compound_property($a,$base,$namespace);
						if ($x) {
							$ret = array_merge($ret,$x);
						}
					}
				}
			}
		}

		/// @fixme de-duplicate

		return $ret;
	}

	/**
	 * @brief
	 *
	 * @param array $base
	 * @param string $namespace if not set return empty string
	 * @return string|NULL
	 */
	 
	function get_namespace($base, $namespace) {

		if (! $namespace) {
			return EMPTY_STR;
		}
		
		$key = null;

		foreach ( [ $this->data, $base ] as $b ) {
			if (! $b) {
				continue;
			}

			if (array_key_exists('@context', $b)) {
				if (is_array($b['@context'])) {
					foreach ($b['@context'] as $ns) {
						if (is_array($ns)) {
							foreach ($ns as $k => $v) {
								if ($namespace === $v) {
									$key = $k;
								}
							}
						}
						else {
							if ($namespace === $ns) {
								$key = '';
							}
						}
					}
				}
				else {
					if ($namespace === $b['@context']) {
						$key = '';
					}
				}
			}
		}

		return $key;
	}

	/**
	 * @brief
	 *
	 * @param string $property
	 * @param array $base (optional)
	 * @param string $namespace (optional) default empty
	 * @return NULL|mixed
	 */
	 
	function get_property_obj($property, $base = '', $namespace = '') {
		$prefix = $this->get_namespace($base, $namespace);
		if ($prefix === null) {
			return null;
		}

		$base = (($base) ? $base : $this->data);
		$propname = (($prefix) ? $prefix . ':' : '') . $property;

		if (! is_array($base)) {
			btlogger('not an array: ' . print_r($base,true));
			return null;
		}

		return ((array_key_exists($propname, $base)) ? $base[$propname] : null);
	}


	/**
	 * @brief Fetches a property from an URL.
	 *
	 * @param string $url
	 * @param array $channel (signing channel, default system channel)
	 * @return NULL|mixed
	 */

	function fetch_property($url,$channel = null,$hub = null) {		
		return Activity::fetch($url,$channel,$hub);
	}

	static function is_an_actor($s) {
		if (! $s) {
			return false;
		}
		return (in_array($s,[ 'Application','Group','Organization','Person','Service' ]));
	}

	static function is_response_activity($s) {
		if (! $s) {
			return false;
		}
		return (in_array($s, [ 'Like', 'Dislike', 'Flag', 'Block', 'Announce', 'Accept', 'Reject', 'TentativeAccept', 'TentativeReject', 'emojiReaction', 'EmojiReaction', 'EmojiReact' ]));
	}





	/**
	 * @brief
	 *
	 * @param string $property
	 * @param array $base
	 * @param string $namespace (optional) default empty
	 * @return NULL|mixed
	 */

	function get_actor($property,$base='',$namespace = '') {
		$x = $this->get_property_obj($property, $base, $namespace);
		if (self::is_url($x)) {
			$y = Activity::get_cached_actor($x);
			if ($y) {
				return $y;
			}
		}
		
		$actor = $this->get_compound_property($property,$base,$namespace,true);
		if (is_array($actor) && self::is_an_actor($actor['type'])) {
			if (array_key_exists('id',$actor) && (! array_key_exists('inbox',$actor))) {
				$actor = $this->fetch_property($actor['id']);
			}
			return $actor;
		}
		return null;
	}


	/**
	 * @brief
	 *
	 * @param string $property
	 * @param array $base
	 * @param string $namespace (optional) default empty
	 * @param boolean $first (optional) default false, if true and result is a sequential array return only the first element
	 * @return NULL|mixed
	 */
	 
	function get_compound_property($property, $base = '', $namespace = '', $first = false) {
		$x = $this->get_property_obj($property, $base, $namespace);
		if (self::is_url($x)) {
			$y = $this->fetch_property($x);
			if (is_array($y)) {
				$x = $y;
			}
		}

		// verify and unpack JSalmon signature if present
		// This may be present in Zot6 packets
		
		if (is_array($x) && array_key_exists('signed',$x)) {
			$ret = JSalmon::verify($x);
			$tmp = JSalmon::unpack($x['data']);
			if ($ret && $ret['success']) {
				if ($ret['signer']) {
					logger('Unpacked: ' . json_encode($tmp,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOGGER_DATA,LOG_DEBUG);
					$saved = json_encode($x,JSON_UNESCAPED_SLASHES);
					$x = $tmp;
					$x['signer'] = $ret['signer'];
					$x['signed_data'] = $saved;
					if ($ret['hubloc']) {
						$x['hubloc'] = $ret['hubloc'];
					}
				}
			}
		}
		if ($first && is_array($x) && array_key_exists(0,$x)) {
			return $x[0];
		}

		return $x;
	}

	/**
	 * @brief Check if string starts with http.
	 *
	 * @param string $url
	 * @return boolean
	 */
	 
	static public function is_url($url) {
		if (($url) && (! is_array($url)) && ((strpos($url, 'http') === 0) || (strpos($url,'x-zot') === 0) || (strpos($url,'bear') === 0))) {
			return true;
		}

		return false;
	}

	/**
	 * @brief Gets the type property.
	 *
	 * @param array $base
	 * @param string $namespace (optional) default empty
	 * @return NULL|mixed
	 */
	 
	function get_primary_type($base = '', $namespace = '') {
		if (! $base) {
			$base = $this->data;
		}
		$x = $this->get_property_obj('type', $base, $namespace);
		if (is_array($x)) {
			foreach ($x as $y) {
				if (strpos($y, ':') === false) {
					return $y;
				}
			}
		}

		return $x;
	}

	function debug() {
		$x = var_export($this, true);
		return $x;
	}


	static function is_as_request() {

		$x = getBestSupportedMimeType([
			'application/ld+json;profile="https://www.w3.org/ns/activitystreams"',
			'application/activity+json',
			'application/ld+json;profile="http://www.w3.org/ns/activitystreams"',
			'application/ld+json', // some versions of Friendica aren't spec compliant and leave off the profile
			'application/x-zot-activity+json'
		]);

		return(($x) ? true : false);
	}
}