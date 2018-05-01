<script>
    var initializeEmbedPhotoDialog = function () {
        $('.embed-photo-selected-photo').each(function (index) {
            $(this).removeClass('embed-photo-selected-photo');
        });
        getPhotoAlbumList();
        $('#embedPhotoModalBodyAlbumDialog').off('click');
        $('#embedPhotoModal').modal('show');
    };

    var choosePhotoFromAlbum = function (album) {
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
                        var image = document.getElementById(evt.target.id);
                        if (typeof($(image).parent()[0]) !== 'undefined') {
                            var imageparent = document.getElementById($(image).parent()[0].id);
                            $(imageparent).toggleClass('embed-photo-selected-photo');
							var href = $(imageparent).attr('href');
                            $.post("embedphotos/photolink", {href: href},
                                function(ddata) {
                                    if (ddata['status']) {
										var pf = $('#profile-photo-profiles').val();
										var prof = ((typeof pf !== 'undefined') ? '?f=&pf=' + pf : '');
                                        window.location.href = 'profile_photo/use/' + ddata['resource_id'] + prof;
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

    var getPhotoAlbumList = function () {
        $.post("embedphotos/albumlist", {},
            function(data) {
                if (data['status']) {
                    var albums = data['albumlist']; //JSON.parse(data['albumlist']);
                    $('#embedPhotoModalLabel').html("{{$modalchoosealbum}}");
                    $('#embedPhotoModalBodyAlbumList').html('<ul class="nav nav-pills flex-column"></ul>');
                    for(var i=0; i<albums.length; i++) {
                        var albumName = albums[i].text;
			var jsAlbumName = albums[i].jstext;
			var albumLink = '<li class="nav-item">';
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
</script>


<div id="profile-photo-content" class="generic-content-wrapper">
    <div class="section-title-wrapper">
    <h2>{{$title}}</h2>
    </div>
    <div class="section-content-wrapper">

		<form enctype="multipart/form-data" action="profile_photo" method="post">
		<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>

		<div id="profile-photo-upload-wrapper">
			{{if $info}}
			<div class="section-content-warning-wrapper">{{$info}}</div>
			{{/if}}
			{{if $importfile}}
			<input type="hidden" name="importfile" value="{{$importfile}}">
			{{else}}
			<label id="profile-photo-upload-label" class="form-label" for="profile-photo-upload">{{$lbl_upfile}}</label>
			<input name="userfile" class="form-input" type="file" id="profile-photo-upload" size="48" />
			{{/if}}
			<div class="clear"></div>


			{{if $single}}
			<input type="hidden" name="profile" value="{{$profile0.id}}" />
			{{else}}



			<label id="profile-photo-profiles-label" class="form-label" for="profile-photo-profiles">{{$lbl_profiles}}</label>
			<select name="profile" id="profile-photo-profiles" class="form-control" >
			{{foreach $profiles as $p}}
				<option value="{{$p.id}}" {{if $p.selected}}selected="selected"{{/if}}>{{$p.name}}</option>
			{{/foreach}}
			</select>
			<div class="clear"></div>
			<br />
			<br />
			{{/if}}

			<div id="profile-photo-submit-wrapper">
				<input type="submit" name="submit" id="profile-photo-submit" value="{{$submit}}">
			</div>
		</div>

		</form>
		<br />
		<div id="profile-photo-link-select-wrapper">
		<button id="embed-photo-wrapper" class="btn btn-default btn-primary" title="{{$embedPhotos}}" onclick="initializeEmbedPhotoDialog();return false;">
		<i id="embed-photo" class="fa fa-file-image-o"></i> {{$select}}
		</button>
		</div>
	</div>
</div>

<div class="modal" id="embedPhotoModal" tabindex="-1" role="dialog" aria-labelledby="embedPhotoLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="embedPhotoModalLabel">{{$embedPhotosModalTitle}}</h4>
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
			</div>
			<div class="modal-body" id="embedPhotoModalBody" >
				<div id="embedPhotoModalBodyAlbumListDialog" class="d-none">
					<div id="embedPhotoModalBodyAlbumList"></div>
				</div>
				<div id="embedPhotoModalBodyAlbumDialog" class="d-none"></div>
			</div>
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->
