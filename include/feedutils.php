<?php
/**
 * @file include/feedutils.php
 * @brief Some functions to work with XML feeds.
 */

/**
 * @brief Return an Atom feed for channel.
 *
 * @see get_feed_for()
 *
 * @param array $channel
 * @param array $params associative array which configures the feed
 * @return string with an atom feed
 */
function get_public_feed($channel, $params) {

	if(! $params)
		$params = [];

	$params['type']        = ((x($params,'type'))     ? $params['type']           : 'xml');
	$params['begin']       = ((x($params,'begin'))    ? $params['begin']          : NULL_DATE);
	$params['end']         = ((x($params,'end'))      ? $params['end']            : datetime_convert('UTC','UTC','now'));
	$params['start']       = ((x($params,'start'))    ? $params['start']          : 0);
	$params['records']     = ((x($params,'records'))  ? $params['records']        : 40);
	$params['direction']   = ((x($params,'direction'))? $params['direction']      : 'desc');
	$params['pages']       = ((x($params,'pages'))    ? intval($params['pages'])  : 0);
	$params['top']         = ((x($params,'top'))      ? intval($params['top'])    : 0);
	$params['cat']         = ((x($params,'cat'))      ? $params['cat']            : '');
	$params['compat']      = ((x($params,'compat'))   ? intval($params['compat']) : 0);


	switch($params['type']) {
		case 'json':
			header("Content-type: application/atom+json");
			break;
		case 'xml':
		default:
			header("Content-type: application/atom+xml");
			break;
	}

	return get_feed_for($channel, get_observer_hash(), $params);
}

/**
 * @brief Create an atom feed for $channel from template.
 *
 * @param array $channel
 * @param string $observer_hash xchan_hash from observer
 * @param array $params
 * @return string with an atom feed
 */
function get_feed_for($channel, $observer_hash, $params) {

	if(! $channel)
		http_status_exit(401);


	// logger('params: ' . print_r($params,true));


	$interactive = ((is_array($params) && array_key_exists('interactive',$params)) ? intval($params['interactive']) : 0);


	if($params['pages']) {
		if(! perm_is_allowed($channel['channel_id'],$observer_hash,'view_pages')) {
			if($interactive) {
				return '';
			}
			else {
				http_status_exit(403);
			}
		}
	}
	else {
		if(! perm_is_allowed($channel['channel_id'],$observer_hash,'view_stream')) {
			if($interactive) {
				return '';
			}
			else {
				http_status_exit(403);
			}
		}
	}


	$feed_template = get_markup_template('atom_feed.tpl');

	$atom = '';

	$feed_author = '';
	if(intval($params['compat']) === 1) {
		$feed_author = atom_render_author('author',$channel);
	}

	$owner = atom_render_author('zot:owner',$channel);

	$atom .= replace_macros($feed_template, array(
		'$version'       => xmlify(Zotlabs\Lib\System::get_project_version()),
		'$generator'     => xmlify(Zotlabs\Lib\System::get_platform_name()),
		'$generator_uri' => 'https://codeberg.org/zot/zap',
		'$feed_id'       => xmlify($channel['xchan_url']),
		'$feed_title'    => xmlify($channel['channel_name']),
		'$feed_updated'  => xmlify(datetime_convert('UTC', 'UTC', 'now', ATOM_TIME)),
		'$author'        => $feed_author,
		'$owner'         => $owner,
		'$profile_page'  => xmlify($channel['xchan_url']),
	));


	$x = [
			'xml' => $atom,
			'channel' => $channel,
			'observer_hash' => $observer_hash,
			'params' => $params
	];
	/**
	 * @hooks atom_feed_top
	 *   * \e string \b xml - the generated feed and what will get returned from the hook
	 *   * \e array \b channel
	 *   * \e string \b observer_hash
	 *   * \e array \b params
	 */
	call_hooks('atom_feed_top', $x);

	$atom = $x['xml'];

	/**
	 * @hooks atom_feed
	 *   A much simpler interface than atom_feed_top.
	 *   * \e string - the feed after atom_feed_top hook
	 */
	call_hooks('atom_feed', $atom);

	$items = items_fetch(
		[
			'wall'       => '1',
			'datequery'  => $params['end'],
			'datequery2' => $params['begin'],
			'start'      => intval($params['start']),
			'records'    => intval($params['records']),
			'direction'  => dbesc($params['direction']),
			'pages'      => $params['pages'],
			'order'      => dbesc('post'),
			'top'        => $params['top'],
			'cat'        => $params['cat'],
			'compat'     => $params['compat']
		], $channel, $observer_hash, CLIENT_MODE_NORMAL, App::$module
	);

	if($items) {
		$type = 'html';
		foreach($items as $item) {
			if($item['item_private'])
				continue;

			$atom .= atom_entry($item, $type, null, $owner, true, '', $params['compat']);
		}
	}

	/**
	 * @hooks atom_feed_end
	 *   \e string - The created XML feed as a string without closing tag
	 */
	call_hooks('atom_feed_end', $atom);

	$atom .= '</feed>' . "\r\n";

	return $atom;
}

