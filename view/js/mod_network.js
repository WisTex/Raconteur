$(document).ready(function() { 
	$("#search-text").contact_autocomplete(baseurl + '/search_ac','',true);
	$("#cid-filter").name_autocomplete(baseurl + '/acl', 'a', true, function(data) {
		$("#cid").val(data.id);
	});
}); 

