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
    </form>
</div>
<div>
    <form>

        <div id="no-secret" style="border: 1px solid red; padding: 3px; background: pink; display: none">
            {{$no_secret_text}}
        </div>
        <div id="has-secret" style="display: none">
            {{$has_secret1_text}} <b><span id="totp_secret">{{$b32secret}}</span></b>
            <br/>{{$has_secret2_text}}
            <p><img id="totp_qrcode" src="{{$image}}" alt="{{$alt_text}}"/></p>
            <p>
                <input title="{{$test_title}}" type="text" id="totp_test"
                       style="width: 30%;"
                       onkeypress="hitkey(event)"
                       onfocus="totp_clear_code()"/>
                <input type="button" value="{{$test_button}}" onclick="totp_test_code()"/>
                <b><span id="totp_testres"></span></b>
            </p>
        </div>
        <div style="float: left">
            <input type="button" style="width: 16em; margin-top: 3px"
                   value="{{$gen_button}}" onclick="expose_password()"/>
        </div>
        <div id="password_form" style="float: left; margin-left: 1em; display: none">
            {{$enter_password}}:
            <input type="password" class="form-control" id="totp_password"
                   onkeypress="go_generate(event)"
            />
            <input type="button" value="{{$go_button}}"
                   onclick="totp_generate_secret()"/>
        </div>
        <div style="clear: left"></div>
        <div id="totp_note"></div>
        <script type="text/javascript">
function choose_message(has_secret) {
	if (has_secret) {
		document.getElementById("no-secret").style.display = "none";
		document.getElementById("has-secret").style.display = "block";
		}
	else {
		document.getElementById("no-secret").style.display = "block";
		document.getElementById("has-secret").style.display = "none";
		}
	}
$(window).on("load", function() {
	choose_message({{$has_secret}});
	totp_clear_code();
	});
function totp_clear_code() {
	var box = document.getElementById("totp_test");
	box.value = "";
	box.focus();
	document.getElementById("totp_testres").innerHTML = "";
	}
function totp_test_code() {
	$.post('/settings/totp',
		{totp_code: document.getElementById('totp_test').value},
		function(data) {
			document.getElementById("totp_testres").innerHTML =
				(data['match'] == '1' ? '{{$test_pass}}' : '{{$test_fail}}');
			});
	}
function totp_generate_secret() {
	$.post('/settings/totp',
		{
			set_secret: '1',
			password: document.getElementById("totp_password").value
			},
		function(data) {
			if (!data['auth']) {
				var box = document.getElementById("totp_password");
				box.value = "";
				box.focus();
				document.getElementById('totp_note').innerHTML =
					"{{$note_password}}";
				return;
				}
			var div = document.getElementById("password_form");
			div.style.display = "none";
			choose_message(true);
			document.getElementById('totp_secret').innerHTML =
				data['secret'];
			document.getElementById('totp_qrcode').src =
				"{{$qrcode_url}}" + (new Date()).getTime();
			document.getElementById('totp_note').innerHTML =
				"{{$note_scan}}";
			totp_clear_code();
			});
	}
function go_generate(ev) {
	if (ev.which == 13) {
		totp_generate_secret();
		ev.preventDefault();
		ev.stopPropagation();
		}
	}
function hitkey(ev) {
	if (ev.which == 13) {
		totp_test_code();
		ev.preventDefault();
		ev.stopPropagation();
		}
	}
function expose_password() {
	var div = document.getElementById("password_form");
	div.style.display = "block";
	var box = document.getElementById("totp_password");
	box.value = "";
	box.focus();
	}
</script>


</div>

