<div class="generic-content-wrapper">
    <div class="section-title-wrapper">
        <h2>{{$title}}</h2>
    </div>

        <div class="section-content-tools-wrapper">

            {{if $secret}}
            <div>
                <div>{{$secret_text}}</div>
                <br>
                <div><strong>{{$secret}}</strong></div>
            </div>
            {{/if}}

            <img src="{{$qrcode}}" alt="{{$uri}}" title="{{$uri}}">

            <form action="#" id="totp-test-form" method="post" autocomplete="off" >
                <div id="otp-test-wrapper">
                    <div style="margin-top: 1rem">
                        <label for="totp_test">{{$test_title}}</label>
                    </div>
                    <div style="margin-top: 1rem">
                        <input title="{{$test_title}}" type="text" id="totp_test"
                            style="width: 30%;"
                            onkeydown="hitkey(event)"
                            onfocus="totp_clear_code()"/>
                    </div>
                    <div style="margin-top: 1rem">
                        <strong id="otptest_results"></strong>
                    </div>
                </div>
                <div class="settings-submit-wrapper" >
                    <button id="otp-test-submit" type="submit"
                        name="submit" class="btn btn-primary" onclick="totp_test_code(); return false;">{{$test}}
                    </button>
                </div>
            </form>


            <form action="settings/multifactor" id="settings-mfa-form" method="post" autocomplete="off" >
                <input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
                {{include file="field_checkbox.tpl" field=$enable_mfa}}
                <div class="settings-submit-wrapper" >
                    <button id="otp-enable-submit" type="submit"
                        name="submit" class="btn btn-primary">{{$submit}}
                    </button>
                </div>

            </form>


        </div>
    </form>
</div>

<script type="text/javascript">

$(window).on("load", function() {
	totp_clear_code();
});

function totp_clear_code() {
	var box = document.getElementById("totp_test");
	box.value = "";
	box.focus();
	document.getElementById("otptest_results").innerHTML = "";
}

function totp_test_code() {
	$.post('/totp_check',
		{totp_code: document.getElementById('totp_test').value},
		function(data) {
			document.getElementById("otptest_results").innerHTML =
				(data['status']) ? '{{$test_pass}}' : '{{$test_fail}}';
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
        }
    );
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


