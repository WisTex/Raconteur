<div class="widget">
	<h3>
    <div class="cursor-pointer" onclick="openClose('{{$content_id}}'); if ($('#{{$content_id}}').is(':visible')) {
    	$('#{{$content_id}}-caret').removeClass('fa-caret-down').addClass('fa-caret-up');
    } else {
    	$('#{{$content_id}}-caret').removeClass('fa-caret-up').addClass('fa-caret-down');
	} return false;" >
	{{$title}}
    <i id="{{$content_id}}-caret" class="fa fa-fw fa-caret-down fakelink"></i>
    </div>
	</h3>
    <div id="{{$content_id}}" style="display:none;">
	{{$content}}
    </div>
</div>
