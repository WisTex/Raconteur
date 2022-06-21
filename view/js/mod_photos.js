/**
 * JavaScript used by mod/photos
 */
$(document).ready(function() {

	// call initialization file
	if (window.File && window.FileList && window.FileReader) {
		UploadInit();
	}

	$("#photo-edit-newtag").contact_autocomplete(baseurl + '/acloader', 'a', false, function(data) {
		$("#photo-edit-newtag").val('@' + data.name);
	});
	
	$(".comment-edit-form  textarea").editor_autocomplete(baseurl+"/acloader?f=&n=1");
	$('textarea').editor_autocomplete(baseurl+"/acloader");
	$('textarea').bbco_autocomplete('bbcode');
	showHideBodyTextarea();

});

function showHideBodyTextarea() {
	if( $('#id_visible').is(':checked'))
		$('#body-textarea').slideDown();
	else
		$('#body-textarea').slideUp();
}

// initialize
function UploadInit() {

	var nickname = $('#invisible-photos-file-upload').data('nickname');
	var fileselect = $("#photos-upload-choose");
	var filedrag = $("#photos-upload-form");
	var submit = $("#dbtn-submit");
	var count = 1;

   $('#invisible-photos-file-upload').fileupload({
            url: 'photos/' + nickname,
            dataType: 'json',
            dropZone: filedrag,
            maxChunkSize: 2 * 1024 * 1024,

            add: function(e,data) {
                $(data.files).each( function() { this.count = ++ count; prepareHtml(this); });

                var allow_cid = ($('#photos-upload-form').data('allow_cid') || []);
                var allow_gid = ($('#photos-upload-form').data('allow_gid') || []);
                var deny_cid  = ($('#photos-upload-form').data('deny_cid') || []);
                var deny_gid  = ($('#photos-upload-form').data('deny_gid') || []);

                $('.acl-field').remove();

                $(allow_gid).each(function(i,v) {
                    $('#photos-upload-form').append("<input class='acl-field' type='hidden' name='group_allow[]' value='"+v+"'>");
                });
                $(allow_cid).each(function(i,v) {
                    $('#photos-upload-form').append("<input class='acl-field' type='hidden' name='contact_allow[]' value='"+v+"'>");
                });
                $(deny_gid).each(function(i,v) {
                    $('#photos-upload-form').append("<input class='acl-field' type='hidden' name='group_deny[]' value='"+v+"'>");
                });
                $(deny_cid).each(function(i,v) {
                    $('#photos-upload-form').append("<input class='acl-field' type='hidden' name='contact_deny[]' value='"+v+"'>");
                });

                data.formData = $('#photos-upload-form').serializeArray();

                data.submit();
            },

           progress: function(e,data) {

                // there will only be one file, the one we are looking for                                                                                                                       
                $(data.files).each( function() {
                    var idx = this.count;

                    // Dynamically update the percentage complete displayed in the file upload list                                                                                              
                    $('#upload-progress-' + idx).html(Math.round(data.loaded / data.total * 100) + '%');
                    $('#upload-progress-bar-' + idx).css('background-size', Math.round(data.loaded / data.total * 100) + '%');

                });


            },

            stop: function(e,data) {
                window.location.href = window.location.href;
            }

        });

        $('#dbtn-submit').click(function(event) { event.preventDefault(); $('#invisible-photos-file-upload').trigger('click'); return false;});

		$('.generic-content-wrapper').on("dragover", function(e) {
			$('#photo-upload-form').show();
		});
	
		// file drop
		filedrag.on("dragover", DragDropUploadFileHover);
		filedrag.on("dragleave", DragDropUploadFileHover);

}

// file drag hover
function DragDropUploadFileHover(e) {
	e.currentTarget.className = (e.type == "dragover" ? "hover" : "");
}

// file selection via drag/drop
function DragDropUploadFileSelectHandler(e) {
	// cancel event and hover styling
	DragDropUploadFileHover(e);

	// fetch FileList object
	var files = e.target.files || e.originalEvent.dataTransfer.files;

	$('.new-upload').remove();

	// process all File objects
	for (var i = 0, f; f = files[i]; i++) {
		prepareHtml(f, i);
		UploadFile(f, i);
	}
}

// file selection via input
function UploadFileSelectHandler(e) {
	// fetch FileList object
	if(e.target.id === 'dbtn-submit') {
		e.preventDefault();
		var files = e.data[0].files;
	}
	if(e.target.id === 'photos-upload-choose') {
		$('.new-upload').remove();
		var files = e.target.files;
	}

	// process all File objects
	for (var i = 0, f; f = files[i]; i++) {
		if(e.target.id === 'photos-upload-choose')
			prepareHtml(f, i);
		if(e.target.id === 'dbtn-submit') {
			UploadFile(f, i);
		}
	}
}

