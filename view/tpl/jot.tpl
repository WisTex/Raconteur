<input id="invisible-wall-file-upload" type="file" name="files" style="visibility:hidden;position:absolute;top:-50px;left:-50px;width:0;height:0;" multiple>
<input id="invisible-comment-upload" type="file" name="files" style="visibility:hidden;position:absolute;top:-50px;left:-50px;width:0;height:0;" multiple>
<form id="profile-jot-form" action="{{$action}}" method="post" class="acl-form" data-form_id="profile-jot-form" data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}' data-deny_gid='{{$deny_gid}}'>
	{{$mimeselect}}
	{{$layoutselect}}
	{{if $id_select}}
	<div class="channel-id-select-div">
		<span class="channel-id-select-desc">{{$id_seltext}}</span> {{$id_select}}
	</div>
	{{/if}}
	<div class="mb-4" id="profile-jot-wrapper">

		{{if $parent}}
		<input type="hidden" name="parent" value="{{$parent}}" />
		{{/if}}
		<input type="hidden" name="obj_type" value="{{$ptyp}}" />
		<input type="hidden" name="profile_uid" value="{{$profile_uid}}" />
		<input type="hidden" name="return" value="{{$return_path}}" />
		<input type="hidden" name="location" id="jot-location" value="{{$defloc}}" />
		<input type="hidden" name="expire" id="jot-expire" value="{{$defexpire}}" />
		<input type="hidden" name="comments_closed" id="jot-commclosed" value="{{$defcommuntil}}" />
		<input type="hidden" name="comments_from" id="jot-commfrom" value="{{$defcommpolicy}}" />
		<input type="hidden" name="created" id="jot-created" value="{{$defpublish}}" />
		<input type="hidden" name="media_str" id="jot-media" value="" />
		<input type="hidden" name="source" id="jot-source" value="{{$source}}" />
		<input type="hidden" name="lat" id="jot-lat" value="{{$lat}}" />
		<input type="hidden" name="lon" id="jot-lon" value="{{$lon}}" />
		<input type="hidden" id="jot-postid" name="post_id" value="{{$post_id}}" />
		<input type="hidden" id="jot-webpage" name="webpage" value="{{$webpage}}" />
		<input type="hidden" name="preview" id="jot-preview" value="0" />
		<input type="hidden" name="draft" id="jot-draft" value="0" />
		<input type="hidden" name="checkin" id="jot-checkin" value="{{$checkin_checked}}" />
		<input type="hidden" name="checkout" id="jot-checkout" value="{{$checkout_checked}}" />
		<input type="hidden" name="hidden_mentions" id="jot-hidden-mentions" value="{{$hidden_mentions}}" />
		<input type="hidden" id="jot-commentstate" name="comments_enabled" value="{{if $commentstate}}{{$commentstate}}{{else}}1{{/if}}" />

		{{if $webpage}}
		<div id="jot-pagetitle-wrap" class="jothidden">
			<input class="w-100 border-0" name="pagetitle" id="jot-pagetitle" type="text" placeholder="{{$placeholdpagetitle}}" value="{{$pagetitle}}">
			{{if $webpage === 8}}
				<input type="hidden" id="recip-complete" name="recips" value="{{$recips}}">
			{{/if}}
		</div>
		{{/if}}
		<div id="jot-title-wrap" class="jothidden">
			<input class="float-start border-0" name="title" id="jot-title" type="text" placeholder="{{$placeholdertitle}}" tabindex="1" value="{{$title}}">
			{{if $reset}}
			<div class="btn-toolbar  float-end">
				<div class="btn-group ">
					<button id="profile-jot-reset" class="btn btn-outline-secondary btn-sm m-1 drop-buttons" title="{{$reset}}" onclick="itemCancel(); return false;">
						<i class="fa fa-close"></i>
					</button>
				</div>
			</div>
			<div class="clear"></div>
			{{/if}}
		</div>
		{{if $catsenabled}}
		<div id="jot-category-wrap" class="jothidden">
			<input class="w-100 border-0" name="category" id="jot-category" type="text" placeholder="{{$placeholdercategory}}" value="{{$category}}" data-role="cat-tagsinput">
		</div>
		{{/if}}
		{{if $summaryenabled}}
		<div id="jot-summary-wrap" class="jothidden">
			<textarea class="profile-jot-summary" id="profile-jot-summary" name="summary" tabindex="2" placeholder="{{$placeholdsummary}}" >{{$summary}}</textarea>
		</div>
		{{/if}}
		<div id="jot-text-wrap">
			<textarea class="profile-jot-text" id="profile-jot-text" name="body" tabindex="3" placeholder="{{$placeholdtext}}" >{{$content}}</textarea>
		</div>
		{{if $attachment}}
		<div id="jot-attachment-wrap">
			<input class="jot-attachment" name="attachment" id="jot-attachment" type="text" value="{{$attachment}}" readonly="readonly" onclick="this.select();">
		</div>
		{{/if}}
		<div id="jot-poll-wrap" class=" d-none">
			<div id="jot-poll-options">
				<div class="jot-poll-option form-group">
					<input class="w-100 border-0" name="poll_answers[]" type="text" value="" placeholder="{{$poll_option_label}}">
				</div>
				<div class="jot-poll-option form-group">
					<input class="w-100 border-0" name="poll_answers[]" type="text" value="" placeholder="{{$poll_option_label}}">
				</div>
			</div>
			{{include file="field_checkbox.tpl" field=$multiple_answers}}
			<div id="jot-poll-tools" class="clearfix">
				<div id="poll-tools-left" class="float-start">
					<button id="jot-add-option" class="btn btn-outline-secondary btn-sm" type="button">
						<i class="fa fa-plus"></i> {{$poll_add_option_label}}
					</button>
				</div>
				<div id="poll-tools-right" class="float-end">
					<div class="input-group">
						<input type="text" name="poll_expire_value" class="form-control" value="10" size="3">
						<select class="form-control" id="duration-select" name="poll_expire_unit">
							<option value="Minutes">{{$poll_expire_unit_label.0}}</option>
							<option value="Hours">{{$poll_expire_unit_label.1}}</option>
							<option value="Days" selected="selected">{{$poll_expire_unit_label.2}}</option>
						</select>
					</div>
				</div>
			</div>
		</div>
		<div id="profile-jot-submit-wrapper" class="clearfix jothidden p-2">
			<div id="profile-jot-submit-left" class="btn-toolbar  float-start">
				{{if $bbcode && $feature_markup}}				
				<div id="jot-markup" class="btn-group mr-2 ">
					<button id="main-editor-bold" class="btn btn-outline-secondary btn-sm" title="{{$bold}}" onclick="inserteditortag('b', 'profile-jot-text'); return false;">
						<i class="fa fa-bold jot-icons"></i>
					</button>
					<button id="main-editor-italic" class="btn btn-outline-secondary btn-sm" title="{{$italic}}" onclick="inserteditortag('i', 'profile-jot-text'); return false;">
						<i class="fa fa-italic jot-icons"></i>
					</button>
					<button id="main-editor-underline" class="btn btn-outline-secondary btn-sm" title="{{$underline}}" onclick="inserteditortag('u', 'profile-jot-text'); return false;">
						<i class="fa fa-underline jot-icons"></i>
					</button>
					<button id="main-editor-quote" class="btn btn-outline-secondary btn-sm" title="{{$quote}}" onclick="inserteditortag('quote', 'profile-jot-text'); return false;">
						<i class="fa fa-quote-left jot-icons"></i>
					</button>
					<button id="main-editor-code" class="btn btn-outline-secondary btn-sm" title="{{$code}}" onclick="inserteditortag('code', 'profile-jot-text'); return false;">
						<i class="fa fa-terminal jot-icons"></i>
					</button>
				</div>
				{{/if}}
				{{if $visitor}}
				&nbsp;
				<div class="btn-group mr-2 ">

				{{* what happens when we comment out the old buttons ?
					{{if $writefiles}}
					<button id="wall-file-upload" class="btn btn-outline-secondary btn-sm" title="{{$attach}}" >
						<i id="wall-file-upload-icon" class="fa fa-paperclip jot-icons"></i>
					</button>
					{{/if}}
				*}}

					{{if $weblink}}
					<button id="profile-link-wrapper" class="btn btn-outline-secondary btn-sm " title="{{$weblink}}" ondragenter="linkdropper(event);" ondragover="linkdropper(event);" ondrop="linkdrop(event);"  onclick="jotGetLink(); return false;">
						<i id="profile-link" class="fa fa-link jot-icons"></i>
					</button>
					{{/if}}

				{{* what happens when we comment out the old buttons ?	
					{{if $embedPhotos}}
					<button id="embed-photo-wrapper" class="btn btn-outline-secondary btn-sm " title="{{$embedPhotos}}" onclick="initializeEmbedPhotoDialog();return false;">
						<i id="embed-photo" class="fa fa-file-image-o jot-icons"></i>
					</button>
					{{/if}}
				*}}

					<!-- new test button -->
					{{if $embedPhotos || $writefiles}}
						<button id="new-embed-photo-wrapper" class="btn btn-outline-secondary btn-sm " title="{{$embedPhotos}} or {{$attach}} " onclick="initializeEmbedFileDialog();return false;">
							<i id="new-embed-photo" class="fa fa-file-o jot-icons"></i>
						</button>
					{{/if}}

					<!-- end new test button -->

					<button type="button" id="profile-poll-wrapper" class="btn btn-outline-secondary btn-sm " title="{{$poll}}" onclick="initPoll();">
						<i id="profile-poll" class="fa fa-bar-chart jot-icons"></i>
					</button>
				</div>
				<div class="btn-group ">
					&nbsp;
					{{if $setloc}}
					<button id="profile-location-wrapper" class="btn btn-outline-secondary btn-sm" title="{{$setloc}}" onclick="jotGetLocation();return false;">
						<i id="profile-location" class="fa fa-globe jot-icons"></i>
					</button>
					{{/if}}
					{{if $clearloc}}
					<button id="profile-nolocation-wrapper" class="btn btn-outline-secondary btn-sm" title="{{$clearloc}}" onclick="jotClearLocation();return false;" disabled="disabled">
						<i id="profile-nolocation" class="fa fa-circle-o jot-icons"></i>
					</button>
					{{/if}}
					{{if $feature_checkin}}
						<button id="profile-checkin-wrapper" class="btn btn-outline-secondary btn-sm" title="{{$checkin}}" onclick="jotCheckin(); return false;">
							<i id="profile-checkin" class="fa fa-sign-in jot-icons"></i>
						</button>
					{{/if}}
					{{if $feature_checkout}}
						<button id="profile-checkout-wrapper" class="btn btn-outline-secondary btn-sm" title="{{$checkout}}" onclick="jotCheckout(); return false;">
							<i id="profile-checkout" class="fa fa-sign-out jot-icons"></i>
						</button>
					{{/if}}
				{{else}}
				<div class="btn-group d-none ">
				{{/if}}
				{{if $feature_expire}}
					<button id="profile-expire-wrapper" class="btn btn-outline-secondary btn-sm" title="{{$expires}}" onclick="jotGetExpiry();return false;">
						<i id="profile-expires" class="fa fa-eraser jot-icons"></i>
					</button>
				{{/if}}

				{{if $feature_comment_control}}
					<button id="profile-commctrl-wrapper" class="btn btn-outline-secondary btn-sm" title="{{$commctrl}}" onclick="jotGetCommCtrl();return false;">
						<i id="profile-commctrl" class="fa fa-comment-o jot-icons"></i>
					</button>
				{{/if}}

				{{if $feature_future}}
					<button id="profile-future-wrapper" class="btn btn-outline-secondary btn-sm" title="{{$future_txt}}" onclick="jotGetPubDate();return false;">
						<i id="profile-future" class="fa fa-clock-o jot-icons"></i>
					</button>
				{{/if}}
				{{if $feature_encrypt}}
					<button id="profile-encrypt-wrapper" class="btn btn-outline-secondary btn-sm" title="{{$encrypt}}" onclick="hz_encrypt('{{$cipher}}','#profile-jot-text');return false;">
						<i id="profile-encrypt" class="fa fa-key jot-icons"></i>
					</button>
				{{/if}}

				</div>
				{{if $writefiles || $weblink || $setloc || $clearloc || $feature_expire || $feature_encrypt }}
					&nbsp;
				<div class="btn-group  d-none">
					<button type="button" id="more-tools" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
						<i id="more-tools-icon" class="fa fa-cog jot-icons"></i>
					</button>
					<div class="dropdown-menu">
						{{if $visitor}}
						{{if $writefiles}}
						<a class="dropdown-item" id="wall-file-upload-sub" href="#" ><i class="fa fa-paperclip"></i>&nbsp;{{$attach}}</a>
						{{/if}}
						{{if $weblink}}
						<a class="dropdown-item" href="#" onclick="jotGetLink(); return false;"><i class="fa fa-link"></i>&nbsp;{{$weblink}}</a>
						{{/if}}
						{{if $embedPhotos}}
						<a class="dropdown-item" href="#" onclick="initializeEmbedPhotoDialog(); return false;"><i class="fa fa-file-image-o jot-icons"></i>&nbsp;{{$embedPhotos}}</a>
						{{/if}}
						<a class="dropdown-item" href="#" onclick="initPoll(); return false"><i id="profile-poll" class="fa fa-bar-chart jot-icons"></i>&nbsp;{{$poll}}</a>
						{{if $setloc}}
						<a class="dropdown-item" href="#" onclick="jotGetLocation(); return false;"><i class="fa fa-globe"></i>&nbsp;{{$setloc}}</a>
						{{/if}}
						{{if $clearloc}}
						<a class="dropdown-item" href="#" onclick="jotClearLocation(); return false;"><i class="fa fa-circle-o"></i>&nbsp;{{$clearloc}}</a>
						{{/if}}
						{{/if}}
						{{if $feature_expire}}
						<a class="dropdown-item" href="#" onclick="jotGetExpiry(); return false;"><i class="fa fa-eraser"></i>&nbsp;{{$expires}}</a>
						{{/if}}
						{{if $feature_comment_control}}
						<a class="dropdown-item" href="#" onclick="jotGetCommCtrl();return false;"><i class="fa fa-comment-o"></i>&nbsp;{{$commctrl}}</a>
						{{/if}}	
						{{if $feature_future}}
						<a class="dropdown-item" href="#" onclick="jotGetPubDate();return false;"><i class="fa fa-clock-o"></i>&nbsp;{{$future_txt}}</a>
						{{/if}}
						{{if $feature_encrypt}}
						<a class="dropdown-item" href="#" onclick="hz_encrypt('{{$cipher}}','#profile-jot-text',$('#profile-jot-text').val());return false;"><i class="fa fa-key"></i>&nbsp;{{$encrypt}}</a>
						{{/if}}
					</div>
				</div>
				{{/if}}
				<div class="btn-group ">
					<div id="profile-rotator" class="mt-2 spinner-wrapper">
						<div class="spinner s"></div>
					</div>
				</div>

			</div>
			<div id="profile-jot-submit-right" class="btn-group  float-end">
				<div class="btn-group ">
				{{if $preview}}
				<button class="btn btn-outline-secondary btn-sm" onclick="preview_post();return false;" title="{{$preview}}">
					<i class="fa fa-eye jot-icons" ></i>
				</button>
				{{/if}}
				{{if $save}}
				<button class="btn btn-sm{{if $is_draft}} btn-warning{{else}} btn-outline-secondary{{/if}}" onclick="save_draft();return false;" title="{{$save}}">
					<i class="fa fa-floppy-o jot-icons" ></i>
				</button>
				{{/if}}
				{{if $jotplugins}}
					<div id="profile-jot-plugin-wrapper" class="mt-2">
						{{$jotplugins}}
					</div>
				{{/if}}
				{{if $jotnets}}
				<button id="dbtn-jotnets" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#jotnetsModal" type="button" title="{{$jotnets_label}}" style="{{if $lockstate == 'lock'}}display: none;{{/if}}">
					<i class="fa fa-share-alt jot-icons"></i>
				</button>
				{{/if}}
				{{if $jotcoll}}
				<button id="dbtn-jotcoll" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#jotcollModal" type="button" title="{{$jotcoll_label}}">
					<i class="fa fa-tags jot-icons"></i>
				</button>
				{{/if}}
				{{if $showacl}}
				<button id="dbtn-acl" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#aclModal" title="{{$permset}}" type="button" data-form_id="profile-jot-form">
					<i id="jot-perms-icon" class="fa fa-{{$lockstate}} jot-icons{{if $bang}} jot-lock-warn{{/if}}"></i>
				</button>
				{{/if}}
				<button id="dbtn-submit" class="btn btn-primary btn-sm" type="submit" tabindex="3" name="button-submit">{{$share}}</button>
				</div>
			</div>
		</div>
	</div>
