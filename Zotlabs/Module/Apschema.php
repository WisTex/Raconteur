<?php

namespace Zotlabs\Module;


class Apschema extends \Zotlabs\Web\Controller {

	function init() {

		$base = z_root();

		$arr = [
			'@context' => [
				'zot'              => z_root() . '/apschema#',
				'id'               => '@id',
				'type'             => '@type',
				'meData'           => 'zot:meData',
				'meDataType'       => 'zot:meDataType',
				'meEncoding'       => 'zot:meEncoding',
				'meAlgorithm'      => 'zot:meAlgorithm',
				'meCreator'        => 'zot:meCreator',
				'meSignatureValue' => 'zot:meSignatureValue',
				'locationAddress'  => 'zot:locationAddress',
				'locationPrimary'  => 'zot:locationPrimary',
				'locationDeleted'  => 'zot:locationDeleted',
				'nomadicLocation'  => 'zot:nomadicLocation',
				'nomadicHubs'      => 'zot:nomadicHubs',

				'magicEnv' => [
					'@id'   => 'zot:magicEnv',
					'@type' => '@id'
				],

				'nomadicLocations' => [
					'@id'   => 'zot:nomadicLocations',
					'@type' => '@id'
				],

				'ostatus'      => 'http://ostatus.org#',
				'conversation' => 'ostatus:conversation'

			]
		];

		header('Content-Type: application/ld+json');
		echo json_encode($arr,JSON_UNESCAPED_SLASHES);
		killme();

	}




}