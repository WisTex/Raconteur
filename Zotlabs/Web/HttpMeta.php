<?php

namespace Zotlabs\Web;


class HttpMeta {

	private $vars = null;
	private $og   = null;

	function __construct() {

		$this->vars = [];
		$this->og   = [];

	}

	function set($property,$value) {
		if (strpos($property,'og:') === 0) {
			$this->og[$property] = $value;
		}
		else {
			$this->vars[$property] = $value;
		}
	}

	function check_required() {
		if (
			($this->og) 
			&& array_key_exists('og:title',$this->og) 
			&& array_key_exists('og:type', $this->og) 
			&& array_key_exists('og:image',$this->og) 
			&& array_key_exists('og:url:secure_url',  $this->og)
                        && array_key_exists('og:description',  $this->og)
		) {
			return true;
		}
		return false;
	}

	function get_field($field) {
		if (strpos($field,'og:') === 0) {
			$arr = $this->og;
		}
		else {
			$arr = $this->vars;
		}
		
		if ($arr && array_key_exists($field,$arr) && $arr[$field]) {
			return $arr[$field];
		}
		return false;
	}


	function get() {
		// use 'name' for most meta fields, and 'property' for opengraph properties
		$o = '';
		if ($this->vars) {
			foreach ($this->vars as $k => $v) {
				$o .= '<meta name="' . htmlspecialchars($k,ENT_COMPAT,'UTF-8',false) . '" content="' . htmlspecialchars($v,ENT_COMPAT,'UTF-8',false) . '" />' . "\r\n" ;
			}
		}
		if ($this->check_required()) {
			foreach ($this->og as $k => $v) {
				$o .= '<meta property="' . htmlspecialchars($k,ENT_COMPAT,'UTF-8',false) . '" content="' . htmlspecialchars($v,ENT_COMPAT,'UTF-8',false) . '" />' . "\r\n" ;
			}
		}
		if ($o) {
			return "\r\n" . $o;
		}
		return $o;
	}

}
