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
				[ 'src' => System::get_project_icon(), 'sizes' => '64x64' ],
				[ 'src' => '/images/' . System::get_platform_name() . '.svg', 'sizes' => '128x128' ],
			],
			'scope' => '/',
			'start_url' => '/',
			'display' => 'fullscreen',
			'orientation' => 'any',
			'theme_color' => 'blue',
			'background_color' => 'white',
			'share_target' => [
				'action' => '/rpost',
				'method' => 'GET',
				'title' => 'title',
				'text' => 'body',
				'url' => 'url',
			]
				
		];
		

		json_return_and_die($ret,'application/manifest+json');
	}












}
