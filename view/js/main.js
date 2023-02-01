
let src = null;
let prev = null;
let livetime = null;
let msie = false;
let stopped = false;
let totStopped = false;
let timer = null;
let alertstimer = null;
let pr = 0;
let liking = 0;
let in_progress = false;
let langSelect = false;
let commentBusy = false;
let last_popup_menu = null;
let last_popup_button = null;
let scroll_next = false;
let next_page = 1;
let page_load = true;
let loadingPage = true;
let pageHasMoreContent = true;
let divmore_height = 400;
let last_filestorage_id = null;
let mediaPlaying = false;
let contentHeightDiff = 0;
let liveRecurse = 0;
let savedTitle = '';
let initialLoad = true;
let cached_data = [];
let mode = '';
let update_url = '';
let update_mode = '';
let orgHeight = 0;

$.ajaxPrefilter(function( options, original_Options, jqXHR ) {
    options.async = true;
});
$.ajaxSetup({cache: false});

if ('serviceWorker' in navigator) {
	navigator.serviceWorker.register('/ServiceWorker.js', { scope: '/' }).then(function(registration) {
		console.log('Service worker registered. scope is', registration.scope);
	}).catch(function(error) {
		console.log('Service worker registration failed because ' + error);
	});
}

// Clear the session and local storage if we switch channel or log out
let cache_uid = '';
if(sessionStorage.getItem('uid') !== null) {
	cache_uid = sessionStorage.getItem('uid');
}
if(cache_uid !== localUser.toString()) {
	sessionStorage.clear();
	localStorage.clear();
	sessionStorage.setItem('uid', localUser.toString());
}


let region = 0;

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

	jQuery.timeago.settings.allowFuture = true;

	savedTitle = document.title;

	updateInit();
	alertsUpdate();
	
	$('a.notification-link').click(function(e){
		let notifyType = $(this).data('type');

		if(! $('#nav-' + notifyType + '-sub').hasClass('show')) {
			loadNotificationItems(notifyType);
			sessionStorage.setItem('notification_open', notifyType);
		}
		else {
			sessionStorage.removeItem('notification_open');
		}
	});

	if(sessionStorage.getItem('notification_open') !== null) {
		let notifyType = sessionStorage.getItem('notification_open');
		$('#nav-' + notifyType + '-sub').addClass('show');
		loadNotificationItems(notifyType);
	}


	$(document).on('z:handleNetWorkNotificationsItems', function(e, obj) {

//		push_notification(
//			obj.name,
//			$('<p>' + obj.message + '</p>').text(),
//			obj.b64mid
//		);
	});


	
	// Allow folks to stop the ajax page updates with the pause/break key
	$(document).keydown(function(event) {
		if(event.keyCode == '8') {
			let target = event.target || event.srcElement;
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
				$('#pause').html('<i class="fa fa-pause fa-fw"></i>');
			} else {
				unpause();
			}
		} else {
			if (!totStopped) {
				unpause();
			}
		}
	});

	let e = document.getElementById('content-complete');
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

function getSelectedText() { 
	let selectedText = ''; 
  
	// window.getSelection 
	if (window.getSelection) { 
		selectedText = window.getSelection(); 
	} 
	// document.getSelection 
	else if (document.getSelection) { 
		selectedText = document.getSelection(); 
	}
	// document.selection 
	else if (document.selection) { 
		selectedText =  document.selection.createRange().text; 
	} else return; 
    return selectedText;
} 

function commentCancel(commentId) {
	$("#comment-edit-text-" + commentId).val('');
	localStorage.removeItem("comment_body-" + commentId);		
}

