<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;

// This is the primary endpoint for communicating with Zot directory services.

class Dirsearch extends Controller {

	function init() {
		App::set_pager_itemspage(60);
	}
	
	function get() {
	
		$ret = [ 'success' => false ];
	
		// logger('request: ' . print_r($_REQUEST,true));
	

		if (argc() > 1 && argv(1) === 'sites') {
			$ret = $this->list_public_sites();
			json_return_and_die($ret);
		}

		$dirmode = intval(get_config('system','directory_mode'));


		$network = EMPTY_STR;

		$sql_extra = '';
	
		$tables = [ 'name', 'address', 'xhash', 'locale', 'region', 'postcode',
					'country', 'gender', 'marital', 'sexual', 'keywords' ];

		// parse advanced query if present
		
		if ($_REQUEST['query']) {
			$advanced = $this->dir_parse_query($_REQUEST['query']);
			if ($advanced) {
				foreach ($advanced as $adv) {
					if (in_array($adv['field'],$tables)) {
						if ($adv['field'] === 'name')
							$sql_extra .= $this->dir_query_build($adv['logic'],'xchan_name',$adv['value']);
						elseif ($adv['field'] === 'address')
	 						$sql_extra .= $this->dir_query_build($adv['logic'],'xchan_addr',$adv['value']);
						elseif ($adv['field'] === 'xhash')
	 						$sql_extra .= $this->dir_query_build($adv['logic'],'xchan_hash',$adv['value']);
						else
							$sql_extra .= $this->dir_query_build($adv['logic'],'xprof_' . $adv['field'],$adv['value']);
					}
				}
			}
		}
	
		$hash     = ((x($_REQUEST['hash']))    ? $_REQUEST['hash']     : '');
		$name     = ((x($_REQUEST,'name'))     ? $_REQUEST['name']     : '');
		$url      = ((x($_REQUEST,'url'))      ? $_REQUEST['url']      : '');
		$hub      = ((x($_REQUEST,'hub'))      ? $_REQUEST['hub']      : '');
		$address  = ((x($_REQUEST,'address'))  ? $_REQUEST['address']  : '');
		$locale   = ((x($_REQUEST,'locale'))   ? $_REQUEST['locale']   : '');
		$region   = ((x($_REQUEST,'region'))   ? $_REQUEST['region']   : '');
		$postcode = ((x($_REQUEST,'postcode')) ? $_REQUEST['postcode'] : '');
		$country  = ((x($_REQUEST,'country'))  ? $_REQUEST['country']  : '');
		$gender   = ((x($_REQUEST,'gender'))   ? $_REQUEST['gender']   : '');
		$marital  = ((x($_REQUEST,'marital'))  ? $_REQUEST['marital']  : '');
		$sexual   = ((x($_REQUEST,'sexual'))   ? $_REQUEST['sexual']   : '');
		$keywords = ((x($_REQUEST,'keywords')) ? $_REQUEST['keywords'] : '');
		$agege    = ((x($_REQUEST,'agege'))    ? intval($_REQUEST['agege'])  : 0 );
		$agele    = ((x($_REQUEST,'agele'))    ? intval($_REQUEST['agele'])  : 0 );
		$kw       = ((x($_REQUEST,'kw'))       ? intval($_REQUEST['kw'])     : 0 );
		$active   = ((x($_REQUEST,'active'))   ? intval($_REQUEST['active']) : 0 );
		$type     = ((array_key_exists('type',$_REQUEST)) ? intval($_REQUEST['type']) : 0);

		// allow a site to disable the directory's keyword list
		if (get_config('system','disable_directory_keywords'))
			$kw = 0;
	
		// by default use a safe search
		$safe = ((x($_REQUEST,'safe'))); 
		if ($safe === false) {
			$safe = 1;
		}

		// Directory mirrors will request sync packets, which are lists
		// of records that have changed since the sync datetime.
		
		if (array_key_exists('sync',$_REQUEST)) {
			if ($_REQUEST['sync'])
				$sync = datetime_convert('UTC','UTC',$_REQUEST['sync']);
			else
				$sync = datetime_convert('UTC','UTC','2010-01-01 01:01:00');
		}
		else
			$sync = false;
	
		if (($dirmode == DIRECTORY_MODE_STANDALONE) && (! $hub)) {
			$hub = App::get_hostname();
		}

		if ($hub) {
			$hub_query = " and xchan_hash in (select hubloc_hash from hubloc where hubloc_host =  '" . protect_sprintf(dbesc($hub)) . "') ";
		}
		else {
			$hub_query = '';
		}
		
		if ($url) {
			$r = q("select xchan_name from hubloc left join xchan on hubloc_hash = xchan_hash where hubloc_url = '%s' or hubloc_id_url = '%s'",
				dbesc($url),
				dbesc($url)
			);
			if ($r && $r[0]['xchan_name']) {
				$name = $r[0]['xchan_name'];
			}
		}

		// The order identifier is validated further below
		
		$sort_order  = ((x($_REQUEST,'order')) ? $_REQUEST['order'] : '');


		// parse and assemble the query for advanced searches
		
		$joiner = ' OR ';

		if($_REQUEST['and'])
			$joiner = ' AND ';
	
		if ($name) {
			$sql_extra .= $this->dir_query_build($joiner,'xchan_name',$name);
		}
		if ($address) {
			$sql_extra .= $this->dir_query_build($joiner,'xchan_addr',$address);
		}
		if ($hash) {
			$sql_extra .= $this->dir_query_build($joiner,'xchan_hash',$hash);
		}
		if ($locale) {
			$sql_extra .= $this->dir_query_build($joiner,'xprof_locale',$locale);
		}
		if ($region) {
			$sql_extra .= $this->dir_query_build($joiner,'xprof_region',$region);
		}
		if ($postcode) {
			$sql_extra .= $this->dir_query_build($joiner,'xprof_postcode',$postcode);
		}
		if ($country) {
			$sql_extra .= $this->dir_query_build($joiner,'xprof_country',$country);
		}
		if ($gender) {
			$sql_extra .= $this->dir_query_build($joiner,'xprof_gender',$gender);
		}
		if ($marital) {
			$sql_extra .= $this->dir_query_build($joiner,'xprof_marital',$marital);
		}
		if ($sexual) {
			$sql_extra .= $this->dir_query_build($joiner,'xprof_sexual',$sexual);
		}
		if ($keywords) {
			$sql_extra .= $this->dir_query_build($joiner,'xprof_keywords',$keywords);
		}
		
		// we only support an age range currently. You must set both agege 
		// (greater than or equal) and agele (less than or equal) 
	
		if($agele && $agege) {
			$sql_extra .= " $joiner ( xprof_age <= " . intval($agele) . " ";
			$sql_extra .= " AND  xprof_age >= " . intval($agege) . ") ";
		}
	
		
	    $perpage      = (($_REQUEST['n'])              ? $_REQUEST['n']                    : 60);
	    $page         = (($_REQUEST['p'])              ? intval($_REQUEST['p'] - 1)        : 0);
	    $startrec     = (($page+1) * $perpage) - $perpage;
		$limit        = (($_REQUEST['limit'])          ? intval($_REQUEST['limit'])        : 0);
		$return_total = ((x($_REQUEST,'return_total')) ? intval($_REQUEST['return_total']) : 0);
	
		// mtime is not currently working
	
		$mtime        = ((x($_REQUEST,'mtime'))        ? datetime_convert('UTC','UTC',$_REQUEST['mtime']) : '');
	
		// merge them into xprof
	
		$ret['success'] = true;
	
		// If &limit=n, return at most n entries
		// If &return_total=1, we count matching entries and return that as 'total_items' for use in pagination.
		// By default we return one page (default 60 items maximum) and do not count total entries
	
		$logic = ((strlen($sql_extra)) ? 'false' : 'true');
	
		if ($hash) {
			$logic = 'true';
		}
	
		if ($dirmode == DIRECTORY_MODE_STANDALONE) {
			$sql_extra .= " and xchan_addr like '%%" . App::get_hostname() . "' ";
		}
	
		$safesql = (($safe > 0) ? " and xchan_censored = 0 and xchan_selfcensored = 0 " : '');
		if ($safe < 0) {
			$safesql = " and ( xchan_censored = 1 OR xchan_selfcensored = 1 ) ";
		}
		
		if ($type) {
			$safesql .= " and xchan_type = " . intval($type);
		}

		$activesql = EMPTY_STR;
		
		if ($active) {
			$activesql = "and xchan_updated > '" . datetime_convert(date_default_timezone_get(),'UTC','now - 60 days') . "' ";
		}

		if ($limit) {
			$qlimit = " LIMIT $limit ";
		}
		else {
			$qlimit = " LIMIT " . intval($perpage) . " OFFSET " . intval($startrec);
			if ($return_total) {
				$r = q("SELECT COUNT(xchan_hash) AS total FROM xchan left join xprof on xchan_hash = xprof_hash where $logic $sql_extra $network and xchan_hidden = 0 and xchan_orphan = 0 and xchan_deleted = 0 $safesql $activesql ");
				if ($r) {
					$ret['total_items'] = $r[0]['total'];
				}
			}
		}
	
		if ($sort_order == 'normal') {
			$order = " order by xchan_name asc ";
	
			// Start the alphabetic search at 'A' 
			// This will make a handful of channels whose names begin with
			// punctuation un-searchable in this mode
	
			$safesql .= " and ascii(substring(xchan_name FROM 1 FOR 1)) > 64 ";
		}
		elseif ($sort_order == 'reverse')
			$order = " order by xchan_name desc ";
		elseif ($sort_order == 'reversedate')
			$order = " order by xchan_name_date asc ";
		else	
			$order = " order by xchan_name_date desc ";
	
	
		// normal directory query

		$r = q("SELECT xchan.*, xprof.* from xchan left join xprof on xchan_hash = xprof_hash 
			where ( $logic $sql_extra ) $hub_query $network and xchan_system = 0 and xchan_hidden = 0 and xchan_orphan = 0 and xchan_deleted = 0 
			$safesql $activesql $order $qlimit "
		);

		$ret['page'] = $page + 1;
		$ret['records'] = count($r);		
	
		if ($r) {
	
			$entries = [];
			$dups    = [];
			$isdup   = EMPTY_STR;

			// Collect activitypub identities and query which also have zot6 identities.
			// Do this once per page fetch rather than once per entry.

			foreach ($r as $rv) {
				if ($rv['xchan_network'] === 'activitypub') {
					if ($isdup) {
						$isdup .= ',';
					}
					$isdup .= "'" . dbesc($rv['xchan_url']) . "'";
				}
				if ($isdup) {
					$isdup = protect_sprintf($isdup);
					$z = q("select xchan_url, xchan_hash from xchan where xchan_url in  ( $isdup ) and xchan_network = 'zot6'");
					if ($z) {
						foreach($z as $zv) {
							$dups[$zv['xchan_url']] = $zv['xchan_hash'];
						}
					}
				}
			}
			
			foreach ($r as $rr) {

				// If it's an activitypub record and the channel also has a zot6 address, don't return it.
				
				if (array_key_exists($rr['xchan_url'],$dups)) {
					continue;
				}

				if (! check_siteallowed($rr['xchan_url'])) {
					continue;
				}

				if (! check_channelallowed($rr['xchan_hash'])) {
					continue;
				}


				$entry = [];
		
				$entry['name']         = $rr['xchan_name'];
				$entry['hash']         = $rr['xchan_hash'];
				$entry['censored']     = $rr['xchan_censored'];
				$entry['selfcensored'] = $rr['xchan_selfcensored'];
				$entry['type']         = $rr['xchan_type'];
				$entry['url']          = $rr['xchan_url'];
				$entry['photo_l']      = $rr['xchan_photo_l'];
				$entry['photo']        = $rr['xchan_photo_m'];
				$entry['address']      = $rr['xchan_addr'];
				$entry['network']      = $rr['xchan_network'];
				$entry['description']  = $rr['xprof_desc'];
				$entry['locale']       = $rr['xprof_locale'];
				$entry['region']       = $rr['xprof_region'];
				$entry['postcode']     = $rr['xprof_postcode'];
				$entry['country']      = $rr['xprof_country'];
				$entry['birthday']     = $rr['xprof_dob'];
				$entry['age']          = $rr['xprof_age'];
				$entry['gender']       = $rr['xprof_gender'];
				$entry['marital']      = $rr['xprof_marital'];
				$entry['sexual']       = $rr['xprof_sexual'];
				$entry['about']        = $rr['xprof_about'];
				$entry['homepage']     = $rr['xprof_homepage'];
				$entry['hometown']     = $rr['xprof_hometown'];
				$entry['keywords']     = $rr['xprof_keywords'];
	
				$entries[] = $entry;
	
			}
	
			$ret['results'] = $entries;
			if ($kw) {
				$k = dir_tagadelic($kw, $hub, $type,$safesql);
				if ($k) {
					$ret['keywords'] = [];
					foreach ($k as $kv) {
						$ret['keywords'][] = [ 'term' => $kv[0], 'weight' => $kv[1], 'normalise' => $kv[2] ];
					}
				}
			}
		}		
		json_return_and_die($ret);
	}
	
