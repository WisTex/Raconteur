<h3>{{$page_title}}</h3>
<div class="descriptive-text">{{$notes}}</div>	
<div class="descriptive-text">{{$limits}}</div>	
<form action="followlist" method="post">
  {{if ! $disabled}}
  <input type='hidden' name='form_security_token' value='{{$form_security_token}}' />
  {{/if}}
  {{include file="field_input.tpl" field=$url}}
  <input type="submit" name="submit" value="{{$submit}}" {{$disabled}}>
</form>
<br>
<br>
