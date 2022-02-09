$(document).ready(function() { 
	$("#recip").name_autocomplete(baseurl + '/acloader', 'm', false, function(data) {
		$("#recip-complete").val(data.xid);
	});
	$('#prvmail-text').bbco_autocomplete('bbcode');
	$("#prvmail-text").editor_autocomplete(baseurl+"/acloader");
}); 
