<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<div class="float-right">
			<button type="button" class="btn btn-sm btn-outline-secondary" onclick="openClose('group_tools')"><i class="fa fa-pencil"></i> {{$details_label}}</button>
			<button id="fullscreen-btn" type="button" class="btn btn-outline-secondary btn-sm" onclick="makeFullScreen();"><i class="fa fa-expand"></i></button>
			<button id="inline-btn" type="button" class="btn btn-outline-secondary btn-sm" onclick="makeFullScreen(false);"><i class="fa fa-compress"></i></button>
		</div>
		<h2>{{$title}}</h2>
	</div>

	<div id="group_tools" class="clearfix section-content-tools-wrapper" style="display: none">
		<form action="group/{{$gid}}" id="group-edit-form" method="post" >
			<input type='hidden' name='form_security_token' value='{{$form_security_token_edit}}'>
			{{include file="field_input.tpl" field=$gname}}
			{{include file="field_checkbox.tpl" field=$public}}
			<a href="group/drop/{{$gid}}?t={{$form_security_token_drop}}" onclick="return confirmDelete();" class="btn btn-sm btn-danger">
				{{$delete}}
			</a>
			<button type="submit" name="submit" class="btn btn-sm btn-primary float-right">{{$submit}}</button>
		</form>
	</div>
	<div class="section-content-info-wrapper">
		{{$desc}}
	</div>
	<div class="section-content-wrapper">
		<div id="group-update-wrapper" class="clearfix">
			{{include file="groupeditor.tpl"}}
		</div>
	</div>
</div>
