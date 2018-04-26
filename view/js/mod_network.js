$(document).ready(function() { 
	if($("#search-text").length)
		$("#search-text").contact_autocomplete(baseurl + '/search_ac','',true);
}); 

