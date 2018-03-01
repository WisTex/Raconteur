<div class="widget">
<h3>{{$title}}</h3>
{{if $options}}
<ul class="nav nav-pills flex-column">
{{foreach $options as $x}}
	{{if is_array($x) }}
		{{foreach $x as $y => $z}}
		<li class="nav-item"><a href="{{$y}}" class="nav-link">{{$z}}</a></li>
		{{/foreach}}
	{{else}}
		<div><strong>{{$x}}</strong></div>
	{{/if}}
{{/foreach}}
</ul>
{{/if}}
</div>

