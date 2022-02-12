<?php
use Zotlabs\Lib\Head;
    
Head::add_css('/library/tiptip/tipTip.css');
Head::add_css('/library/jgrowl/jquery.jgrowl.css');
Head::add_css('/library/jRange/jquery.range.css');

Head::add_css('/view/css/conversation.css');
Head::add_css('/view/css/widgets.css');
Head::add_css('/view/css/colorbox.css');
Head::add_css('/library/justifiedGallery/justifiedGallery.min.css');

Head::add_js('jquery.js');
Head::add_js('/library/justifiedGallery/jquery.justifiedGallery.min.js');
Head::add_js('/library/sprintf.js/dist/sprintf.min.js');

Head::add_js('/library/textcomplete/textcomplete.min.js');
Head::add_js('autocomplete.js');

Head::add_js('/library/jquery.timeago.js');
Head::add_js('/library/readmore.js/readmore.js');
Head::add_js('/library/sticky-kit/sticky-kit.min.js');
Head::add_js('/library/jgrowl/jquery.jgrowl.min.js');

Head::add_js('/library/sjcl/sjcl.js');

Head::add_js('acl.js');
Head::add_js('webtoolkit.base64.js');
Head::add_js('main.js');
Head::add_js('crypto.js');
Head::add_js('/library/jRange/jquery.range.js');
Head::add_js('/library/colorbox/jquery.colorbox-min.js');

Head::add_js('/library/jquery.AreYouSure/jquery.are-you-sure.js');
Head::add_js('/library/tableofcontents/jquery.toc.js');
Head::add_js('/library/imagesloaded/imagesloaded.pkgd.min.js');
/**
 * Those who require this feature will know what to do with it.
 * Those who don't, won't.
 * Eventually this functionality needs to be provided by a module
 * such that permissions can be enforced. At the moment it's
 * more of a proof of concept; but sufficient for our immediate needs.
 */

$channel = App::get_channel();
if($channel && file_exists($channel['channel_address'] . '.js'))
	Head::add_js('/' . $channel['channel_address'] . '.js');
