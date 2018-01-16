$(document).ready(function() {

	$('#id_permcat').change(function() {
		$('.loading').toggleClass('invisible');
		var permName = $('#id_permcat').val();
		loadConnectionRole(permName);
	});


});


function loadConnectionRole(name) {

	if(! name)
		name = 'default';

	$('.defperms-edit input').each(function() {
		if(! $(this).is(':disabled'))
			$(this).removeAttr('checked');
	});

	$.get('permcat/' + name, function(data) {
		$(data.perms).each(function() {
			if(this.value)
				$('#id_perms_' + this.name).attr('checked','checked');
		});
		$('.loading').toggleClass('invisible');
	});
}


