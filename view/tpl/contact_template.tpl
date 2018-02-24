<div class="contact-entry-wrapper" id="contact-entry-wrapper-{{$contact.id}}" >
	<div class="contact-entry-photo-wrapper" >
		<a href="{{$contact.link}}" title="{{$contact.img_hover}}" ><img class="contact-block-img" src="{{$contact.thumb}}" alt="{{$contact.name}}" /></a>
		{{if $contact.oneway}}
		<i class="fa fa-fw fa-minus-circle oneway-overlay text-danger"></i>
		{{/if}}
	</div>
	<div class="contact-entry-photo-end" ></div>
	<div class="contact-entry-name" id="contact-entry-name-{{$contact.id}}" >{{$contact.name}}</div>
	<div class="contact-entry-end" ></div>
</div>
