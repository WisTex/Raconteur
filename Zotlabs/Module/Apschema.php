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
				'ical'               => 'http://www.w3.org/2002/12/cal#',
				'conversation'       => 'ostatus:conversation',
				'sensitive'          => 'as:sensitive',
				'inheritPrivacy'     => 'as:inheritPrivacy',
				'commentPolicy'      => 'zot:commentPolicy',
				'topicalCollection'  => 'zot:topicalCollection',
				'rrule'              => 'ical:rrule',
			]
		];

		header('Content-Type: application/ld+json');
		echo json_encode($arr,JSON_UNESCAPED_SLASHES);
		killme();

	}




}