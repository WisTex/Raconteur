<?php

namespace Zotlabs\Lib;
use DomDocument;

/**
 *  SVGSantiizer
 * 
 *  Whitelist-based PHP SVG sanitizer.
 * 
 *  @link https://github.com/alister-/SVG-Sanitizer}
 *  @author Alister Norris
 *  @copyright Copyright (c) 2013 Alister Norris
 *  @license http://opensource.org/licenses/mit-license.php The MIT License
 *  @package svgsanitizer
 */

class SvgSanitizer {
	
	private $xmlDoc;				// PHP XML DOMDocument

	private $removedattrs = [];

	private static $allowed_functions = [ 'matrix', 'url', 'translate', 'rgb' ];

	// defines the whitelist of elements and attributes allowed.
	private static $whitelist = [
		'a' => [ 'class', 'clip-path', 'clip-rule', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'id', 'mask', 'opacity', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform', 'href', 'xlink:href', 'xlink:title' ],
		'circle' => [ 'class', 'clip-path', 'clip-rule', 'cx', 'cy', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'id', 'mask', 'opacity', 'r', 'requiredFeatures', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform' ],
		'clipPath' => [ 'class', 'clipPathUnits', 'id' ],
		'defs' => [ ],
	    'style' => [ 'type' ],
		'desc' => [ ],
		'ellipse' => [ 'class', 'clip-path', 'clip-rule', 'cx', 'cy', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'id', 'mask', 'opacity', 'requiredFeatures', 'rx', 'ry', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform' ],
		'feGaussianBlur' => [ 'class', 'color-interpolation-filters', 'id', 'requiredFeatures', 'stdDeviation' ],
		'filter' => [ 'class', 'color-interpolation-filters', 'filterRes', 'filterUnits', 'height', 'id', 'primitiveUnits', 'requiredFeatures', 'width', 'x', 'xlink:href', 'y' ],
		'foreignObject' => [ 'class', 'font-size', 'height', 'id', 'opacity', 'requiredFeatures', 'style', 'transform', 'width', 'x', 'y' ],
		'g' => [ 'class', 'clip-path', 'clip-rule', 'id', 'display', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'mask', 'opacity', 'requiredFeatures', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform', 'font-family', 'font-size', 'font-style', 'font-weight', 'text-anchor' ],
		'image' => [ 'class', 'clip-path', 'clip-rule', 'filter', 'height', 'id', 'mask', 'opacity', 'requiredFeatures', 'style', 'systemLanguage', 'transform', 'width', 'x', 'xlink:href', 'xlink:title', 'y' ],
		'line' => [ 'class', 'clip-path', 'clip-rule', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'id', 'marker-end', 'marker-mid', 'marker-start', 'mask', 'opacity', 'requiredFeatures', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform', 'x1', 'x2', 'y1', 'y2' ],
		'linearGradient' => [ 'class', 'id', 'gradientTransform', 'gradientUnits', 'requiredFeatures', 'spreadMethod', 'systemLanguage', 'x1', 'x2', 'xlink:href', 'y1', 'y2' ],
		'marker' => [ 'id', 'class', 'markerHeight', 'markerUnits', 'markerWidth', 'orient', 'preserveAspectRatio', 'refX', 'refY', 'systemLanguage', 'viewBox' ],
		'mask' => [ 'class', 'height', 'id', 'maskContentUnits', 'maskUnits', 'width', 'x', 'y' ],
		'metadata' => [ 'class', 'id' ],
		'path' => [ 'class', 'clip-path', 'clip-rule', 'd', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'id', 'marker-end', 'marker-mid', 'marker-start', 'mask', 'opacity', 'requiredFeatures', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform' ],
		'pattern' => [ 'class', 'height', 'id', 'patternContentUnits', 'patternTransform', 'patternUnits', 'requiredFeatures', 'style', 'systemLanguage', 'viewBox', 'width', 'x', 'xlink:href', 'y' ],
		'polygon' => [ 'class', 'clip-path', 'clip-rule', 'id', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'id', 'class', 'marker-end', 'marker-mid', 'marker-start', 'mask', 'opacity', 'points', 'requiredFeatures', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform' ],
		'polyline' => [ 'class', 'clip-path', 'clip-rule', 'id', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'marker-end', 'marker-mid', 'marker-start', 'mask', 'opacity', 'points', 'requiredFeatures', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform' ],
		'radialGradient' => [ 'class', 'cx', 'cy', 'fx', 'fy', 'gradientTransform', 'gradientUnits', 'id', 'r', 'requiredFeatures', 'spreadMethod', 'systemLanguage', 'xlink:href' ],
		'rect' => [ 'class', 'clip-path', 'clip-rule', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'height', 'id', 'mask', 'opacity', 'requiredFeatures', 'rx', 'ry', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform', 'width', 'x', 'y' ],
		'stop' => [ 'class', 'id', 'offset', 'requiredFeatures', 'stop-color', 'stop-opacity', 'style', 'systemLanguage' ],
		'svg' => [ 'class', 'clip-path', 'clip-rule', 'filter', 'id', 'height', 'mask', 'preserveAspectRatio', 'requiredFeatures', 'style', 'systemLanguage', 'viewBox', 'width', 'x', 'xmlns', 'xmlns:se', 'xmlns:xlink', 'y' ],
		'switch' => [ 'class', 'id', 'requiredFeatures', 'systemLanguage' ],
		'symbol' => [ 'class', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'font-family', 'font-size', 'font-style', 'font-weight', 'id', 'opacity', 'preserveAspectRatio', 'requiredFeatures', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform', 'viewBox' ],
		'text' => [ 'class', 'clip-path', 'clip-rule', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'font-family', 'font-size', 'font-style', 'font-weight', 'id', 'mask', 'opacity', 'requiredFeatures', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'text-anchor', 'transform', 'x', 'xml:space', 'y' ],
		'textPath' => [ 'class', 'id', 'method', 'requiredFeatures', 'spacing', 'startOffset', 'style', 'systemLanguage', 'transform', 'xlink:href' ],
		'title' => [ ],
		'tspan' => [ 'class', 'clip-path', 'clip-rule', 'dx', 'dy', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'font-family', 'font-size', 'font-style', 'font-weight', 'id', 'mask', 'opacity', 'requiredFeatures', 'rotate', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'text-anchor', 'textLength', 'transform', 'x', 'xml:space', 'y' ],
		'use' => [ 'class', 'clip-path', 'clip-rule', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'height', 'id', 'mask', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'transform', 'width', 'x', 'xlink:href', 'y' ],
	];

	function __construct() {
		$this->xmlDoc = new DOMDocument('1.0','UTF-8');
		$this->xmlDoc->preserveWhiteSpace = false;
		libxml_use_internal_errors(true);
	}

	// load XML SVG
	function load($file) {
		$this->xmlDoc->load($file);
	}

	function loadXML($str) {
		if (! $this->xmlDoc->loadXML($str)) {
			logger('loadxml: ' . print_r(libxml_get_errors(),true), LOGGER_DEBUG);
			return false;
		}
		return true;
	}

	function sanitize()
	{
		// all elements in xml doc
		$allElements = $this->xmlDoc->getElementsByTagName('*');

		// loop through all elements
		for($i = 0; $i < $allElements->length; $i++)
		{
			$this->removedattrs = [];
			
			$currentNode = $allElements->item($i);

			// logger('current_node: ' . print_r($currentNode,true));

			// array of allowed attributes in specific element
			$whitelist_attr_arr = self::$whitelist[$currentNode->tagName];

			// does element exist in whitelist?
		    if(isset($whitelist_attr_arr)) {
					$total = $currentNode->attributes->length;
					
		    		for($x = 0; $x < $total; $x++) {

		    			// get attributes name
		    			$attrName = $currentNode->attributes->item($x)->nodeName;

						// logger('checking: ' . print_r($currentNode->attributes->item($x),true));
						$matches = false;
						
		    			// check if attribute isn't in whitelist
		    			if(! in_array($attrName, $whitelist_attr_arr)) {
							$this->removedattrs[] = $attrName;
		    			}
						// check for disallowed functions
						elseif (preg_match_all('/([a-zA-Z0-9]+)[\s]*\(/',
							$currentNode->attributes->item($x)->textContent,$matches,PREG_SET_ORDER)) {
							if ($attrName === 'text') {
								continue;
							}
							foreach ($matches as $match) {
								if(! in_array($match[1],self::$allowed_functions)) {
									logger('queue_remove_function: ' . $match[1],LOGGER_DEBUG);
									$this->removedattrs[] = $attrName;
								}
							}
						}
		    		}	
					if ($this->removedattrs) {
						foreach ($this->removedattrs as $attr) {
							$currentNode->removeAttribute($attr);
							logger('removed: ' . $attr, LOGGER_DEBUG);
						}
					}

			}

		    // else remove element
		    else {
				logger('remove_node: ' . print_r($currentNode,true));
		        $currentNode->parentNode->removeChild($currentNode);
		    }	
		}
		return true;
	}

	function saveSVG() {
		$this->xmlDoc->formatOutput = true;
		return($this->xmlDoc->saveXML());
	}
}
