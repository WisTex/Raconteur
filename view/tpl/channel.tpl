<div class="section-subtitle-wrapper">
	<div class="pull-right">
		{{if $channel.default_links}}
		{{/if}}
		{{if $channel.delegate}}
			{{$delegated_desc}}
		{{/if}}
	</div>
	<h3>
		{{*if $selected == $channel.channel_id}}
		<i class="fa fa-circle text-success" title="{{$msg_selected}}"></i>
		{{/if*}}
		{{if $channel.delegate}}
		<i class="fa fa-arrow-circle-right" title="{{$delegated_desc}}"></i>
		{{/if}}
		{{if $channel.xchan_type == 2}}<i class="fa fa-tags" title="{{$channel.collections_label}}"></i>&nbsp;{{elseif $channel.xchan_type == 1}}<i class="fa fa-comments-o" title="{{$channel.forum_label}}"></i>&nbsp;{{/if}}
		{{if $selected != $channel.channel_id}}<a href="{{$channel.link}}" title="{{$channel.channel_name}}">{{/if}}
		{{$channel.channel_name}}
		{{if $selected != $channel.channel_id}}</a>{{/if}}
        {{if $channel.channel_system}}&nbsp;&nbsp;&nbsp;&nbsp;<span class="descriptive-text">{{$channel.msg_system}}</span>{{/if}}
	</h3>
	<div class="clear"></div>
</div>
<div class="section-content-wrapper">
	<div class="pull-left" style="width: 75%;">
	<div class="channel-photo-wrapper">
		{{if $selected != $channel.channel_id}}<a href="{{$channel.link}}" class="channel-selection-photo-link" title="{{$channel.channel_name}}">{{/if}}
			<img class="channel-photo{{if $selected == $channel.channel_id}} channel-active{{/if}}" src="{{$channel.xchan_photo_m}}" alt="{{$channel.channel_name}}" />
		{{if $selected != $channel.channel_id}}</a>{{/if}}
	</div>
	<div class="channel-notifications-wrapper">
		{{if !$channel.delegate}}
		<div class="channel-notification">
			<i class="fa fa-fw fa-user{{if $channel.intros != 0}} text-danger{{/if}}"></i>
			{{if $channel.intros != 0}}<a href='manage/{{$channel.channel_id}}/connections/ifpending'>{{/if}}{{$channel.intros|string_format:$intros_format}}{{if $channel.intros != 0}}</a>{{/if}}
		</div>
		{{/if}}
	</div>
	</div>
	{{if $channel.default_links}}
	<div class="pull-left">
		{{if $channel.default}}
		<a href="manage/{{$channel.channel_id}}/noop" class="make-default-link">
			<i class="fa fa-check-square-o"></i>&nbsp;{{$msg_default}}
		</a>
		{{else}}
		<a href="manage/{{$channel.channel_id}}/default" class="make-default-link">
			<i class="fa fa-square-o"></i>&nbsp;{{$msg_make_default}}
		</a>
		{{/if}}
		<br>
		{{if $channel.include_in_menu}}
		<a href="manage/{{$channel.channel_id}}/menu" class="channel-menu-link">
			<i class="fa fa-check-square-o"></i>&nbsp;{{$msg_no_include}}
		</a>
		{{else}}
		<a href="manage/{{$channel.channel_id}}/menu" class="channel-menu-link">
			<i class="fa fa-square-o"></i>&nbsp;{{$msg_include}}
		</a>
		{{/if}}
		<br>
	</div>
	{{/if}}
	<div class="clear"></div>
</div>