</form>

<div id="jot-preview-content" style="display:none;"></div>
{{$acl}}
{{if $jotnets}}
	<div class="modal" id="jotnetsModal" tabindex="-1" role="dialog" aria-labelledby="jotnetsModalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title" id="jotnetsModalLabel">{{$jotnets_label}}</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					{{$jotnets}}
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{$close}}</button>
				</div>
			</div><!-- /.modal-content -->
		</div><!-- /.modal-dialog -->
	</div><!-- /.modal -->
{{/if}}

{{if $jotcoll}}
	<div class="modal" id="jotcollModal" tabindex="-1" role="dialog" aria-labelledby="jotcollModalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title" id="jotcollModalLabel">{{$jotcoll_label}}</h4>
					<button type="button" class="close" data-bs-dismiss="modal" aria-hidden="true">&times;</button>
				</div>
				<div class="modal-body">
					{{$jotcoll}}
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{$close}}</button>
				</div>
			</div><!-- /.modal-content -->
		</div><!-- /.modal-dialog -->
	</div><!-- /.modal -->
{{/if}}
{{if $feature_comment_control}}
<!-- Modal for comment control-->
<div class="modal" id="commModal" tabindex="-1" role="dialog" aria-labelledby="commModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="commModalLabel">{{$commctrl}}</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body form-group" >
				{{include file="field_checkbox.tpl" field=$comments_allowed}}				
				{{include file="field_select.tpl" field=$comment_perms}}
				<div class="date">
					<label for="commclose-date">{{$commclosedate}}</label>
					<input type="text" placeholder="yyyy-mm-dd HH:MM" name="start_text" value="{{$comments_closed}}" id="commclose-date" class="form-control" />
				</div>
				<script>
					$(function () {
						var picker = $('#commclose-date').datetimepicker({format:'Y-m-d H:i', minDate: 0 });
					});
				</script>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{$commModalCANCEL}}</button>
				<button id="comm-modal-OKButton" type="button" class="btn btn-primary">{{$commModalOK}}</button>
			</div>
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->
{{/if}}


