var src = null;
var prev = null;
var livetime = null;
var msie = false;
var stopped = false;
var totStopped = false;
var timer = null;
var pr = 0;
var liking = 0;
var in_progress = false;
var langSelect = false;
var commentBusy = false;
var last_popup_menu = null;
var last_popup_button = null;
var scroll_next = false;
var next_page = 1;
var page_load = true;
var loadingPage = true;
var pageHasMoreContent = true;
var divmore_height = 400;
var last_filestorage_id = null;
var mediaPlaying = false;
var contentHeightDiff = 0;
var liveRecurse = 0;
var savedTitle = '';
var initialLoad = true;

// Clear the session and local storage if we switch channel or log out
var cache_uid = '';
if(sessionStorage.getItem('uid') !== null) {
	cache_uid = sessionStorage.getItem('uid');
}
if(cache_uid !== localUser.toString()) {
	sessionStorage.clear();
	localStorage.clear();
	sessionStorage.setItem('uid', localUser.toString());
}

$.ajaxSetup({cache: false});

$(document).ready(function() {

	$(document).on('click focus', '.comment-edit-form', handle_comment_form);

	jQuery.timeago.settings.strings = {
		prefixAgo     : aStr['t01'],
		prefixFromNow : aStr['t02'],
		suffixAgo     : aStr['t03'],
		suffixFromNow : aStr['t04'],
		seconds       : aStr['t05'],
		minute        : aStr['t06'],
		minutes       : aStr['t07'],
		hour          : aStr['t08'],
		hours         : aStr['t09'],
		day           : aStr['t10'],
		days          : aStr['t11'],
		month         : aStr['t12'],
		months        : aStr['t13'],
		year          : aStr['t14'],
		years         : aStr['t15'],
		wordSeparator : aStr['t16'],
		numbers       : aStr['t17'],
	};

	savedTitle = document.title;

	updateInit();

	$('a.notification-link').click(function(e){
		var notifyType = $(this).data('type');

		if(! $('#nav-' + notifyType + '-sub').hasClass('show')) {
			loadNotificationItems(notifyType);
			sessionStorage.setItem('notification_open', notifyType);
		}
		else {
			sessionStorage.removeItem('notification_open');
		}
	});

	if(sessionStorage.getItem('notification_open') !== null) {
		var notifyType = sessionStorage.getItem('notification_open');
		$('#nav-' + notifyType + '-sub').addClass('show');
		loadNotificationItems(notifyType);
	}

	// Allow folks to stop the ajax page updates with the pause/break key
	$(document).keydown(function(event) {
		if(event.keyCode == '8') {
			var target = event.target || event.srcElement;
			if (!/input|textarea/i.test(target.nodeName)) {
				return false;
			}
		}

		if(event.keyCode == '19' || (event.ctrlKey && event.which == '32')) {
			event.preventDefault();
			if(stopped === false) {
				stopped = true;
				if (event.ctrlKey) {
					totStopped = true;
				}
				$('#pause').html('<img src="images/pause.gif" alt="pause" style="border: 1px solid black;" />');
			} else {
				unpause();
			}
		} else {
			if (!totStopped) {
				unpause();
			}
		}
	});

	var e = document.getElementById('content-complete');
	if(e)
		pageHasMoreContent = false;

	initialLoad = false;

});

function datasrc2src(selector) {
	$(selector).each(function(i, el) {
		$(el).attr("src", $(el).data("src"));
		$(el).removeAttr("data-src");
	});
}

function confirmDelete() {
	return confirm(aStr.delitem);
}

function handle_comment_form(e) {
	e.stopPropagation();

	//handle eventual expanded forms
	var expanded = $('.comment-edit-text.expanded');
	var i = 0;

	if(expanded.length) {
		expanded.each(function() {
			var ex_form = $(expanded[i].form);
			var ex_fields = ex_form.find(':input[type=text], textarea');
			var ex_fields_empty = true;

			ex_fields.each(function() {
				if($(this).val() != '')
					ex_fields_empty = false;
			});
			if(ex_fields_empty) {
				ex_form.find('.comment-edit-text').removeClass('expanded').attr('placeholder', aStr.comment);
				ex_form.find(':not(.comment-edit-text)').hide();
			}
			i++
		});
	}

	// handle clicked form
	var form = $(this);
	var fields = form.find(':input[type=text], textarea');
	var fields_empty = true;

	if(form.find('.comment-edit-text').length) {
		var commentElm = form.find('.comment-edit-text').attr('id');
		var submitElm = commentElm.replace(/text/,'submit');

		$('#' + commentElm).addClass('expanded').removeAttr('placeholder');
		$('#' + commentElm).attr('tabindex','9');
		$('#' + submitElm).attr('tabindex','10');
		
		form.find(':not(:visible)').show();
	}

	// handle click outside of form (close empty forms)
	$(document).on('click', function(e) {
		fields.each(function() {
			if($(this).val() != '')
				fields_empty = false;
		});
		if(fields_empty) {
			var emptyCommentElm = form.find('.comment-edit-text').attr('id');
        	var emptySubmitElm = commentElm.replace(/text/,'submit');

			$('#' + emptyCommentElm).removeClass('expanded').attr('placeholder', aStr.comment);
			$('#' + emptyCommentElm).removeAttr('tabindex');
			$('#' + emptySubmitElm).removeAttr('tabindex');
			form.find(':not(.comment-edit-text)').hide();
		}
	});
	
	var commentSaveTimer = null;
	var emptyCommentElm = form.find('.comment-edit-text').attr('id');
	var convId = emptyCommentElm.replace('comment-edit-text-','');
	$(document).on('focusout','#' + emptyCommentElm,function(e){
		if(commentSaveTimer)
			clearTimeout(commentSaveTimer);
		commentSaveChanges(convId,true);
		commentSaveTimer = null;
	});

	$(document).on('focusin','#' + emptyCommentElm,function(e){
		commentSaveTimer = setTimeout(function () {
			commentSaveChanges(convId,false);
		},10000);
	});

	function commentSaveChanges(convId,isFinal = false) {
		if(auto_save_draft) {
			tmp = $('#' + emptyCommentElm).val();
			if(tmp) {
				localStorage.setItem("comment_body-" + convId, tmp);
			}
			else {
				localStorage.removeItem("comment_body-" + convId);
			}
			if( !isFinal) {
				commentSaveTimer = setTimeout(commentSaveChanges,10000,convId);
			}
		}
	}
}

