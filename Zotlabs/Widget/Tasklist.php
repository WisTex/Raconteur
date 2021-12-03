<?php

namespace Zotlabs\Widget;

use Zotlabs\Lib\Apps;

require_once('include/event.php');

class Tasklist
{

    public function widget($arr)
    {

        $o = EMPTY_STR;

        if (!(local_channel() && Apps::system_app_installed(local_channel(), 'Tasks'))) {
            return $o;
        }

        $o .= '<script>var tasksShowAll = 0; $(document).ready(function() { tasksFetch(); $("#tasklist-new-form").submit(function(event) { event.preventDefault(); $.post( "tasks/new", $("#tasklist-new-form").serialize(), function(data) { tasksFetch();  $("#tasklist-new-summary").val(""); } ); return false; } )});</script>';
        $o .= '<script>function taskComplete(id) { $.post("tasks/complete/"+id, function(data) { tasksFetch();}); }
			function tasksFetch() {
				$.get("tasks/fetch" + ((tasksShowAll) ? "/all" : ""), function(data) {
					$(".tasklist-tasks").html(data.html);
				});
			}
			</script>';

        $o .= '<div class="widget">' . '<h3>' . t('Tasks') . '</h3><div class="tasklist-tasks">';
        $o .= '</div><form id="tasklist-new-form" action="" ><input class="form-control" id="tasklist-new-summary" type="text" name="summary" value="" /></form>';
        $o .= '</div>';
        return $o;
    }
}
