<div class="widget">
	<h3>
    <div class="cursor-pointer" onclick="openClose('{{$content_id}}'); if ($('#{{$content_id}}').is(':visible')) {
    	$('#{{$content_id}}-chevron').removeClass('fa-chevron-down').addClass('fa-chevron-up');
    } else {
    	$('#{{$content_id}}-chevron').removeClass('fa-chevron-up').addClass('fa-chevron-down');
	} return false;" >
	{{$title}}
    <i id="{{$content_id}}-chevron" class="fa fa-fw fa-chevron-down fakelink"></i>
    </div>
	</h3>
    <div id="{{$content_id}}" style="display:none;">
	{{$content}}
    </div>
</div>
