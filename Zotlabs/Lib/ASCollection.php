<?php

namespace Zotlabs\Lib;

use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Activity;

/**
 * Class for dealing with fetching ActivityStreams collections (ordered or unordered, normal or paged).
 * Construct with the object url and an optional channel to sign the request.
 * Use $class->get() to return an array of collection members.
 */
 



class ASCollection {

	private $url      = null;
	private $channel  = null;
	private $nextpage = null;
	private $data     = [];
	
	function __construct($url,$channel = null) {

		$this->url     = $url;
		$this->channel = $channel;
		
		$data = Activity::fetch($url,$channel);

		if (! is_array($data)) {
			return;
		}

		if (! in_array($data['type'], ['Collection','OrderedCollection'])) {
			return false;
		}
		
		if (array_key_exists('first',$data)) {
			$this->nextpage = $data['first'];
		}

		if (isset($data['items']) && is_array($data['items'])) {
			$this->data = $data['items'];			
		}
		elseif (isset($data['orderedItems']) && is_array($data['orderedItems'])) {
			$this->data = $data['orderedItems'];			
		}

		do {
			$x = $this->next();
		} while ($x);
	}

	function get() {
		return $this->data;
	}

	function next() {

		if (! $this->nextpage) {
			return false;
		}
				
		$data = Activity::fetch($this->nextpage,$this->channel);

		if (! is_array($data)) {
			return false;
		}
		
		if (! in_array($data['type'], ['CollectionPage','OrderedCollectionPage'])) {
			return false;
		}

		$this->setnext($data);

		if (isset($data['items']) && is_array($data['items'])) {
			$this->data = array_merge($this->data,$data['items']);			
		}
		elseif (isset($data['orderedItems']) && is_array($data['orderedItems'])) {
			$this->data = array_merge($this->data,$data['orderedItems']);			
		}

		return true;
	}

	function setnext($data) {
		if (array_key_exists('next',$data)) {
			$this->nextpage = $data['next'];
		}
		elseif (array_key_exists('last',$data)) {
			$this->nextpage = $data['last'];
		}
		else {
			$this->nextpage = false;
		}
	}

}