function commentClose(obj, id) {
	if(obj.value === '') {
		obj.value = aStr.comment;
		$("#comment-edit-text-" + id).removeClass("expanded");
		$("#mod-cmnt-wrap-" + id).hide();
		$("#comment-tools-" + id).hide();
		$("#comment-edit-anon-" + id).hide();
		return true;
	}
	return false;
}

function showHideCommentBox(id) {
	if( $('#comment-edit-form-' + id).is(':visible')) {
		$('#comment-edit-form-' + id).hide();
	} else {
		$('#comment-edit-form-' + id).show();
	}
}

function commentInsert(obj, id) {
	var tmpStr = $("#comment-edit-text-" + id).val();
	if(tmpStr == '$comment') {
		tmpStr = '';
		$("#comment-edit-text-" + id).addClass("expanded");
		openMenu("comment-tools-" + id);
	}
	var ins = $(obj).html();
	ins = ins.replace('&lt;','<');
	ins = ins.replace('&gt;','>');
	ins = ins.replace('&amp;','&');
	ins = ins.replace('&quot;','"');
	$("#comment-edit-text-" + id).val(tmpStr + ins);
}

function insertbbcomment(comment, BBcode, id) {
	// allow themes to override this
	if(typeof(insertFormatting) != 'undefined')
		return(insertFormatting(comment, BBcode, id));

	var urlprefix = ((BBcode == 'url') ? '#^' : '');

	var tmpStr = $("#comment-edit-text-" + id).val();
	if(tmpStr == comment) {
		tmpStr = "";
		$("#comment-edit-text-" + id).addClass("expanded");
		openMenu("comment-tools-" + id);
		$("#comment-edit-text-" + id).val(tmpStr);
	}

	textarea = document.getElementById("comment-edit-text-" +id);
	if (document.selection) {
		textarea.focus();
		selected = document.selection.createRange();
		selected.text = urlprefix+"["+BBcode+"]" + selected.text + "[/"+BBcode+"]";
	} else if (textarea.selectionStart || textarea.selectionStart == "0") {
		var start = textarea.selectionStart;
		var end = textarea.selectionEnd;
		textarea.value = textarea.value.substring(0, start) + urlprefix+"["+BBcode+"]" + textarea.value.substring(start, end) + "[/"+BBcode+"]" + textarea.value.substring(end, textarea.value.length);
	}
	return true;
}

function inserteditortag(BBcode, id) {
	// allow themes to override this
	if(typeof(insertEditorFormatting) != 'undefined')
		return(insertEditorFormatting(BBcode));

	textarea = document.getElementById(id);
	if (document.selection) {
		textarea.focus();
		selected = document.selection.createRange();
		selected.text = urlprefix+"["+BBcode+"]" + selected.text + "[/"+BBcode+"]";
	} else if (textarea.selectionStart || textarea.selectionStart == "0") {
		var start = textarea.selectionStart;
		var end = textarea.selectionEnd;
		textarea.value = textarea.value.substring(0, start) + "["+BBcode+"]" + textarea.value.substring(start, end) + "[/"+BBcode+"]" + textarea.value.substring(end, textarea.value.length);
	}
	return true;
}

function insertCommentAttach(comment,id) {

	activeCommentID = id;
	activeCommentText = comment;

	$('body').css('cursor', 'wait');

	$('#invisible-comment-upload').trigger('click');
 
	return false;

}

function insertCommentURL(comment, id) {
	reply = prompt(aStr.linkurl);
	if(reply && reply.length) {
		reply = bin2hex(reply);
		$('body').css('cursor', 'wait');
		$.get('linkinfo?f=&binurl=' + reply, function(data) {
			var tmpStr = $("#comment-edit-text-" + id).val();
			if(tmpStr == comment) {
				tmpStr = "";
				$("#comment-edit-text-" + id).addClass("expanded");
				openMenu("comment-tools-" + id);
				$("#comment-edit-text-" + id).val(tmpStr);
			}

			textarea = document.getElementById("comment-edit-text-" +id);
			textarea.value = textarea.value + data;
			preview_comment(id);
			$('body').css('cursor', 'auto');
		});
	}
	return true;
}

function doFollowAuthor(url) {
	$.get(url, function(data) { notificationsUpdate(); });
	return true;
}


function viewsrc(id) {
	$.colorbox({href: 'viewsrc/' + id, maxWidth: '80%', maxHeight: '80%' });
}

function showHideComments(id) {
	if( $('#collapsed-comments-' + id).is(':visible')) {
		$('#collapsed-comments-' + id + ' .autotime').timeago('dispose');
		$('#collapsed-comments-' + id).slideUp();
		$('#hide-comments-' + id).html(aStr.showmore);
		$('#hide-comments-total-' + id).show();
	} else {
		$('#collapsed-comments-' + id + ' .autotime').timeago();
		$('#collapsed-comments-' + id).slideDown();
		$('#hide-comments-' + id).html(aStr.showfewer);
		$('#hide-comments-total-' + id).hide();
	}
}

function openClose(theID) {
	if(document.getElementById(theID).style.display == "block") {
		document.getElementById(theID).style.display = "none";
	} else {
		document.getElementById(theID).style.display = "block";
	}
}

function openCloseTR(theID) {
	if(document.getElementById(theID).style.display == "table-row") {
		document.getElementById(theID).style.display = "none";
	} else {
		document.getElementById(theID).style.display = "table-row";
	}
}

function closeOpen(theID) {
	if(document.getElementById(theID).style.display == "none") {
		document.getElementById(theID).style.display = "block";
	} else {
		document.getElementById(theID).style.display = "none";
	}
}

function openMenu(theID) {
	document.getElementById(theID).style.display = "block";
}

function closeMenu(theID) {
	document.getElementById(theID).style.display = "none";
}

function markRead(notifType) {
	$.get('ping?f=&markRead='+notifType);
	$('.' + notifType + '-button').hide();
	$('#nav-' + notifType + '-sub').removeClass('show');
	sessionStorage.removeItem(notifType + '_notifications_cache');
	sessionStorage.removeItem('notification_open');
	if(timer) clearTimeout(timer);
	timer = setTimeout(updateInit,2000);
}

function markItemRead(itemId) {
	$.get('ping?f=&markItemRead='+itemId);
	$('.unseen-wall-indicator-'+itemId).hide();
}

