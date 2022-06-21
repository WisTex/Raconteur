<div class="directory-item{{if $entry.safe}} safe{{/if}}" id="directory-item-{{$entry.hash}}" >
	<hr>
	<div class="section-subtitle-wrapper clearfix">
		<div class="pull-right">
			{{if $entry.ignlink}}
			<a class="directory-ignore btn btn-warning btn-sm" href="{{$entry.ignlink}}"> {{$entry.ignore_label}}</a>
			{{/if}}
			{{if $entry.censor}}
			<a class="directory-censor btn btn-danger btn-sm" href="{{$entry.censor}}"> {{$entry.censor_label}}</a>
			{{/if}}
			{{if $entry.connect}}
			<a class="btn btn-success btn-sm" href="{{$entry.connect}}"><i class="fa fa-plus connect-icon"></i> {{$entry.conn_label}}</a>
			{{/if}}
		</div>
		<h3>{{if $entry.type == 2}}<i class="fa fa-tags" title="{{$entry.collections_label}}"></i>&nbsp;{{elseif $entry.type == 1}}<i class="fa fa-comments-o" title="{{$entry.forum_label}}"></i>&nbsp;{{/if}}<a href='{{$entry.profile_link}}' >{{$entry.name}}</a>{{if $entry.online}}&nbsp;<i class="fa fa-asterisk online-now" title="{{$entry.online}}"></i>{{/if}}</h3>
	</div>
	<div class="section-content-tools-wrapper directory-collapse">
		<div class="contact-photo-wrapper" id="directory-photo-wrapper-{{$entry.hash}}" >
			<div class="contact-photo" id="directory-photo-{{$entry.hash}}" >
				<a href="{{$entry.profile_link}}" class="directory-profile-link" id="directory-profile-link-{{$entry.hash}}" >
					<img class="directory-photo-img" src="{{$entry.photo}}" alt="{{$entry.alttext}}" title="{{$entry.alttext}}" />
				</a>
			</div>
		</div>
		<div class="contact-info">

			{{if $entry.network}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$entry.network_label}}</span> {{$entry.network}}
			</div>
			{{/if}}

			{{if $entry.common_friends}}
			<div id="dir-common" class="contact-info-element">
				<span class="contact-info-label">{{$entry.common_label}}</span> {{$entry.common_count}}
			</div>
			{{/if}}

			{{if $entry.pdesc}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$entry.pdesc_label}}</span> {{$entry.pdesc}}
			</div>
			{{/if}}

			{{if $entry.age}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$entry.age_label}}</span> {{$entry.age}}
			</div>
			{{/if}}

			{{if $entry.location}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$entry.location_label}}</span> {{$entry.location}}
			</div>
			{{/if}}

			{{if $entry.hometown}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$entry.hometown_label}}</span> {{$entry.hometown}}
			</div>
			{{/if}}

			{{if $entry.homepage}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$entry.homepage}}</span> {{$entry.homepageurl}}
			</div>
			{{/if}}

			{{if $entry.kw}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$entry.kw}}</span> {{$entry.keywords}}
			</div>
			{{/if}}

			{{if $entry.about}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$entry.about_label}}</span> {{$entry.about}}
			</div>
			{{/if}}
		</div>
        {{if $entry.cover}}
        <img class="directory-photo-cover" src="{{$entry.cover}}" alt="{{$entry.altcover}}" style="margin-top: 1rem; width: 100%; maxwidth: 100%;" title={{$entry.altcover}}" />
        {{/if}}
	</div>
</div>
