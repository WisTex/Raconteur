<?php
namespace Zotlabs\Module;

/*
 * Embed
 * Given a post_id returns a share tag for inclusion in a post
 *
 */
 
use Zotlabs\Web\Controller;

class Embed extends Controller
{

    public function init()
    {
        $post_id = ((argc() > 1) ? intval(argv(1)) : 0);

        if ($post_id && local_channel()) {
            echo '[share=' . $post_id . '][/share]';
        }
        killme();
    }
}
