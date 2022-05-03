<h3>{{$page_title}}</h3>
	
<form action="dev/ap_probe" method="get">
  {{include file="field_input.tpl" field=$resource}}
  {{include file="field_checkbox.tpl" field=$authf}}
  <input type="submit" name="submit" value="{{$submit}}" >
</form>
<br>
<br>

