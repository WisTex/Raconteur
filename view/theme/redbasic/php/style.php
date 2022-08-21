<?php

use Code\Lib\Channel;

if (!App::$install) {

    // Get the UID of the channel owner
    $uid = Channel::get_theme_uid();

    if ($uid) {
        load_pconfig($uid, 'redbasic');
    }

    // Load the owners pconfig
    $nav_bg = get_pconfig($uid, 'redbasic', 'nav_bg');
    $nav_icon_colour = get_pconfig($uid, 'redbasic', 'nav_icon_colour');
    $nav_active_icon_colour = get_pconfig($uid, 'redbasic', 'nav_active_icon_colour');
    $banner_colour = get_pconfig($uid, 'redbasic', 'banner_colour');
    $narrow_navbar = get_pconfig($uid, 'redbasic', 'narrow_navbar');
    $link_colour = get_pconfig($uid, 'redbasic', 'link_colour');
    $schema = get_pconfig($uid, 'redbasic', 'schema');
    $bgcolour = get_pconfig($uid, 'redbasic', 'background_colour');
    $background_image = get_pconfig($uid, 'redbasic', 'background_image');
    $item_colour = get_pconfig($uid, 'redbasic', 'item_colour');
    $comment_item_colour = get_pconfig($uid, 'redbasic', 'comment_item_colour');
    $font_size = get_pconfig($uid, 'redbasic', 'font_size');
    $font_colour = get_pconfig($uid, 'redbasic', 'font_colour');
    $radius = get_pconfig($uid, 'redbasic', 'radius');
    $shadow = get_pconfig($uid, 'redbasic', 'photo_shadow');
    $converse_width = get_pconfig($uid, 'redbasic', 'converse_width');
    $top_photo = get_pconfig($uid, 'redbasic', 'top_photo');
    $reply_photo = get_pconfig($uid, 'redbasic', 'reply_photo');
}

// Now load the scheme.  If a value is changed above, we'll keep the settings
// If not, we'll keep those defined by the schema
// Setting $schema to '' wasn't working for some reason, so we'll check it's
// not --- like the mobile theme does instead.

// Allow layouts to over-ride the schema - used as a filename component so sanitize.

$schema = str_replace(['/', '.'], ['', ''], ((isset($_REQUEST['schema']) && $_REQUEST['schema']) ? $_REQUEST['schema'] : EMPTY_STR));


if (($schema) && ($schema != '---')) {

    // Check it exists, because this setting gets distributed to clones
    if (file_exists('view/theme/redbasic/schema/' . $schema . '.php')) {
        $schemefile = 'view/theme/redbasic/schema/' . $schema . '.php';
        require_once($schemefile);
    }

    if (file_exists('view/theme/redbasic/schema/' . $schema . '.css')) {
        $schemecss = file_get_contents('view/theme/redbasic/schema/' . $schema . '.css');
    }

}

// Allow admins to set a default schema for the hub.
// default.php and default.css MUST be symlinks to existing schema files in view/theme/redbasic/schema
if ((!$schema) || ($schema == '---')) {

    if (file_exists('view/theme/redbasic/schema/default.php')) {
        $schemefile = 'view/theme/redbasic/schema/default.php';
        require_once($schemefile);
    }

    if (file_exists('view/theme/redbasic/schema/default.css')) {
        $schemecss = file_get_contents('view/theme/redbasic/schema/default.css');
    }

}

//Set some defaults - we have to do this after pulling owner settings, and we have to check for each setting
//individually.  If we don't, we'll have problems if a user has set one, but not all options.
if (!(isset($nav_bg) && $nav_bg))
    $nav_bg = '#777';
if (!(isset($nav_icon_colour) && $nav_icon_colour))
    $nav_icon_colour = 'rgba(255, 255, 255, 0.5)';
if (!(isset($nav_active_icon_colour) && $nav_active_icon_colour))
    $nav_active_icon_colour = 'rgba(255, 255, 255, 0.75)';
if (!(isset($link_colour) && $link_colour))
    $link_colour = '#007bff';
if (!(isset($banner_colour) && $banner_colour))
    $banner_colour = '#fff';
if (!(isset($bgcolour) && $bgcolour))
    $bgcolour = '#eee';
if (!(isset($background_image) && $background_image))
    $background_image = '';
if (!(isset($item_colour) && $item_colour))
    $item_colour = '#fff';
if (!(isset($comment_item_colour) && $comment_item_colour))
    $comment_item_colour = '#fff';
if (!(isset($item_opacity) && $item_opacity))
    $item_opacity = '1';
if (!(isset($font_size) && $font_size))
    $font_size = '0.875rem';
if (!(isset($font_colour) && $font_colour))
    $font_colour = '#4d4d4d';
if (!(isset($radius) && $radius))
    $radius = '0.25rem';
if (!(isset($shadow) && $shadow))
    $shadow = '0';
if (!(isset($converse_width) && $converse_width))
    $converse_width = '790';
if (!(isset($top_photo) && $top_photo))
    $top_photo = '2.3rem';
if (!(isset($reply_photo) && $reply_photo))
    $reply_photo = '2.3rem';

// Apply the settings
if (file_exists('view/theme/redbasic/css/style.css')) {

    $x = file_get_contents('view/theme/redbasic/css/style.css');

    if ($narrow_navbar && file_exists('view/theme/redbasic/css/narrow_navbar.css')) {
        $x .= file_get_contents('view/theme/redbasic/css/narrow_navbar.css');
    }

    if (isset($schemecss)) {
        $x .= $schemecss;
    }

    $aside_width = 288;

    // left aside and right aside are 285px + converse width
    $main_width = (($aside_width * 2) + intval($converse_width));

    // prevent main_width smaller than 768px
    $main_width = (($main_width < 768) ? 768 : $main_width);

    $options = array(
        '$nav_bg' => $nav_bg,
        '$nav_icon_colour' => $nav_icon_colour,
        '$nav_active_icon_colour' => $nav_active_icon_colour,
        '$link_colour' => $link_colour,
        '$banner_colour' => $banner_colour,
        '$bgcolour' => $bgcolour,
        '$background_image' => $background_image,
        '$item_colour' => $item_colour,
        '$comment_item_colour' => $comment_item_colour,
        '$font_size' => $font_size,
        '$font_colour' => $font_colour,
        '$radius' => $radius,
        '$shadow' => $shadow,
        '$converse_width' => $converse_width,
        '$top_photo' => $top_photo,
        '$reply_photo' => $reply_photo,
        '$main_width' => $main_width,
        '$aside_width' => $aside_width
    );

    echo str_replace(array_keys($options), array_values($options), $x);

}

// Set the schema to the default schema in derived themes. See the documentation for creating derived themes how to override this. 

if (local_channel() && App::$channel && App::$channel['channel_theme'] != 'redbasic')
    set_pconfig(local_channel(), 'redbasic', 'schema', '---');
