$(document).ready(function() {
//	$('form').areYouSure(); // Warn user about unsaved settings
	$('textarea').editor_autocomplete(baseurl + "/acl");
	$('textarea').bbco_autocomplete('bbcode');
});
