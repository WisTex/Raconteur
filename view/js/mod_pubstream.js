$(document).ready(function() {
	$(".comment-edit-wrapper textarea").editor_autocomplete(baseurl+"/acloader?f=&n=1");
	// make auto-complete work in more places
	$(".wall-item-comment-wrapper textarea").editor_autocomplete(baseurl+"/acloader?f=&n=1");
});
