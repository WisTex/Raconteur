<?php
use Code\Lib\Head;
    
Head::add_css('/vendor/forkawesome/fork-awesome/css/fork-awesome.min.css');
Head::add_css('/vendor/twbs/bootstrap/dist/css/bootstrap.min.css');
Head::add_css('/library/bootstrap-tagsinput/bootstrap-tagsinput.css');
Head::add_css('/view/css/bootstrap-red.css');
Head::add_css('/library/datetimepicker/jquery.datetimepicker.css');
Head::add_css('/library/bootstrap-colorpicker/dist/css/bootstrap-colorpicker.min.css');

require_once('view/php/theme_init.php');

Head::add_js('/vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js');
Head::add_js('/library/bootbox/bootbox.min.js');
Head::add_js('/library/bootstrap-tagsinput/bootstrap-tagsinput.js');
Head::add_js('/library/datetimepicker/jquery.datetimepicker.js');
Head::add_js('/library/bootstrap-colorpicker/dist/js/bootstrap-colorpicker.js');
Head::add_js('/library/swipe/jquery.touchSwipe.min.js');

