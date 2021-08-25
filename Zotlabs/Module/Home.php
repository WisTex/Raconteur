<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\LDSignatures;
use Zotlabs\Web\HTTPSig;
use Zotlabs\Web\Controller;

require_once('include/conversation.php');

class Home extends Controller {

	function init() {



		$ret = [];
	
		call_hooks('home_init',$ret);

		if (ActivityStreams::is_as_request()) {
			$x = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			Activity::ap_schema()
			]], Activity::encode_site() );

			$headers = [];
			$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;
			$x['signature'] = LDSignatures::sign($x,[ 'channel_address' => z_root(), 'channel_prvkey' => get_config('system','prvkey') ]);
			$ret = json_encode($x, JSON_UNESCAPED_SLASHES);
			logger('data: ' . jindent($ret), LOGGER_DATA);
			$headers['Date'] = datetime_convert('UTC','UTC', 'now', 'D, d M Y H:i:s \\G\\M\\T');
			$headers['Digest'] = HTTPSig::generate_digest_header($ret);
			$headers['(request-target)'] = strtolower($_SERVER['REQUEST_METHOD']) . ' ' . $_SERVER['REQUEST_URI'];

			$h = HTTPSig::create_sig($headers,get_config('system','prvkey'),z_root());
			HTTPSig::set_headers($h);

			echo $ret;
			killme();
		}


		if (Libzot::is_zot_request()) {

			$key = get_config('system','prvkey');
			$ret = json_encode(Libzot::site_info());

			$headers = [ 'Content-Type' => 'application/x-zot+json', 'Digest' => HTTPSig::generate_digest_header($ret) ];
			$headers['(request-target)'] = strtolower($_SERVER['REQUEST_METHOD']) . ' ' . $_SERVER['REQUEST_URI'];
			$h = HTTPSig::create_sig($headers, $key, z_root());
			HTTPSig::set_headers($h);
			echo $ret;
			killme();
		}

		$splash = ((argc() > 1 && argv(1) === 'splash') ? true : false);
	
		$channel = App::get_channel();
		if (local_channel() && $channel && $channel['xchan_url'] && ! $splash) {
			$dest = $channel['channel_startpage'];
			if (! $dest) {
				$dest = get_pconfig(local_channel(),'system','startpage');
			}
			if (! $dest) {
				$dest = get_config('system','startpage');
			}
			if (! $dest) {
				$dest = z_root() . '/stream';
			}
			goaway($dest);
		}

		if (remote_channel() && (! $splash) && $_SESSION['atoken']) {
			$r = q("select * from atoken where atoken_id = %d",
				intval($_SESSION['atoken'])
			);
			if ($r) {
				$x = channelx_by_n($r[0]['atoken_uid']);
				if ($x) {
					goaway(z_root() . '/channel/' . $x['channel_address']);
				}
			}
		} 

	
		if (get_account_id() && ! $splash) {
			goaway(z_root() . '/new_channel');
		}
	
	}
	
	
	function get($update = 0, $load = false) {
	
		$o = EMPTY_STR;
	
		if (x($_SESSION,'theme')) {
			unset($_SESSION['theme']);
		}
		if (x($_SESSION,'mobile_theme')) {
			unset($_SESSION['mobile_theme']);
		}
	
		$splash = ((argc() > 1 && argv(1) === 'splash') ? true : false);
	
		call_hooks('home_content',$o);
		if ($o) {
			return $o;
		}
		
		$frontpage = get_config('system','frontpage');
		if ($frontpage) {
			if (strpos($frontpage,'include:') !== false) {
				$file = trim(str_replace('include:' , '', $frontpage));
				if (file_exists($file)) {
					App::$page['template'] = 'full';
					App::$page['title'] = t('$Projectname');
					$o .= file_get_contents($file);
					return $o;
				}
			}
			if (strpos($frontpage,'http') !== 0) {
				$frontpage = z_root() . '/' . $frontpage;
			}
			if (intval(get_config('system','mirror_frontpage'))) {
				$o = '<html><head><title>' . t('$Projectname') . '</title></head><body style="margin: 0; padding: 0; border: none;" ><iframe src="' . $frontpage . '" width="100%" height="100%" style="margin: 0; padding: 0; border: none;" ></iframe></body></html>';
				echo $o;
				killme();
			}
			goaway($frontpage);
		}
	
	
		$sitename = get_config('system','sitename');
		if ($sitename) { 
			$o .= '<h1 class="home-welcome">' . sprintf( t('Welcome to %s') ,$sitename) . '</h1>';
		}
		
		$loginbox = get_config('system','login_on_homepage');
		if (intval($loginbox) || $loginbox === false) {
			$o .= login(true);
		}
	
		return $o;
	}
}
