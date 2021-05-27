<div class="generic-content-wrapper-styled" id='adminpage'>
	<h1>{{$title}} - {{$page}}</h1>

	<form action="{{$baseurl}}/admin/security" method="post">

	<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>


	{{include file="field_checkbox.tpl" field=$block_public}}
	{{include file="field_checkbox.tpl" field=$block_public_search}}
	{{include file="field_checkbox.tpl" field=$block_public_dir}}
	{{include file="field_checkbox.tpl" field=$localdir_hide}}
	{{include file="field_checkbox.tpl" field=$cloud_noroot}}
	{{include file="field_checkbox.tpl" field=$cloud_disksize}}
	{{include file="field_checkbox.tpl" field=$thumbnail_security}}
	{{include file="field_checkbox.tpl" field=$inline_pdf}}

	{{include file="field_checkbox.tpl" field=$transport_security}}
	{{include file="field_checkbox.tpl" field=$content_security}}
	{{include file="field_checkbox.tpl" field=$embed_sslonly}}

	{{include file="field_textarea.tpl" field=$allowed_email}}
	{{include file="field_textarea.tpl" field=$not_allowed_email}}	

	{{include file="field_textarea.tpl" field=$allowed_sites}}
	{{include file="field_textarea.tpl" field=$denied_sites}}

	{{include file="field_textarea.tpl" field=$allowed_channels}}
	{{include file="field_textarea.tpl" field=$denied_channels}}

	{{include file="field_textarea.tpl" field=$psallowed_sites}}
	{{include file="field_textarea.tpl" field=$psdenied_sites}}

	{{include file="field_textarea.tpl" field=$psallowed_channels}}
	{{include file="field_textarea.tpl" field=$psdenied_channels}}

	{{include file="field_textarea.tpl" field=$embed_allow}}
	{{include file="field_textarea.tpl" field=$embed_deny}}


	<div class="admin-submit-wrapper" >
		<input type="submit" name="submit" class="admin-submit" value="{{$submit}}" />
	</div>

	</form>

</div>
