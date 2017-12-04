<input id="invisible-cloud-file-upload" type="file" name="files" style="visibility:hidden;position:absolute;top:-50;left:-50;width:0;height:0;" multiple>
<div id="files-mkdir-tools" class="section-content-tools-wrapper">
	<label for="files-mkdir">{{$folder_header}}</label>
	<form id="mkdir-form" method="post" action="file_upload" class="acl-form" data-form_id="mkdir-form" data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}' data-deny_gid='{{$deny_gid}}'>
		<input type="hidden" name="folder" value="{{$folder}}" />
		<input type="hidden" name="channick" value="{{$channick}}" />
		<input type="hidden" name="return_url" value="{{$return_url}}" />
		<input id="files-mkdir" type="text" name="filename" class="form-control form-group">
		<div class="pull-right btn-group">
			<div class="btn-group">
				{{if $lockstate}}
				<button class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#aclModal" type="button">
					<i class="jot-perms-icon fa fa-{{$lockstate}}"></i>
				</button>
				{{/if}}
				<button class="btn btn-primary btn-sm pull-right" type="submit" value="{{$folder_submit}}">{{$folder_submit}}</button>
			</div>
		</div>
	</form>
	<div class="clear"></div>
</div>
<div id="files-upload-tools" class="section-content-tools-wrapper">
	{{if $quota.limit || $quota.used}}<div class="{{if $quota.warning}}section-content-danger-wrapper{{else}}section-content-info-wrapper{{/if}}">{{if $quota.warning}}<strong>{{$quota.warning}} </strong>{{/if}}{{if $quota.desc}}{{$quota.desc}}<br><br>{{/if}}</div>{{/if}}
	<form id="ajax-upload-files" method="post" action="#" enctype="multipart/form-data" class="acl-form" data-form_id="ajax-upload-files" data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}' data-deny_gid='{{$deny_gid}}'>
		<input type="hidden" name="directory" value="{{$path}}" />
		<input type="hidden" name="channick" value="{{$channick}}" />
		<input type="hidden" name="return_url" value="{{$return_url}}" />
		<!--label for="files-upload">{{$upload_header}}</label>
		<input class="form-group pull-left" id="files-upload" type="file" name="files[]" multiple -->
		{{include file="field_checkbox.tpl" field=$notify}}
		<div class="pull-right btn-group">
			<div class="btn-group">
				{{if $lockstate}}
				<button class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#aclModal" type="button">
					<i class="jot-perms-icon fa fa-{{$lockstate}}"></i>
				</button>
				{{/if}}
				<button id="upload-submit" class="btn btn-primary btn-sm pull-right">{{$upload_submit}}</button>
			</div>
		</div>
	</form>
	<div class="clear"></div>
</div>
{{$aclselect}}
