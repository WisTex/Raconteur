<?php

namespace Zotlabs\Lib;


class Share {

	private $item = null;


	public function __construct($post_id) {
	
		if(! $post_id)
			return;
	
		if(! (local_channel() || remote_channel()))
			return;
	
		$r = q("SELECT * from item left join xchan on author_xchan = xchan_hash WHERE id = %d  LIMIT 1",
			intval($post_id)
		);
		if(! $r)
			return;

		if(($r[0]['item_private']) && ($r[0]['xchan_network'] !== 'rss'))
			return;
	
		$sql_extra = item_permissions_sql($r[0]['uid']);
	
		$r = q("select * from item where id = %d $sql_extra",
			intval($post_id)
		);
		if(! $r)
			return;
	
		if($r[0]['mimetype'] !== 'text/bbcode')
			return;
	
		/** @FIXME eventually we want to post remotely via rpost on your home site */
		// When that works remove this next bit:
	
		if(! local_channel())
			return;

		xchan_query($r);
	
		$this->item = $r[0];
		return;
	}

	public function obj() {
		$obj = [];

		if(! $this->item)
			return $obj;

		$obj['type']         = $this->item['obj_type'];
		$obj['id']           = $this->item['mid'];
		$obj['content']      = $this->item['body'];
		$obj['content_type'] = $this->item['mimetype'];
		$obj['title']        = $this->item['title'];
		$obj['created']      = $this->item['created'];
		$obj['edited']       = $this->item['edited'];
		$obj['author'] = [
			'name'     => $this->item['author']['xchan_name'],
			'address'  => $this->item['author']['xchan_addr'],
			'network'  => $this->item['author']['xchan_network'],
			'link'     => [
				[ 
					'rel'  => 'alternate', 
					'type' => 'text/html', 
					'href' => $this->item['author']['xchan_url']
				],
				[
					'rel'  => 'photo', 
					'type' => $this->item['author']['xchan_photo_mimetype'], 
					'href' => $this->item['author']['xchan_photo_m']
				]
			]
		];

		$obj['owner'] = [
			'name'     => $this->item['owner']['xchan_name'],
			'address'  => $this->item['owner']['xchan_addr'],
			'network'  => $this->item['owner']['xchan_network'],
			'link'     => [
				[ 
					'rel'  => 'alternate', 
					'type' => 'text/html', 
					'href' => $this->item['owner']['xchan_url']
				],
				[
					'rel'  => 'photo', 
					'type' => $this->item['owner']['xchan_photo_mimetype'], 
					'href' => $this->item['owner']['xchan_photo_m']
				]
			]
		];

		$obj['link'] = [
			'rel'  => 'alternate',
			'type' => 'text/html',
			'href' => $this->item['plink']
		];

		return $obj;
	}

	public function bbcode() {
		$bb = NULL_STR;

		if(! $this->item)
			return $bb;

		$is_photo = (($this->item['obj_type'] === ACTIVITY_OBJ_PHOTO) ? true : false);
		if($is_photo) {
			$object = json_decode($this->item['obj'],true);
			$photo_bb = $object['body'];
		}
	
		if (strpos($this->item['body'], "[/share]") !== false) {
			$pos = strpos($this->item['body'], "[share");
			$bb = substr($this->item['body'], $pos);
		} else {
			$bb = "[share author='".urlencode($this->item['author']['xchan_name']).
				"' profile='"    . $this->item['author']['xchan_url'] .
				"' avatar='"     . $this->item['author']['xchan_photo_s'] .
				"' link='"       . $this->item['plink'] .
				"' auth='"       . (($this->item['author']['network'] === 'zot') ? 'true' : 'false') .
				"' posted='"     . $this->item['created'] .
				"' message_id='" . $this->item['mid'] .
			"']";
			if($this->item['title'])
				$bb .= '[b]'.$this->item['title'].'[/b]'."\r\n";
			$bb .= (($is_photo) ? $photo_bb . "\r\n" . $this->item['body'] : $this->item['body']);
			$bb .= "[/share]";
		}

		return $bb;

	}

}