function notificationsUpdate(cached_data) {
	var pingCmd = 'ping' + ((localUser != 0) ? '?f=&uid=' + localUser : '');

	if(cached_data !== undefined) {
		handleNotifications(cached_data);
	}
	else {
		$.get(pingCmd,function(data) {

			// Put the object into storage
			sessionStorage.setItem('notifications_cache', JSON.stringify(data));

			if(data.invalid == 1) {
				window.location.href=window.location.href;
			}

			handleNotifications(data);

			$.jGrowl.defaults.closerTemplate = '<div>[ ' + aStr.closeAll + ']</div>';

			$(data.notice).each(function() {
				$.jGrowl(this.message, { sticky: true, theme: 'notice' });
			});

			$(data.info).each(function(){
				$.jGrowl(this.message, { sticky: false, theme: 'info', life: 10000 });
			});
		});
	}

	var notifyType = null;
	if($('.notification-content.show').length) {
		notifyType = $('.notification-content.show').data('type');
	}
	if(notifyType !== null) {
		loadNotificationItems(notifyType);
	}

	if(timer) clearTimeout(timer);
	timer = setTimeout(updateInit,updateInterval);
}

function handleNotifications(data) {
	if(data.network || data.home || data.intros || data.register || data.mail || data.all_events || data.notify || data.files || data.pubs) {
		$('.notifications-btn').css('opacity', 1);
		$('#no_notifications').hide();
	}
	else {
		$('.notifications-btn').css('opacity', 0.5);
		$('#navbar-collapse-1').removeClass('show');
		$('#no_notifications').show();
	}

	if(data.home || data.intros || data.register || data.mail || data.notify || data.files) {
		$('.notifications-btn-icon').removeClass('fa-exclamation-circle');
		$('.notifications-btn-icon').addClass('fa-exclamation-triangle');
	}
	if(!data.home && !data.intros && !data.register && !data.mail && !data.notify && !data.files) {
		$('.notifications-btn-icon').removeClass('fa-exclamation-triangle');
		$('.notifications-btn-icon').addClass('fa-exclamation-circle');
	}
	if(data.all_events_today) {
		$('.all_events-update').removeClass('badge-secondary');
		$('.all_events-update').addClass('badge-danger');
	}
	else {
		$('.all_events-update').removeClass('badge-danger');
		$('.all_events-update').addClass('badge-secondary');
	}

	$.each(data, function(index, item) {
		//do not process those
		var arr = ['notice', 'info', 'invalid'];
		if(arr.indexOf(index) !== -1)
			return;

		if(item == 0) {
			$('.' + index + '-button').fadeOut();
			sessionStorage.removeItem(index + '_notifications_cache');
		} else {
			$('.' + index + '-button').fadeIn();
			$('.' + index + '-update').html(item);
		}
	});
}

function handleNotificationsItems(notifyType, data) {
	var notifications_tpl= unescape($("#nav-notifications-template[rel=template]").html());
	var notify_menu = $("#nav-" + notifyType + "-menu");

	notify_menu.html('');

	$(data).each(function() {
		html = notifications_tpl.format(this.notify_link,this.photo,this.name,this.message,this.when,this.hclass,this.b64mid,this.notify_id,this.thread_top);
		notify_menu.append(html);
	});

	datasrc2src('#notifications .notification img[data-src]');

	if($('#tt-' + notifyType + '-only').hasClass('active'))
		$('#nav-' + notifyType + '-menu [data-thread_top=false]').hide();

	if($('#cn-' + notifyType + '-input').length) {
		var filter = $('#cn-' + notifyType + '-input').val().toString().toLowerCase();
		if(filter) {
			$('#nav-' + notifyType + '-menu .notification').each(function(i, el){
				var cn = $(el).data('contact_name').toString().toLowerCase();
				if(cn.indexOf(filter) === -1)
					$(el).addClass('d-none');
				else
					$(el).removeClass('d-none');
			});
		}
	}
}

function contextualHelp() {
	var container = $("#contextual-help-content");

	if(container.hasClass('contextual-help-content-open')) {
		container.removeClass('contextual-help-content-open');
		$('main').css('margin-top', '')
	}
	else {
		container.addClass('contextual-help-content-open');
		var mainTop = container.outerHeight(true);
		$('main').css('margin-top', mainTop + 'px');
	}
}

function contextualHelpFocus(target, openSidePanel) {
        if($(target).length) {
            if (openSidePanel) {
                    $("main").addClass('region_1-on');  // Open the side panel to highlight element
            }
            else {
                    $("main").removeClass('region_1-on');
            }

	    var css_position = $(target).parent().css('position');
	    if (css_position === 'fixed') {
	            $(target).parent().css('position', 'static');
	    }

            $('html,body').animate({ scrollTop: $(target).offset().top - $('nav').outerHeight(true) - $('#contextual-help-content').outerHeight(true)}, 'slow');
            for (i = 0; i < 3; i++) {
                    $(target).fadeTo('slow', 0.1).fadeTo('slow', 1.0);
            }

	    $(target).parent().css('position', css_position);
        }
}

function updatePageItems(mode, data) {

	if(mode === 'append') {
		$(data).each(function() {
			$('#page-end').before($(this));
		});

		if(loadingPage) {
			loadingPage = false;
		}
	}

	var e = document.getElementById('content-complete');
	if(e) {
		pageHasMoreContent = false;
	}

	collapseHeight();
}


