<div style="width:8rem;height:10rem;margin:5px;float:left;padding:5px;overflow:hidden;display:inline-block;">
<a href="{{$photo.link}}" id="photo-top-photo-link-{{$photo.id}}" title="{{$photo.title}}">
	{{if $photo.src}}
	<div style="width:8rem;height:8rem;">
	<img style="width:8rem;max-width:8rem;max-height:8rem;vertical-align: bottom;" src="{{$photo.src}}" alt="{{if $photo.album.name}}{{$photo.album.name}}{{elseif $photo.desc}}{{$photo.desc}}{{elseif $photo.alt}}{{$photo.alt}}{{else}}{{$photo.unknown}}{{/if}}" title="{{$photo.title}}" id="photo-top-photo-{{$photo.id}}" />
	</div>
	{{else}}
	<div style="width:8rem;height:8rem;">
	<i class="fa fa-fw {{$photo.icon}}" style="font-size:5rem;vertical-align:bottom;"></i>
	</div>
	{{/if}}
	<div>
	{{$photo.filename}}
	</div>
</a>
</div>
<div class="clear"></div>
