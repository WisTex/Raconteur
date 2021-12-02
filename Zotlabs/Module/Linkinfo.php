<?php
namespace Zotlabs\Module;

use App;
use DOMDocument;
use DomXPath;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Libzot;
use Zotlabs\Lib as Zlib;

require_once('include/security.php');

class Linkinfo extends Controller {

	function get() {
	
		logger('linkinfo: ' . print_r($_REQUEST,true), LOGGER_DEBUG);
	
		$text = null;
		$str_tags = '';
		$process_embed = true;
		$process_oembed = (($_GET['oembed']) ? true : false);	
		$process_zotobj = true;

		if(local_channel()) {
			$saved_oembed = ((get_pconfig(local_channel(),'system','linkinfo_embed',true)) ? true : false);
			if ($saved_oembed !== $process_oembed) {
				set_pconfig(local_channel(),'system','linkinfo_embed',intval($process_oembed));
			}
		}

		$br = "\n";
	
		if (x($_GET,'binurl'))
			$url = trim(hex2bin($_GET['binurl']));
		else
			$url = trim($_GET['url']);
	
		if (substr($url,0,1) === '!') {
			$process_embed = false;
			$url = substr($url,1);
		}

		$url = strip_zids($url);

		if (strpos($url,'geo:') === 0) {
			if ($process_embed) {
				echo $br . '[map=' . substr($url,4) . ']' . $br;
			}
			else {
				echo $br . '[url]' . $url . '[/url]' . $br;
			}
			killme();
		}

		if (strpos($url,'tel:') === 0 || (is_phone_number($url) !== false)) {
			$phone = $url;
			if (strpos($url,'tel:') !== 0) {
				$url = 'tel:' . is_phone_number($url);
			}
			echo $br . '[url=' . $url . ']' . $phone . '[/url]' . $br;
			killme();
		}

		$m = parse_url($url);
		
		if (! $m['scheme']) {
			if (strpos($url,'@')) {
				$xc = discover_by_webbie($url);
				if ($xc) {
					$x = q("select * from xchan where xchan_hash = '%s'",
						dbesc($xc)
					);
					if ($x) {
						$url = $x[0]['xchan_url'];
					}
				}
				else {
					echo $br . '[url=mailto:' . $url . ']' . $url . '[/url]' . $br;
					killme();
				}
			}
			else {
				$url = 'http://' . $url;
			}
		}	
	
		if ($_GET['title'])
			$title = strip_tags(trim($_GET['title']));
	
		if ($_GET['description'])
			$text = strip_tags(trim($_GET['description']));
	
		if ($_GET['tags']) {
			$arr_tags = str_getcsv($_GET['tags']);
			if (count($arr_tags)) {
				array_walk($arr_tags,'self::arr_add_hashes');
				$str_tags = $br . implode(' ',$arr_tags) . $br;
			}
		}
	
		logger('linkinfo: ' . $url, LOGGER_DEBUG);
	
		$zrl = is_matrix_url($url);

		if (! $process_embed) {
			if ($zrl) {
				echo $br . '[zrl]' . $url . '[/zrl]' . $br;
			}
			else {
				echo $br . '[url]' . $url . '[/url]' . $br;
			}
			killme();
		}

		$result = z_fetch_url($url, false, 0, [ 'novalidate' => true, 'nobody' => true ] );
		if ($result['success']) {
			$hdrs=[];
			$h = explode("\n",$result['header']);
			foreach ($h as $l) {
				list($k,$v) = array_map("trim", explode(":", trim($l), 2));
				$hdrs[strtolower($k)] = $v;
			}
			if (array_key_exists('content-type', $hdrs))
				$type = $hdrs['content-type'];
			if ($type) {
				if (stripos($type,'image/') !== false) {
					$basename = basename($url);
					
					if ($zrl)
						echo $br . '[zmg alt="' . $basename . '"]' . $url . '[/zmg]' . $br;
					else
						echo $br . '[img alt="' . $basename . '"]' . $url . '[/img]' . $br;
					killme();
				}
				if ((stripos($type,'video/') !== false) || ($type === 'application/ogg')) {
					$thumb = self::get_video_poster($url);
					if($thumb) {
						if ($zrl)
							echo $br . '[zvideo poster=\'' . $thumb . '\']' . $url . '[/zvideo]' . $br;
						else
							echo $br . '[video poster=\'' . $thumb . '\']' . $url . '[/video]' . $br;
						killme();
					}
					if ($zrl)
						echo $br . '[zvideo]' . $url . '[/zvideo]' . $br;
					else
						echo $br . '[video]' . $url . '[/video]' . $br;
					killme();
				}
				if (stripos($type,'audio/') !== false) {
					if ($zrl)
						echo $br . '[zaudio]' . $url . '[/zaudio]' . $br;
					else
						echo $br . '[audio]' . $url . '[/audio]' . $br;
					killme();
				}
				if (strtolower($type) === 'text/calendar') {
					$content = z_fetch_url($url,false,0,array('novalidate' => true));
					if ($content['success']) {
						$ev = ical_to_ev($content['body']);
						if ($ev) {
							echo $br . format_event_bbcode($ev[0]) . $br;
							killme();
						}
					}
				}
				if (strtolower($type) === 'application/pdf' || strtolower($type) === 'application/x-pdf') {
					echo $br . '[embed]' . $url . '[/embed]' . $br;
					killme();
				}
			}
		}
	
		$template = $br . '[url=%s]%s[/url]%s' . $br;
	
		$arr = array('url' => $url, 'text' => '');
	
		call_hooks('parse_link', $arr);
	
		if (strlen($arr['text'])) {
			echo $arr['text'];
			killme();
		}

		if ($process_oembed) {
			$x = oembed_process($url);
			if ($x) {
				echo $x;
				killme();
			}
		}
		
		if ($process_zotobj) {
			$x = Activity::fetch($url, App::get_channel());
			$y = null;
			if (is_array($x)) {
				if (ActivityStreams::is_an_actor($x['type']) && $x['id']) {
					if (check_siteallowed($x['id']) && check_channelallowed($x['id'])) {
						$url = $x['url'];
						if (is_array($url)) {
							$url = $url[0]['href'];
						}
						$name = (($x['name']) ? $x['name'] . ' (' . $x['preferredUsername'] . ')' : $x['preferredUsername']);

						if (array_path_exists('icon/url',$x)) {
							$text = $br . $br . '[zrl=' . $url . '][zmg=300x300]' . $x['icon']['url'] . '[/zmg][/zrl]' ;
						}
						$text .= $br . $br . '[zrl=' . $url . ']' . $name . '[/zrl]' . $br . $br;
						echo $text;
						killme();
					}
				}
				else {
					$y = new ActivityStreams($x);
					if ($y->is_valid() && $y->type === 'Announce' && is_array($y->obj)
						&& array_key_exists('object',$y->obj) && array_key_exists('actor',$y->obj)) {
						// This is a relayed/forwarded Activity (as opposed to a shared/boosted object)
						// Reparse the encapsulated Activity and use that instead
						logger('relayed activity',LOGGER_DEBUG);
						$y = new ActivityStreams($y->obj);
					}
				}

				if ($y && $y->is_valid()) {
					$z = Activity::decode_note($y);
					$r = q("select hubloc_hash, hubloc_network, hubloc_url from hubloc where hubloc_hash = '%s' OR hubloc_id_url = '%s'",
						dbesc(is_array($y->actor) ? $y->actor['id'] : $y->actor),
						dbesc(is_array($y->actor) ? $y->actor['id'] : $y->actor)
					); 

					if ($r) {
						$r = Libzot::zot_record_preferred($r);
						if ($z) {
							$z['author_xchan'] = $r['hubloc_hash'];
						}
					}

					if ($z) {

						// do not allow somebody to embed a post that was blocked by the site admin
						// We *will* let them over-rule any blocks they created themselves
						
						if (check_siteallowed($r['hubloc_id_url']) && check_channelallowed($z['author_xchan'])) {
							$s = new Zlib\Share($z);
							echo $s->bbcode();
							killme();
						}
					}
				}
			}
		}


		if ($url && $title && $text) {
	
			$text = $br . '[quote]' . trim($text) . '[/quote]' . $br;
	
			$title = str_replace(array("\r","\n"),array('',''),$title);
	
			$result = sprintf($template,$url,($title) ? $title : $url,$text) . $str_tags;
	
			logger('linkinfo (unparsed): returns: ' . $result);
	
			echo $result;
			killme();
		}
	
		$siteinfo = self::parseurl_getsiteinfo($url);
	
		// If the site uses this platform, use zrl rather than url so they get zids sent to them by default
	
		if (is_matrix_url($url))
			$template = str_replace('url','zrl',$template);
	
		if ($siteinfo["title"] == "") {
			echo sprintf($template,$url,$url,'') . $str_tags;
			killme();
		} else {
			$text = $siteinfo["text"];
			$title = $siteinfo["title"];
		}
	
		$image = "";
	
		if (isset($siteinfo['images']) && is_array($siteinfo['images']) && count($siteinfo["images"])) {
			/* Execute below code only if image is present in siteinfo */
	
			$total_images = 0;
			$max_images = get_config('system','max_bookmark_images');
			if ($max_images === false)
				$max_images = 2;
			else
				$max_images = intval($max_images);
	
			foreach ($siteinfo["images"] as $imagedata) {
	                        if ($url) {
	                            $image .= sprintf('[url=%s]', $url);
	                        }
				$image .= '[img='.$imagedata["width"].'x'.$imagedata["height"].']'.$imagedata["src"].'[/img]';
	                        if ($url) {
	                            $image .= '[/url]';
	                        }
	                        $image .= "\n";
				$total_images ++;
				if ($max_images && $max_images >= $total_images)
					break;
	        }
		}
	
		if (strlen($text)) {
			$text = $br.'[quote]'.trim($text).'[/quote]'.$br ;
		}
	
		if ($image) {
			$text = $br.$br.$image.$text;
		}
		$title = str_replace(array("\r","\n"),array('',''),$title);
	
		$result = sprintf($template,$url,($title) ? $title : $url,$text) . $str_tags;
	
		logger('linkinfo: returns: ' . $result, LOGGER_DEBUG);
	
		echo trim($result);
		killme();
	
	}
	
	
	public static function deletexnode(&$doc, $node) {
		$xpath = new DomXPath($doc);
		$list = $xpath->query("//".$node);
		foreach ($list as $child)
			$child->parentNode->removeChild($child);
	}
	