/**
 * @brief Return the verb for an item, or fall back to ACTIVITY_POST.
 *
 * @param array $item an associative array with
 *   * \e string \b verb
 * @return string item's verb if set, default ACTIVITY_POST see boot.php
 */
function construct_verb($item) {
	if ($item['verb'])
		return $item['verb'];

	return ACTIVITY_POST;
}

function construct_activity_object($item) {

	if($item['obj']) {
		$o = '<as:object>' . "\r\n";
		$r = json_decode($item['obj'],false);

		if(! $r)
			return '';
		if($r->type)
			$o .= '<as:obj_type>' . xmlify($r->type) . '</as:obj_type>' . "\r\n";
		if($r->id)
			$o .= '<id>' . xmlify($r->id) . '</id>' . "\r\n";
		if($r->title)
			$o .= '<title>' . xmlify($r->title) . '</title>' . "\r\n";
		if($r->links) {
			/** @FIXME!! */
			if(substr($r->link,0,1) === '<') {
				$r->link = preg_replace('/\<link(.*?)\"\>/','<link$1"/>',$r->link);
				$o .= $r->link;
			}
			else
				$o .= '<link rel="alternate" type="text/html" href="' . xmlify($r->link) . '" />' . "\r\n";
		}
		if($r->content) {
			$o .= '<content type="html" >' . xmlify(bbcode($r->content)) . '</content>' . "\r\n";
		}
		$o .= '</as:object>' . "\r\n";

		return $o;
	}

	return '';
}

function construct_activity_target($item) {

	if($item['target']) {
		$o = '<as:target>' . "\r\n";
		$r = json_decode($item['target'],false);
		if(! $r)
			return '';
		if($r->type)
			$o .= '<as:obj_type>' . xmlify($r->type) . '</as:obj_type>' . "\r\n";
		if($r->id)
			$o .= '<id>' . xmlify($r->id) . '</id>' . "\r\n";
		if($r->title)
			$o .= '<title>' . xmlify($r->title) . '</title>' . "\r\n";
		if($r->links) {
			/** @FIXME !!! */
			if(substr($r->link,0,1) === '<') {
				if(strstr($r->link,'&') && (! strstr($r->link,'&amp;')))
					$r->link = str_replace('&','&amp;', $r->link);
				$r->link = preg_replace('/\<link(.*?)\"\>/','<link$1"/>',$r->link);
				$o .= $r->link;
			}
			else
				$o .= '<link rel="alternate" type="text/html" href="' . xmlify($r->link) . '" />' . "\r\n";
		}
		if($r->content)
			$o .= '<content type="html" >' . xmlify(bbcode($r->content)) . '</content>' . "\r\n";

		$o .= '</as:target>' . "\r\n";

		return $o;
	}

	return '';
}


/**
 * @brief Return a XML tag with author information.
 *
 * @param string $tag The XML tag to create
 * @param string $nick preferred username
 * @param string $name displayed name of the author
 * @param string $uri
 * @param int $h image height
 * @param int $w image width
 * @param string $type profile photo mime type
 * @param string $photo Fully qualified URL to a profile/avator photo
 * @return string XML tag
 */
function atom_author($tag, $nick, $name, $uri, $h, $w, $type, $photo) {
	$o = '';
	if(! $tag)
		return $o;

	$nick = xmlify($nick);
	$name = xmlify($name);
	$uri = xmlify($uri);
	$h = intval($h);
	$w = intval($w);
	$photo = xmlify($photo);

	$o .= "<$tag>\r\n";
	$o .= "  <id>$uri</id>\r\n";
	$o .= "  <name>$nick</name>\r\n";
	$o .= "  <uri>$uri</uri>\r\n";
	$o .= '  <link rel="photo"  type="' . $type . '" media:width="' . $w . '" media:height="' . $h . '" href="' . $photo . '" />' . "\r\n";
	$o .= '  <link rel="avatar" type="' . $type . '" media:width="' . $w . '" media:height="' . $h . '" href="' . $photo . '" />' . "\r\n";
	$o .= '  <poco:preferredUsername>' . $nick . '</poco:preferredUsername>' . "\r\n";
	$o .= '  <poco:displayName>' . $name . '</poco:displayName>' . "\r\n";

	/**
	 * @hooks atom_author
	 *  Possibility to add further tags to returned XML string
	 *   * \e string - The created XML tag as a string without closing tag
	 */
	call_hooks('atom_author', $o);

	$o .= "</$tag>\r\n";

	return $o;
}


