<div class="directory-item{{if $entry.safe}} safe{{/if}}" id="directory-item-{{$entry.hash}}" >
	<div class="section-subtitle-wrapper clearfix">
		<div class="float-end">
			{{if $entry.ignlink}}
			<a class="directory-ignore btn btn-warning btn-sm" href="{{$entry.ignlink}}"> {{$entry.ignore_label}}</a>
			{{/if}}
			{{if $entry.censor}}
			<a class="directory-censor btn btn-danger btn-sm" href="{{$entry.censor}}"> {{$entry.censor_label}}</a>
			{{/if}}
			{{if $entry.connect}}
			<a class="btn btn-success btn-sm" href="{{$entry.connect}}"><i class="fa fa-plus connect-icon"></i> {{$entry.connect_label}}</a>
			{{else}}
			<button class="btn btn-warning btn-sm" disabled><i class="fa fa-lock fa-fw connect-icon"></i></button>
			{{/if}}
		</div>
		<h3><a href='{{$entry.profile_link}}' >{{$entry.name}}</a></h3>
	</div>
	<div class="section-content-tools-wrapper directory-collapse">
		<div class="contact-photo-wrapper" id="directory-photo-wrapper-{{$entry.hash}}" >
			<!--div class="contact-photo" id="directory-photo-{{$entry.hash}}" -->
				<a href="{{$entry.profile_link}}" class="directory-profile-link" id="directory-profile-link-{{$entry.hash}}" >
					<img class="directory-photo-img" src="{{$entry.photo}}" height="80" width="80" alt="{{$entry.alttext}}" title="{{$entry.alttext}}" >
				</a>
			<!--/div-->
		</div>
		<div class="contact-info">

			{{if $entry.network}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$entry.network_label}}</span> {{$entry.network}}
			</div>
			{{/if}}

			{{if $entry.version}}
			<div id="dir-common" class="contact-info-element">
				<span class="contact-info-label">{{$entry.version_label}}</span> {{$entry.version}}
			</div>
			{{/if}}

			{{if $entry.updated}}
				<div class="contact-info-element">
					<span class="contact-info-label">{{$entry.updated_label}}</span> {{$entry.updated}}
				</div>
			{{/if}}

			{{if $entry.access}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$entry.access_label}}</span> {{$entry.access}}
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
	</div>
<hr>
</div>
