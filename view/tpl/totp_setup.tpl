<div class="generic-content-wrapper">
    <div class="section-title-wrapper">
        <h2>{{$title}}</h2>
    </div>
    <form action="settings/multifactor" id="settings-mfa-form" method="post" autocomplete="off" >
        <div class="section-content-tools-wrapper">
        <div class="form-group">
        <input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
        {{if $secret}}
            {{include file="field_input.tpl" field=$secret}}
        {{/if}}
        <img src="{{$qrcode}}" alt="{{$uri}}" title="{{$uri}}">

        {{include file="field_input.tpl" field=$test}}
        <div id="otptest_results"></div>

        {{include file="field_checkbox.tpl" field=$enable_mfa}}

        <div class="settings-submit-wrapper" >
            <button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
        </div>
        </div>
        </div>
    <form>
</div>
