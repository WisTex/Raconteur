	$(document).ready(function() {

		$("#newchannel-submit-button").attr('disabled','disabled');

		$("#id_name").blur(function() {
			$("#name-spinner").show();
			var zreg_name = $("#id_name").val();
			$.get("new_channel/autofill.json?f=&name=" + encodeURIComponent(zreg_name),function(data) {
				$("#id_nickname").val(data);
				if(data.error) {
					$("#help_name").html("");
					zFormError("#help_name",data.error);
				}
				else {
					$("#newchannel-submit-button").removeAttr('disabled');
				}
				$("#name-spinner").hide();
			});
		});

		$("#id_nickname").click(function() {
			$("#newchannel-submit-button").attr('disabled','disabled');
		});

	});


	function validate_channel() {
		$("#nick-spinner").show();
		var zreg_nick = $("#id_nickname").val();
		$.get("new_channel/checkaddr.json?f=&nick=" + encodeURIComponent(zreg_nick),function(data) {
			$("#id_nickname").val(data);
			if(data.error) {
				$("#help_nickname").html("");
				zFormError("#help_nickname",data.error);
			}
			else {
				$("#newchannel-submit-button").removeAttr('disabled');
			}
			$("#nick-spinner").hide();
		});

	}