function updateConvItems(mode,data) {

	if(mode === 'update' || mode === 'replace') {
		prev = 'threads-begin';
	}
	if(mode === 'append') {
		next = 'threads-end';
	}
	
	if(mode === 'replace') {
		$('.thread-wrapper').remove(); // clear existing content
	}

	$('.thread-wrapper.toplevel_item',data).each(function() {

		var ident = $(this).attr('id');
		var convId = ident.replace('thread-wrapper-','');
		var commentWrap = $('#'+ident+' .collapsed-comments').attr('id');


		var itmId = 0;
		var isVisible = false;

		// figure out the comment state
		if(typeof commentWrap !== 'undefined')
			itmId = commentWrap.replace('collapsed-comments-','');
				
		if($('#collapsed-comments-'+itmId).is(':visible'))
			isVisible = true;




		// insert the content according to the mode and first_page 
		// and whether or not the content exists already (overwrite it)

		if($('#' + ident).length == 0) {
			if((mode === 'update' || mode === 'replace') && profile_page == 1) {
					$('#' + prev).after($(this));
				prev = ident;
			}
			if(mode === 'append') {
				$('#' + next).before($(this));
			}
		}
		else {
			$('#' + ident).replaceWith($(this));
		}		

		// set the comment state to the state we discovered earlier

		if(isVisible)
			showHideComments(itmId);

		var commentBody = localStorage.getItem("comment_body-" + convId);

		if(commentBody) {
			var commentElm = $('#comment-edit-text-' + convId);
			if(auto_save_draft) {
				if($(commentElm).val() === '') {
					$('#comment-edit-form-' + convId).show();
					$(commentElm).addClass("expanded");
					openMenu("comment-tools-" + convId);
					$(commentElm).val(commentBody);
				}
			} else {
				localStorage.removeItem("comment_body-" + convId);
			}
		}



		// trigger the autotime function on all newly created content

		$("> .wall-item-outside-wrapper .autotime, > .thread-wrapper .autotime",this).timeago();
		$("> .shared_header .autotime",this).timeago();
		
		if((mode === 'append' || mode === 'replace') && (loadingPage)) {
			loadingPage = false;
		}

		// if single thread view and  the item has a title, display it in the title bar

		if(mode === 'replace') {
			if (window.location.search.indexOf("mid=") != -1 || window.location.pathname.indexOf("display") != -1) {
				var title = $(".wall-item-title").text();
				title.replace(/^\s+/, '');
				title.replace(/\s+$/, '');
				if (title) {
					savedTitle = title + " " + savedTitle;
				}
			}
		}
	});

	// reset rotators and cursors we may have set before reaching this place

	$('.like-rotator').hide();

	if(commentBusy) {
		commentBusy = false;
		$('body').css('cursor', 'auto');
	}

	// Setup to determine if the media player is playing. This affects
	// some content loading decisions. 

	$('video').off('playing');
	$('video').off('pause');
	$('audio').off('playing');
	$('audio').off('pause');

	$('video').on('playing', function() {
		mediaPlaying = true;
	});
	$('video').on('pause', function() {
		mediaPlaying = false;
	});
	$('audio').on('playing', function() {
		mediaPlaying = true;
	});
	$('audio').on('pause', function() {
		mediaPlaying = false;
	});

	/* autocomplete @nicknames */
	$(".comment-edit-form  textarea").editor_autocomplete(baseurl+"/acl?f=&n=1");
	/* autocomplete bbcode */
	$(".comment-edit-form  textarea").bbco_autocomplete('bbcode');

	var bimgs = ((preloadImages) ? false : $(".wall-item-body img").not(function() { return this.complete; }));
	var bimgcount = bimgs.length;

	if (bimgcount) {
		bimgs.on('load',function() {
			bimgcount--;
			if (! bimgcount) {
				collapseHeight();
			}
		});
	} else {
		collapseHeight();
	}

	// auto-scroll to a particular comment in a thread (designated by mid) when in single-thread mode
	// use the same method to generate the submid as we use in ThreadItem, 
	// base64_encode + replace(['+','='],['','']);

	var submid = ((bParam_mid.length) ? bParam_mid : 'abcdefg');
	var encoded = ((submid.substr(0,4) == 'b64.') ? true : false);
	var submid_encoded = ((encoded) ? submid.substr(4) : window.btoa(submid));

	submid_encoded = submid_encoded.replace(/[\+\=]/g,'');
	if($('.item_' + submid_encoded).length && !$('.item_' + submid_encoded).hasClass('toplevel_item') && mode == 'replace') {
		if($('.collapsed-comments').length) {
			var scrolltoid = $('.collapsed-comments').attr('id').substring(19);
			$('#collapsed-comments-' + scrolltoid + ' .autotime').timeago();
			$('#collapsed-comments-' + scrolltoid).show();
			$('#hide-comments-' + scrolltoid).html(aStr.showfewer);
			$('#hide-comments-total-' + scrolltoid).hide();
		}
		$('html, body').animate({ scrollTop: $('.item_' + submid_encoded).offset().top - $('nav').outerHeight() }, 'slow');
		$('.item_' + submid_encoded).addClass('item-highlight');
	}

	$(document.body).trigger("sticky_kit:recalc");
}

function collapseHeight() {
	var origContentHeight = Math.ceil($("#region_2").height());
	var cDiff = 0;
	var i = 0;
	var position = $(window).scrollTop();

	$(".wall-item-content, .directory-collapse").each(function() {
		var orgHeight = $(this).outerHeight(true);
		if(orgHeight > divmore_height) {
			if(! $(this).hasClass('divmore') && $(this).has('div.no-collapse').length == 0) {

				// check if we will collapse some content above the visible content and compensate the diff later
				if($(this).offset().top + divmore_height - $(window).scrollTop() + cDiff - ($(".divgrow-showmore").outerHeight() * i) < 65) {
					diff = orgHeight - divmore_height;
					cDiff = cDiff + diff;
					i++;
				}

				$(this).readmore({
					speed: 0,
					heightMargin: 50,
					collapsedHeight: divmore_height,
					moreLink: '<a href="#" class="divgrow-showmore fakelink">' + aStr.divgrowmore + '</a>',
					lessLink: '<a href="#" class="divgrow-showmore fakelink">' + aStr.divgrowless + '</a>',
					beforeToggle: function(trigger, element, expanded) {
						if(expanded) {
							if((($(element).offset().top + divmore_height) - $(window).scrollTop()) < 65 ) {
								$(window).scrollTop($(window).scrollTop() - ($(element).outerHeight(true) - divmore_height));
							}
						}
					}
				});
				$(this).addClass('divmore');
			}
		}
	});

	var collapsedContentHeight = Math.ceil($("#region_2").height());
	contentHeightDiff = origContentHeight - collapsedContentHeight;
	console.log('collapseHeight() - contentHeightDiff: ' + contentHeightDiff + 'px');

	if(i){
		var sval = position - cDiff + ($(".divgrow-showmore").outerHeight() * i);
		console.log('collapsed above viewport count: ' + i);
		$(window).scrollTop(sval);
	}
}

function updateInit() {

	if($('#live-network').length)    { src = 'network'; }
	if($('#live-channel').length)    { src = 'channel'; }
	if($('#live-pubstream').length)  { src = 'pubstream'; }
	if($('#live-display').length)    { src = 'display'; }
	if($('#live-hq').length)         { src = 'hq'; }
	if($('#live-search').length)     { src = 'search'; }
	// if($('#live-cards').length)      { src = 'cards'; }
	// if($('#live-articles').length)   { src = 'articles'; }

	if (initialLoad && (sessionStorage.getItem('notifications_cache') !== null)) {
		var cached_data = JSON.parse(sessionStorage.getItem('notifications_cache'));
		notificationsUpdate(cached_data);
	}

	if(! src) {
		notificationsUpdate();
	}
	else {
		liveUpdate();
	}

	if($('#live-photos').length || $('#live-cards').length || $('#live-articles').length ) {
		if(liking) {
			liking = 0;
			window.location.href=window.location.href;
		}
	}
}

