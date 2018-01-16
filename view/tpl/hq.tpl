{{if $no_messages}}
<div class="alert alert-warning alert-dismissible fade show" role="alert">
	<button type="button" class="close" data-dismiss="alert" aria-label="Close">
		<span aria-hidden="true">&times;</span>
	</button>
	<h3>{{$no_messages_label.0}}</h3>
	<br>
	{{$no_messages_label.1}}
</div>
{{/if}}
<div id="jot-popup">
{{$editor}}
</div>

