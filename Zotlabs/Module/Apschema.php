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
				'inheritPrivacy'     => 'as:inheritPrivacy',
				'commentPolicy'      => 'zot:commentPolicy',
				'topicalCollection'  => 'zot:topicalCollection',
				'eventRepeat'        => 'zot:eventRepeat',
			]
		];

		header('Content-Type: application/ld+json');
		echo json_encode($arr,JSON_UNESCAPED_SLASHES);
		killme();

	}




}