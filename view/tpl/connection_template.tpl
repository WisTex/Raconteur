<div id="contact-entry-wrapper-{{$contact.id}}" class="directory-item">
	<div class="section-subtitle-wrapper clearfix">
		<div class="float-end">
			{{if $contact.approve && $contact.ignore}}
			<form action="connedit/{{$contact.id}}" method="post" >
			<button type="submit" class="btn btn-success btn-sm" name="pending" value="1" title="{{$contact.approve_hover}}"><i class="fa fa-check"></i> {{$contact.approve}}</button>

			<a href="connedit/{{$contact.id}}/ignore" class="btn btn-warning btn-sm" title="{{$contact.ignore_hover}}"><i class="fa fa-ban"></i> {{$contact.ignore}}</a>

			{{/if}}
			{{if $contact.allow_delete}}
			<a href="#" class="btn btn-danger btn-sm contact-delete-btn" title="{{$contact.delete_hover}}" onclick="dropItem('{{$contact.deletelink}}', '#contact-entry-wrapper-{{$contact.id}}'); return false;"><i class="fa fa-trash-o"></i> {{$contact.delete}}</a>
			{{/if}}
			<a href="{{$contact.link}}" class="btn btn-outline-secondary btn-sm" title="{{$contact.edit_hover}}"><i class="fa fa-pencil"></i> {{$contact.edit}}</a>
			{{if $contact.approve}}
			</form>
			{{/if}}
		</div>
		<h3>{{if $contact.channel_type == 2}}<i class="fa fa-tags"></i>&nbsp;{{elseif $contact.channel_type == 1}}<i class="fa fa-comments-o"></i>&nbsp;{{/if}}<a href="{{$contact.url}}" title="{{$contact.img_hover}}" >{{$contact.name}}</a>{{if $contact.phone}}&nbsp;<a class="btn btn-outline-secondary btn-sm" href="tel:{{$contact.phone}}" title="{{$contact.call}}"><i class="fa fa-phone connphone"></i></a>{{/if}}</h3>
	</div>
	<div class="section-content-tools-wrapper">
		<div class="contact-photo-wrapper" >
			<!--a href="{{$contact.url}}" title="{{$contact.img_hover}}" -->
				<img class="directory-photo-img contact-photo-img fakelink {{if $contact.classes}}{{$contact.classes}}{{/if}}" src="{{$contact.thumb}}" alt="{{$contact.name}}" data-bs-toggle="dropdown" />
				{{if $contact.author_menu}}
					<i class="fa fa-caret-down contact-photo-caret cursor-pointer" data-bs-toggle="dropdown"></i>
					<div class="dropdown-menu">
						<img src="{{$contact.large_avatar}}" style="width: 200px; height: 200px;" id="wall-item-popup-photo-{{$contact.id}}" alt="{{$contact.name}}" />
						<div style="margin-top: 20px;">
							<hr>
							{{foreach $contact.author_menu as $mitem}}
                                {{if $mitem.input}}
                                <input style="margin-left: 5px; width: 75%;" id="cti-{{$mitem.id}}" class="float-start" type="text" ><button style="margin-left: 3px;" class="btn btn-sm float-start" onclick="window.location.href='{{$mitem.href}}'.replace('{}',encodeURIComponent($('#cti-{{$mitem.id}}').val()));"><i class="fa fa-fw fa-search" ></i></button>
                                {{else}}
								<a class="dropdown-item" {{if $mitem.href}}href="{{$mitem.href}}"{{/if}} {{if $mitem.action}}onclick="{{$mitem.action}}"{{/if}} {{if $mitem.title}}title="{{$mitem.title}}"{{/if}} >{{$mitem.title}}</a>
                                {{/if}}
							{{/foreach}}
						</div>
					</div>
				{{/if}}
			<!--/a -->
			{{if $contact.oneway}}
			<i class="fa fa-fw fa-minus-circle oneway-overlay text-danger"></i>
			{{/if}}
		</div>
		<div class="contact-info">
			{{if $contact.status}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$contact.status_label}}:</span> {{$contact.status}}
			</div>
			{{/if}}
			{{if $contact.connected}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$contact.connected_label}}:</span> <span class="autotime" title="{{$contact.connected}}"></span>
			</div>
			{{/if}}
			{{if $contact.webbie}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$contact.webbie_label}}:</span> {{$contact.webbie}}
			</div>
			{{/if}}
			{{if $contact.network}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$contact.network_label}}:</span> {{$contact.network}} - <a href="{{$contact.recentlink}}">{{$contact.recent_label}}</a>
			</div>
			{{/if}}
		</div>
	</div>
<hr>
</div>

