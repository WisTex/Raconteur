<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<h2>{{$title}}</h2>
		<div class="clear"></div>
	</div>
	<div class="section-content-tools-wrapper">
		<div class="section-content-info-wrapper">
			{{$desc}}
		</div>

		<form action="settings/permcats" id="settings-permcats-form" method="post" autocomplete="off" >
			<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
			{{include file="field_input.tpl" field=$name}}

			<div class="settings-submit-wrapper form-group">
				<button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
			</div>
	</div>

	<div class="panel">
		<div class="section-subtitle-wrapper" role="tab" id="perms-tool">
			<h3>
				<a data-toggle="collapse" data-target="#perms-tool-collapse" href="#perms-tool-collapse" aria-expanded="true" aria-controls="perms-tool-collapse">
				{{$permlbl}}
				</a>
			</h3>
		</div>
		<div id="perms-tool-collapse" class="panel-collapse collapse" role="tabpanel" aria-labelledby="perms-tool" style="display:block;">
			<div class="section-content-tools-wrapper">
				<div class="section-content-warning-wrapper">
				{{$permnote}}
				</div>


        		<div class="defperms-edit">
    				{{foreach $perms as $prm}}
	    			{{include file="field_checkbox.tpl" field=$prm}}
		    		{{/foreach}}
				</div>

				{{if $hidden_perms}}
				{{foreach $hidden_perms as $prm}}
					<input type="hidden" name="{{$prm.0}}" value="{{$prm.1}}" >
					{{/foreach}}
				{{/if}}

				<div class="settings-submit-wrapper" >
					<button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
				</div>
			</div>
		</div>
		{{if $permcats}}
		<div class="section-content-wrapper-np">
			<table id="permcat-index">
			{{foreach $permcats as $k => $v}}
			<tr class="permcat-row-{{$k}}">
				<td width="99%"><a href="settings/permcats/{{$k}}">{{$v}}</a></td>
				<td width="1%"><i class="fa fa-trash-o drop-icons" onClick="dropItem('/settings/permcats/{{$k}}/drop', '.permcat-row-{{$k}}')"></i></td>
			</tr>
			{{/foreach}}
			</table>
		</div>
		{{/if}}

	</div>
	</form>

</div>
