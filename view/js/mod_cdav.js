$(document).ready(function() {
	$('textarea').editor_autocomplete(baseurl + "/acloader");
	$('textarea').bbco_autocomplete('bbcode');
});
