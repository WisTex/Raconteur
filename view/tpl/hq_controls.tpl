<div class="widget">
	<h3>{{$title}}</h3>
	<ul class="nav nav-pills flex-column">
		{{foreach $menu as $m}}
		<li class="nav-item"><a href="{{$m.href}}" id="{{$m.id}}" class="nav-link{{if $m.class}} {{$m.class}}{{/if}}">{{$m.label}}</a></li>
		{{/foreach}}
	</ul>
</div>