/**
 * @brief Return an atom tag with author information from an xchan.
 *
 * @param string $tag
 * @param array $xchan
 * @return string
 */
function atom_render_author($tag, $xchan) {

	$nick = xmlify(substr($xchan['xchan_addr'], 0, strpos($xchan['xchan_addr'], '@')));
	$id   = xmlify($xchan['xchan_url']);
	$name = xmlify($xchan['xchan_name']);
	$photo = xmlify($xchan['xchan_photo_l']);
	$type = xmlify($xchan['xchan_photo_mimetype']);
	$w = $h = 300;

	$o = "<$tag>\r\n";
	$o .= "  <as:object-type>http://activitystrea.ms/schema/1.0/person</as:object-type>\r\n";
	$o .= "  <id>$id</id>\r\n";
	$o .= "  <name>$nick</name>\r\n";
	$o .= "  <uri>$id</uri>\r\n";
	$o .= '  <link rel="alternate" type="text/html" href="' . $id . '" />' . "\r\n";
	$o .= '  <link rel="photo"  type="' . $type . '" media:width="' . $w . '" media:height="' . $h . '" href="' . $photo . '" />' . "\r\n";
	$o .= '  <link rel="avatar" type="' . $type . '" media:width="' . $w . '" media:height="' . $h . '" href="' . $photo . '" />' . "\r\n";
	$o .= '  <poco:preferredUsername>' . $nick . '</poco:preferredUsername>' . "\r\n";
	$o .= '  <poco:displayName>' . $name . '</poco:displayName>' . "\r\n";

	/**
	 * @hooks atom_render_author
	 *   Possibility to add further tags to returned XML string.
	 *   * \e string The created XML tag as a string without closing tag
	 */
	call_hooks('atom_render_author', $o);

	$o .= "</$tag>\r\n";

	return $o;
}

function compat_photos_list($s) {

	$ret = [];

	$found = preg_match_all('/\[[zi]mg(.*?)\](.*?)\[/ism',$s,$matches,PREG_SET_ORDER);

	if($found) {
		foreach($matches as $match) {
			$entry = [
				'href' => $match[2],
				'type' => guess_image_type($match[2])
			];
			$sizer = new \Zotlabs\Lib\Img_filesize($match[2]);
			$size = $sizer->getSize();
			if(intval($size)) {
				$entry['length'] = intval($size);
			}

			$ret[] = $entry;
		}
	}

	return $ret;
}


/**
 * @brief Create an item for the Atom feed.
 *
 * @see get_feed_for()
 *
 * @param array $item
 * @param string $type
 * @param array $author
 * @param array $owner
 * @param string $comment default false
 * @param number $cid default 0
 * @param boolean $compat default false
 * @return void|string
 */
