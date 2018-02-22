<h1>OAuth 2.0 Test Vehicle</h1>

{{foreach $endpoints as $ept}}
<div class="oath2test-form-box">
<form action="{{$baseurl}}/{{$ept.0}}" method="{{$ept.4}}">
	<h3>{{$ept.3}}</h3>
	<p>{{$baseurl}}/{{$ept.0}}/?{{foreach $ept.1 as $field}}{{$field.0}}={{$field.1}}&<input type="hidden" name="{{$field.0}}" value="{{$field.1}}" />{{/foreach}}
	</p>
	<button type="submit" name="{{$ept.2}}_submit" value="submit" class="btn btn-med" title="">Submit</button>
	
</form>
	</div>
{{/foreach}}