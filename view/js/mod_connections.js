$(document).ready(function() {
	$("#contacts-search").contact_autocomplete(baseurl + '/acloader', 'a', true);
	$("#follow_input").discover_autocomplete(baseurl + '/acloader', 'x', true);
	$(".autotime").timeago();
}); 