	public static function completeurl($url, $scheme) {
	        $urlarr = parse_url($url);
	
	        if (isset($urlarr["scheme"]))
	                return($url);
	
	        $schemearr = parse_url($scheme);
	
	        $complete = $schemearr["scheme"]."://".$schemearr["host"];
	
	        if ($schemearr["port"] != "")
	                $complete .= ":".$schemearr["port"];
	
			if (strpos($urlarr['path'],'/') !== 0)
				$complete .= '/';
	
	        $complete .= $urlarr["path"];
	
	        if ($urlarr["query"] != "")
	                $complete .= "?".$urlarr["query"];
	
	        if ($urlarr["fragment"] != "")
	                $complete .= "#".$urlarr["fragment"];
	
	        return($complete);
	}

	public static function get_video_poster($url) {

		if(strpos($url,z_root() . '/cloud/') === false) {
			return EMPTY_STR;
		}
		$m = parse_url($url,PHP_URL_PATH);
		if($m) {
			// strip leading '/cloud/'
			$m = substr($m,7);
		}
		$nick = substr($m,0,strpos($m,'/'));
		$p = substr($m,strpos($m,'/')+1);

		// get the channel to check permissions
		
		$u = channelx_by_nick($nick);

		if($u && $p) {

			$sql_extra = permissions_sql(intval($u['channel_id']));

			$r = q("select hash, content from attach where display_path = '%s' and uid = %d and os_storage = 1 $sql_extra limit 1",
				dbesc($p),
				intval($u['channel_id'])
			);
			if($r) {
				$path = dbunescbin($r[0]['content']);
				if($path && @file_exists($path . '.thumb')) {
					return z_root() . '/poster/' . $nick . '/' . $r[0]['hash'];
				}
			}
		}
		return EMPTY_STR;
	}

	
	public static function parseurl_getsiteinfo($url) {
		$siteinfo = [];
	
	
		$result = z_fetch_url($url,false,0,array('novalidate' => true));
		if (! $result['success'])
			return $siteinfo;
	
		$header = $result['header'];
		$body   = $result['body'];
		
		// Check codepage in HTTP headers or HTML if not exist
		$cp = (preg_match('/Content-Type: text\/html; charset=(.+)\r\n/i', $header, $o) ? $o[1] : '');
		if (empty($cp))
		    $cp = (preg_match('/meta.+content=["|\']text\/html; charset=([^"|\']+)/i', $body, $o) ? $o[1] : 'AUTO');

		$body   = mb_convert_encoding($body, 'UTF-8', $cp);
		$body   = mb_convert_encoding($body, 'HTML-ENTITIES', "UTF-8");
	
		$doc    = new DOMDocument();
		@$doc->loadHTML($body);
	
		self::deletexnode($doc, 'style');
		self::deletexnode($doc, 'script');
		self::deletexnode($doc, 'option');
		self::deletexnode($doc, 'h1');
		self::deletexnode($doc, 'h2');
		self::deletexnode($doc, 'h3');
		self::deletexnode($doc, 'h4');
		self::deletexnode($doc, 'h5');
		self::deletexnode($doc, 'h6');
		self::deletexnode($doc, 'ol');
		self::deletexnode($doc, 'ul');
	
		$xpath = new DomXPath($doc);
	
		//$list = $xpath->query("head/title");
		$list = $xpath->query("//title");
		foreach ($list as $node)
			$siteinfo["title"] =  html_entity_decode($node->nodeValue, ENT_QUOTES, "UTF-8");
	
		//$list = $xpath->query("head/meta[@name]");
		$list = $xpath->query("//meta[@name]");
		foreach ($list as $node) {
			$attr = [];
			if ($node->attributes->length)
	                        foreach ($node->attributes as $attribute)
	                                $attr[$attribute->name] = $attribute->value;
	
			$attr["content"] = html_entity_decode($attr["content"], ENT_QUOTES, "UTF-8");
	
			switch (strtolower($attr["name"])) {
				case "fulltitle":
					$siteinfo["title"] = trim($attr["content"]);
					break;
				case "description":
					$siteinfo["text"] = trim($attr["content"]);
					break;
				case "thumbnail":
					$siteinfo["image"] = $attr["content"];
					break;
				case "twitter:image":
					$siteinfo["image"] = $attr["content"];
					break;
				case "twitter:image:src":
					$siteinfo["image"] = $attr["content"];
					break;
				case "twitter:card":
					if (($siteinfo["type"] == "") || ($attr["content"] == "photo")) {
						$siteinfo["type"] = $attr["content"];
					}
					break;
				case "twitter:description":
					$siteinfo["text"] = trim($attr["content"]);
					break;
				case "twitter:title":
					$siteinfo["title"] = trim($attr["content"]);
					break;
				case "dc.title":
					$siteinfo["title"] = trim($attr["content"]);
					break;
				case "dc.description":
					$siteinfo["text"] = trim($attr["content"]);
					break;
				case "keywords":
					$keywords = explode(",", $attr["content"]);
					break;
				case "news_keywords":
					$keywords = explode(",", $attr["content"]);
					break;
			}
		}
	
		//$list = $xpath->query("head/meta[@property]");
		$list = $xpath->query("//meta[@property]");
		foreach ($list as $node) {
			$attr = [];
			if ($node->attributes->length)
	                        foreach ($node->attributes as $attribute)
	                                $attr[$attribute->name] = $attribute->value;
	
			$attr["content"] = html_entity_decode($attr["content"], ENT_QUOTES, "UTF-8");
	
			switch (strtolower($attr["property"])) {
				case "og:image":
					$siteinfo["image"] = $attr["content"];
					break;
				case "og:title":
					$siteinfo["title"] = $attr["content"];
					break;
				case "og:description":
					$siteinfo["text"] = $attr["content"];
					break;
			}
		}
	
		if ($siteinfo["image"] == "") {
	            $list = $xpath->query("//img[@src]");
	            foreach ($list as $node) {
	                $attr = [];
	                if ($node->attributes->length)
	                    foreach ($node->attributes as $attribute)
	                        $attr[$attribute->name] = $attribute->value;
	
				$src = self::completeurl($attr["src"], $url);
				$photodata = @getimagesize($src);
	
				if (($photodata) && ($photodata[0] > 150) and ($photodata[1] > 150)) {
					if ($photodata[0] > 300) {
						$photodata[1] = round($photodata[1] * (300 / $photodata[0]));
						$photodata[0] = 300;
					}
					if ($photodata[1] > 300) {
						$photodata[0] = round($photodata[0] * (300 / $photodata[1]));
						$photodata[1] = 300;
					}
					$siteinfo["images"][] = array("src"=>$src,
									"width"=>$photodata[0],
									"height"=>$photodata[1]);
				}
	
	 		}
	    } else {
			$src = self::completeurl($siteinfo["image"], $url);
	
			unset($siteinfo["image"]);
	
			$photodata = @getimagesize($src);
	
			if (($photodata) && ($photodata[0] > 10) and ($photodata[1] > 10))
				$siteinfo["images"][] = array("src"=>$src,
								"width"=>$photodata[0],
								"height"=>$photodata[1]);
		}
	
		if ($siteinfo["text"] == "") {
			$text = "";
	
			$list = $xpath->query("//div[@class='article']");
			foreach ($list as $node)
				if (strlen($node->nodeValue) > 40)
					$text .= " ".trim($node->nodeValue);
	
			if ($text == "") {
				$list = $xpath->query("//div[@class='content']");
				foreach ($list as $node)
					if (strlen($node->nodeValue) > 40)
						$text .= " ".trim($node->nodeValue);
			}
	
			// If none text was found then take the paragraph content
			if ($text == "") {
				$list = $xpath->query("//p");
				foreach ($list as $node)
					if (strlen($node->nodeValue) > 40)
						$text .= " ".trim($node->nodeValue);
			}
	
			if ($text != "") {
				$text = trim(str_replace(array("\n", "\r"), array(" ", " "), $text));
	
				while (strpos($text, "  "))
					$text = trim(str_replace("  ", " ", $text));
	
				$siteinfo["text"] = html_entity_decode(substr($text,0,350), ENT_QUOTES, "UTF-8").'...';
			}
		}
	
		return($siteinfo);
	}
	

	private static function arr_add_hashes(&$item,$k) {
		if (substr($item,0,1) !== '#') {
			$item = '#' . $item;
		}
	}

}
