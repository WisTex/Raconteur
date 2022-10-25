<?php

namespace Code\Widget;

use App;

class Tagcloud implements WidgetInterface
{

    public function widget(array $arguments): string
    {

        $o = '';
        $uid = App::$profile_uid;
        $count = ((x($arguments, 'count')) ? intval($arguments['count']) : 24);
        $flags = 0;
        $type = TERM_HASHTAG;

        // @FIXME there exists no $authors variable
        $r = tagadelic($uid, $count, $authors, $owner, $flags, 0, $type);

        // @FIXME this should use a template

        if ($r) {
            $o = '<div class="tagblock widget"><h3>' . t('Tags') . '</h3><div class="tags" align="center">';
            foreach ($r as $rv) {
                $o .= '<span class="tag' . $rv[2] . '">' . $rv[0] . ' </span> ' . "\r\n";
            }
            $o .= '</div></div>';
        }
        return $o;
    }
}
