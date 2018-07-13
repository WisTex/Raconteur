$(document).ready(function() { 
	$(".autotime").timeago();
	$("#recip").name_autocomplete(baseurl + '/acl', 'm', false, function(data) {
		$("#recip-complete").val(data.xid);
	});
	$('#prvmail-text').bbco_autocomplete('bbcode');
	$("#prvmail-text").editor_autocomplete(baseurl+"/acl");
}); 
