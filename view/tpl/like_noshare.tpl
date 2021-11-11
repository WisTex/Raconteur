<div class="wall-item-like-buttons" id="wall-item-like-buttons-{{$id}}">
	<button type="button" title="{{if $ilike}}{{$unlikethis}}{{else}}{{$likethis}}{{/if}}" class="btn btn-outline-secondary btn-sm" onclick="dolike({{$id}},{{if $ilike}} 'Undo/' + {{/if}} 'Like' ); return false;">
		<i class="fa fa-thumbs-o-up item-tool{{if $ilike}} ivoted{{/if}}" ></i>
	</button>
	<button type="button" title="{{if $inolike}}{{$unnolike}}{{else}}{{$nolike}}{{/if}}" class="btn btn-outline-secondary btn-sm" onclick="dolike({{$id}},{{if $inolike}} 'Undo/' + {{/if}} 'Dislike'); return false;">
		<i class="fa fa-thumbs-o-down item-tool{{if $inolike}} ivoted{{/if}}" ></i>
	</button>
<div id="like-rotator-{{$id}}" class="like-rotator"></div>
</div>