	function dir_query_build($joiner,$field,$s) {
		$ret = '';
		if (trim($s))
			$ret .= dbesc($joiner) . " " . dbesc($field) . " like '" . protect_sprintf( '%' . dbesc($s) . '%' ) . "' ";
		return $ret;
	}
	
	function dir_flag_build($joiner,$field,$bit,$s) {
		return dbesc($joiner) . " ( " . dbesc($field) . " & " . intval($bit) . " ) " . ((intval($s)) ? '>' : '=' ) . " 0 ";
	}
	
	
	function dir_parse_query($s) {
	
		$ret = [];
		$curr = [];
		$all = explode(' ',$s);
		$quoted_string = false;
	
		if ($all) {
			foreach ($all as $q) {
				if ($quoted_string === false) {
					if ($q === 'and') {
						$curr['logic'] = 'and';
						continue;
					}
					if ($q === 'or') {
						$curr['logic'] = 'or';
						continue;
					}
					if ($q === 'not') {
						$curr['logic'] .= ' not';
						continue;
					}
					if (strpos($q,'=')) {
						if (! isset($curr['logic']))
							$curr['logic'] = 'or';
						$curr['field'] = trim(substr($q,0,strpos($q,'=')));
						$curr['value'] = trim(substr($q,strpos($q,'=')+1));
						if ($curr['value'][0] == '"' && $curr['value'][strlen($curr['value'])-1] != '"') {
							$quoted_string = true;
							$curr['value'] = substr($curr['value'],1);
							continue;
						}
						elseif ($curr['value'][0] == '"' && $curr['value'][strlen($curr['value'])-1] == '"') {
							$curr['value'] = substr($curr['value'],1,strlen($curr['value'])-2);
							$ret[] = $curr;
							$curr = [];
							continue;
						}	
						else {
							$ret[] = $curr;
							$curr = [];
							continue;
						}
					}
				}
				else {
					if ($q[strlen($q)-1] == '"') {
						$curr['value'] .= ' ' . str_replace('"','',trim($q));
						$ret[] = $curr;
						$curr = [];
						$quoted_string = false;
					}
					else
						$curr['value'] .= ' ' . trim($q);
				}
			}
		}
		logger('dir_parse_query:' . print_r($ret,true),LOGGER_DATA);
		return $ret;
	}
	
		
	function list_public_sites() {
	
		$rand = db_getfunc('rand');
		$realm = get_directory_realm();

		$r = q("select * from site where site_type = %d and site_dead = 0",
				intval(SITE_TYPE_ZOT)
		);
			
		$ret = array('success' => false);
	
		if ($r) {
			$ret['success'] = true;
			$ret['sites'] = [];
	
			foreach ($r as $rr) {
				
				if ($rr['site_access'] == ACCESS_FREE)
					$access = 'free';
				elseif ($rr['site_access'] == ACCESS_PAID)
					$access = 'paid';
				elseif ($rr['site_access'] == ACCESS_TIERED)
					$access = 'tiered';
				else
					$access = 'private';
	
				if ($rr['site_register'] == REGISTER_OPEN)
					$register = 'open';
				elseif ($rr['site_register'] == REGISTER_APPROVE)
					$register = 'approve';
				else
					$register = 'closed';
	
				$ret['sites'][] = array('url' => $rr['site_url'], 'access' => $access, 'register' => $register, 'sellpage' => $rr['site_sellpage'], 'location' => $rr['site_location'], 'project' => $rr['site_project'], 'version' => $rr['site_version']);

			}
		}
		return $ret;
	}

}
