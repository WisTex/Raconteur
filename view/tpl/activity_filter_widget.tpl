<div class="widget">
	<h3 class="d-flex justify-content-between align-items-center">
		{{$title}}
		{{if $reset}}
		<a href="{{$reset.url}}" class="text-muted" title="{{$reset.title}}">
			<i class="fa fa-fw fa-{{$reset.icon}}"></i>
		</a>
		{{/if}}
	</h3>
	{{$content}}
</div>
