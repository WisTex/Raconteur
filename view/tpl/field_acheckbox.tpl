<tr class="highlight" style="line-height: 2";>
	<td>
		<label class="mainlabel" for='id_{{$field.0}}'>{{$field.1}}</label><br>
		<span class='field_abook_help'>{{$field.6}}</span>
	</td>
	{{if $notself}}
	<td class="abook-them form-group checkbox">
        <div>
        <input type="checkbox" name='them-id-{{$field.0}}' id='them_id_{{$field.0}}' value="1" {{if $field.2}}checked="checked"{{/if}} disabled="disabled" /><label class="switchlabel" for='them_id_{{$field.0}}'> <span class="onoffswitch-inner" data-on='{{if $field.4}}{{$field.4.1}}{{/if}}' data-off='{{if $field.4}}{{$field.4.0}}{{/if}}'></span><span class="onoffswitch-switch"></span>
        </div>
	</td>
	{{/if}}
	<td class="abook-me form-group checkbox" >
		{{if $self || !$field.5 }}
        <div>
        <input type="checkbox" class="abook-edit-me" name='{{$field.0}}' id='id_{{$field.0}}' value="1" {{if $field.3}}checked="checked"{{/if}} {{if $field.5}}disabled="disabled"{{/if}} /><label class="switchlabel" for='id_{{$field.0}}'> <span class="onoffswitch-inner" data-on='{{if $field.4}}{{$field.4.1}}{{/if}}' data-off='{{if $field.4}}{{$field.4.0}}{{/if}}'></span><span class="onoffswitch-switch"></span></label>
		<!--input type="checkbox" name='{{$field.0}}' class='abook-edit-me' id='me_id_{{$field.0}}' value="{{$field.4}}" {{if $field.3}}checked="checked"{{/if}} /-->
        </div>
		{{/if}}
		{{if $notself && $field.5}}
        <div>
		<input type="hidden" name='{{$field.0}}' value="{{if $field.7}}1{{else}}0{{/if}}" />
        <input type="checkbox" name='{{$field.0}}' id='id_{{$field.0}}' value="{{if $field.7}}1{{else}}0{{/if}}" {{if $field.3}}checked="checked"{{/if}} {{if $field.5}}disabled="disabled"{{/if}} /><label class="switchlabel" for='id_{{$field.0}}'> <span class="onoffswitch-inner" data-on='{{if $field.4}}{{$field.4.1}}{{/if}}' data-off='{{if $field.4}}{{$field.4.0}}{{/if}}'></span><span class="onoffswitch-switch"></span></label>
        </div>
    
		{{*if $field.3}}<i class="fa fa-check-square-o" style="color:#800;"></i>{{else}}<i class="fa fa-square-o"></i>{{/if*}}
		{{/if}}
	</td>
	<td>
		{{if $field.5}}<span class="permission-inherited">{{$inherited}}{{if $self}}{{if $field.7}} <i class="fa fa-check-square-o"></i>{{else}} <i class="fa fa-square-o"></i>{{/if}}{{/if}}</span>{{/if}}
	</td>
</tr>
