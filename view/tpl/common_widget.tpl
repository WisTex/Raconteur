<div class="widget">
	<h3>
    <div class="cursor-pointer" onclick="openClose('{{$content_id}}');" >
	{{$title}}
    <i class="fa fa-fw fa-caret-down fakelink"></i>
    </div>
	</h3>
    <div id="{{$content_id}}" style="display:none;">
	{{$content}}
    </div>
</div>
