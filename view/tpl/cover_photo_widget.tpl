<script>
	var aside_padding_top;
	var section_padding_top;
	var coverSlid = false;
	var hide_cover = Boolean({{$hide_cover}});

	$(document).ready(function() {
		if(! $('#cover-photo').length)
			return;

		aside_padding_top = parseInt($('aside').css('padding-top'));
		section_padding_top = parseInt($('section').css('padding-top'));

		$(document).on('click mouseup keyup', slideUpCover);

		if($(window).width() > 755) {
			$('#cover-photo').removeClass('d-none');
			datasrc2src('#cover-photo > img');

			if(hide_cover) {
				hideCover();
			}

			if($(window).scrollTop() < $('#cover-photo').height()) {
				$('body').css('cursor', 'n-resize');
				$('.navbar').removeClass('fixed-top');
				$('main').css('margin-top', - $('nav').outerHeight(true) + 'px');
				$('main').css('opacity', 0);
			}
		}
		else {
			$('#cover-photo').remove();
			coverSlid = true;
		}
	});

	$(window).scroll(function () {
		if($(window).width() > 755 && $(window).scrollTop() > ($('#cover-photo').height() - 1)) {
			$('body').css('cursor', '');
			$('.navbar').addClass('fixed-top');
			$('main').css('margin-top', '');
			$('main').css('opacity', 1);
			coverSlid = true;
		}
		else if ($(window).width() > 755 && $(window).scrollTop() < $('#cover-photo').height()){
			if(coverSlid) {
				$(window).scrollTop(Math.ceil($('#cover-photo').height()));
				setTimeout(function(){ coverSlid = false; }, 1000);
			}
			else {
				if($(window).scrollTop() < $('#cover-photo').height()) {
					$('body').css('cursor', 'n-resize');
					$('.navbar').removeClass('fixed-top');
					$('main').css('margin-top', - $('nav').outerHeight(true) + 'px');
					$('main').css('opacity', 0);
				}
			}
		}
		if($('main').css('opacity') < 1) {
			$('main').css('opacity', ($(window).scrollTop()/$('#cover-photo').height()).toFixed(1));
		}
	});

	$(window).resize(function () {
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
		$('html, body').animate({scrollTop: Math.ceil($('#cover-photo').height()) + 'px' }, 'fast');
		return;
	}

	function hideCover() {
		if(coverSlid) {
			return;
		}
		window.scrollTo(0, Math.ceil($('#cover-photo').height()));
	}
</script>

<div class="d-none" id="cover-photo" title="{{$hovertitle}}">
	{{$photo_html}}
	<div id="cover-photo-caption">
		<h1>{{$title}}</h1>
		<h3>{{$subtitle}}</h3>
	</div>
</div>
