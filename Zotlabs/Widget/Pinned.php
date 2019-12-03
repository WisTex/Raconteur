<?php
namespace Zotlabs\Widget;

/*
 * Show pinned content
 *
 */

use App;

class Pinned {

	private $allowed_types = 0;
	private $uid = 0;


	/*
	 * @brief Displays pinned items
	 *
	 */
	 
	function widget($args) {

		$ret = '';

		$this->uid = intval($uid);
		if(! $this->uid)
			return $ret;

		$this->allowed_types = get_config('system', 'pin_types', [ ITEM_TYPE_POST ]);

		$id_list = $this->list($types);

		if(empty($id_list))
			return $ret;

		$ret['ids'] = array_column($id_list, 'id');

		$observer = App::get_observer();

		foreach($id_list as $item) {
			
			$author = channelx_by_hash($item['author_xchan']);
			$owner = channelx_by_hash($item['owner_xchan']);
			
			$profile_avatar = $author['xchan_photo_m'];
			$profile_link = chanlink_hash($item['author_xchan']);
			$profile_name = $author['xchan_name'];

			$commentable = ($item['item_commnet'] == 0 && $item['comments_closed'] == NULL_DATE ? true : false);

			$location = format_location($item);
			$isevent = false;
			$attend = null;
			$canvote = false;
	
			if($item['obj_type'] === ACTIVITY_OBJ_EVENT) {
				$response_verbs[] = 'attendyes';
				$response_verbs[] = 'attendno';
				$response_verbs[] = 'attendmaybe';
				if($commentable && $observer) {
					$isevent = true;
					$attend = array( t('I will attend'), t('I will not attend'), t('I might attend'));
				}
			}
			
			$consensus = (intval($item['item_consensus']) ? true : false);
			if($consensus) {
				$response_verbs[] = 'agree';
				$response_verbs[] = 'disagree';
				$response_verbs[] = 'abstain';
				if($commentable && $observer) {
					$conlabels = array( t('I agree'), t('I disagree'), t('I abstain'));
					$canvote = true;
				}
			}
			
			$verified = (intval($item['item_verified']) ? t('Message signature validated') : '');
			$forged = ((! intval($item['item_verified']) && $item['sig']) ? t('Message signature incorrect') : '');
			
			$shareable = ((local_channel() && $item['item_private'] != 1) ? true : false);
			if ($shareable) {
				// This actually turns out not to be possible in some protocol stacks without opening up hundreds of new issues.
				// Will allow it only for uri resolvable sources.
				if(strpos($item['mid'],'http') === 0) {
					$share = []; //Not yet ready for primetime
					//$share = array( t('Repeat This'), t('repeat'));
				}
				$embed = array( t('Share This'), t('share'));
			}
			
			if(strcmp(datetime_convert('UTC','UTC',$item['created']),datetime_convert('UTC','UTC','now - 12 hours')) > 0)
				$is_new = true;

			$body = prepare_body($item,true);
			
			$str = [
				'item_type'		=> intval($item['item_type']),			
				'body'			=> $body['html'],
				'tags'			=> $body['tags'],
				'categories'	=> $body['categories'],
				'mentions'		=> $body['mentions'],
				'attachments'	=> $body['attachments'],
				'folders'		=> $body['folders'],
				'text'			=> strip_tags($body['html']),
				'id'			=> $item['id'],
				'mids'			=> json_encode([ 'b64.' . base64url_encode($item['mid']) ]),
				'isevent'		=> $isevent,
				'attend'		=> $attend,
				'consensus'		=> $consensus,
				'conlabels'		=> $conlabels,
				'canvote'		=> $canvote,
				'linktitle' 	=> sprintf( t('View %s\'s profile - %s'), $profile_name, ($author['xchan_addr'] ? $author['xchan_addr'] : $author['xchan_url']) ),
				'olinktitle' 	=> sprintf( t('View %s\'s profile - %s'), $owner['xchan_name'], ($owner['xchan_addr'] ? $owner['xchan_addr'] : $owner['xchan_url']) ),
				'profile_url' 	=> $profile_link,
				'name' 			=> $profile_name,
				'thumb'			=> $profile_avatar,
				'via'			=> t('via'),
				'title'			=> $item['title'],
				'title_tosource' => get_pconfig($item['uid'],'system','title_tosource'),
				'ago'			=> relative_date($item['created']),
				'app' 			=> $item['app'],
				'str_app' 		=> sprintf( t('from %s'), $item['app'] ),
				'isotime' 		=> datetime_convert('UTC', date_default_timezone_get(), $item['created'], 'c'),
				'localtime' 	=> datetime_convert('UTC', date_default_timezone_get(), $item['created'], 'r'),
				'editedtime' 	=> (($item['edited'] != $item['created']) ? sprintf( t('last edited: %s'), datetime_convert('UTC', date_default_timezone_get(), $item['edited'], 'r') ) : ''),
				'expiretime' 	=> ($item['expires'] > NULL_DATE ? sprintf( t('Expires: %s'), datetime_convert('UTC', date_default_timezone_get(), $item['expires'], 'r') ) : ''),
				'lock' 			=> $lock,
				'verified' 		=> $verified,
				'forged' 		=> $forged,
				'location' 		=> $location,
				'divider' 		=> get_pconfig($item['uid'],'system','item_divider'),
				'attend_title' 	=> t('Attendance Options'),
				'vote_title' 	=> t('Voting Options'),
				'is_new' 		=> $is_new,
				'owner_url' 	=> ($owner['xchan_addr'] != $author['xchan_addr'] ? chanlink_hash($owner['xchan_hash']) : ''),
				'owner_photo' 	=> $owner['xchan_photo_m'],
				'owner_name' 	=> $owner['xchan_name'],
				'photo' 		=> $body['photo'],
				'event' 		=> $body['event'],
				'has_tags' 		=> (($body['tags'] || $body['categories'] || $body['mentions'] || $body['attachments'] || $body['folders']) ? true : false),
				// Item toolbar buttons
				'share'     	=> $share,
				'embed'			=> $embed,
				'plink'			=> get_plink($item),
				'pinned'    	=> t('Pinned post')
				// end toolbar buttons
			];
			
			$tpl = get_markup_template('pinned_item.tpl');			
			$ret['html'] .= replace_macros($tpl, $str);
		}

		return $ret;
	}

	
	/*
	 * @brief List pinned items depend on type
	 *
	 * @param $types
	 * @return array of pinned items
	 *
	 */	
	private function list($types) {

		if(empty($types) || (! is_array($types)))
			return [];
		
		$item_types = array_intersect($this->allowed_types, $types);
		if(empty($item_types))
			return [];
		
		$mids_list = [];
		
		foreach($item_types as $type) {
		
			$mids = get_pconfig($this->uid, 'pinned', $type, []);
			foreach($mids as $mid) {
				if(! empty($mid) && strpos($mid,'b64.') === 0)
					$mids_list[] = @base64url_decode(substr($mid,4));
			}
		}
		if(empty($mids_list))
			return [];
		
		$r = q("SELECT * FROM item WHERE mid IN ( '%s' ) AND uid = %d AND id = parent AND item_private = 0 ORDER BY created DESC",
			dbesc(implode(",", $mids_list)),
			intval($this->uid)
		);
		if($r)
			return $r;
		
		return [];
	}
}
