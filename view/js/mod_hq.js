function hqLiveUpdate(notify_id, b64mid) {

	if(typeof profile_uid === 'undefined') profile_uid = false; /* Should probably be unified with channelId defined in head.tpl */
	if((src === null) || (stopped) || (! profile_uid)) { $('.like-rotator').hide(); return; }
	if(($('.comment-edit-text.expanded').length) || (in_progress)) {
		if(livetime) {
			clearTimeout(livetime);
		}
		livetime = setTimeout(liveUpdate, 10000);
		return;
	}
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

			if(notify_id !== 'undefined') {
			$.post(
				"hq",
				{
					"notify_id" : notify_id
				},
				function(data) {
					if(timer) clearTimeout(timer);
					timer = setTimeout(NavUpdate,10);
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

				// FIXME - the following lines were added so that almost
				// immediately after we update the posts on the page, we
				// re-check and update the notification counts.
				// As it turns out this causes a bit of an inefficiency
				// as we're pinging twice for every update, once before
				// and once after. A btter way to do this is to rewrite
				// NavUpdate and perhaps LiveUpdate so that we check for 
				// post updates first and only call the notification ping 
				// once. 

				updateCountsOnly = true;
				if(timer) clearTimeout(timer);
				timer = setTimeout(NavUpdate,10);

			});
		}
		else {
			page_load = false;
			scroll_next = false;
			updateConvItems(update_mode,data);
			$("#page-spinner").hide();
			$("#profile-jot-text-loading").hide();

			in_progress = false;

			// FIXME - the following lines were added so that almost
			// immediately after we update the posts on the page, we
			// re-check and update the notification counts.
			// As it turns out this causes a bit of an inefficiency
			// as we're pinging twice for every update, once before
			// and once after. A btter way to do this is to rewrite
			// NavUpdate and perhaps LiveUpdate so that we check for 
			// post updates first and only call the notification ping 
			// once. 

			updateCountsOnly = true;
			if(timer) clearTimeout(timer);
			timer = setTimeout(NavUpdate,10);

		}
	});
}
