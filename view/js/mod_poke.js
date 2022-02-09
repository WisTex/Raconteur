$(document).ready(function() { 
	$("#poke-recip").name_autocomplete(baseurl + '/acloader', 'a', false, function(data) {
		$("#poke-recip-complete").val(data.id);
	});
}); 
