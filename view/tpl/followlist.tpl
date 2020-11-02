<h3>{{$page_title}}</h3>
<div class="descriptive-text">{{$notes}}</div>	
<div class="descriptive-text">{{$limits}}</div>	
<form action="followlist" method="post">
  {{include file="field_input.tpl" field=$url}}
  <input type="submit" name="submit" value="{{$submit}}" >
</form>
<br>
<br>
