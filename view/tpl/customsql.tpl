<div class="generic-content-wrapper" id='customsql'>
	<div class="section-title-wrapper"><h1>{{$title}}</h1></div>
	<div class="section-content-wrapper">
    <div class="descriptive_text">{{$warn}}</div>
    <br><br>
    <form action="dev/customsql" method="post">
    <input type="hidden" name="form_security_token" value="{{$form_security_token}}">
    {{include file="field_textarea.tpl" field=$query}}
    {{include file="field_checkbox.tpl" field=$ok}}
    <div class="settings-submit-wrapper" >
        <button type="submit" name="done" value="{{$submit}}" class="btn btn-primary">{{$submit}}</button>
    </div>
    </form>
	</div>
</div>