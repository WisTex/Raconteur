<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\ASCollection;
use Zotlabs\Lib\Connect;
use Zotlabs\Lib\Apps;

class Followlist extends Controller {

	function post() {

		if (! local_channel()) {
			return;
		}

		if (! Apps::system_app_installed(local_channel(),'Followlist')) {
			return;
		}

		check_form_security_token_redirectOnErr('/stream', 'followlist');

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

        $desc = t('This app allows you to connect to everybody in a pre-defined ActivityPub collection, such as follower/following lists. Install the app and revisit this page to input the source URL.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

		if (! local_channel()) {
			return login();
		}


		if (! Apps::system_app_installed(local_channel(),'Followlist')) {
			return $text;
		}

		$max_records = get_config('system','max_imported_channels',1000);

		// check service class limits

		$r = q("select count(*) as total from abook where abook_channel = %d and abook_self = 0 ",
			intval(local_channel())
		);
		if ($r) {
			$total_channels = $r[0]['total'];
		}

		$sc = service_class_fetch(local_channel(),'total_channels');
		if ($sc !== false) {
			$allowed = intval($sc) - $total_channels;
			if ($allowed < $max_records) {
				$max_records = $allowed;
			}
		}

		return replace_macros(get_markup_template('followlist.tpl'), [
			'$page_title'          => t('Followlist'),
			'$limits'              => sprintf( t('You may import up to %d records'), $max_records), 
			'$form_security_token' => get_form_security_token("followlist"),
			'$disabled'            => (($total_channels > $max_records) ? ' disabled="disabled" ' : EMPTY_STR),
			'$notes'               => t('Enter the URL of an ActivityPub followers/following collection to import'),
			'$url'                 => [ 'url', t('URL of followers/following list'), '', '' ],
			'$submit'              => t('Submit')
		]);

	}
}