<div class="generic-content-wrapper">
	<div class="clearfix section-title-wrapper">
		<button type="button" class="btn btn-sm btn-success float-right" onclick="openClose('group_tools')"><i class="fa fa-plus-circle"></i> {{$add_new_label}}</button>
		<h2>{{$title}}</h2>
	</div>
	<div id="group_tools" class="clearfix section-content-tools-wrapper"{{if ! $new}} style="display: none"{{/if}}>
		<form action="group/new" id="group-edit-form" method="post" >
			<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
			{{include file="field_input.tpl" field=$gname}}
			{{include file="field_checkbox.tpl" field=$public}}
			<button type="submit" name="submit" class="btn btn-sm btn-primary float-right">{{$submit}}</button>
		</form>
	</div>

	<table id="groups-index">
		<tr>
			<th width="99%">{{$name_label}}</th>
			<th width="1%">{{$count_label}}</th>
		</tr>

		{{foreach $entries as $group}}
		<tr id="groups-index-{{$group.id}}" class="group-index-row">
			<td><a href="group/{{$group.id}}">{{$group.name}}</a></td>
			<td>{{$group.count}}</td>
		</tr>
		{{/foreach}}
	</table>
</div>
