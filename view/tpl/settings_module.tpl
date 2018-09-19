<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<h2>{{$title}}</h2>
	</div>
	<div class="section-content-wrapper">
		<form action="{{$action_url}}" method="post" autocomplete="off">
		<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
		{{foreach $features as $feature}}
		{{include file="field_checkbox.tpl" field=$feature}}
		{{/foreach}}
		<div class="settings-submit-wrapper" >
			<button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
		</div>
	</div>
</div>