function prepareHtml(f) {

	var num = f.count - 1;
	var i = f.count;

	$('#upload-index #new-upload-progress-bar-' + num.toString()).after(
		'<tr id="new-upload-' + i + '" class="new-upload">' +
		'<td width="1%"><i class="fa ' + getIconFromType(f.type) + '" title="' + f.type + '"></i></td>' +
		'<td width="96%">' + f.name + '</td>' +
		'<td id="upload-progress-' + i + '" width="1%"></td>' +
		'<td class="d-none d-md-table-cell" width="1%">' + formatSizeUnits(f.size) + '</td>' +
		'</tr>' +
		'<tr id="new-upload-progress-bar-' + i + '" class="new-upload">' +
		'<td id="upload-progress-bar-' + i + '" colspan="4" class="upload-progress-bar"></td>' +
		'</tr>'
	);
}

function formatSizeUnits(bytes){
	if      (bytes>=1000000000) {bytes=(bytes/1000000000).toFixed(2)+' GB';}
	else if (bytes>=1000000)    {bytes=(bytes/1000000).toFixed(2)+' MB';}
	else if (bytes>=1000)       {bytes=(bytes/1000).toFixed(2)+' KB';}
	else if (bytes>1)           {bytes=bytes+' bytes';}
	else if (bytes==1)          {bytes=bytes+' byte';}
	else                        {bytes='0 byte';}
	return bytes;
}

// this is basically a js port of include/misc.php getIconFromType() function
function getIconFromType(type) {
	var map = {
		//Common file
		'application/octet-stream': 'fa-file-o',
		//Text
		'text/plain': 'fa-file-text-o',
		'application/msword': 'fa-file-word-o',
		'application/pdf': 'fa-file-pdf-o',
		'application/vnd.oasis.opendocument.text': 'fa-file-word-o',
		'application/epub+zip': 'fa-book',
		//Spreadsheet
		'application/vnd.oasis.opendocument.spreadsheet': 'fa-file-excel-o',
		'application/vnd.ms-excel': 'fa-file-excel-o',
		//Image
		'image/jpeg': 'fa-picture-o',
		'image/png': 'fa-picture-o',
		'image/gif': 'fa-picture-o',
		'image/svg+xml': 'fa-picture-o',
		//Archive
		'application/zip': 'fa-file-archive-o',
		'application/x-rar-compressed': 'fa-file-archive-o',
		//Audio
		'audio/mpeg': 'fa-file-audio-o',
		'audio/mp3': 'fa-file-audio-o', //webkit browsers need that
		'audio/wav': 'fa-file-audio-o',
		'application/ogg': 'fa-file-audio-o',
		'audio/ogg': 'fa-file-audio-o',
		'audio/webm': 'fa-file-audio-o',
		'audio/mp4': 'fa-file-audio-o',
		//Video
		'video/quicktime': 'fa-file-video-o',
		'video/webm': 'fa-file-video-o',
		'video/mp4': 'fa-file-video-o',
		'video/x-matroska': 'fa-file-video-o'
	};

	var iconFromType = 'fa-file-o';

	if (type in map) {
		iconFromType = map[type];
	}

	return iconFromType;
}

// upload  files
function UploadFile(file, idx) {

	window.filesToUpload = window.filesToUpload + 1;

	var xhr = new XMLHttpRequest();

	xhr.withCredentials = true;   // Include the SESSION cookie info for authentication

	(xhr.upload || xhr).addEventListener('progress', function (e) {

		var done = e.position || e.loaded;
		var total = e.totalSize || e.total;
		// Dynamically update the percentage complete displayed in the file upload list
		$('#upload-progress-' + idx).html(Math.round(done / total * 100) + '%');
		$('#upload-progress-bar-' + idx).css('background-size', Math.round(done / total * 100) + '%');

		if(done == total) {
			$('#upload-progress-' + idx).html('Processing...');
		}

	});


	xhr.addEventListener('load', function (e) {
		//we could possibly turn the filenames to real links here and add the delete and edit buttons to avoid page reload...
		$('#upload-progress-' + idx).html('Ready!');

		//console.log('xhr upload complete', e);
		window.fileUploadsCompleted = window.fileUploadsCompleted + 1;

		// When all the uploads have completed, refresh the page
		if (window.filesToUpload > 0 && window.fileUploadsCompleted === window.filesToUpload) {

			window.fileUploadsCompleted = window.filesToUpload = 0;

			// After uploads complete, refresh browser window to display new files
			window.location.href = window.location.href;
		}
	});


	xhr.addEventListener('error', function (e) {
		$('#upload-progress-' + idx).html('<span style="color: red;">ERROR</span>');
	});

	// POST to the entire cloud path 
	xhr.open('post', $('#photos-upload-form').attr( 'action' ), true);

	var formfields = $("#photos-upload-form").serializeArray();

	var data = new FormData();
	$.each(formfields, function(i, field) {
		data.append(field.name, field.value);
	});
	data.append('userfile', file);

	xhr.send(data);
}
