<?php

namespace Zotlabs\Module;


class Apschema extends \Zotlabs\Web\Controller {

	function init() {

		$base = z_root();

		$arr = [
			'@context' => [
				'zot'                => z_root() . '/apschema#',
				'id'                 => '@id',
				'type'               => '@type',
				'ostatus'            => 'http://ostatus.org#',
				'conversation'       => 'ostatus:conversation',
				'sensitive'          => 'as:sensitive',
				'movedTo'            => 'as:movedTo',
				'copiedTo'           => 'as:copiedTo',
				'alsoKnownAs'        => 'as:alsoKnownAs',
				'inheritPrivacy'     => 'as:inheritPrivacy',
				'EmojiReact'         => 'as:EmojiReact',
				'commentPolicy'      => 'zot:commentPolicy',
				'topicalCollection'  => 'zot:topicalCollection',
				'eventRepeat'        => 'zot:eventRepeat',
				'emojiReaction'      => 'zot:emojiReaction',
				'expires'            => 'zot:expires',
				'directMessage'      => 'zot:directMessage',
				'replyTo'            => 'zot:replyTo',
				'meta'               => 'zot:meta',
				'value'              => 'zot:value',
			]
		];

		header('Content-Type: application/ld+json');
		echo json_encode($arr,JSON_UNESCAPED_SLASHES);
		killme();

	}




}