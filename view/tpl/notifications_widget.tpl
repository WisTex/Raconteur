<script>
	var notifications_parent;
	$(document).ready(function() {
		notifications_parent = $('#notifications_wrapper')[0].parentElement.id;
		$('#notifications-btn').click(function() {
			if($('#notifications_wrapper').hasClass('fs'))
				$('#notifications_wrapper').prependTo('#' + notifications_parent);
			else
				$('#notifications_wrapper').prependTo('section');

			$('#notifications_wrapper').toggleClass('fs');
			if($('#navbar-collapse-2').hasClass('show')){
				$('#navbar-collapse-2').removeClass('show');
			}
		});
	});

	{{if $module == 'display' || $module == 'hq'}}
	$(document).on('click', '.notification', function(e) {
		var b64mid = $(this).data('b64mid');
		var notify_id = $(this).data('notify_id');
		var path = $(this)[0].pathname.substr(1,7);

		console.log(path);

		{{if $module == 'hq'}}
		if(b64mid !== 'undefined' && path !== 'pubstre') {
		{{else}}
		if(path === 'display' && b64mid) {
		{{/if}}
			e.preventDefault();
			e.stopPropagation();

			$('.thread-wrapper').remove();

			if(! page_load)
				$(this).fadeOut();

			bParam_mid = b64mid;
			mode = 'replace';
			page_load = true;
			{{if $module == 'hq'}}
			hqLiveUpdate(notify_id);
			{{else}}
			liveUpdate();
			{{/if}}

			if($('#notifications_wrapper').hasClass('fs'))
				$('#notifications_wrapper').prependTo('#' + notifications_parent).removeClass('fs');
		}
	});
	{{/if}}
</script>


{{if $notifications}}
<div id="notifications_wrapper">
	<div id="notifications" class="navbar-nav" data-children=".nav-item">
		<div id="nav-notifications-template" rel="template">
			<a class="list-group-item clearfix notification {5}" href="{0}" title="{2} {3}" data-b64mid="{6}" data-notify_id="{7}">
				<img class="menu-img-3" data-src="{1}">
				<span class="contactname">{2}</span>
				<span class="dropdown-sub-text">{3}<br>{4}</span>
			</a>
		</div>
		{{foreach $notifications as $notification}}
		<div class="collapse {{$notification.type}}-button">
			<a class="list-group-item" href="#nav-{{$notification.type}}-menu" title="{{$notification.title}}" data-toggle="collapse" data-parent="#notifications" rel="#nav-{{$notification.type}}-menu">
				<i class="fa fa-fw fa-{{$notification.icon}}"></i> {{$notification.label}}
				<span class="float-right badge badge-{{$notification.severity}} {{$notification.type}}-update"></span>
			</a>
			<div id="nav-{{$notification.type}}-menu" class="collapse notification-content" rel="{{$notification.type}}">
				{{if $notification.viewall}}
				<a class="list-group-item text-dark" id="nav-{{$notification.type}}-see-all" href="{{$notification.viewall.url}}">
					<i class="fa fa-fw fa-external-link"></i> {{$notification.viewall.label}}
				</a>
				{{/if}}
				{{if $notification.markall}}
				<a class="list-group-item text-dark" id="nav-{{$notification.type}}-mark-all" href="{{$notification.markall.url}}" onclick="markRead('{{$notification.type}}'); return false;">
					<i class="fa fa-fw fa-check"></i> {{$notification.markall.label}}
				</a>
				{{/if}}
				{{$loading}}
			</div>
		</div>
		{{/foreach}}
	</div>
</div>
{{/if}}
