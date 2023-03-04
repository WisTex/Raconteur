<div style="width: 30em; margin: auto; margin-top: 3em; padding: 1em; border: 1px solid grey">
    <h3 style="text-align: center">{{$header}}</h3>

    <div>{{$desc}}</div>

    <div style="margin: auto; margin-top: 1em; width: 18em">
        <input type="text" class="form-control" style="float: left; width: 8em" id="totp-code" onkeydown="hitkey(event)"/>
        <input type="button" style="margin-left: 1em; float: left" value={{$submit}} onclick="totp_verify()"/>
        <div style="clear: left"></div>
        <div id="feedback" style="margin-top: 4px; text-align: center"></div>
    </div>
</div>
<script type="text/javascript">
var totp_success_msg = '{{$success}}';
var totp_fail_msg = '{{$fail}}';
var totp_maxfails_msg = '{{$maxfails}}';
var try_countdown = 3;

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
	$.post("totp", {totp_code: code},
		function(resp) {
			var report = document.getElementById("feedback");
			var box = document.getElementById("totp-code");
			if (resp['match'] == "1") {
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
			});
	}
function hitkey(ev) {
	if (ev.which == 13) totp_verify();
	}
</script>
