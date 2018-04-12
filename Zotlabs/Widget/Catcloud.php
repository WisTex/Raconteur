<?php

namespace Zotlabs\Widget;

class Catcloud {

	function widget($arr) {

		if((! \App::$profile['profile_uid']) || (! \App::$profile['channel_hash']))
			return '';

		$limit = ((array_key_exists('limit',$arr)) ? intval($arr['limit']) : 50);

		if(array_key_exists('type',$arr)) {
			switch($arr['type']) {

				case 'cards':

					if(! perm_is_allowed(\App::$profile['profile_uid'], get_observer_hash(), 'view_pages'))
						return '';

					return card_catblock(\App::$profile['profile_uid'], $limit, '', \App::$profile['channel_hash']);

				case 'articles':
			
					if(! perm_is_allowed(\App::$profile['profile_uid'], get_observer_hash(), 'view_pages'))
						return '';

					return article_catblock(\App::$profile['profile_uid'], $limit, '', \App::$profile['channel_hash']);


				default:
					break;
			}
		}


		if(! perm_is_allowed(\App::$profile['profile_uid'], get_observer_hash(), 'view_stream'))
			return '';

		return catblock(\App::$profile['profile_uid'], $limit, '', \App::$profile['channel_hash']);


	}

}
