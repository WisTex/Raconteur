$(document).ready(function() {
	$("#contacts-search").contact_autocomplete(baseurl + '/acl', 'a', true);
	$(".autotime").timeago();
}); 

