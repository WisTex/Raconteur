<?php

namespace Zotlabs\Lib;

use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Activity;

/**
 * Class for dealing with ActivityStreams ordered collections.
 * Construct with the object url and an optional channel to sign the request.
 * Returns an array of collection members.
 * If desired, call $collection->next to return additional pages (arrays of collection members).
 * Returns an empty array when there is nothing more to return
 */
 



class ASCollection {

	private $url = null;
	private $channel = null;
	private $nextpage = null;
	private $data = null;

	function __construct($url,$channel = null) {

		$this->url = $url;
		$this->channel = $channel;
		
		$data = Activity::fetch($url,$channel);

		if (! $data) {
			return false;
		}

		$ptr = $data;
			
		if ($data['type'] === 'OrderedCollection') {
			if (array_key_exists('first',$data)) {
				$ptr = $data['first'];
			}
		}

		if ($ptr['type'] === 'OrderedCollectionPage' && $ptr['orderedItems']) {
			$this->data = $ptr['orderedItems'];			
		}

		$this->setnext($data);
		return $this->data;
	}

	function next() {

		if (! $this->nextpage) {
			return [];
		}
		$data = Activity::fetch($this->nextpage,$channel);

		if (! $data) {
			return [];
		}
		
		if ($data['type'] === 'OrderedCollectionPage' && $data['orderedItems']) {
			$this->data = $data['orderedItems'];			
		}
		
		$this->setnext($data);
		return $this->data;
	}

	function setnext($data) {
		if (array_key_exists('next',$data)) {
			$this->nextpage = $data['next'];
		}
		elseif (array_key_exists('last',$data)) {
			$this->nextpage = $data['last'];
		}
	}

}