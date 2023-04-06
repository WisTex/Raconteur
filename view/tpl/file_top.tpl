<div class="cloud-icon tiles cloud-icon-modal" >
	<a href="{{$photo.link}}" id="photo-top-photo-link-{{$photo.id}}" title="{{$photo.title}}">
	{{if $photo.src}}
	<img src="{{$photo.src}}" alt="{{if $photo.album.name}}{{$photo.album.name}}{{elseif $photo.desc}}{{$photo.desc}}{{elseif $photo.alt}}{{$photo.alt}}{{else}}{{$photo.unknown}}{{/if}}" title="{{$photo.title}}" id="photo-top-photo-{{$photo.id}}" />
	{{else}}
	<div class="cloud-icon-container" id="photo-top-photo-link-{{$photo.id}}">
	<i id="photo-top-photo-{{$photo.id}}" class="fa fa-fw {{$photo.icon}}" ></i>
	</div>
	{{/if}}

	</a>

	<div class="cloud-title">
		<a href="{{$photo.link}}" title="{{$photo.filename}}">{{$photo.filename}}</a>
	</div>
</div>
