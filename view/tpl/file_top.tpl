<a href="{{$photo.link}}" id="photo-top-photo-link-{{$photo.id}}" title="{{$photo.title}}">
	{{if $photo.src}}
	<div style="width:5rem;height:5rem;">
	<img style="width:5rem;max-width:5rem;" src="{{$photo.src}}" alt="{{if $photo.album.name}}{{$photo.album.name}}{{elseif $photo.desc}}{{$photo.desc}}{{elseif $photo.alt}}{{$photo.alt}}{{else}}{{$photo.unknown}}{{/if}}" title="{{$photo.title}}" id="photo-top-photo-{{$photo.id}}" />
	</div>
	{{else}}
	<div style="width:5rem;height:5rem;">
	<i class="fa fa-fw {{$photo.icon}}" style="font-size:3rem;"></i>
	</div>
	{{/if}}
</a>