function atom_entry($item, $type, $author, $owner, $comment = false, $cid = 0, $compat = false) {

	if(! $item['parent'])
		return;

	if($item['deleted'])
		return '<at:deleted-entry ref="' . xmlify($item['mid']) . '" when="' . xmlify(datetime_convert('UTC','UTC',$item['edited'] . '+00:00',ATOM_TIME)) . '" />' . "\r\n";

	create_export_photo_body($item);

	// provide separate summary and content unless compat is true; as summary represents a content-warning on some networks

	$matches = false;
	if(preg_match('|\[summary\](.*?)\[/summary\]|ism',$item['body'],$matches))
		$summary = $matches[1];
	else
		$summary = '';

	$body = $item['body'];

	if($summary) 
		$body = preg_replace('|^(.*?)\[summary\](.*?)\[/summary\](.*?)$|ism','$1$3',$item['body']);

	if($compat)
		$summary = '';

	if($item['allow_cid'] || $item['allow_gid'] || $item['deny_cid'] || $item['deny_gid'])
		$body = fix_private_photos($body,$owner['uid'],$item,$cid);

	if($compat) {
		$compat_photos = compat_photos_list($body);
	}
	else {
		$compat_photos = null;
	}

	$o = "\r\n\r\n<entry>\r\n";

	if(is_array($author)) {
		$o .= atom_render_author('author',$author);
	}
	else {
		$o .= atom_render_author('author',$item['author']);
	}

	$o .= atom_render_author('zot:owner',$item['owner']);

	if(($item['parent'] != $item['id']) || ($item['parent_mid'] !== $item['mid']) || (($item['thr_parent'] !== '') && ($item['thr_parent'] !== $item['mid']))) {
		$parent_item = (($item['thr_parent']) ? $item['thr_parent'] : $item['parent_mid']);
		// ensure it's a legal uri and not just a message-id
		if(! strpos($parent_item,':'))
			$parent_item = 'X-ZOT:' . $parent_item;

		$o .= '<thr:in-reply-to ref="' . xmlify($parent_item) . '" type="text/html" href="' .  xmlify($item['plink']) . '" />' . "\r\n";
	}

	if(activity_match($item['obj_type'],ACTIVITY_OBJ_EVENT) && activity_match($item['verb'],ACTIVITY_POST)) {
		$obj = ((is_array($item['obj'])) ? $item['obj'] : json_decode($item['obj'],true));

		$o .= '<title>' . xmlify($item['title']) . '</title>' . "\r\n";
		$o .= '<summary xmlns="urn:ietf:params:xml:ns:xcal">' . xmlify(bbcode($obj['title'])) . '</summary>' . "\r\n";
		$o .= '<dtstart xmlns="urn:ietf:params:xml:ns:xcal">' . datetime_convert('UTC','UTC', $obj['dtstart'],'Ymd\\THis' . (($obj['adjust']) ? '\\Z' : '')) .  '</dtstart>' . "\r\n";
		$o .= '<dtend xmlns="urn:ietf:params:xml:ns:xcal">' . datetime_convert('UTC','UTC', $obj['dtend'],'Ymd\\THis' . (($obj['adjust']) ? '\\Z' : '')) .  '</dtend>' . "\r\n";
		$o .= '<location xmlns="urn:ietf:params:xml:ns:xcal">' . ((is_array($obj['location'])) ? xmlify(bbcode($obj['location']['content'])) : xmlify(bbcode($obj['location']))) . '</location>' . "\r\n";
		$o .= '<content type="' . $type . '" >' . xmlify(bbcode($obj['description'])) . '</content>' . "\r\n";
	}
	else {
		$o .= '<title>' . xmlify($item['title']) . '</title>' . "\r\n";
		if($summary)
			$o .= '<summary type="' . $type . '" >' . xmlify(prepare_text($summary,$item['mimetype'])) . '</summary>' . "\r\n";
		$o .= '<content type="' . $type . '" >' . xmlify(prepare_text($body,$item['mimetype'])) . '</content>' . "\r\n";
	}

	$o .= '<id>' . xmlify($item['mid']) . '</id>' . "\r\n";
	$o .= '<published>' . xmlify(datetime_convert('UTC','UTC',$item['created'] . '+00:00',ATOM_TIME)) . '</published>' . "\r\n";
	$o .= '<updated>' . xmlify(datetime_convert('UTC','UTC',$item['edited'] . '+00:00',ATOM_TIME)) . '</updated>' . "\r\n";

	$o .= '<link rel="alternate" type="text/html" href="' . xmlify($item['plink']) . '" />' . "\r\n";

	if($item['location']) {
		$o .= '<zot:location>' . xmlify($item['location']) . '</zot:location>' . "\r\n";
		$o .= '<poco:address><poco:formatted>' . xmlify($item['location']) . '</poco:formatted></poco:address>' . "\r\n";
	}

	if($item['coord'])
		$o .= '<georss:point>' . xmlify($item['coord']) . '</georss:point>' . "\r\n";

	if(($item['item_private']) || strlen($item['allow_cid']) || strlen($item['allow_gid']) || strlen($item['deny_cid']) || strlen($item['deny_gid']))
		$o .= '<zot:private>' . (($item['item_private']) ? $item['item_private'] : 1) . '</zot:private>' . "\r\n";

	if($item['app'])
		$o .= '<statusnet:notice_info local_id="' . $item['id'] . '" source="' . xmlify($item['app']) . '" ></statusnet:notice_info>' . "\r\n";

	$verb = construct_verb($item);
	$o .= '<as:verb>' . xmlify($verb) . '</as:verb>' . "\r\n";
	$actobj = construct_activity_object($item);
	if(strlen($actobj))
		$o .= $actobj;

	$actarg = construct_activity_target($item);
	if(strlen($actarg))
		$o .= $actarg;

	if($item['attach']) {
		$enclosures = json_decode($item['attach'], true);
		if($enclosures) {
			foreach($enclosures as $enc) {
				$o .= '<link rel="enclosure" '
				. (($enc['href']) ? 'href="' . $enc['href'] . '" ' : '')
				. (($enc['length']) ? 'length="' . $enc['length'] . '" ' : '')
				. (($enc['type']) ? 'type="' . $enc['type'] . '" ' : '')
				. ' />' . "\r\n";
			}
		}
	}
	if($compat_photos) {
		foreach($compat_photos as $enc) {
			$o .= '<link rel="enclosure" '
			. (($enc['href']) ? 'href="' . $enc['href'] . '" ' : '')
			. ((array_key_exists('length',$enc)) ? 'length="' . $enc['length'] . '" ' : '')
			. (($enc['type']) ? 'type="' . $enc['type'] . '" ' : '')
			. ' />' . "\r\n";
		}
	}

	if($item['term']) {
		foreach($item['term'] as $term) {
			$scheme = '';
			$label = '';
			switch($term['ttype']) {
				case TERM_UNKNOWN:
					$scheme = NAMESPACE_ZOT . '/term/unknown';
					$label = $term['term'];
					break;
				case TERM_HASHTAG:
				case TERM_COMMUNITYTAG:
					$scheme = NAMESPACE_ZOT . '/term/hashtag';
					$label = '#' . $term['term'];
					break;
				case TERM_MENTION:
					$scheme = NAMESPACE_ZOT . '/term/mention';
					$label = '@' . $term['term'];
					break;
				case TERM_CATEGORY:
					$scheme = NAMESPACE_ZOT . '/term/category';
					$label = $term['term'];
					break;
				default:
					break;
			}
			if(! $scheme)
				continue;

			$o .= '<category scheme="' . $scheme . '" term="' . $term['term'] . '" label="' . $label . '" />' . "\r\n";
		}
	}

	$o .= '</entry>' . "\r\n";

	// build array to pass to hook
	$x = [
		'item'     => $item,
		'type'     => $type,
		'author'   => $author,
		'owner'    => $owner,
		'comment'  => $comment,
		'abook_id' => $cid,
		'entry'    => $o
	];
	/**
	 * @hooks atom_entry
	 *   * \e array \b item
	 *   * \e string \b type
	 *   * \e array \b author
	 *   * \e array \b owner
	 *   * \e string \b comment
	 *   * \e number \b abook_id
	 *   * \e string \b entry - The generated entry and what will get returned
	 */
	call_hooks('atom_entry', $x);

	return $x['entry'];
}

