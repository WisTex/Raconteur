<div id="follow-sidebar" class="widget">
	<h3>{{$connect}}</h3>
	<form action="superblock" method="post" />
		<div class="input-group">
			<input class="form-control" id="follow_input" type="text" name="block" title="{{$hint}}" placeholder="{{$desc}}" />
			<div class="input-group-append">
				<button class="btn btn-sm btn-success" type="submit" name="submit" value="{{$follow}}" title="{{$follow}}"><i class="fa fa-fw fa-plus"></i></button>
			</div>
		</div>
	</form>
</div>
