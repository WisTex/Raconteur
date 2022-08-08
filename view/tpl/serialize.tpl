<h3>{{$page_title}}</h3>

<form action="dev/serialize" method="post">
  {{include file="field_textarea.tpl" field=$text}}
  {{include file="field_textarea.tpl" field=$stext}}
  <input type="submit" name="submit" value="{{$submit}}" >
</form>
<br>
<br>
