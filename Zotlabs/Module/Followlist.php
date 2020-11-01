<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\ASCollection;
use Zotlabs\Lib\Connect;


class Followlist extends Controller {

	function post() {

		if (! local_channel()) {
			return;
		}

		$max_records = get_config('system','max_imported_channels',1000);
		$importer = App::get_channel();
		$url = $_GET['url'];


		if ($importer && $url) {

			$obj = new ASCollection($url, $importer, 0, $max_records);
			$actors = $obj->get();

			if ($actors) {
				foreach ($actors as $actor) {
					if (is_array($actor)) {
						$result = Connect::connect($importer,$actor['id']);
					}
					else {
						$result = Connect::connect($importer,$actor);
					}
					if (! $result['success']) {
						notice ( t('Connect failed: ') . $url . t(' Reason: ') . $result['message']);
					}
				}
			}
		}
	}

	function get() {

		if (! local_channel()) {
			return login();
		}

		return replace_macros(get_markup_template('followlist.tpl'), [
			'$page_title' => t('Followlist'),
			'$notes'      => t('Enter the URL of an ActivityPub followers/following collection to import'),
			'$url'        => [ 'url', t('URL of followers/following list'), '', '' ],
			'$submit'     => t('Submit')
		]);

	}
}