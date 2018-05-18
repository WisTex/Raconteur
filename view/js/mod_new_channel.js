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
			else {
				event.preventDefault();
				console.log('channel created');
			}
		});

	});

	function validate_channel() {
		if($("#id_nickname").hasClass('is-validated'))
			return true;

		$("#nick_help_loading").show();
		$("#nick_help_text").hide();
		var zreg_nick = $("#id_nickname").val();
		$.get("new_channel/checkaddr.json?f=&nick=" + encodeURIComponent(zreg_nick),function(data) {
			$("#id_nickname").val(data);
			if(data !== zreg_nick) {
				$("#id_nickname").addClass('is-validated');
				$("#help_nickname").addClass('text-danger').removeClass('text-muted');
				$("#help_nickname").html('Your chosen nickname was either already taken or not valid. Please use our suggestion (' + data + ') or enter a new one.');
				$("#id_nickname").focus();
			}
			else {
				$("#id_nickname").addClass('is-validated');
				$("#help_nickname").addClass('text-success').removeClass('text-muted').removeClass('text-danger');
				$("#help_nickname").html("Thank you, this nickname is valid.");
			}
			$("#nick_help_loading").hide();
			$("#nick_help_text").show();

		});
		return true;

	}

	function validate_name() {
		if($("#id_name").hasClass('is-validated'))
			return true;

		var verbs = ['lovely', 'wonderful', 'gorgeous', 'great'];
		var verb = verbs[Math.floor((Math.random() * 4) + 0)];

		if(! $("#id_name").val()) {
			$("#id_name").focus();
			$("#help_name").addClass('text-danger').removeClass('text-muted');
			$("#help_name").html("A channel name is required.");
			return false;
		}
		else {
			$("#help_name").addClass('text-success').removeClass('text-muted').removeClass('text-danger');
			$("#help_name").html('This is a ' + verb + ' channel name.');
			$("#id_name").addClass('is-validated');
			return true;
		}
	}
