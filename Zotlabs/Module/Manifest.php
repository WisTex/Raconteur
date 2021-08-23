<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\System;


class Manifest extends Controller {

	function init() {
		$ret = [
			'name' => System::get_platform_name(),
			'short_name' => System::get_platform_name(),
			'icons' => [
				[ 'src' => '/images/' . System::get_platform_name() . '-64' . '.png', 'sizes' => '64x64' ],
				[ 'src' => '/images/' . System::get_platform_name() . '-192' . '.png', 'sizes' => '192x192' ],
				[ 'src' => '/images/' . System::get_platform_name() . '-512' . '.png', 'sizes' => '512x512' ],
				[ 'src' => '/images/' . System::get_platform_name() . '.svg', 'sizes' => '600x600' ],
			],
			'scope' => '/',
			'start_url' => z_root(),
			'display' => 'fullscreen',
			'orientation' => 'any',
			'theme_color' => 'blue',
			'background_color' => 'white',
			'share_target' => [
				'action' => '/rpost',
				'method' => 'POST',
				'enctype' => 'multipart/form-data',
				'params' => [
					'title' => 'title',
					'text' => 'body',
					'url' => 'url',
					'files' => [
						[ 'name' => 'userfile',
							'accept' => [ 'image/*', 'audio/*', 'video/*', 'text/*', 'application/*' ]
						]
					]
				]
			]
				
		];
		

		json_return_and_die($ret,'application/manifest+json');
	}












}
