{{if $notifications}}
<script>
	var notifications_parent;

	$(document).ready(function() {
		notifications_parent = $('#notifications_wrapper')[0].parentElement.id;
		$('.notifications-btn').click(function() {
			if($('#notifications_wrapper').hasClass('fs'))
				$('#notifications_wrapper').prependTo('#' + notifications_parent);
			else
				$('#notifications_wrapper').prependTo('section');

			$('#notifications_wrapper').toggleClass('fs');
			if($('#navbar-collapse-2').hasClass('show')){
				$('#navbar-collapse-2').removeClass('show');
			}
		});

		window.onpopstate = function(e) {
			if(e.state !== null)
				getData(e.state.b64mid, '');
		};
	});

	{{if $module == 'display' || $module == 'hq' || $startpage == 'hq'}}
	$(document).on('click', '.notification', function(e) {
		var b64mid = $(this).data('b64mid');
		var notify_id = $(this).data('notify_id');
		var path = $(this)[0].pathname.substr(1,7);
		var stateObj = { b64mid: b64mid };

		if(b64mid === 'undefined' && notify_id === 'undefined')
			return;

		{{if $module != 'hq' && $startpage == 'hq'}}
			e.preventDefault();
			if(typeof notify_id !== 'undefined' && notify_id !== 'undefined') {
				$.post(
					"hq",
					{
						"notify_id" : notify_id
					}
				);
			}
			window.location.href = 'hq/' + b64mid;
			return;
		{{else}}
			{{if $module == 'display'}}
			history.pushState(stateObj, '', 'display/' + b64mid);
			{{/if}}

			{{if $module == 'hq'}}
			history.pushState(stateObj, '', 'hq/' + b64mid);
			{{/if}}

			{{if $module == 'hq'}}
			if(b64mid !== 'undefined') {
			{{else}}
			if(path === 'display' && b64mid) {
			{{/if}}
				e.preventDefault();

				if(! page_load) {
					getData(b64mid, notify_id);
				}

				if($('#notifications_wrapper').hasClass('fs'))
					$('#notifications_wrapper').prependTo('#' + notifications_parent).removeClass('fs');
			}
		{{/if}}
	});
	{{/if}}

	{{foreach $notifications as $notification}}
	{{if $notification.filter}}
	$(document).on('click', '#tt-{{$notification.type}}-only', function(e) {
		e.preventDefault();
		$('#nav-{{$notification.type}}-menu [data-thread_top=false]').toggle();
		$(this).toggleClass('active sticky-top');
	});
	$(document).on('click ', '#cn-{{$notification.type}}-input-clear', function(e) {
		$('#cn-{{$notification.type}}-input').val('');
		$('#cn-{{$notification.type}}-only').removeClass('active sticky-top');
		$("#nav-{{$notification.type}}-menu .notification").removeClass('d-none');
		$('#cn-{{$notification.type}}-input-clear').addClass('d-none');
	});
	$(document).on('input', '#cn-{{$notification.type}}-input', function(e) {
		var val = $('#cn-{{$notification.type}}-input').val().toString().toLowerCase();

		if(val) {
			$('#cn-{{$notification.type}}-only').addClass('active sticky-top');
			$('#cn-{{$notification.type}}-input-clear').removeClass('d-none');
		}
		else {
			$('#cn-{{$notification.type}}-only').removeClass('active sticky-top');
			$('#cn-{{$notification.type}}-input-clear').addClass('d-none');
		}

		$("#nav-{{$notification.type}}-menu .notification").each(function(i, el){
			var cn = $(el).data('contact_name').toString().toLowerCase();

			if(cn.indexOf(val) === -1)
				$(this).addClass('d-none');
			else
				$(this).removeClass('d-none');
		});
	});
	{{/if}}
	{{/foreach}}

	function getData(b64mid, notify_id) {
		$('.thread-wrapper').remove();
		bParam_mid = b64mid;
		mode = 'replace';
		page_load = true;
		{{if $module == 'hq'}}
		liveUpdate(notify_id);
		{{/if}}
		{{if $module == 'display'}}
		liveUpdate();
		{{/if}}
	}
</script>

<div id="notifications_wrapper">
	<div id="no_notifications" class="d-xl-none">
		{{$no_notifications}}<span class="jumping-dots"><span class="dot-1">.</span><span class="dot-2">.</span><span class="dot-3">.</span></span>
	</div>
	<div id="nav-notifications-template" rel="template">
		<a class="list-group-item clearfix notification {5}" href="{0}" title="{2} {3}" data-b64mid="{6}" data-notify_id="{7}" data-thread_top="{8}" data-contact_name="{2}">
			<img class="menu-img-3" data-src="{1}">
			<span class="contactname">{2}</span>
			<span class="dropdown-sub-text">{3}<br>{4}</span>
		</a>
	</div>
	<div id="notifications" class="navbar-nav">
		{{foreach $notifications as $notification}}
		<div class="collapse {{$notification.type}}-button">
			<a class="list-group-item notification-link" href="#" title="{{$notification.title}}" data-target="#nav-{{$notification.type}}-sub" data-toggle="collapse" data-type="{{$notification.type}}">
				<i class="fa fa-fw fa-{{$notification.icon}}"></i> {{$notification.label}}
				<span class="float-right badge badge-{{$notification.severity}} {{$notification.type}}-update"></span>
			</a>
			<div id="nav-{{$notification.type}}-sub" class="collapse notification-content" data-parent="#notifications" data-type="{{$notification.type}}">
				{{if $notification.viewall}}
				<a class="list-group-item text-dark" id="nav-{{$notification.type}}-see-all" href="{{$notification.viewall.url}}">
					<i class="fa fa-fw fa-external-link"></i> {{$notification.viewall.label}}
				</a>
				{{/if}}
				{{if $notification.markall}}
				<div class="list-group-item cursor-pointer" id="nav-{{$notification.type}}-mark-all" onclick="markRead('{{$notification.type}}'); return false;">
					<i class="fa fa-fw fa-check"></i> {{$notification.markall.label}}
				</div>
				{{/if}}
				{{if $notification.filter}}
				<div class="list-group-item cursor-pointer" id="tt-{{$notification.type}}-only">
					<i class="fa fa-fw fa-filter"></i> {{$notification.filter.posts_label}}
				</div>
				<div class="list-group-item clearfix notifications-textinput" id="cn-{{$notification.type}}-only">
					<div class="text-muted notifications-textinput-filter"><i class="fa fa-fw fa-filter"></i></div>
					<input id="cn-{{$notification.type}}-input" type="text" class="form-control form-control-sm" placeholder="{{$notification.filter.name_label}}">
					<div id="cn-{{$notification.type}}-input-clear" class="text-muted notifications-textinput-clear d-none"><i class="fa fa-times"></i></div>
				</div>
				{{/if}}
				<div id="nav-{{$notification.type}}-menu" class="">
					{{$loading}}<span class="jumping-dots"><span class="dot-1">.</span><span class="dot-2">.</span><span class="dot-3">.</span></span>
				</div>
			</div>
		</div>
		{{/foreach}}
	</div>
</div>
{{/if}}
