<div class="widget" id="dir_sort_links">
<h3>{{$header}}</h3>

{{if ! $hide_local}}
{{include file="field_checkbox.tpl" field=$globaldir}}
{{/if}}
{{include file="field_checkbox.tpl" field=$pubforums}}
{{include file="field_checkbox.tpl" field=$collections}}
{{include file="field_checkbox.tpl" field=$safemode}}
{{include file="field_checkbox.tpl" field=$activedir}}

</div>
