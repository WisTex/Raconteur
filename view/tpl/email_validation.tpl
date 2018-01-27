<h2>{{$title}}</h2>

<div class="descriptive-paragraph" style="font-size: 1.2em;"><p>{{$desc}}</p></div>

<form action="email_validation" method="post">
{{include file="field_input.tpl" field=$token}}

<div class="pull-right">
	<a href="email_resend/{{$email}}" class="btn btn-warning">{{$resend}}</a>
</div>
<div class="submit-wrapper" >
	<button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
</div>
</form>
<div class="clear"></div>

