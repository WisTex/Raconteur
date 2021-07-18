<div id="pinned-wrapper-{{$id}}" class="thread-wrapper toplevel_item generic-content-wrapper h-entry" data-b64mids='{{$mids}}'>
	<div class="wall-item-outside-wrapper" id="pinned-item-outside-wrapper-{{$id}}">
		<div class="clearfix wall-item-content-wrapper" id="pinned-item-content-wrapper-{{$id}}">
			{{if $photo}}
				<div class="wall-photo-item" id="pinned-photo-item-{{$id}}">
					{{$photo}}
				</div>
			{{/if}}
			{{if $event}}
				<div class="wall-event-item" id="pinned-event-item-{{$id}}">
					{{$event}}
				</div>
			{{/if}}
			{{if $title && !$event}}
				<div class="p-2{{if $is_new}} bg-primary text-white{{/if}} wall-item-title h3{{if !$photo}} rounded-top{{/if}}" id="pinned-item-title-{{$id}}">
					{{if $title_tosource}}
						{{if $plink}}
							<a href="{{$plink.href}}" title="{{$title}} ({{$plink.title}})" rel="nofollow">
						{{/if}}
					{{/if}}
					{{$title}}
					{{if $title_tosource}}
						{{if $plink}}
							</a>
						{{/if}}
					{{/if}}
				</div>
				{{if ! $is_new}}
					<hr class="m-0">
				{{/if}}
			{{/if}}
			<div class="p-2 clearfix wall-item-head{{if !$title && !$event && !$photo}} rounded-top{{/if}}{{if $is_new && !$event}} wall-item-head-new{{/if}}">
				<span class="float-right wall-item-pinned" title="{{$pinned}}"><i class="fa fa-thumb-tack">&nbsp;</i></span>
				<div class="wall-item-info" id="pinned-item-info-{{$id}}" >
					<div class="wall-item-photo-wrapper{{if $owner_url}} wwfrom{{/if}} h-card p-author" id="pinned-item-photo-wrapper-{{$id}}">
						<img src="{{$thumb}}" class="fakelink wall-item-photo u-photo p-name" id="pinned-item-photo-{{$id}}" alt="{{$name}}" data-toggle="dropdown" />
						{{if $thread_author_menu}}
							<i class="fa fa-caret-down wall-item-photo-caret cursor-pointer" data-toggle="dropdown"></i>
							<div class="dropdown-menu">
								{{foreach $thread_author_menu as $mitem}}
									<a class="dropdown-item" {{if $mitem.href}}href="{{$mitem.href}}"{{/if}} {{if $mitem.action}}onclick="{{$mitem.action}}"{{/if}} {{if $mitem.title}}title="{{$mitem.title}}"{{/if}} >{{$mitem.title}}</a>
								{{/foreach}}
							</div>
						{{/if}}
					</div>
				</div>
				<div class="wall-item-author">
					<a href="{{$profile_url}}" title="{{$linktitle}}" class="wall-item-name-link u-url"><span class="wall-item-name" id="pinned-item-name-{{$id}}" >{{$name}}</span></a>{{if $owner_url}}&nbsp;{{$via}}&nbsp;<a href="{{$owner_url}}" title="{{$olinktitle}}" class="wall-item-name-link"><span class="wall-item-name" id="pinned-item-ownername-{{$id}}">{{$owner_name}}</span></a>{{/if}}
				</div>
				<div class="wall-item-ago"  id="pinned-item-ago-{{$id}}">
					{{if $verified}}<i class="fa fa-check item-verified" title="{{$verified}}"></i>&nbsp;{{elseif $forged}}<i class="fa fa-exclamation item-forged" title="{{$forged}}"></i>&nbsp;{{/if}}{{if $location}}<span class="wall-item-location p-location" id="pinned-item-location-{{$id}}">{{$location}},&nbsp;</span>{{/if}}<span class="autotime" title="{{$isotime}}"><time class="dt-published" datetime="{{$isotime}}">{{$localtime}}</time>{{if $editedtime}}&nbsp;{{$editedtime}}{{/if}}{{if $expiretime}}&nbsp;{{$expiretime}}{{/if}}</span>{{if $editedtime}}&nbsp;<i class="fa fa-pencil"></i>{{/if}}&nbsp;{{if $app}}<span class="item.app">{{$str_app}}</span>{{/if}}
				</div>
			</div>
			{{if $divider}}
				<hr class="wall-item-divider">
			{{/if}}
			{{if $body}}
				<div class="p-2 wall-item-content clearfix" id="pinned-item-content-{{$id}}">
					<div class="wall-item-body e-content" id="pinned-item-body-{{$id}}" >
						{{$body}}
					</div>
				</div>
			{{/if}}
			{{if $has_tags}}
				<div class="p-2 wall-item-tools clearfix">
					<div class="body-tags">
						<span class="tag">{{$mentions}} {{$tags}} {{$categories}} {{$folders}}</span>
					</div>
				</div>
			{{/if}}
				<div class="p-2 clearfix wall-item-tools">
					<div class="float-right wall-item-tools-right">
						<div class="btn-group">
							<div id="pinned-rotator-{{$id}}" class="spinner-wrapper">
								<div class="spinner s"></div>
							</div>
						</div>
						<div class="btn-group">
						{{if $isevent}}
							<div class="btn-group">
								<button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown" id="pinned-item-attend-menu-{{$id}}" title="{{$attend_title}}">
									<i class="fa fa-calendar-check-o"></i>
								</button>
								<div class="dropdown-menu dropdown-menu-right">
									<a class="dropdown-item" href="#" title="{{if $item.my_responses.attend}}{{$item.undo_attend}}{{else}}{{$item.attend.0}}{{/if}}" onclick="itemAddToCal({{$item.id}}); dolike({{$item.id}},{{if $item.my_responses.attend}} 'Undo/' + {{/if}} 'Accept'); return false;">
										<i class="item-act-list fa fa-check{{if $item.my_responses.attend}} ivoted{{/if}}" ></i> {{$item.attend.0}}
									</a>
									<a class="dropdown-item" href="#" title="{{if $item.my_responses.attendno}}{{$item.undo_attend}}{{else}}{{$item.attend.1}}{{/if}}" onclick="itemAddToCal({{$item.id}}), dolike({{$item.id}},{{if $item.my_responses.attendno}} 'Undo/' + {{/if}} 'Reject'); return false;">
										<i class="item-act-list fa fa-times{{if $item.my_responses.attendno}} ivoted{{/if}}" ></i> {{$item.attend.1}}
									</a>
									<a class="dropdown-item" href="#" title="{{if $item.my_responses.attendmaybe}}{{$item.undo_attend}}{{else}}{{$item.attend.2}}{{/if}}" onclick="itemAddToCal({{$item.id}}); dolike({{$item.id}},{{if $item.my_responses.attendmaybe}} 'Undo/' {{/if}} 'TentativeAccept'); return false;">
										<i class="item-act-list fa fa-question{{if $item.my_responses.attendmaybe}} ivoted{{/if}}" ></i> {{$item.attend.2}}
									</a>
								</div>
							</div>
						{{/if}}
						<div class="btn-group">
							<button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown" id="pinned-item-menu-{{$id}}">
								<i class="fa fa-cog"></i>
							</button>
							<div class="dropdown-menu dropdown-menu-right" role="menu" aria-labelledby="wall-item-menu-{{$id}}">
								{{if $share}}
									<a class="dropdown-item" href="#" onclick="jotShare({{$id}},{{$item_type}}); return false"><i class="generic-icons-nav fa fa-fw fa-retweet" title="{{$share.0}}"></i>{{$share.0}}</a>
								{{/if}}
								{{if $embed}}
									<a class="dropdown-item" href="#" onclick="jotEmbed({{$id}},{{$item_type}}); return false"><i class="generic-icons-nav fa fa-fw fa-share" title="{{$embed.0}}"></i>{{$embed.0}}</a>
								{{/if}}
								{{if $plink}}
									<a class="dropdown-item" href="{{$plink.href}}" title="{{$plink.title}}" class="u-url"><i class="generic-icons-nav fa fa-fw fa-external-link"></i>{{$plink.title}}</a>
								{{/if}}
							</div>
						</div>
					</div>
				</div>
				{{if $attachments}}
					<div class="wall-item-tools-left btn-group" id="pinned-item-tools-left-{{$id}}">
						<div class="btn-group">
							<button type="button" class="btn btn-outline-secondary btn-sm wall-item-like dropdown-toggle" data-toggle="dropdown" id="pinned-attachment-menu-{{$id}}">
								<i class="fa fa-paperclip"></i>
							</button>
							<div class="dropdown-menu">{{$attachments}}</div>
						</div>
					</div>
				{{/if}}
			</div>
		</div>
	</div>
</div>