function get_mentions($item,$tags) {
	$o = '';

	if(! count($tags))
		return $o;

	foreach($tags as $x) {
		if($x['ttype'] == TERM_MENTION) {
			$o .= "\t\t" . '<link rel="mentioned" href="' . $x['url'] . '" />' . "\r\n";
			$o .= "\t\t" . '<link rel="ostatus:attention" href="' . $x['url'] . '" />' . "\r\n";
		}
	}
	return $o;
}

/**
 * @brief Return atom link elements for all of our hubs.
 *
 * @return string
 */
function feed_hublinks() {
	$hub = get_config('system', 'huburl');

	$hubxml = '';
	if(strlen($hub)) {
		$hubs = explode(',', $hub);
		if(count($hubs)) {
			foreach($hubs as $h) {
				$h = trim($h);
				if(! strlen($h))
					continue;

				$hubxml .= '<link rel="hub" href="' . xmlify($h) . '" />' . "\n" ;
			}
		}
	}

	return $hubxml;
}

/**
 * @brief Return atom link elements for salmon endpoints
 *
 * @param string $nick
 * @return string
 */
function feed_salmonlinks($nick) {

	$salmon  = '<link rel="salmon" href="' . xmlify(z_root() . '/salmon/' . $nick) . '" />' . "\n" ;

	// old style links that status.net still needed as of 12/2010

	$salmon .= '  <link rel="http://salmon-protocol.org/ns/salmon-replies" href="' . xmlify(z_root() . '/salmon/' . $nick) . '" />' . "\n" ;
	$salmon .= '  <link rel="http://salmon-protocol.org/ns/salmon-mention" href="' . xmlify(z_root() . '/salmon/' . $nick) . '" />' . "\n" ;

	return $salmon;
}