function liveUpdate(notify_id) {

	if(typeof profile_uid === 'undefined') profile_uid = false; /* Should probably be unified with channelId defined in head.tpl */

	if((src === null) || (stopped) || (! profile_uid)) { $('.like-rotator').hide(); return; }

	if(($('.comment-edit-text.expanded').length) || (in_progress) || (mediaPlaying)) {
		if(livetime) {
			clearTimeout(livetime);
		}
		livetime = setTimeout(liveUpdate, 10000);
		return;
	}

	if(timer)
		clearTimeout(timer);

	if(livetime !== null)
		livetime = null;

	prev = 'live-' + src;

	in_progress = true;

	var update_url;
	var update_mode;

	if(scroll_next) {
		bParam_page = next_page;
		page_load = true;
	}
	else {
		bParam_page = 1;
	}

	update_url = buildCmd();

	if(page_load) {
		$("#page-spinner").show();
		if(bParam_page == 1)
			update_mode = 'replace';
		else
			update_mode = 'append';
	}
	else {
		update_mode = 'update';
		var orgHeight = $("#region_2").height();
	}

	var dstart = new Date();
	console.log('LOADING data...');
	$.get(update_url, function(data) {

		// on shared hosts occasionally the live update process will be killed
		// leaving an incomplete HTML structure, which leads to conversations getting
		// truncated and the page messed up if all the divs aren't closed. We will try 
		// again and give up if we can't get a valid HTML response after 10 tries.

		if((data.indexOf("<html>") != (-1)) && (data.indexOf("</html>") == (-1))) {
			console.log('Incomplete data. Reloading');
			in_progress = false;
			liveRecurse ++;
			if(liveRecurse < 10) {
				liveUpdate();
			}
			else {
				console.log('Incomplete data. Too many attempts. Giving up.');
			}
		}		

		// else data was valid - reset the recursion counter
		liveRecurse = 0;

		if(typeof notify_id !== 'undefined' && notify_id !== 'undefined') {
			$.post(
				"hq",
				{
					"notify_id" : notify_id
				}
			);
		}

		var dready = new Date();
		console.log('DATA ready in: ' + (dready - dstart)/1000 + ' seconds.');

		if(update_mode === 'update' || preloadImages) {
			console.log('LOADING images...');

			$('.wall-item-body, .wall-photo-item',data).imagesLoaded( function() {
				var iready = new Date();
				console.log('IMAGES ready in: ' + (iready - dready)/1000 + ' seconds.');

				page_load = false;
				scroll_next = false;
				updateConvItems(update_mode,data);
				$("#page-spinner").hide();
				$("#profile-jot-text-loading").hide();

				// adjust scroll position if new content was added above viewport
				if(update_mode === 'update') {
					$(window).scrollTop($(window).scrollTop() + $("#region_2").height() - orgHeight + contentHeightDiff);
				}

				in_progress = false;

			});
		}
		else {
			page_load = false;
			scroll_next = false;
			updateConvItems(update_mode,data);
			$("#page-spinner").hide();
			$("#profile-jot-text-loading").hide();

			in_progress = false;

		}

	})
	.done(function() {
		notificationsUpdate();
	});
}

function pageUpdate() {

	in_progress = true;

	var update_url;
	var update_mode;

	if(scroll_next) {
		bParam_page = next_page;
		page_load = true;
	}
	else {
		bParam_page = 1;
	}

	update_url = baseurl + '/' + page_query + '/?f=&aj=1&page=' + bParam_page + extra_args ;

	$("#page-spinner").show();
	update_mode = 'append';

	$.get(update_url,function(data) {
		page_load = false;
		scroll_next = false;
		updatePageItems(update_mode,data);
		$("#page-spinner").hide();
		$(".autotime").timeago();
		in_progress = false;
	});
}

function justifyPhotos(id) {
	justifiedGalleryActive = true;
	$('#' + id).show();
	$('#' + id).justifiedGallery({
		selector: 'a, div:not(.spinner, #page-end)',
		margins: 3,
		border: 0,
		sizeRangeSuffixes: {
			'lt100': '-3',
			'lt240': '-3',
			'lt320': '-3',
			'lt500': '-2',
			'lt640': '-2',
			'lt1024': '-1'
		}
	}).on('jg.complete', function(e){ justifiedGalleryActive = false; });
}

function justifyPhotosAjax(id) {
	justifiedGalleryActive = true;
	$('#' + id).justifiedGallery('norewind').on('jg.complete', function(e){ justifiedGalleryActive = false; });
}

function loadNotificationItems(notifyType) {
	var pingExCmd = 'ping/' + notifyType + ((localUser != 0) ? '?f=&uid=' + localUser : '');

	var clicked = $('[data-type=\'' + notifyType + '\']').data('clicked');

	if((clicked === undefined) && (sessionStorage.getItem(notifyType + '_notifications_cache') !== null)) {
		var cached_data = JSON.parse(sessionStorage.getItem(notifyType + '_notifications_cache'));
		handleNotificationsItems(notifyType, cached_data);
		$('[data-type=\'' + notifyType + '\']').data('clicked',true);
		console.log('updating ' + notifyType + ' notifications from cache...');
	}
	else {
		var cached_data = [];
	}

	console.log('updating ' + notifyType + ' notifications...');
	$.get(pingExCmd, function(data) {
		if(data.invalid == 1) {
			window.location.href=window.location.href;
		}

		if(JSON.stringify(cached_data[0]) === JSON.stringify(data.notify[0])) {
			console.log(notifyType + ' notifications cache up to date - update deferred');
		}
		else {
			handleNotificationsItems(notifyType, data.notify);
			sessionStorage.setItem(notifyType + '_notifications_cache', JSON.stringify(data.notify));
		}
	});
}

