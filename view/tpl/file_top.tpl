<a href="{{$photo.link}}" id="photo-top-photo-link-{{$photo.id}}" title="{{$photo.title}}">
	{{if $photo.src}}
	<img src="{{$photo.src}}" alt="{{if $photo.album.name}}{{$photo.album.name}}{{elseif $photo.desc}}{{$photo.desc}}{{elseif $photo.alt}}{{$photo.alt}}{{else}}{{$photo.unknown}}{{/if}}" title="{{$photo.title}}" id="photo-top-photo-{{$photo.id}}" />
	{{else}}
	<i class=""fa fa-fw fa-{{$icon}}"></i>
	{{/if}}
</a>

