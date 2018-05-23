	$(document).ready(function() {
		$("#id_name").focus();

		$("#id_name").blur(function() {
			if(validate_name()) {
				var zreg_name = $("#id_name").val();
				$("#name_help_loading").show();
				$("#name_help_text").hide();
				$.get("new_channel/autofill.json?f=&name=" + encodeURIComponent(zreg_name),function(data) {
					$("#id_nickname").val(data);
					$("#id_nickname").addClass('is-validated');
					$("#name_help_loading").hide();
					$("#name_help_text").show();
				});
			}
		});

		$("#id_nickname").on('input', function() {
			$("#id_nickname").removeClass('is-validated');
		});

		$("#newchannel-form").on('submit', function(event) {
			if(! validate_name()) {
				$("#id_name").focus()
				return false;
			}

			if(! validate_channel()) {
				$("#id_nickname").focus()
				return false;
			}

			if(! $("#id_nickname").hasClass('is-validated')) {
				event.preventDefault();
			}
		});

	});

	function validate_channel() {
		if($("#id_nickname").hasClass('is-validated'))
			return true;

		$("#nick_help_loading").show();
		$("#nick_help_text").hide();
		var zreg_name = $("#id_name").val();
		var zreg_nick = $("#id_nickname").val();
		$.get("new_channel/checkaddr.json?f=&nick=" + encodeURIComponent(zreg_nick) + '&name=' + encodeURIComponent(zreg_name),function(data) {
			$("#id_nickname").val(data);
			if(data !== zreg_nick) {
				$("#id_nickname").addClass('is-validated');
				$("#help_nickname").addClass('text-danger').removeClass('text-muted');
				$("#help_nickname").html(aStr['nick_invld1'] + data + aStr['nick_invld2']);
				$("#id_nickname").focus();
			}
			else {
				$("#id_nickname").addClass('is-validated');
				$("#help_nickname").addClass('text-success').removeClass('text-muted').removeClass('text-danger');
				$("#help_nickname").html(aStr['nick_valid']);
			}
			$("#nick_help_loading").hide();
			$("#nick_help_text").show();

		});
		return true;

	}

	function validate_name() {
		if($("#id_name").hasClass('is-validated'))
			return true;

		var verbs = [ aStr['lovely'], aStr['wonderful'], aStr['fantastic'], aStr['great'] ];
		var verb = verbs[Math.floor((Math.random() * 4) + 0)];

		if(! $("#id_name").val()) {
			$("#id_name").focus();
			$("#help_name").addClass('text-danger').removeClass('text-muted');
			$("#help_name").html(aStr['name_empty']);
			return false;
		}
		else {
			$("#help_name").addClass('text-success').removeClass('text-muted').removeClass('text-danger');
			$("#help_name").html(aStr['name_ok1'] + verb + aStr['name_ok2']);
			$("#id_name").addClass('is-validated');
			return true;
		}
	}