// Since our ajax calls are asynchronous, we will give a few
// seconds for the first ajax call (setting like/dislike), then
// run the updater to pick up any changes and display on the page.
// The updater will turn any rotators off when it's done.
// This function will have returned long before any of these
// events have completed and therefore there won't be any
// visible feedback that anything changed without all this
// trickery. This still could cause confusion if the "like" ajax call
// is delayed and updateInit runs before it completes.
function dolike(ident, verb) {
	unpause();
	$('#like-rotator-' + ident.toString()).show();
	$.get('like/' + ident.toString() + '?verb=' + verb, updateInit );
	liking = 1;
}

function doprofilelike(ident, verb) {
	$.get('like/' + ident + '?verb=' + verb, function() { window.location.href=window.location.href; });
}

function dropItem(url, object) {
	var confirm = confirmDelete();
	if(confirm) {
		$('body').css('cursor', 'wait');
		$(object).fadeTo('fast', 0.33, function () {
			$.get(url).done(function() {
				$(object).remove();
				$('body').css('cursor', 'auto');
			});
		});
		return true;

	}
	else {
		return false;
	}
}

function dosubthread(ident) {
	unpause();
	$('#like-rotator-' + ident.toString()).show();
	$.get('subthread/sub/' + ident.toString(), updateInit );
	liking = 1;
}

function dounsubthread(ident) {
	unpause();
	$('#like-rotator-' + ident.toString()).show();
	$.get('subthread/unsub/' + ident.toString(), updateInit );
	liking = 1;
}

function dostar(ident) {
	ident = ident.toString();
	$('#like-rotator-' + ident).show();
	$.get('starred/' + ident, function(data) {
		if(data.result == 1) {
			$('#starred-' + ident).addClass('starred');
			$('#starred-' + ident).removeClass('unstarred');
			$('#starred-' + ident).addClass('fa-star');
			$('#starred-' + ident).removeClass('fa-star-o');
			$('#star-' + ident).addClass('hidden');
			$('#unstar-' + ident).removeClass('hidden');
			var btn_tpl = '<div class="btn-group" id="star-button-' + ident + '"><button type="button" class="btn btn-outline-secondary btn-sm wall-item-like" onclick="dostar(' + ident + ');"><i class="fa fa-star"></i></button></div>'
			$('#wall-item-tools-left-' + ident).prepend(btn_tpl);
		}
		else {
			$('#starred-' + ident).addClass('unstarred');
			$('#starred-' + ident).removeClass('starred');
			$('#starred-' + ident).addClass('fa-star-o');
			$('#starred-' + ident).removeClass('fa-star');
			$('#star-' + ident).removeClass('hidden');
			$('#unstar-' + ident).addClass('hidden');
			$('#star-button-' + ident).remove();
		}
		$('#like-rotator-' + ident).hide();
	});
}

function getPosition(e) {
	var cursor = {x:0, y:0};
	if ( e.pageX || e.pageY  ) {
		cursor.x = e.pageX;
		cursor.y = e.pageY;
	}
	else {
		if( e.clientX || e.clientY ) {
			cursor.x = e.clientX + (document.documentElement.scrollLeft || document.body.scrollLeft) - document.documentElement.clientLeft;
			cursor.y = e.clientY + (document.documentElement.scrollTop  || document.body.scrollTop)  - document.documentElement.clientTop;
		}
		else {
			if( e.x || e.y ) {
				cursor.x = e.x;
				cursor.y = e.y;
			}
		}
	}
	return cursor;
}

function lockview(type, id) {
	$.get('lockview/' + type + '/' + id, function(data) {
		$('#panel-' + id).html(data);
	});
}

function filestorage(event, nick, id) {
	$('#cloud-index-' + last_filestorage_id).removeClass('cloud-index-active');
	$('#perms-panel-' + last_filestorage_id).hide().html('');
	$('#file-edit-' + id).show();
	$.get('filestorage/' + nick + '/' + id + '/edit', function(data) {
		$('#cloud-index-' + id).addClass('cloud-index-active');
		$('#perms-panel-' + id).html(data).show();
		$('#file-edit-' + id).hide();
		last_filestorage_id = id;
	});
}

function post_comment(id) {
	unpause();
	commentBusy = true;
	$('body').css('cursor', 'wait');
	$("#comment-preview-inp-" + id).val("0");
	$.post(
		"item",
		$("#comment-edit-form-" + id).serialize(),
		function(data) {
			if(data.success) {
				localStorage.removeItem("comment_body-" + id);
				$("#comment-edit-preview-" + id).hide();
				$("#comment-edit-wrapper-" + id).hide();
				$("#comment-edit-text-" + id).val('');
				var tarea = document.getElementById("comment-edit-text-" + id);
				if(tarea) {
					commentClose(tarea, id);
					$(document).unbind( "click.commentOpen");
				}
				if(timer) clearTimeout(timer);
				timer = setTimeout(updateInit,1500);
			}
			if(data.reload) {
				window.location.href=data.reload;
			}
		},
		"json"
	);
	return false;
}

function preview_comment(id) {
	$("#comment-preview-inp-" + id).val("1");
	$("#comment-edit-preview-" + id).show();
	$.post(
		"item",
		$("#comment-edit-form-" + id).serialize(),
		function(data) {
			if(data.preview) {
				$("#comment-edit-preview-" + id).html(data.preview);
				$("#comment-edit-preview-" + id + " .autotime").timeago();
				$("#comment-edit-preview-" + id + " a").click(function() { return false; });
			}
		},
		"json"
	);
	return true;
}

function importElement(elem) {
	$.post(
		"impel",
		{ "element" : elem },
		function(data) {
			if(timer) clearTimeout(timer);
			timer = setTimeout(updateInit,10);
		}
	);
	return false;
}

function preview_post() {
	$("#jot-preview").val("1");
	$("#jot-preview-content").show();
	$.post(
		"item",
		$("#profile-jot-form").serialize(),
		function(data) {
			if(data.preview) {
				$("#jot-preview-content").html(data.preview);
				$("#jot-preview-content .autotime").timeago();
				$("#jot-preview-content" + " a").click(function() { return false; });
			}
		},
		"json"
	);
	$("#jot-preview").val("0");
	return true;
}

function preview_mail() {
	$("#mail-preview").val("1");
	$("#mail-preview-content").show();
	$.post(
		"mail",
		$("#prvmail-form").serialize(),
		function(data) {
			if(data.preview) {
				$("#mail-preview-content").html(data.preview);
				$("#mail-preview-content" + " a").click(function() { return false; });
			}
		},
		"json"
	);
	$("#mail-preview").val("0");
	return true;
}

function unpause() {
	// unpause auto reloads if they are currently stopped
	totStopped = false;
	stopped = false;
	$('#pause').html('');
}

