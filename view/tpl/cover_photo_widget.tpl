<script>
	var aside_padding_top;
	var section_padding_top;
	var coverSlid = false;
	var hide_cover = Boolean({{$hide_cover}});
	var cover_height;

	$(document).ready(function() {
		if(! $('#cover-photo').length)
			return;

		if($(window).width() < 755) {
			$('#cover-photo').remove();
			coverSlid = true;
			return;
		}

		$('#cover-photo').removeClass('d-none');
		cover_height = Math.ceil($(window).width()/2.75862069);
		$('#cover-photo').css('height', cover_height + 'px');
		datasrc2src('#cover-photo > img');

		$(document).on('click mouseup keyup', slideUpCover);

		if(hide_cover) {
			hideCover();
		}
		else if(!hide_cover && !coverSlid)  {
			coverVisibleActions();
		}
	});

	$(window).scroll(function () {
		if($(window).scrollTop() >= cover_height) {
			coverHiddenActions();
			coverSlid = true;
		}
		else if ($(window).scrollTop() < cover_height){
			if(coverSlid) {
				$(window).scrollTop(cover_height);
				setTimeout(function(){ coverSlid = false; }, 1000);
			}
			else {
				if($(window).scrollTop() < cover_height) {
					coverVisibleActions();
				}
			}
		}
		if($('main').css('opacity') < 1) {
			$('main').css('opacity', ($(window).scrollTop()/cover_height).toFixed(1));
		}
	});

	$(window).resize(function () {
		cover_height = Math.ceil($(window).width()/2.75862069);
		$('#cover-photo').css('height', cover_height + 'px');
		if($(window).width() < 755) {
			$('#cover-photo').remove();
			$('.navbar').addClass('fixed-top');
			$('main').css('opacity', 1);
			coverSlid = true;
		}

	});

	function slideUpCover() {
		if(coverSlid) {
			return;
		}
		$('html, body').animate({scrollTop: cover_height + 'px'}, 'fast');
		return;
	}

	function hideCover() {
		if(coverSlid) {
			return;
		}
		window.scrollTo(0, cover_height);
		return;
	}

	function coverVisibleActions() {
		$('body').css('cursor', 'n-resize');
		$('.navbar').removeClass('fixed-top');
		$('main').css('margin-top', - $('nav').outerHeight(true) + 'px');
		$('main').css('opacity', 0);
	}

	function coverHiddenActions() {
		$('body').css('cursor', '');
		$('.navbar').addClass('fixed-top');
		$('main').css('margin-top', '');
		$('main').css('opacity', 1);
	}
</script>

<div class="d-none" id="cover-photo" title="{{$hovertitle}}">
	{{$photo_html}}
	<div id="cover-photo-caption">
		<h1>{{$title}}</h1>
		<h3>{{$subtitle}}</h3>
	</div>
</div>