{{if $feature_expire}}
<!-- Modal for item expiry-->
<div class="modal" id="expiryModal" tabindex="-1" role="dialog" aria-labelledby="expiryModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="expiryModalLabel">{{$expires}}</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body form-group" style="width:90%">
				<div class="date">
					<input type="text" placeholder="yyyy-mm-dd HH:MM" name="start_text" value="{{$defexpire}}" id="expiration-date" class="form-control" />
				</div>
				<script>
					$(function () {
						var picker = $('#expiration-date').datetimepicker({format:'Y-m-d H:i', minDate: 0 });
					});
				</script>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{$expiryModalCANCEL}}</button>
				<button id="expiry-modal-OKButton" type="button" class="btn btn-primary">{{$expiryModalOK}}</button>
			</div>
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->
{{/if}}



{{if $feature_future}}
<div class="modal" id="createdModal" tabindex="-1" role="dialog" aria-labelledby="createdModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="createdModalLabel">{{$future_txt}}</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body form-group" style="width:90%">
				<div class="date">
					<input type="text" placeholder="yyyy-mm-dd HH:MM" name="created_text" id="created-date" class="form-control" />
				</div>
				<script>
					$(function () {
						var picker = $('#created-date').datetimepicker({format:'Y-m-d H:i', minDate: 0 });
					});
				</script>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{$expiryModalCANCEL}}</button>
				<button id="created-modal-OKButton" type="button" class="btn btn-primary">{{$expiryModalOK}}</button>
			</div>
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->
{{/if}}