function bin2hex(s) {
	// Converts the binary representation of data to hex    
	//   
	// version: 812.316  
	// discuss at: http://phpjs.org/functions/bin2hex  
	// +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)  
	// +   bugfixed by: Onno Marsman  
	// +   bugfixed by: Linuxworld  
	// *     example 1: bin2hex('Kev');  
	// *     returns 1: '4b6576'  
	// *     example 2: bin2hex(String.fromCharCode(0x00));  
	// *     returns 2: '00'  
	var v,i, f = 0, a = [];
	s += '';
	f = s.length;

	for (i = 0; i<f; i++) {
		a[i] = s.charCodeAt(i).toString(16).replace(/^([\da-f])$/,"0$1");
	}

	return a.join('');
}

function hex2bin(hex) {
	var bytes = [], str;

	for(var i=0; i< hex.length-1; i+=2)
		bytes.push(parseInt(hex.substr(i, 2), 16));

	return String.fromCharCode.apply(String, bytes);
}

function groupChangeMember(gid, cid, sec_token) {
	$('body .fakelink').css('cursor', 'wait');
	$.get('group/' + gid + '/' + cid + "?t=" + sec_token, function(data) {
		$('#group-update-wrapper').html(data);
		$('body .fakelink').css('cursor', 'auto');
	});
}

function profChangeMember(gid, cid) {
	$('body .fakelink').css('cursor', 'wait');
	$.get('profperm/' + gid + '/' + cid, function(data) {
		$('#prof-update-wrapper').html(data);
		$('body .fakelink').css('cursor', 'auto');
	});
}

function contactgroupChangeMember(gid, cid) {
	$('body').css('cursor', 'wait');
	$.get('contactgroup/' + gid + '/' + cid, function(data) {
		$('body').css('cursor', 'auto');
		$('#group-' + gid).toggleClass('fa-check-square-o fa-square-o');
	});
}

function checkboxhighlight(box) {
	if($(box).is(':checked')) {
		$(box).addClass('checkeditem');
	} else {
		$(box).removeClass('checkeditem');
	}
}

/**
 * sprintf in javascript
 *  "{0} and {1}".format('zero','uno');
 */
String.prototype.format = function() {
	var formatted = this;
	for (var i = 0; i < arguments.length; i++) {
		var regexp = new RegExp('\\{'+i+'\\}', 'gi');
		formatted = formatted.replace(regexp, arguments[i]);
	}
	return formatted;
};

// Array Remove
Array.prototype.remove = function(item) {
	to = undefined;
	from = this.indexOf(item);
	var rest = this.slice((to || from) + 1 || this.length);
	this.length = from < 0 ? this.length + from : from;
	return this.push.apply(this, rest);
};

function zFormError(elm,x) {
	if(x) {
		$(elm).addClass("zform-error");
		$(elm).removeClass("zform-ok");
	} else {
		$(elm).addClass("zform-ok");
		$(elm).removeClass("zform-error");
	}
}


$(window).scroll(function () {
	if(typeof buildCmd == 'function') {
		// This is a content page with items and/or conversations
		if($(window).scrollTop() + $(window).height() > $(document).height() - 300) {
			if((pageHasMoreContent) && (! loadingPage)) {
				next_page++;
				scroll_next = true;
				loadingPage = true;
				liveUpdate();
			}
		}
	}
	else {
		// This is some other kind of page - perhaps a directory
		if($(window).scrollTop() + $(window).height() > $(document).height() - 300) {
			if((pageHasMoreContent) && (! loadingPage) && (! justifiedGalleryActive)) {
				next_page++;
				scroll_next = true;
				loadingPage = true;
				pageUpdate();
			}
		}
	}
});

var chanviewFullSize = false;

function chanviewFull() {
	if(chanviewFullSize) {
		chanviewFullSize = false;
		$('#chanview-iframe-border').css({ 'position' : 'relative', 'z-index' : '10' });
		$('#remote-channel').css({ 'position' : 'relative' , 'z-index' : '10' });
	}
	else {
		chanviewFullSize = true;
		$('#chanview-iframe-border').css({ 'position' : 'fixed', 'top' : '0', 'left' : '0', 'z-index' : '150001' });
		$('#remote-channel').css({ 'position' : 'fixed', 'top' : '0', 'left' : '0', 'z-index' : '150000' });
		resize_iframe();
	}
}

function addhtmltext(data) {
	data = h2b(data);
	addeditortext(data);
}

function loadText(textRegion,data) {
	var currentText = $(textRegion).val();
	$(textRegion).val(currentText + data);
}

function addeditortext(data) {
	if(plaintext == 'none') {
		var currentText = $("#profile-jot-text").val();
		$("#profile-jot-text").val(currentText + data);
	}
}

