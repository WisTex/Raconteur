		<script>
			var auto_save_draft = {{$auto_save_draft}};
		</script>
		{{if $threaded}}
		<div class="comment-wwedit-wrapper threaded" id="comment-edit-wrapper-{{$id}}" style="display: block;">
		{{else}}
		<div class="comment-wwedit-wrapper" id="comment-edit-wrapper-{{$id}}" style="display: block;">
		{{/if}}
			<form class="comment-edit-form" style="display: block;" id="comment-edit-form-{{$id}}" action="item" method="post" onsubmit="post_comment({{$id}}); return false;">
				<input type="hidden" name="type" value="{{$type}}" />
				<input type="hidden" name="profile_uid" value="{{$profile_uid}}" />
				<input type="hidden" name="parent" value="{{$parent}}" />
				<input type="hidden" name="return" value="{{$return_path}}" />
				<input type="hidden" name="jsreload" value="{{$jsreload}}" />
				<input type="hidden" name="preview" id="comment-preview-inp-{{$id}}" value="0" />
				<input type="hidden" name="draft" id="comment-draft-{{$id}}" value="0" />
				<input type="hidden" name="hidden_mentions" id="hidden-mentions-{{$id}}" value="" />	
				{{if $anoncomments && ! $observer}}
				<div id="comment-edit-anon-{{$id}}" style="display: none;" >
					{{include file="field_input.tpl" field=$anonname}}
					{{include file="field_input.tpl" field=$anonmail}}
					{{include file="field_input.tpl" field=$anonurl}}
					{{$anon_extras}}
				</div>
				{{/if}}
				<textarea id="comment-edit-text-{{$id}}" class="comment-edit-text  {{if $top}}toplevel{{/if}}" placeholder="{{$comment}}" name="body" ondragenter="linkdropper(event);" ondragleave="linkdropexit(event);" ondragover="linkdropper(event);" ondrop="linkdrop(event);" ></textarea>
				<div id="comment-tools-{{$id}}" class="pt-2 comment-tools">
					<div id="comment-edit-bb-{{$id}}" class="btn-toolbar float-start">
						{{if $feature_markup}}
						<div class="btn-group mr-2">
							<button class="btn btn-outline-secondary btn-sm" title="{{$edbold}}" onclick="insertbbcomment('{{$comment}}','b', {{$id}}); return false;">
								<i class="fa fa-bold comment-icon"></i>
							</button>
							<button class="btn btn-outline-secondary btn-sm" title="{{$editalic}}" onclick="insertbbcomment('{{$comment}}','i', {{$id}}); return false;">
								<i class="fa fa-italic comment-icon"></i>
							</button>
							<button class="btn btn-outline-secondary btn-sm" title="{{$eduline}}" onclick="insertbbcomment('{{$comment}}','u', {{$id}}); return false;">
								<i class="fa fa-underline comment-icon"></i>
							</button>
							<button class="btn btn-outline-secondary btn-sm" title="{{$edquote}}" onclick="insertbbcomment('{{$comment}}','quote', {{$id}}); return false;">
								<i class="fa fa-quote-left comment-icon"></i>
							</button>
							<button class="btn btn-outline-secondary btn-sm" title="{{$edcode}}" onclick="insertbbcomment('{{$comment}}','code', {{$id}}); return false;">
								<i class="fa fa-terminal comment-icon"></i>
							</button>
						</div>
						{{/if}}
						<div class="btn-group mr-2">
							{{if $can_upload}}
							<button class="btn btn-outline-secondary btn-sm" title="{{$edatt}}" onclick="insertCommentAttach('{{$comment}}',{{$id}}); return false;">
								<i class="fa fa-paperclip comment-icon"></i>
							</button>
							{{/if}}
							<button class="btn btn-outline-secondary btn-sm" title="{{$edurl}}" onclick="insertCommentURL('{{$comment}}',{{$id}}); return false;">
								<i class="fa fa-link comment-icon"></i>
							</button>
						</div>
						{{if $feature_encrypt}}
						<div class="btn-group mr-2">
							<button class="btn btn-outline-secondary btn-sm" title="{{$encrypt}}" onclick="hz_encrypt('{{$cipher}}','#comment-edit-text-' + '{{$id}}'); return false;">
								<i class="fa fa-key comment-icon"></i>
							</button>
						</div>
						{{/if}}
						{{$comment_buttons}}
					</div>
					<div class="btn-group float-end" id="comment-edit-submit-wrapper-{{$id}}">
						{{if $preview}}
						<button id="comment-edit-presubmit-{{$id}}" class="btn btn-outline-secondary btn-sm" onclick="preview_comment({{$id}}); return false;" title="{{$preview}}">
							<i class="fa fa-eye comment-icon" ></i>
						</button>
						{{/if}}
						{{if $save}}
						<button class="btn btn-sm {{if $isdraft}} btn-primary{{else}} btn-outline-secondary{{/if}}" onclick="save_draft_comment({{$id}});return false;" title="{{$save}}">
							<i class="fa fa-floppy-o comment-icon" ></i>
						</button>
						{{/if}}						
						<button id="comment-edit-submit-{{$id}}" class="btn btn-primary btn-sm" type="submit" name="button-submit" onclick="post_comment({{$id}}); return false;">{{$submit}}</button>
					</div>
					{{if $reset}}
					<div class="btn-group float-end" id="comment-edit-reset-wrapper-{{$id}}">
						<button id="comment-reset-{{$id}}" class="btn btn-outline-secondary btn-sm comment-reset" title="{{$reset}}" onclick="commentCancel({{$id}}); return false;"><i class="fa fa-close fa-fw drop-icons"></i></button>
					</div>
					{{/if}}
				</div>
				<div class="clear"></div>
			</form>
		</div>
		<div id="comment-edit-preview-{{$id}}" class="comment-edit-preview mt-4"></div>