{{if $embedPhotos}}
<div class="modal" id="embedPhotoModal" tabindex="-1" role="dialog" aria-labelledby="embedPhotoLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="embedPhotoModalLabel">{{$embedPhotosModalTitle}}</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
{{/if}}

{{if $weblink}}
<div class="modal" id="linkModal" tabindex="-1" role="dialog" aria-labelledby="linkModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="linkModalLabel">{{$linkurl}}</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body form-group" style="width:100%">
					<input type="text" name="link_url" id="id_link_url" class="form-control" >
					<div style="margin-top: 10px;"><input type="radio" name="link_style" value="0" > {{$weblink_style.0}}</div>
					<div style="margin-top: 5px;"><input type="radio" name="link_style" value="1" checked > {{$weblink_style.1}}</div>

					<div id="linkmodaldiscover" style="margin-top: 10px;">
						<div class="clearfix form-group">
							<label for="id_oembed">{{$discombed}}</label>
							<div class="float-end"><input type="checkbox" name='oembed' id='id_oembed' value="1" {{$embedchecked}} ></div>
							<div class="descriptive-text">{{$discombed2}}</div>
						</div>
						<!--div class="clearfix form-group">
							<label for="id_zotobj">{{$disczot}}</label>
							<div class="float-end"><input type="checkbox" name='zotobj' id='id_zotobj' value="1" checked ></div>
						</div -->
					</div>
			</div>
			<div class="modal-footer">
				<button id="link-modal-CancelButton" type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{$linkModalCANCEL}}</button>
				<button id="link-modal-OKButton" type="button" class="btn btn-primary">{{$linkModalOK}}</button>
			</div>
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->
{{/if}}

<!-- start the new modal here -->

<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  	<div class="modal-dialog">
    	<div class="modal-content">
      		<div class="modal-header">
        		<h1 class="modal-title fs-5" id="exampleModalLabel">Select File</h1>
        		<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      		</div>
      		<div class="modal-body">
			  	{{if $writefiles}}
				<button id="wall-file-upload-1" class="btn btn-labeled btn-primary" data-bs-dismiss="modal" title="{{$attach}}"><i id="wall-file-upload-icon-1" class="fa fa-upload jot-icons me-1"></i>Upload
				</button>
				{{/if}}
				
	  			{{if $embedPhotos}}
				<button class="btn btn-labeled btn-success float-end" data-bs-dismiss="modal" href="#" onclick="initializeEmbedPhotoDialog(); return false;"><i class="fa fa-file-o jot-icons me-1"></i>Embed an existing File</button>
				{{/if}}
      		</div>
      		<div class="modal-footer">
        		<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        	</div>
    	</div>
  	</div>
</div>



<!-- end the new modal here -->

{{if $content || $attachment || $expanded}}
<script>initEditor();</script>
{{/if}}
