<h1>OAuth 2.0 Test Vehicle</h1>

{{foreach $endpoints as $ept}}
<div class="oath2test-form-box">
<form action="{{$baseurl}}/{{$ept.0}}/" method="{{$ept.4}}">
	<h3>{{$ept.3}}</h3>
	{{$baseurl}}/{{$ept.0}}/?{{foreach $ept.1 as $field}}{{$field.0}}={{$field.1}}&<input type="hidden" name="{{$field.0}}" value="{{$field.1}}" />{{/foreach}}
	<br>
	<button type="submit" name="{{$ept.2}}_submit" value="submit" class="btn btn-med" title="">Submit</button>
	<span style="display: {{if $ept.5}}inline{{else}}none{{/if}}; font-size: 2em;">&nbsp;<i class="fa fa-check"></i></span>
</form>
	</div>
{{/foreach}}
<div>
	<h3>API response</h3>
	<pre style="display: inline-block; overflow-x: auto; white-space: nowrap; width: 100%;">
	<code>
	{{$api_response}}
	</code>
	</pre>
</div>