function h2b(s) {
	var y = s;
	function rep(re, str) {
		y = y.replace(re,str);
	}

	rep(/<a.*?href=\"(.*?)\".*?>(.*?)<\/a>/gi,"[url=$1]$2[/url]");
	rep(/<span style=\"font-size:(.*?);\">(.*?)<\/span>/gi,"[size=$1]$2[/size]");
	rep(/<span style=\"color:(.*?);\">(.*?)<\/span>/gi,"[color=$1]$2[/color]");
	rep(/<font>(.*?)<\/font>/gi,"$1");
	rep(/<img.*?width=\"(.*?)\".*?height=\"(.*?)\".*?src=\"(.*?)\".*?\/>/gi,"[img=$1x$2]$3[/img]");
	rep(/<img.*?height=\"(.*?)\".*?width=\"(.*?)\".*?src=\"(.*?)\".*?\/>/gi,"[img=$2x$1]$3[/img]");
	rep(/<img.*?src=\"(.*?)\".*?height=\"(.*?)\".*?width=\"(.*?)\".*?\/>/gi,"[img=$3x$2]$1[/img]");
	rep(/<img.*?src=\"(.*?)\".*?width=\"(.*?)\".*?height=\"(.*?)\".*?\/>/gi,"[img=$2x$3]$1[/img]");
	rep(/<img.*?src=\"(.*?)\".*?\/>/gi,"[img]$1[/img]");

	rep(/<ul class=\"listbullet\" style=\"list-style-type\: circle\;\">(.*?)<\/ul>/gi,"[list]$1[/list]");
	rep(/<ul class=\"listnone\" style=\"list-style-type\: none\;\">(.*?)<\/ul>/gi,"[list=]$1[/list]");
	rep(/<ul class=\"listdecimal\" style=\"list-style-type\: decimal\;\">(.*?)<\/ul>/gi,"[list=1]$1[/list]");
	rep(/<ul class=\"listlowerroman\" style=\"list-style-type\: lower-roman\;\">(.*?)<\/ul>/gi,"[list=i]$1[/list]");
	rep(/<ul class=\"listupperroman\" style=\"list-style-type\: upper-roman\;\">(.*?)<\/ul>/gi,"[list=I]$1[/list]");
	rep(/<ul class=\"listloweralpha\" style=\"list-style-type\: lower-alpha\;\">(.*?)<\/ul>/gi,"[list=a]$1[/list]");
	rep(/<ul class=\"listupperalpha\" style=\"list-style-type\: upper-alpha\;\">(.*?)<\/ul>/gi,"[list=A]$1[/list]");
	rep(/<li>(.*?)<\/li>/gi,"[li]$1[/li]");

	rep(/<code>(.*?)<\/code>/gi,"[code]$1[/code]");
	rep(/<\/(strong|b)>/gi,"[/b]");
	rep(/<(strong|b)>/gi,"[b]");
	rep(/<\/(em|i)>/gi,"[/i]");
	rep(/<(em|i)>/gi,"[i]");
	rep(/<\/u>/gi,"[/u]");

	rep(/<span style=\"text-decoration: ?underline;\">(.*?)<\/span>/gi,"[u]$1[/u]");
	rep(/<u>/gi,"[u]");
	rep(/<blockquote[^>]*>/gi,"[quote]");
	rep(/<\/blockquote>/gi,"[/quote]");
	rep(/<hr \/>/gi,"[hr]");
	rep(/<br (.*?)\/>/gi,"\n");
	rep(/<br\/>/gi,"\n");
	rep(/<br>/gi,"\n");
	rep(/<p>/gi,"");
	rep(/<\/p>/gi,"\n");
	rep(/&nbsp;/gi," ");
	rep(/&quot;/gi,"\"");
	rep(/&lt;/gi,"<");
	rep(/&gt;/gi,">");
	rep(/&amp;/gi,"&");

	return y;
}

function b2h(s) {
	var y = s;
	function rep(re, str) {
		y = y.replace(re,str);
	}

	rep(/\&/gi,"&amp;");
	rep(/\</gi,"&lt;");
	rep(/\>/gi,"&gt;");
	rep(/\"/gi,"&quot;");

	rep(/\n/gi,"<br />");
	rep(/\[b\]/gi,"<strong>");
	rep(/\[\/b\]/gi,"</strong>");
	rep(/\[i\]/gi,"<em>");
	rep(/\[\/i\]/gi,"</em>");
	rep(/\[u\]/gi,"<u>");
	rep(/\[\/u\]/gi,"</u>");
	rep(/\[hr\]/gi,"<hr />");
	rep(/\[url=([^\]]+)\](.*?)\[\/url\]/gi,"<a href=\"$1\">$2</a>");
	rep(/\[url\](.*?)\[\/url\]/gi,"<a href=\"$1\">$1</a>");
	rep(/\[img=(.*?)x(.*?)\](.*?)\[\/img\]/gi,"<img width=\"$1\" height=\"$2\" src=\"$3\" />");
	rep(/\[img\](.*?)\[\/img\]/gi,"<img src=\"$1\" />");

	rep(/\[zrl=([^\]]+)\](.*?)\[\/zrl\]/gi,"<a href=\"$1" + '?f=&zid=' + zid + "\">$2</a>");
	rep(/\[zrl\](.*?)\[\/zrl\]/gi,"<a href=\"$1" + '?f=&zid=' + zid + "\">$1</a>");
	rep(/\[zmg=(.*?)x(.*?)\](.*?)\[\/zmg\]/gi,"<img width=\"$1\" height=\"$2\" src=\"$3" + '?f=&zid=' + zid + "\" />");
	rep(/\[zmg\](.*?)\[\/zmg\]/gi,"<img src=\"$1" + '?f=&zid=' + zid + "\" />");

	rep(/\[list\](.*?)\[\/list\]/gi, '<ul class="listbullet" style="list-style-type: circle;">$1</ul>');
	rep(/\[list=\](.*?)\[\/list\]/gi, '<ul class="listnone" style="list-style-type: none;">$1</ul>');
	rep(/\[list=1\](.*?)\[\/list\]/gi, '<ul class="listdecimal" style="list-style-type: decimal;">$1</ul>');
	rep(/\[list=i\](.*?)\[\/list\]/gi,'<ul class="listlowerroman" style="list-style-type: lower-roman;">$1</ul>');
	rep(/\[list=I\](.*?)\[\/list\]/gi, '<ul class="listupperroman" style="list-style-type: upper-roman;">$1</ul>');
	rep(/\[list=a\](.*?)\[\/list\]/gi, '<ul class="listloweralpha" style="list-style-type: lower-alpha;">$1</ul>');
	rep(/\[list=A\](.*?)\[\/list\]/gi, '<ul class="listupperalpha" style="list-style-type: upper-alpha;">$1</ul>');
	rep(/\[li\](.*?)\[\/li\]/gi, '<li>$1</li>');
	rep(/\[color=(.*?)\](.*?)\[\/color\]/gi,"<span style=\"color: $1;\">$2</span>");
	rep(/\[size=(.*?)\](.*?)\[\/size\]/gi,"<span style=\"font-size: $1;\">$2</span>");
	rep(/\[code\](.*?)\[\/code\]/gi,"<code>$1</code>");
	rep(/\[quote.*?\](.*?)\[\/quote\]/gi,"<blockquote>$1</blockquote>");

	rep(/\[video\](.*?)\[\/video\]/gi,"<a href=\"$1\">$1</a>");
	rep(/\[audio\](.*?)\[\/audio\]/gi,"<a href=\"$1\">$1</a>");

	rep(/\[\&amp\;([#a-z0-9]+)\;\]/gi,'&$1;');

	rep(/\<(.*?)(src|href)=\"[^hfm](.*?)\>/gi,'<$1$2="">');

	return y;
}

function zid(s) {
	if((! s.length) || (s.indexOf('zid=') != (-1)))
		return s;

	if(! zid.length)
		return s;

	var has_params = ((s.indexOf('?') == (-1)) ? false : true);
	var achar = ((has_params) ? '&' : '?');
	s = s + achar + 'f=&zid=' + zid;

	return s;
}
