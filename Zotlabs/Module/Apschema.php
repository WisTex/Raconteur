<?php

namespace Zotlabs\Module;

use Zotlabs\Web\Controller;

class Apschema extends Controller {

	function init() {

		$arr = [
			'@context' => [
				'zot'                => z_root() . '/apschema#',
				'as'                 => 'https://www.w3.org/ns/activitystreams#',
				'toot'               => 'http://joinmastodon.org/ns#',
				'ostatus'            => 'http://ostatus.org#',
				'diaspora'           => 'https://diasporafoundation.org/ns/',
				'schema'             => 'http://schema.org#',
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
				'Category'           => 'zot:Category',
				'replyTo'            => 'zot:replyTo',
				'PropertyValue'      => 'schema:PropertyValue',
				'value'              => 'schema:value',
				'discoverable'       => 'toot:discoverable',
				'guid'               => 'diaspora:guid',
			]
		];

		header('Content-Type: application/ld+json');
		echo json_encode($arr,JSON_UNESCAPED_SLASHES);
		killme();

	}




}