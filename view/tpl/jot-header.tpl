<script src="vendor/blueimp/jquery-file-upload/js/vendor/jquery.ui.widget.js"></script>
<script src="vendor/blueimp/jquery-file-upload/js/jquery.iframe-transport.js"></script>
<script src="vendor/blueimp/jquery-file-upload/js/jquery.fileupload.js"></script>
<script type="text/javascript">

let editor = false;
let plaintext = '{{$editselect}}';
let pretext = '{{$pretext}}';

let activeCommentID = 0;
let activeCommentText = '';
let postSaveTimer = null;

    $(document).on( "click", ".wall-item-delete-link,.page-delete-link,.layout-delete-link,.block-delete-link", function(e) {
        let link = $(this).attr("href"); // "get" the intended link in a let

        if (typeof(eval($.fn.modal)) === 'function'){
            e.preventDefault();
            bootbox.confirm("<h4>{{$confirmdelete}}</h4>",function(result) {
                if (result) {
                    document.location.href = link;
                }
            });
        } else {
            return confirm("{{$confirmdelete}}");
        }
    });

    $(document).ready(function() {

		let cleaned = false;

		if({{$auto_save_draft}}) {
			let doctype = $('#jot-webpage').val();
			let postid = '-' + doctype + '-' + $('#jot-postid').val();
			let postTitle = localStorage.getItem("post_title" + postid);
			let postBody = localStorage.getItem("post_body" + postid);
			let postCategory = (($("#jot-category").length) ? localStorage.getItem("post_category" + postid) : '');
			let openEditor = false;

			if(postTitle) {
				$('#jot-title').val(postTitle);
				openEditor = true;
			}
			if(postBody) {
				$('#profile-jot-text').val(postBody);
				openEditor = true;
			}
			if(postCategory) {
				let categories = postCategory.split(',');
				categories.forEach(function(cat) {
					$('#jot-category').tagsinput('add', cat);
				});
				openEditor = true;
			}
			if(openEditor) {
				enableOnUser();
			}
		} else {
			postSaveChanges('clean');
		}

		$(document).on('submit', '#profile-jot-form', function() {
			postSaveChanges('clean');
			cleaned = true;
		});

		$(document).on('focusout',"#profile-jot-wrapper",function(e){
			if(! cleaned)
				postSaveChanges('stop');
		});

		$(document).on('focusin',"#profile-jot-wrapper",function(e){
			postSaveTimer = setTimeout(function () {
				postSaveChanges('start');
			},10000);
		});
	});

	$(document).ready(function() {
	
		$("#profile-jot-text").focus(enableOnUser);
		$("#profile-jot-text").click(enableOnUser);

		$('#id_mimetype').on('load', jotSetMime);
		$('#id_mimetype').on('change', jotSetMime);

		$("input[name='link_style']").change(function() {
			let radioValue = $("input[name='link_style']:checked"). val();
			if(radioValue == '0') {
				$("#linkmodaldiscover").hide();
			}
			else {
				$("#linkmodaldiscover").show();
			}
		});

        jotLocateStatus();
        jotCheckinStatus();
        jotCheckoutStatus();

		$('#jot-add-option').on('click', jotAddOption);
		$(document).on('click', '.poll-option-close', jotRemoveOption);

		function jotSetMime() { 
			let mtype = $('#id_mimetype').val(); 
			if(mtype == 'text/bbcode' || mtype == 'text/x-multicode')
				$('#profile-jot-submit-left').show();
			else
				$('#profile-jot-submit-left').hide();
		}

		/**
		 * uses the jQuery file upload plugin to upload files.
		 * It is initialized on an element with an ID of "invisible-wall-file-upload" using the .fileupload() method.
		 * It is configured with several options passed as an object to the .fileupload() method:
		 * url: specifies the server endpoint to which the uploaded file will be sent. It includes a PHP variable $nickname in the URL path.
		 * dataType: specifies the expected format of the response from the server, which is JSON in this case.
		 * dropZone: specifies the element that is used as the drop zone for the file upload. In this case, it's the element with an ID of "profile-jot-text".
		 * maxChunkSize: specifies the maximum chunk size for chunked file uploads. It's set to 2 megabytes in this case.
		 * add: specifies a callback function to be executed when a file is added to the queue.
		 * In this case, the function shows a rotating icon to indicate that the upload is in progress and submits the file for uploading.
		 * done: specifies a callback function to be executed when the upload is complete.
		 * In this case, the function appends the file's URL to a text area with an ID of "jot-media" 
		 * and adds the file's URL to the text editor by calling a function named addeditortext().
		 * stop: specifies a callback function to be executed when the upload is stopped, 
		 * either because it's completed or because it was cancelled. 
		 * In this case, the function hides the rotating icon and calls a function named preview_post(), which previews the uploaded file in the user's post.
		 *  
		 */

		$('#invisible-wall-file-upload').fileupload({
			url: 'wall_attach/{{$nickname}}',
			dataType: 'json',
			dropZone: $('#profile-jot-text'),
			maxChunkSize: 2 * 1024 * 1024,
			add: function(e,data) {
				// console.log(e);
				// console.log(data);
				$('#profile-rotator').show();
				data.submit();
			},
			done: function(e,data) {
				addeditortext(data.result.message);
				$('#jot-media').val($('#jot-media').val() + data.result.message);
			},
			stop: function(e,data) {
				preview_post();
				$('#profile-rotator').hide();
			},
		});

		$('#wall-file-upload').click(function(event) { event.preventDefault(); $('#invisible-wall-file-upload').trigger('click'); return false;});
		$('#wall-file-upload-sub').click(function(event) { event.preventDefault(); $('#invisible-wall-file-upload').trigger('click'); return false;});

		/* start new */

		$('#wall-file-upload-1').click(function(event) { event.preventDefault(); $('#invisible-wall-file-upload').trigger('click'); return false;});
		$('#wall-file-upload-sub').click(function(event) { event.preventDefault(); $('#invisible-wall-file-upload').trigger('click'); return false;});

		/* end new */

        // call initialization file
        if (window.File && window.FileList && window.FileReader) {
			DragDropUploadInit();
        }


		$('#invisible-comment-upload').fileupload({
			url: 'wall_attach/{{$nickname}}',
			dataType: 'json',
			dropZone: $(),
			maxChunkSize: 2 * 1024 * 1024,
			add: function(e,data) {
				let tmpStr = $("#comment-edit-text-" + activeCommentID).val();
				if(tmpStr == activeCommentText) {
					tmpStr = "";
					$("#comment-edit-text-" + activeCommentID).addClass("comment-edit-text-full");
					$("#comment-edit-text-" + activeCommentID).removeClass("comment-edit-text-empty");
					openMenu("comment-tools-" + activeCommentID);
					$("#comment-edit-text-" + activeCommentID).val(tmpStr);
				}
				data.submit();
			},

			done: function(e,data) {
				textarea = document.getElementById("comment-edit-text-" + activeCommentID);
				if (textarea != null ) {
					textarea.value = textarea.value + data.result.message;
				}
			},
			stop: function(e,data) {
				$('body').css('cursor', 'auto');
				preview_comment(activeCommentID);
				activeCommentID = 0;
			},
		});

	});

    function initEditor(cb){
        if(editor == false){
            $("#profile-jot-text-loading").show();
            $("#profile-jot-reset").removeClass('d-none');
            {{$geotag}}
            if(plaintext == 'none') {
                $("#profile-jot-text-loading").hide();
                $(".jothidden").show();
                $("#profile-jot-text").addClass('jot-expanded');
                $("#profile-jot-summary").addClass('jot-expanded');

                /*
                let bodytextarea = document.querySelector('#profile-jot-text');
                if (typeof bodytextarea != "undefined") {
                    bodytextarea.addEventListener('input', function handlechange(event) {
                        imagewatcher(event)
                    });
                }
                */
                
                {{if $bbco_autocomplete}}
                $("#profile-jot-text").bbco_autocomplete('{{$bbco_autocomplete}}'); // autocomplete bbcode
                $("#profile-jot-summary").bbco_autocomplete('{{$bbco_autocomplete}}'); // autocomplete bbcode
                {{/if}}
                {{if $editor_autocomplete}}
                if(typeof channelId === 'undefined') {
                    $("#profile-jot-text").editor_autocomplete(baseurl+"/acloader");
                    $("#profile-jot-summary").editor_autocomplete(baseurl+"/acloader");
                }
                else {
                    $("#profile-jot-text").editor_autocomplete(baseurl+"/acloader",[channelId]); // Also gives suggestions from current channel's connections
                    $("#profile-jot-summary").editor_autocomplete(baseurl+"/acloader",[channelId]); // Also gives suggestions from current channel's connections
                }
                {{/if}}
                editor = true;
                if (typeof cb!="undefined") cb();
                if(pretext.length)
                    addeditortext(pretext);
                return;
            }
                editor = true;
        } else {
            if (typeof cb!="undefined") cb();
        }
    }

    function imagewatcher(event) {
        let imgfind = /\[[iz]mg.*?alt="(.*?)".*?](.*?)\[/.exec(event.target.value)
        console.log(imgfind);
    }
    function enableOnUser(){
        if(editor)
            return;

        initEditor();
    }

	function deleteCheckedItems() {
		let checkedstr = '';

		$('.item-select').each( function() {
			if($(this).is(':checked')) {
				if(checkedstr.length != 0) {
					checkedstr = checkedstr + ',' + $(this).val();
				}
				else {
					checkedstr = $(this).val();
				}
			}
		});
		$.post('item', { dropitems: checkedstr }, function(data) {
			window.location.reload();
		});
	}

	function jotGetLink() {
		textarea = document.getElementById('profile-jot-text');
		if (textarea.selectionStart || textarea.selectionStart == "0") {
			let start = textarea.selectionStart;
			let end = textarea.selectionEnd;	
			if (end > start) {
				let reply = prompt("{{$linkurl}}");
				if(reply && reply.length) {
					textarea.value = textarea.value.substring(0, start) + "[url=" + reply + "]" + textarea.value.substring(start, end) + "[/url]" + textarea.value.substring(end, textarea.value.length);
				}
				return true;
			}
		}
		$('#linkModal').modal('show');
		$('#id_link_url').focus();
		$('#link-modal-OKButton').on('click',jotgetlinkmodal);
		$('#link-modal-CancelButton').on('click',jotclearmodal);
	}

	function jotLocateStatus() {
		if($('#jot-lat').val() || $('#jot-lon').val() || $('#jot-location').val()) {
            $('#profile-nolocation-wrapper').attr('disabled', false);
            $('#profile-nolocation-wrapper').show();
        }
        else {
            $('#profile-nolocation-wrapper').attr('disabled', true);
            $('#profile-nolocation-wrapper').hide();
        }
    }

	function jotclearmodal() {
		$('#link-modal-OKButton').off('click',jotgetlinkmodal);
		$('#link-modal-CancelButton').off('click',jotclearmodal);
	}

	function jotgetlinkmodal() {
		let reply = $('#id_link_url').val();

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
			$('#profile-rotator').show();
			$.get('{{$baseurl}}/linkinfo?f=&binurl=' + reply + optstr, function(data) {
				addeditortext(data);
				preview_post();
				$('#id_link_url').val('');
				$('#link-modal-OKButton').off('click',jotgetlinkmodal);
				$('#link-modal-CancelButton').off('click',jotclearmodal);
				$('#profile-rotator').hide();
			});
	
			$('#linkModal').modal('hide');
		}
	}

	function jotGetLocation() {
		let reply = prompt("{{$whereareu}}", $('#jot-location').val());
		if(reply && reply.length) {
			// A single period indicates "use my browser location"
		    if(reply == '.') {
			    if(navigator.geolocation) {
				    reply = '';
					navigator.geolocation.getCurrentPosition(function(position) {
					    $('#jot-lat').val(position.coords.latitude);
                        $('#jot-lon').val(position.coords.longitude);
                        jotLocateStatus();
	                    jotCheckinStatus();
	                    jotCheckoutStatus();
				    });
			    }
			}
			else {
		        $('#jot-location').val(reply);
		        jotLocateStatus();
		        jotCheckinStatus();
	            jotCheckoutStatus();
			}
		}
	}

	function superblock(author,item) {
		$.get('superblock?f=&item=' + item + '&block=' + author, function(data) {
			location.reload(true);
		});
	}

	function blocksite(author) {
		$.get('superblock?f=&blocksite=' + author, function(data) {
			location.reload(true);
		});
	}


	function jotGetExpiry() {
		//reply = prompt("{{$expirewhen}}", $('#jot-expire').val());
		$('#expiryModal').modal('show');
		$('#expiry-modal-OKButton').on('click', function() {
			reply=$('#expiration-date').val();
			if(reply && reply.length) {
				$('#jot-expire').val(reply);
				$('#expiryModal').modal('hide');
			}
		})
	}

	function jotGetCommCtrl() {
		$('#commModal').modal('show');
		$('#comm-modal-OKButton').on('click', function() {

			let comment_state = $("input[name='comments_allowed']:checked").val();
			if (comment_state && comment_state.length) {
				$('#jot-commentstate').val(comment_state);
			}
			else {
				$('#jot-commentstate').val(0);
			}				
			
			let post_comments = $('#id_post_comments').val();
			if (post_comments && post_comments.length) {
				$('#jot-commfrom').val(post_comments);
			}
			let reply=$('#commclose-date').val();
			if(reply && reply.length) {
				$('#jot-commclosed').val(reply);
			}
			$('#commModal').modal('hide');
		})
	}

	/**
	 *  opens a modal window with an ID of "createdModal" 
	 *  and listens for a click event on a button with an ID of "created-modal-OKButton". 
	 *  When the button is clicked, it retrieves the value of an input field with an ID of "created-date" 
	 *  and if it is not empty, it sets the value of an input field with an ID of "jot-created" 
	 *  to the retrieved value and hides the modal window.
	 */
	
	function jotGetPubDate() {
		$('#createdModal').modal('show');
		$('#created-modal-OKButton').on('click', function() {
			reply=$('#created-date').val();
			if(reply && reply.length) {
				$('#jot-created').val(reply);
				$('#createdModal').modal('hide');
			}
		})
	}


	function jotShare(id,post_type) {
		$('#like-rotator-' + id).show();
		$.get('{{$baseurl}}/share/' + id, function(data) {
			$('#like-rotator-' + id).hide();
			updateInit();
		});
	}

	function jotCheckin() {
	    let checkinVal = 1 - $('#jot-checkin').val();
	    $('#jot-checkin').val(checkinVal);
	    if (checkinVal) {
	        $('#profile-checkin-wrapper').addClass('_orange');
	    }
	    else {
	          $('#profile-checkin-wrapper').removeClass('_orange');
	    }
	}

    function jotCheckinStatus() {
        let checkinVal = parseInt($('#jot-checkin').val());
        if (checkinVal > 0) {
            $('#profile-checkin-wrapper').addClass('_orange');
        }
        else {
            $('#profile-checkin-wrapper').removeClass('_orange');
        }
        if ($('#jot-lat').val() && $('#jot-lon').val()) {
            $('#profile-checkin-wrapper').show();
        } else {
            $('#profile-checkin-wrapper').hide();
        }
    }

    function jotCheckout() {
        let checkoutVal = 1 - $('#jot-checkout').val();
        $('#jot-checkout').val(checkoutVal);
        if (checkoutVal) {
            $('#profile-checkout-wrapper').addClass('_orange');
        }
        else {
            $('#profile-checkout-wrapper').removeClass('_orange');
        }
    }

    function jotCheckoutStatus() {
        let checkoutVal = parseInt($('#jot-checkout').val());
        if (checkoutVal > 0) {
            $('#profile-checkout-wrapper').addClass('_orange');
        }
        else {
            $('#profile-checkout-wrapper').removeClass('_orange');
        }
        if ($('#jot-lat').val() && $('#jot-lon').val()) {
            $('#profile-checkout-wrapper').show();
        } else {
            $('#profile-checkout-wrapper').hide();
        }
    }

    function jotEmbed(id,post_type) {

		if ($('#jot-popup').length != 0) $('#jot-popup').show();

		$('#like-rotator-' + id).show();
		$.get('{{$baseurl}}/embed/' + id, function(data) {
			if (!editor) $("#profile-jot-text").val("");
			initEditor(function(){
				addeditortext(data);
				$('#like-rotator-' + id).hide();
				$(window).scrollTop(0);
			});
		});

	}

	function linkdropper(event) {
		let linkFound = ((event.dataTransfer.types.indexOf("text/uri-list") > -1) ? true : false);
		if(linkFound) {
			event.preventDefault();
			let editwin = '#' + event.target.id;
			let commentwin = false;
			if(editwin) {
				commentwin = ((editwin.indexOf('comment') >= 0) ? true : false);
				if(commentwin) {
					let commentid = editwin.substring(editwin.lastIndexOf('-') + 1);
					$('#comment-edit-text-' + commentid).addClass('hover');
				}
			}
		}
	}

	function linkdropexit(event) {
		let editwin = '#' + event.target.id;
		let commentwin = false;
		if(editwin) {
			commentwin = ((editwin.indexOf('comment') >= 0) ? true : false);
			if(commentwin) {
				let commentid = editwin.substring(editwin.lastIndexOf('-') + 1);
				$('#comment-edit-text-' + commentid).removeClass('hover');
			}
		}
	}

	function linkdrop(event) {
		let reply = event.dataTransfer.getData("text/uri-list");
		if(reply) {
			event.preventDefault();
			let editwin = '#' + event.target.id;
			let commentwin = false;
			if(editwin) {
				commentwin = ((editwin.indexOf('comment') >= 0) ? true : false);
				if(commentwin) {
					let commentid = editwin.substring(editwin.lastIndexOf('-') + 1);
					$("#comment-edit-text-" + commentid).addClass("expanded");
				}
			}
		}

		if(reply && reply.length) {
			reply = bin2hex(reply);
			$('#profile-rotator').show();
			$.get('{{$baseurl}}/linkinfo?f=&binurl=' + reply, function(data) {
				if(commentwin) {
					$(editwin).val( $(editwin).val() + data );
					$('#profile-rotator').hide();
				}
				else {
					if (!editor) $("#profile-jot-text").val("");
					initEditor(function(){
					addeditortext(data);
					preview_post();
					$('#profile-rotator').hide();
					});
				}
			});
		}
	}

	function itemTag(id) {
		reply = prompt("{{$term}}");
		if(reply && reply.length) {
			reply = reply.replace('#','');
			if(reply.length) {

				commentBusy = true;
				$('body').css('cursor', 'wait');

				$.get('{{$baseurl}}/tagger/' + id + '?term=' + reply);
				if(timer) clearTimeout(timer);
				timer = setTimeout(updateInit,3000);
				liking = 1;
			}
		}
	}

	function itemCancel() {
		$("#jot-title").val('');
		$("#profile-jot-text").val('');
		$(".jot-poll-option input").val('');
		$("#jot-category").tagsinput('removeAll');

		postSaveChanges('clean');

		{{if $reset}}
		$(".jothidden").hide();
		$("#profile-jot-text").removeClass('jot-expanded');
		$("#profile-jot-reset").addClass('d-none');
		$("#jot-poll-wrap").addClass('d-none');
		$("#jot-preview-content").html('').hide();
		editor = false;
		{{else}}
		window.history.back();
		{{/if}}
	}

	function itemFiler(id) {
		if($('#item-filer-dialog').length)
			$('#item-filer-dialog').remove();

		$.get('filer/', function(data){
			$('body').append(data);
			$('#item-filer-dialog').modal('show');
			$("#filer_save").click(function(e){
				e.preventDefault();
				reply = $("#id_term").val();
				if(reply && reply.length) {
					commentBusy = true;
					$('body').css('cursor', 'wait');
					$.get('{{$baseurl}}/filer/' + id + '?term=' + reply, updateInit);
					liking = 1;
					$('#item-filer-dialog').modal('hide');
				}
				return false;
			});
		});
		
	}

	function itemBookmark(id) {
		$.get('{{$baseurl}}/bookmarks?f=&item=' + id);
		if(timer) clearTimeout(timer);
		timer = setTimeout(updateInit,1000);
	}

	function itemAddToCal(id) {
		$.get('{{$baseurl}}/calendar/add/' + id);
		if(timer) clearTimeout(timer);
		timer = setTimeout(updateInit,1000);
	}

	function toggleVoting() {
		if($('#jot-consensus').val() > 0) {
			$('#jot-consensus').val(0);
			$('#profile-voting, #profile-voting-sub').removeClass('fa-check-square-o').addClass('fa-square-o');
		}
		else {
			$('#jot-consensus').val(1);
			$('#profile-voting, #profile-voting-sub').removeClass('fa-square-o').addClass('fa-check-square-o');
		}
	}

	function jotReact(id,icon) {
		if(id && icon) {
			$.get('{{$baseurl}}/react?f=&postid=' + id + '&emoji=' + icon);
			if(timer) clearTimeout(timer);
			timer = setTimeout(updateInit,1000);
		}
	}

	function jotClearLocation() {
		$('#jot-lat').val('');
		$('#jot-lon').val('');
		$('#jot-location').val('');
		jotLocateStatus();
	    jotCheckinStatus();
	    jotCheckoutStatus();
	}

	/* start new function */

	let initializeEmbedFileDialog = function () {
    			
        getFileDirList();
    	$('#embedFileModal').modal('show');
	};

	/* end new function */

	let initializeEmbedPhotoDialog = function () {
        $('.embed-photo-selected-photo').each(function (index) {
            $(this).removeClass('embed-photo-selected-photo');
        });
        getPhotoAlbumList();
        $('#embedPhotoModalBodyAlbumDialog').off('click');
        $('#embedPhotoModal').modal('show');
    };

    let choosePhotoFromAlbum = function (album) {
        $.post("embedphotos/album", {name: album},
            function(data) {
                if (data['status']) {
                    $('#embedPhotoModalLabel').html("{{$modalchooseimages}}");
                    $('#embedPhotoModalBodyAlbumDialog').html('\
                            <div><div class="nav nav-pills flex-column">\n\
                                <li class="nav-item"><a class="nav-link" href="#" onclick="initializeEmbedPhotoDialog();return false;">\n\
                                    <i class="fa fa-chevron-left"></i>&nbsp\n\
                                    {{$modaldiffalbum}}\n\
                                    </a>\n\
                                </li>\n\
                            </div><br></div>')
                    $('#embedPhotoModalBodyAlbumDialog').append(data['content']);
                    $('#embedPhotoModalBodyAlbumDialog').click(function (evt) {
                        evt.preventDefault();
                        let image = document.getElementById(evt.target.id);
                        if (typeof($(image).parent()[0]) !== 'undefined') {
                            let imageparent = document.getElementById($(image).parent()[0].id);
                            $(imageparent).toggleClass('embed-photo-selected-photo');
                            let href = $(imageparent).attr('href');
                            $.post("embedphotos/photolink", {href: href},
                                function(ddata) {
                                    if (ddata['status']) {
                                        addeditortext(ddata['photolink']);
										preview_post();
                                    } else {
                                        window.console.log("{{$modalerrorlink}}" + ':' + ddata['errormsg']);
                                    }
                                    return false;
                                },
         	                   'json');
	                        $('#embedPhotoModalBodyAlbumDialog').html('');
    	                    $('#embedPhotoModalBodyAlbumDialog').off('click');
        	                $('#embedPhotoModal').modal('hide');
                        }
                    });
                    $('#embedPhotoModalBodyAlbumListDialog').addClass('d-none');
                    $('#embedPhotoModalBodyAlbumDialog').removeClass('d-none');
                } else {
                    window.console.log("{{$modalerroralbum}} " + JSON.stringify(album) + ':' + data['errormsg']);
                }
                return false;
            },
        'json');
    };

    let getPhotoAlbumList = function () {
        $.post("embedphotos/albumlist", {},
            function(data) {
                if (data['status']) {
                    let albums = data['albumlist']; //JSON.parse(data['albumlist']);
                    $('#embedPhotoModalLabel').html("{{$modalchoosealbum}}");
                    $('#embedPhotoModalBodyAlbumList').html('<ul class="nav nav-pills flex-column"></ul>');
                    for(let i=0; i<albums.length; i++) {
                        let albumName = albums[i].text;
			let jsAlbumName = albums[i].jstext;
			let albumLink = '<li class="nav-item">';
			albumLink += '<a class="nav-link" href="#" onclick="choosePhotoFromAlbum(\'' + jsAlbumName + '\'); return false;">' + albumName + '</a>';
                        albumLink += '</li>';
                        $('#embedPhotoModalBodyAlbumList').find('ul').append(albumLink);
                    }
                    $('#embedPhotoModalBodyAlbumDialog').addClass('d-none');
                    $('#embedPhotoModalBodyAlbumListDialog').removeClass('d-none');
                } else {
                    window.console.log("{{$modalerrorlist}}" + ':' + data['errormsg']);
                }
                return false;
            },
        'json');
    };

	{{* start new getFileDirList *}}

	let getFileDirList = function () {
		$.post("embedfiles", {},
		    function(data) {
				// alert(JSON.stringify(data));
				
				let success = data.success;
				let address = data.address;
				let results = data.content;
				let path = results[0].display_path;

				alert(address);
				console.log(JSON.stringify(results));
				
				if (data.success) {

					// results[0] breaks the loop because it has no object before it.
					// we'll define it here and start the loop at 1
					let content = `<button class="btn" type="button" data-bs-toggle="collapse" data-bs-target="#embedDir-0" aria-expanded="false" aria-controls="embedDir-0"><i class="fa fa-folder-o fa-lg me-1"></i>${results[0].filename}</button>`;
								
					for(let i=1; i<(results.length); i++) {

					
if (results[i].is_dir === "1" && results[(i-1)].is_dir === "1") {
  //  is_dir preceded by another is_dir = if child directory add opening <ul> to the beginning of the button
  if(results[i].folder === results[(i-1)].hash){ content += `<ul class="collapse" id="embedDir-${(i-1)}">`;}
  content += `<button class="btn" type="button" data-bs-toggle="collapse" data-bs-target="#embedDir-${i}" aria-expanded="false" aria-controls="embedDir-${i}"><i class="fa fa-folder-o fa-lg me-1"></i>${results[i].filename}</button>`;
  continue;

} else if (results[i].is_dir === '1' && results[(i-1)].is_dir !== '1') {
  //  is_dir preceded by a file = if directory is not a sibling add closing </ul> to the beginning of the button
  if(results[i].folder !== results[(i-1)].folder){ content += `</ul>`;}
  content += `<button class="btn" type="button" data-bs-toggle="collapse" data-bs-target="#embedDir-${i}" aria-expanded="false" aria-controls="embedDir-${i}"><i class="fa fa-folder-o fa-lg me-1"></i>${results[i].filename}</button>`;
  continue;

} else if (results[i].is_dir !== '1' && results[(i-1)].is_dir === '1') {
  //  file preceded by a is_dir = only add opening <ul> to the beginning of file if button is not a sibling
  if(results[i].folder !== results[(i-1)].folder){content += `<ul class="collapse" id="embedDir-${(i-1)}">`}
  content += `<li><img src="`${baseurl}/cloud/${channel_address}/${results[i].display_path}`" class="img-fluid img-thumbnail"/></li>`;
  continue;

} else if (results[i].is_dir !== '1' && results[(i-1)].is_dir !== '1') {
  //  file preceded by another file = just the line item
  content += `<li><img src="`${baseurl}/cloud/${channel_address}/${results[i].display_path}`" class="img-fluid img-thumbnail"/></li>`;
  continue;

}

					} // end new loop

					// close the last ul
					//content += `</ul>`
					$('#embedFileDirModalBody').html( content);
					
				} else {
                    window.console.log(`{{$modalerrorlist}} : data['errormsg']`);
                }
                return false;
            },
        'json');
	};
	{{* end new getFileDirList *}}

    // initialize drag-drop
    function DragDropUploadInit() {

      let filedrag = $("#profile-jot-text");

	  // file drop
        filedrag.on("dragover", DragDropUploadFileHover);
        filedrag.on("dragleave", DragDropUploadFileHover);
        filedrag.on("drop", DragDropUploadFileSelectHandler);
    }

	// file drag hover
	function DragDropUploadFileHover(e) {
		if(e.type == 'dragover')
			$(e.target).addClass('hover');
		else
			$(e.target).removeClass('hover');
	}

    // file selection
    function DragDropUploadFileSelectHandler(e) {

      // cancel event and hover styling
      DragDropUploadFileHover(e);
      // open editor if it isn't yet initialised
	  if (! editor) {
			enableOnUser();
	  }
	  linkdrop(e);

    }

	function initPoll() {
		$('#jot-poll-wrap').toggleClass('d-none');
	}

	function jotAddOption() {
		let option = '<div class="jot-poll-option form-group"><input class="w-100 border-0" name="poll_answers[]" type="text" value="" placeholder="Option"><div class="poll-option-close"><i class="fa fa-close"></i></div></div>';
		$('#jot-poll-options').append(option);
	}

	function jotRemoveOption(e) {
		$(this).closest('.jot-poll-option').remove();
	}

	function initRecipe() {
		$('#jot-recipe-wrap').toggleClass('d-none');
	}

	function jotAddIngredient() {
		let option = '<div class="jot-ingredient form-group"><input class="w-100 border-0" name="poll_answers[]" type="text" value="" placeholder="Option"><div class="poll-option-close"><i class="fa fa-close"></i></div></div>';
		$('#jot-poll-options').append(option);
	}

	function jotRemoveIngedient(e) {
		$(this).closest('.jot-ingredient').remove();
	}

	function postSaveChanges(action) {
		if({{$auto_save_draft}}) {

			let doctype = $('#jot-webpage').val();
			let postid = '-' + doctype + '-' + $('#jot-postid').val();

			if(action != 'clean') {
				localStorage.setItem("post_title" + postid, $("#jot-title").val());
				localStorage.setItem("post_body" + postid, $("#profile-jot-text").val());
				if($("#jot-category").length)
					localStorage.setItem("post_category" + postid, $("#jot-category").val());
			}

			if(action == 'start') {
				postSaveTimer = setTimeout(function () {
					postSaveChanges('start');
				},10000);
			}

			if(action == 'stop') {
				clearTimeout(postSaveTimer);
				postSaveTimer = null;
			}

			if(action == 'clean') {
				clearTimeout(postSaveTimer);
				postSaveTimer = null;
				localStorage.removeItem("post_title" + postid);
				localStorage.removeItem("post_body" + postid);
				localStorage.removeItem("post_category" + postid);
			}
		}
	}


</script>
