$(document).ready(function() {
	$("#contacts-search").contact_autocomplete(baseurl + '/acl', 'a', true);
	$("#follow_input").contact_autocomplete(baseurl + '/acl', 'x', true);
	$(".autotime").timeago();
}); 

