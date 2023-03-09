<script type="text/javascript">
let totp_success_msg = '{{$success}}';
let totp_fail_msg = '{{$fail}}';
let totp_maxfails_msg = '{{$maxfails}}';
let try_countdown = 3;

$(window).on("load", function() {
	totp_clear();
});

function totp_clear() {
	let box = document.getElementById("totp-code");
	box.value = "";
	box.focus();
}
function totp_verify() {
	var code = document.getElementById("totp-code").value;
	$.post("totp_check", {totp_code: code},
		function(resp) {
			let report = document.getElementById("feedback");
			let box = document.getElementById("totp-code");
			if (resp['status']) {
				report.innerHTML = "<b>" + totp_success_msg + "</b>";
				window.location = "/";
			}
			else {
				try_countdown -= 1;
				if (try_countdown < 1) {
					report.innerHTML = totp_maxfails_msg;
					window.location = "/logout";
				}
				else {
					report.innerHTML = totp_fail_msg;
					totp_clear();
				}
			}
		}
    );
}

function hitkey(ev) {
	if (ev.which == 13) totp_verify();
}
</script>

<div class="generic-content-wrapper">
    <div class="section-content-tools-wrapper">
        <h3 style="text-align: center;">{{$header}}</h3>
        <div>{{$desc}}</div>
        <br>
        <div class="form-group">
            <input type="text" class="form-control" style="width: 10em" id="totp-code" onkeydown="hitkey(event)"/>
            <div id="feedback"></div>
        </div>
        <br>
        <div>
            <input type="button" class="btn btn-primary" value={{$submit}} onclick="totp_verify()"/>
        </div>
    </div>
</div>
