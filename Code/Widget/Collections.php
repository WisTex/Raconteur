<?php

namespace Code\Widget;

use App;
use Code\Lib\AccessList;

class Collections
{

    public function widget($args)
    {

//      if(argc() < 2)
//          return;

        $mode = ((array_key_exists('mode', $args)) ? $args['mode'] : 'conversation');

        switch ($mode) {
            case 'conversation':
                $every = argv(0);
                $each = argv(0);
                $edit = true;
                $current = $_REQUEST['gid'];
                $abook_id = 0;
                $wmode = 0;
                break;
            case 'connections':
                $every = 'connections';
                $each = 'lists';
                $edit = true;
                $current = 0;
                $abook_id = 0;
                $wmode = 1;
                break;
            case 'groups':
                $every = 'connections';
                $each = argv(0);
                $edit = false;
                $current = intval(argv(1));
                $abook_id = 0;
                $wmode = 1;
                break;
            case 'abook':
                $every = 'connections';
                $each = 'lists';
                $edit = false;
                $current = 0;
                $abook_id = App::$poi['abook_xchan'];
                $wmode = 1;
                break;
            default:
                return '';
                break;
        }

        return AccessList::widget($every, $each, $edit, $current, $abook_id, $wmode);
    }
}
