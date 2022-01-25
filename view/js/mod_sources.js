$(document).ready(function() { 
	$(document).ready(function() { 
		$("#id_name").contact_autocomplete(baseurl + '/acloader', 'a', false, function(data) {
			$("#id_abook").val(data.id);
		});
	}); 
}); 
