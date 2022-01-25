$(document).ready(function() {
	$(".comment-edit-wrapper textarea").editor_autocomplete(baseurl+"/acloader?f=&n=1");
	$(".wall-item-comment-wrapper textarea").editor_autocomplete(baseurl+"/acloader?f=&n=1");
});
