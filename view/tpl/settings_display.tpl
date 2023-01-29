<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<h2>{{$ptitle}}</h2>
	</div>
	<form action="settings/display" id="settings-form" method="post" autocomplete="off" >
		<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>

		<div class="panel-group" id="settings" role="tablist" aria-multiselectable="true">
			{{if $theme}}
			<div class="panel">
				<div class="section-subtitle-wrapper" role="tab" id="theme-settings-title">
					<h3>
						<a data-bs-toggle="collapse" data-bs-target="#theme-settings-content" href="#" aria-expanded="true" aria-controls="theme-settings-content">
							{{$d_tset}}
						</a>
					</h3>
				</div>
				<div id="theme-settings-content" class="collapse show" role="tabpanel" aria-labelledby="theme-settings" data-parent="#settings" >
					<div class="section-content-tools-wrapper">
						{{if $theme}}
							{{include file="field_themeselect.tpl" field=$theme}}
						{{/if}}
						{{if $schema}}
							{{include file="field_select.tpl" field=$schema}}
						{{/if}}
						<div class="settings-submit-wrapper" >
							<button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
						</div>
					</div>
				</div>
			</div>
			{{/if}}
			<div class="panel">
				<div class="section-subtitle-wrapper" role="tab" id="custom-settings-title">
					<h3>
						<a data-bs-toggle="collapse" data-bs-target="#custom-settings-content" href="" aria-expanded="true" aria-controls="custom-settings-content">
							{{$d_ctset}}
						</a>
					</h3>
				</div>
				<div id="custom-settings-content" class="collapse{{if !$theme}} in{{/if}}" role="tabpanel" aria-labelledby="custom-settings" data-parent="#settings" >
					<div class="section-content-tools-wrapper">
						{{if $theme_config}}
							{{$theme_config}}
						{{/if}}
					</div>
				</div>
			</div>
			<div class="panel">
				<div class="section-subtitle-wrapper" role="tab" id="content-settings-title">
					<h3>
						<a data-bs-toggle="collapse" data-bs-target="#content-settings-content" href="" aria-expanded="true" aria-controls="content-settings-content">
							{{$d_cset}}
						</a>
					</h3>
				</div>
				<div id="content-settings-content" class="collapse{{if !$theme && !$theme_config}} in{{/if}}" role="tabpanel" aria-labelledby="content-settings" data-parent="#settings">
					<div class="section-content-wrapper">
						{{include file="field_input.tpl" field=$ajaxint}}
						{{include file="field_input.tpl" field=$itemspage}}
						{{include file="field_input.tpl" field=$indentpx}}
						{{include file="field_input.tpl" field=$channel_divmore_height}}
						{{include file="field_input.tpl" field=$stream_divmore_height}}
						{{include file="field_checkbox.tpl" field=$nosmile}}
						{{include file="field_checkbox.tpl" field=$channel_menu}}
						{{include file="field_checkbox.tpl" field=$user_scalable}}
						{{include file="field_checkbox.tpl" field=$preload_images}}
						{{if $expert}}
						<div class="form-group">
							<a class="btn btn-outline-secondary "href="pdledit">{{$layout_editor}}</a>
						</div>
						{{/if}}
						<div class="settings-submit-wrapper" >
							<button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</form>
</div>
