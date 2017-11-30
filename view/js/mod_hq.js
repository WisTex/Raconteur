$(document).on('click', '#jot-toggle', function(e) {
	e.preventDefault();
	e.stopPropagation();

	$(this).toggleClass('active');
	$(window).scrollTop(0);
	$('#jot-popup').toggle();
	$('#profile-jot-text').focus();

});