function handle_comment_form(e) {
	e.stopPropagation();

	//handle eventual expanded forms
	let expanded = $('.comment-edit-text.expanded');
	let i = 0;

	if(expanded.length) {
		expanded.each(function() {
			let ex_form = $(expanded[i].form);
			let ex_fields = ex_form.find(':input[type=text], textarea');
			let ex_fields_empty = true;

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
	let form = $(this);
	let fields = form.find(':input[type=text], textarea');
	let fields_empty = true;
	let commentElm = false;
	
	if(form.find('.comment-edit-text').length) {
		commentElm = form.find('.comment-edit-text').attr('id');
		let commentId = commentElm.replace('comment-edit-text-','');
		let submitElm = commentElm.replace(/text/,'submit');

		$('#' + commentElm).addClass('expanded').removeAttr('placeholder');
		$('#' + commentElm).attr('tabindex','9');
		$('#' + submitElm).attr('tabindex','10');
		
		form.find(':not(:visible)').show();
		commentAuthors(commentId);
	}

	
	// handle click outside of form (close empty forms)
	$(document).on('click', function(e) {
		fields.each(function() {
			if($(this).val() != '')
				fields_empty = false;
		});
		if(fields_empty) {
			let emptyCommentElm = form.find('.comment-edit-text').attr('id');
        	let emptySubmitElm = commentElm.replace(/text/,'submit');

			$('#' + emptyCommentElm).removeClass('expanded').attr('placeholder', aStr.comment);
			$('#' + emptyCommentElm).removeAttr('tabindex');
			$('#' + emptySubmitElm).removeAttr('tabindex');
			form.find(':not(.comment-edit-text)').hide();
		}
	});
	
	let commentSaveTimer = null;
	let emptyCommentElm = form.find('.comment-edit-text').attr('id');
	let convId = emptyCommentElm.replace('comment-edit-text-','');

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

function doreply(parent, ident) {
	showHideCommentBox(parent);
	openClose('wall-item-comment-wrapper-' + ident.toString());
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
	let tmpStr = $("#comment-edit-text-" + id).val();
	if(tmpStr == '$comment') {
		tmpStr = '';
		$("#comment-edit-text-" + id).addClass("expanded");
		openMenu("comment-tools-" + id);
	}
	let ins = $(obj).html();
	ins = ins.replace('&lt;','<');
	ins = ins.replace('&gt;','>');
	ins = ins.replace('&amp;','&');
	ins = ins.replace('&quot;','"');
	$("#comment-edit-text-" + id).val(tmpStr + ins);
}

function commentAuthors(id) {
	$("#hidden-mentions-" + id).val($("#thread-authors-" + id).html());
}

function insertbbcomment(comment, BBcode, id) {
	// allow themes to override this
	if(typeof(insertFormatting) != 'undefined')
		return(insertFormatting(comment, BBcode, id));

	let tmpStr = $("#comment-edit-text-" + id).val();
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
		selected.text = "["+BBcode+"]" + selected.text + "[/"+BBcode+"]";
	} else if (textarea.selectionStart || textarea.selectionStart == "0") {
		let start = textarea.selectionStart;
		let end = textarea.selectionEnd;
		textarea.value = textarea.value.substring(0, start) + "["+BBcode+"]" + textarea.value.substring(start, end) + "[/"+BBcode+"]" + textarea.value.substring(end, textarea.value.length);
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
		let start = textarea.selectionStart;
		let end = textarea.selectionEnd;
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

// used by link modal to pass data to callbacks and still allow handler removal
let currentComment = null;
let currentID = null;

function insertCommentURL(comment, id) {
	textarea = document.getElementById("comment-edit-text-" +id);
    if (textarea.selectionStart || textarea.selectionStart == "0") {
       let start = textarea.selectionStart;
       let end = textarea.selectionEnd;	
       if (end > start) {
          reply = prompt(aStr['linkurl']);
          if(reply && reply.length) {
            textarea.value = textarea.value.substring(0, start) + "[url=" + reply + "]" + textarea.value.substring(start, end) + "[/url]" + textarea.value.substring(end, textarea.value.length);
          }
		   return true; 
       }
	}
	
	if ($('#jot-popup').length != 0) $('#jot-popup').show();

	currentComment = comment;
	currentID = id;
	
	$('#linkModal').modal('show');
	$('#id_link_url').focus();
	$('#link-modal-CancelButton').on('click', commentclearlinkmodal);
	$('#link-modal-OKButton').on('click', commentgetlinkmodal);

	return true;
}

function commentclearlinkmodal() {
	$('#link-modal-OKButton').off('click', commentgetlinkmodal);
	$('#link-modal-CancelButton').off('click', commentclearlinkmodal);
}

function commentgetlinkmodal() {
	let reply=$('#id_link_url').val();
	if(reply && reply.length) {
		let radioValue = $("input[name='link_style']:checked"). val();
		if(radioValue == '0') {
			reply = '!' + reply;
		}
		let optstr = '';
		let opts =  $("input[name='oembed']:checked"). val();
		if(opts) {
			optstr = optstr + '&oembed=1';
		}
		opts =  $("input[name='zotobj']:checked"). val();
		if(opts) {
			optstr = optstr + '&zotobj=1';
		}
		reply = bin2hex(reply);
		$('body').css('cursor', 'wait');
		$.get('linkinfo?f=&binurl=' + reply + optstr, function(data) {
			$('#linkModal').modal('hide');
			$("#comment-edit-text-" + currentID).focus();
			$("#comment-edit-text-" + currentID).addClass("expanded");
			openMenu("comment-tools-" + currentID);
			let tmpStr = $("#comment-edit-text-" + currentID).val();
	
			textarea = document.getElementById("comment-edit-text-" + currentID);
			textarea.value = textarea.value + data;
			preview_comment(currentID);
			$('#link-modal-OKButton').off('click', commentgetlinkmodal);
			$('#link-modal-CancelButton').off('click', commentclearlinkmodal);
			$('#id_link_url').val('');
			$('body').css('cursor', 'auto');
		});
	}
}

function doFollowAuthor(url) {
	$.get(url, function(data) { notificationsUpdate(); });
	return true;
}

function doPoke(xchan) {
	$.get('poke?xchan=' + xchan, function(data) { notificationsUpdate(); });
	return true;
}



function update_role_text() {
	let new_role = $("#id_permissions_role").val();
	if (typeof(new_role) !== 'undefined') {
		$("#channel_role_text").html(aStr[new_role]);
	}	
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


function collapseComments(id) {
	if( $('.collapsed-comments-' + id).is(':visible')) {
		$('.collapsed-comments-' + id + ' .autotime').timeago('dispose');
		$('.collapsed-comments-' + id).slideUp();
		$('#hide-comments-' + id).html(aStr.showmore);
		$('#hide-comments-total-' + id).show();
	} else {
		$('.collapsed-comments-' + id + ' .autotime').timeago();
		$('.collapsed-comments-' + id).slideDown();
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

function alertsUpdate() {
	
	let alertspingCmd = 'fastping' + ((localUser != 0) ? '?f=&uid=' + localUser : '');

	$.get(alertspingCmd,function(data) {
		if (! data) {
			return;
		}

		if(data.invalid == 1) {
			window.location.href=window.location.href;
		}

		$.jGrowl.defaults.closerTemplate = '<div>[ ' + aStr.closeAll + ']</div>';

		$(data.notice).each(function() { $.jGrowl(this.message, { sticky: false, theme: 'notice', life: 10000 }); });

		$(data.info).each(function() { $.jGrowl(this.message, { sticky: false, theme: 'info' }); });
	});

	if (alertstimer) {
		clearTimeout(alertstimer);
	}
	alertstimer = setTimeout(alertsUpdate,alertsInterval);
}




function notificationsUpdate(cached_data) {
	let pingCmd = 'ping' + ((localUser != 0) ? '?f=&uid=' + localUser : '');

	if(cached_data !== undefined) {
		handleNotifications(cached_data);
	} else {

		$.get(pingCmd,function(data) {

			if(! data) return;

			// Put the object into storage
			sessionStorage.setItem('notifications_cache', JSON.stringify(data));

			let fnotifs = []; if(data.forums) {
			$.each(data.forums_sub, function() { fnotifs.push(this);
			}); handleNotificationsItems('forums', fnotifs); }

			if(data.invalid == 1) {
				window.location.href=window.location.href; }

			handleNotifications(data);
		});
	}

	let notifyType = null; if($('.notification-content.show').length)
	{ notifyType = $('.notification-content.show').data('type'); }
	if(notifyType !== null) { loadNotificationItems(notifyType); }

	if(timer) clearTimeout(timer);
	timer = setTimeout(updateInit,updateInterval);
}
					  
function handleNotifications(data) {
	if(data.stream || data.home || data.intros || data.register || data.mail || data.all_events || data.notify || data.files || data.pubs || data.forums || data.moderate) {
		$('.notifications-btn').css('opacity', 1);
		$('#no_notifications').hide();
        $('#notifications_wrapper').show();
	}
	else {
		$('.notifications-btn').css('opacity', 0.5);
		$('#navbar-collapse-1').removeClass('show');
		$('#no_notifications').show();
        $('#notifications_wrapper').hide();
        sessionStorage.removeItem('notifications_cache');

	}

	if(data.home || data.intros || data.register || data.mail || data.notify || data.files || data.moderate) {
		$('.notifications-btn-icon').removeClass('fa-exclamation-circle');
		$('.notifications-btn-icon').addClass('fa-exclamation-triangle');        
	}
	if(!data.home && !data.intros && !data.register && !data.mail && !data.notify && !data.files && !data.moderate) {
		$('.notifications-btn-icon').removeClass('fa-exclamation-triangle');
		$('.notifications-btn-icon').addClass('fa-exclamation-circle');
	}
	if(data.all_events_today) {
		$('.all_events-update').removeClass('badge').removeClass('bg-secondary');
		$('.all_events-update').addClass('badge').addClass('bg-danger');;
	}
	else {
		$('.all_events-update').removeClass('badge').removeClass('bg-danger');
		$('.all_events-update').addClass('badge').addClass('bg-secondary');
	}

	
	$.each(data, function(index, item) {
		//do not process those
		let arr = ['invalid'];
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
	let notifications_tpl = ((notifyType == 'forums') ? unescape($("#nav-notifications-forums-template[rel=template]").html()) : unescape($("#nav-notifications-template[rel=template]").html()));
	let notify_menu = $("#nav-" + notifyType + "-menu");

	notify_menu.html('');

	$(data).each(function() {
		if (notifyType == 'notify') {
			$(document).trigger('z:handleNetWorkNotificationsItems', this);
		}
			
		html = notifications_tpl.format(this.notify_link,this.photo,this.name,this.addr,this.message,this.when,this.hclass,this.b64mid,this.notify_id,this.thread_top,this.unseen,this.private_forum);
		notify_menu.append(html);
	});

	datasrc2src('#notifications .notification img[data-src]');

	if($('#tt-' + notifyType + '-only').hasClass('active'))
		$('#nav-' + notifyType + '-menu [data-thread_top=false]').hide();

	if($('#cn-' + notifyType + '-input').length) {
		let filter = $('#cn-' + notifyType + '-input').val().toString().toLowerCase();
		if(filter) {
			$('#nav-' + notifyType + '-menu .notification').each(function(i, el){
				let cn = $(el).data('contact_name').toString().toLowerCase();
				if(cn.indexOf(filter) === -1)
					$(el).addClass('d-none');
				else
					$(el).removeClass('d-none');
			});
		}
	}
}

function contextualHelp() {
	let container = $("#contextual-help-content");

	if(container.hasClass('contextual-help-content-open')) {
		container.removeClass('contextual-help-content-open');
		$('main').css('margin-top', '')
	}
	else {
		container.addClass('contextual-help-content-open');
		let mainTop = container.outerHeight(true);
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

	    let css_position = $(target).parent().css('position');
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

	let e = document.getElementById('content-complete');
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

		let ident = $(this).attr('id');
		let convId = ident.replace('thread-wrapper-','');
		let commentWrap = $('#'+ident+' .collapsed-comments').attr('id');


		let itmId = 0;
		let isVisible = false;

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

		let commentBody = localStorage.getItem("comment_body-" + convId);

		if(commentBody) {
			commentElm = $('#comment-edit-text-' + convId);
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

		$(".pinned .autotime").timeago();
		$("> .wall-item-outside-wrapper .autotime, > .thread-wrapper .autotime",this).timeago();
		$("> .shared_header .autotime",this).timeago();
		
		if((mode === 'append' || mode === 'replace') && (loadingPage)) {
			loadingPage = false;
		}

		// if single thread view and  the item has a title, display it in the title bar

		if(mode === 'replace') {
			if (window.location.search.indexOf("mid=") != -1 || window.location.pathname.indexOf("display") != -1) {
				let title = $(".wall-item-title").text();
				title.replace(/^\s+/, '');
				title.replace(/\s+$/, '');
				if (title) {
					savedTitle = title + " " + savedTitle;
					document.title = title;
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
	$(".comment-edit-form  textarea").editor_autocomplete(baseurl+"/acloader?f=&n=1");
	/* autocomplete bbcode */
	$(".comment-edit-form  textarea").bbco_autocomplete('bbcode');

	let bimgs = ((preloadImages) ? false : $(".wall-item-body img, .wall-photo-item img").not(function() { return this.complete; }));
	let bimgcount = bimgs.length;

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

	let submid = ((bParam_mid.length) ? bParam_mid : 'abcdefg');
	let encoded = ((submid.substr(0,4) == 'b64.') ? true : false);
	let submid_encoded = ((encoded) ? submid.substr(4) : window.btoa(submid));

    submid_encoded = submid_encoded.replace(/[\+\=]/g,'');
	if($('.item_' + submid_encoded).length && !$('.item_' + submid_encoded).hasClass('toplevel_item') && mode == 'replace') {
		if($('.collapsed-comments').length) {
			let scrolltoid = $('.collapsed-comments').attr('id').substring(19);
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
	let origContentHeight = Math.ceil($("#region_2").height());
	let cDiff = 0;
	let i = 0;
	let position = $(window).scrollTop();

	$(".wall-item-content, .directory-collapse").each(function() {
		orgHeight = $(this).outerHeight(true);
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

	let collapsedContentHeight = Math.ceil($("#region_2").height());
	contentHeightDiff = liking ? 0 : origContentHeight - collapsedContentHeight;

	console.log('collapseHeight() - contentHeightDiff: ' + contentHeightDiff + 'px');

	if(i && ! liking){
		let sval = position - cDiff + ($(".divgrow-showmore").outerHeight() * i);
		console.log('collapsed above viewport count: ' + i);
		$(window).scrollTop(sval);
	}
	
}

function updateInit() {

	if($('#live-stream').length)     { src = 'stream'; }
	if($('#live-channel').length)    { src = 'channel'; }
	if($('#live-pubstream').length)  { src = 'pubstream'; }
	if($('#live-display').length)    { src = 'display'; }
	if($('#live-hq').length)         { src = 'hq'; }
	if($('#live-search').length)     { src = 'search'; }

	if (initialLoad && (sessionStorage.getItem('notifications_cache') !== null)) {
		cached_data = JSON.parse(sessionStorage.getItem('notifications_cache'));
		notificationsUpdate(cached_data);

		let fnotifs = [];
		if(cached_data.forums) {
			$.each(cached_data.forums_sub, function() {
				fnotifs.push(this);
			});
			handleNotificationsItems('forums', fnotifs);
		}

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

	let origHeight = 0;
	let expanded = $('.comment-edit-text.expanded');

	
	if(typeof profile_uid === 'undefined') profile_uid = false;

	if((src === null) || (! profile_uid)) { $('.like-rotator').hide(); return; }

	if(in_progress || mediaPlaying || expanded.length || stopped) {
		console.log('liveUpdate: deferred');
		if(livetime) {
			clearTimeout(livetime);
		}
		livetime = setTimeout(liveUpdate, 10000);
		return;
	}
	console.log('liveUpdate');
	if(timer)
		clearTimeout(timer);

	if(livetime !== null)
		livetime = null;

	prev = 'live-' + src;

	in_progress = true;


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
		origHeight = $("#region_2").height();
	}

	let dstart = new Date();
	console.log('LOADING data...' + update_url);
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

		let dready = new Date();
		console.log('DATA ready in: ' + (dready - dstart)/1000 + ' seconds.');

		if(update_mode === 'update' || preloadImages) {
			console.log('LOADING images...');

			$('.wall-item-body, .wall-photo-item',data).imagesLoaded( function() {
				let iready = new Date();
				console.log('IMAGES ready in: ' + (iready - dready)/1000 + ' seconds.');

				page_load = false;
				scroll_next = false;
				updateConvItems(update_mode,data);
				$("#page-spinner").hide();
				$("#profile-jot-text-loading").hide();

				// adjust scroll position if new content was added above viewport
				if(update_mode === 'update' && !justifiedGalleryActive) {
					$(window).scrollTop($(window).scrollTop() + $("#region_2").height() - origHeight + contentHeightDiff);
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


	if(scroll_next) {
		bParam_page = next_page;
		page_load = true;
	}
	else {
		bParam_page = 1;
	}

	update_url = baseurl + '/' + decodeURIComponent(page_query) + '/?f=&aj=1&page=' + bParam_page + extra_args ;
	
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
		selector: 'a, div:not(#page-end)',
		margins: 3,
		border: 0
	}).on('jg.complete', function(e){ justifiedGalleryActive = false; });
}

function justifyPhotosAjax(id) {
	justifiedGalleryActive = true;
	$('#' + id).justifiedGallery('norewind').on('jg.complete', function(e){ justifiedGalleryActive = false; });
}

function loadNotificationItems(notifyType) {
	let pingExCmd = 'ping/' + notifyType + ((localUser != 0) ? '?f=&uid=' + localUser : '');

	let clicked = $('[data-type=\'' + notifyType + '\']').data('clicked');

	if((clicked === undefined) && (sessionStorage.getItem(notifyType + '_notifications_cache') !== null)) {
		cached_data = JSON.parse(sessionStorage.getItem(notifyType + '_notifications_cache'));
		handleNotificationsItems(notifyType, cached_data);
		$('[data-type=\'' + notifyType + '\']').data('clicked',true);
		console.log('updating ' + notifyType + ' notifications from cache...');
	}
	else {
		cached_data = [];
	}

	console.log('updating ' + notifyType + ' notifications...');

	$.get(pingExCmd, function(data) {
		if(data.invalid == 1) {
			window.location.href=window.location.href;
		}

		if(JSON.stringify(cached_data[0]) === JSON.stringify(data.notify[0])) {
			console.log(notifyType + ' notifications cache up to date');
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
	$.get('plike/' + ident + '?verb=' + verb, function() { window.location.href=window.location.href; });
}


function dopin(id) {
	id = id.toString();
	$('#like-rotator-' + id).show();
	$.post('pin', { 'id' : id })
		.done(function() {
			window.location.href=window.location.href;
		})
		.fail(function() {
			window.location.href=window.location.href;
		})
}

function dropItem(url, object) {
	let confirm = confirmDelete();
	if(confirm) {
		let id = url.split('/')[2];
		$('body').css('cursor', 'wait');
		$(object + ', #pinned-wrapper-' + id).fadeTo('fast', 0.33, function () {
			$.post('pin', { 'id' : id });
			$.get(url).done(function() {
				$(object + ', #pinned-wrapper-' + id).remove();
				$('body').css('cursor', 'auto');
			});
		});
		return true;
	}
	else {
			return false;
	}
}


function dropItem(url, object) {

	let confirm = confirmDelete();
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
			let btn_tpl = '<div class="btn-group" id="star-button-' + ident + '"><button type="button" class="btn btn-outline-secondary btn-sm wall-item-like" onclick="dostar(' + ident + ');"><i class="fa fa-star"></i></button></div>'
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
	let cursor = {x:0, y:0};
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

function submitPoll(id) {

	$.post('vote/' + id,
		$('#question-form-' + id).serialize(),
		function(data) {
			$.jGrowl(data.message, { sticky: false, theme: ((data.success) ? 'info' : 'notice'), life: 10000 });
			if(timer) clearTimeout(timer);
			timer = setTimeout(updateInit,1500);
		}
	);

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
				let tarea = document.getElementById("comment-edit-text-" + id);
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

function save_draft() {
	$("#jot-draft").val("1");
	$.post(
		"item",
		$("#profile-jot-form").serialize(),
		function() {
			itemCancel();
			document.location.href=document.location.href;
		},
	);
	return true;
}

function save_draft_comment(id) {
	$("#comment-draft-" + id).val("1");
	$.post(
		"item",
		$("#comment-edit-form-" + id).serialize(),
		function() {
			commentCancel(id);
			document.location.href=document.location.href;
		},
	);
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
	let v,i, f = 0, a = [];
	s += '';
	f = s.length;

	for (i = 0; i<f; i++) {
		a[i] = s.charCodeAt(i).toString(16).replace(/^([\da-f])$/,"0$1");
	}

	return a.join('');
}

function hex2bin(hex) {
	let bytes = [], str;

	for(let i=0; i< hex.length-1; i+=2)
		bytes.push(parseInt(hex.substr(i, 2), 16));

	return String.fromCharCode.apply(String, bytes);
}

function groupChangeMember(gid, cid, sec_token) {
	$('body .fakelink').css('cursor', 'wait');
	$.get('lists/' + gid + '/' + cid + "?t=" + sec_token, function(data) {
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
	let formatted = this;
	for (let i = 0; i < arguments.length; i++) {
		let regexp = new RegExp('\\{'+i+'\\}', 'gi');
		formatted = formatted.replace(regexp, arguments[i]);
	}
	return formatted;
};

// Array Remove
Array.prototype.remove = function(item) {
	to = undefined;
	from = this.indexOf(item);
	let rest = this.slice((to || from) + 1 || this.length);
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

let chanviewFullSize = false;

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
	let currentText = $(textRegion).val();
	$(textRegion).val(currentText + data);
}

function addeditortext(data) {
	if(plaintext == 'none') {
		let textarea = document.getElementById('profile-jot-text');
		if (textarea) {
			textarea.value = textarea.value + data
			let evt = new CustomEvent('input');
			textarea.dispatchEvent(evt);
		}
	}
}

function h2b(s) {
	let y = s;
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
	let y = s;
	function rep(re, str) {
		y = y.replace(re,str);
	}

	rep(/\&/gi,"&amp;");
	rep(/\</gi,"&lt;");
	rep(/\>/gi,"&gt;");
	rep(/\"/gi,"&quot;");

	rep(/\n/gi,"<br>");
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

function dozid(s) {
	if((! s.length) || (s.indexOf('zid=') != (-1)))
		return s;

	if(! zid.length)
		return s;

	let has_params = ((s.indexOf('?') == (-1)) ? false : true);
	let achar = ((has_params) ? '&' : '?');
	s = s + achar + 'f=&zid=' + zid;

	return s;
}

function push_notification_request(e) {
    if ('Notification' in window) {
		if (Notification.permission !== 'granted') {
        	Notification.requestPermission(function(permission) {
				if(permission === 'granted') {
					$(e.target).closest('div').hide();
				}
			});
		}
    }
}

function push_notification(title, body, b64mid) {
	let options = {
		body: body,
		data: b64mid,
		icon: aStr.icon,
		silent: false
	}

	let n = new Notification(title, options);
	n.onclick = function (e) {
		window.location.href = baseurl + '/display/?mid=' + e.target.data;
	}
}
