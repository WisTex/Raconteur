<?php

namespace Zotlabs\Lib;



class MessageFilter {


	static public function evaluate($item,$incl,$excl) {

		require_once('include/html2plain.php');

		$text = prepare_text($item['body'],((isset($item['mimetype'])) ? $item['mimetype'] : 'text/bbcode'));
		$text = html2plain(($item['title']) ? $item['title'] . ' ' . $text : $text);

		$lang = null;

		if((strpos($incl,'lang=') !== false) || (strpos($excl,'lang=') !== false) || (strpos($incl,'lang!=') !== false) || (strpos($excl,'lang!=') !== false)) {
			$lang = detect_language($text);
		}

		$tags = ((isset($item['term']) && is_array($item['term']) && count($item['term'])) ? $item['term'] : false);

		// exclude always has priority

		$exclude = (($excl) ? explode("\n",$excl) : null);

		if($exclude) {
			foreach($exclude as $word) {
				$word = trim($word);
				if(! $word)
					continue;
				if(substr($word,0,1) === '#' && $tags) {
					foreach($tags as $t)
						if((($t['ttype'] == TERM_HASHTAG) || ($t['ttype'] == TERM_COMMUNITYTAG)) && (($t['term'] === substr($word,1)) || (substr($word,1) === '*')))
							return false;
				}
				elseif(substr($word,0,1) === '$' && $tags) {
					foreach($tags as $t)
						if(($t['ttype'] == TERM_CATEGORY) && (($t['term'] === substr($word,1)) || (substr($word,1) === '*')))
							return false;
				}
				elseif((strpos($word,'/') === 0) && preg_match($word,$text))
					return false;
				elseif((strpos($word,'lang=') === 0) && ($lang) && (strcasecmp($lang,trim(substr($word,5))) == 0))
					return false;
				elseif((strpos($word,'lang!=') === 0) && ($lang) && (strcasecmp($lang,trim(substr($word,6))) != 0))
					return false;
				elseif(stristr($text,$word) !== false)
					return false;
			}
		}

		$include = (($incl) ? explode("\n",$incl) : null);

		if($include) {
			foreach($include as $word) {
				$word = trim($word);
				if(! $word)
					continue;
				if(substr($word,0,1) === '#' && $tags) {
					foreach($tags as $t)
						if((($t['ttype'] == TERM_HASHTAG) || ($t['ttype'] == TERM_COMMUNITYTAG)) && (($t['term'] === substr($word,1)) || (substr($word,1) === '*')))
							return true;
				}
				elseif(substr($word,0,1) === '$' && $tags) {
					foreach($tags as $t)
						if(($t['ttype'] == TERM_CATEGORY) && (($t['term'] === substr($word,1)) || (substr($word,1) === '*')))
							return true;
				}
				elseif((strpos($word,'/') === 0) && preg_match($word,$text))
					return true;
				elseif((strpos($word,'lang=') === 0) && ($lang) && (strcasecmp($lang,trim(substr($word,5))) == 0))
					return true;
				elseif((strpos($word,'lang!=') === 0) && ($lang) && (strcasecmp($lang,trim(substr($word,6))) != 0))
					return true;
				elseif(stristr($text,$word) !== false)
					return true;
			}
		}
		else {
			return true;
		}

		return false;
	}


}
